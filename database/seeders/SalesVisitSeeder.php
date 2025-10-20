<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SalesVisitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed Business Types
        $businessTypes = [
            ['name_en' => 'Restaurants', 'name_ar' => 'مطاعم', 'is_active' => true, 'sort_order' => 1],
            ['name_en' => 'Cafes', 'name_ar' => 'مقاهي', 'is_active' => true, 'sort_order' => 2],
            ['name_en' => 'Retail Stores', 'name_ar' => 'متاجر تجزئة', 'is_active' => true, 'sort_order' => 3],
            ['name_en' => 'Fashion & Clothing', 'name_ar' => 'أزياء وملابس', 'is_active' => true, 'sort_order' => 4],
            ['name_en' => 'Electronics', 'name_ar' => 'إلكترونيات', 'is_active' => true, 'sort_order' => 5],
            ['name_en' => 'Beauty & Cosmetics', 'name_ar' => 'تجميل ومستحضرات', 'is_active' => true, 'sort_order' => 6],
            ['name_en' => 'Food Products', 'name_ar' => 'منتجات غذائية', 'is_active' => true, 'sort_order' => 7],
            ['name_en' => 'Real Estate', 'name_ar' => 'عقارات', 'is_active' => true, 'sort_order' => 8],
            ['name_en' => 'Healthcare', 'name_ar' => 'رعاية صحية', 'is_active' => true, 'sort_order' => 9],
            ['name_en' => 'Automotive', 'name_ar' => 'سيارات', 'is_active' => true, 'sort_order' => 10],
            ['name_en' => 'Jewelry', 'name_ar' => 'مجوهرات', 'is_active' => true, 'sort_order' => 11],
            ['name_en' => 'Furniture', 'name_ar' => 'أثاث', 'is_active' => true, 'sort_order' => 12],
            ['name_en' => 'Sports & Fitness', 'name_ar' => 'رياضة ولياقة', 'is_active' => true, 'sort_order' => 13],
            ['name_en' => 'Education', 'name_ar' => 'تعليم', 'is_active' => true, 'sort_order' => 14],
            ['name_en' => 'Other', 'name_ar' => 'أخرى', 'is_active' => true, 'sort_order' => 99],
        ];

        foreach ($businessTypes as $type) {
            DB::table('tbl_business_types')->updateOrInsert(
                ['name_en' => $type['name_en']],
                array_merge($type, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('✅ Seeded ' . count($businessTypes) . ' business types');

        // Seed Product Categories
        $productCategories = [
            ['name_en' => 'Food & Beverages', 'name_ar' => 'طعام ومشروبات', 'is_active' => true, 'sort_order' => 1],
            ['name_en' => 'Fashion', 'name_ar' => 'أزياء', 'is_active' => true, 'sort_order' => 2],
            ['name_en' => 'Electronics', 'name_ar' => 'إلكترونيات', 'is_active' => true, 'sort_order' => 3],
            ['name_en' => 'Cosmetics', 'name_ar' => 'مستحضرات تجميل', 'is_active' => true, 'sort_order' => 4],
            ['name_en' => 'Home & Garden', 'name_ar' => 'منزل وحديقة', 'is_active' => true, 'sort_order' => 5],
            ['name_en' => 'Sports Equipment', 'name_ar' => 'معدات رياضية', 'is_active' => true, 'sort_order' => 6],
            ['name_en' => 'Toys & Games', 'name_ar' => 'ألعاب', 'is_active' => true, 'sort_order' => 7],
            ['name_en' => 'Books & Stationery', 'name_ar' => 'كتب وقرطاسية', 'is_active' => true, 'sort_order' => 8],
            ['name_en' => 'Automotive Parts', 'name_ar' => 'قطع سيارات', 'is_active' => true, 'sort_order' => 9],
            ['name_en' => 'Jewelry & Accessories', 'name_ar' => 'مجوهرات وإكسسوارات', 'is_active' => true, 'sort_order' => 10],
            ['name_en' => 'Medical Supplies', 'name_ar' => 'مستلزمات طبية', 'is_active' => true, 'sort_order' => 11],
            ['name_en' => 'Building Materials', 'name_ar' => 'مواد بناء', 'is_active' => true, 'sort_order' => 12],
            ['name_en' => 'Services', 'name_ar' => 'خدمات', 'is_active' => true, 'sort_order' => 13],
            ['name_en' => 'Other', 'name_ar' => 'أخرى', 'is_active' => true, 'sort_order' => 99],
        ];

        foreach ($productCategories as $category) {
            DB::table('tbl_product_categories')->updateOrInsert(
                ['name_en' => $category['name_en']],
                array_merge($category, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('✅ Seeded ' . count($productCategories) . ' product categories');
    }
}
