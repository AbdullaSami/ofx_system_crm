<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\v1\Auth\AuthController;
use App\Http\Controllers\v1\DepartmentController;
use App\Http\Controllers\v1\ServiceController;
use App\Http\Controllers\v1\TeamController;
use App\Http\Controllers\v1\EmployeesController;
use App\Http\Controllers\v1\LeadController;
use App\Http\Controllers\v1\FollowUpController;
use App\Http\Controllers\v1\ContractController;
use App\Http\Controllers\v1\ClientController;
use App\Http\Controllers\v1\CollectionController;
use App\Http\Controllers\v1\TreasuryController;
use App\Http\Controllers\v1\ExpenseController;
use App\Http\Controllers\v1\ReportsController;
use App\Http\Controllers\v1\UserController;


Route::post('/login', [AuthController::class, 'login']);

Route::get('/permissions', function () {
    return \Spatie\Permission\Models\Permission::all();
});
Route::get('/roles', function () {
    return \Spatie\Permission\Models\Role::all();
});
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::apiResource('users', UserController::class);
Route::apiResource('departments', DepartmentController::class);
Route::apiResource('services', ServiceController::class);
Route::post('service/layouts', [ServiceController::class, 'getServicesLayouts']);
Route::apiResource('teams', TeamController::class);
Route::apiResource('employees', EmployeesController::class);
Route::post('employees/{id}/salary', [EmployeesController::class, 'paySalary']);
Route::post('employees/{id}/commission', [EmployeesController::class, 'payCommission']);
Route::apiResource('leads', LeadController::class);
Route::apiResource('follow-ups', FollowUpController::class);
Route::apiResource('contracts', ContractController::class);
Route::post('contracts/{contract}/cancel', [ContractController::class, 'cancelContract']);
Route::post('contracts/{contract}/service/{service_slug}/cancel', [ContractController::class, 'cancelSingleService']);
// Route::get('layout/{id}/create', [ContractController::class, 'create']);
Route::apiResource('clients', ClientController::class);
Route::apiResource('collections', CollectionController::class);
Route::apiResource('treasury', TreasuryController::class);

Route::apiResource('expenses', ExpenseController::class);
Route::delete('expenses/{expense}/attachments/{attachment}', [ExpenseController::class, 'destroyAttachment'])
    ->name('expenses.attachments.destroy');

Route::get('reports/dashboard', [ReportsController::class, 'dashboard']);
});