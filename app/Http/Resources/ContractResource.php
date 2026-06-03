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

            'services' => $this->services->map(fn($service) => [
                'id'         => $service->id,
                'name'       => $service->name,
                'unit_price' => $service->pivot->unit_price,
                'layout'     => $this->layoutAnswers
                    ->filter(fn($answer) => $answer->layoutField?->layout?->service_id === $service->id)
                    ->groupBy('layout_id')
                    ->map(fn($answers, $layoutId) => [
                        'id'     => $layoutId,
                        'fields' => $answers->map(fn($answer) => [
                            'layout_field_id' => $answer->layout_field_id,
                            'field_name'      => $answer->layoutField?->field_name,
                            'answer'          => $answer->answer,
                        ])->values(),
                    ])
                    ->values()
                    ->first(),
            ]),
        ];
    }
}
