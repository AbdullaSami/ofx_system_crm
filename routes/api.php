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
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::apiResource('departments', DepartmentController::class);
Route::apiResource('services', ServiceController::class);
Route::apiResource('teams', TeamController::class);
Route::apiResource('employees', EmployeesController::class);
Route::apiResource('leads', LeadController::class);
Route::apiResource('follow-ups', FollowUpController::class);
Route::apiResource('contracts', ContractController::class)->middleware('auth:sanctum');
Route::apiResource('clients', ClientController::class);
