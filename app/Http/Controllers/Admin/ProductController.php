<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    private function rules(bool $imageRequired): array
    {
        return [
            'category_id' => 'required|exists:categories,id',
            'name'        => 'required|string|max:150',
            'description' => 'required|string|max:5000',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'min_stock'   => 'nullable|integer|min:0',
            'image'       => ($imageRequired ? 'required' : 'nullable') . '|image|mimes:jpeg,png,jpg,webp|max:2048',
        ];
    }

    private function messages(): array
    {
        return [
            'category_id.required' => 'Kategori wajib dipilih.',
            'category_id.exists'   => 'Kategori yang dipilih tidak valid.',
            'name.required'        => 'Nama produk wajib diisi.',
            'description.required' => 'Deskripsi produk wajib diisi.',
            'price.required'       => 'Harga wajib diisi.',
            'price.numeric'        => 'Harga harus berupa angka.',
            'price.min'            => 'Harga tidak boleh negatif.',
            'stock.required'       => 'Stok wajib diisi.',
            'stock.integer'        => 'Stok harus berupa bilangan bulat.',
            'stock.min'            => 'Stok tidak boleh negatif.',
            'image.required'       => 'Foto produk wajib diunggah.',
            'image.image'          => 'File foto harus berupa gambar.',
            'image.mimes'          => 'Foto harus berformat JPG, PNG, atau WEBP.',
            'image.max'            => 'Ukuran foto maksimal 2 MB.',
        ];
    }

    // 1. Tampilkan Daftar Produk
    public function index()
    {
        $products = Product::with('category')->withCount('orderItems')->latest()->get();

        return view('admin.products.index', compact('products'));
    }

    // 2. Form Tambah Produk
    public function create()
    {
        $categories = Category::all();

        return view('admin.products.create', compact('categories'));
    }

    // 3. Simpan Produk Baru ke Database
    public function store(Request $request)
    {
        $request->validate($this->rules(imageRequired: true), $this->messages());

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        Product::create([
            'category_id' => $request->category_id,
            'name'        => $request->name,
            'description' => $request->description,
            'price'       => $request->price,
            'stock'       => $request->stock,
            'min_stock'   => $request->input('min_stock', 5) ?? 5,
            'image'       => $imagePath,
            'is_active'   => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.products.index')->with('success', 'Produk berhasil ditambahkan!');
    }

    // 4. Form Edit Produk
    public function edit(Product $product)
    {
        $categories = Category::all();

        return view('admin.products.edit', compact('product', 'categories'));
    }

    // 5. Update Produk di Database
    public function update(Request $request, Product $product)
    {
        $request->validate($this->rules(imageRequired: false), $this->messages());

        $imagePath = $product->image;
        if ($request->hasFile('image')) {
            // Hapus gambar lama jika ada (file bawaan di public/images tidak ikut terhapus)
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $oldStock = (int) $product->stock;
        $newStock = (int) $request->stock;

        $product->update([
            'category_id' => $request->category_id,
            'name'        => $request->name,
            'description' => $request->description,
            'price'       => $request->price,
            'stock'       => $newStock,
            'min_stock'   => $request->input('min_stock', $product->min_stock) ?? $product->min_stock,
            'image'       => $imagePath,
            'is_active'   => $request->boolean('is_active'),
        ]);

        // Perubahan stok lewat form edit tetap dicatat di riwayat stok
        if ($newStock !== $oldStock) {
            $product->stockAdjustments()->create([
                'user_id'      => auth()->id(),
                'type'         => $newStock > $oldStock ? 'add' : 'reduce',
                'quantity'     => abs($newStock - $oldStock),
                'stock_before' => $oldStock,
                'stock_after'  => $newStock,
                'reason'       => 'Perubahan stok lewat form edit produk',
            ]);
        }

        return redirect()->route('admin.products.index')->with('success', 'Produk berhasil diperbarui!');
    }

    // 6. Hapus Produk
    public function destroy(Product $product)
    {
        // order_items.product_id memakai ON DELETE RESTRICT: produk yang pernah dipesan
        // tidak bisa dihapus (sebelumnya menyebabkan error 500). Sarankan nonaktifkan.
        if ($product->hasOrderHistory()) {
            return redirect()->route('admin.products.index')
                ->with('error', 'Produk ini sudah pernah dipesan sehingga tidak bisa dihapus. Nonaktifkan saja lewat menu Edit agar tidak tampil di katalog.');
        }

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Produk berhasil dihapus!');
    }

    // 7. Aktifkan / Nonaktifkan Produk (RQ-017: satu-satunya jalan untuk produk yang sudah pernah dipesan)
    public function toggleActive(Product $product)
    {
        $product->update(['is_active' => ! $product->is_active]);

        $state = $product->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->route('admin.products.index')->with('success', "Produk {$product->name} berhasil {$state}.");
    }
}
