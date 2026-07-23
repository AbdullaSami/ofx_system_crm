<?php

namespace App\Http\Controllers\v1;

use Illuminate\Routing\Controller as BaseController;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Http\Concerns\AuthorizesScope;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Http\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends BaseController
{
    use AuthorizesScope;

    public function __construct(protected ExpenseService $expenseService)
    {
        $this->middleware('permission:expenses.view|expenses.view.own')->only('index');
        $this->middleware('permission:expenses.view|expenses.view.own')->only('show');
        $this->middleware('permission:expenses.create')->only('store');
        $this->middleware('permission:expenses.update|expenses.update.own')->only('update');
        $this->middleware('permission:expenses.delete|expenses.delete.own')->only(['destroy', 'destroyAttachment']);
    }

    public function index(Request $request): JsonResponse
    {
        // Apply data-scope via the model scope (filters by created_by for own-scoped users)
        $query = Expense::query()->visibleTo(auth()->user());

        $expenses = $this->expenseService->listScoped(
            $query,
            $request->only(['treasury_id', 'expense_type', 'from', 'to', 'per_page'])
        );

        return response()->json(ExpenseResource::collection($expenses)->response()->getData(true));
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $data               = $request->validated();
        $data['created_by'] = $request->user()?->id;

        $expense = $this->expenseService->create($data, $request->file('attachments', []));

        return response()->json(new ExpenseResource($expense), 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        // Ownership check: own-scoped users may only view their own expenses
        $this->authorizeExpenseAccess($expense, 'view');

        $expense->load(['treasury', 'expensable', 'attachments']);

        return response()->json(new ExpenseResource($expense));
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        // Ownership check: own-scoped users may only update their own expenses
        $this->authorizeExpenseAccess($expense, 'update');

        $expense = $this->expenseService->update(
            $expense,
            $request->validated(),
            $request->file('attachments', [])
        );

        return response()->json(new ExpenseResource($expense));
    }

    public function destroy(Expense $expense): JsonResponse
    {
        // Ownership check: own-scoped users may only delete their own expenses
        $this->authorizeExpenseAccess($expense, 'delete');

        $this->expenseService->delete($expense);

        return response()->json(['message' => 'Expense deleted successfully.']);
    }

    public function destroyAttachment(Expense $expense, ExpenseAttachment $attachment): JsonResponse
    {
        abort_if($attachment->expense_id !== $expense->id, 404);

        // Ownership check: own-scoped users may only delete attachments of their own expenses
        $this->authorizeExpenseAccess($expense, 'delete');

        $this->expenseService->deleteAttachment($attachment);

        return response()->json(['message' => 'Attachment removed successfully.']);
    }

    /**
     * Authorize access to a specific expense record.
     * Expenses are owned by user (created_by), not employee, so we handle this separately.
     */
    private function authorizeExpenseAccess(Expense $expense, string $action): void
    {
        $user = auth()->user();

        // Global permission: can access any expense
        if ($user->can("expenses.{$action}")) {
            return;
        }

        // Own-scoped permission: can only access own expenses (by user ID)
        if ($user->can("expenses.{$action}.own")) {
            abort_if(
                (int) $expense->created_by !== (int) $user->id,
                403,
                "You do not have permission to {$action} this expense."
            );
            return;
        }

        abort(403, "You do not have permission to {$action} expenses.");
    }
}
