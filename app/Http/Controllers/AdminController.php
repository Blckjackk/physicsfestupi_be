<?php
namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Peserta;
use App\Models\Ujian;
use App\Models\Soal;
use App\Models\AktivitasPeserta;
use App\Models\Jawaban;
use App\Exports\HasilUjianExport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class AdminController extends Controller
{
    // ====== AUTHENTICATION ======

    /**
     * Login admin
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function loginAdmin(Request $request): JsonResponse
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'username' => 'required|string',
                'password' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Cari admin berdasarkan username
            $admin = Admin::where('username', $request->username)->first();

            if (!$admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Username tidak ditemukan'
                ], 404);
            }

            // Verifikasi password
            if (!Hash::check($request->password, $admin->password_hash)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password salah'
                ], 401);
            }

            // Generate token jika menggunakan Sanctum (optional)
            // $token = $admin->createToken('admin-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login berhasil',
                'data' => [
                    'admin' => [
                        'id' => $admin->id,
                        'username' => $admin->username,
                        'role' => $admin->role
                    ],
                    // 'token' => $token, // uncomment jika menggunakan Sanctum
                    'login_time' => now()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal login',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ====== DASHBOARD ======

    /**
     * Get dashboard statistics
     * 
     * @return JsonResponse
     */
    public function getDashboard(): JsonResponse
    {
        try {
            // Total peserta
            $totalPeserta = Peserta::count();
            
            // Count peserta berdasarkan status aktivitas
            $belumLogin = AktivitasPeserta::where('status', 'belum_login')->distinct('peserta_id')->count('peserta_id');
            $belumMulai = AktivitasPeserta::where('status', 'belum_mulai')->distinct('peserta_id')->count('peserta_id');
            $sedangMengerjakan = AktivitasPeserta::where('status', 'sedang_mengerjakan')->distinct('peserta_id')->count('peserta_id');
            $sudahSubmit = AktivitasPeserta::where('status', 'sudah_submit')->distinct('peserta_id')->count('peserta_id');
            
            return response()->json([
                'success' => true,
                'message' => 'Data dashboard berhasil diambil',
                'data' => [
                    'peserta_ujian' => $totalPeserta,
                    'belum_login' => $belumLogin,
                    'belum_mulai' => $belumMulai,
                    'sedang_mengerjakan' => $sedangMengerjakan,
                    'sudah_submit' => $sudahSubmit,
                    'last_updated' => now()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ====== PESERTA MANAGEMENT ======

    /**
     * Ambil daftar semua peserta dengan status dan ujian yang diambil
     * 
     * @return JsonResponse
     */
    public function getPeserta(): JsonResponse
    {
        try {
            $peserta = Peserta::with(['aktivitasPeserta.ujian'])
                             ->select('id', 'username', 'password_hash', 'nilai_total', 'created_at', 'updated_at')
                             ->orderBy('created_at', 'desc')
                             ->get();
            
            // Format data peserta dengan informasi ujian dan status
            $pesertaData = $peserta->map(function ($pesertaItem, $index) {
                // Ambil aktivitas peserta pertama (karena 1 peserta hanya bisa 1 ujian)
                $aktivitas = $pesertaItem->aktivitasPeserta->first();
                
                // Tentukan status berdasarkan aktivitas
                $status = 'Belum Login';
                $ujian = null;
                
                if ($aktivitas) {
                    $ujian = $aktivitas->ujian->nama_ujian;
                    
                    switch ($aktivitas->status) {
                        case 'belum_login':
                            $status = 'Belum Login';
                            break;
                        case 'belum_mulai':
                            $status = 'Belum Mulai';
                            break;
                        case 'sedang_mengerjakan':
                            $status = 'Sedang Ujian';
                            break;
                        case 'sudah_submit':
                            $status = 'Sudah Submit';
                            break;
                        default:
                            $status = 'Belum Login';
                    }
                }
                
                return [
                    'no' => $index + 1,
                    'id' => $pesertaItem->id,
                    'username' => $pesertaItem->username,
                    'password_hash' => $pesertaItem->password_hash, // Password hash untuk admin
                    'ujian' => $ujian ?? 'Tidak ada ujian',
                    'status' => $status,
                    'nilai_total' => $pesertaItem->nilai_total,
                    'created_at' => $pesertaItem->created_at,
                    'updated_at' => $pesertaItem->updated_at
                ];
            });
            
            return response()->json([
                'success' => true,
                'message' => 'Daftar peserta berhasil diambil',
                'data' => $pesertaData
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
                'nilai_total' => 'nullable|integer|min:0',
                'ujian_id' => 'sometimes|required|exists:ujian,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            \DB::beginTransaction();

            // Update data peserta
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

            // Update ujian assignment jika ada
            $ujianAssigned = null;
            if ($request->has('ujian_id')) {
                // Hapus aktivitas peserta yang lama
                AktivitasPeserta::where('peserta_id', $peserta->id)->delete();

                // Ambil data ujian yang baru dipilih
                $ujian = Ujian::find($request->ujian_id);

                // Buat aktivitas peserta baru untuk ujian yang dipilih
                $aktivitas = AktivitasPeserta::create([
                    'peserta_id' => $peserta->id,
                    'ujian_id' => $ujian->id,
                    'status' => 'belum_login',
                    'waktu_login' => null,
                    'waktu_submit' => null
                ]);

                $ujianAssigned = [
                    'ujian_id' => $ujian->id,
                    'nama_ujian' => $ujian->nama_ujian,
                    'status' => 'belum_login',
                    'waktu_mulai_pengerjaan' => $ujian->waktu_mulai_pengerjaan,
                    'waktu_akhir_pengerjaan' => $ujian->waktu_akhir_pengerjaan
                ];
            }

            \DB::commit();

            // Ambil data ujian saat ini jika tidak diupdate
            if (!$ujianAssigned) {
                $aktivitasSekarang = AktivitasPeserta::with('ujian')->where('peserta_id', $peserta->id)->first();
                if ($aktivitasSekarang) {
                    $ujianAssigned = [
                        'ujian_id' => $aktivitasSekarang->ujian->id,
                        'nama_ujian' => $aktivitasSekarang->ujian->nama_ujian,
                        'status' => $aktivitasSekarang->status,
                        'waktu_mulai_pengerjaan' => $aktivitasSekarang->ujian->waktu_mulai_pengerjaan,
                        'waktu_akhir_pengerjaan' => $aktivitasSekarang->ujian->waktu_akhir_pengerjaan
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Peserta berhasil diupdate',
                'data' => [
                    'peserta' => [
                        'id' => $peserta->id,
                        'username' => $peserta->username,
                        'nilai_total' => $peserta->nilai_total,
                        'updated_at' => $peserta->updated_at
                    ],
                    'ujian_assigned' => $ujianAssigned,
                    'changes' => [
                        'username_changed' => $request->has('username'),
                        'password_changed' => $request->has('password'),
                        'nilai_total_changed' => $request->has('nilai_total'),
                        'ujian_changed' => $request->has('ujian_id')
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            \DB::rollBack();
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
            $sudahSubmit = $hasilPeserta->where('status', 'sudah_submit')->count();
            $sedangMengerjakan = $hasilPeserta->where('status', 'sedang_mengerjakan')->count();
            $belumMulai = $hasilPeserta->where('status', 'belum_mulai')->count();
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
                        'belum_mulai' => $belumMulai,
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
                $sudahLogin = $ujian->aktivitasPeserta->whereIn('status', ['sedang_mengerjakan', 'sudah_submit'])->count();
                $sedangMengerjakan = $ujian->aktivitasPeserta->where('status', 'sedang_mengerjakan')->count();
                $sudahSelesai = $ujian->aktivitasPeserta->where('status', 'sudah_submit')->count();

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
     * Dashboard ujian - Ambil daftar ujian dengan statistik peserta
     * 
     * @return JsonResponse
     */
    public function getUjian(): JsonResponse
    {
        try {
            // Ambil semua ujian dengan aktivitas peserta
            $ujian = Ujian::with(['aktivitasPeserta.peserta'])
                    ->select('id', 'nama_ujian', 'deskripsi', 'waktu_mulai_pengerjaan', 'waktu_akhir_pengerjaan', 'created_at')
                    ->orderBy('created_at', 'desc')
                    ->get();
            
            // Format data untuk dashboard
            $dashboardData = $ujian->map(function ($ujianItem, $index) {
                // Hitung statistik berdasarkan status aktivitas peserta
                $jumlahPendaftar = $ujianItem->aktivitasPeserta->count();
                $sedangMengerjakan = $ujianItem->aktivitasPeserta->where('status', 'sedang_mengerjakan')->count();
                $selesai = $ujianItem->aktivitasPeserta->where('status', 'selesai')->count();
                
                return [
                    'no' => $index + 1,
                    'ujian_id' => $ujianItem->id,
                    'nama_ujian' => $ujianItem->nama_ujian,
                    'deskripsi' => $ujianItem->deskripsi,
                    'waktu_mulai_pengerjaan' => $ujianItem->waktu_mulai_pengerjaan,
                    'waktu_akhir_pengerjaan' => $ujianItem->waktu_akhir_pengerjaan,
                    'jumlah_pendaftar' => $jumlahPendaftar,
                    'sedang_mengerjakan' => $sedangMengerjakan,
                    'selesai' => $selesai,
                    'created_at' => $ujianItem->created_at
                ];
            });

            // Ambil data lengkap aktivitas peserta untuk semua ujian
            $aktivitasPeserta = AktivitasPeserta::with(['peserta', 'ujian'])
                ->select('id', 'peserta_id', 'ujian_id', 'status', 'waktu_login', 'waktu_submit', 'created_at', 'updated_at')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($aktivitas) {
                    return [
                        'aktivitas_id' => $aktivitas->id,
                        'peserta_id' => $aktivitas->peserta_id,
                        'username' => $aktivitas->peserta->username,
                        'ujian_id' => $aktivitas->ujian_id,
                        'nama_ujian' => $aktivitas->ujian->nama_ujian,
                        'status' => $aktivitas->status,
                        'waktu_login' => $aktivitas->waktu_login,
                        'waktu_submit' => $aktivitas->waktu_submit,
                        'durasi_pengerjaan' => $aktivitas->waktu_login && $aktivitas->waktu_submit 
                            ? \Carbon\Carbon::parse($aktivitas->waktu_submit)->diffInMinutes($aktivitas->waktu_login) . ' menit'
                            : null,
                        'created_at' => $aktivitas->created_at,
                        'updated_at' => $aktivitas->updated_at
                    ];
                });
            
            return response()->json([
                'success' => true,
                'message' => 'Dashboard ujian berhasil diambil',
                'data' => [
                    'ujian_dashboard' => $dashboardData,
                    'aktivitas_peserta' => $aktivitasPeserta,
                    'summary' => [
                        'total_ujian' => $ujian->count(),
                        'total_peserta_terdaftar' => $aktivitasPeserta->unique('peserta_id')->count(),
                        'total_aktivitas' => $aktivitasPeserta->count()
                    ]
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil dashboard ujian',
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

    // ====== JAWABAN & HASIL UJIAN ======

    /**
     * Get hasil ujian semua peserta - Dashboard hasil ujian
     * 
     * @return JsonResponse
     */
    public function getJawabanPeserta(): JsonResponse
    {
        try {
            // Ambil semua aktivitas peserta yang sudah login dengan relasi yang diperlukan
            $aktivitasPeserta = AktivitasPeserta::with([
                'peserta:id,username',
                'ujian:id,nama_ujian',
                'ujian.soal:id,ujian_id' // Untuk hitung jumlah soal
            ])
            ->whereIn('status', ['sedang_mengerjakan', 'selesai']) // Hanya yang sudah mulai ujian
            ->orderBy('created_at', 'desc')
            ->get();

            // Format data hasil ujian
            $hasilUjian = $aktivitasPeserta->map(function ($aktivitas, $index) {
                // Hitung jumlah soal di ujian
                $jumlahSoal = $aktivitas->ujian->soal->count();
                
                // Hitung jumlah soal yang sudah dijawab peserta
                $terjawab = Jawaban::where('peserta_id', $aktivitas->peserta_id)
                                  ->where('ujian_id', $aktivitas->ujian_id)
                                  ->whereNotNull('jawaban_peserta')
                                  ->count();

                return [
                    'no' => $index + 1,
                    'username' => $aktivitas->peserta->username,
                    'nama_ujian' => $aktivitas->ujian->nama_ujian,
                    'mulai' => $aktivitas->waktu_login ? $aktivitas->waktu_login->format('d/m/Y H:i:s') : '-',
                    'selesai' => $aktivitas->waktu_submit ? $aktivitas->waktu_submit->format('d/m/Y H:i:s') : '-',
                    'jumlah_soal' => $jumlahSoal,
                    'terjawab' => $terjawab,
                    'progress' => $jumlahSoal > 0 ? round(($terjawab / $jumlahSoal) * 100, 1) . '%' : '0%',
                    'status' => $aktivitas->status === 'selesai' ? 'Selesai' : 'Sedang Ujian',
                    'peserta_id' => $aktivitas->peserta_id,
                    'ujian_id' => $aktivitas->ujian_id,
                    'aktivitas_id' => $aktivitas->id
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Data hasil ujian berhasil diambil',
                'data' => $hasilUjian,
                'summary' => [
                    'total_peserta_ujian' => $hasilUjian->count(),
                    'sedang_ujian' => $hasilUjian->where('status', 'Sedang Ujian')->count(),
                    'sudah_selesai' => $hasilUjian->where('status', 'Selesai')->count(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data hasil ujian',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detail jawaban peserta per ujian
     * 
     * @param int $pesertaId
     * @param int $ujianId
     * @return JsonResponse
     */
    public function getDetailJawabanPeserta(int $pesertaId, int $ujianId): JsonResponse
    {
        try {
            // Validasi peserta dan ujian
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

            // Ambil aktivitas peserta
            $aktivitas = AktivitasPeserta::where('peserta_id', $pesertaId)
                                        ->where('ujian_id', $ujianId)
                                        ->first();

            if (!$aktivitas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Peserta tidak terdaftar di ujian ini'
                ], 404);
            }

            // Ambil semua soal ujian dengan jawaban peserta
            $soalDenganJawaban = Soal::where('ujian_id', $ujianId)
                ->with(['jawaban' => function($query) use ($pesertaId) {
                    $query->where('peserta_id', $pesertaId);
                }])
                ->orderBy('nomor_soal', 'asc')
                ->get()
                ->map(function ($soal) {
                    $jawaban = $soal->jawaban->first();
                    
                    return [
                        'nomor_soal' => $soal->nomor_soal,
                        'pertanyaan' => $soal->pertanyaan,
                        'opsi_a' => $soal->opsi_a,
                        'opsi_b' => $soal->opsi_b,
                        'opsi_c' => $soal->opsi_c,
                        'opsi_d' => $soal->opsi_d,
                        'opsi_e' => $soal->opsi_e,
                        'jawaban_benar' => $soal->jawaban_benar,
                        'jawaban_peserta' => $jawaban ? $jawaban->jawaban_peserta : null,
                        'is_correct' => $jawaban ? $jawaban->benar : null,
                        'status_jawaban' => $jawaban ? 
                            ($jawaban->jawaban_peserta ? 'dijawab' : 'kosong') : 'kosong'
                    ];
                });

            // Hitung statistik
            $totalSoal = $soalDenganJawaban->count();
            $dijawab = $soalDenganJawaban->where('status_jawaban', 'dijawab')->count();
            $benar = $soalDenganJawaban->where('is_correct', true)->count();
            $salah = $soalDenganJawaban->where('is_correct', false)->count();

            return response()->json([
                'success' => true,
                'message' => 'Detail jawaban peserta berhasil diambil',
                'data' => [
                    'peserta' => [
                        'id' => $peserta->id,
                        'username' => $peserta->username
                    ],
                    'ujian' => [
                        'id' => $ujian->id,
                        'nama_ujian' => $ujian->nama_ujian
                    ],
                    'aktivitas' => [
                        'status' => $aktivitas->status,
                        'waktu_login' => $aktivitas->waktu_login,
                        'waktu_submit' => $aktivitas->waktu_submit,
                        'durasi' => $aktivitas->getDurationInMinutes()
                    ],
                    'statistik' => [
                        'total_soal' => $totalSoal,
                        'dijawab' => $dijawab,
                        'kosong' => $totalSoal - $dijawab,
                        'benar' => $benar,
                        'salah' => $salah,
                        'nilai' => $totalSoal > 0 ? round(($benar / $totalSoal) * 100, 2) : 0
                    ],
                    'soal_jawaban' => $soalDenganJawaban
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail jawaban peserta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export hasil ujian ke Excel
     * 
     * @param int $ujianId
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
     */
    public function exportHasilUjian(int $ujianId)
    {
        try {
            // Validasi ujian
            $ujian = Ujian::find($ujianId);
            
            if (!$ujian) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ujian tidak ditemukan'
                ], 404);
            }

            // Cek apakah ada peserta yang sudah mengambil ujian
            $aktivitasCount = AktivitasPeserta::where('ujian_id', $ujianId)
                                            ->whereIn('status', ['sedang_mengerjakan', 'selesai'])
                                            ->count();

            if ($aktivitasCount == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Belum ada peserta yang mengambil ujian ini'
                ], 400);
            }

            // Generate filename dengan timestamp
            $timestamp = now()->format('Y-m-d_H-i-s');
            $cleanUjianName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $ujian->nama_ujian);
            $filename = "Hasil_Ujian_{$cleanUjianName}_{$timestamp}.xlsx";

            // Set headers untuk download
            $headers = [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0',
                'Cache-Control' => 'max-age=1',
                'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
                'Last-Modified' => gmdate('D, d M Y H:i:s') . ' GMT',
                'Cache-Control' => 'cache, must-revalidate',
                'Pragma' => 'public',
            ];

            return Excel::download(
                new HasilUjianExport($ujianId),
                $filename,
                \Maatwebsite\Excel\Excel::XLSX,
                $headers
            );

        } catch (\Exception $e) {
            \Log::error('Export Excel Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal export hasil ujian',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get info export hasil ujian (untuk preview sebelum download)
     * 
     * @param int $ujianId
     * @return JsonResponse
     */
    public function getInfoExportUjian(int $ujianId): JsonResponse
    {
        try {
            // Validasi ujian
            $ujian = Ujian::find($ujianId);
            
            if (!$ujian) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ujian tidak ditemukan'
                ], 404);
            }

            // Hitung statistik
            $totalSoal = Soal::where('ujian_id', $ujianId)->count();
            $totalPeserta = AktivitasPeserta::where('ujian_id', $ujianId)
                                          ->whereIn('status', ['sedang_mengerjakan', 'selesai'])
                                          ->count();
            $sedangUjian = AktivitasPeserta::where('ujian_id', $ujianId)
                                         ->where('status', 'sedang_mengerjakan')
                                         ->count();
            $sudahSelesai = AktivitasPeserta::where('ujian_id', $ujianId)
                                          ->where('status', 'selesai')
                                          ->count();

            // Perkiraan kolom yang akan diexport
            $kolomDasar = 13; // Username, Status, Waktu, dll
            $kolomSoal = $totalSoal * 4; // Setiap soal = 4 kolom (Pertanyaan, Jawaban Benar, Jawaban Peserta, Status)
            $totalKolom = $kolomDasar + $kolomSoal;

            return response()->json([
                'success' => true,
                'message' => 'Info export berhasil diambil',
                'data' => [
                    'ujian' => [
                        'id' => $ujian->id,
                        'nama_ujian' => $ujian->nama_ujian,
                        'deskripsi' => $ujian->deskripsi,
                        'waktu_mulai_pengerjaan' => $ujian->waktu_mulai_pengerjaan,
                        'waktu_akhir_pengerjaan' => $ujian->waktu_akhir_pengerjaan
                    ],
                    'statistik' => [
                        'total_soal' => $totalSoal,
                        'total_peserta_export' => $totalPeserta,
                        'sedang_ujian' => $sedangUjian,
                        'sudah_selesai' => $sudahSelesai,
                        'estimasi_kolom' => $totalKolom,
                        'estimasi_baris' => $totalPeserta + 1 // +1 untuk header
                    ],
                    'export_info' => [
                        'format' => 'Excel (.xlsx)',
                        'kolom_dasar' => [
                            'No', 'Username', 'Nama Ujian', 'Status', 'Waktu Login', 
                            'Waktu Submit', 'Durasi (Menit)', 'Total Soal', 'Dijawab', 
                            'Kosong', 'Benar', 'Salah', 'Nilai'
                        ],
                        'kolom_per_soal' => [
                            'Soal X', 'Jawaban Benar X', 'Jawaban Peserta X', 'Status X'
                        ],
                        'estimated_size' => $this->estimateFileSize($totalPeserta, $totalKolom),
                        'can_export' => $totalPeserta > 0 && $totalSoal > 0
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil info export',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Estimate file size for export
     */
    private function estimateFileSize($totalRows, $totalCols): string
    {
        // Estimasi kasar: 50 bytes per cell + overhead Excel
        $estimatedBytes = ($totalRows * $totalCols * 50) + 10240; // 10KB overhead
        
        if ($estimatedBytes < 1024) {
            return $estimatedBytes . ' bytes';
        } elseif ($estimatedBytes < 1048576) {
            return round($estimatedBytes / 1024, 1) . ' KB';
        } else {
            return round($estimatedBytes / 1048576, 1) . ' MB';
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