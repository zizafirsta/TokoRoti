<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /** Ringkasan isi keranjang: jumlah total barang & total harga. */
    private function summary(array $cart): array
    {
        $count = 0;
        $total = 0;

        foreach ($cart as $item) {
            $count += (int) $item['quantity'];
            $total += (int) $item['price'] * (int) $item['quantity'];
        }

        return ['cart_count' => $count, 'cart_total' => $total];
    }

    // Tampilkan Isi Keranjang
    // Data (nama, harga, stok) disegarkan dari database supaya tidak basi.
    public function index()
    {
        $cart = session()->get('cart', []);
        $notices = [];

        if ($cart) {
            $products = Product::whereIn('id', array_keys($cart))->get()->keyBy('id');

            foreach ($cart as $id => $item) {
                $product = $products->get($id);

                if (! $product || ! $product->is_active || $product->stock < 1) {
                    $notices[] = ($item['name'] ?? 'Sebuah produk') . ' sudah tidak tersedia dan dihapus dari keranjang.';
                    unset($cart[$id]);
                    continue;
                }

                if ($item['quantity'] > $product->stock) {
                    $notices[] = "Jumlah {$product->name} disesuaikan dengan stok tersedia ({$product->stock}).";
                    $item['quantity'] = $product->stock;
                }

                $cart[$id] = [
                    'name'      => $product->name,
                    'quantity'  => $item['quantity'],
                    'price'     => $product->price,
                    'image_url' => $product->image_url,
                    'stock'     => $product->stock,
                ];
            }

            session()->put('cart', $cart);
        }

        // Data untuk komponen keranjang interaktif (Alpine.js)
        $items = [];
        foreach ($cart as $id => $item) {
            $items[] = [
                'id'        => (int) $id,
                'name'      => $item['name'],
                'price'     => (int) $item['price'],
                'quantity'  => (int) $item['quantity'],
                'saved'     => (int) $item['quantity'],
                'stock'     => (int) ($item['stock'] ?? 99),
                'image_url' => $item['image_url'] ?? null,
                'show_url'  => route('shop.show', $id),
                'update_url' => route('cart.update', $id),
                'remove_url' => route('cart.remove', $id),
            ];
        }

        return view('cart.index', compact('items', 'notices'));
    }

    // Tambahkan Produk ke Keranjang (mendukung respons JSON untuk modal konfirmasi)
    public function add(Request $request, $id)
    {
        $data = $request->validate([
            'quantity' => 'nullable|integer|min:1|max:99',
        ], [
            'quantity.integer' => 'Jumlah harus berupa angka.',
            'quantity.min'     => 'Jumlah minimal 1.',
            'quantity.max'     => 'Jumlah maksimal 99 per pesanan.',
        ]);

        $product = Product::where('is_active', true)->findOrFail($id);

        if ($product->stock < 1) {
            $message = "{$product->name} sedang habis.";

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $message], 422)
                : redirect()->back()->with('error', $message);
        }

        $cart = session()->get('cart', []);
        $quantity = (int) ($data['quantity'] ?? 1);
        $current = (int) ($cart[$id]['quantity'] ?? 0);
        $wanted = $current + $quantity;
        $newQuantity = min($wanted, (int) $product->stock);
        $added = $newQuantity - $current;

        if ($added < 1) {
            $message = "Jumlah {$product->name} di keranjang sudah mencapai stok tersedia ({$product->stock}).";

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $message], 422)
                : redirect()->back()->with('error', $message);
        }

        $cart[$id] = [
            'name'      => $product->name,
            'quantity'  => $newQuantity,
            'price'     => $product->price,
            'image_url' => $product->image_url,
            'stock'     => $product->stock,
        ];

        session()->put('cart', $cart);

        $adjusted = $newQuantity < $wanted;
        $message = $adjusted
            ? "Jumlah disesuaikan dengan stok tersedia ({$product->stock})."
            : 'Roti berhasil ditambahkan ke keranjang!';

        if ($request->expectsJson()) {
            return response()->json([
                'ok'         => true,
                'message'    => $message,
                'adjusted'   => $adjusted,
                'added'      => $added,
                'in_cart'    => $newQuantity,
                'product'    => $product->name,
                'cart_url'   => route('cart.index'),
            ] + $this->summary($cart));
        }

        return redirect()->back()->with('success', $message);
    }

    // Update Jumlah Roti di Keranjang (mendukung respons JSON)
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1|max:99',
        ], [
            'quantity.required' => 'Jumlah wajib diisi.',
            'quantity.integer'  => 'Jumlah harus berupa angka.',
            'quantity.min'      => 'Jumlah minimal 1.',
            'quantity.max'      => 'Jumlah maksimal 99 per pesanan.',
        ]);

        $cart = session()->get('cart', []);

        if (! isset($cart[$id])) {
            $message = 'Produk tidak ada di keranjang.';

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $message], 404)
                : redirect()->route('cart.index')->with('error', $message);
        }

        $product = Product::find($id);

        if (! $product || ! $product->is_active || $product->stock < 1) {
            unset($cart[$id]);
            session()->put('cart', $cart);

            $message = 'Produk sudah tidak tersedia dan dihapus dari keranjang.';

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'removed' => true, 'message' => $message] + $this->summary($cart), 410)
                : redirect()->route('cart.index')->with('error', $message);
        }

        $requested = (int) $data['quantity'];
        $quantity = min($requested, (int) $product->stock);

        $cart[$id] = [
            'name'      => $product->name,
            'quantity'  => $quantity,
            'price'     => $product->price,
            'image_url' => $product->image_url,
            'stock'     => $product->stock,
        ];
        session()->put('cart', $cart);

        $adjusted = $quantity < $requested;
        $message = $adjusted
            ? "Jumlah disesuaikan dengan stok tersedia ({$product->stock})."
            : 'Keranjang berhasil diperbarui!';

        if ($request->expectsJson()) {
            return response()->json([
                'ok'       => true,
                'message'  => $message,
                'adjusted' => $adjusted,
                'quantity' => $quantity,
                'stock'    => (int) $product->stock,
                'subtotal' => (int) $product->price * $quantity,
            ] + $this->summary($cart));
        }

        return redirect()->route('cart.index')->with('success', $message);
    }

    // Hapus Produk dari Keranjang (mendukung respons JSON)
    public function remove(Request $request, $id)
    {
        $cart = session()->get('cart', []);

        if (isset($cart[$id])) {
            unset($cart[$id]);
            session()->put('cart', $cart);
        }

        $message = 'Roti berhasil dihapus dari keranjang!';

        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => $message,
                'empty'   => empty($cart),
            ] + $this->summary($cart));
        }

        return redirect()->route('cart.index')->with('success', $message);
    }
}
