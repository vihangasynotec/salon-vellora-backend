<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Sale::with(['customer', 'cashier', 'staff'])->where('is_held', false)->orderBy('id', 'desc');

        if ($request->has('search') && $request->search !== '') {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('invoice_number', 'LIKE', "%{$s}%")
                  ->orWhereHas('customer', function ($cq) use ($s) {
                      $cq->where('name', 'LIKE', "%{$s}%")->orWhere('phone', 'LIKE', "%{$s}%");
                  });
            });
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date . ' 00:00:00', $request->end_date . ' 23:59:59']);
        }

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        $sales = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $sales,
        ]);
    }

    public function show($id): JsonResponse
    {
        $sale = Sale::with(['customer', 'cashier', 'staff', 'items.staff', 'payments', 'quotation'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $sale,
        ]);
    }
}
