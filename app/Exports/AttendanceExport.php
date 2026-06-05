<?php

namespace App\Exports;

use App\Models\Attendance;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AttendanceExport implements FromCollection, WithHeadings, WithStyles, WithMapping, WithColumnWidths, WithTitle
{
    protected $startDate;
    protected $endDate;
    private $rowNumber = 0;

    public function __construct($startDate = null, $endDate = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        $query = Attendance::with(['mahasiswa']);
        
        if ($this->startDate && $this->endDate) {
            $query->whereBetween('date', [$this->startDate, $this->endDate]);
        } elseif ($this->startDate) {
            $query->whereDate('date', '>=', $this->startDate);
        } elseif ($this->endDate) {
            $query->whereDate('date', '<=', $this->endDate);
        }
        
        return $query->orderBy('date', 'desc')->get();
    }

    public function map($attendance): array
    {
        $mahasiswa = $attendance->mahasiswa;
        
        // Format status
        $statusText = ucfirst($attendance->status);
        if ($attendance->status === 'izin') {
            $statusText = 'Izin';
        } elseif ($attendance->status === 'sakit') {
            $statusText = 'Sakit';
        } elseif ($attendance->check_out) {
            $statusText = 'Lengkap';
        } elseif ($attendance->check_in) {
            $statusText = 'Hadir';
        } else {
            $statusText = 'Absen';
        }
        
        return [
            ++$this->rowNumber,
            $mahasiswa ? $mahasiswa->name : '-',
            $mahasiswa ? $mahasiswa->kelompok : '-',
            $mahasiswa ? $mahasiswa->jurusan : '-',
            $attendance->date ? date('d/m/Y', strtotime($attendance->date)) : '-',
            $attendance->check_in ? date('H:i', strtotime($attendance->check_in)) : '-',
            $attendance->check_out ? date('H:i', strtotime($attendance->check_out)) : '-',
            $statusText,
            $attendance->camera_id ?? '-',
        ];
    }

    public function headings(): array
    {
        $dateRange = '';
        if ($this->startDate && $this->endDate) {
            $dateRange = date('d/m/Y', strtotime($this->startDate)) . ' - ' . date('d/m/Y', strtotime($this->endDate));
        } elseif ($this->startDate) {
            $dateRange = 'Mulai ' . date('d/m/Y', strtotime($this->startDate));
        } elseif ($this->endDate) {
            $dateRange = 'Sampai ' . date('d/m/Y', strtotime($this->endDate));
        }
        
        return [
            ['LAPORAN ABSENSI MAHASISWA'],
            $dateRange ? [$dateRange] : [''],
            [''],
            ['No', 'Nama', 'Kelompok', 'Jurusan', 'Tanggal', 'Jam Masuk', 'Jam Keluar', 'Status', 'Kamera'],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 25,
            'C' => 12,
            'D' => 18,
            'E' => 12,
            'F' => 10,
            'G' => 10,
            'H' => 12,
            'I' => 12,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $titleRow = $this->startDate || $this->endDate ? 1 : 1;
        $headerRow = $this->startDate || $this->endDate ? 4 : 3;
        $dataStartRow = $this->startDate || $this->endDate ? 5 : 4;
        
        if ($this->startDate || $this->endDate) {
            $sheet->mergeCells('A1:I1');
            $sheet->mergeCells('A2:I2');
        } else {
            $sheet->mergeCells('A1:I1');
        }
        
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
            // Date range row styling (if exists)
            2 => [
                'font' => [
                    'bold' => true,
                    'size' => 11,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F7CFF'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            // Header row styling
            $headerRow => [
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
            "A{$dataStartRow}:I1000" => [
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
        return 'Laporan Absensi';
    }
}
