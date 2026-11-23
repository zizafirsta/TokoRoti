<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockAdjustment;
use Illuminate\Http\Request;

class StockController extends Controller
{
    // Daftar stok semua produk + riwayat penyesuaian terbaru
    public function index()
    {
        $products = Product::with('category')->orderBy('name')->get();
        $history = StockAdjustment::with(['product', 'user'])->latest('id')->limit(15)->get();

        return view('admin.stock.index', compact('products', 'history'));
    }

    // RQ-018 & RQ-019: tambah / kurangi stok disertai alasan, stok tidak boleh negatif
    public function adjust(Request $request, Product $product)
    {
        $data = $request->validate([
            'type'     => 'required|in:add,reduce',
            'quantity' => 'required|integer|min:1|max:100000',
            'reason'   => 'required|string|min:3|max:255',
        ], [
            'type.required'     => 'Pilih jenis penyesuaian (tambah / kurangi).',
            'type.in'           => 'Jenis penyesuaian tidak valid.',
            'quantity.required' => 'Jumlah wajib diisi.',
            'quantity.integer'  => 'Jumlah harus berupa bilangan bulat.',
            'quantity.min'      => 'Jumlah minimal 1.',
            'reason.required'   => 'Alasan penyesuaian stok wajib diisi.',
            'reason.min'        => 'Alasan minimal 3 karakter.',
        ]);

        try {
            $product->adjustStock($data['type'], (int) $data['quantity'], $data['reason'], auth()->id());
        } catch (\DomainException $e) {
            return redirect()->route('admin.stock.index')->with('error', $e->getMessage());
        }

        $verb = $data['type'] === 'add' ? 'ditambah' : 'dikurangi';

        return redirect()->route('admin.stock.index')
            ->with('success', "Stok {$product->name} berhasil {$verb} {$data['quantity']}. Stok sekarang: {$product->stock}.");
    }
}
