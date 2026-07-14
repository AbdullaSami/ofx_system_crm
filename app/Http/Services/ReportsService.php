<?php

namespace App\Http\Services;

use App\Models\Contract;
use App\Models\Lead;
use App\Models\Collection;
use App\Models\Expense;
use App\Models\Service;
use App\Models\Employee;
use App\Models\Client;
use Illuminate\Support\Facades\DB;

class ReportsService
{
    /**
     * Get all reports for the dashboard based on active filters.
     */
    public function getDashboardData(array $filters): array
    {
        return [
            'best_selling_service'      => $this->getBestSellingService($filters),
            'top_sales_by_revenue'      => $this->getTopSalesByRevenue($filters),
            'top_sales_by_contracts'    => $this->getTopSalesByContracts($filters),
            'monthly_sales'             => $this->getMonthlySales($filters),
            'top_customers'             => $this->getTopCustomers($filters),
            'latest_contracts'          => $this->getLatestContracts($filters),
            'lead_sources'              => $this->getLeadSources($filters),
            'conversion_rate'           => $this->getConversionRate($filters),
            'registered_collections'    => $this->getRegisteredCollections($filters),
            'collected_amount'          => $this->getCollectedAmount($filters),
            'payment_method_comparison' => $this->getPaymentMethodComparison($filters),
            'advertisement_spending'    => $this->getAdvertisementSpending($filters),
        ];
    }

    /**
     * Report 1: Best Selling Service.
     */
    protected function getBestSellingService(array $filters): ?array
    {
        $contractsQuery = Contract::query();
        $this->applyContractFilters($contractsQuery, $filters);
        $contractIds = $contractsQuery->pluck('id');

        if ($contractIds->isEmpty()) {
            return null;
        }

        $bestSellingService = DB::table('contract_service')
            ->join('services', 'contract_service.service_id', '=', 'services.id')
            ->whereIn('contract_service.contract_id', $contractIds)
            ->whereNull('contract_service.deleted_at')
            ->select(
                'services.name',
                DB::raw('COUNT(DISTINCT contract_service.contract_id) as total_contracts'),
                DB::raw('SUM((contract_service.unit_price * contract_service.quantity) - contract_service.discount) as total_revenue')
            )
            ->groupBy('services.id', 'services.name')
            ->orderByDesc('total_contracts')
            ->first();

        if (!$bestSellingService) {
            return null;
        }

        $totalRevenueAllServices = DB::table('contract_service')
            ->whereIn('contract_id', $contractIds)
            ->whereNull('deleted_at')
            ->sum(DB::raw('(unit_price * quantity) - discount'));

        $percentage = $totalRevenueAllServices > 0
            ? ($bestSellingService->total_revenue / $totalRevenueAllServices) * 100
            : 0;

        return [
            'name' => $bestSellingService->name,
            'total_contracts' => (int) $bestSellingService->total_contracts,
            'total_revenue' => round((float) $bestSellingService->total_revenue, 2),
            'percentage_of_total_sales' => round($percentage, 2),
        ];
    }

    /**
     * Report 2: Top Sales Representative by Revenue.
     */
    protected function getTopSalesByRevenue(array $filters): array
    {
        $contractsQuery = Contract::query();
        $this->applyContractFilters($contractsQuery, $filters);

        return $contractsQuery
            ->join('employees', 'contracts.employee_id', '=', 'employees.id')
            ->select(
                'employees.employee_name as sales_name',
                DB::raw('COUNT(contracts.id) as number_of_contracts'),
                DB::raw('SUM(contracts.amount) as total_revenue'),
                DB::raw('AVG(contracts.amount) as average_contract_value')
            )
            ->groupBy('employees.id', 'employees.employee_name')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(fn($item) => [
                'sales_name' => $item->sales_name,
                'number_of_contracts' => (int) $item->number_of_contracts,
                'total_revenue' => round((float) $item->total_revenue, 2),
                'average_contract_value' => round((float) $item->average_contract_value, 2),
            ])
            ->toArray();
    }

    /**
     * Report 3: Top Sales Representative by Contracts.
     */
    protected function getTopSalesByContracts(array $filters): array
    {
        $contractsQuery = Contract::query();
        $this->applyContractFilters($contractsQuery, $filters);

        return $contractsQuery
            ->join('employees', 'contracts.employee_id', '=', 'employees.id')
            ->select(
                'employees.employee_name as sales_name',
                DB::raw('COUNT(contracts.id) as number_of_contracts'),
                DB::raw('SUM(contracts.amount) as total_revenue')
            )
            ->groupBy('employees.id', 'employees.employee_name')
            ->orderByDesc('number_of_contracts')
            ->get()
            ->map(fn($item) => [
                'sales_name' => $item->sales_name,
                'number_of_contracts' => (int) $item->number_of_contracts,
                'total_revenue' => round((float) $item->total_revenue, 2),
            ])
            ->toArray();
    }

