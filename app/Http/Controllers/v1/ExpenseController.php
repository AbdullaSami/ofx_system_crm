<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(protected ExpenseService $expenseService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $expenses = $this->expenseService->list(
            $request->only(['treasury_id', 'expense_type', 'from', 'to', 'per_page'])
        );

        return response()->json(ExpenseResource::collection($expenses)->response()->getData(true));
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;

        $expense = $this->expenseService->create($data, $request->file('attachments', []));

        return response()->json(new ExpenseResource($expense), 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        $expense->load(['treasury', 'expensable', 'attachments']);

        return response()->json(new ExpenseResource($expense));
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        $expense = $this->expenseService->update(
            $expense,
            $request->validated(),
            $request->file('attachments', [])
        );

        return response()->json(new ExpenseResource($expense));
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $this->expenseService->delete($expense);

        return response()->json(['message' => 'Expense deleted successfully.']);
    }

    public function destroyAttachment(Expense $expense, ExpenseAttachment $attachment): JsonResponse
    {
        abort_if($attachment->expense_id !== $expense->id, 404);

        $this->expenseService->deleteAttachment($attachment);

        return response()->json(['message' => 'Attachment removed successfully.']);
    }
}
