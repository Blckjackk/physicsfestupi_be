<?php

namespace App\Exports;

use App\Models\Ujian;
use App\Models\AktivitasPeserta;
use App\Models\Soal;
use App\Models\Jawaban;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Collection;

class HasilUjianExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle
{
    protected $ujianId;
    protected $ujian;
    protected $soalList;

    public function __construct($ujianId)
    {
        $this->ujianId = $ujianId;
        $this->ujian = Ujian::find($ujianId);
        $this->soalList = Soal::where('ujian_id', $ujianId)
                              ->orderBy('nomor_soal', 'asc')
                              ->get();
    }

    /**
     * @return Collection
     */
    public function collection()
    {
        // Ambil semua aktivitas peserta untuk ujian ini yang sudah login
        return AktivitasPeserta::with(['peserta', 'ujian'])
            ->where('ujian_id', $this->ujianId)
            ->whereIn('status', ['sedang_mengerjakan', 'selesai'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        $baseHeadings = [
            'No',
            'Username',
            'Nama Ujian',
            'Status',
            'Waktu Login',
            'Waktu Submit',
            'Durasi (Menit)',
            'Total Soal',
            'Dijawab',
            'Kosong',
            'Benar',
            'Salah',
            'Nilai'
        ];

        // Tambahkan heading untuk setiap soal
        $soalHeadings = [];
        foreach ($this->soalList as $soal) {
            $soalHeadings[] = "Soal {$soal->nomor_soal}";
            $soalHeadings[] = "Jawaban Benar {$soal->nomor_soal}";
            $soalHeadings[] = "Jawaban Peserta {$soal->nomor_soal}";
            $soalHeadings[] = "Status {$soal->nomor_soal}";
        }

        return array_merge($baseHeadings, $soalHeadings);
    }

    /**
     * @param mixed $aktivitas
     */
    public function map($aktivitas): array
    {
        // Hitung statistik
        $totalSoal = $this->soalList->count();
        $dijawab = Jawaban::where('peserta_id', $aktivitas->peserta_id)
                         ->where('ujian_id', $this->ujianId)
                         ->whereNotNull('jawaban_peserta')
                         ->count();
        
        $benar = Jawaban::where('peserta_id', $aktivitas->peserta_id)
                       ->where('ujian_id', $this->ujianId)
                       ->where('benar', true)
                       ->count();
        
        $salah = Jawaban::where('peserta_id', $aktivitas->peserta_id)
                       ->where('ujian_id', $this->ujianId)
                       ->where('benar', false)
                       ->count();

        $kosong = $totalSoal - $dijawab;
        $nilai = $totalSoal > 0 ? round(($benar / $totalSoal) * 100, 2) : 0;
        
        $durasi = null;
        if ($aktivitas->waktu_login && $aktivitas->waktu_submit) {
            $durasi = \Carbon\Carbon::parse($aktivitas->waktu_login)
                     ->diffInMinutes($aktivitas->waktu_submit);
        }

        $baseData = [
            $aktivitas->id, // No (akan diganti dengan counter di styles)
            $aktivitas->peserta->username,
            $aktivitas->ujian->nama_ujian,
            $this->getStatusText($aktivitas->status),
            $aktivitas->waktu_login ? $aktivitas->waktu_login->format('d/m/Y H:i:s') : '-',
            $aktivitas->waktu_submit ? $aktivitas->waktu_submit->format('d/m/Y H:i:s') : '-',
            $durasi ?? '-',
            $totalSoal,
            $dijawab,
            $kosong,
            $benar,
            $salah,
            $nilai
        ];

        // Tambahkan data jawaban untuk setiap soal
        $jawabanData = [];
        foreach ($this->soalList as $soal) {
            // Ambil jawaban peserta untuk soal ini
            $jawaban = Jawaban::where('peserta_id', $aktivitas->peserta_id)
                             ->where('ujian_id', $this->ujianId)
                             ->where('soal_id', $soal->id)
                             ->first();

            // Potong pertanyaan jika terlalu panjang (max 50 karakter)
            $pertanyaan = strlen($soal->pertanyaan) > 50 
                ? substr($soal->pertanyaan, 0, 50) . '...' 
                : $soal->pertanyaan;

            $jawabanData[] = $pertanyaan; // Soal
            $jawabanData[] = strtoupper($soal->jawaban_benar); // Jawaban Benar
            $jawabanData[] = $jawaban ? strtoupper($jawaban->jawaban_peserta ?? '-') : '-'; // Jawaban Peserta
            
            // Status jawaban
            if (!$jawaban || !$jawaban->jawaban_peserta) {
                $status = 'Kosong';
            } elseif ($jawaban->benar === true) {
                $status = 'Benar';
            } elseif ($jawaban->benar === false) {
                $status = 'Salah';
            } else {
                $status = 'Belum Dinilai';
            }
            $jawabanData[] = $status;
        }

        return array_merge($baseData, $jawabanData);
    }

    /**
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        $totalRows = $this->collection()->count() + 1; // +1 for header
        $totalCols = count($this->headings());
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols);

        // Style header
        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['argb' => 'FF4472C4']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN
                ]
            ]
        ]);

        // Style data rows
        if ($totalRows > 1) {
            $sheet->getStyle('A2:' . $lastColumn . $totalRows)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FFD9D9D9']
                    ]
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ]);

            // Style untuk kolom status jawaban (warna berdasarkan nilai)
            $statusColumns = [];
            $baseColumns = 13; // Jumlah kolom dasar
            for ($i = 0; $i < $this->soalList->count(); $i++) {
                $statusColumns[] = $baseColumns + ($i * 4) + 4; // Kolom status setiap soal
            }

            // Auto-fit row height
            $sheet->getDefaultRowDimension()->setRowHeight(-1);
        }

        // Freeze panes (freeze header dan beberapa kolom pertama)
        $sheet->freezePane('N2'); // Freeze sampai kolom M (13 kolom pertama)

        return [];
    }

    /**
     * @return array
     */
    public function columnWidths(): array
    {
        $widths = [
            'A' => 5,   // No
            'B' => 15,  // Username
            'C' => 20,  // Nama Ujian
            'D' => 12,  // Status
            'E' => 18,  // Waktu Login
            'F' => 18,  // Waktu Submit
            'G' => 12,  // Durasi
            'H' => 10,  // Total Soal
            'I' => 10,  // Dijawab
            'J' => 8,   // Kosong
            'K' => 8,   // Benar
            'L' => 8,   // Salah
            'M' => 8,   // Nilai
        ];

        // Tambahkan lebar kolom untuk setiap soal
        $currentColumn = 'N';
        for ($i = 0; $i < $this->soalList->count(); $i++) {
            $widths[$currentColumn] = 30; // Soal
            $currentColumn++;
            $widths[$currentColumn] = 12; // Jawaban Benar
            $currentColumn++;
            $widths[$currentColumn] = 15; // Jawaban Peserta
            $currentColumn++;
            $widths[$currentColumn] = 12; // Status
            $currentColumn++;
        }

        return $widths;
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Hasil Ujian - ' . ($this->ujian ? $this->ujian->nama_ujian : 'Unknown');
    }

    /**
     * Get status text in Indonesian
     */
    private function getStatusText($status): string
    {
        switch ($status) {
            case 'belum_login':
                return 'Belum Login';
            case 'sedang_mengerjakan':
                return 'Sedang Ujian';
            case 'selesai':
                return 'Selesai';
            default:
                return 'Unknown';
        }
    }
}