<?php

use App\Models\Client;
use App\Models\Collection;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────
// Shared helpers
// ─────────────────────────────────────────────

/**
 * Return a unique employee_code for test isolation.
 */
function uniqueCode(): string
{
    static $n = 0;
    return 'EMP-TEST-' . (++$n) . '-' . uniqid();
}

/**
 * Create a User with the given role and an associated Employee record.
 */
function createUserWithRole(string $roleName): User
{
    Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole($roleName);

    // Non-admin users need an associated employee for scoping
    if ($roleName !== 'Admin') {
        Employee::create([
            'employee_name' => $user->name,
            'first_name'    => 'John',
            'last_name'     => 'Doe',
            'email'         => 'emp_' . $user->id . '_' . uniqid() . '@example.com',
            'employee_code' => uniqueCode(),
            'user_id'       => $user->id,
            'status'        => 'active',
        ]);
    }

    return $user;
}

/**
 * Create a fresh Employee row with a unique code.
 */
function makeEmployee(string $name, string $emailPrefix): Employee
{
    return Employee::create([
        'employee_name' => $name,
        'email'         => $emailPrefix . '_' . uniqid() . '@test.com',
        'employee_code' => uniqueCode(),
        'status'        => 'active',
    ]);
}

/**
 * Create a minimal Department row and return it.
 */
function createDepartment(string $name = 'General'): Department
{
    return Department::create(['name' => $name . '-' . uniqid()]);
}

/**
 * Create a minimal Service row and return it.
 */
function createService(string $name = 'Web Design'): Service
{
    // Each call creates a fresh department, so the composite unique(name, department_id)
    // won't conflict even when the same service name is reused across tests.
    $dept = createDepartment();
    return Service::create(['name' => $name, 'department_id' => $dept->id]);
}

/**
 * Create a minimal Client row and return it.
 */
function createClient(string $name = 'Acme Corp'): Client
{
    return Client::create([
        'client_name' => $name,
        'email'       => \Illuminate\Support\Str::random(8) . '@acme.com',
    ]);
}

/**
 * Create a Contract and attach a service to it.
 */
