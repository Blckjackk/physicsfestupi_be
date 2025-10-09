<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Peserta;
use App\Models\Ujian;
use App\Models\Soal;
use App\Models\Jawaban;
use App\Models\AktivitasPeserta;

class PesertaController extends Controller
{
    /**
     * Helper method untuk mendapatkan ujian_id yang di-assign ke peserta
     */
    private function getPesertaUjianId($peserta_id)
    {
        $aktivitas = AktivitasPeserta::where('peserta_id', $peserta_id)->first();
        return $aktivitas ? $aktivitas->ujian_id : null;
    }

    /**
     * Helper method untuk auto-update status aktivitas peserta
     */
    private function autoUpdateStatusAktivitas($peserta_id, $ujian_id)
    {
        $ujian = Ujian::find($ujian_id);
        if (!$ujian) {
            return false;
        }

        $now = Carbon::now()->setTimezone('Asia/Jakarta');
        $waktu_mulai = Carbon::parse($ujian->waktu_mulai_pengerjaan)->setTimezone('Asia/Jakarta');
        $waktu_akhir = Carbon::parse($ujian->waktu_akhir_pengerjaan)->setTimezone('Asia/Jakarta');

        // Get or create aktivitas peserta
        $aktivitas = AktivitasPeserta::firstOrCreate(
            [
                'peserta_id' => $peserta_id,
                'ujian_id' => $ujian_id
            ],
            [
                'status' => 'belum_login',
                'waktu_login' => null,
                'waktu_submit' => null
            ]
        );

        // Auto-update status berdasarkan waktu dan kondisi saat ini
        if ($now->lt($waktu_mulai)) {
            // Ujian belum mulai
            if ($aktivitas->status === 'belum_login') {
                $aktivitas->status = 'belum_mulai';
                $aktivitas->save();
            }
        } elseif ($now->gte($waktu_mulai) && $now->lt($waktu_akhir)) {
            // Ujian sedang berlangsung
            if (in_array($aktivitas->status, ['belum_login', 'belum_mulai'])) {
                $aktivitas->status = 'sedang_mengerjakan';
                if (!$aktivitas->waktu_login) {
                    $aktivitas->waktu_login = $now;
                }
                $aktivitas->save();
            }
        }

        return $aktivitas;
    }

