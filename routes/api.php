<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\v1\DepartmentController;
use App\Http\Controllers\v1\ServiceController;
use App\Http\Controllers\v1\TeamController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::apiResource('departments', DepartmentController::class);
Route::apiResource('services', ServiceController::class);
Route::apiResource('teams', TeamController::class);


