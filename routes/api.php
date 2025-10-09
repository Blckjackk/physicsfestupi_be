<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\PesertaController;


Route::get('/user', function (Request $request) {
    return $request->user();
});

// Admin routes untuk mengelola sistem CBT
Route::prefix('admin')->group(function () {
    // Authentication
    Route::post('/login', [AdminController::class, 'loginAdmin']); // POST /api/admin/login
    Route::get('/me', [AdminController::class, 'getMe']); // GET /api/admin/me - Get current admin data
    
    // Dashboard
    Route::get('/dashboard', [AdminController::class, 'getDashboard']); // GET /api/admin/dashboard
    
    // CRUD Peserta
    Route::get('/peserta', [AdminController::class, 'getPeserta']); // GET /api/admin/peserta
    Route::post('/peserta', [AdminController::class, 'createPeserta']); // POST /api/admin/peserta
    Route::put('/peserta/{id}', [AdminController::class, 'updatePeserta']); // PUT /api/admin/peserta/{id}
    Route::delete('/peserta/{id}', [AdminController::class, 'deletePeserta']); // DELETE /api/admin/peserta/{id}
    Route::post('/peserta/batch-delete', [AdminController::class, 'batchDeletePeserta']); // POST /api/admin/peserta/batch-delete
    
    // CRUD Ujian
    Route::get('/ujian', [AdminController::class, 'getUjian']); // GET /api/admin/ujian
    Route::post('/ujian', [AdminController::class, 'createUjian']); // POST /api/admin/ujian
    Route::put('/ujian/{id}', [AdminController::class, 'updateUjian']); // PUT /api/admin/ujian/{id}
    Route::delete('/ujian/{id}', [AdminController::class, 'deleteUjian']); // DELETE /api/admin/ujian/{id}
    
    // CRUD Soal
    Route::get('/soal/{ujian_id}', [AdminController::class, 'getSoalByUjian']); // GET /api/admin/soal/{ujian_id}
    Route::post('/soal', [AdminController::class, 'createSoal']); // POST /api/admin/soal
    Route::post('/soal/{id}', [AdminController::class, 'updateSoal']); // POST /api/admin/soal/{id} - Support multipart upload
    Route::put('/soal/{id}', [AdminController::class, 'updateSoal']); // PUT /api/admin/soal/{id}
    Route::delete('/soal/{id}', [AdminController::class, 'deleteSoal']); // DELETE /api/admin/soal/{id}
    
    // Jawaban & Hasil Ujian
    Route::get('/jawaban/peserta', [AdminController::class, 'getJawabanPeserta']); // GET /api/admin/jawaban/peserta
    Route::get('/jawaban/peserta/{peserta_id}/ujian/{ujian_id}', [AdminController::class, 'getDetailJawabanPeserta']); // GET /api/admin/jawaban/peserta/{peserta_id}/ujian/{ujian_id}
    Route::delete('/hasil-ujian/{peserta_id}/{ujian_id}', [AdminController::class, 'deleteHasilUjianPeserta']); // DELETE /api/admin/hasil-ujian/{peserta_id}/{ujian_id}
    Route::post('/hasil-ujian/batch-delete', [AdminController::class, 'batchDeleteHasilUjian']); // POST /api/admin/hasil-ujian/batch-delete
    
    // Export Excel
    Route::get('/export/info/{id}', [AdminController::class, 'getInfoExportUjian']); // GET /api/admin/export/info/{id}
    Route::get('/export/hasil-ujian/{id}', [AdminController::class, 'exportHasilUjian']); // GET /api/admin/export/hasil-ujian/{id}
    Route::get('/export/semua-hasil-ujian', [AdminController::class, 'exportSemuaHasilUjian']); // GET /api/admin/export/semua-hasil-ujian
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
    Route::get('/me', [PesertaController::class, 'getMe']); // GET /api/peserta/me - Get current peserta data
    
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