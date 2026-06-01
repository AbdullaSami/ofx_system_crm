<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'contract_number' => $this->contract_number,
            'status'          => $this->status,
            'start_date'      => $this->start_date?->toDateString(),
            'end_date'        => $this->end_date?->toDateString(),
            'value'           => $this->value,
            'created_at'      => $this->created_at?->toDateTimeString(),

            // Conditionally load relations only if they were eager-loaded
            // This prevents N+1 if you forget `with()` somewhere
            'client'          => $this->client->client_name ?? null,
            'employee'        => $this->employee->employee_name ?? null,
        ];
    }
}
