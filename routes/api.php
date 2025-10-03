<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Admin routes untuk mengelola sistem CBT
Route::prefix('admin')->group(function () {
    
    // CRUD Peserta
    Route::get('/peserta', [AdminController::class, 'getPeserta']); // GET /api/admin/peserta
    Route::post('/peserta', [AdminController::class, 'createPeserta']); // POST /api/admin/peserta
    Route::put('/peserta/{id}', [AdminController::class, 'updatePeserta']); // PUT /api/admin/peserta/{id}
    Route::delete('/peserta/{id}', [AdminController::class, 'deletePeserta']); // DELETE /api/admin/peserta/{id}
    
    // CRUD Ujian
    Route::get('/ujian', [AdminController::class, 'getUjian']); // GET /api/admin/ujian
    Route::post('/ujian', [AdminController::class, 'createUjian']); // POST /api/admin/ujian
    Route::put('/ujian/{id}', [AdminController::class, 'updateUjian']); // PUT /api/admin/ujian/{id}
    Route::delete('/ujian/{id}', [AdminController::class, 'deleteUjian']); // DELETE /api/admin/ujian/{id}
    
    // CRUD Soal
    Route::get('/soal/{ujian_id}', [AdminController::class, 'getSoalByUjian']); // GET /api/admin/soal/{ujian_id}
    Route::post('/soal', [AdminController::class, 'createSoal']); // POST /api/admin/soal
    Route::put('/soal/{id}', [AdminController::class, 'updateSoal']); // PUT /api/admin/soal/{id}
    Route::delete('/soal/{id}', [AdminController::class, 'deleteSoal']); // DELETE /api/admin/soal/{id}
    
    // CRUD Jawaban (untuk testing/manual input)
    Route::post('/jawaban', [AdminController::class, 'createJawaban']); // POST /api/admin/jawaban
    Route::put('/jawaban/{id}', [AdminController::class, 'updateJawaban']); // PUT /api/admin/jawaban/{id}
    Route::delete('/jawaban/{id}', [AdminController::class, 'deleteJawaban']); // DELETE /api/admin/jawaban/{id}
});