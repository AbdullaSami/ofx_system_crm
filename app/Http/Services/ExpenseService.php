<?php

namespace App\Http\Services;

use App\Models\Collection;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\TreasuryAccount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    /**
     * Map an expense_type to the model class it relates to.
     * 'general' expenses have no related model.
     */
    protected function expensableClassFor(string $expenseType): ?string
    {
        return match ($expenseType) {
            Expense::TYPE_WAGE => Employee::class,
            Expense::TYPE_REFUND => Collection::class,
            Expense::TYPE_GENERAL => null,
            Expense::TYPE_PAY_BILL => null,
            default => null,
        };
    }

    public function list(array $filters = [])
    {
        $query = Expense::query()->with(['treasury', 'expensable', 'attachments']);

        if (!empty($filters['treasury_id'])) {
            $query->where('treasury_id', $filters['treasury_id']);
        }

        if (!empty($filters['expense_type'])) {
            $query->where('expense_type', $filters['expense_type']);
        }

        if (!empty($filters['from'])) {
            $query->whereDate('expense_date', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate('expense_date', '<=', $filters['to']);
        }

        return $query->latest('expense_date')->paginate($filters['per_page'] ?? 15);
    }

    /**
     * @param array $data validated request data
     * @param UploadedFile[] $files receipt attachments
     */
    public function create(array $data, array $files = []): Expense
    {
        return DB::transaction(function () use ($data, $files) {
            $treasury = TreasuryAccount::lockForUpdate()->findOrFail($data['treasury_id']);

            if ($treasury->balance < $data['amount']) {
                throw ValidationException::withMessages([
                    'amount' => 'Treasury balance is insufficient for this expense.',
                ]);
            }

            $expensableClass = $this->expensableClassFor($data['expense_type']);

            $expense = Expense::create([
                'treasury_id' => $treasury->id,
                'expense_type' => $data['expense_type'],
                'expensable_type' => $expensableClass,
                'expensable_id' => $expensableClass ? $data['expensable_id'] : null,
                'amount' => $data['amount'],
                'expense_date' => $data['expense_date'],
                'description' => $data['description'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            $treasury->decrement('balance', $data['amount']);

            $this->storeAttachments($expense, $files);

            return $expense->load(['treasury', 'expensable', 'attachments']);
        });
    }

    public function update(Expense $expense, array $data, array $files = []): Expense
    {
        return DB::transaction(function () use ($expense, $data, $files) {
            $newTreasuryId = $data['treasury_id'] ?? $expense->treasury_id;
            $newAmount = $data['amount'] ?? $expense->amount;

            // Refund the old treasury before applying the new charge.
            $oldTreasury = TreasuryAccount::lockForUpdate()->findOrFail($expense->treasury_id);
            $oldTreasury->increment('balance', $expense->amount);

            $newTreasury = $newTreasuryId === $oldTreasury->id
                ? $oldTreasury
                : TreasuryAccount::lockForUpdate()->findOrFail($newTreasuryId);

            if ($newTreasury->balance < $newAmount) {
                throw ValidationException::withMessages([
                    'amount' => 'Treasury balance is insufficient for this expense.',
                ]);
            }

            $expenseType = $data['expense_type'] ?? $expense->expense_type;
            $expensableClass = $this->expensableClassFor($expenseType);

            $expense->update([
                'treasury_id' => $newTreasury->id,
                'expense_type' => $expenseType,
                'expensable_type' => $expensableClass,
                'expensable_id' => $expensableClass
                    ? ($data['expensable_id'] ?? $expense->expensable_id)
                    : null,
                'amount' => $newAmount,
                'expense_date' => $data['expense_date'] ?? $expense->expense_date,
                'description' => $data['description'] ?? $expense->description,
            ]);

            $newTreasury->decrement('balance', $newAmount);

            $this->storeAttachments($expense, $files);

            return $expense->load(['treasury', 'expensable', 'attachments']);
        });
    }

    public function delete(Expense $expense): void
    {
        DB::transaction(function () use ($expense) {
            $treasury = TreasuryAccount::lockForUpdate()->findOrFail($expense->treasury_id);
            $treasury->increment('balance', $expense->amount);

            foreach ($expense->attachments as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            $expense->delete();
        });
    }

    public function deleteAttachment(ExpenseAttachment $attachment): void
    {
        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();
    }

    /**
     * @param UploadedFile[] $files
     */
    protected function storeAttachments(Expense $expense, array $files): void
    {
        foreach ($files as $file) {
            $path = $file->store("expenses/{$expense->id}/receipts", 'public');

            ExpenseAttachment::create([
                'expense_id' => $expense->id,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }
}
