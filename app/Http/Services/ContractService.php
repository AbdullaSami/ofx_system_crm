<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;

use App\Models\Contract;
use App\Models\Client;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

// app/Services/ContractService.php
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
                'client_id'       => $clientId,
                'employee_id'     => $data['employee_id'],
                'contract_number' => $contractNumber,
                'start_date'      => $data['start_date'],
                'end_date'        => $data['end_date'],
                'amount'          => $data['amount'],
                'discount'        => $data['discount'] ?? null,
                'notes'           => $data['notes'] ?? null,
                'status'          => $data['status'],
                'signed_by'       => $data['signed_by'] ?? null,
                'payment_method'  => $data['payment_method'] ?? null,
            ]);

            // Bulk attach services
            if (! empty($data['services'])) {
                $syncData = collect($data['services'])
                    ->mapWithKeys(fn($s) => [
                        $s['id'] => ['unit_price' => $s['unit_price']]
                    ])->all();

                $contract->services()->sync($syncData);
            }

            return $contract;
        });
    }

    private function createClient(array $data): Client
    {
        return Client::create([
            'client_name' => $data['client_name']
                ?? "{$data['first_name']} {$data['last_name']}",
            'first_name'  => $data['first_name'],
            'last_name'   => $data['last_name'],
            'email'       => $data['email'],
            'phone'       => $data['phone'],
            'company'     => $data['company'] ?? null,
            'whatsapp'    => $data['whatsapp'] ?? null,
            'lead_id'     => $data['lead_id'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'user_id'     => $data['user_id'] ?? null,
        ]);
    }

    private function generateContractNumber(): string
    {
        $latest = Contract::lockForUpdate()->latest('id')->value('contract_number');
        $next   = $latest ? ((int) substr($latest, -4)) + 1 : 1;

        return 'CTR-' . now()->year . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
