<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FinkokController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/facturacion', [FinkokController::class, 'stamp']);
Route::post('/facturacion/pago', [FinkokController::class, 'stampPago']);
Route::post('/facturacion/pagoMultiMoneda', [FinkokController::class, 'stampPagoMultiMoneda']);
Route::post('/cancelar', [FinkokController::class, 'cancel']);

