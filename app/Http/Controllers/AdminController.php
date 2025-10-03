<?php
namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Peserta;
use App\Models\Ujian;
use App\Models\Soal;
use App\Models\AktivitasPeserta;
use App\Models\Jawaban;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    /**
     * Ambil daftar semua peserta
     * 
     * @return JsonResponse
     */
    public function getPeserta(): JsonResponse
    {
        try {
            $peserta = Peserta::select('id', 'username', 'nilai_total', 'created_at', 'updated_at')
                             ->orderBy('created_at', 'desc')
                             ->get();
            
            return response()->json([
                'success' => true,
                'message' => 'Daftar peserta berhasil diambil',
                'data' => $peserta
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar peserta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buat akun peserta baru
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function createPeserta(Request $request): JsonResponse
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'username' => 'required|string|max:50|unique:peserta,username',
                'password' => 'required|string|min:6',
                'nilai_total' => 'nullable|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Buat peserta baru
            $peserta = Peserta::create([
                'username' => $request->username,
                'password_hash' => Hash::make($request->password),
                'role' => 'peserta',
                'nilai_total' => $request->nilai_total
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Peserta berhasil dibuat',
                'data' => [
                    'id' => $peserta->id,
                    'username' => $peserta->username,
                    'nilai_total' => $peserta->nilai_total,
                    'created_at' => $peserta->created_at
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat peserta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update akun peserta
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updatePeserta(Request $request, int $id): JsonResponse
    {
        try {
            // Cari peserta
            $peserta = Peserta::find($id);
            
            if (!$peserta) {
                return response()->json([
                    'success' => false,
                    'message' => 'Peserta tidak ditemukan'
                ], 404);
            }

            // Validasi input
            $validator = Validator::make($request->all(), [
                'username' => 'sometimes|required|string|max:50|unique:peserta,username,' . $id,
                'password' => 'sometimes|required|string|min:6',
                'nilai_total' => 'nullable|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update data
            $updateData = [];
            
            if ($request->has('username')) {
                $updateData['username'] = $request->username;
            }
            
            if ($request->has('password')) {
                $updateData['password_hash'] = Hash::make($request->password);
            }
            
            if ($request->has('nilai_total')) {
                $updateData['nilai_total'] = $request->nilai_total;
            }

            $peserta->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Peserta berhasil diupdate',
                'data' => [
                    'id' => $peserta->id,
                    'username' => $peserta->username,
                    'nilai_total' => $peserta->nilai_total,
                    'updated_at' => $peserta->updated_at
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate peserta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hapus akun peserta
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function deletePeserta(int $id): JsonResponse
    {
        try {
            // Cari peserta
            $peserta = Peserta::find($id);
            
            if (!$peserta) {
                return response()->json([
                    'success' => false,
                    'message' => 'Peserta tidak ditemukan'
                ], 404);
            }

            // Hapus peserta (cascade akan otomatis menghapus aktivitas dan jawaban)
            $peserta->delete();

            return response()->json([
                'success' => true,
                'message' => 'Peserta berhasil dihapus'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus peserta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ====== UJIAN MANAGEMENT ======

    /**
     * Ambil daftar semua ujian
     * 
     * @return JsonResponse
     */
    public function getUjian(): JsonResponse
    {
        try {
            $ujian = Ujian::with(['soal' => function($query) {
                        $query->select('id', 'ujian_id', 'nomor_soal', 'pertanyaan');
                    }])
                    ->select('id', 'nama_ujian', 'deskripsi', 'waktu_mulai_pengerjaan', 'waktu_akhir_pengerjaan', 'created_at')
                    ->orderBy('created_at', 'desc')
                    ->get();
            
            return response()->json([
                'success' => true,
                'message' => 'Daftar ujian berhasil diambil',
                'data' => $ujian
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar ujian',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buat ujian baru
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function createUjian(Request $request): JsonResponse
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'nama_ujian' => 'required|string|max:100',
                'deskripsi' => 'nullable|string',
                'waktu_mulai_pengerjaan' => 'required|date',
                'waktu_akhir_pengerjaan' => 'required|date|after:waktu_mulai_pengerjaan'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Buat ujian baru
            $ujian = Ujian::create([
                'nama_ujian' => $request->nama_ujian,
                'deskripsi' => $request->deskripsi,
                'waktu_mulai_pengerjaan' => $request->waktu_mulai_pengerjaan,
                'waktu_akhir_pengerjaan' => $request->waktu_akhir_pengerjaan
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ujian berhasil dibuat',
                'data' => $ujian
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat ujian',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update ujian
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateUjian(Request $request, int $id): JsonResponse
    {
        try {
            // Cari ujian
            $ujian = Ujian::find($id);
            
            if (!$ujian) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ujian tidak ditemukan'
                ], 404);
            }

            // Validasi input
            $validator = Validator::make($request->all(), [
                'nama_ujian' => 'sometimes|required|string|max:100',
                'deskripsi' => 'nullable|string',
                'waktu_mulai_pengerjaan' => 'sometimes|required|date',
                'waktu_akhir_pengerjaan' => 'sometimes|required|date|after:waktu_mulai_pengerjaan'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update data
            $updateData = [];
            
            if ($request->has('nama_ujian')) {
                $updateData['nama_ujian'] = $request->nama_ujian;
            }
            
            if ($request->has('deskripsi')) {
                $updateData['deskripsi'] = $request->deskripsi;
            }
            
            if ($request->has('waktu_mulai_pengerjaan')) {
                $updateData['waktu_mulai_pengerjaan'] = $request->waktu_mulai_pengerjaan;
            }
            
            if ($request->has('waktu_akhir_pengerjaan')) {
                $updateData['waktu_akhir_pengerjaan'] = $request->waktu_akhir_pengerjaan;
            }

            $ujian->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Ujian berhasil diupdate',
                'data' => $ujian->fresh()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate ujian',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hapus ujian
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function deleteUjian(int $id): JsonResponse
    {
        try {
            // Cari ujian
            $ujian = Ujian::find($id);
            
            if (!$ujian) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ujian tidak ditemukan'
                ], 404);
            }

            // Hapus ujian (cascade akan otomatis menghapus soal, aktivitas dan jawaban)
            $ujian->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ujian berhasil dihapus'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus ujian',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ====== SOAL MANAGEMENT ======

    /**
     * Ambil soal dalam ujian tertentu
     * 
     * @param int $ujianId
     * @return JsonResponse
     */
    public function getSoalByUjian(int $ujianId): JsonResponse
    {
        try {
            // Cek apakah ujian exists
            $ujian = Ujian::find($ujianId);
            if (!$ujian) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ujian tidak ditemukan'
                ], 404);
            }

            $soal = Soal::where('ujian_id', $ujianId)
                        ->orderBy('nomor_soal', 'asc')
                        ->get();
            
            return response()->json([
                'success' => true,
                'message' => 'Daftar soal berhasil diambil',
                'data' => [
                    'ujian' => $ujian,
                    'soal' => $soal
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar soal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tambah soal baru
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function createSoal(Request $request): JsonResponse
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'ujian_id' => 'required|exists:ujian,id',
                'nomor_soal' => 'required|integer|min:1',
                'tipe_soal' => 'required|in:text,gambar',
                'deskripsi_soal' => 'nullable|string',
                'pertanyaan' => 'required|string',
                'media_soal' => 'nullable|string|max:255',
                'opsi_a' => 'nullable|string',
                'opsi_a_media' => 'nullable|string|max:255',
                'opsi_b' => 'nullable|string',
                'opsi_b_media' => 'nullable|string|max:255',
                'opsi_c' => 'nullable|string',
                'opsi_c_media' => 'nullable|string|max:255',
                'opsi_d' => 'nullable|string',
                'opsi_d_media' => 'nullable|string|max:255',
                'opsi_e' => 'nullable|string',
                'opsi_e_media' => 'nullable|string|max:255',
                'jawaban_benar' => 'required|in:a,b,c,d,e,A,B,C,D,E'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Cek duplikasi nomor soal dalam ujian yang sama
            $existingSoal = Soal::where('ujian_id', $request->ujian_id)
                                ->where('nomor_soal', $request->nomor_soal)
                                ->exists();

            if ($existingSoal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor soal sudah digunakan dalam ujian ini'
                ], 422);
            }

            // Buat soal baru
            $soal = Soal::create([
                'ujian_id' => $request->ujian_id,
                'nomor_soal' => $request->nomor_soal,
                'tipe_soal' => $request->tipe_soal,
                'deskripsi_soal' => $request->deskripsi_soal,
                'pertanyaan' => $request->pertanyaan,
                'media_soal' => $request->media_soal,
                'opsi_a' => $request->opsi_a,
                'opsi_a_media' => $request->opsi_a_media,
                'opsi_b' => $request->opsi_b,
                'opsi_b_media' => $request->opsi_b_media,
                'opsi_c' => $request->opsi_c,
                'opsi_c_media' => $request->opsi_c_media,
                'opsi_d' => $request->opsi_d,
                'opsi_d_media' => $request->opsi_d_media,
                'opsi_e' => $request->opsi_e,
                'opsi_e_media' => $request->opsi_e_media,
                'jawaban_benar' => strtolower($request->jawaban_benar)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Soal berhasil dibuat',
                'data' => $soal->load('ujian')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat soal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update soal
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateSoal(Request $request, int $id): JsonResponse
    {
        try {
            // Cari soal
            $soal = Soal::find($id);
            
            if (!$soal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Soal tidak ditemukan'
                ], 404);
            }

            // Validasi input
            $validator = Validator::make($request->all(), [
                'nomor_soal' => 'sometimes|required|integer|min:1',
                'tipe_soal' => 'sometimes|required|in:text,gambar',
                'deskripsi_soal' => 'nullable|string',
                'pertanyaan' => 'sometimes|required|string',
                'media_soal' => 'nullable|string|max:255',
                'opsi_a' => 'nullable|string',
                'opsi_a_media' => 'nullable|string|max:255',
                'opsi_b' => 'nullable|string',
                'opsi_b_media' => 'nullable|string|max:255',
                'opsi_c' => 'nullable|string',
                'opsi_c_media' => 'nullable|string|max:255',
                'opsi_d' => 'nullable|string',
                'opsi_d_media' => 'nullable|string|max:255',
                'opsi_e' => 'nullable|string',
                'opsi_e_media' => 'nullable|string|max:255',
                'jawaban_benar' => 'sometimes|required|in:a,b,c,d,e,A,B,C,D,E'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Cek duplikasi nomor soal jika nomor_soal diupdate
            if ($request->has('nomor_soal') && $request->nomor_soal != $soal->nomor_soal) {
                $existingSoal = Soal::where('ujian_id', $soal->ujian_id)
                                    ->where('nomor_soal', $request->nomor_soal)
                                    ->where('id', '!=', $id)
                                    ->exists();

                if ($existingSoal) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Nomor soal sudah digunakan dalam ujian ini'
                    ], 422);
                }
            }

            // Update data
            $updateData = $request->only([
                'nomor_soal', 'tipe_soal', 'deskripsi_soal', 'pertanyaan', 'media_soal',
                'opsi_a', 'opsi_a_media', 'opsi_b', 'opsi_b_media', 'opsi_c', 'opsi_c_media',
                'opsi_d', 'opsi_d_media', 'opsi_e', 'opsi_e_media'
            ]);

            if ($request->has('jawaban_benar')) {
                $updateData['jawaban_benar'] = strtolower($request->jawaban_benar);
            }

            $soal->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Soal berhasil diupdate',
                'data' => $soal->fresh(['ujian'])
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate soal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hapus soal
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function deleteSoal(int $id): JsonResponse
    {
        try {
            // Cari soal
            $soal = Soal::find($id);
            
            if (!$soal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Soal tidak ditemukan'
                ], 404);
            }

            // Hapus soal (cascade akan otomatis menghapus jawaban terkait)
            $soal->delete();

            return response()->json([
                'success' => true,
                'message' => 'Soal berhasil dihapus'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus soal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ====== JAWABAN MANAGEMENT ======

    /**
     * Tambah jawaban peserta (untuk testing atau input manual)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function createJawaban(Request $request): JsonResponse
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'peserta_id' => 'required|exists:peserta,id',
                'ujian_id' => 'required|exists:ujian,id',
                'soal_id' => 'required|exists:soal,id',
                'jawaban_peserta' => 'required|in:a,b,c,d,e,A,B,C,D,E',
                'benar' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Cek apakah soal benar-benar milik ujian tersebut
            $soal = Soal::where('id', $request->soal_id)
                        ->where('ujian_id', $request->ujian_id)
                        ->first();

            if (!$soal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Soal tidak ditemukan dalam ujian tersebut'
                ], 404);
            }

            // Cek apakah jawaban sudah ada
            $existingJawaban = Jawaban::where('peserta_id', $request->peserta_id)
                                     ->where('ujian_id', $request->ujian_id)
                                     ->where('soal_id', $request->soal_id)
                                     ->first();

            if ($existingJawaban) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jawaban untuk soal ini sudah ada'
                ], 422);
            }

            // Auto-grade jika tidak disediakan status benar/salah
            $benar = $request->benar;
            if ($benar === null) {
                $benar = $soal->isCorrectAnswer($request->jawaban_peserta);
            }

            // Buat jawaban baru
            $jawaban = Jawaban::create([
                'peserta_id' => $request->peserta_id,
                'ujian_id' => $request->ujian_id,
                'soal_id' => $request->soal_id,
                'jawaban_peserta' => strtolower($request->jawaban_peserta),
                'benar' => $benar
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Jawaban berhasil ditambahkan',
                'data' => $jawaban->load(['peserta:id,username', 'ujian:id,nama_ujian', 'soal:id,nomor_soal,pertanyaan'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan jawaban',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update jawaban peserta
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateJawaban(Request $request, int $id): JsonResponse
    {
        try {
            // Cari jawaban
            $jawaban = Jawaban::find($id);
            
            if (!$jawaban) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jawaban tidak ditemukan'
                ], 404);
            }

            // Validasi input
            $validator = Validator::make($request->all(), [
                'jawaban_peserta' => 'sometimes|required|in:a,b,c,d,e,A,B,C,D,E',
                'benar' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update data
            $updateData = [];
            
            if ($request->has('jawaban_peserta')) {
                $updateData['jawaban_peserta'] = strtolower($request->jawaban_peserta);
                
                // Auto-grade jika jawaban_peserta diubah dan benar tidak disediakan
                if (!$request->has('benar')) {
                    $updateData['benar'] = $jawaban->soal->isCorrectAnswer($request->jawaban_peserta);
                }
            }
            
            if ($request->has('benar')) {
                $updateData['benar'] = $request->benar;
            }

            $jawaban->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Jawaban berhasil diupdate',
                'data' => $jawaban->fresh(['peserta:id,username', 'ujian:id,nama_ujian', 'soal:id,nomor_soal,pertanyaan'])
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate jawaban',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hapus jawaban peserta
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function deleteJawaban(int $id): JsonResponse
    {
        try {
            // Cari jawaban
            $jawaban = Jawaban::find($id);
            
            if (!$jawaban) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jawaban tidak ditemukan'
                ], 404);
            }

            // Hapus jawaban
            $jawaban->delete();

            return response()->json([
                'success' => true,
                'message' => 'Jawaban berhasil dihapus'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus jawaban',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}