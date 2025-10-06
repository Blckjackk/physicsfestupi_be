<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Peserta;
use App\Models\Ujian;
use App\Models\Soal;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class PesertaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // buat admin dummy
        Admin ::create([
            'username' => 'admin',
            'password_hash' => Hash::make('admin123'),
            'role' => 'superadmin'
        ]);
        // Buat peserta dummy
        $peserta = [
            [
                'username' => 'peserta001',
                'password_hash' => Hash::make('password123'),
                'role' => 'peserta',
                'nilai_total' => null
            ],
            [
                'username' => 'peserta002', 
                'password_hash' => Hash::make('password123'),
                'role' => 'peserta',
                'nilai_total' => null
            ],
            [
                'username' => 'peserta003',
                'password_hash' => Hash::make('password123'),
                'role' => 'peserta',
                'nilai_total' => null
            ]
            
        ];

        foreach ($peserta as $p) {
            Peserta::create($p);
        }

        // Buat ujian dummy dengan waktu WIB
        $ujian = [
            [
                'nama_ujian' => 'Physics Test Chapter 1-5',
                'deskripsi' => 'Ujian Fisika materi Kinematika, Dinamika, dan Termodinamika',
                'waktu_mulai_pengerjaan' => Carbon::now('Asia/Jakarta')->addDay()->setHour(9)->setMinute(0)->setSecond(0),
                'waktu_akhir_pengerjaan' => Carbon::now('Asia/Jakarta')->addDay()->setHour(11)->setMinute(0)->setSecond(0)
            ],
            [
                'nama_ujian' => 'Physics Test Chapter 6-10', 
                'deskripsi' => 'Ujian Fisika materi Gelombang, Optik, dan Fisika Modern',
                'waktu_mulai_pengerjaan' => Carbon::now('Asia/Jakarta')->addDays(2)->setHour(14)->setMinute(0)->setSecond(0),
                'waktu_akhir_pengerjaan' => Carbon::now('Asia/Jakarta')->addDays(2)->setHour(16)->setMinute(0)->setSecond(0)
            ]
        ];

        foreach ($ujian as $u) {
            Ujian::create($u);
        }

        // Soal untuk ujian pertama (ID 1)
        $soalUjian1 = [
            [
                'ujian_id' => 1,
                'nomor_soal' => 1,
                'pertanyaan' => 'Sebuah benda bergerak dengan kecepatan konstan 20 m/s. Jarak yang ditempuh dalam waktu 5 detik adalah...',
                'opsi_a' => '80 m',
                'opsi_b' => '100 m',
                'opsi_c' => '120 m',
                'opsi_d' => '150 m',
                'jawaban_benar' => 'b'
            ],
            [
                'ujian_id' => 1,
                'nomor_soal' => 2,
                'pertanyaan' => 'Hukum Newton pertama menyatakan bahwa...',
                'opsi_a' => 'F = ma',
                'opsi_b' => 'Benda akan tetap diam atau bergerak lurus beraturan jika tidak ada gaya luar',
                'opsi_c' => 'Setiap aksi ada reaksi',
                'opsi_d' => 'Gravitasi berbanding terbalik dengan kuadrat jarak',
                'jawaban_benar' => 'b'
            ],
            [
                'ujian_id' => 1,
                'nomor_soal' => 3,
                'pertanyaan' => 'Rumus energi kinetik adalah...',
                'opsi_a' => 'mgh',
                'opsi_b' => '½mv²',
                'opsi_c' => 'mc²',
                'opsi_d' => 'Fd',
                'jawaban_benar' => 'b'
            ],
            [
                'ujian_id' => 1,
                'nomor_soal' => 4,
                'pertanyaan' => 'Satuan tekanan dalam SI adalah...',
                'opsi_a' => 'Newton',
                'opsi_b' => 'Joule',
                'opsi_c' => 'Pascal',
                'opsi_d' => 'Watt',
                'jawaban_benar' => 'c'
            ],
            [
                'ujian_id' => 1,
                'nomor_soal' => 5,
                'pertanyaan' => 'Proses perubahan wujud dari cair ke gas disebut...',
                'opsi_a' => 'Kondensasi',
                'opsi_b' => 'Sublimasi',
                'opsi_c' => 'Evaporasi',
                'opsi_d' => 'Kristalisasi',
                'jawaban_benar' => 'c'
            ]
        ];

        foreach ($soalUjian1 as $soal) {
            Soal::create($soal);
        }

        // Soal untuk ujian kedua (ID 2)
        $soalUjian2 = [
            [
                'ujian_id' => 2,
                'nomor_soal' => 1,
                'pertanyaan' => 'Frekuensi gelombang yang memiliki panjang gelombang 2 m dan kecepatan 10 m/s adalah...',
                'opsi_a' => '5 Hz',
                'opsi_b' => '10 Hz',
                'opsi_c' => '20 Hz',
                'opsi_d' => '0.2 Hz',
                'jawaban_benar' => 'a'
            ],
            [
                'ujian_id' => 2,
                'nomor_soal' => 2,
                'pertanyaan' => 'Indeks bias adalah perbandingan antara...',
                'opsi_a' => 'Kecepatan cahaya di udara dengan kecepatan cahaya di medium',
                'opsi_b' => 'Intensitas cahaya masuk dengan intensitas cahaya keluar',
                'opsi_c' => 'Panjang gelombang di udara dengan panjang gelombang di medium',
                'opsi_d' => 'Frekuensi cahaya di udara dengan frekuensi di medium',
                'jawaban_benar' => 'a'
            ],
            [
                'ujian_id' => 2,
                'nomor_soal' => 3,
                'pertanyaan' => 'Efek fotolistrik pertama kali dijelaskan oleh...',
                'opsi_a' => 'Newton',
                'opsi_b' => 'Einstein',
                'opsi_c' => 'Planck',
                'opsi_d' => 'Bohr',
                'jawaban_benar' => 'b'
            ],
            [
                'ujian_id' => 2,
                'nomor_soal' => 4,
                'pertanyaan' => 'Konstanta Planck memiliki satuan...',
                'opsi_a' => 'J.s',
                'opsi_b' => 'J/s',
                'opsi_c' => 'J.Hz',
                'opsi_d' => 'J.m',
                'jawaban_benar' => 'a'
            ],
            [
                'ujian_id' => 2,
                'nomor_soal' => 5,
                'pertanyaan' => 'Spektrum cahaya tampak memiliki panjang gelombang sekitar...',
                'opsi_a' => '380-780 nm',
                'opsi_b' => '380-780 μm',
                'opsi_c' => '380-780 mm',
                'opsi_d' => '380-780 m',
                'jawaban_benar' => 'a'
            ]
        ];

        foreach ($soalUjian2 as $soal) {
            Soal::create($soal);
        }

        echo "✅ PesertaSeeder berhasil dijalankan!\n";
        echo "📊 Data yang dibuat:\n";
        echo "   - 3 Peserta (peserta001, peserta002, peserta003)\n";
        echo "   - 2 Ujian dengan waktu WIB\n";
        echo "   - 10 Soal Fisika (5 per ujian)\n";
        echo "🕐 Jadwal Ujian:\n";
        echo "   - Ujian 1: Besok jam 09:00-11:00 WIB\n";
        echo "   - Ujian 2: Lusa jam 14:00-16:00 WIB\n";
    }
}
