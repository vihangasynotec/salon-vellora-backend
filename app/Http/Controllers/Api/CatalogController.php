<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function getCatalog(Request $request): JsonResponse
    {
        $salonInfo = [
            'name' => Setting::get('salon_name', 'Salon Vellora'),
            'type' => Setting::get('business_type', 'Ladies Salon & Bridal Dressing'),
            'location' => Setting::get('salon_address', 'Galwatawaththa Junction, Pinnaduwa, Galle, Sri Lanka'),
            'phone' => Setting::get('salon_phone', '074 084 4739'),
            'email' => Setting::get('salon_email', 'salonvellora26@gmail.com'),
            'hours' => Setting::get('business_hours', 'Tue - Sun: 9:00 AM - 7:00 PM (Mon Closed)'),
            'currency' => Setting::get('currency', 'LKR'),
        ];

        $categories = ServiceCategory::with(['services' => function ($q) {
            $q->where('status', 'active');
        }])->where('status', 'active')->orderBy('display_order')->get();

        $featuredServices = Service::where('is_featured', true)
            ->where('status', 'active')
            ->with('category')
            ->get();

        $packages = ServicePackage::with('services')
            ->where('status', 'active')
            ->get();

        $rentals = \App\Models\RentalItem::where('status', 'available')
            ->where('available_quantity', '>', 0)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'salon' => $salonInfo,
                'categories' => $categories,
                'featured_services' => $featuredServices,
                'packages' => $packages,
                'rentals' => $rentals,
            ]
        ]);
    }
}
