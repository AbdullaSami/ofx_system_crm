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

            'client'          => $this->client->client_name ?? null,
            'employee'        => $this->employee->employee_name ?? null,

            'services'        => $this->services->map(fn($service) => [
                'id'         => $service->id,
                'name'       => $service->name,
                'unit_price' => $service->pivot->unit_price,
                'layout'     => $service->pivot->layout_id ? [
                    'id'   => $service->pivot->layout_id,
                    'name' => $service->pivot->layout?->name,
                    'fields' => $service->pivot->layoutAnswers?->map(fn($answer) => [
                        'layout_field_id' => $answer->layout_field_id,
                        'field_name'      => $answer->layoutField?->name,
                        'answer'          => $answer->answer,
                    ]),
                ] : null,
            ]),
        ];
    }
}
