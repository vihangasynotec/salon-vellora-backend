<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalonVelloraServicesSeeder extends Seeder
{
    public function run(): void
    {
        // Disable foreign key checks for clean replacement
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // 1. Remove references to old services
        DB::table('appointments')->whereNotNull('service_id')->update(['service_id' => null]);
        DB::table('quotation_items')->whereNotNull('service_id')->update(['service_id' => null]);
        DB::table('service_package_items')->truncate();
        DB::table('service_packages')->truncate();

        // 2. Delete all existing services & service categories
        DB::table('services')->truncate();
        DB::table('service_categories')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 3. Define the 8 Official Service Categories
        $categoriesData = [
            [
                'id' => 1,
                'name' => 'Hair Services',
                'slug' => 'hair-services',
                'description' => 'Precision haircuts, washes, blow dry styling, curling, hair spas and deep treatments',
                'display_order' => 1,
                'status' => 'active',
            ],
            [
                'id' => 2,
                'name' => 'Hair Colour & Chemical',
                'slug' => 'hair-colour-chemical',
                'description' => 'Highlights, balayage, relaxing, rebonding, Sepora keratin, protein & botox treatments',
                'display_order' => 2,
                'status' => 'active',
            ],
            [
                'id' => 3,
                'name' => 'Facial & Skincare',
                'slug' => 'facial-skincare',
                'description' => 'Clean-ups, gold, pearl, vitamin C, herbal, acne care, hydrating & de-tan facials',
                'display_order' => 3,
                'status' => 'active',
            ],
            [
                'id' => 4,
                'name' => 'Threading & Waxing',
                'slug' => 'threading-waxing',
                'description' => 'Eyebrow shaping, full face threading, full body, arm, leg & bikini line waxing & de-tan',
                'display_order' => 4,
                'status' => 'active',
            ],
            [
                'id' => 5,
                'name' => 'Eyebrow, Eyelash & Others',
                'slug' => 'eyebrow-eyelash-others',
                'description' => 'Lash lifting, brow lamination, tinting, simple & bridal henna, ear piercing & saree pinning',
                'display_order' => 5,
                'status' => 'active',
            ],
            [
                'id' => 6,
                'name' => 'Manicure, Pedicure & Nails',
                'slug' => 'manicure-pedicure-nails',
                'description' => 'Spa manicure, pedicure, gel polish, acrylic nails, nail extensions & nail art',
                'display_order' => 6,
                'status' => 'active',
            ],
            [
                'id' => 7,
                'name' => 'Makeup & Party Dressing',
                'slug' => 'makeup-party-dressing',
                'description' => 'Party, glam & HD makeup, engagement & homecoming makeup, saree draping & hairstyling',
                'display_order' => 7,
                'status' => 'active',
            ],
            [
                'id' => 8,
                'name' => 'Body Care & Relaxation',
                'slug' => 'body-care-relaxation',
                'description' => 'Hand & foot massage, body scrubs, body masks, body polishing & scalp relaxation',
                'display_order' => 8,
                'status' => 'active',
            ],
        ];

        foreach ($categoriesData as $cat) {
            ServiceCategory::create($cat);
        }

        // 4. Define all 118 Official Services
        $services = [
            // ==========================================
            // 1. HAIR SERVICES (17 items)
            // ==========================================
            ['category_id' => 1, 'name' => 'Ladies Hair Cut', 'code' => 'HS-01', 'price' => 1000.00, 'duration_minutes' => 45],
            ['category_id' => 1, 'name' => 'Hair Trim', 'code' => 'HS-02', 'price' => 500.00, 'duration_minutes' => 30],
            ['category_id' => 1, 'name' => 'Fringe Cut', 'code' => 'HS-03', 'price' => 500.00, 'duration_minutes' => 20],
            ['category_id' => 1, 'name' => 'Kids Hair Cut', 'code' => 'HS-04', 'price' => 400.00, 'duration_minutes' => 30],
            ['category_id' => 1, 'name' => 'Hair Wash', 'code' => 'HS-05', 'price' => 800.00, 'duration_minutes' => 30],
            ['category_id' => 1, 'name' => 'Wash & Blow Dry', 'code' => 'HS-06', 'price' => 1000.00, 'duration_minutes' => 45],
            ['category_id' => 1, 'name' => 'Blow Dry Styling', 'code' => 'HS-07', 'price' => 1000.00, 'duration_minutes' => 45],
            ['category_id' => 1, 'name' => 'Hair Ironing', 'code' => 'HS-08', 'price' => 800.00, 'duration_minutes' => 30],
            ['category_id' => 1, 'name' => 'Curling / Tong Styling', 'code' => 'HS-09', 'price' => 1000.00, 'duration_minutes' => 45],
            ['category_id' => 1, 'name' => 'Simple Hair Setting', 'code' => 'HS-10', 'price' => 1000.00, 'duration_minutes' => 45],
            ['category_id' => 1, 'name' => 'Party Hairstyle', 'code' => 'HS-11', 'price' => 2000.00, 'duration_minutes' => 60],
            ['category_id' => 1, 'name' => 'Bridal Hairstyle', 'code' => 'HS-12', 'price' => 5000.00, 'duration_minutes' => 90],
            ['category_id' => 1, 'name' => 'Head Massage', 'code' => 'HS-13', 'price' => 1000.00, 'duration_minutes' => 30],
            ['category_id' => 1, 'name' => 'Oil Treatment', 'code' => 'HS-14', 'price' => 1000.00, 'duration_minutes' => 45],
            ['category_id' => 1, 'name' => 'Hair Spa', 'code' => 'HS-15', 'price' => 1500.00, 'duration_minutes' => 60],
            ['category_id' => 1, 'name' => 'Deep Conditioning', 'code' => 'HS-16', 'price' => 1000.00, 'duration_minutes' => 45],
            ['category_id' => 1, 'name' => 'Dandruff Treatment', 'code' => 'HS-17', 'price' => 1500.00, 'duration_minutes' => 60],

            // ==========================================
            // 2. HAIR COLOUR & CHEMICAL (16 items)
            // ==========================================
            ['category_id' => 2, 'name' => 'Root Touch Up', 'code' => 'HC-01', 'price' => 2000.00, 'duration_minutes' => 60],
            ['category_id' => 2, 'name' => 'Short Hair Colour', 'code' => 'HC-02', 'price' => 2500.00, 'duration_minutes' => 60],
            ['category_id' => 2, 'name' => 'Medium Hair Colour', 'code' => 'HC-03', 'price' => 3500.00, 'duration_minutes' => 90],
            ['category_id' => 2, 'name' => 'Long Hair Colour', 'code' => 'HC-04', 'price' => 5000.00, 'duration_minutes' => 120],
            ['category_id' => 2, 'name' => 'Grey Hair Coverage', 'code' => 'HC-05', 'price' => 2000.00, 'duration_minutes' => 60],
            ['category_id' => 2, 'name' => 'Highlights (Partial)', 'code' => 'HC-06', 'price' => 3000.00, 'duration_minutes' => 90],
            ['category_id' => 2, 'name' => 'Full Highlights', 'code' => 'HC-07', 'price' => 6000.00, 'duration_minutes' => 120],
            ['category_id' => 2, 'name' => 'Balayage / Ombre', 'code' => 'HC-08', 'price' => 8000.00, 'duration_minutes' => 150],
            ['category_id' => 2, 'name' => 'Hair Bleaching', 'code' => 'HC-09', 'price' => 3000.00, 'duration_minutes' => 60],
            ['category_id' => 2, 'name' => 'Hair Toner', 'code' => 'HC-10', 'price' => 2000.00, 'duration_minutes' => 45],
            ['category_id' => 2, 'name' => 'Hair Relaxing', 'code' => 'HC-11', 'price' => 5000.00, 'duration_minutes' => 120],
            ['category_id' => 2, 'name' => 'Hair Rebonding', 'code' => 'HC-12', 'price' => 7000.00, 'duration_minutes' => 180],
            ['category_id' => 2, 'name' => 'Keratin Treatment (Sepora)', 'code' => 'HC-13', 'price' => 12000.00, 'duration_minutes' => 180],
            ['category_id' => 2, 'name' => 'Protein Treatment', 'code' => 'HC-14', 'price' => 3000.00, 'duration_minutes' => 90],
            ['category_id' => 2, 'name' => 'Hair Botox Treatment', 'code' => 'HC-15', 'price' => 5000.00, 'duration_minutes' => 120],
            ['category_id' => 2, 'name' => 'Perming', 'code' => 'HC-16', 'price' => 6000.00, 'duration_minutes' => 120],

            // ==========================================
            // 3. FACIAL & SKINCARE (17 items)
            // ==========================================
            ['category_id' => 3, 'name' => 'Basic Clean-up', 'code' => 'FS-01', 'price' => 1800.00, 'duration_minutes' => 45],
            ['category_id' => 3, 'name' => 'Herbal Clean-up', 'code' => 'FS-02', 'price' => 2000.00, 'duration_minutes' => 45],
            ['category_id' => 3, 'name' => 'Basic Facial', 'code' => 'FS-03', 'price' => 2800.00, 'duration_minutes' => 60],
            ['category_id' => 3, 'name' => 'Herbal Facial', 'code' => 'FS-04', 'price' => 3000.00, 'duration_minutes' => 60],
            ['category_id' => 3, 'name' => 'Fruit Facial', 'code' => 'FS-05', 'price' => 2000.00, 'duration_minutes' => 60],
            ['category_id' => 3, 'name' => 'Gold Facial', 'code' => 'FS-06', 'price' => 5000.00, 'duration_minutes' => 75],
            ['category_id' => 3, 'name' => 'Pearl Facial', 'code' => 'FS-07', 'price' => 3000.00, 'duration_minutes' => 60],
            ['category_id' => 3, 'name' => 'Vitamin C Facial', 'code' => 'FS-08', 'price' => 3500.00, 'duration_minutes' => 60],
            ['category_id' => 3, 'name' => 'Acne Care Facial', 'code' => 'FS-09', 'price' => 2500.00, 'duration_minutes' => 60],
            ['category_id' => 3, 'name' => 'Hydrating Facial', 'code' => 'FS-10', 'price' => 2500.00, 'duration_minutes' => 60],
            ['category_id' => 3, 'name' => 'Skin Glow Facial', 'code' => 'FS-11', 'price' => 3000.00, 'duration_minutes' => 60],
            ['category_id' => 3, 'name' => 'Charcoal Facial', 'code' => 'FS-12', 'price' => 3000.00, 'duration_minutes' => 60],
            ['category_id' => 3, 'name' => 'De-tan Facial', 'code' => 'FS-13', 'price' => 1500.00, 'duration_minutes' => 45],
            ['category_id' => 3, 'name' => 'Face Massage', 'code' => 'FS-14', 'price' => 1000.00, 'duration_minutes' => 30],
            ['category_id' => 3, 'name' => 'Face Mask', 'code' => 'FS-15', 'price' => 800.00, 'duration_minutes' => 30],
            ['category_id' => 3, 'name' => 'Back Clean-up', 'code' => 'FS-16', 'price' => 2000.00, 'duration_minutes' => 45],
            ['category_id' => 3, 'name' => 'Back Facial', 'code' => 'FS-17', 'price' => 3000.00, 'duration_minutes' => 60],

            // ==========================================
            // 4. THREADING & WAXING (20 items)
            // ==========================================
            ['category_id' => 4, 'name' => 'Eyebrows Threading', 'code' => 'TW-01', 'price' => 250.00, 'duration_minutes' => 15],
            ['category_id' => 4, 'name' => 'Upper Lip', 'code' => 'TW-02', 'price' => 200.00, 'duration_minutes' => 10],
            ['category_id' => 4, 'name' => 'Chin Threading', 'code' => 'TW-03', 'price' => 250.00, 'duration_minutes' => 15],
            ['category_id' => 4, 'name' => 'Forehead Threading', 'code' => 'TW-04', 'price' => 250.00, 'duration_minutes' => 15],
            ['category_id' => 4, 'name' => 'Full Face Threading', 'code' => 'TW-05', 'price' => 700.00, 'duration_minutes' => 30],
            ['category_id' => 4, 'name' => 'Eyebrows + Upper Lip', 'code' => 'TW-06', 'price' => 500.00, 'duration_minutes' => 20],
            ['category_id' => 4, 'name' => 'Underarm Waxing', 'code' => 'TW-07', 'price' => 700.00, 'duration_minutes' => 20],
            ['category_id' => 4, 'name' => 'Half Arm Waxing', 'code' => 'TW-08', 'price' => 600.00, 'duration_minutes' => 25],
            ['category_id' => 4, 'name' => 'Full Arm Waxing', 'code' => 'TW-09', 'price' => 1000.00, 'duration_minutes' => 40],
            ['category_id' => 4, 'name' => 'Half Leg Waxing', 'code' => 'TW-10', 'price' => 1000.00, 'duration_minutes' => 30],
            ['category_id' => 4, 'name' => 'Full Leg Waxing', 'code' => 'TW-11', 'price' => 1500.00, 'duration_minutes' => 45],
            ['category_id' => 4, 'name' => 'Full Back Waxing', 'code' => 'TW-12', 'price' => 1500.00, 'duration_minutes' => 45],
            ['category_id' => 4, 'name' => 'Stomach Waxing', 'code' => 'TW-13', 'price' => 500.00, 'duration_minutes' => 20],
            ['category_id' => 4, 'name' => 'Bikini Line Waxing', 'code' => 'TW-14', 'price' => 1000.00, 'duration_minutes' => 30],
            ['category_id' => 4, 'name' => 'Full Body Waxing', 'code' => 'TW-15', 'price' => 3500.00, 'duration_minutes' => 90],
            ['category_id' => 4, 'name' => 'Full Face Wax', 'code' => 'TW-16', 'price' => 1000.00, 'duration_minutes' => 30],
            ['category_id' => 4, 'name' => 'Upper Lip Wax', 'code' => 'TW-17', 'price' => 300.00, 'duration_minutes' => 10],
            ['category_id' => 4, 'name' => 'Underarm De-tan', 'code' => 'TW-18', 'price' => 500.00, 'duration_minutes' => 20],
            ['category_id' => 4, 'name' => 'Arm De-tan', 'code' => 'TW-19', 'price' => 800.00, 'duration_minutes' => 30],
            ['category_id' => 4, 'name' => 'Leg De-tan', 'code' => 'TW-20', 'price' => 1000.00, 'duration_minutes' => 30],

            // ==========================================
            // 5. EYEBROW, EYELASH & OTHERS (10 items)
            // ==========================================
            ['category_id' => 5, 'name' => 'Eyebrow Shaping', 'code' => 'EO-01', 'price' => 200.00, 'duration_minutes' => 15],
            ['category_id' => 5, 'name' => 'Eyebrow Tinting', 'code' => 'EO-02', 'price' => 500.00, 'duration_minutes' => 20],
            ['category_id' => 5, 'name' => 'Eyelash Tinting', 'code' => 'EO-03', 'price' => 500.00, 'duration_minutes' => 25],
            ['category_id' => 5, 'name' => 'Lash Lifting', 'code' => 'EO-04', 'price' => 2000.00, 'duration_minutes' => 45],
            ['category_id' => 5, 'name' => 'Eyebrow Lamination', 'code' => 'EO-05', 'price' => 2000.00, 'duration_minutes' => 45],
            ['category_id' => 5, 'name' => 'Strip Lashes', 'code' => 'EO-06', 'price' => 500.00, 'duration_minutes' => 15],
            ['category_id' => 5, 'name' => 'Henna Application (Simple)', 'code' => 'EO-07', 'price' => 1800.00, 'duration_minutes' => 45],
            ['category_id' => 5, 'name' => 'Bridal / Event Henna', 'code' => 'EO-08', 'price' => 2000.00, 'duration_minutes' => 90],
            ['category_id' => 5, 'name' => 'Ear Piercing', 'code' => 'EO-09', 'price' => 500.00, 'duration_minutes' => 15],
            ['category_id' => 5, 'name' => 'Saree Pinning', 'code' => 'EO-10', 'price' => 500.00, 'duration_minutes' => 20],

            // ==========================================
            // 6. MANICURE, PEDICURE & NAILS (14 items)
            // ==========================================
            ['category_id' => 6, 'name' => 'Basic Manicure', 'code' => 'MP-01', 'price' => 1000.00, 'duration_minutes' => 40],
            ['category_id' => 6, 'name' => 'Spa Manicure', 'code' => 'MP-02', 'price' => 1500.00, 'duration_minutes' => 60],
            ['category_id' => 6, 'name' => 'Basic Pedicure', 'code' => 'MP-03', 'price' => 2000.00, 'duration_minutes' => 45],
            ['category_id' => 6, 'name' => 'Spa Pedicure', 'code' => 'MP-04', 'price' => 2800.00, 'duration_minutes' => 60],
            ['category_id' => 6, 'name' => 'Manicure + Pedicure', 'code' => 'MP-05', 'price' => 2000.00, 'duration_minutes' => 75],
            ['category_id' => 6, 'name' => 'Normal Nail Polish', 'code' => 'MP-06', 'price' => 300.00, 'duration_minutes' => 20],
            ['category_id' => 6, 'name' => 'Gel Nail Polish', 'code' => 'MP-07', 'price' => 1000.00, 'duration_minutes' => 45],
            ['category_id' => 6, 'name' => 'Gel Polish Removal', 'code' => 'MP-08', 'price' => 500.00, 'duration_minutes' => 25],
            ['category_id' => 6, 'name' => 'Nail Extensions', 'code' => 'MP-09', 'price' => 2500.00, 'duration_minutes' => 90],
            ['category_id' => 6, 'name' => 'Acrylic Nails', 'code' => 'MP-10', 'price' => 3000.00, 'duration_minutes' => 90],
            ['category_id' => 6, 'name' => 'Nail Art (Simple)', 'code' => 'MP-11', 'price' => 500.00, 'duration_minutes' => 30],
            ['category_id' => 6, 'name' => 'Nail Art (Full Set)', 'code' => 'MP-12', 'price' => 2000.00, 'duration_minutes' => 60],
            ['category_id' => 6, 'name' => 'Nail Repair', 'code' => 'MP-13', 'price' => 500.00, 'duration_minutes' => 20],
            ['category_id' => 6, 'name' => 'Cuticle Care', 'code' => 'MP-14', 'price' => 500.00, 'duration_minutes' => 20],

            // ==========================================
            // 7. MAKEUP & PARTY DRESSING (14 items)
            // ==========================================
            ['category_id' => 7, 'name' => 'Light / Natural Makeup', 'code' => 'MD-01', 'price' => 2800.00, 'duration_minutes' => 45],
            ['category_id' => 7, 'name' => 'Party Makeup', 'code' => 'MD-02', 'price' => 3000.00, 'duration_minutes' => 60],
            ['category_id' => 7, 'name' => 'Glam Makeup', 'code' => 'MD-03', 'price' => 4000.00, 'duration_minutes' => 75],
            ['category_id' => 7, 'name' => 'HD Makeup', 'code' => 'MD-04', 'price' => 5000.00, 'duration_minutes' => 90],
            ['category_id' => 7, 'name' => 'Engagement Makeup', 'code' => 'MD-05', 'price' => 8000.00, 'duration_minutes' => 120],
            ['category_id' => 7, 'name' => 'Homecoming Makeup', 'code' => 'MD-06', 'price' => 8000.00, 'duration_minutes' => 120],
            ['category_id' => 7, 'name' => 'Saree Draping', 'code' => 'MD-07', 'price' => 800.00, 'duration_minutes' => 30],
            ['category_id' => 7, 'name' => 'Saree Pleating', 'code' => 'MD-08', 'price' => 800.00, 'duration_minutes' => 30],
            ['category_id' => 7, 'name' => 'Draping + Pleating', 'code' => 'MD-09', 'price' => 800.00, 'duration_minutes' => 45],
            ['category_id' => 7, 'name' => 'Party Hairstyle', 'code' => 'MD-10', 'price' => 3000.00, 'duration_minutes' => 60],
            ['category_id' => 7, 'name' => 'Makeup + Hairstyle', 'code' => 'MD-11', 'price' => 4000.00, 'duration_minutes' => 90],
            ['category_id' => 7, 'name' => 'Makeup + Hairstyle + Saree', 'code' => 'MD-12', 'price' => 5000.00, 'duration_minutes' => 120],
            ['category_id' => 7, 'name' => 'Eyelash Application', 'code' => 'MD-13', 'price' => 500.00, 'duration_minutes' => 15],
            ['category_id' => 7, 'name' => 'Individual Lash Application', 'code' => 'MD-14', 'price' => 1000.00, 'duration_minutes' => 30],

            // ==========================================
            // 8. BODY CARE & RELAXATION (10 items)
            // ==========================================
            ['category_id' => 8, 'name' => 'Hand Massage', 'code' => 'BR-01', 'price' => 1000.00, 'duration_minutes' => 30],
            ['category_id' => 8, 'name' => 'Foot Massage', 'code' => 'BR-02', 'price' => 2000.00, 'duration_minutes' => 45],
            ['category_id' => 8, 'name' => 'Head, Neck & Shoulder Massage', 'code' => 'BR-03', 'price' => 800.00, 'duration_minutes' => 30],
            ['category_id' => 8, 'name' => 'Body Scrub', 'code' => 'BR-04', 'price' => 2000.00, 'duration_minutes' => 60],
            ['category_id' => 8, 'name' => 'Full Body Scrub', 'code' => 'BR-05', 'price' => 3000.00, 'duration_minutes' => 90],
            ['category_id' => 8, 'name' => 'Foot Scrub', 'code' => 'BR-06', 'price' => 1000.00, 'duration_minutes' => 30],
            ['category_id' => 8, 'name' => 'Hand Scrub', 'code' => 'BR-07', 'price' => 800.00, 'duration_minutes' => 25],
            ['category_id' => 8, 'name' => 'Body Mask', 'code' => 'BR-08', 'price' => 1500.00, 'duration_minutes' => 45],
            ['category_id' => 8, 'name' => 'Body Polishing', 'code' => 'BR-09', 'price' => 3000.00, 'duration_minutes' => 90],
            ['category_id' => 8, 'name' => 'Hair Wash + Head Massage', 'code' => 'BR-10', 'price' => 2500.00, 'duration_minutes' => 60],
        ];

        $insertedCount = 0;
        foreach ($services as $srv) {
            $srv['discount'] = 0.00;
            $srv['is_featured'] = false;
            $srv['status'] = 'active';
            Service::create($srv);
            $insertedCount++;
        }

        echo "Successfully cleared previous services and seeded {$insertedCount} services across 8 categories.\n";
    }
}
