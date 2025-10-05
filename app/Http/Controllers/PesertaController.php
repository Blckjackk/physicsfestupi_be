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

            // Get aktivitas peserta untuk ujian_id = 1 (default)
            // Cek status apakah peserta sudah submit atau belum
            $aktivitas = AktivitasPeserta::where('peserta_id', $peserta->id)
                                       ->where('ujian_id', 1)
                                       ->first();

            $aktivitas_data = [
                'ujian_id' => 1,
                'status' => $aktivitas ? $aktivitas->status : 'belum_login',
                'waktu_login' => $aktivitas ? $aktivitas->waktu_login : null,
                'waktu_submit' => $aktivitas ? $aktivitas->waktu_submit : null
            ];

            return response()->json([
                'success' => true,
                'message' => 'Login berhasil',
                'data' => [
                    'peserta' => $peserta,
                    'token' => $token,
                    'aktivitas_ujian' => $aktivitas_data
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
            $ujian = Ujian::find($id);

            if (!$ujian) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ujian tidak ditemukan'
                ], 404);
            }

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
            $ujian_id = $request->ujian_id;

            // Cek apakah ujian masih berjalan
            $ujian = Ujian::find($ujian_id);
            $now = Carbon::now()->setTimezone('Asia/Jakarta');
            $waktu_mulai = Carbon::parse($ujian->waktu_mulai_pengerjaan)->setTimezone('Asia/Jakarta');
            $waktu_akhir = Carbon::parse($ujian->waktu_akhir_pengerjaan)->setTimezone('Asia/Jakarta');

            // Cek atau buat aktivitas peserta
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
            
            $aktivitas = AktivitasPeserta::where('peserta_id', $peserta_id)
                                       ->where('ujian_id', $id)
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
            
            // Debug info
            if ($request->has('debug')) {
                return response()->json([
                    'debug' => true,
                    'ujian_id' => $ujian_id,
                    'peserta_id' => $peserta_id,
                    'request_data' => $request->all()
                ]);
            }

            // Cek apakah ujian ada
            $ujian = Ujian::find($ujian_id);
            if (!$ujian) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ujian tidak ditemukan'
                ], 404);
            }

            // Ambil soal-soal ujian
            $soal = Soal::where('ujian_id', $ujian_id)
                       ->orderBy('nomor_soal')
                       ->get();

            if ($soal->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada soal untuk ujian ini'
                ], 404);
            }

            // Ambil jawaban peserta yang sudah tersimpan
            $jawaban_peserta = Jawaban::where('peserta_id', $peserta_id)
                                    ->where('ujian_id', $ujian_id)
                                    ->get()
                                    ->keyBy('soal_id');

            // Gabungkan soal dengan jawaban yang sudah ada
            $soal_dengan_jawaban = $soal->map(function ($item) use ($jawaban_peserta) {
                $jawaban = $jawaban_peserta->get($item->id);
                $item->jawaban_peserta = $jawaban ? $jawaban->jawaban_peserta : null;
                $item->sudah_dijawab = $jawaban ? true : false;
                return $item;
            });

            return response()->json([
                'success' => true,
                'message' => 'Soal ujian berhasil diambil',
                'data' => [
                    'ujian' => [
                        'id' => $ujian->id,
                        'nama_ujian' => $ujian->nama_ujian,
                        'deskripsi' => $ujian->deskripsi
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
            
            // Debug info
            if ($request->has('debug')) {
                return response()->json([
                    'debug' => true,
                    'request_data' => $request->all(),
                    'peserta_id' => $peserta_id
                ]);
            }

            // Ambil jawaban benar dari soal
            $soal = Soal::find($request->soal_id);
            if (!$soal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Soal tidak ditemukan'
                ], 404);
            }

            $benar = ($request->jawaban === $soal->jawaban_benar) ? 1 : 0;

            // Simpan atau update jawaban
            $jawaban = Jawaban::updateOrCreate(
                [
                    'peserta_id' => $peserta_id,
                    'ujian_id' => $request->ujian_id,
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
                    'ujian_id' => $request->ujian_id,
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

            // Debug info
            if ($request->has('debug')) {
                return response()->json([
                    'debug' => true,
                    'ujian_id' => $ujian_id,
                    'peserta_id' => $peserta_id,
                    'request_data' => $request->all()
                ]);
            }

            $jawaban = Jawaban::where('peserta_id', $peserta_id)
                            ->where('ujian_id', $ujian_id)
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
            $request->validate([
                'ujian_id' => 'required|integer|exists:ujian,id',
                'peserta_id' => 'sometimes|integer|exists:peserta,id' // Optional for testing
            ]);

            // For testing without middleware, use peserta_id from request or default to 1
            $peserta_id = $request->peserta_id ?? 1; // Default peserta ID for testing
            $ujian_id = $request->ujian_id;

            // Debug info
            if ($request->has('debug')) {
                return response()->json([
                    'debug' => true,
                    'ujian_id' => $ujian_id,
                    'peserta_id' => $peserta_id,
                    'request_data' => $request->all()
                ]);
            }

            DB::beginTransaction();

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

            if ($aktivitas->status === 'sudah_submit') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Ujian sudah pernah di-submit sebelumnya',
                    'data' => [
                        'waktu_submit_sebelumnya' => $aktivitas->waktu_submit->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s T')
                    ]
                ], 400);
            }

            // Update status dan waktu submit
            $waktu_submit = Carbon::now('Asia/Jakarta');
            $aktivitas->status = 'sudah_submit';
            $aktivitas->waktu_submit = $waktu_submit;
            $aktivitas->save();

            // Hitung nilai total
            $total_benar = Jawaban::where('peserta_id', $peserta_id)
                                ->where('ujian_id', $ujian_id)
                                ->where('benar', 1)
                                ->count();

            $total_soal = Soal::where('ujian_id', $ujian_id)->count();
            $total_jawaban = Jawaban::where('peserta_id', $peserta_id)
                                  ->where('ujian_id', $ujian_id)
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
                    'ujian_id' => $ujian_id,
                    'waktu_submit' => $waktu_submit->format('Y-m-d H:i:s T'),
                    'total_soal' => $total_soal,
                    'total_jawaban' => $total_jawaban,
                    'total_benar' => $total_benar,
                    'total_salah' => $total_jawaban - $total_benar,
                    'nilai_total' => $nilai,
                    'status' => 'sudah_submit'
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
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
