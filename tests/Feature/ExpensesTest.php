<?php

use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function createExpenseUser(): User
{
    Permission::firstOrCreate(['name' => 'expenses.view', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->givePermissionTo('expenses.view');

    return $user;
}

function makeTreasuryAccount(string $name = 'Main Safe', float $balance = 50000.0): int
{
    return DB::table('treasury_accounts')->insertGetId([
        'account_name' => $name,
        'balance'      => $balance,
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);
}

test('guest cannot access expenses endpoint', function () {
    $response = $this->getJson('/api/expenses');
    $response->assertUnauthorized();
});

test('user can list expenses and receives total_expenses', function () {
    $user = createExpenseUser();
    $treasuryId = makeTreasuryAccount();

    Expense::create([
        'treasury_id'  => $treasuryId,
        'expense_type' => 'general',
        'amount'       => 100.00,
        'expense_date' => '2026-09-01',
        'description'  => 'Test 1',
    ]);

    Expense::create([
        'treasury_id'  => $treasuryId,
        'expense_type' => 'general',
        'amount'       => 250.50,
        'expense_date' => '2026-09-02',
        'description'  => 'Test 2',
    ]);

    $response = $this
        ->actingAs($user, 'sanctum')
        ->getJson('/api/expenses');

    $response->assertOk();
    $response->assertJsonStructure([
        'data',
        'links',
        'meta',
        'total_expenses',
    ]);
    expect((float) $response->json('total_expenses'))->toBe(350.50);
});

test('expenses can be filtered by date range', function () {
    $user = createExpenseUser();
    $treasuryId = makeTreasuryAccount();

    Expense::create([
        'treasury_id'  => $treasuryId,
        'expense_type' => 'general',
        'amount'       => 100.00,
        'expense_date' => '2026-09-01',
    ]);
    Expense::create([
        'treasury_id'  => $treasuryId,
        'expense_type' => 'general',
        'amount'       => 200.00,
        'expense_date' => '2026-09-02',
    ]);
    Expense::create([
        'treasury_id'  => $treasuryId,
        'expense_type' => 'general',
        'amount'       => 300.00,
        'expense_date' => '2026-09-10',
    ]);

    $response = $this
        ->actingAs($user, 'sanctum')
        ->getJson('/api/expenses?from_date=2026-09-01&to_date=2026-09-02');

    $response->assertOk();
    expect(count($response->json('data')))->toBe(2);
    expect((float) $response->json('total_expenses'))->toBe(300.00);
});

test('expenses can be filtered by treasury_id', function () {
    $user = createExpenseUser();
    $treasury1 = makeTreasuryAccount('Treasury 1');
    $treasury2 = makeTreasuryAccount('Treasury 2');

    Expense::create([
        'treasury_id'  => $treasury1,
        'expense_type' => 'general',
        'amount'       => 150.00,
        'expense_date' => '2026-09-01',
    ]);
    Expense::create([
        'treasury_id'  => $treasury2,
        'expense_type' => 'general',
        'amount'       => 400.00,
        'expense_date' => '2026-09-01',
    ]);

    $response = $this
        ->actingAs($user, 'sanctum')
        ->getJson("/api/expenses?treasury_id={$treasury1}");

    $response->assertOk();
    expect(count($response->json('data')))->toBe(1);
    expect((float) $response->json('total_expenses'))->toBe(150.00);
});

test('expenses can be filtered by exact amount and min/max amount', function () {
    $user = createExpenseUser();
    $treasuryId = makeTreasuryAccount();

    Expense::create([
        'treasury_id'  => $treasuryId,
        'expense_type' => 'general',
        'amount'       => 50.00,
        'expense_date' => '2026-09-01',
    ]);
    Expense::create([
        'treasury_id'  => $treasuryId,
        'expense_type' => 'general',
        'amount'       => 150.00,
        'expense_date' => '2026-09-01',
    ]);
    Expense::create([
        'treasury_id'  => $treasuryId,
        'expense_type' => 'general',
        'amount'       => 500.00,
        'expense_date' => '2026-09-01',
    ]);

    // Exact amount filter
    $responseExact = $this
        ->actingAs($user, 'sanctum')
        ->getJson('/api/expenses?amount=150.00');

    $responseExact->assertOk();
    expect(count($responseExact->json('data')))->toBe(1);
    expect((float) $responseExact->json('total_expenses'))->toBe(150.00);

    // Min and Max filter
    $responseRange = $this
        ->actingAs($user, 'sanctum')
        ->getJson('/api/expenses?min_amount=100.00&max_amount=600.00');

    $responseRange->assertOk();
    expect(count($responseRange->json('data')))->toBe(2);
    expect((float) $responseRange->json('total_expenses'))->toBe(650.00);
});

test('total_expenses calculates total across all pages, not just current page', function () {
    $user = createExpenseUser();
    $treasuryId = makeTreasuryAccount();

    for ($i = 1; $i <= 25; $i++) {
        Expense::create([
            'treasury_id'  => $treasuryId,
            'expense_type' => 'general',
            'amount'       => 10.00,
            'expense_date' => '2026-09-01',
        ]);
    }

    $response = $this
        ->actingAs($user, 'sanctum')
        ->getJson('/api/expenses?per_page=10&page=1');

    $response->assertOk();
    expect(count($response->json('data')))->toBe(10);
    expect((float) $response->json('total_expenses'))->toBe(250.00);
});
