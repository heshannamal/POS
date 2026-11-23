<?php

use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\DeviceAuthController;
use App\Http\Controllers\Api\V1\StoreDataController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\ShiftController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| POS Cloud v1 — desktop and mobile API
|--------------------------------------------------------------------------
| All business data endpoints require a true Sanctum Bearer token, a live
| device, active business membership and the existing tenant permissions.
| Public customer booking and platform administration are intentionally
| excluded from this initial release.
*/
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/auth/device-login',[DeviceAuthController::class,'login'])
        ->middleware('throttle:5,1')->name('auth.login');

    Route::middleware(['auth:sanctum','api.device'])->prefix('tenants/{tenant:slug}')
        ->name('tenants.')->group(function (): void {
            Route::get('/me',[StoreDataController::class,'me'])->name('me');
            Route::get('/devices',[DeviceAuthController::class,'devices'])->name('devices.index');
            Route::delete('/devices/{device}',[DeviceAuthController::class,'revoke'])
                ->middleware('throttle:20,1')->name('devices.revoke');
            Route::post('/auth/logout',[DeviceAuthController::class,'logout'])->name('auth.logout');

            Route::get('/branches',[StoreDataController::class,'branches'])->name('branches');

            Route::middleware('api.device:pos.sales')->group(function (): void {
                Route::get('/products',[StoreDataController::class,'products'])->name('products');
                Route::get('/stock',[StoreDataController::class,'stock'])->name('stock');
                Route::get('/registers',[StoreDataController::class,'registers'])->name('registers');

                Route::get('/sales',[StoreDataController::class,'sales'])->name('sales');
                Route::get('/sales/{sale}',[StoreDataController::class,'sale'])->name('sales.show');
                Route::get('/sync/manifest',[SyncController::class,'manifest'])
                    ->name('sync.manifest');
                Route::get('/sync/stock-movements',[SyncController::class,'stockMovements'])
                    ->name('sync.stock');
                Route::post('/sales',[CheckoutController::class,'store'])
                    ->middleware(['pos.operational','throttle:30,1'])->name('sales.store');
            });
            Route::middleware('api.device:pos.shifts.operate')->group(function (): void {
                Route::get('/shifts',[ShiftController::class,'index'])->name('shifts.index');
                Route::post('/shifts',[ShiftController::class,'open'])
                    ->middleware(['pos.operational','throttle:20,1'])->name('shifts.open');
                Route::post('/shifts/{shift}/cash-movements',[ShiftController::class,'movement'])
                    ->middleware(['pos.operational','throttle:30,1'])->name('shifts.movement');
                // Closing remains available during subscription suspension to reconcile cash safely.
                Route::post('/shifts/{shift}/close',[ShiftController::class,'close'])
                    ->middleware('throttle:20,1')->name('shifts.close');
            });
        });
});
