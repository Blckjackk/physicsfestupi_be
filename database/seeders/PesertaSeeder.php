<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Peserta;
use Illuminate\Support\Facades\Hash;

class PesertaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Sample data peserta untuk testing
        $pesertaData = [
            [
                'username' => 'peserta001',
                'password_hash' => Hash::make('password123'),
                'role' => 'peserta',
                'nilai_total' => null,
            ],
            [
                'username' => 'peserta002',
                'password_hash' => Hash::make('password123'),
                'role' => 'peserta',
                'nilai_total' => 85,
            ],
            [
                'username' => 'peserta003',
                'password_hash' => Hash::make('password123'),
                'role' => 'peserta',
                'nilai_total' => 92,
            ],
            [
                'username' => 'peserta004',
                'password_hash' => Hash::make('password123'),
                'role' => 'peserta',
                'nilai_total' => 78,
            ],
            [
                'username' => 'peserta005',
                'password_hash' => Hash::make('password123'),
                'role' => 'peserta',
                'nilai_total' => null,
            ],
            [
                'username' => 'john_doe',
                'password_hash' => Hash::make('john123'),
                'role' => 'peserta',
                'nilai_total' => 88,
            ],
            [
                'username' => 'jane_smith',
                'password_hash' => Hash::make('jane123'),
                'role' => 'peserta',
                'nilai_total' => 95,
            ],
            [
                'username' => 'test_user',
                'password_hash' => Hash::make('test123'),
                'role' => 'peserta',
                'nilai_total' => null,
            ],
        ];

        // Insert data ke database
        foreach ($pesertaData as $data) {
            Peserta::create($data);
        }
    }
}
