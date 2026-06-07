<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this?->id ?? null,
            'contract_number'  => $this?->whenLoaded('contract', fn() => $this->contract->contract_number) ?? null,
            'employee_name'    => $this?->whenLoaded('contract', fn() => $this->contract->employee->name ?? null),
            'client_name'      => $this?->whenLoaded('client', fn() => $this->client->client_name) ?? null,
            'amount_due'       => $this?->amount_due ?? null,
            'amount_collected' => $this?->amount_collected ?? null,
            'due_date'         => $this?->due_date ?? null,
            'collection_date'  => $this?->collection_date ?? null,
            'status'           => $this?->status ?? null,
            'payment_method'   => $this?->payment_method ?? null,
            'reference_number' => $this?->reference_number ?? null,
            'notes'            => $this?->notes ?? null,
        ];
    }
}
