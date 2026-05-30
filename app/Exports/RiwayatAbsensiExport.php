<?php

namespace App\Exports;

use App\Models\Attendance;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// 👇 2. Tambahkan implement WithMapping
class RiwayatAbsensiExport implements FromCollection, WithHeadings, WithStyles, WithMapping 
{
    protected $mahasiswaId;
    private $rowNumber = 0; // 👇 3. Buat variabel untuk menghitung baris

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
            
            // Format Tanggal: mengambil (Tahun-Bulan-Tanggal) tanpa jam
            $attendance->date ? date('Y-m-d', strtotime($attendance->date)) : '-',
            
            // Format Jam Masuk: mengambil (Jam:Menit) tanpa detik
            $attendance->check_in ? date('H:i', strtotime($attendance->check_in)) : '-',
            
            // Format Jam Keluar: mengambil (Jam:Menit) tanpa detik
            $attendance->check_out ? date('H:i', strtotime($attendance->check_out)) : '-',
            
            $attendance->status,
        ];
    }

    public function headings(): array
    {
        return ['No', 'Tanggal', 'Jam Masuk', 'Jam Keluar', 'Status'];
    }

    // Mengatur agar header tebal dan rapi
    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}