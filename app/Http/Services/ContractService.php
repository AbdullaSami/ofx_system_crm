<?php

namespace App\Http\Services;

use App\Models\Client;
use App\Models\Contract;
use App\Models\LayoutAnswer;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

class ContractService
{
    public function create(array $data): Contract
    {
        return DB::transaction(function () use ($data) {

            // Resolve or create client
            $clientId = $data['client_id'] ?? $this->createClient($data)->id;

            // Generate number safely inside transaction
            $contractNumber = $this->generateContractNumber();

            $contract = Contract::create([
                'client_id' => $clientId,
                'employee_id' => $data['employee_id'],
                'contract_number' => $contractNumber,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'amount' => $data['amount'],
                'discount' => $data['discount'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => $data['status'],
                'signed_by' => $data['signed_by'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
            ]);

            // Bulk attach services
            if (! empty($data['services'])) {
                $serviceIds = Service::whereIn('slug', collect($data['services'])->pluck('slug'))
                    ->pluck('id', 'slug'); // ['some-slug' => 1, 'other-slug' => 2]

                $syncData = collect($data['services'])
                    ->mapWithKeys(fn ($s) => [
                        $serviceIds[$s['slug']] => [
                            'unit_price' => $s['unit_price'],
                            'quantity' => $s['quantity'] ?? 1,
                            'discount' => $s['discount'] ?? 0,
                            'billing_frequency' => $s['billing_frequency'] ?? 'monthly',
                            'status' => $s['status'] ?? 'active',
                        ],
                    ])->all();

                $contract->services()->sync($syncData);

                // Store layout answers
                $this->storeLayoutAnswers(
                    $contract,
                    $data['services']
                );
            }

            return $contract;
        });
    }

    public function update(Contract $contract, array $data): Contract
    {
        return DB::transaction(function () use ($contract, $data) {

            $contract->update([
                'employee_id' => $data['employee_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'amount' => $data['amount'],
                'discount' => $data['discount'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'status' => $data['status'],
                'signed_by' => $data['signed_by'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
            ]);

            if (isset($data['services'])) {

                $serviceIds = Service::whereIn('slug', collect($data['services'])->pluck('slug'))
                    ->pluck('id', 'slug');

                $syncData = collect($data['services'])
                    ->mapWithKeys(fn ($s) => [
                        $serviceIds[$s['slug']] => [
                            'unit_price' => $s['unit_price'],
                            'quantity' => $s['quantity'] ?? 1,
                            'discount' => $s['discount'] ?? 0,
                            'billing_frequency' => $s['billing_frequency'] ?? 'monthly',
                            'status' => $s['status'] ?? 'active',
                        ],
                    ])->all();

                $contract->services()->sync($syncData);

                $contract->layoutAnswers()->delete();

                // Inject the resolved IDs back so storeLayoutAnswers can use them
                $servicesWithIds = collect($data['services'])
                    ->map(fn ($s) => array_merge($s, ['id' => $serviceIds[$s['slug']]]))
                    ->all();

                $this->storeLayoutAnswers($contract, $servicesWithIds);
            }

            return $contract->fresh();
        });
    }

    protected function storeLayoutAnswers(Contract $contract, array $services): void
    {
        $answers = [];

        foreach ($services as $service) {

            if (
                empty($service['layout']) ||
                empty($service['layout']['fields'])
            ) {
                continue;
            }

            foreach ($service['layout']['fields'] as $field) {

                $answers[] = [
                    'contract_id' => $contract->id,
                    'layout_field_id' => $field['layout_field_id'],
                    'answer' => $field['answer'] ?? null,
                    'answered_by' => auth()->id(),
                    'answered_at' => now(),
                    'validation_status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (! empty($answers)) {
            LayoutAnswer::insert($answers);
        }
    }

    private function createClient(array $data): Client
    {
        return Client::create([
            'client_name' => $data['client_name']
                ?? "{$data['first_name']} {$data['last_name']}",
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'company' => $data['company'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'lead_id' => $data['lead_id'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'user_id' => $data['user_id'] ?? null,
        ]);
    }

    private function generateContractNumber(): string
    {
        $latest = Contract::lockForUpdate()->latest('id')->value('contract_number');
        $next = $latest ? ((int) substr($latest, -4)) + 1 : 1;

        return 'CTR-'.now()->year.'-'.str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
