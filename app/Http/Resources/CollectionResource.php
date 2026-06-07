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
            'id'               => $this->id,
            'contract_id'      => $this->contract_id,
            'client_id'        => $this->client_id,
            'contract_number'  => $this->whenLoaded('contract', fn() => $this->contract->contract_number),
            'employee_name'    => $this->whenLoaded('contract', fn() => $this->contract->employee?->name),
            'client_name'      => $this->whenLoaded('client', fn() => $this->client->client_name),
            'amount_due'       => $this->amount_due,
            'amount_collected' => $this->amount_collected,
            'due_date'         => $this->due_date,
            'collection_date'  => $this->collection_date,
            'status'           => $this->status,
            'payment_method'   => $this->payment_method,
            'reference_number' => $this->reference_number,
            'notes'            => $this->notes,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
