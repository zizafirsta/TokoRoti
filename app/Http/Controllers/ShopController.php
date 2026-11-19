<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    // Tampilkan Halaman Katalog Utama & Filter Kategori
    public function index(Request $request)
    {
        $categories = Category::all();
        $query = Product::with('category')->where('is_active', true);

        // Filter berdasarkan kategori jika dipilih
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        $products = $query->latest()->get();

        return view('shop.index', compact('products', 'categories'));
    }

    // Tampilkan Detail Produk
    public function show($id)
    {
        $product = Product::with('category')->where('is_active', true)->findOrFail($id);

        return view('shop.show', compact('product'));
    }

    // Halaman Dashboard (sebelumnya $products tidak pernah dikirim ke view)
    public function dashboard()
    {
        $products = Product::where('is_active', true)->latest()->take(8)->get();

        return view('dashboard', compact('products'));
    }
}
