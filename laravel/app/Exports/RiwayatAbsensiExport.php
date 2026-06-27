<?php

namespace App\Exports;

use App\Models\Attendance;
use App\Models\Mahasiswa;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RiwayatAbsensiExport implements FromCollection, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $mahasiswaId;

    private $rowNumber = 0;

    public function __construct($mahasiswaId)
    {
        $this->mahasiswaId = $mahasiswaId;
    }

    public function collection()
    {
        return Attendance::where('mahasiswa_id', $this->mahasiswaId)
            ->orderBy('date', 'desc')
            ->get();
    }

    public function map($attendance): array
    {
        return [
            ++$this->rowNumber,
            $attendance->date ? date('d/m/Y', strtotime($attendance->date)) : '-',
            $attendance->check_in ? date('H:i', strtotime($attendance->check_in)) : '-',
            $attendance->check_out ? date('H:i', strtotime($attendance->check_out)) : '-',
            ucfirst($attendance->status),
        ];
    }

    public function headings(): array
    {
        return [
            ['LAPORAN RIWAYAT ABSENSI'],
            [''],
            ['No', 'Tanggal', 'Jam Masuk', 'Jam Keluar', 'Status'],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 15,
            'C' => 12,
            'D' => 12,
            'E' => 15,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->mergeCells('A1:E1');

        return [
            // Title row styling
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 14,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2D5BFF'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            // Header row styling
            3 => [
                'font' => [
                    'bold' => true,
                    'size' => 11,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '06D6A0'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ],
            // Data rows styling
            'A4:E1000' => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'E0E0E0'],
                    ],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function title(): string
    {
        $mahasiswa = Mahasiswa::find($this->mahasiswaId);

        return $mahasiswa ? 'Riwayat - '.$mahasiswa->name : 'Riwayat Absensi';
    }
}
