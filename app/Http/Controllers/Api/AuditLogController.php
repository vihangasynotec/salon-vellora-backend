<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('user')->orderBy('created_at', 'desc');

        if ($request->has('module') && $request->module !== '') {
            $query->where('module', $request->module);
        }

        if ($request->has('action') && $request->action !== '') {
            $query->where('action', $request->action);
        }

        if ($request->has('search') && $request->search !== '') {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('user_name', 'LIKE', "%{$s}%")
                  ->orWhere('action', 'LIKE', "%{$s}%")
                  ->orWhere('module', 'LIKE', "%{$s}%");
            });
        }

        $logs = $query->paginate($request->get('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }
}