function createContract(Employee $employee, Client $client, Service $service, array $overrides = []): Contract
{
    $amount = $overrides['amount'] ?? 5000.00;

    $contract = Contract::create(array_merge([
        'client_id'       => $client->id,
        'employee_id'     => $employee->id,
        'contract_number' => 'CTR-' . uniqid(),
        'start_date'      => now()->toDateString(),
        'end_date'        => now()->addYear()->toDateString(),
        'amount'          => $amount,
        'amount_paid'     => $amount / 2,
        'status'          => 'active',
    ], $overrides));

    DB::table('contract_service')->insert([
        'contract_id'       => $contract->id,
        'service_id'        => $service->id,
        'quantity'          => 1,
        'unit_price'        => $amount,
        'discount'          => 0,
        'billing_frequency' => 'monthly',
        'status'            => 'active',
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);

    return $contract;
}

/**
 * Create a Collection record for a contract/client.
 */
function createCollection(Contract $contract, Client $client, array $overrides = []): Collection
{
    return Collection::create(array_merge([
        'contract_id'      => $contract->id,
        'client_id'        => $client->id,
        'amount_due'       => 1000.00,
        'amount_collected' => 800.00,
        'due_date'         => now()->toDateString(),
        'status'           => 'partial',
        'payment_method'   => 'Cash',
    ], $overrides));
}

/**
 * Create a TreasuryAccount and return its id.
 */
function createTreasury(string $accountName = 'Main Account', float $balance = 999999): int
{
    return DB::table('treasury_accounts')->insertGetId([
        'account_name' => $accountName,
        'balance'      => $balance,
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);
}

// ─────────────────────────────────────────────
// 1. Authentication guard
// ─────────────────────────────────────────────

test('guest cannot access reports dashboard', function () {
    $response = $this->getJson('/api/reports/dashboard');
    $response->assertUnauthorized();
});

// ─────────────────────────────────────────────
// 2. Admin receives the full dashboard payload
// ─────────────────────────────────────────────

test('admin can access reports dashboard and receives all 12 keys', function () {
    $admin = createUserWithRole('Admin');

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/reports/dashboard');

    $response->assertOk();

    $response->assertJsonStructure([
        'best_selling_service',
        'top_sales_by_revenue',
        'top_sales_by_contracts',
        'monthly_sales',
        'top_customers',
        'latest_contracts',
        'lead_sources',
        'conversion_rate',
        'registered_collections',
        'collected_amount',
        'payment_method_comparison',
        'advertisement_spending',
    ]);
});

// ─────────────────────────────────────────────
// 3. Validation rejects invalid filter params
// ─────────────────────────────────────────────

test('invalid filter parameters return a 422 response', function () {
    $admin = createUserWithRole('Admin');

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/reports/dashboard?year=not-a-year&month=99');

    $response->assertUnprocessable();
});

test('to_date before from_date is rejected', function () {
    $admin = createUserWithRole('Admin');

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/reports/dashboard?from_date=2026-06-01&to_date=2026-01-01');

    $response->assertUnprocessable();
});

// ─────────────────────────────────────────────
// 4. Best Selling Service — correct values
// ─────────────────────────────────────────────

test('best_selling_service returns the correct service name and contract count', function () {
    $admin    = createUserWithRole('Admin');
    $employee = makeEmployee('Rep A', 'repa');
    $client   = createClient('Client A');
    $service  = createService('SEO Boost');

    createContract($employee, $client, $service);
    createContract($employee, $client, $service);

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/reports/dashboard');

    $response->assertOk();

    $bestService = $response->json('data.best_selling_service');
    expect($bestService)->not->toBeNull();
    expect($bestService['name'])->toBe('SEO Boost');
    expect($bestService['total_contracts'])->toBe(2);
    expect((float) $bestService['total_revenue'])->toBeGreaterThan(0);
    expect($bestService['percentage_of_total_sales'])->toBe(100.0);
});

// ─────────────────────────────────────────────
// 5. Top Sales by Revenue — ordering
// ─────────────────────────────────────────────

test('top_sales_by_revenue returns employees ordered by total revenue descending', function () {
    $admin   = createUserWithRole('Admin');
    $empA    = makeEmployee('Alice', 'alice');
    $empB    = makeEmployee('Bob',   'bob');
    $client  = createClient();
    $service = createService();

    createContract($empA, $client, $service, ['amount' => 10000]);
    createContract($empB, $client, $service, ['amount' => 3000]);

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/reports/dashboard');

    $response->assertOk();

    $topSales = $response->json('data.top_sales_by_revenue');
    expect($topSales[0]['sales_name'])->toBe('Alice');
    expect($topSales[0]['total_revenue'])->toBe(10000.0);
    expect($topSales[1]['sales_name'])->toBe('Bob');
});

// ─────────────────────────────────────────────
// 6. Top Sales by Contracts — ordering
// ─────────────────────────────────────────────

test('top_sales_by_contracts returns employees ordered by contract count descending', function () {
    $admin   = createUserWithRole('Admin');
    $empA    = makeEmployee('Carol', 'carol');
    $empB    = makeEmployee('Dave',  'dave');
    $client  = createClient();
    $service = createService();

    createContract($empA, $client, $service);
    createContract($empA, $client, $service);
    createContract($empA, $client, $service);
    createContract($empB, $client, $service);

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/reports/dashboard');

    $response->assertOk();

    $topContracts = $response->json('data.top_sales_by_contracts');
    expect($topContracts[0]['sales_name'])->toBe('Carol');
    expect($topContracts[0]['number_of_contracts'])->toBe(3);
});

// ─────────────────────────────────────────────
// 7. Monthly Sales — grouping
// ─────────────────────────────────────────────

test('monthly_sales returns data grouped by month', function () {
    $admin   = createUserWithRole('Admin');
    $emp     = makeEmployee('Rep', 'rep');
    $client  = createClient();
    $service = createService();

    createContract($emp, $client, $service, ['start_date' => '2026-03-01', 'amount' => 1000]);
    createContract($emp, $client, $service, ['start_date' => '2026-03-15', 'amount' => 2000]);
    createContract($emp, $client, $service, ['start_date' => '2026-04-01', 'amount' => 500]);

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/reports/dashboard');

    $response->assertOk();

    $monthly = collect($response->json('data.monthly_sales'));

    $march = $monthly->firstWhere('month', '2026-03');
    expect($march)->not->toBeNull();
    expect($march['number_of_contracts'])->toBe(2);
    expect($march['total_revenue'])->toBe(3000.0);

    $april = $monthly->firstWhere('month', '2026-04');
    expect($april)->not->toBeNull();
    expect($april['number_of_contracts'])->toBe(1);
});

// ─────────────────────────────────────────────
// 8. Monthly Sales — year filter
// ─────────────────────────────────────────────

test('monthly_sales respects year filter', function () {
    $admin   = createUserWithRole('Admin');
    $emp     = makeEmployee('Rep2', 'rep2');
    $client  = createClient();
    $service = createService();

    createContract($emp, $client, $service, ['start_date' => '2025-06-01', 'amount' => 9999]);
    createContract($emp, $client, $service, ['start_date' => '2026-06-01', 'amount' => 1234]);

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/reports/dashboard?year=2026');

    $response->assertOk();

    $monthly = collect($response->json('data.monthly_sales'));
    expect($monthly->pluck('month')->filter(fn($m) => str_starts_with($m, '2025'))->count())->toBe(0);

    $june2026 = $monthly->firstWhere('month', '2026-06');
    expect($june2026)->not->toBeNull();
    expect($june2026['total_revenue'])->toBe(1234.0);
});

// ─────────────────────────────────────────────
// 9. Top Customers — top 10, ordering
// ─────────────────────────────────────────────

test('top_customers returns up to 10 customers ordered by contract value', function () {
    $admin   = createUserWithRole('Admin');
    $emp     = makeEmployee('Rep3', 'rep3');
    $service = createService();

    for ($i = 1; $i <= 12; $i++) {
        $client = createClient("Client {$i}");
        createContract($emp, $client, $service, ['amount' => $i * 1000]);
    }

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/reports/dashboard');

    $response->assertOk();

    $customers = $response->json('data.top_customers');
    expect(count($customers))->toBeLessThanOrEqual(10);
    expect((float) $customers[0]['total_contract_value'])->toBe(12000.0);
});

// ─────────────────────────────────────────────
// 10. Latest Contracts — ordering and structure
// ─────────────────────────────────────────────

test('latest_contracts returns up to 10 newest contracts with required keys', function () {
    $admin   = createUserWithRole('Admin');
    $emp     = makeEmployee('Rep4', 'rep4');
    $client  = createClient();
    $service = createService('Branding');

    for ($i = 1; $i <= 12; $i++) {
        createContract($emp, $client, $service);
    }

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/reports/dashboard');

    $response->assertOk();

    $contracts = $response->json('data.latest_contracts');
    expect(count($contracts))->toBeLessThanOrEqual(10);

    expect($contracts[0])->toHaveKeys([
        'contract_number',
        'customer',
        'sales_representative',
        'service',
        'contract_value',
        'status',
        'created_at',
    ]);
});

// ─────────────────────────────────────────────
// 11. Lead Sources — grouping and conversion pct
// ─────────────────────────────────────────────

test('lead_sources groups leads by source and calculates conversion percentage', function () {
    $admin = createUserWithRole('Admin');

    Lead::create(['lead_name' => 'Lead 1', 'source' => 'Facebook',  'status' => 'converted']);
    Lead::create(['lead_name' => 'Lead 2', 'source' => 'Facebook',  'status' => 'new']);
    Lead::create(['lead_name' => 'Lead 3', 'source' => 'Facebook',  'status' => 'new']);
    Lead::create(['lead_name' => 'Lead 4', 'source' => 'Instagram', 'status' => 'converted']);
    Lead::create(['lead_name' => 'Lead 5', 'source' => 'Instagram', 'status' => 'converted']);

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/reports/dashboard');

    $response->assertOk();

    $sources = collect($response->json('data.lead_sources'));
    $fb = $sources->firstWhere('platform', 'Facebook');

    expect($fb)->not->toBeNull();
    expect($fb['total_leads'])->toBe(3);
    expect($fb['converted_leads'])->toBe(1);
    expect($fb['conversion_percentage'])->toBe(33.33);
});

// ─────────────────────────────────────────────
// 12. Conversion Rate — overall calculation
// ─────────────────────────────────────────────

test('conversion_rate calculates total, converted, lost and percentage correctly', function () {
    $admin = createUserWithRole('Admin');

    Lead::create(['lead_name' => 'L1', 'status' => 'converted']);
    Lead::create(['lead_name' => 'L2', 'status' => 'converted']);
    Lead::create(['lead_name' => 'L3', 'status' => 'lost']);
    Lead::create(['lead_name' => 'L4', 'status' => 'new']);

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/reports/dashboard');

    $response->assertOk();

    $cr = $response->json('data.conversion_rate');
    expect($cr['total_leads'])->toBe(4);
    expect($cr['converted_leads'])->toBe(2);
    expect($cr['lost_leads'])->toBe(1);
    expect($cr['conversion_percentage'])->toBe(50.0);
});

// ─────────────────────────────────────────────
// 13. Registered Collections — totals
// ─────────────────────────────────────────────

test('registered_collections sums amount_due across all collections', function () {
    $admin    = createUserWithRole('Admin');
    $emp      = makeEmployee('Rep5', 'rep5');
    $client   = createClient();
    $service  = createService();
    $contract = createContract($emp, $client, $service);

    createCollection($contract, $client, ['amount_due' => 500, 'amount_collected' => 0]);
    createCollection($contract, $client, ['amount_due' => 750, 'amount_collected' => 0]);

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/reports/dashboard');

    $response->assertOk();

    $rc = $response->json('data.registered_collections');
    expect($rc['total_amount'])->toBe(1250.0);
    expect($rc['number_of_transactions'])->toBe(2);
});

// ─────────────────────────────────────────────
// 14. Collected Amount — sums amount_collected
// ─────────────────────────────────────────────

test('collected_amount sums amount_collected for collections with payments', function () {
    $admin    = createUserWithRole('Admin');
    $emp      = makeEmployee('Rep6', 'rep6');
    $client   = createClient();
    $service  = createService();
    $contract = createContract($emp, $client, $service);

    createCollection($contract, $client, ['amount_collected' => 400]);
    createCollection($contract, $client, ['amount_collected' => 600]);
    createCollection($contract, $client, ['amount_collected' => 0]);

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/reports/dashboard');

    $response->assertOk();

    $ca = $response->json('data.collected_amount');
    expect((float) $ca['total_collected'])->toBe(1000.0);
    expect($ca['number_of_successful_collections'])->toBe(2);
});

// ─────────────────────────────────────────────
// 15. Payment Method Comparison — guaranteed keys
// ─────────────────────────────────────────────

test('payment_method_comparison always contains InstaPay, Vodafone Cash, and Cash entries', function () {
    $admin = createUserWithRole('Admin');

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/reports/dashboard');

    $response->assertOk();

    $comparison = $response->json('data.payment_method_comparison');
    $methods = collect($comparison)->pluck('payment_method')->values()->all();

    expect(in_array('InstaPay', $methods))->toBeTrue();
    expect(in_array('Vodafone Cash', $methods))->toBeTrue();
    expect(in_array('Cash', $methods))->toBeTrue();
});

test('payment_method_comparison calculates percentages correctly', function () {
    $admin    = createUserWithRole('Admin');
    $emp      = makeEmployee('Rep7', 'rep7');
    $client   = createClient();
    $service  = createService();
    $contract = createContract($emp, $client, $service);

    createCollection($contract, $client, ['amount_collected' => 300, 'payment_method' => 'Cash']);
    createCollection($contract, $client, ['amount_collected' => 700, 'payment_method' => 'InstaPay']);

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/reports/dashboard');

    $response->assertOk();

    $comparison = collect($response->json('data.payment_method_comparison'));

    $cash     = $comparison->firstWhere('payment_method', 'Cash');
    $instapay = $comparison->firstWhere('payment_method', 'InstaPay');

    expect((float) $cash['total_amount'])->toBe(300.0);
    expect((float) $cash['percentage_of_total'])->toBe(30.0);
    expect((float) $instapay['total_amount'])->toBe(700.0);
    expect((float) $instapay['percentage_of_total'])->toBe(70.0);
});

// ─────────────────────────────────────────────
// 16. Advertisement Spending — platform detection
// ─────────────────────────────────────────────

test('advertisement_spending detects platform costs from expense descriptions', function () {
    $admin      = createUserWithRole('Admin');
    $treasuryId = createTreasury();

    Expense::create([
        'treasury_id'  => $treasuryId,
        'expense_type' => 'general',
        'amount'       => 1500.00,
        'expense_date' => now()->toDateString(),
        'description'  => 'Facebook ads campaign Q2',
    ]);

    Expense::create([
        'treasury_id'  => $treasuryId,
        'expense_type' => 'general',
        'amount'       => 800.00,
        'expense_date' => now()->toDateString(),
        'description'  => 'Google ads spend',
    ]);

    Lead::create(['lead_name' => 'FB1', 'source' => 'Facebook', 'status' => 'new']);
    Lead::create(['lead_name' => 'FB2', 'source' => 'Facebook', 'status' => 'new']);
    Lead::create(['lead_name' => 'FB3', 'source' => 'Facebook', 'status' => 'new']);
    Lead::create(['lead_name' => 'G1',  'source' => 'Google',   'status' => 'new']);
    Lead::create(['lead_name' => 'G2',  'source' => 'Google',   'status' => 'new']);

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/reports/dashboard');

    $response->assertOk();

    $adSpend = collect($response->json('data.advertisement_spending'));

    $fb = $adSpend->firstWhere('platform', 'Facebook');
    expect((float) $fb['total_advertising_cost'])->toBe(1500.0);
    expect($fb['leads_generated'])->toBe(3);
    expect((float) $fb['cost_per_lead'])->toBe(500.0);

    $google = $adSpend->firstWhere('platform', 'Google');
    expect((float) $google['total_advertising_cost'])->toBe(800.0);
    expect($google['leads_generated'])->toBe(2);
    expect((float) $google['cost_per_lead'])->toBe(400.0);

    // Must be sorted descending by cost — Facebook first
    expect($adSpend->first()['platform'])->toBe('Facebook');
});

// ─────────────────────────────────────────────
// 17. Non-admin scoping — sees only own data
// ─────────────────────────────────────────────

test('non-admin user only sees their own contracts in reports', function () {
    Role::firstOrCreate(['name' => 'Admin',    'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);

    $salesUser = createUserWithRole('Employee');
    $salesEmp  = $salesUser->employee;

    $otherEmp = makeEmployee('Other', 'other');

    $client  = createClient();
    $service = createService();

    createContract($salesEmp, $client, $service, ['amount' => 5000]);
    createContract($otherEmp, $client, $service, ['amount' => 9999]);

    $response = $this
        ->actingAs($salesUser, 'sanctum')
        ->getJson('/api/reports/dashboard');

    $response->assertOk();

    $topSales = collect($response->json('data.top_sales_by_revenue'));
    expect($topSales->pluck('sales_name')->all())->not->toContain('Other');
    expect((float) $topSales->first()['total_revenue'])->toBe(5000.0);

    $latestContracts = $response->json('data.latest_contracts');
    expect(count($latestContracts))->toBe(1);
    expect((float) $latestContracts[0]['contract_value'])->toBe(5000.0);
});

// ─────────────────────────────────────────────
// 18. Date-range filter is respected globally
// ─────────────────────────────────────────────

test('from_date and to_date filters restrict contract-based reports', function () {
    $admin   = createUserWithRole('Admin');
    $emp     = makeEmployee('Rep8', 'rep8');
    $client  = createClient();
    $service = createService();

    createContract($emp, $client, $service, ['start_date' => '2026-05-15', 'amount' => 1111]);
    createContract($emp, $client, $service, ['start_date' => '2025-01-01', 'amount' => 9999]);

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/reports/dashboard?from_date=2026-01-01&to_date=2026-12-31');

    $response->assertOk();

    $topSales = $response->json('data.top_sales_by_revenue');
    expect((float) $topSales[0]['total_revenue'])->toBe(1111.0);
    expect($topSales[0]['number_of_contracts'])->toBe(1);
});

// ─────────────────────────────────────────────
// 19. Service filter restricts contract reports
// ─────────────────────────────────────────────

test('service filter restricts contract-based reports to contracts with that service', function () {
    $admin    = createUserWithRole('Admin');
    $emp      = makeEmployee('Rep9', 'rep9');
    $client   = createClient();
    $serviceA = createService('SEO');
    $serviceB = createService('PPC');

    createContract($emp, $client, $serviceA, ['amount' => 2000]);
    createContract($emp, $client, $serviceB, ['amount' => 8000]);

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson("/api/reports/dashboard?service={$serviceA->id}");

    $response->assertOk();

    $topSales = $response->json('data.top_sales_by_revenue');
    expect((float) $topSales[0]['total_revenue'])->toBe(2000.0);
    expect($topSales[0]['number_of_contracts'])->toBe(1);
});
