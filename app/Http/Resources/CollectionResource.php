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
            'id' => $this->id,
            'contract_number' => $this->contract->contract_number,
            'client_name' => $this->client->client_name,
            'amount_due' => $this->amount_due,
            'amount_collected' => $this->amount_collected,
            'due_date' => $this->due_date,
            'collection_date' => $this->collection_date,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
        ];
    }
}
