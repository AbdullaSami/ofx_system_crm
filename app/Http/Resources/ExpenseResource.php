<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'expense_type' => $this->expense_type,
            'amount' => (float) $this->amount,
            'expense_date' => $this->expense_date->toDateString(),
            'description' => $this->description,

            'treasury' => $this->whenLoaded('treasury', fn () => [
                'id' => $this->treasury->id,
                'name' => $this->treasury->account_name,
                'balance' => (float) $this->treasury->balance,
            ]),

            'expensable_type' => $this->expensable_type
                ? class_basename($this->expensable_type)
                : null,
            'expensable' => $this->whenLoaded('expensable', fn () => $this->expensable ? [
                'id' => $this->expensable->id,
                'name' => $this->expensable->name ?? null,
            ] : null),

            'attachments' => ExpenseAttachmentResource::collection($this->whenLoaded('attachments')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
