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

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::apiResource('departments', DepartmentController::class);
Route::apiResource('services', ServiceController::class);
Route::apiResource('teams', TeamController::class);
Route::apiResource('employees', EmployeesController::class);
Route::post('employees/{id}/salary', [EmployeesController::class, 'paySalary']);
Route::post('employees/{id}/commission', [EmployeesController::class, 'payCommission']);
Route::apiResource('leads', LeadController::class);
Route::apiResource('follow-ups', FollowUpController::class);
Route::apiResource('contracts', ContractController::class)->middleware('auth:sanctum');
Route::post('contracts/{contract}/cancel', [ContractController::class, 'cancelContract'])->middleware('auth:sanctum');
Route::post('contracts/{contract}/service/{service_slug}/cancel', [ContractController::class, 'cancelSingleService'])->middleware('auth:sanctum');
Route::get('layout/{id}/create', [ContractController::class, 'create']);
Route::apiResource('clients', ClientController::class);
Route::apiResource('collections', CollectionController::class);
