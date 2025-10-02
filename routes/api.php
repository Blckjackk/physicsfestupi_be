<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Admin routes untuk mengelola peserta
Route::prefix('admin')->group(function () {
    // CRUD Peserta
    Route::get('/peserta', [AdminController::class, 'getPeserta']); // GET /api/admin/peserta
    Route::post('/peserta', [AdminController::class, 'createPeserta']); // POST /api/admin/peserta
    Route::put('/peserta/{id}', [AdminController::class, 'updatePeserta']); // PUT /api/admin/peserta/{id}
    Route::delete('/peserta/{id}', [AdminController::class, 'deletePeserta']); // DELETE /api/admin/peserta/{id}
});