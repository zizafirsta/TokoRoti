<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Akun Admin & Customer contoh (aman dijalankan berulang)
        User::updateOrCreate(
            ['email' => 'admin@tokoroti.com'],
            [
                'name'     => 'Admin Toko',
                'password' => Hash::make('password123'),
                'role'     => 'admin',
            ]
        );

        User::updateOrCreate(
            ['email' => 'customer@gmail.com'],
            [
                'name'     => 'Pelanggan Setia',
                'password' => Hash::make('password123'),
                'role'     => 'customer',
            ]
        );

        // 2. Kategori (kolom slug wajib & unik, sebelumnya tidak diisi -> seeding error)
        $categories = [];
        foreach (['Bread', 'Cake', 'Pastry', 'Cookies'] as $name) {
            $categories[$name] = Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }

        // 3. Produk contoh memakai gambar yang sudah ada di public/images
        //    (harga & stok hanyalah contoh, silakan ubah lewat menu Admin)
        $products = [
            ['Croissant Crispy',      'Pastry',  'images/roti1.png',              22000, 'Croissant berlapis dengan kulit renyah dan aroma butter yang harum.'],
            ['Soft Bun Chocolate',    'Bread',   'images/roti2.png',              15000, 'Roti empuk dengan isian cokelat leleh.'],
            ['Artisan Sourdough',     'Bread',   'images/roti3.png',              45000, 'Sourdough fermentasi alami, kulit renyah dan bagian dalam kenyal.'],
            ['Basque Cheesecake',     'Cake',    'images/BasqueCheesecake.png',   28000, 'Cheesecake gaya Basque dengan bagian atas karamel dan tekstur creamy.'],
            ['Cookies Nutella',       'Cookies', 'images/CookiesNutella.png',     18000, 'Cookies renyah dengan isian Nutella.'],
            ['Vanilla Strawberry',    'Cake',    'images/vanillaStrawberry.png',  32000, 'Kue vanilla lembut dengan stroberi segar.'],
        ];

        foreach ($products as [$name, $category, $image, $price, $description]) {
            Product::firstOrCreate(
                ['name' => $name],
                [
                    'category_id' => $categories[$category]->id,
                    'description' => $description,
                    'price'       => $price,
                    'stock'       => 20,
                    'image'       => $image,
                    'is_active'   => true,
                ]
            );
        }
    }
}
