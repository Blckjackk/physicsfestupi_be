<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\PesertaController;


Route::get('/user', function (Request $request) {
    return $request->user();
});

// Test route untuk debugging
Route::get('/test', function () {
    return response()->json([
        'message' => 'API is working!',
        'timestamp' => now()
    ]);
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
});

// Peserta routes untuk sistem CBT
Route::prefix('peserta')->group(function () {
    
    // Test route untuk peserta
    Route::post('/test-login', function (Request $request) {
        return response()->json([
            'message' => 'Peserta route is working!',
            'data' => $request->all()
        ]);
    });
    
    // Authentication
    Route::post('/login', [PesertaController::class, 'login']); // POST /api/peserta/login
    Route::post('/logout', [PesertaController::class, 'logout'])->middleware('auth:sanctum'); // POST /api/peserta/logout
    
    // Ujian Management
    Route::get('/ujian-test', [PesertaController::class, 'getAvailableUjian']); // GET /api/peserta/ujian-test - Test tanpa middleware
    Route::get('/ujian', [PesertaController::class, 'getAvailableUjian']); // GET /api/peserta/ujian - Daftar ujian yang tersedia (no middleware for testing)
    Route::get('/ujian/{id}', [PesertaController::class, 'getUjianDetail']); // GET /api/peserta/ujian/{id} - Detail ujian tertentu (no middleware for testing)
    Route::post('/ujian/mulai', [PesertaController::class, 'mulaiUjian']); // POST /api/peserta/ujian/mulai - Catat waktu login & status peserta (no middleware for testing)
    Route::get('/ujian/waktu/{id}', [PesertaController::class, 'cekWaktuUjian']); // GET /api/peserta/ujian/waktu/{id} - Cek apakah ujian sudah bisa dimulai atau belum (no middleware for testing)
    Route::get('/ujian/status/{id}', [PesertaController::class, 'getStatusUjian']); // GET /api/peserta/ujian/status/{id} - Cek status aktivitas peserta untuk ujian tertentu (no middleware for testing)
    
    // Soal Management
    Route::get('/soal/{ujian_id}', [PesertaController::class, 'getSoalUjian']); // GET /api/peserta/soal/{ujian_id} - Ambil semua soal ujian berdasarkan nomor soal (no middleware for testing)
    Route::get('/soal/{ujian_id}/{nomor_soal}', [PesertaController::class, 'getSoalByNomor']); // GET /api/peserta/soal/{ujian_id}/{nomor_soal} - Ambil soal tertentu berdasarkan nomor soal (no middleware for testing)
    
    // Jawaban Management
    Route::post('/jawaban', [PesertaController::class, 'simpanJawaban']); // POST /api/peserta/jawaban - Kirim jawaban pilihan ganda peserta (auto-save) (no middleware for testing)
    Route::get('/jawaban/{ujian_id}', [PesertaController::class, 'getJawabanPeserta']); // GET /api/peserta/jawaban/{ujian_id} - Untuk memuat ulang jawaban peserta (no middleware for testing)
    
    // Submit ujian
    Route::post('/ujian/selesai', [PesertaController::class, 'selesaiUjian']); // POST /api/peserta/ujian/selesai - Submit ujian & update status jadi sudah_submit (no middleware for testing)
    Route::post('/ujian/auto-save', [PesertaController::class, 'autoSaveJawaban']); // POST /api/peserta/ujian/auto-save - Auto save jawaban peserta (no middleware for testing)
});