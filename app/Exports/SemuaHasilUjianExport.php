<?php

namespace App\Exports;

use App\Models\Ujian;
use App\Models\AktivitasPeserta;
use App\Models\Soal;
use App\Models\Jawaban;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
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

class SemuaHasilUjianExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        $sheets = [];
        
        // Ambil semua ujian yang memiliki peserta yang sudah mengambil ujian
        $ujianList = Ujian::whereHas('aktivitasPeserta', function($query) {
            $query->whereIn('status', ['sedang_mengerjakan', 'sudah_submit']);
        })->orderBy('created_at', 'desc')->get();

        foreach ($ujianList as $ujian) {
            $sheets[] = new HasilUjianPerSheetExport($ujian->id, $ujian->nama_ujian);
        }

        // Jika tidak ada ujian, buat sheet kosong
        if (empty($sheets)) {
            $sheets[] = new EmptySheetExport();
        }

        return $sheets;
    }
}

class HasilUjianPerSheetExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle
{
    protected $ujianId;
    protected $ujianNama;
    protected $ujian;
    protected $soalList;
    protected $rowNumber = 0; // Counter untuk nomor urut

    public function __construct($ujianId, $ujianNama)
    {
        $this->ujianId = $ujianId;
        $this->ujianNama = $ujianNama;
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
        return AktivitasPeserta::with(['peserta', 'ujian'])
            ->where('ujian_id', $this->ujianId)
            ->whereIn('status', ['sedang_mengerjakan', 'sudah_submit'])
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
        // Increment counter untuk nomor urut
        $this->rowNumber++;
        
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
            $this->rowNumber, // Menggunakan counter mulai dari 1
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
            $jawaban = Jawaban::where('peserta_id', $aktivitas->peserta_id)
                             ->where('ujian_id', $this->ujianId)
                             ->where('soal_id', $soal->id)
                             ->first();

            // Potong pertanyaan jika terlalu panjang
            $pertanyaan = strlen($soal->pertanyaan) > 50 
                ? substr($soal->pertanyaan, 0, 50) . '...' 
                : $soal->pertanyaan;

            $jawabanData[] = $pertanyaan;
            $jawabanData[] = strtoupper($soal->jawaban_benar);
            $jawabanData[] = $jawaban ? strtoupper($jawaban->jawaban_peserta ?? '-') : '-';
            
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
        $totalRows = $this->collection()->count() + 1;
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

            $sheet->getDefaultRowDimension()->setRowHeight(-1);
        }

        // Freeze panes
        $sheet->freezePane('N2');

        return [];
    }

    /**
     * @return array
     */
    public function columnWidths(): array
    {
        $widths = [
            'A' => 5,   'B' => 15,  'C' => 20,  'D' => 12,
            'E' => 18,  'F' => 18,  'G' => 12,  'H' => 10,
            'I' => 10,  'J' => 8,   'K' => 8,   'L' => 8,   'M' => 8,
        ];

        // Tambahkan lebar kolom untuk setiap soal
        $currentColumn = 'N';
        for ($i = 0; $i < $this->soalList->count(); $i++) {
            $widths[$currentColumn] = 30;
            $currentColumn++;
            $widths[$currentColumn] = 12;
            $currentColumn++;
            $widths[$currentColumn] = 15;
            $currentColumn++;
            $widths[$currentColumn] = 12;
            $currentColumn++;
        }

        return $widths;
    }

    /**
     * @return string
     */
    public function title(): string
    {
        $cleanTitle = preg_replace('/[^A-Za-z0-9_\- ]/', '', $this->ujianNama);
        return substr($cleanTitle, 0, 31); // Excel sheet name limit is 31 characters
    }

    /**
     * Get status text in Indonesian
     */
    private function getStatusText($status): string
    {
        switch ($status) {
            case 'belum_login':
                return 'Belum Login';
            case 'belum_mulai':
                return 'Belum Mulai';
            case 'sedang_mengerjakan':
                return 'Sedang Ujian';
            case 'sudah_submit':
                return 'Sudah Submit';
            default:
                return 'Unknown';
        }
    }
}

class EmptySheetExport implements FromCollection, WithHeadings, WithTitle
{
    public function collection()
    {
        return collect([]);
    }

    public function headings(): array
    {
        return ['Tidak ada data hasil ujian'];
    }

    public function title(): string
    {
        return 'Tidak Ada Data';
    }
}