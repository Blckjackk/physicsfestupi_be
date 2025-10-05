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
                'ujian_id' => 'required|exists:ujian,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            \DB::beginTransaction();

            // Buat peserta baru dengan nilai_total default 0
            $peserta = Peserta::create([
                'username' => $request->username,
                'password_hash' => Hash::make($request->password),
                'role' => 'peserta',
                'nilai_total' => 0
            ]);

            // Ambil data ujian yang dipilih
            $ujian = Ujian::find($request->ujian_id);

            // Buat aktivitas peserta untuk ujian yang dipilih (1 ujian saja)
            $aktivitas = AktivitasPeserta::create([
                'peserta_id' => $peserta->id,
                'ujian_id' => $ujian->id,
                'status' => 'belum_login',
                'waktu_login' => null,
                'waktu_submit' => null
            ]);

            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Peserta berhasil dibuat dan terdaftar ke ujian',
                'data' => [
                    'peserta' => [
                        'id' => $peserta->id,
                        'username' => $peserta->username,
                        'nilai_total' => $peserta->nilai_total,
                        'created_at' => $peserta->created_at
                    ],
                    'ujian_assigned' => [
                        'ujian_id' => $ujian->id,
                        'nama_ujian' => $ujian->nama_ujian,
                        'status' => 'belum_login',
                        'waktu_mulai' => $ujian->waktu_mulai,
                        'waktu_selesai' => $ujian->waktu_selesai
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            \DB::rollBack();
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

    /**
     * Get detail peserta beserta ujian yang di-assign
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function getDetailPeserta(int $id): JsonResponse
    {
        try {
            $peserta = Peserta::with(['aktivitasPeserta.ujian'])->find($id);
            
            if (!$peserta) {
                return response()->json([
                    'success' => false,
                    'message' => 'Peserta tidak ditemukan'
                ], 404);
            }

            // Format data ujian yang di-assign
            $ujianAssigned = $peserta->aktivitasPeserta->map(function ($aktivitas) {
                return [
                    'ujian_id' => $aktivitas->ujian->id,
                    'nama_ujian' => $aktivitas->ujian->nama_ujian,
                    'deskripsi' => $aktivitas->ujian->deskripsi,
                    'waktu_mulai' => $aktivitas->ujian->waktu_mulai,
                    'waktu_selesai' => $aktivitas->ujian->waktu_selesai,
                    'durasi_menit' => $aktivitas->ujian->durasi_menit,
                    'status_peserta' => $aktivitas->status,
                    'waktu_login' => $aktivitas->waktu_login,
                    'waktu_submit' => $aktivitas->waktu_submit,
                    'nilai_total' => $aktivitas->nilai_total
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Detail peserta berhasil diambil',
                'data' => [
                    'peserta' => [
                        'id' => $peserta->id,
                        'username' => $peserta->username,
                        'created_at' => $peserta->created_at,
                        'updated_at' => $peserta->updated_at
                    ],
                    'ujian_assigned' => $ujianAssigned,
                    'total_ujian' => $ujianAssigned->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail peserta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign ujian ke peserta
     * 
     * @param Request $request
     * @param int $pesertaId
     * @return JsonResponse
     */
    public function assignUjianToPeserta(Request $request, int $pesertaId): JsonResponse
    {
        try {
            // Cari peserta
            $peserta = Peserta::find($pesertaId);
            if (!$peserta) {
                return response()->json([
                    'success' => false,
                    'message' => 'Peserta tidak ditemukan'
                ], 404);
            }

            // Validasi input
            $validator = Validator::make($request->all(), [
                'ujian_ids' => 'required|array|min:1',
                'ujian_ids.*' => 'exists:ujian,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            \DB::beginTransaction();

            $ujian_assigned = [];
            $ujian_already_exists = [];

            foreach ($request->ujian_ids as $ujianId) {
                // Cek apakah peserta sudah terdaftar di ujian ini
                $existingActivity = AktivitasPeserta::where('peserta_id', $pesertaId)
                                                  ->where('ujian_id', $ujianId)
                                                  ->first();

                if ($existingActivity) {
                    $ujian = Ujian::find($ujianId);
                    $ujian_already_exists[] = [
                        'ujian_id' => $ujianId,
                        'nama_ujian' => $ujian->nama_ujian,
                        'status' => $existingActivity->status
                    ];
                } else {
                    // Buat aktivitas baru
                    $ujian = Ujian::find($ujianId);
                    AktivitasPeserta::create([
                        'peserta_id' => $pesertaId,
                        'ujian_id' => $ujianId,
                        'status' => 'belum_login',
                        'waktu_login' => null,
                        'waktu_submit' => null
                    ]);

                    $ujian_assigned[] = [
                        'ujian_id' => $ujianId,
                        'nama_ujian' => $ujian->nama_ujian,
                        'status' => 'belum_login'
                    ];
                }
            }

            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Proses assign ujian selesai',
                'data' => [
                    'peserta' => [
                        'id' => $peserta->id,
                        'username' => $peserta->username
                    ],
                    'ujian_berhasil_diassign' => $ujian_assigned,
                    'ujian_sudah_terdaftar' => $ujian_already_exists,
                    'total_berhasil' => count($ujian_assigned),
                    'total_sudah_ada' => count($ujian_already_exists)
                ]
            ], 200);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal assign ujian ke peserta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unassign ujian dari peserta
     * 
     * @param int $pesertaId
     * @param int $ujianId
     * @return JsonResponse
     */
    public function unassignUjianFromPeserta(int $pesertaId, int $ujianId): JsonResponse
    {
        try {
            // Cari peserta dan ujian
            $peserta = Peserta::find($pesertaId);
            $ujian = Ujian::find($ujianId);

            if (!$peserta) {
                return response()->json([
                    'success' => false,
                    'message' => 'Peserta tidak ditemukan'
                ], 404);
            }

            if (!$ujian) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ujian tidak ditemukan'
                ], 404);
            }

            // Cari aktivitas peserta
            $aktivitas = AktivitasPeserta::where('peserta_id', $pesertaId)
                                        ->where('ujian_id', $ujianId)
                                        ->first();

            if (!$aktivitas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Peserta tidak terdaftar di ujian ini'
                ], 404);
            }

            // Cek apakah peserta sudah mengerjakan ujian
            if ($aktivitas->status !== 'belum_login') {
                return response()->json([
                    'success' => false,
                    'message' => "Tidak dapat menghapus assignment. Peserta sudah {$aktivitas->status} untuk ujian ini."
                ], 422);
            }

            // Hapus aktivitas (juga akan menghapus jawaban jika ada karena foreign key cascade)
            $aktivitas->delete();

            return response()->json([
                'success' => true,
                'message' => 'Peserta berhasil dihapus dari ujian',
                'data' => [
                    'peserta' => [
                        'id' => $peserta->id,
                        'username' => $peserta->username
                    ],
                    'ujian' => [
                        'id' => $ujian->id,
                        'nama_ujian' => $ujian->nama_ujian
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal unassign ujian dari peserta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ====== LAPORAN & MONITORING ======

    /**
     * Laporan hasil ujian per ujian
     * 
     * @param int $ujianId
     * @return JsonResponse
     */
    public function getLaporanUjian(int $ujianId): JsonResponse
    {
        try {
            $ujian = Ujian::with(['aktivitasPeserta.peserta'])->find($ujianId);
            
            if (!$ujian) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ujian tidak ditemukan'
                ], 404);
            }

            // Format data laporan
            $hasilPeserta = $ujian->aktivitasPeserta->map(function ($aktivitas) {
                return [
                    'peserta_id' => $aktivitas->peserta->id,
                    'username' => $aktivitas->peserta->username,
                    'status' => $aktivitas->status,
                    'waktu_login' => $aktivitas->waktu_login,
                    'waktu_submit' => $aktivitas->waktu_submit,
                    'nilai_total' => $aktivitas->nilai_total,
                    'durasi_pengerjaan' => $aktivitas->waktu_login && $aktivitas->waktu_submit 
                        ? \Carbon\Carbon::parse($aktivitas->waktu_submit)->diffInMinutes($aktivitas->waktu_login) . ' menit'
                        : null
                ];
            });

            // Statistik
            $totalPeserta = $hasilPeserta->count();
            $sudahSubmit = $hasilPeserta->where('status', 'selesai')->count();
            $sedangMengerjakan = $hasilPeserta->where('status', 'sedang_ujian')->count();
            $belumLogin = $hasilPeserta->where('status', 'belum_login')->count();
            
            $nilaiTertinggi = $hasilPeserta->where('nilai_total', '>', 0)->max('nilai_total') ?? 0;
            $nilaiTerendah = $hasilPeserta->where('nilai_total', '>', 0)->min('nilai_total') ?? 0;
            $rataRata = $hasilPeserta->where('nilai_total', '>', 0)->avg('nilai_total') ?? 0;

            return response()->json([
                'success' => true,
                'message' => 'Laporan ujian berhasil diambil',
                'data' => [
                    'ujian' => [
                        'id' => $ujian->id,
                        'nama_ujian' => $ujian->nama_ujian,
                        'deskripsi' => $ujian->deskripsi,
                        'waktu_mulai' => $ujian->waktu_mulai,
                        'waktu_selesai' => $ujian->waktu_selesai,
                        'durasi_menit' => $ujian->durasi_menit
                    ],
                    'statistik' => [
                        'total_peserta' => $totalPeserta,
                        'sudah_submit' => $sudahSubmit,
                        'sedang_mengerjakan' => $sedangMengerjakan,
                        'belum_login' => $belumLogin,
                        'persentase_kehadiran' => $totalPeserta > 0 ? round(($sudahSubmit + $sedangMengerjakan) / $totalPeserta * 100, 2) : 0
                    ],
                    'nilai' => [
                        'tertinggi' => round($nilaiTertinggi, 2),
                        'terendah' => round($nilaiTerendah, 2),
                        'rata_rata' => round($rataRata, 2)
                    ],
                    'hasil_peserta' => $hasilPeserta
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil laporan ujian',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Monitoring status ujian real-time
     * 
     * @return JsonResponse
     */
    public function getMonitoringUjian(): JsonResponse
    {
        try {
            // Ambil ujian yang sedang berlangsung atau akan dimulai hari ini
            $today = \Carbon\Carbon::today();
            $now = \Carbon\Carbon::now();

            $ujianAktif = Ujian::with(['aktivitasPeserta.peserta'])
                ->whereDate('waktu_mulai', '>=', $today)
                ->orWhere(function($query) use ($now) {
                    $query->where('waktu_mulai', '<=', $now)
                          ->where('waktu_selesai', '>=', $now);
                })
                ->get();

            $monitoring = $ujianAktif->map(function ($ujian) use ($now) {
                $statusUjian = 'belum_dimulai';
                if ($now->gte(\Carbon\Carbon::parse($ujian->waktu_mulai)) && $now->lte(\Carbon\Carbon::parse($ujian->waktu_selesai))) {
                    $statusUjian = 'sedang_berlangsung';
                } elseif ($now->gt(\Carbon\Carbon::parse($ujian->waktu_selesai))) {
                    $statusUjian = 'selesai';
                }

                $totalPeserta = $ujian->aktivitasPeserta->count();
                $sudahLogin = $ujian->aktivitasPeserta->whereIn('status', ['sedang_ujian', 'selesai'])->count();
                $sedangMengerjakan = $ujian->aktivitasPeserta->where('status', 'sedang_ujian')->count();
                $sudahSelesai = $ujian->aktivitasPeserta->where('status', 'selesai')->count();

                return [
                    'ujian_id' => $ujian->id,
                    'nama_ujian' => $ujian->nama_ujian,
                    'waktu_mulai' => $ujian->waktu_mulai,
                    'waktu_selesai' => $ujian->waktu_selesai,
                    'status_ujian' => $statusUjian,
                    'sisa_waktu' => $statusUjian === 'sedang_berlangsung' 
                        ? \Carbon\Carbon::parse($ujian->waktu_selesai)->diffInMinutes($now) . ' menit'
                        : null,
                    'statistik_peserta' => [
                        'total' => $totalPeserta,
                        'sudah_login' => $sudahLogin,
                        'sedang_mengerjakan' => $sedangMengerjakan,
                        'sudah_selesai' => $sudahSelesai,
                        'belum_login' => $totalPeserta - $sudahLogin
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Data monitoring berhasil diambil',
                'data' => [
                    'timestamp' => $now,
                    'ujian_monitoring' => $monitoring
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data monitoring',
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

            \DB::beginTransaction();

            // Buat ujian baru
            $ujian = Ujian::create([
                'nama_ujian' => $request->nama_ujian,
                'deskripsi' => $request->deskripsi,
                'waktu_mulai_pengerjaan' => $request->waktu_mulai_pengerjaan,
                'waktu_akhir_pengerjaan' => $request->waktu_akhir_pengerjaan
            ]);

            // Otomatis buat aktivitas peserta untuk semua peserta yang ada dengan status belum_login
            $peserta_list = Peserta::all();
            $aktivitas_created = [];

            foreach ($peserta_list as $peserta) {
                $aktivitas = AktivitasPeserta::create([
                    'peserta_id' => $peserta->id,
                    'ujian_id' => $ujian->id,
                    'status' => 'belum_login',
                    'waktu_login' => null,
                    'waktu_submit' => null
                ]);

                $aktivitas_created[] = [
                    'peserta_id' => $peserta->id,
                    'username' => $peserta->username,
                    'status' => 'belum_login'
                ];
            }

            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ujian berhasil dibuat dengan status otomatis untuk semua peserta',
                'data' => [
                    'ujian' => $ujian,
                    'total_peserta' => count($aktivitas_created),
                    'aktivitas_peserta' => $aktivitas_created
                ]
            ], 201);

        } catch (\Exception $e) {
            \DB::rollBack();
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
}