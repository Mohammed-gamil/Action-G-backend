<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Client;
use App\Models\Visit;
use App\Models\BusinessType;
use App\Models\ProductCategory;

class SampleVisitsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get sales reps
        $salesReps = User::where('role', 'SALES_REP')->get();
        
        if ($salesReps->isEmpty()) {
            $this->command->error('❌ No sales reps found! Run UserSeeder first.');
            return;
        }

        // Get business types and categories
        $businessTypes = BusinessType::active()->get();
        $productCategories = ProductCategory::active()->get();

        if ($businessTypes->isEmpty() || $productCategories->isEmpty()) {
            $this->command->error('❌ No business types or categories found! Run SalesVisitSeeder first.');
            return;
        }

        // Sample clients data
        $sampleClients = [
            [
                'store_name' => 'مطعم البيك',
                'contact_person' => 'أحمد محمد',
                'mobile' => '0501234567',
                'mobile_2' => '0112345678',
                'address' => 'الرياض، حي الملز، شارع التحلية',
                'business_type_id' => $businessTypes->where('name_en', 'Restaurants')->first()->id ?? 1,
            ],
            [
                'store_name' => 'كافيه نجد',
                'contact_person' => 'فهد العتيبي',
                'mobile' => '0507654321',
                'mobile_2' => null,
                'address' => 'الرياض، حي النرجس، طريق الملك فهد',
                'business_type_id' => $businessTypes->where('name_en', 'Cafes')->first()->id ?? 2,
            ],
            [
                'store_name' => 'متجر أناقة للأزياء',
                'contact_person' => 'سارة الأحمد',
                'mobile' => '0503456789',
                'mobile_2' => '0113456789',
                'address' => 'جدة، حي الزهراء، شارع الأمير سلطان',
                'business_type_id' => $businessTypes->where('name_en', 'Fashion & Clothing')->first()->id ?? 4,
            ],
            [
                'store_name' => 'إلكترونيات المستقبل',
                'contact_person' => 'خالد السالم',
                'mobile' => '0509876543',
                'mobile_2' => '0129876543',
                'address' => 'الدمام، حي الفيصلية، شارع الظهران',
                'business_type_id' => $businessTypes->where('name_en', 'Electronics')->first()->id ?? 5,
            ],
            [
                'store_name' => 'صالون جمال الورود',
                'contact_person' => 'منى الحربي',
                'mobile' => '0551122334',
                'mobile_2' => null,
                'address' => 'الرياض، حي العليا، طريق العروبة',
                'business_type_id' => $businessTypes->where('name_en', 'Beauty & Cosmetics')->first()->id ?? 6,
            ],
            [
                'store_name' => 'مخبز النخيل',
                'contact_person' => 'عبدالله القحطاني',
                'mobile' => '0504445566',
                'mobile_2' => '0114445566',
                'address' => 'الرياض، حي الياسمين، شارع الأمير ماجد',
                'business_type_id' => $businessTypes->where('name_en', 'Food Products')->first()->id ?? 7,
            ],
            [
                'store_name' => 'معرض الماس للسيارات',
                'contact_person' => 'محمد الشمري',
                'mobile' => '0556677889',
                'mobile_2' => '0126677889',
                'address' => 'جدة، حي الروضة، طريق المدينة',
                'business_type_id' => $businessTypes->where('name_en', 'Automotive')->first()->id ?? 10,
            ],
            [
                'store_name' => 'مجوهرات الفخامة',
                'contact_person' => 'عبدالعزيز المطيري',
                'mobile' => '0502233445',
                'mobile_2' => null,
                'address' => 'الرياض، حي المروج، شارع الملك عبدالله',
                'business_type_id' => $businessTypes->where('name_en', 'Jewelry')->first()->id ?? 11,
            ],
            [
                'store_name' => 'أثاث المنزل العصري',
                'contact_person' => 'ناصر الدوسري',
                'mobile' => '0557788990',
                'mobile_2' => '0117788990',
                'address' => 'الدمام، حي المريكبات، شارع الخليج',
                'business_type_id' => $businessTypes->where('name_en', 'Furniture')->first()->id ?? 12,
            ],
            [
                'store_name' => 'رياضة الأبطال',
                'contact_person' => 'سلطان العنزي',
                'mobile' => '0503344556',
                'mobile_2' => null,
                'address' => 'الرياض، حي الربوة، طريق الملك خالد',
                'business_type_id' => $businessTypes->where('name_en', 'Sports & Fitness')->first()->id ?? 13,
            ],
        ];

        $createdClients = [];
        foreach ($sampleClients as $clientData) {
            // Assign random sales rep as creator
            $rep = $salesReps->random();
            
            $client = Client::updateOrInsert(
                ['mobile' => $clientData['mobile']],
                array_merge($clientData, [
                    'created_by_rep_id' => $rep->id,
                    'created_at' => now()->subDays(rand(1, 30)),
                    'updated_at' => now()->subDays(rand(0, 5)),
                ])
            );

            $createdClients[] = Client::where('mobile', $clientData['mobile'])->first();
        }

        $this->command->info('✅ Seeded ' . count($sampleClients) . ' clients');

        // Sample visits
        $visitStatuses = ['draft', 'submitted', 'pending_review', 'approved', 'closed_lost'];
        $shootingGoals = [
            ['product_showcase', 'social_media'],
            ['promotional_video', 'website_content'],
            ['product_showcase', 'promotional_video', 'social_media'],
            ['behind_scenes'],
            ['product_showcase', 'website_content'],
        ];
        $serviceTypes = [
            ['photography', 'video'],
            ['photography'],
            ['video', 'editing'],
            ['photography', 'video', 'editing'],
            ['editing'],
        ];
        $locations = ['client_location', 'action_studio', 'external'];
        $budgetRanges = ['5000-10000 ريال', '10000-20000 ريال', '20000-30000 ريال', '30000-50000 ريال', '50000+ ريال'];

        $visitCount = 0;
        foreach ($createdClients as $client) {
            // Create 1-3 visits per client
            $numVisits = rand(1, 3);
            
            for ($i = 0; $i < $numVisits; $i++) {
                $status = $visitStatuses[array_rand($visitStatuses)];
                $rep = $salesReps->random();
                $hasAgency = rand(0, 1) == 1;
                $needsVoice = rand(0, 1) == 1;
                
                $visitData = [
                    'client_id' => $client->id,
                    'rep_id' => $rep->id,
                    'visit_date' => now()->subDays(rand(0, 20))->format('Y-m-d'),
                    'status' => $status,
                    'has_previous_agency' => $hasAgency,
                    'previous_agency_name' => $hasAgency ? 'وكالة التسويق الرقمي' : null,
                    'needs_voiceover' => $needsVoice,
                    'voiceover_language' => $needsVoice ? (rand(0, 1) ? 'Arabic' : 'English') : null,
                    'shooting_goals' => json_encode($shootingGoals[array_rand($shootingGoals)]),
                    'shooting_goals_other_text' => null,
                    'service_types' => json_encode($serviceTypes[array_rand($serviceTypes)]),
                    'service_types_other_text' => null,
                    'preferred_location' => $locations[array_rand($locations)],
                    'product_category_id' => $productCategories->random()->id,
                    'product_description' => 'منتجات عالية الجودة تحتاج إلى تصوير احترافي',
                    'estimated_product_count' => rand(5, 50),
                    'preferred_shoot_date' => now()->addDays(rand(7, 30))->format('Y-m-d'),
                    'budget_range' => $budgetRanges[array_rand($budgetRanges)],
                    'rep_notes' => 'عميل مهتم، يحتاج متابعة',
                    'admin_notes' => $status == 'approved' ? 'تمت الموافقة على المشروع' : ($status == 'closed_lost' ? 'تم إغلاق الصفقة - الميزانية غير كافية' : null),
                    'approved_by_admin_id' => $status == 'approved' ? User::where('role', 'ADMIN')->first()?->id : null,
                    'approved_at' => $status == 'approved' ? now()->subDays(rand(1, 5)) : null,
                    'created_at' => now()->subDays(rand(0, 15)),
                    'updated_at' => now()->subDays(rand(0, 3)),
                ];

                Visit::create($visitData);
                $visitCount++;
            }
        }

        $this->command->info('✅ Seeded ' . $visitCount . ' visits');
        $this->command->info('');
        $this->command->info('📊 Summary:');
        $this->command->info('   • Clients: ' . count($createdClients));
        $this->command->info('   • Visits: ' . $visitCount);
        $this->command->info('   • Draft: ' . Visit::where('status', 'draft')->count());
        $this->command->info('   • Submitted: ' . Visit::where('status', 'submitted')->count());
        $this->command->info('   • Pending Review: ' . Visit::where('status', 'pending_review')->count());
        $this->command->info('   • Approved: ' . Visit::where('status', 'approved')->count());
        $this->command->info('   • Closed Lost: ' . Visit::where('status', 'closed_lost')->count());
    }
}
