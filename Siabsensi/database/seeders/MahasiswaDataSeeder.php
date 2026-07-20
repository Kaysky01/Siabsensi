<?php

namespace Database\Seeders;

use App\Models\Prodi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class MahasiswaDataSeeder extends Seeder
{
    /**
     * Password default mahasiswa: tanggal + bulan + tahun lahir (format ddmmYYYY)
     * Contoh: lahir 08/06/2008 → password = "08062008"
     */
    private function defaultPassword(?string $tanggalLahir): string
    {
        if (!$tanggalLahir) return '00000000';
        try {
            return Carbon::parse($tanggalLahir)->format('dmY'); // dd + mm + yyyy
        } catch (\Exception $e) {
            return '00000000';
        }
    }

    public function run()
    {
        $excelFile = __DIR__ . '/data/data-mahasiswa-20260720-133554.xlsx';

        if (!file_exists($excelFile)) {
            $this->command->error("Excel file not found: {$excelFile}");
            return;
        }

        $this->command->info('Membaca file data mahasiswa dari Excel (proses ini mungkin butuh waktu beberapa menit)...');
        
        $spreadsheet = IOFactory::load($excelFile);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();
        
        $chunkSize   = 500;
        $mahasiswaChunk = [];
        $usersToInsert  = [];
        $count = 0;
        
        $now = Carbon::now();
        $prodiCache = [];

        // Skip baris pertama (index 0) karena header
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            // Mapping berdasarkan struktur file xlsx terbaru:
            // 0: No | 1: Nama | 2: Email | 3: Tanggal Lahir | 4: No. Pendaftaran | 5: Prodi Diterima | 6: No. HP Ayah
            
            $id = trim((string) ($row[4] ?? ''));
            if (empty($id) || $id === '-') {
                continue; // Skip invalid ID
            }

            $nama  = trim((string) ($row[1] ?? ''));
            $email = trim((string) ($row[2] ?? ''));
            $email = ($email !== '-' && !empty($email)) ? $email : null;

            $tglLahirStr = trim((string) ($row[3] ?? ''));
            $tanggalLahir = null;
            if (!empty($tglLahirStr) && $tglLahirStr !== '-') {
                try {
                    if (is_numeric($tglLahirStr)) {
                        $tanggalLahir = Carbon::instance(ExcelDate::excelToDateTimeObject((float) $tglLahirStr))->format('Y-m-d');
                    } elseif (strpos($tglLahirStr, '/') !== false) {
                        $tanggalLahir = Carbon::createFromFormat('d/m/Y', $tglLahirStr)->format('Y-m-d');
                    } else {
                        $tanggalLahir = Carbon::parse($tglLahirStr)->format('Y-m-d');
                    }
                } catch (\Exception $e) {
                    $tanggalLahir = null;
                }
            }

            $prodiName = trim((string) ($row[5] ?? ''));
            $jurusanName = $prodiName; // Fallback jika tidak ketemu relasinya
            
            // Resolve Jurusan otomatis dari nama Prodi (case-insensitive)
            if (!empty($prodiName)) {
                $prodiKey = strtolower($prodiName);
                if (isset($prodiCache[$prodiKey])) {
                    $jurusanName = $prodiCache[$prodiKey];
                } else {
                    $prodiModel = Prodi::with('jurusan')->whereRaw('LOWER(nama) = ?', [$prodiKey])->first();
                    if ($prodiModel && $prodiModel->jurusan) {
                        $jurusanName = $prodiModel->jurusan->nama;
                        $prodiName = $prodiModel->nama; // Normalize nama prodi
                        $prodiCache[$prodiKey] = $jurusanName;
                    }
                }
            }

            $noTelpOrtu = trim((string) ($row[6] ?? ''));
            $noTelpOrtu = ($noTelpOrtu !== '-' && !empty($noTelpOrtu)) ? $noTelpOrtu : null;

            $mahasiswaChunk[] = [
                'id'               => $id,
                'name'             => $nama,
                'kompi'            => '-',
                'jurusan'          => $jurusanName,
                'prodi'            => $prodiName,
                'tanggal_lahir'    => $tanggalLahir,
                'email'            => $email,
                'no_telp_mahasiswa'=> null,
                'no_telp_ortu'     => $noTelpOrtu,
                'qr_code_id'       => $id,
                'is_active'        => 1,
                'created_at'       => $now,
            ];

            // Siapkan user untuk mahasiswa ini
            $usersToInsert[] = [
                'username'     => $id,                                         // Nomor registrasi
                'password'     => Hash::make($this->defaultPassword($tanggalLahir)), // bulan+tahun
                'full_name'    => $nama,
                'email'        => $email,
                'role'         => 'mahasiswa',
                'mahasiswa_id' => $id,
                'is_active'    => 1,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];

            $count++;

            if (count($mahasiswaChunk) === $chunkSize) {
                DB::table('mahasiswa')->upsert($mahasiswaChunk, ['id'], [
                    'name', 'kompi', 'jurusan', 'prodi', 'tanggal_lahir',
                    'email', 'no_telp_ortu', 'qr_code_id', 'is_active'
                ]);

                // Upsert users — skip yang sudah ada berdasarkan mahasiswa_id
                DB::table('users')->upsert($usersToInsert, ['username'], [
                    'password', 'full_name', 'email', 'mahasiswa_id', 'is_active'
                ]);

                $mahasiswaChunk = [];
                $usersToInsert  = [];
                $this->command->info("Inserted {$count} records...");
            }
        }

        // Insert sisa data
        if (count($mahasiswaChunk) > 0) {
            DB::table('mahasiswa')->upsert($mahasiswaChunk, ['id'], [
                'name', 'kompi', 'jurusan', 'prodi', 'tanggal_lahir',
                'email', 'no_telp_ortu', 'qr_code_id', 'is_active'
            ]);

            DB::table('users')->upsert($usersToInsert, ['username'], [
                'password', 'full_name', 'email', 'mahasiswa_id', 'is_active'
            ]);

            $this->command->info("Inserted {$count} records...");
        }

        $this->command->info("Seeder selesai. Total mahasiswa + user dibuat: {$count}");
        $this->command->info("Password default: tanggal+bulan+tahun lahir (contoh lahir 08/06/2008 → password: 08062008)");
    }
}
