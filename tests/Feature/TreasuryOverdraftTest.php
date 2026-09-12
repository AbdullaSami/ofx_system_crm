<?php

use App\Http\Services\TreasuryAccountingService;
use App\Models\Employee;
use App\Models\TreasuryAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create();
    foreach (['treasury.create', 'treasury.update', 'treasury.view', 'expenses.create', 'expenses.update', 'expenses.delete', 'employees.pay_salary'] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }
    $this->actingAs($user, 'sanctum');

    $this->treasury = TreasuryAccount::create(['account_name' => 'Cash', 'balance' => 100]);
});

function overdraftEmployee(): Employee
{
    return Employee::create([
        'employee_name' => 'Test Employee',
        'email' => 'overdraft@example.com',
        'employee_code' => 'OD-001',
    ]);
}

test('treasury accounts can be created updated and read with negative balances', function () {
    $response = $this->postJson('/api/treasury', [
        'account_name' => 'Bank',
        'balance' => -125.50,
    ])->assertCreated();
    $id = $response->json('id');

    $this->assertDatabaseHas('treasury_accounts', ['id' => $id, 'balance' => -125.50]);
    $this->patchJson("/api/treasury/{$id}", ['balance' => -250.75])->assertOk();
    $response = $this->getJson("/api/treasury/{$id}")->assertOk();
    expect((float) $response->json('balance'))->toBe(-250.75);
    $this->assertDatabaseHas('treasury_accounts', ['id' => $id, 'balance' => -250.75]);
});

test('treasury debits can cross zero and continue from a negative balance then recover with credits', function () {
    $service = app(TreasuryAccountingService::class);
    $transaction = $service->recordTransaction($this->treasury->id, 150.25, 'debit', 'First payment');
    expect((float) $this->treasury->fresh()->balance)->toBe(-50.25);
    $this->assertDatabaseHas('treasury_transactions', [
        'id' => $transaction->id,
        'treasury_account_id' => $this->treasury->id,
        'amount' => 150.25,
        'transaction_type' => 'debit',
        'description' => 'First payment',
    ]);

    $service->recordTransaction($this->treasury->id, 25.50, 'debit');
    expect((float) $this->treasury->fresh()->balance)->toBe(-75.75);
    $service->recordTransaction($this->treasury->id, 100, 'credit');
    expect((float) $this->treasury->fresh()->balance)->toBe(24.25);
    $this->assertDatabaseCount('treasury_transactions', 3);
});

test('expenses can overdraw a treasury on creation update and account change and be refunded', function () {
    $response = $this->postJson('/api/expenses', [
        'treasury_id' => $this->treasury->id,
        'expense_type' => 'general',
        'amount' => 150,
        'expense_date' => '2026-09-13',
    ])->assertCreated();
    $id = $response->json('id');
    expect((float) $this->treasury->fresh()->balance)->toBe(-50.0);
    expect((float) $response->json('treasury.balance'))->toBe(-50.0);

    $this->patchJson("/api/expenses/{$id}", ['amount' => 200])->assertOk();
    expect((float) $this->treasury->fresh()->balance)->toBe(-100.0);

    $other = TreasuryAccount::create(['account_name' => 'Bank', 'balance' => -25]);
    $this->patchJson("/api/expenses/{$id}", ['treasury_id' => $other->id])->assertOk();
    expect((float) $this->treasury->fresh()->balance)->toBe(100.0);
    expect((float) $other->fresh()->balance)->toBe(-225.0);

    $this->deleteJson("/api/expenses/{$id}")->assertOk();
    expect((float) $other->fresh()->balance)->toBe(-25.0);
    $this->assertDatabaseMissing('expenses', ['id' => $id]);
});

test('salary advances can overdraw on creation update and account change and be reversed', function () {
    $employee = overdraftEmployee();
    $response = $this->postJson('/api/salary-advance', [
        'employee_id' => $employee->id,
        'payment_method' => 'Cash',
        'amount' => 150,
        'date' => '2026-09-13',
    ])->assertCreated();
    $id = $response->json('id');
    expect((float) $this->treasury->fresh()->balance)->toBe(-50.0);
    $this->assertDatabaseHas('treasury_transactions', [
        'id' => $response->json('treasury_transaction_id'),
        'treasury_account_id' => $this->treasury->id,
        'amount' => 150,
        'transaction_type' => 'debit',
    ]);

    $this->patchJson("/api/salary-advance/{$id}", ['amount' => 200])->assertOk();
    expect((float) $this->treasury->fresh()->balance)->toBe(-100.0);

    $other = TreasuryAccount::create(['account_name' => 'Bank', 'balance' => -25]);
    $this->patchJson("/api/salary-advance/{$id}", ['payment_method' => 'Bank'])->assertOk();
    expect((float) $this->treasury->fresh()->balance)->toBe(100.0);
    expect((float) $other->fresh()->balance)->toBe(-225.0);

    $this->deleteJson("/api/salary-advance/{$id}")->assertOk();
    expect((float) $other->fresh()->balance)->toBe(-25.0);
    $this->assertDatabaseMissing('salary_advances', ['id' => $id]);
});

test('salary payments can overdraw the treasury and debit the net salary only once', function () {
    $employee = overdraftEmployee();
    $this->postJson("/api/employees/{$employee->id}/salary", [
        'amount' => 200,
        'bonus' => 25,
        'deductions' => 50,
        'payment_method' => 'Cash',
    ])->assertOk();

    expect((float) $this->treasury->fresh()->balance)->toBe(-75.0);
    $this->assertDatabaseHas('treasury_transactions', [
        'treasury_account_id' => $this->treasury->id,
        'amount' => 175,
        'transaction_type' => 'debit',
    ]);
    $this->assertDatabaseHas('expenses', [
        'treasury_id' => $this->treasury->id,
        'expense_type' => 'wage',
        'amount' => 175,
    ]);
    $this->assertDatabaseCount('salaries', 1);
    $this->assertDatabaseCount('treasury_transactions', 1);
});

test('overdrafts do not allow nonpositive expense or salary advance amounts', function (float $amount) {
    $employee = overdraftEmployee();
    $this->postJson('/api/expenses', [
        'treasury_id' => $this->treasury->id,
        'expense_type' => 'general',
        'amount' => $amount,
        'expense_date' => '2026-09-13',
    ])->assertUnprocessable()->assertJsonValidationErrors('amount');

    $this->postJson('/api/salary-advance', [
        'employee_id' => $employee->id,
        'payment_method' => 'Cash',
        'amount' => $amount,
        'date' => '2026-09-13',
    ])->assertUnprocessable()->assertJsonValidationErrors('amount');

    expect((float) $this->treasury->fresh()->balance)->toBe(100.0);
    $this->assertDatabaseCount('expenses', 0);
    $this->assertDatabaseCount('salary_advances', 0);
    $this->assertDatabaseCount('treasury_transactions', 0);
})->with([0.0, -10.0]);