    /**
     * Login peserta
     */
    public function login(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string'
            ]);

            $peserta = Peserta::where('username', $request->username)->first();

            // Debug info
            if (!$peserta) {
                return response()->json([
                    'success' => false,
                    'message' => 'Username tidak ditemukan',
                    'debug' => [
                        'username_input' => $request->username,
                        'total_peserta' => Peserta::count()
                    ]
                ], 401);
            }

            if (!Hash::check($request->password, $peserta->password_hash)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password salah',
                    'debug' => [
                        'username' => $request->username,
                        'password_input' => $request->password,
                        'stored_hash' => $peserta->password_hash
                    ]
                ], 401);
            }

            // Buat token untuk autentikasi (temporarily disabled)
            // $token = $peserta->createToken('peserta-token')->plainTextToken;
            $token = 'temporary-token-' . $peserta->id . '-' . time();

            // Get aktivitas peserta - ambil ujian yang di-assign ke peserta ini
            $aktivitas = AktivitasPeserta::where('peserta_id', $peserta->id)->first();
            
            if (!$aktivitas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Peserta belum di-assign ke ujian manapun',
                    'debug' => [
                        'peserta_id' => $peserta->id,
                        'username' => $peserta->username
                    ]
                ], 403);
            }

            // Get ujian data berdasarkan aktivitas peserta
            $ujian = Ujian::find($aktivitas->ujian_id);

            // Validasi waktu ujian
            if ($ujian) {
                $now = Carbon::now();
                $waktu_mulai = Carbon::parse($ujian->waktu_mulai_pengerjaan);
                $waktu_akhir = Carbon::parse($ujian->waktu_akhir_pengerjaan);

                // Jika ujian belum dimulai
                if ($now->lt($waktu_mulai)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ujian belum dimulai',
                        'error_type' => 'exam_not_started',
                        'waktu_mulai' => $waktu_mulai->format('Y-m-d H:i:s'),
                        'waktu_sekarang' => $now->format('Y-m-d H:i:s'),
                        'waktu_mulai_formatted' => $waktu_mulai->format('d M Y, H:i'),
                    ], 403);
                }

                // Jika ujian sudah berakhir
                if ($now->gt($waktu_akhir)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ujian sudah berakhir',
                        'error_type' => 'exam_ended',
                        'waktu_akhir' => $waktu_akhir->format('Y-m-d H:i:s'),
                        'waktu_sekarang' => $now->format('Y-m-d H:i:s'),
                        'waktu_akhir_formatted' => $waktu_akhir->format('d M Y, H:i'),
                    ], 403);
                }
            }

            $aktivitas_data = [
                'ujian_id' => $aktivitas->ujian_id,
                'status' => $aktivitas->status,
                'waktu_login' => $aktivitas->waktu_login,
                'waktu_submit' => $aktivitas->waktu_submit
            ];

            $ujian_data = $ujian ? [
                'id' => $ujian->id,
                'nama_ujian' => $ujian->nama_ujian,
                'deskripsi' => $ujian->deskripsi,
                'waktu_mulai_pengerjaan' => $ujian->waktu_mulai_pengerjaan,
                'waktu_akhir_pengerjaan' => $ujian->waktu_akhir_pengerjaan
            ] : null;

            return response()->json([
                'success' => true,
                'message' => 'Login berhasil',
                'data' => [
                    'peserta' => $peserta,
                    'token' => $token,
                    'aktivitas_ujian' => $aktivitas_data,
                    'ujian' => $ujian_data
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout peserta
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logout berhasil'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current peserta data for authentication validation
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getMe(Request $request)
    {
        try {
            // Cek header Authorization
            $authHeader = $request->header('Authorization');
            
            if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token tidak ditemukan'
                ], 401);
            }

            // Extract token (untuk simulasi, karena belum pakai Sanctum sepenuhnya)
            $token = substr($authHeader, 7); // Remove "Bearer " prefix
            
            // Validasi token (simulasi - dalam implementasi nyata gunakan Sanctum)
            if (empty($token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token tidak valid'
                ], 401);
            }

            // Simulasi: untuk testing, kembalikan data peserta dummy
            // Dalam implementasi nyata, gunakan $request->user() dengan Sanctum
            
            return response()->json([
                'success' => true,
                'message' => 'Data peserta berhasil diambil',
                'data' => [
                    'id' => 1,
                    'username' => 'peserta01',
                    'nama_lengkap' => 'Test Peserta',
                    'role' => 'peserta',
                    'created_at' => now()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data peserta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mendapatkan daftar ujian yang tersedia
     */
    public function getAvailableUjian(Request $request)
    {
        try {
            // Debug: cek koneksi database
            $count = Ujian::count();
            
            $ujian = Ujian::all();

            return response()->json([
                'success' => true,
                'message' => 'Data ujian berhasil diambil',
                'debug' => [
                    'total_ujian' => $count,
                    'ujian_table_exists' => true
                ],
                'data' => $ujian
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'error_detail' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    /**
     * Mendapatkan detail ujian tertentu
     */
    public function getUjianDetail(Request $request, $id)
    {
        try {
            $ujian = Ujian::find($id);

            if (!$ujian) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ujian tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail ujian berhasil diambil',
                'data' => $ujian
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cek waktu ujian - apakah sudah bisa dimulai atau belum
     */
    public function cekWaktuUjian(Request $request, $id)
    {
        try {
            // For testing without middleware, use peserta_id from request or default to 1
            $peserta_id = $request->peserta_id ?? 1; // Default peserta ID for testing

            $ujian = Ujian::find($id);

            if (!$ujian) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ujian tidak ditemukan'
                ], 404);
            }

            // Auto-update status aktivitas peserta
            $this->autoUpdateStatusAktivitas($peserta_id, $id);

            // Pastikan semua waktu menggunakan timezone Asia/Jakarta (WIB)
            $now = Carbon::now()->setTimezone('Asia/Jakarta');
            $waktu_mulai = Carbon::parse($ujian->waktu_mulai_pengerjaan)->setTimezone('Asia/Jakarta');
            $waktu_akhir = Carbon::parse($ujian->waktu_akhir_pengerjaan)->setTimezone('Asia/Jakarta');

            $status_waktu = 'belum_mulai'; // default
            $pesan = '';
            $countdown = null;

            if ($now->lt($waktu_mulai)) {
                $status_waktu = 'belum_mulai';
                $countdown = $waktu_mulai->diffInSeconds($now);
                $pesan = 'Ujian belum dimulai. Akan dimulai pada ' . $waktu_mulai->format('d/m/Y H:i:s T');
            } elseif ($now->gte($waktu_mulai) && $now->lt($waktu_akhir)) {
                $status_waktu = 'bisa_mulai';
                $pesan = 'Ujian sedang berlangsung. Anda bisa memulai mengerjakan';
            } else {
                $status_waktu = 'sudah_berakhir';
                $pesan = 'Ujian sudah berakhir';
            }

            return response()->json([
                'success' => true,
                'message' => $pesan,
                'data' => [
                    'status_waktu' => $status_waktu,
                    'countdown_seconds' => $countdown,
                    'waktu_mulai' => $waktu_mulai->format('d/m/Y H:i:s T'),
                    'waktu_akhir' => $waktu_akhir->format('d/m/Y H:i:s T'),
                    'waktu_sekarang' => $now->format('d/m/Y H:i:s T'),
                    'timezone' => 'Asia/Jakarta (WIB)'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mulai ujian - catat waktu login dan update status aktivitas
     */
    public function mulaiUjian(Request $request)
    {
        try {
            $request->validate([
                'ujian_id' => 'required|integer|exists:ujian,id',
                'peserta_id' => 'sometimes|integer|exists:peserta,id' // Optional for testing
            ]);

            // For testing without middleware, use peserta_id from request or default to 1
            $peserta_id = $request->peserta_id ?? 1; // Default peserta ID for testing
            
            // Get ujian_id yang benar dari peserta (ignore request ujian_id untuk konsistensi)
            $peserta_ujian_id = $this->getPesertaUjianId($peserta_id);
            if (!$peserta_ujian_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Peserta belum di-assign ke ujian manapun',
                    'debug' => [
                        'peserta_id' => $peserta_id,
                        'request_ujian_id' => $request->ujian_id
                    ]
                ], 403);
            }

            // Cek apakah ujian masih berjalan
            $ujian = Ujian::find($peserta_ujian_id);
            $now = Carbon::now()->setTimezone('Asia/Jakarta');
            $waktu_mulai = Carbon::parse($ujian->waktu_mulai_pengerjaan)->setTimezone('Asia/Jakarta');
            $waktu_akhir = Carbon::parse($ujian->waktu_akhir_pengerjaan)->setTimezone('Asia/Jakarta');

            // Cek atau buat aktivitas peserta
            $aktivitas = AktivitasPeserta::firstOrCreate(
                [
                    'peserta_id' => $peserta_id,
                    'ujian_id' => $peserta_ujian_id
                ],
                [
                    'status' => 'belum_login',
                    'waktu_login' => null,
                    'waktu_submit' => null
                ]
            );

            // Update waktu login jika belum ada
            if (!$aktivitas->waktu_login) {
                $aktivitas->waktu_login = $now;
            }

            // Tentukan status berdasarkan waktu
            if ($now->lt($waktu_mulai)) {
                $aktivitas->status = 'belum_mulai';
                $countdown = $waktu_mulai->diffInSeconds($now);
                
                $aktivitas->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Login berhasil! Ujian belum dimulai.',
                    'data' => [
                        'status' => 'belum_mulai',
                        'countdown_seconds' => $countdown,
                        'waktu_mulai' => $waktu_mulai->format('d/m/Y H:i:s'),
                        'can_start' => false
                    ]
                ]);
            } elseif ($now->gte($waktu_mulai) && $now->lt($waktu_akhir)) {
                // Cek apakah sudah submit sebelumnya
                if ($aktivitas->status === 'sudah_submit') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Anda sudah menyelesaikan ujian ini',
                        'data' => [
                            'status' => 'sudah_submit',
                            'can_start' => false
                        ]
                    ]);
                }

                $aktivitas->status = 'sedang_mengerjakan';
                $aktivitas->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Berhasil masuk ke ujian. Silakan mulai mengerjakan!',
                    'data' => [
                        'status' => 'sedang_mengerjakan',
                        'waktu_akhir' => $waktu_akhir->format('d/m/Y H:i:s'),
                        'can_start' => true
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Ujian sudah berakhir',
                    'data' => [
                        'status' => 'sudah_berakhir',
                        'can_start' => false
                    ]
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mendapatkan status aktivitas peserta untuk ujian tertentu
     */
    public function getStatusUjian(Request $request, $id)
    {
        try {
            $peserta_id = $request->user()->id;
            
            // Get ujian_id yang benar dari peserta (ignore parameter URL)
            $peserta_ujian_id = $this->getPesertaUjianId($peserta_id);
            if (!$peserta_ujian_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Peserta belum di-assign ke ujian manapun',
                    'debug' => [
                        'peserta_id' => $peserta_id,
                        'url_ujian_id' => $id
                    ]
                ], 403);
            }
            
            $aktivitas = AktivitasPeserta::where('peserta_id', $peserta_id)
                                       ->where('ujian_id', $peserta_ujian_id)
                                       ->first();

            $status = $aktivitas ? $aktivitas->status : 'belum_login';

            return response()->json([
                'success' => true,
                'message' => 'Status aktivitas berhasil diambil',
                'data' => [
                    'status' => $status,
                    'waktu_login' => $aktivitas ? $aktivitas->waktu_login : null,
                    'waktu_submit' => $aktivitas ? $aktivitas->waktu_submit : null
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mendapatkan semua soal ujian (untuk navigasi)
     */
    public function getSoalUjian(Request $request, $ujian_id)
    {
        try {
            // For testing without middleware, use peserta_id from request or default to 1
            $peserta_id = $request->peserta_id ?? 1; // Default peserta ID for testing
            
            // Get ujian_id yang benar dari peserta (ignore parameter URL untuk mencegah bug)
            $peserta_ujian_id = $this->getPesertaUjianId($peserta_id);
            if (!$peserta_ujian_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Peserta belum di-assign ke ujian manapun',
                    'debug' => [
                        'peserta_id' => $peserta_id,
                        'url_ujian_id' => $ujian_id
                    ]
                ], 403);
            }
            
            // Debug info
            if ($request->has('debug')) {
                return response()->json([
                    'debug' => true,
                    'url_ujian_id' => $ujian_id,
                    'peserta_ujian_id' => $peserta_ujian_id,
                    'peserta_id' => $peserta_id,
                    'request_data' => $request->all()
                ]);
            }

            // Auto-update status aktivitas peserta menggunakan ujian_id yang benar
            $aktivitas = $this->autoUpdateStatusAktivitas($peserta_id, $peserta_ujian_id);
            if (!$aktivitas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ujian tidak ditemukan'
                ], 404);
            }

            // Cek apakah ujian ada (gunakan ujian_id yang benar)
            $ujian = Ujian::find($peserta_ujian_id);
            if (!$ujian) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ujian tidak ditemukan'
                ], 404);
            }

            // Validasi waktu ujian
            $now = Carbon::now();
            $waktu_mulai = Carbon::parse($ujian->waktu_mulai_pengerjaan);
            $waktu_akhir = Carbon::parse($ujian->waktu_akhir_pengerjaan);

            // Jika ujian belum dimulai
            if ($now->lt($waktu_mulai)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ujian belum dimulai',
                    'error_type' => 'exam_not_started',
                    'waktu_mulai' => $waktu_mulai->format('Y-m-d H:i:s'),
                    'waktu_sekarang' => $now->format('Y-m-d H:i:s'),
                    'waktu_mulai_formatted' => $waktu_mulai->format('d M Y, H:i'),
                ], 403);
            }

            // Jika ujian sudah berakhir
            if ($now->gt($waktu_akhir)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ujian sudah berakhir',
                    'error_type' => 'exam_ended',
                    'waktu_akhir' => $waktu_akhir->format('Y-m-d H:i:s'),
                    'waktu_sekarang' => $now->format('Y-m-d H:i:s'),
                    'waktu_akhir_formatted' => $waktu_akhir->format('d M Y, H:i'),
                ], 403);
            }

            // Ambil soal-soal ujian (gunakan ujian_id yang benar)
            $soal = Soal::where('ujian_id', $peserta_ujian_id)
                       ->orderBy('nomor_soal')
                       ->get();

            if ($soal->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada soal untuk ujian ini'
                ], 404);
            }

            // Ambil jawaban peserta yang sudah tersimpan (gunakan ujian_id yang benar)
            $jawaban_peserta = Jawaban::where('peserta_id', $peserta_id)
                                    ->where('ujian_id', $peserta_ujian_id)
                                    ->get()
                                    ->keyBy('soal_id');

            // Gabungkan soal dengan jawaban yang sudah ada
            $soal_dengan_jawaban = $soal->map(function ($item) use ($jawaban_peserta) {
                $jawaban = $jawaban_peserta->get($item->id);
                $item->jawaban_peserta = $jawaban ? $jawaban->jawaban_peserta : null;
                $item->sudah_dijawab = $jawaban ? true : false;
                return $item;
            });

            // Calculate remaining time based on exam schedule and peserta login time
            $sekarang = Carbon::now();
            $waktu_mulai = Carbon::parse($ujian->waktu_mulai_pengerjaan);
            $waktu_akhir = Carbon::parse($ujian->waktu_akhir_pengerjaan);
            
            // Calculate exam duration in seconds
            $durasi_ujian_detik = $waktu_mulai->diffInSeconds($waktu_akhir);
            
            // Check if exam has started
            if ($sekarang < $waktu_mulai) {
                // Exam hasn't started yet
                $waktu_tersisa_detik = $durasi_ujian_detik; // Full duration available
            } elseif ($sekarang > $waktu_akhir) {
                // Exam has ended
                $waktu_tersisa_detik = 0;
            } else {
                // Exam is active - calculate remaining time
                $waktu_tersisa_detik = max(0, $sekarang->diffInSeconds($waktu_akhir, false));
            }

            return response()->json([
                'success' => true,
                'message' => 'Soal ujian berhasil diambil',
                'data' => [
                    'ujian' => [
                        'id' => $ujian->id,
                        'nama_ujian' => $ujian->nama_ujian,
                        'deskripsi' => $ujian->deskripsi,
                        'waktu_mulai_pengerjaan' => $ujian->waktu_mulai_pengerjaan,
                        'waktu_akhir_pengerjaan' => $ujian->waktu_akhir_pengerjaan,
                        'server_time' => Carbon::now()->toISOString(),
                        'waktu_tersisa_detik' => $waktu_tersisa_detik
                    ],
                    'total_soal' => $soal->count(),
                    'soal' => $soal_dengan_jawaban
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    /**
     * Mendapatkan soal berdasarkan nomor soal tertentu
     */
    public function getSoalByNomor(Request $request, $ujian_id, $nomor_soal)
    {
        try {
            $peserta_id = $request->user()->id;

            // Cek status peserta untuk ujian ini
            $aktivitas = AktivitasPeserta::where('peserta_id', $peserta_id)
                                       ->where('ujian_id', $ujian_id)
                                       ->first();

            if (!$aktivitas || $aktivitas->status !== 'sedang_mengerjakan') {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda belum memulai ujian atau ujian sudah selesai'
                ], 403);
            }

            // Ambil soal berdasarkan nomor
            $soal = Soal::where('ujian_id', $ujian_id)
                       ->where('nomor_soal', $nomor_soal)
                       ->first();

            if (!$soal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Soal tidak ditemukan'
                ], 404);
            }

            // Ambil jawaban peserta jika ada
            $jawaban = Jawaban::where('peserta_id', $peserta_id)
                            ->where('ujian_id', $ujian_id)
                            ->where('soal_id', $soal->id)
                            ->first();

            $soal->jawaban_peserta = $jawaban ? $jawaban->jawaban_peserta : null;

            return response()->json([
                'success' => true,
                'message' => 'Soal berhasil diambil',
                'data' => $soal
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simpan jawaban peserta (auto-save)
     */
    public function simpanJawaban(Request $request)
    {
        try {
            $request->validate([
                'ujian_id' => 'required|integer|exists:ujian,id',
                'soal_id' => 'required|integer|exists:soal,id',
                'jawaban' => 'required|string|in:a,b,c,d,e', // Changed from jawaban_peserta to jawaban
                'peserta_id' => 'sometimes|integer|exists:peserta,id' // Optional for testing
            ]);

            // For testing without middleware, use peserta_id from request or default to 1
            $peserta_id = $request->peserta_id ?? 1; // Default peserta ID for testing
            
            // Get ujian_id yang benar dari peserta (ignore request ujian_id untuk mencegah bug)
            $peserta_ujian_id = $this->getPesertaUjianId($peserta_id);
            if (!$peserta_ujian_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Peserta belum di-assign ke ujian manapun',
                    'debug' => [
                        'peserta_id' => $peserta_id,
                        'request_ujian_id' => $request->ujian_id
                    ]
                ], 403);
            }
            
            // Debug info
            if ($request->has('debug')) {
                return response()->json([
                    'debug' => true,
                    'request_data' => $request->all(),
                    'peserta_id' => $peserta_id,
                    'peserta_ujian_id' => $peserta_ujian_id
                ]);
            }

            // Auto-update status aktivitas peserta (gunakan ujian_id yang benar)
            $this->autoUpdateStatusAktivitas($peserta_id, $peserta_ujian_id);

            // Ambil jawaban benar dari soal
            $soal = Soal::find($request->soal_id);
            if (!$soal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Soal tidak ditemukan'
                ], 404);
            }

            $benar = ($request->jawaban === $soal->jawaban_benar) ? 1 : 0;

            // Simpan atau update jawaban (gunakan ujian_id yang benar)
            $jawaban = Jawaban::updateOrCreate(
                [
                    'peserta_id' => $peserta_id,
                    'ujian_id' => $peserta_ujian_id,
                    'soal_id' => $request->soal_id
                ],
                [
                    'jawaban_peserta' => $request->jawaban, // Save as jawaban_peserta in database
                    'benar' => $benar
                ]
            );

            // Get soal nomor for response
            $nomor_soal = $soal->nomor_soal;

            return response()->json([
                'success' => true,
                'message' => 'Jawaban berhasil disimpan',
                'data' => [
                    'jawaban_id' => $jawaban->id,
                    'peserta_id' => $peserta_id,
                    'ujian_id' => $peserta_ujian_id,
                    'soal_id' => $request->soal_id,
                    'nomor_soal' => $nomor_soal,
                    'jawaban' => $request->jawaban,
                    'benar' => $benar ? true : false,
                    'jawaban_benar' => $soal->jawaban_benar,
                    'waktu_simpan' => now()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s T')
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    /**
     * Auto save jawaban (dipanggil periodik)
     */
    public function autoSaveJawaban(Request $request)
    {
        try {
            $request->validate([
                'ujian_id' => 'required|integer|exists:ujian,id',
                'jawaban' => 'required|array',
                'jawaban.*.soal_id' => 'required|integer|exists:soal,id',
                'jawaban.*.jawaban_peserta' => 'required|string|in:a,b,c,d,e'
            ]);

            $peserta_id = $request->user()->id;
            $saved_count = 0;

            // Cek apakah peserta sedang mengerjakan ujian
            $aktivitas = AktivitasPeserta::where('peserta_id', $peserta_id)
                                       ->where('ujian_id', $request->ujian_id)
                                       ->first();

            if (!$aktivitas || $aktivitas->status !== 'sedang_mengerjakan') {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak sedang mengerjakan ujian ini'
                ], 403);
            }

            DB::beginTransaction();

            foreach ($request->jawaban as $jawaban_data) {
                // Ambil jawaban benar dari soal
                $soal = Soal::find($jawaban_data['soal_id']);
                $benar = ($jawaban_data['jawaban_peserta'] === $soal->jawaban_benar) ? 1 : 0;

                // Simpan atau update jawaban
                Jawaban::updateOrCreate(
                    [
                        'peserta_id' => $peserta_id,
                        'ujian_id' => $request->ujian_id,
                        'soal_id' => $jawaban_data['soal_id']
                    ],
                    [
                        'jawaban_peserta' => $jawaban_data['jawaban_peserta'],
                        'benar' => $benar
                    ]
                );

                $saved_count++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Auto save berhasil untuk {$saved_count} jawaban",
                'data' => [
                    'saved_count' => $saved_count
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mendapatkan jawaban peserta untuk ujian tertentu
     */
    public function getJawabanPeserta(Request $request, $ujian_id)
    {
        try {
            // For testing without middleware, use peserta_id from request or default to 1
            $peserta_id = $request->peserta_id ?? 1; // Default peserta ID for testing

            // Get ujian_id yang benar dari peserta (ignore parameter URL)
            $peserta_ujian_id = $this->getPesertaUjianId($peserta_id);
            if (!$peserta_ujian_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Peserta belum di-assign ke ujian manapun',
                    'debug' => [
                        'peserta_id' => $peserta_id,
                        'url_ujian_id' => $ujian_id
                    ]
                ], 403);
            }

            // Debug info
            if ($request->has('debug')) {
                return response()->json([
                    'debug' => true,
                    'url_ujian_id' => $ujian_id,
                    'peserta_ujian_id' => $peserta_ujian_id,
                    'peserta_id' => $peserta_id,
                    'request_data' => $request->all()
                ]);
            }

            $jawaban = Jawaban::where('peserta_id', $peserta_id)
                            ->where('ujian_id', $peserta_ujian_id)
                            ->with('soal:id,nomor_soal,pertanyaan')
                            ->get();

            // Transform data untuk response yang lebih informatif
            $jawaban_formatted = $jawaban->map(function ($item) {
                return [
                    'id' => $item->id,
                    'soal_id' => $item->soal_id,
                    'nomor_soal' => $item->soal->nomor_soal ?? null,
                    'pertanyaan' => substr($item->soal->pertanyaan ?? '', 0, 100) . '...',
                    'jawaban_peserta' => $item->jawaban_peserta,
                    'benar' => $item->benar ? true : false,
                    'created_at' => $item->created_at->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s T')
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Jawaban peserta berhasil diambil',
                'data' => [
                    'peserta_id' => $peserta_id,
                    'ujian_id' => $ujian_id,
                    'total_jawaban' => $jawaban->count(),
                    'jawaban' => $jawaban_formatted
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    /**
     * Selesai ujian - submit dan update status
     */
    public function selesaiUjian(Request $request)
    {
        try {
            // Debug logging
            \Log::info('selesaiUjian request:', $request->all());
            
            // Check if ujian exists first
            $ujian_id = $request->ujian_id;
            $ujian_exists = \App\Models\Ujian::where('id', $ujian_id)->exists();
            
            \Log::info('Ujian validation:', [
                'ujian_id' => $ujian_id,
                'ujian_exists' => $ujian_exists,
                'all_ujian_ids' => \App\Models\Ujian::pluck('id')->toArray()
            ]);
            
            $request->validate([
                'ujian_id' => 'required|integer|exists:ujian,id',
                'peserta_id' => 'sometimes|integer|exists:peserta,id' // Optional for testing
            ]);

            // For testing without middleware, use peserta_id from request or default to 1
            $peserta_id = $request->peserta_id ?? 1; // Default peserta ID for testing
            
            // Get ujian_id yang benar dari peserta (ignore request ujian_id)
            $peserta_ujian_id = $this->getPesertaUjianId($peserta_id);
            if (!$peserta_ujian_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Peserta belum di-assign ke ujian manapun',
                    'debug' => [
                        'peserta_id' => $peserta_id,
                        'request_ujian_id' => $request->ujian_id
                    ]
                ], 403);
            }

            // Debug info
            if ($request->has('debug')) {
                return response()->json([
                    'debug' => true,
                    'request_ujian_id' => $request->ujian_id,
                    'peserta_ujian_id' => $peserta_ujian_id,
                    'peserta_id' => $peserta_id,
                    'request_data' => $request->all()
                ]);
            }

            // Cek apakah sudah pernah submit (prevent double submit) - gunakan ujian_id yang benar
            $aktivitas = AktivitasPeserta::where('peserta_id', $peserta_id)
                                       ->where('ujian_id', $peserta_ujian_id)
                                       ->first();

            if ($aktivitas && $aktivitas->status === 'sudah_submit') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ujian sudah pernah di-submit sebelumnya',
                    'data' => [
                        'status' => 'sudah_submit',
                        'waktu_submit_sebelumnya' => $aktivitas->waktu_submit->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s T')
                    ]
                ], 400);
            }

            DB::beginTransaction();

            // Get or create aktivitas peserta jika belum ada - gunakan ujian_id yang benar
            if (!$aktivitas) {
                $aktivitas = AktivitasPeserta::firstOrCreate(
                    [
                        'peserta_id' => $peserta_id,
                        'ujian_id' => $peserta_ujian_id
                    ],
                    [
                        'status' => 'belum_login',
                        'waktu_login' => null,
                        'waktu_submit' => null
                    ]
                );
            }

            // Update status dan waktu submit - hanya jika belum submit
            if ($aktivitas->status !== 'sudah_submit') {
                $waktu_submit = Carbon::now('Asia/Jakarta');
                $aktivitas->status = 'sudah_submit';
                $aktivitas->waktu_submit = $waktu_submit;
                $aktivitas->save();

                // Hitung nilai total - gunakan ujian_id yang benar
                $total_benar = Jawaban::where('peserta_id', $peserta_id)
                                    ->where('ujian_id', $peserta_ujian_id)
                                    ->where('benar', 1)
                                    ->count();

                $total_soal = Soal::where('ujian_id', $peserta_ujian_id)->count();
                $total_jawaban = Jawaban::where('peserta_id', $peserta_id)
                                      ->where('ujian_id', $peserta_ujian_id)
                                      ->count();

                $nilai = $total_soal > 0 ? round(($total_benar / $total_soal) * 100, 2) : 0;

                // Update nilai di tabel peserta
                $peserta = Peserta::find($peserta_id);
                $peserta->nilai_total = $nilai;
                $peserta->save();

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Ujian berhasil diselesaikan',
                    'data' => [
                        'peserta_id' => $peserta_id,
                        'ujian_id' => $peserta_ujian_id,
                        'waktu_submit' => $waktu_submit->format('Y-m-d H:i:s T'),
                        'total_soal' => $total_soal,
                        'total_jawaban' => $total_jawaban,
                        'total_benar' => $total_benar,
                        'total_salah' => $total_jawaban - $total_benar,
                        'nilai_total' => $nilai,
                        'status' => 'sudah_submit'
                    ]
                ]);
            } else {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Ujian sudah pernah di-submit sebelumnya',
                    'data' => [
                        'status' => 'sudah_submit',
                        'waktu_submit_sebelumnya' => $aktivitas->waktu_submit->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s T')
                    ]
                ], 400);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            \Log::error('Validation error in selesaiUjian:', [
                'errors' => $e->errors(),
                'request' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(', ', array_flatten($e->errors())),
                'errors' => $e->errors(),
                'debug' => [
                    'request_data' => $request->all(),
                    'validation_rules' => [
                        'ujian_id' => 'required|integer|exists:ujian,id',
                        'peserta_id' => 'sometimes|integer|exists:peserta,id'
                    ]
                ]
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }
}