    /**
     * Report 4: Best Selling Months.
     */
    protected function getMonthlySales(array $filters): array
    {
        $contractsQuery = Contract::query();
        $this->applyContractFilters($contractsQuery, $filters);

        $driver = DB::connection()->getDriverName();
        $monthRaw = $driver === 'sqlite'
            ? "strftime('%Y-%m', start_date)"
            : "DATE_FORMAT(start_date, '%Y-%m')";

        return $contractsQuery
            ->select(
                DB::raw("{$monthRaw} as month_label"),
                DB::raw('COUNT(id) as number_of_contracts'),
                DB::raw('SUM(amount) as total_revenue')
            )
            ->groupBy(DB::raw($monthRaw))
            ->orderBy(DB::raw($monthRaw), 'desc')
            ->get()
            ->map(fn($item) => [
                'month' => $item->month_label ?: 'Unknown',
                'number_of_contracts' => (int) $item->number_of_contracts,
                'total_revenue' => round((float) $item->total_revenue, 2),
            ])
            ->toArray();
    }

    /**
     * Report 5: Top Customers.
     */
    protected function getTopCustomers(array $filters): array
    {
        $contractsQuery = Contract::query();
        $this->applyContractFilters($contractsQuery, $filters);

        return $contractsQuery
            ->join('clients', 'contracts.client_id', '=', 'clients.id')
            ->select(
                'clients.client_name as customer_name',
                DB::raw('COUNT(contracts.id) as number_of_contracts'),
                DB::raw('SUM(contracts.amount_paid) as total_paid'),
                DB::raw('SUM(contracts.amount) as total_contract_value')
            )
            ->groupBy('clients.id', 'clients.client_name')
            ->orderByDesc('total_contract_value')
            ->limit(10)
            ->get()
            ->map(fn($item) => [
                'customer_name' => $item->customer_name,
                'number_of_contracts' => (int) $item->number_of_contracts,
                'total_paid' => round((float) $item->total_paid, 2),
                'total_contract_value' => round((float) $item->total_contract_value, 2),
            ])
            ->toArray();
    }

