<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'best_selling_service'      => $this->resource['best_selling_service'] ?? null,
            'top_sales_by_revenue'      => $this->resource['top_sales_by_revenue'] ?? [],
            'top_sales_by_contracts'    => $this->resource['top_sales_by_contracts'] ?? [],
            'monthly_sales'             => $this->resource['monthly_sales'] ?? [],
            'top_customers'             => $this->resource['top_customers'] ?? [],
            'latest_contracts'          => $this->resource['latest_contracts'] ?? [],
            'lead_sources'              => $this->resource['lead_sources'] ?? [],
            'conversion_rate'           => $this->resource['conversion_rate'] ?? null,
            'registered_collections'    => $this->resource['registered_collections'] ?? null,
            'collected_amount'          => $this->resource['collected_amount'] ?? null,
            'payment_method_comparison' => $this->resource['payment_method_comparison'] ?? [],
            'advertisement_spending'    => $this->resource['advertisement_spending'] ?? [],
        ];
    }
}
