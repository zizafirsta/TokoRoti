<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    // 1. Tampilkan Halaman Form Checkout
    public function index()
    {
        $cart = session()->get('cart', []);
        if (empty($cart)) {
            return redirect()->route('shop.index')->with('error', 'Keranjang belanja Anda kosong.');
        }

        // Ambil harga & stok terbaru dari database supaya ringkasan selalu akurat
        $products = Product::whereIn('id', array_keys($cart))->get()->keyBy('id');

        $items = [];
        $total = 0;

        foreach ($cart as $id => $details) {
            $product = $products->get($id);
            $quantity = (int) $details['quantity'];

            if (! $product || ! $product->is_active || $product->stock < 1 || $quantity > $product->stock) {
                // Halaman keranjang otomatis menyesuaikan jumlah/menghapus produk & memberi tahu pelanggan
                return redirect()->route('cart.index')
                    ->with('error', 'Ada produk yang stoknya berubah. Silakan periksa keranjang Anda terlebih dahulu.');
            }

            $price = (int) $product->price;
            $items[] = [
                'name'      => $product->name,
                'price'     => $price,
                'quantity'  => $quantity,
                'image_url' => $product->image_url,
            ];
            $total += $price * $quantity;
        }

        $shippingFee = (int) config('toko.shipping_fee');
        $methods = Payment::availableMethods();

        return view('checkout.index', compact('items', 'total', 'shippingFee', 'methods'));
    }

    // 2. Proses Checkout & Simpan Order ke Database
    public function process(Request $request)
    {
        $request->validate([
            'fulfillment_type' => 'required|in:pickup,delivery',
            'payment_method'   => ['required', Rule::in(array_keys(Payment::availableMethods()))],
            'address'          => 'nullable|required_if:fulfillment_type,delivery|string|max:1000',
            'note'             => 'nullable|string|max:1000',
        ], [
            'payment_method.required' => 'Pilih metode pembayaran terlebih dahulu.',
            'payment_method.in'       => 'Metode pembayaran yang dipilih tidak tersedia.',
            'address.required_if'     => 'Alamat pengiriman wajib diisi untuk pesanan yang dikirim.',
        ]);

        $cart = session()->get('cart', []);
        if (empty($cart)) {
            return redirect()->route('shop.index')->with('error', 'Keranjang belanja kosong.');
        }

        DB::beginTransaction();
        try {
            // Ambil harga & stok TERBARU dari database (jangan percaya data di session),
            // dan kunci barisnya agar dua pembeli tidak bisa membeli stok terakhir bersamaan.
            $products = Product::whereIn('id', array_keys($cart))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = 0;
            $lines = [];

            foreach ($cart as $id => $details) {
                $product = $products->get($id);
                $quantity = (int) $details['quantity'];

                if (! $product || ! $product->is_active) {
                    throw new \DomainException('Ada produk di keranjang yang sudah tidak tersedia. Silakan periksa keranjang Anda.');
                }

                if ($quantity < 1 || $product->stock < $quantity) {
                    throw new \DomainException("Stok {$product->name} tidak mencukupi (tersisa {$product->stock}).");
                }

                $price = (int) $product->price;
                $subtotal += $price * $quantity;

                $lines[] = [$product, $quantity, $price];
            }

            $shippingFee = $request->fulfillment_type === 'delivery' ? (int) config('toko.shipping_fee') : 0;
            $total = $subtotal + $shippingFee;

            $invoiceNo = 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            // Menggabungkan Alamat dan Catatan
            $fullNote = $request->note;
            if ($request->fulfillment_type === 'delivery' && $request->address) {
                $fullNote = 'Alamat Pengiriman: ' . $request->address . ($request->note ? ' | Catatan: ' . $request->note : '');
            }

            $order = Order::create([
                'user_id'          => auth()->id(),
                'invoice_no'       => $invoiceNo,
                'fulfillment_type' => $request->fulfillment_type,
                'subtotal'         => $subtotal,
                'shipping_fee'     => $shippingFee,
                'discount'         => 0,
                'total'            => $total,
                'status'           => 'pending',
                'note'             => $fullNote,
            ]);

            $order->statusLogs()->create(['status' => 'pending', 'changed_by' => auth()->id()]);

            // Simpan item pesanan & kurangi stok
            foreach ($lines as [$product, $quantity, $price]) {
                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $product->id,
                    'quantity'   => $quantity,
                    'price'      => $price,
                ]);

                $product->decrement('stock', $quantity);
            }

            Payment::create([
                'order_id' => $order->id,
                'method'   => $request->payment_method,
                'amount'   => $total,
                'status'   => 'pending',
            ]);

            DB::commit();

            session()->forget('cart');

            return redirect()->route('checkout.payment', $order->id);

        } catch (\DomainException $e) {
            DB::rollBack();

            return redirect()->route('cart.index')->with('error', $e->getMessage());

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Checkout gagal: ' . $e->getMessage(), ['exception' => $e]);

            // Detail teknis hanya ditampilkan saat APP_DEBUG=true
            $detail = config('app.debug') ? ' (' . $e->getMessage() . ')' : '';

            return redirect()->back()->withInput()
                ->with('error', 'Gagal memproses pesanan. Silakan coba lagi.' . $detail);
        }
    }

    // 3. Halaman Instruksi Pembayaran (transfer bank / QRIS / bayar di tempat)
    public function payment(Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        // Pesanan yang sudah dibayar / dibatalkan tidak perlu halaman pembayaran lagi
        if ($order->status !== 'pending') {
            return redirect()->route('orders.show', $order->id)
                ->with('error', 'Pesanan ini tidak menunggu pembayaran.');
        }

        $order->load(['items.product', 'payment']);

        $banks = Payment::banks();
        $qrisUrl = Payment::qrisImageUrl();
        $whatsapp = config('toko.whatsapp');

        return view('checkout.payment', compact('order', 'banks', 'qrisUrl', 'whatsapp'));
    }

    // 4. Unggah Bukti Pembayaran (transfer bank / QRIS)
    public function uploadProof(Request $request, Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        $payment = $order->payment;

        if ($order->status !== 'pending' || ! $payment || ! $payment->needsProof()) {
            return redirect()->route('orders.show', $order->id)
                ->with('error', 'Pesanan ini tidak membutuhkan bukti pembayaran.');
        }

        $request->validate([
            'proof'      => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'payer_name' => ['nullable', 'string', 'max:100'],
        ], [
            'proof.required' => 'Pilih foto bukti pembayaran terlebih dahulu.',
            'proof.mimes'    => 'Bukti pembayaran harus berupa gambar (JPG, PNG, atau WEBP).',
            'proof.max'      => 'Ukuran bukti pembayaran maksimal 4 MB.',
            'proof.uploaded' => 'Unggahan gagal. Pastikan ukuran file tidak melebihi 4 MB.',
        ]);

        // Bukti disimpan di disk privat (storage/app/private) - hanya pemilik & admin yang bisa melihat
        $path = $request->file('proof')->store('payment-proofs', 'local');

        if ($payment->proof) {
            Storage::disk('local')->delete($payment->proof);
        }

        $payment->update([
            'proof'             => $path,
            'payer_name'        => $request->payer_name,
            'proof_uploaded_at' => now(),
        ]);

        return redirect()->route('orders.show', $order->id)
            ->with('success', 'Bukti pembayaran berhasil dikirim. Admin akan memverifikasi pesanan Anda secepatnya.');
    }
}