    /**
     * Report 6: Latest 10 Contracts.
     */
    protected function getLatestContracts(array $filters): array
    {
        $contractsQuery = Contract::query()->with(['client', 'employee', 'services']);
        $this->applyContractFilters($contractsQuery, $filters);

        return $contractsQuery
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn($contract) => [
                'contract_number' => $contract->contract_number,
                'customer' => $contract->client->client_name ?? null,
                'sales_representative' => $contract->employee->employee_name ?? null,
                'service' => $contract->services->pluck('name')->join(', '),
                'contract_value' => round((float) $contract->amount, 2),
                'status' => $contract->status,
                'created_at' => $contract->created_at?->toDateTimeString(),
            ])
            ->toArray();
    }

    /**
     * Report 7: Platform Generating the Most Leads.
     */
    protected function getLeadSources(array $filters): array
    {
        $leadsQuery = Lead::query();
        $this->applyLeadFilters($leadsQuery, $filters);

        return $leadsQuery
            ->select(
                'source as platform',
                DB::raw('COUNT(id) as total_leads'),
                DB::raw("SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted_leads")
            )
            ->groupBy('source')
            ->orderByDesc('total_leads')
            ->get()
            ->map(function ($item) {
                $platform = $item->platform ?: 'Unknown';
                $total = (int) $item->total_leads;
                $converted = (int) $item->converted_leads;
                $pct = $total > 0 ? ($converted / $total) * 100 : 0;

                return [
                    'platform' => $platform,
                    'total_leads' => $total,
                    'converted_leads' => $converted,
                    'conversion_percentage' => round($pct, 2),
                ];
            })
            ->toArray();
    }

    /**
     * Report 8: Conversion Rate.
     */
    protected function getConversionRate(array $filters): array
    {
        $leadsQuery = Lead::query();
        $this->applyLeadFilters($leadsQuery, $filters);

        $conversionRateData = $leadsQuery
            ->select(
                DB::raw('COUNT(id) as total_leads'),
                DB::raw("SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted_leads"),
                DB::raw("SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as lost_leads")
            )
            ->first();

        $totalLeads = (int) ($conversionRateData->total_leads ?? 0);
        $convertedLeads = (int) ($conversionRateData->converted_leads ?? 0);
        $lostLeads = (int) ($conversionRateData->lost_leads ?? 0);
        $conversionPercentage = $totalLeads > 0 ? ($convertedLeads / $totalLeads) * 100 : 0;

        return [
            'total_leads' => $totalLeads,
            'converted_leads' => $convertedLeads,
            'lost_leads' => $lostLeads,
            'conversion_percentage' => round($conversionPercentage, 2),
        ];
    }

    /**
     * Report 9: Total Registered Collections.
     */
    protected function getRegisteredCollections(array $filters): array
    {
        $collectionsQuery = Collection::query();
        $this->applyCollectionFilters($collectionsQuery, $filters);

        $registered = $collectionsQuery
            ->select(
                DB::raw('SUM(amount_due) as total_amount'),
                DB::raw('COUNT(id) as number_of_transactions')
            )
            ->first();

        return [
            'total_amount' => round((float) ($registered->total_amount ?? 0), 2),
            'number_of_transactions' => (int) ($registered->number_of_transactions ?? 0),
        ];
    }

    /**
     * Report 10: Total Collected Amount.
     */
    protected function getCollectedAmount(array $filters): array
    {
        $collectionsQuery = Collection::query();
        $this->applyCollectionFilters($collectionsQuery, $filters);

        $collected = $collectionsQuery
            ->select(
                DB::raw('SUM(amount_collected) as total_collected'),
                DB::raw('COUNT(CASE WHEN amount_collected > 0 THEN 1 END) as number_of_successful_collections')
            )
            ->first();

        return [
            'total_collected' => round((float) ($collected->total_collected ?? 0), 2),
            'number_of_successful_collections' => (int) ($collected->number_of_successful_collections ?? 0),
        ];
    }

    /**
     * Report 11: Payment Method Comparison.
     */
    protected function getPaymentMethodComparison(array $filters): array
    {
        $collectionsQuery = Collection::query();
        $this->applyCollectionFilters($collectionsQuery, $filters);

        $payments = $collectionsQuery
            ->whereNotNull('payment_method')
            ->select(
                'payment_method',
                DB::raw('COUNT(id) as number_payments'),
                DB::raw('SUM(amount_collected) as total_amount')
            )
            ->groupBy('payment_method')
            ->get();

        $totalPaymentsAmount = $payments->sum('total_amount');

        $paymentMethods = [
            'InstaPay' => ['number_of_payments' => 0, 'total_amount' => 0.0, 'percentage_of_total' => 0.0],
            'Vodafone Cash' => ['number_of_payments' => 0, 'total_amount' => 0.0, 'percentage_of_total' => 0.0],
            'Cash' => ['number_of_payments' => 0, 'total_amount' => 0.0, 'percentage_of_total' => 0.0],
        ];

        foreach ($payments as $payment) {
            $method = $payment->payment_method;
            $matchedKey = null;
            foreach (array_keys($paymentMethods) as $key) {
                if (strcasecmp($key, $method) === 0) {
                    $matchedKey = $key;
                    break;
                }
            }

            $num = (int) $payment->number_payments;
            $amount = (float) $payment->total_amount;

            if ($matchedKey) {
                $paymentMethods[$matchedKey]['number_of_payments'] += $num;
                $paymentMethods[$matchedKey]['total_amount'] += $amount;
            } else {
                $paymentMethods[$method] = [
                    'number_of_payments' => $num,
                    'total_amount' => $amount,
                    'percentage_of_total' => 0.0,
                ];
            }
        }

        foreach ($paymentMethods as $key => &$data) {
            $data['total_amount'] = round($data['total_amount'], 2);
            $data['percentage_of_total'] = $totalPaymentsAmount > 0
                ? round(($data['total_amount'] / $totalPaymentsAmount) * 100, 2)
                : 0.0;
        }
        unset($data); // break the reference to avoid the foreach-by-reference PHP bug

        $paymentMethodComparison = [];
        foreach ($paymentMethods as $method => $data) {
            $paymentMethodComparison[] = array_merge(['payment_method' => $method], $data);
        }

        return $paymentMethodComparison;
    }

    /**
     * Report 12: Platform Advertisement Spending.
     */
    protected function getAdvertisementSpending(array $filters): array
    {
        $platforms = ['Facebook', 'Instagram', 'Google', 'TikTok', 'Snapchat'];

        $expensesQuery = Expense::query()->where('expense_type', 'general');

        // Apply date filters to expenses
        $expensesQuery->when($filters['from_date'] ?? null, function ($q, $from) {
            $q->whereDate('expense_date', '>=', $from);
        });
        $expensesQuery->when($filters['to_date'] ?? null, function ($q, $to) {
            $q->whereDate('expense_date', '<=', $to);
        });
        $expensesQuery->when($filters['year'] ?? null, function ($q, $year) {
            $q->whereYear('expense_date', $year);
        });
        $expensesQuery->when($filters['month'] ?? null, function ($q, $month) {
            $q->whereMonth('expense_date', $month);
        });

        $expenses = $expensesQuery->get();

        $leadsQuery = Lead::query();
        $this->applyLeadFilters($leadsQuery, $filters);
        $leads = $leadsQuery->get();

        $advertisementSpending = [];
        foreach ($platforms as $platform) {
            $platformCost = $expenses->filter(function ($expense) use ($platform) {
                return stripos($expense->description ?? '', $platform) !== false;
            })->sum('amount');

            $platformLeads = $leads->filter(function ($lead) use ($platform) {
                return stripos($lead->source ?? '', $platform) !== false;
            })->count();

            $costPerLead = $platformLeads > 0 ? $platformCost / $platformLeads : null;

            $advertisementSpending[] = [
                'platform' => $platform,
                'total_advertising_cost' => round((float) $platformCost, 2),
                'leads_generated' => $platformLeads,
                'cost_per_lead' => $costPerLead !== null ? round((float) $costPerLead, 2) : null,
            ];
        }

        // Sort descending by total_advertising_cost
        usort($advertisementSpending, function ($a, $b) {
            return $b['total_advertising_cost'] <=> $a['total_advertising_cost'];
        });

        return $advertisementSpending;
    }

    /**
     * Apply common query filters to a Contract query builder instance.
     */
    protected function applyContractFilters($query, array $filters): void
    {
        $query->when($filters['from_date'] ?? null, function ($q, $from) {
            $q->where('start_date', '>=', $from);
        });

        $query->when($filters['to_date'] ?? null, function ($q, $to) {
            $q->where('start_date', '<=', $to);
        });

        $query->when($filters['year'] ?? null, function ($q, $year) {
            $q->whereYear('start_date', $year);
        });

        $query->when($filters['month'] ?? null, function ($q, $month) {
            $q->whereMonth('start_date', $month);
        });

        $query->when($filters['sales_representative'] ?? null, function ($q, $employeeId) {
            $q->where('employee_id', $employeeId);
        });

        $query->when($filters['customer'] ?? null, function ($q, $clientId) {
            $q->where('client_id', $clientId);
        });

        $query->when($filters['service'] ?? null, function ($q, $serviceId) {
            $q->whereHas('services', function ($sub) use ($serviceId) {
                $sub->where('services.slug', $serviceId);
            });
        });
    }

    /**
     * Apply common query filters to a Lead query builder instance.
     */
    protected function applyLeadFilters($query, array $filters): void
    {
        $query->when($filters['from_date'] ?? null, function ($q, $from) {
            $q->whereDate('created_at', '>=', $from);
        });

        $query->when($filters['to_date'] ?? null, function ($q, $to) {
            $q->whereDate('created_at', '<=', $to);
        });

        $query->when($filters['year'] ?? null, function ($q, $year) {
            $q->whereYear('created_at', $year);
        });

        $query->when($filters['month'] ?? null, function ($q, $month) {
            $q->whereMonth('created_at', $month);
        });

        $query->when($filters['sales_representative'] ?? null, function ($q, $employeeId) {
            $q->where('assigned_to', $employeeId);
        });

        $query->when($filters['customer'] ?? null, function ($q, $clientId) {
            $q->whereHas('clients', function ($sub) use ($clientId) {
                $sub->where('id', $clientId);
            });
        });

        $query->when($filters['service'] ?? null, function ($q, $serviceId) {
            $q->whereHas('services', function ($sub) use ($serviceId) {
                $sub->where('services.slug', $serviceId);
            });
        });
    }

    /**
     * Apply common query filters to a Collection query builder instance.
     */
    protected function applyCollectionFilters($query, array $filters): void
    {
        $query->when($filters['from_date'] ?? null, function ($q, $from) {
            $q->whereDate('due_date', '>=', $from);
        });

        $query->when($filters['to_date'] ?? null, function ($q, $to) {
            $q->whereDate('due_date', '<=', $to);
        });

        $query->when($filters['year'] ?? null, function ($q, $year) {
            $q->whereYear('due_date', $year);
        });

        $query->when($filters['month'] ?? null, function ($q, $month) {
            $q->whereMonth('due_date', $month);
        });

        $query->when($filters['sales_representative'] ?? null, function ($q, $employeeId) {
            $q->whereHas('contract', function ($sub) use ($employeeId) {
                $sub->where('employee_id', $employeeId);
            });
        });

        $query->when($filters['customer'] ?? null, function ($q, $clientId) {
            $q->where('client_id', $clientId);
        });

        $query->when($filters['service'] ?? null, function ($q, $serviceId) {
            $q->whereHas('contract.services', function ($sub) use ($serviceId) {
                $sub->where('services.slug', $serviceId);
            });
        });
    }
}
