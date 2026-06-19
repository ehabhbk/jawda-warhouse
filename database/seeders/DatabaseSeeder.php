<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Item;
use App\Models\Shelf;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'مدير النظام',
            'username' => 'admin',
            'email' => 'admin@admin.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone' => '0500000000',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'أمين المخزن',
            'username' => 'storekeeper',
            'email' => 'storekeeper@store.com',
            'password' => Hash::make('password'),
            'role' => 'storekeeper',
            'phone' => '0500000001',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'موظف',
            'username' => 'user',
            'email' => 'user@user.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'phone' => '0500000002',
            'is_active' => true,
        ]);

        Shelf::create(['code' => 'A-01', 'name' => 'رف A-01', 'location' => 'المستودع الرئيسي - القسم أ']);
        Shelf::create(['code' => 'A-02', 'name' => 'رف A-02', 'location' => 'المستودع الرئيسي - القسم أ']);
        Shelf::create(['code' => 'B-01', 'name' => 'رف B-01', 'location' => 'المستودع الرئيسي - القسم ب']);
        Shelf::create(['code' => 'B-02', 'name' => 'رف B-02', 'location' => 'المستودع الرئيسي - القسم ب']);
        Shelf::create(['code' => 'C-01', 'name' => 'رف C-01', 'location' => 'المستودع الرئيسي - القسم ج']);

        Category::create(['name' => 'إلكترونيات', 'description' => 'الأجهزة الإلكترونية وملحقاتها']);
        Category::create(['name' => 'أدوات مكتبية', 'description' => 'أدوات القرطاسية والمكتب']);
        Category::create(['name' => 'مواد تنظيف', 'description' => 'منظفات ومطهرات']);
        Category::create(['name' => 'مواد غذائية', 'description' => 'مواد غذائية ومشروبات']);
        Category::create(['name' => 'أدوات صحية', 'description' => 'أدوات سباكة وصحية']);

        Supplier::create(['name' => 'شركة الأمل للتجارة', 'phone' => '0511111111', 'email' => 'info@alamal.com', 'address' => 'الرياض']);
        Supplier::create(['name' => 'مؤسسة الفهد للتوريدات', 'phone' => '0522222222', 'email' => 'info@alfahad.com', 'address' => 'جدة']);
        Supplier::create(['name' => 'شركة النخلة للمواد الغذائية', 'phone' => '0533333333', 'email' => 'info@nakhlah.com', 'address' => 'الدمام']);

        $items = [
            ['code' => 'ITM-001', 'name' => 'حاسوب محمول', 'category_id' => 1, 'shelf_id' => 1, 'quantity' => 15, 'min_quantity' => 5, 'purchase_price' => 2500, 'sale_price' => 3500, 'unit' => 'piece'],
            ['code' => 'ITM-002', 'name' => 'طابعة', 'category_id' => 1, 'shelf_id' => 1, 'quantity' => 10, 'min_quantity' => 3, 'purchase_price' => 800, 'sale_price' => 1200, 'unit' => 'piece'],
            ['code' => 'ITM-003', 'name' => 'ورق طباعة A4', 'category_id' => 2, 'shelf_id' => 2, 'quantity' => 100, 'min_quantity' => 20, 'purchase_price' => 15, 'sale_price' => 25, 'unit' => 'carton'],
            ['code' => 'ITM-004', 'name' => 'قلم حبر أزرق', 'category_id' => 2, 'shelf_id' => 2, 'quantity' => 500, 'min_quantity' => 50, 'purchase_price' => 2, 'sale_price' => 5, 'unit' => 'piece'],
            ['code' => 'ITM-005', 'name' => 'منظف زجاج', 'category_id' => 3, 'shelf_id' => 3, 'quantity' => 40, 'min_quantity' => 10, 'purchase_price' => 12, 'sale_price' => 20, 'unit' => 'bottle'],
            ['code' => 'ITM-006', 'name' => 'ماء شرب 1.5 لتر', 'category_id' => 4, 'shelf_id' => 4, 'quantity' => 200, 'min_quantity' => 30, 'purchase_price' => 2, 'sale_price' => 3, 'unit' => 'bottle'],
            ['code' => 'ITM-007', 'name' => 'صنبور ماء', 'category_id' => 5, 'shelf_id' => 5, 'quantity' => 25, 'min_quantity' => 5, 'purchase_price' => 40, 'sale_price' => 65, 'unit' => 'piece'],
        ];

        foreach ($items as $itemData) {
            Item::create($itemData);
        }
    }
}
