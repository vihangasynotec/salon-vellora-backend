<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Expense::with(['category', 'user'])->orderBy('expense_date', 'desc');

        if ($request->has('category_id') && $request->category_id !== '') {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('expense_date', [$request->start_date, $request->end_date]);
        }

        $expenses = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $expenses,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:expense_categories,id',
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $validated['expense_number'] = 'EXP-' . date('Ymd') . '-' . strtoupper(Str::random(4));
        $validated['created_by'] = $request->user()->id;

        $expense = Expense::create($validated);

        AuditLogService::log('create', 'expenses', $expense->id, null, ['title' => $expense->title, 'amount' => $expense->amount], $request->user()->name);

        return response()->json([
            'success' => true,
            'message' => 'Expense logged successfully.',
            'data' => $expense->load('category'),
        ], 201);
    }

    public function destroy($id): JsonResponse
    {
        $expense = Expense::findOrFail($id);
        $expense->delete();

        AuditLogService::log('delete', 'expenses', $id, null, null);

        return response()->json([
            'success' => true,
            'message' => 'Expense deleted successfully.',
        ]);
    }

    public function getCategories(): JsonResponse
    {
        $cats = ExpenseCategory::withCount('expenses')->get();
        return response()->json(['success' => true, 'data' => $cats]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:expense_categories,name',
            'description' => 'nullable|string',
        ]);

        $category = ExpenseCategory::create($validated);

        return response()->json(['success' => true, 'message' => 'Expense category created.', 'data' => $category], 201);
    }
}
