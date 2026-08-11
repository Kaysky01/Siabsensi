<?php

namespace App\Exports;

use App\Models\Attendance;
use App\Models\Mahasiswa;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceExport implements FromQuery, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $startDate;
    protected $endDate;
    protected $status;
    protected $kompi;
    protected $jurusan;
    protected $prodi;
    protected $search;
    protected $exportFields;

    private $rowNumber = 0;

    private $fieldLabels = [
        'id' => 'ID / No Pendaftaran',
        'name' => 'Nama Mahasiswa',
        'email' => 'Email',
        'kompi' => 'Kompi',
        'jurusan' => 'Jurusan',
        'prodi' => 'Prodi',
        'date' => 'Tanggal',
        'check_in' => 'Jam Masuk',
        'check_out' => 'Jam Keluar',
        'status' => 'Status Absensi',
        'camera_id' => 'Kamera / Device',
    ];

    public function __construct(
        $startDate = null,
        $endDate = null,
        $status = 'all',
        $kompi = null,
        $jurusan = null,
        $prodi = null,
        $search = null,
        $exportFields = []
    ) {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->status = $status;
        $this->kompi = $kompi;
        $this->jurusan = $jurusan;
        $this->prodi = $prodi;
        $this->search = $search;

        if (empty($exportFields)) {
            $this->exportFields = ['id', 'name', 'kompi', 'jurusan', 'prodi', 'date', 'check_in', 'check_out', 'status', 'camera_id'];
        } else {
            $this->exportFields = array_intersect(array_keys($this->fieldLabels), (array) $exportFields);
        }
    }

    public function query()
    {
        $table = (new Attendance)->getTable();
        $mhsTable = (new Mahasiswa)->getTable();

        if ($this->status === 'alpha') {
            $query = Mahasiswa::select(
                "$mhsTable.id",
                "$mhsTable.id as mahasiswa_id",
                "$mhsTable.name",
                "$mhsTable.kompi",
                "$mhsTable.jurusan",
                "$mhsTable.prodi",
                "$mhsTable.email",
                DB::raw('null as check_in'),
                DB::raw('null as check_out'),
                DB::raw('null as date'),
                DB::raw("'alpha' as status"),
                DB::raw('null as camera_id')
            )->whereNotExists(function ($q) use ($table, $mhsTable) {
                $q->select(DB::raw(1))->from($table)
                    ->whereColumn("$table.mahasiswa_id", "$mhsTable.id");
                if ($this->startDate && $this->endDate) {
                    $q->whereBetween("$table.date", [$this->startDate, $this->endDate]);
                }
            });
        } else {
            $query = Attendance::join($mhsTable, "$table.mahasiswa_id", '=', "$mhsTable.id")
                ->select(
                    "$table.*",
                    "$mhsTable.id as mhs_id",
                    "$mhsTable.name",
                    "$mhsTable.kompi",
                    "$mhsTable.jurusan",
                    "$mhsTable.prodi",
                    "$mhsTable.email"
                );

            if ($this->startDate && $this->endDate) {
                $query->whereBetween("$table.date", [$this->startDate, $this->endDate]);
            } elseif ($this->startDate) {
                $query->whereDate("$table.date", '>=', $this->startDate);
            } elseif ($this->endDate) {
                $query->whereDate("$table.date", '<=', $this->endDate);
            }

            if ($this->status && $this->status !== 'all') {
                if (in_array($this->status, ['hadir', 'present'])) {
                    $query->whereIn("$table.status", ['hadir', 'present']);
                } else {
                    $query->where("$table.status", $this->status);
                }
            }
        }

        if ($this->kompi && $this->kompi !== 'all') {
            $query->where("$mhsTable.kompi", $this->kompi);
        }

        if ($this->jurusan) {
            $query->where("$mhsTable.jurusan", $this->jurusan);
        }

        if ($this->prodi) {
            $query->where("$mhsTable.prodi", $this->prodi);
        }

        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search, $mhsTable) {
                $q->where("$mhsTable.name", 'like', "%{$search}%")
                  ->orWhere("$mhsTable.id", 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function map($row): array
    {
        $data = [++$this->rowNumber];

        $statusBadge = $row->getStatusBadgeData();
        $statusText = $statusBadge['label'];
        if ($isManual) {
            $statusText .= ' (Manual)';
        }

        foreach ($this->exportFields as $field) {
            switch ($field) {
                case 'id':
                    $data[] = (string) ($row->mhs_id ?? $row->id ?? '-');
                    break;
                case 'name':
                    $data[] = $row->name ?? '-';
                    break;
                case 'email':
                    $data[] = $row->email ?? '-';
                    break;
                case 'kompi':
                    $data[] = $row->kompi ?? '-';
                    break;
                case 'jurusan':
                    $data[] = $row->jurusan ?? '-';
                    break;
                case 'prodi':
                    $data[] = $row->prodi ?? '-';
                    break;
                case 'date':
                    $data[] = $row->date ? date('d/m/Y', strtotime($row->date)) : '-';
                    break;
                case 'check_in':
                    $data[] = $row->check_in ? date('H:i', strtotime($row->check_in)) : '-';
                    break;
                case 'check_out':
                    $data[] = $row->check_out ? date('H:i', strtotime($row->check_out)) : '-';
                    break;
                case 'status':
                    $data[] = $statusText;
                    break;
                case 'camera_id':
                    $data[] = $row->camera_id ?? '-';
                    break;
            }
        }

        return $data;
    }

    public function headings(): array
    {
        $dateRange = '';
        if ($this->startDate && $this->endDate) {
            $dateRange = date('d/m/Y', strtotime($this->startDate)).' - '.date('d/m/Y', strtotime($this->endDate));
        } elseif ($this->startDate) {
            $dateRange = 'Mulai '.date('d/m/Y', strtotime($this->startDate));
        } elseif ($this->endDate) {
            $dateRange = 'Sampai '.date('d/m/Y', strtotime($this->endDate));
        }

        $headerRow = ['No'];
        foreach ($this->exportFields as $field) {
            $headerRow[] = $this->fieldLabels[$field] ?? ucfirst($field);
        }

        return [
            ['LAPORAN ABSENSI MAHASISWA'],
            $dateRange ? ['Periode: ' . $dateRange] : [''],
            [''],
            $headerRow,
        ];
    }

    public function columnWidths(): array
    {
        $widths = ['A' => 6];
        $colIndex = 1;

        $defaultWidths = [
            'id' => 18,
            'name' => 25,
            'email' => 25,
            'kompi' => 12,
            'jurusan' => 20,
            'prodi' => 20,
            'date' => 14,
            'check_in' => 12,
            'check_out' => 12,
            'status' => 15,
            'camera_id' => 15,
        ];

        foreach ($this->exportFields as $field) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $widths[$colLetter] = $defaultWidths[$field] ?? 15;
            $colIndex++;
        }

        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        $totalCols = count($this->exportFields) + 1;
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols);

        $sheet->mergeCells("A1:{$lastColLetter}1");
        $sheet->mergeCells("A2:{$lastColLetter}2");

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
            2 => [
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3B82F6']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
            4 => [
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            ],
            "A5:{$lastColLetter}1000" => [
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
        ];
    }

    public function title(): string
    {
        return 'Rekap Absensi';
    }
}
