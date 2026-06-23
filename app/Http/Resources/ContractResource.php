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
            'client_id'          => $this->client->id ?? null,
            'employee'        => $this->employee->employee_name ?? null,


            'services' => $this->services->map(function ($service) {
                $serviceCollections = $service->relationLoaded('collections')
                    ? $service->collections->where('contract_id', $this->id)
                    : $service->collectionsForContract($this->id)->get();

                return [
                'id'         => $service->id,
                'name'       => $service->name,
                'slug'       => $service->slug,
                'unit_price' => $service->pivot->unit_price,
                'collections_count' => $serviceCollections->count(),
                'collections' => $serviceCollections->map(fn($collection) => [
                    'id'          => $collection->id,
                    'amount_due'        => $collection->amount_due,
                    'amount_collected'      => $collection->amount_collected,
                    'due_date'      => $collection->due_date,
                    'collection_date' => $collection->collection_date,
                    'status'        => $collection->status,
                    'payment_method' => $collection->payment_method,
                    'notes'         => $collection->notes,
                ])->values(),
                'layouts' => $this->layoutAnswers
                    ->filter(fn($answer) => $answer->layoutField)
                    ->groupBy(fn($answer) => $answer->layoutField->layout_id)
                    ->map(function ($answers, $layoutId) {
                        return [
                            'layout_id' => $layoutId,
                            'fields' => $answers->map(function ($answer) {
                                return [
                                    'layout_field_id' => $answer->layout_field_id,
                                    'field_name'      => $answer->layoutField->field_name,
                                    'field_type'      => $answer->layoutField->field_type,
                                    'answer'          => $answer->answer,
                                ];
                            })->values(),
                        ];
                    })
                    ->values(),
                ];
            }),

            'collections' => CollectionResource::collection($this->collections),
        ];
    }
}
