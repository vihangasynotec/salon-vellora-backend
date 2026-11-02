<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RentalItem;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Roles
        $roles = [
            ['name' => 'super_admin', 'display_name' => 'Super Admin', 'description' => 'Full access to everything in Salon Vellora', 'is_system' => true],
            ['name' => 'admin', 'display_name' => 'Admin', 'description' => 'Salon management access', 'is_system' => true],
            ['name' => 'manager', 'display_name' => 'Manager', 'description' => 'Operations, POS, staff, appointments and reports', 'is_system' => true],
            ['name' => 'receptionist', 'display_name' => 'Receptionist', 'description' => 'Front desk, appointments, quotations and clients', 'is_system' => true],
            ['name' => 'cashier', 'display_name' => 'Cashier', 'description' => 'POS checkout, payments, bills and invoices', 'is_system' => true],
            ['name' => 'staff', 'display_name' => 'Staff', 'description' => 'Stylist schedule and service tracking', 'is_system' => true],
        ];

        $roleModels = [];
        foreach ($roles as $r) {
            $roleModels[$r['name']] = Role::updateOrCreate(['name' => $r['name']], $r);
        }

        // 2. Granular Permissions
        $permissions = [
            // Dashboard
            ['name' => 'dashboard.view', 'display_name' => 'View Dashboard', 'module' => 'Dashboard'],
            
            // Users
            ['name' => 'users.view', 'display_name' => 'View Users', 'module' => 'Users'],
            ['name' => 'users.create', 'display_name' => 'Create Users', 'module' => 'Users'],
            ['name' => 'users.edit', 'display_name' => 'Edit Users', 'module' => 'Users'],
            ['name' => 'users.delete', 'display_name' => 'Delete Users', 'module' => 'Users'],
            
            // Roles
            ['name' => 'roles.view', 'display_name' => 'View Roles', 'module' => 'Roles'],
            ['name' => 'roles.create', 'display_name' => 'Create Roles', 'module' => 'Roles'],
            ['name' => 'roles.edit', 'display_name' => 'Edit Roles', 'module' => 'Roles'],
            ['name' => 'roles.delete', 'display_name' => 'Delete Roles', 'module' => 'Roles'],

            // Customers
            ['name' => 'customers.view', 'display_name' => 'View Customers', 'module' => 'Customers'],
            ['name' => 'customers.create', 'display_name' => 'Create Customers', 'module' => 'Customers'],
            ['name' => 'customers.edit', 'display_name' => 'Edit Customers', 'module' => 'Customers'],
            ['name' => 'customers.delete', 'display_name' => 'Delete Customers', 'module' => 'Customers'],

            // Services
            ['name' => 'services.view', 'display_name' => 'View Services', 'module' => 'Services'],
            ['name' => 'services.create', 'display_name' => 'Create Services', 'module' => 'Services'],
            ['name' => 'services.edit', 'display_name' => 'Edit Services', 'module' => 'Services'],
            ['name' => 'services.delete', 'display_name' => 'Delete Services', 'module' => 'Services'],

            // Appointments
            ['name' => 'appointments.view', 'display_name' => 'View Appointments', 'module' => 'Appointments'],
            ['name' => 'appointments.create', 'display_name' => 'Create Appointments', 'module' => 'Appointments'],
            ['name' => 'appointments.edit', 'display_name' => 'Edit Appointments', 'module' => 'Appointments'],
            ['name' => 'appointments.cancel', 'display_name' => 'Cancel Appointments', 'module' => 'Appointments'],
            ['name' => 'appointments.delete', 'display_name' => 'Delete Appointments', 'module' => 'Appointments'],

            // POS
            ['name' => 'pos.view', 'display_name' => 'View POS Screen', 'module' => 'POS'],
            ['name' => 'pos.create', 'display_name' => 'Process Checkout', 'module' => 'POS'],
            ['name' => 'pos.hold', 'display_name' => 'Hold Bill', 'module' => 'POS'],
            ['name' => 'pos.resume', 'display_name' => 'Resume Held Bill', 'module' => 'POS'],
            ['name' => 'pos.cancel', 'display_name' => 'Cancel Cart', 'module' => 'POS'],
            ['name' => 'pos.refund', 'display_name' => 'Process Sale Refund', 'module' => 'POS'],

            // Sales & Invoices
            ['name' => 'sales.view', 'display_name' => 'View Sales & Invoices', 'module' => 'Sales'],
            ['name' => 'sales.create', 'display_name' => 'Create Sale', 'module' => 'Sales'],
            ['name' => 'sales.edit', 'display_name' => 'Edit Sale', 'module' => 'Sales'],
            ['name' => 'sales.delete', 'display_name' => 'Delete / Void Sale', 'module' => 'Sales'],

            // Quotations
            ['name' => 'quotations.view', 'display_name' => 'View Quotations', 'module' => 'Quotations'],
            ['name' => 'quotations.create', 'display_name' => 'Create Quotation', 'module' => 'Quotations'],
            ['name' => 'quotations.edit', 'display_name' => 'Edit Quotation', 'module' => 'Quotations'],
            ['name' => 'quotations.delete', 'display_name' => 'Delete Quotation', 'module' => 'Quotations'],
            ['name' => 'quotations.approve', 'display_name' => 'Approve Quotation', 'module' => 'Quotations'],
            ['name' => 'quotations.reject', 'display_name' => 'Reject Quotation', 'module' => 'Quotations'],
            ['name' => 'quotations.convert', 'display_name' => 'Convert Quotation', 'module' => 'Quotations'],

            // Inventory
            ['name' => 'inventory.view', 'display_name' => 'View Inventory', 'module' => 'Inventory'],
            ['name' => 'inventory.create', 'display_name' => 'Add Products', 'module' => 'Inventory'],
            ['name' => 'inventory.adjust', 'display_name' => 'Adjust Stock', 'module' => 'Inventory'],

            // Purchases
            ['name' => 'purchases.view', 'display_name' => 'View Purchases', 'module' => 'Purchases'],
            ['name' => 'purchases.create', 'display_name' => 'Create Purchases', 'module' => 'Purchases'],
            ['name' => 'purchases.edit', 'display_name' => 'Edit Purchases', 'module' => 'Purchases'],

            // Expenses
            ['name' => 'expenses.view', 'display_name' => 'View Expenses', 'module' => 'Expenses'],
            ['name' => 'expenses.create', 'display_name' => 'Create Expense', 'module' => 'Expenses'],
            ['name' => 'expenses.edit', 'display_name' => 'Edit Expense', 'module' => 'Expenses'],
            ['name' => 'expenses.delete', 'display_name' => 'Delete Expense', 'module' => 'Expenses'],

            // Staff
            ['name' => 'staff.view', 'display_name' => 'View Staff', 'module' => 'Staff'],
            ['name' => 'staff.create', 'display_name' => 'Create Staff', 'module' => 'Staff'],
            ['name' => 'staff.edit', 'display_name' => 'Edit Staff', 'module' => 'Staff'],

            // Reports
            ['name' => 'reports.view', 'display_name' => 'View Reports', 'module' => 'Reports'],
            ['name' => 'reports.export', 'display_name' => 'Export Reports', 'module' => 'Reports'],

            // Settings & Audit
            ['name' => 'settings.view', 'display_name' => 'View Settings', 'module' => 'Settings'],
            ['name' => 'settings.edit', 'display_name' => 'Edit Settings', 'module' => 'Settings'],
            ['name' => 'audit_logs.view', 'display_name' => 'View Audit Trail', 'module' => 'Audit'],
        ];

        $permissionIds = [];
        foreach ($permissions as $p) {
            $model = Permission::updateOrCreate(['name' => $p['name']], $p);
            $permissionIds[$p['name']] = $model->id;
        }

        // Assign Permissions to Roles
        // Super Admin gets all
        $roleModels['super_admin']->permissions()->sync(array_values($permissionIds));

        // Admin gets everything except user/role deletion
        $adminPerms = array_filter($permissionIds, fn($k) => !in_array($k, ['users.delete', 'roles.delete']), ARRAY_FILTER_USE_KEY);
        $roleModels['admin']->permissions()->sync(array_values($adminPerms));

        // Manager gets dashboard, pos, sales, appointments, customers, services, staff, inventory, purchases, quotations, expenses, reports
        $managerPerms = array_filter($permissionIds, fn($k) => !str_starts_with($k, 'users.') && !str_starts_with($k, 'roles.') && !str_starts_with($k, 'settings.'), ARRAY_FILTER_USE_KEY);
        $roleModels['manager']->permissions()->sync(array_values($managerPerms));

        // Receptionist
        $receptionistKeys = ['dashboard.view', 'customers.view', 'customers.create', 'customers.edit', 'appointments.view', 'appointments.create', 'appointments.edit', 'appointments.cancel', 'services.view', 'quotations.view', 'quotations.create', 'quotations.edit', 'quotations.convert', 'pos.view', 'pos.create', 'sales.view'];
        $receptionistPerms = array_intersect_key($permissionIds, array_flip($receptionistKeys));
        $roleModels['receptionist']->permissions()->sync(array_values($receptionistPerms));

        // Cashier
        $cashierKeys = ['pos.view', 'pos.create', 'pos.hold', 'pos.resume', 'pos.cancel', 'customers.view', 'customers.create', 'sales.view', 'sales.create'];
        $cashierPerms = array_intersect_key($permissionIds, array_flip($cashierKeys));
        $roleModels['cashier']->permissions()->sync(array_values($cashierPerms));

        // Staff
        $staffKeys = ['appointments.view', 'services.view', 'customers.view'];
        $staffPerms = array_intersect_key($permissionIds, array_flip($staffKeys));
        $roleModels['staff']->permissions()->sync(array_values($staffPerms));

        // 3. Create Default Users
        $users = [
            [
                'name' => 'Super Administrator',
                'email' => 'admin@salonvellora.com',
                'phone' => '0740844739',
                'password' => Hash::make('password123'),
                'role' => 'Super Admin',
                'role_id' => $roleModels['super_admin']->id,
                'status' => 'active',
                'max_discount_percentage' => 100.00,
            ],
            [
                'name' => 'Salon Manager',
                'email' => 'manager@salonvellora.com',
                'phone' => '0740844739',
                'password' => Hash::make('password123'),
                'role' => 'Manager',
                'role_id' => $roleModels['manager']->id,
                'status' => 'active',
                'max_discount_percentage' => 30.00,
            ],
            [
                'name' => 'Front Desk Reception',
                'email' => 'reception@salonvellora.com',
                'phone' => '0740844739',
                'password' => Hash::make('password123'),
                'role' => 'Receptionist',
                'role_id' => $roleModels['receptionist']->id,
                'status' => 'active',
                'max_discount_percentage' => 15.00,
            ],
            [
                'name' => 'Cashier Desk',
                'email' => 'cashier@salonvellora.com',
                'phone' => '0740844739',
                'password' => Hash::make('password123'),
                'role' => 'Cashier',
                'role_id' => $roleModels['cashier']->id,
                'status' => 'active',
                'max_discount_percentage' => 10.00,
            ],
            [
                'name' => 'Vihanga Dilshan',
                'email' => 'stylist@salonvellora.com',
                'phone' => '0740844739',
                'password' => Hash::make('password123'),
                'role' => 'Staff',
                'role_id' => $roleModels['staff']->id,
                'status' => 'active',
                'max_discount_percentage' => 5.00,
            ],
        ];

        foreach ($users as $u) {
            $userRecord = User::withTrashed()->updateOrCreate(['email' => $u['email']], $u);
            if ($userRecord->trashed()) {
                $userRecord->restore();
            }
        }

        // 4. Staff Profiles
        $staffMembers = [
            ['name' => 'Vihanga Dilshan', 'phone' => '0740844739', 'email' => 'vihanga@salonvellora.com', 'designation' => 'Salon Director & Bridal Specialist', 'specialization' => 'Bridal Dressing, High-Fashion Styling', 'base_salary' => 85000, 'commission_rate' => 10.0],
            ['name' => 'Sachini Senanayake', 'phone' => '0771122334', 'email' => 'sachini@salonvellora.com', 'designation' => 'Senior Hair Stylist', 'specialization' => 'Keratin, Hair Color, Layered Cuts', 'base_salary' => 60000, 'commission_rate' => 8.0],
            ['name' => 'Kaveesha Rasanjali', 'phone' => '0769988776', 'email' => 'kaveesha@salonvellora.com', 'designation' => 'Esthetician & Nail Artist', 'specialization' => 'Gold Facials, Acrylic Nails, Gel Art', 'base_salary' => 55000, 'commission_rate' => 7.5],
            ['name' => 'Dilrukshi Gunawardena', 'phone' => '0714455667', 'email' => 'dilrukshi@salonvellora.com', 'designation' => 'Bridal Makeup Artist', 'specialization' => 'HD Bridal Makeup, Airbrush', 'base_salary' => 65000, 'commission_rate' => 9.0],
        ];

        foreach ($staffMembers as $s) {
            Staff::updateOrCreate(['name' => $s['name']], $s);
        }

        // 5. Service Categories & Services
        $categories = [
            ['name' => 'Bridal Dressing', 'slug' => 'bridal-dressing', 'description' => 'Signature bridal makeovers, dressing & jewellery setup', 'display_order' => 1],
            ['name' => 'Hair Care & Styling', 'slug' => 'hair-care', 'description' => 'Precision haircuts, luxury blow dry & creative styling', 'display_order' => 2],
            ['name' => 'Hair Treatments', 'slug' => 'hair-treatments', 'description' => 'Keratin smooth, Botox hair revival & deep conditioning', 'display_order' => 3],
            ['name' => 'Facial & Skincare', 'slug' => 'facial-skincare', 'description' => 'Radiance glow, deep cleansing & anti-aging facials', 'display_order' => 4],
            ['name' => 'Nails & Spa', 'slug' => 'nails-spa', 'description' => 'Luxury gel nail art, acrylics, manicure & pedicure spa', 'display_order' => 5],
            ['name' => 'Makeup & Beauty', 'slug' => 'makeup-beauty', 'description' => 'Party glam, evening elegance & threading', 'display_order' => 6],
        ];

        $catModels = [];
        foreach ($categories as $c) {
            $catModels[$c['slug']] = ServiceCategory::updateOrCreate(['slug' => $c['slug']], $c);
        }

        $services = [
            ['category_id' => $catModels['bridal-dressing']->id, 'name' => 'Royal Kandyan Bridal Dressing', 'code' => 'SV-BR-01', 'price' => 55000, 'duration_minutes' => 210, 'discount' => 0, 'is_featured' => true, 'description' => 'Complete traditional Kandyan bride with authentic handmade jewellery, headpiece setting & bouquet arrangement'],
            ['category_id' => $catModels['bridal-dressing']->id, 'name' => 'Western / Indian Bridal Dressing', 'code' => 'SV-BR-02', 'price' => 45000, 'duration_minutes' => 180, 'discount' => 0, 'is_featured' => true, 'description' => 'Flawless HD bridal makeover, veil pinning, hair styling & gown drapery'],
            ['category_id' => $catModels['bridal-dressing']->id, 'name' => 'Bridal Retouch & Changeover', 'code' => 'SV-BR-03', 'price' => 18000, 'duration_minutes' => 60, 'discount' => 0, 'is_featured' => false, 'description' => 'Evening homecoming or reception dress change with makeup retouch and hair styling'],
            
            ['category_id' => $catModels['hair-care']->id, 'name' => 'Precision Hair Cut & Luxury Blow Dry', 'code' => 'SV-HC-01', 'price' => 3500, 'duration_minutes' => 45, 'discount' => 0, 'is_featured' => true, 'description' => 'Consultation, scalp massage, precision cut and volumizing blow dry'],
            ['category_id' => $catModels['hair-care']->id, 'name' => 'Full Global Hair Color', 'code' => 'SV-HC-02', 'price' => 12500, 'duration_minutes' => 90, 'discount' => 0, 'is_featured' => false, 'description' => 'Premium ammonia-free permanent rich hair color'],
            ['category_id' => $catModels['hair-care']->id, 'name' => 'Balayage & Highlights', 'code' => 'SV-HC-03', 'price' => 18500, 'duration_minutes' => 120, 'discount' => 0, 'is_featured' => true, 'description' => 'Sun-kissed hand-painted highlights with high gloss toner'],

            ['category_id' => $catModels['hair-treatments']->id, 'name' => 'Brazilian Keratin Smoothing', 'code' => 'SV-HT-01', 'price' => 22000, 'duration_minutes' => 150, 'discount' => 1000, 'is_featured' => true, 'description' => 'Eliminates 95% frizz, delivers mirror-like shine lasting up to 5 months'],
            ['category_id' => $catModels['hair-treatments']->id, 'name' => 'Permanent Hair Smoothening', 'code' => 'SV-HT-02', 'price' => 18000, 'duration_minutes' => 180, 'discount' => 0, 'is_featured' => false, 'description' => 'Silky sleek straight hair with enriched collagen protection'],
            
            ['category_id' => $catModels['facial-skincare']->id, 'name' => 'Luxury 24K Gold Bridal Facial', 'code' => 'SV-FC-01', 'price' => 8500, 'duration_minutes' => 75, 'discount' => 500, 'is_featured' => true, 'description' => 'Pure 24K gold foil infusion, ultrasonic firming and lymphatic drainage for unmatched radiant bridal glow'],
            ['category_id' => $catModels['facial-skincare']->id, 'name' => 'Deep Clarifying Herbal Cleanse', 'code' => 'SV-FC-02', 'price' => 6000, 'duration_minutes' => 60, 'discount' => 0, 'is_featured' => false, 'description' => 'Steam extraction, botanical purifying mask and hydrating vitamin serum'],

            ['category_id' => $catModels['nails-spa']->id, 'name' => 'Luxury Gel Nail Art & Extensions', 'code' => 'SV-NL-01', 'price' => 7500, 'duration_minutes' => 90, 'discount' => 0, 'is_featured' => true, 'description' => 'Sculpted gel extensions with custom bridal crystals and chrome finish'],
            ['category_id' => $catModels['nails-spa']->id, 'name' => 'Aromatherapy Manicure & Pedicure Spa', 'code' => 'SV-NL-02', 'price' => 5500, 'duration_minutes' => 75, 'discount' => 0, 'is_featured' => false, 'description' => 'Rose petal foot soak, exfoliating scrub, paraffin mask and massage'],

            ['category_id' => $catModels['makeup-beauty']->id, 'name' => 'Party & Occasion Glamour Makeup', 'code' => 'SV-MK-01', 'price' => 12000, 'duration_minutes' => 60, 'discount' => 0, 'is_featured' => false, 'description' => 'Full glam evening makeup with premium mink lashes and setting spray'],
            ['category_id' => $catModels['makeup-beauty']->id, 'name' => 'Full Face Threading & Eyebrow Shaping', 'code' => 'SV-MK-02', 'price' => 1500, 'duration_minutes' => 25, 'discount' => 0, 'is_featured' => false, 'description' => 'Gentle threading with cooling aloe vera massage'],
        ];

        $serviceModels = [];
        foreach ($services as $s) {
            $serviceModels[$s['code']] = Service::updateOrCreate(['code' => $s['code']], $s);
        }

        // 6. Service Packages
        $pkg1 = ServicePackage::updateOrCreate(
            ['name' => 'Royal Kandyan Bridal Package'],
            [
                'description' => 'The ultimate grand Kandyan bride experience including traditional dressing, 24K gold facial, gel nail art and pre-bridal hair treatment.',
                'original_total' => 93000,
                'package_price' => 78000,
                'discount' => 15000,
                'duration_minutes' => 360,
                'is_featured' => true,
                'status' => 'active',
            ]
        );
        $pkg1->services()->sync([
            $serviceModels['SV-BR-01']->id => ['quantity' => 1],
            $serviceModels['SV-FC-01']->id => ['quantity' => 1],
            $serviceModels['SV-NL-01']->id => ['quantity' => 1],
            $serviceModels['SV-HT-01']->id => ['quantity' => 1],
        ]);

        $pkg2 = ServicePackage::updateOrCreate(
            ['name' => 'Western Bridal Glow Package'],
            [
                'description' => 'Western bride makeover including HD bridal makeup, veil styling, gold facial and luxury manicure/pedicure spa.',
                'original_total' => 67500,
                'package_price' => 55000,
                'discount' => 12500,
                'duration_minutes' => 300,
                'is_featured' => true,
                'status' => 'active',
            ]
        );
        $pkg2->services()->sync([
            $serviceModels['SV-BR-02']->id => ['quantity' => 1],
            $serviceModels['SV-FC-01']->id => ['quantity' => 1],
            $serviceModels['SV-NL-02']->id => ['quantity' => 1],
        ]);

        // 7. Product Categories, Brands, Products, Suppliers
        $pcat1 = ProductCategory::updateOrCreate(['slug' => 'hair-care'], ['name' => 'Hair Care', 'description' => 'Professional Shampoos, Conditioners & Serums']);
        $pcat2 = ProductCategory::updateOrCreate(['slug' => 'skincare-cosmetics'], ['name' => 'Skincare & Cosmetics', 'description' => 'Luxury Foundations, Serums & Creams']);

        $b1 = Brand::updateOrCreate(['name' => "L'Oréal Professionnel"]);
        $b2 = Brand::updateOrCreate(['name' => 'Keune Haircosmetics']);
        $b3 = Brand::updateOrCreate(['name' => 'MAC Cosmetics']);
        $b4 = Brand::updateOrCreate(['name' => 'Salon Vellora Signature']);

        $supplier = Supplier::updateOrCreate(
            ['name' => 'Lanka Beauty Distributors (Pvt) Ltd'],
            [
                'contact_person' => 'Sunil Wickramasinghe',
                'phone' => '0112345678',
                'email' => 'sales@lankabeauty.lk',
                'address' => '45 Galle Road, Colombo 03',
                'opening_balance' => 0,
                'current_balance' => 0,
                'status' => 'active',
            ]
        );

        $products = [
            ['name' => "L'Oréal Serie Expert Absolut Repair Shampoo 500ml", 'category_id' => $pcat1->id, 'brand_id' => $b1->id, 'sku' => 'LOR-ABS-500', 'barcode' => '790011223301', 'cost_price' => 5200, 'retail_price' => 6800, 'stock_quantity' => 24, 'alert_quantity' => 5, 'unit' => 'bottle'],
            ['name' => "L'Oréal Serie Expert Absolut Repair Mask 500ml", 'category_id' => $pcat1->id, 'brand_id' => $b1->id, 'sku' => 'LOR-MSK-500', 'barcode' => '790011223302', 'cost_price' => 6000, 'retail_price' => 7800, 'stock_quantity' => 18, 'alert_quantity' => 4, 'unit' => 'tub'],
            ['name' => 'Keune Care Satin Oil Treatment 95ml', 'category_id' => $pcat1->id, 'brand_id' => $b2->id, 'sku' => 'KEU-SAT-095', 'barcode' => '790011223303', 'cost_price' => 4200, 'retail_price' => 5500, 'stock_quantity' => 15, 'alert_quantity' => 5, 'unit' => 'bottle'],
            ['name' => 'MAC Studio Fix Fluid SPF 15 Foundation 30ml', 'category_id' => $pcat2->id, 'brand_id' => $b3->id, 'sku' => 'MAC-SFF-030', 'barcode' => '790011223304', 'cost_price' => 11000, 'retail_price' => 14000, 'stock_quantity' => 10, 'alert_quantity' => 3, 'unit' => 'bottle'],
            ['name' => 'Salon Vellora Pure Argan Glow Hair Serum 100ml', 'category_id' => $pcat1->id, 'brand_id' => $b4->id, 'sku' => 'SV-ARG-100', 'barcode' => '790011223305', 'cost_price' => 2500, 'retail_price' => 4200, 'stock_quantity' => 35, 'alert_quantity' => 8, 'unit' => 'bottle'],
            ['name' => 'Salon Vellora Rose Petal Hydrating Facial Mist 120ml', 'category_id' => $pcat2->id, 'brand_id' => $b4->id, 'sku' => 'SV-ROS-120', 'barcode' => '790011223306', 'cost_price' => 1600, 'retail_price' => 2800, 'stock_quantity' => 40, 'alert_quantity' => 10, 'unit' => 'bottle'],
        ];

        foreach ($products as $p) {
            Product::updateOrCreate(['sku' => $p['sku']], $p);
        }

        // 8. Sample Customers
        $customers = [
            ['name' => 'Dilini Perera', 'phone' => '0771234567', 'email' => 'dilini.perera@gmail.com', 'address' => 'Galle Fort, Galle', 'loyalty_points' => 350, 'total_spend' => 35000, 'total_visits' => 4],
            ['name' => 'Chathurika Silva', 'phone' => '0719876543', 'email' => 'chathurika.s@gmail.com', 'address' => 'Pinnaduwa, Galle', 'loyalty_points' => 780, 'total_spend' => 78000, 'total_visits' => 2],
            ['name' => 'Sanduni Fernando', 'phone' => '0765551234', 'email' => 'sanduni.f@outlook.com', 'address' => 'Unawatuna, Galle', 'loyalty_points' => 120, 'total_spend' => 12000, 'total_visits' => 2],
            ['name' => 'Tharushi Jayawardena', 'phone' => '0741122334', 'email' => 'tharushi.j@gmail.com', 'address' => 'Karapitiya, Galle', 'loyalty_points' => 500, 'total_spend' => 50000, 'total_visits' => 1],
        ];

        foreach ($customers as $c) {
            Customer::updateOrCreate(['phone' => $c['phone']], $c);
        }

        // 9. Expense Categories
        $expCats = ['Salon Utilities & Electricity', 'Salon Supplies & Foil', 'Rent & Premises Maintenance', 'Marketing & Social Media', 'Staff Refreshments & Tea'];
        foreach ($expCats as $ec) {
            ExpenseCategory::updateOrCreate(['name' => $ec], ['name' => $ec]);
        }

        // 10. Default Settings
        $settings = [
            // General & Salon Details
            ['group' => 'general', 'key' => 'salon_name', 'value' => 'Salon Vellora', 'type' => 'string'],
            ['group' => 'general', 'key' => 'business_type', 'value' => 'Ladies Salon & Bridal Dressing', 'type' => 'string'],
            ['group' => 'general', 'key' => 'salon_address', 'value' => 'Galwatawaththa Junction, Pinnaduwa, Galle, Sri Lanka', 'type' => 'string'],
            ['group' => 'general', 'key' => 'salon_phone', 'value' => '074 084 4739', 'type' => 'string'],
            ['group' => 'general', 'key' => 'salon_email', 'value' => 'salonvellora26@gmail.com', 'type' => 'string'],
            ['group' => 'general', 'key' => 'currency', 'value' => 'LKR', 'type' => 'string'],
            ['group' => 'general', 'key' => 'business_hours', 'value' => 'Tuesday - Sunday: 9:00 AM - 7:00 PM (Monday Closed)', 'type' => 'string'],
            
            // Invoicing & Receipts
            ['group' => 'invoice', 'key' => 'invoice_prefix', 'value' => 'SV-INV-', 'type' => 'string'],
            ['group' => 'invoice', 'key' => 'invoice_footer', 'value' => 'Thank you for visiting Salon Vellora! Feel beautiful inside out.', 'type' => 'string'],
            ['group' => 'invoice', 'key' => 'invoice_terms', 'value' => '1. Goods sold are not returnable. 2. Please retain receipt for loyalty verification.', 'type' => 'string'],
            
            // Quotations
            ['group' => 'quotation', 'key' => 'quotation_prefix', 'value' => 'QTN-', 'type' => 'string'],
            ['group' => 'quotation', 'key' => 'quotation_validity_days', 'value' => '30', 'type' => 'integer'],
            ['group' => 'quotation', 'key' => 'quotation_default_terms', 'value' => "1. 50% advance deposit is required to confirm the bridal booking date.\n2. Balance amount must be settled on or before the dressing day.\n3. Date changes are subject to stylist availability.", 'type' => 'string'],

            // POS & Billing
            ['group' => 'pos', 'key' => 'tax_percentage', 'value' => '0.00', 'type' => 'number'],
            ['group' => 'pos', 'key' => 'service_charge_percentage', 'value' => '0.00', 'type' => 'number'],
            ['group' => 'pos', 'key' => 'cashier_max_discount', 'value' => '10', 'type' => 'number'],
            ['group' => 'pos', 'key' => 'manager_max_discount', 'value' => '30', 'type' => 'number'],

            // Loyalty
            ['group' => 'loyalty', 'key' => 'loyalty_spend_per_point', 'value' => '100', 'type' => 'number'],
            ['group' => 'loyalty', 'key' => 'loyalty_redemption_value', 'value' => '1', 'type' => 'number'],
        ];

        foreach ($settings as $s) {
            Setting::updateOrCreate(['key' => $s['key']], $s);
        }

        // 11. Luxury Bridal & Item Rentals (24h Standard Rent)
        $rentalItems = [
            [
                'name' => 'Kanchipuram Silk Bridal Saree (Royal Maroon & Gold Zari)',
                'code' => 'RNT-SAR-001',
                'category' => 'Sarees & Lehengas',
                'description' => 'Pure handwoven Kanchipuram silk saree with rich antique gold zari border. Includes matching pre-stitched bridal blouse (Size 34-38).',
                'rental_price_per_24h' => 7500.00,
                'security_deposit' => 10000.00,
                'late_fee_per_day' => 1500.00,
                'total_quantity' => 2,
                'available_quantity' => 2,
                'condition' => 'Pristine',
                'image' => 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?w=800&q=80&auto=format&fit=crop',
                'status' => 'available',
            ],
            [
                'name' => 'Traditional Kandyan Mul Anduma Saree Set (Ivory & Gold)',
                'code' => 'RNT-SAR-002',
                'category' => 'Sarees & Lehengas',
                'description' => 'Authentic Sri Lankan Kandyan bridal saree with exquisite hand embroidery, pearl beading, and custom frill work.',
                'rental_price_per_24h' => 8500.00,
                'security_deposit' => 12000.00,
                'late_fee_per_day' => 2000.00,
                'total_quantity' => 1,
                'available_quantity' => 1,
                'condition' => 'Pristine',
                'image' => 'https://images.unsplash.com/photo-1583939003579-730e3918a45a?w=800&q=80&auto=format&fit=crop',
                'status' => 'available',
            ],
            [
                'name' => '24K Gold Plated Heritage Bridal Jewellery Set (7 Pcs)',
                'code' => 'RNT-JWL-001',
                'category' => 'Jewellery Sets',
                'description' => 'Grand bridal set: Choker necklace, long mala, matching jhumka earrings, maang tikka, haath phool, and nath.',
                'rental_price_per_24h' => 4500.00,
                'security_deposit' => 6000.00,
                'late_fee_per_day' => 1000.00,
                'total_quantity' => 3,
                'available_quantity' => 3,
                'condition' => 'Excellent',
                'image' => 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=800&q=80&auto=format&fit=crop',
                'status' => 'available',
            ],
            [
                'name' => 'Royal Emerald Velvet Bridal Lehenga Set',
                'code' => 'RNT-LHG-001',
                'category' => 'Sarees & Lehengas',
                'description' => 'Deep emerald green heavy velvet lehenga with zardozi and sequin embroidery, double net dupatta.',
                'rental_price_per_24h' => 9500.00,
                'security_deposit' => 15000.00,
                'late_fee_per_day' => 2000.00,
                'total_quantity' => 1,
                'available_quantity' => 1,
                'condition' => 'Pristine',
                'image' => 'https://images.unsplash.com/photo-1595777457583-95e059d581b8?w=800&q=80&auto=format&fit=crop',
                'status' => 'available',
            ],
            [
                'name' => 'Handcrafted Crystal Princess Bridal Tiara / Crown',
                'code' => 'RNT-ACC-001',
                'category' => 'Hair & Veils',
                'description' => 'Swarovski-element crystal bridal crown with silver finish. Lightweight and secure for wedding day wear.',
                'rental_price_per_24h' => 2500.00,
                'security_deposit' => 4000.00,
                'late_fee_per_day' => 500.00,
                'total_quantity' => 4,
                'available_quantity' => 4,
                'condition' => 'Excellent',
                'image' => 'https://images.unsplash.com/photo-1535295972055-1c762f4483e5?w=800&q=80&auto=format&fit=crop',
                'status' => 'available',
            ],
            [
                'name' => 'Cathedral Bridal Lace Veil (3.5 Meters)',
                'code' => 'RNT-ACC-002',
                'category' => 'Hair & Veils',
                'description' => 'Dramatic European illusion soft tulle veil with French scalloped chantilly lace border.',
                'rental_price_per_24h' => 3000.00,
                'security_deposit' => 5000.00,
                'late_fee_per_day' => 750.00,
                'total_quantity' => 2,
                'available_quantity' => 2,
                'condition' => 'Pristine',
                'image' => 'https://images.unsplash.com/photo-1519741497674-611481863552?w=800&q=80&auto=format&fit=crop',
                'status' => 'available',
            ],
            [
                'name' => 'Luxury Kundan & Pearl Choker Necklace with Jhumkas',
                'code' => 'RNT-JWL-002',
                'category' => 'Jewellery Sets',
                'description' => 'Intricate Meenakari reverse with uncut glass Kundan stones and natural freshwater seed pearls.',
                'rental_price_per_24h' => 3500.00,
                'security_deposit' => 5000.00,
                'late_fee_per_day' => 800.00,
                'total_quantity' => 2,
                'available_quantity' => 2,
                'condition' => 'Excellent',
                'image' => 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=800&q=80&auto=format&fit=crop',
                'status' => 'available',
            ],
            [
                'name' => 'Bridal Velvet Clutch & Crystal Brooch Set',
                'code' => 'RNT-ACC-003',
                'category' => 'Bridal Accessories',
                'description' => 'Custom embroidered rose gold clutch bag with removable crystal chain and matching hair brooch.',
                'rental_price_per_24h' => 1800.00,
                'security_deposit' => 3000.00,
                'late_fee_per_day' => 400.00,
                'total_quantity' => 3,
                'available_quantity' => 3,
                'condition' => 'Good',
                'image' => 'https://images.unsplash.com/photo-1566150905458-1bf1fc113f0d?w=800&q=80&auto=format&fit=crop',
                'status' => 'available',
            ],
        ];

        foreach ($rentalItems as $ri) {
            RentalItem::updateOrCreate(['code' => $ri['code']], $ri);
        }

        $this->call(BeautyProductsSeeder::class);
        $this->call(SalonVelloraServicesSeeder::class);
    }
}
