<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

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
        $csvFile = __DIR__ . '/data/mahasiswa.csv';

        if (!file_exists($csvFile)) {
            $this->command->error("CSV file not found: {$csvFile}");
            return;
        }

        $this->command->info('Membaca file data mahasiswa...');
        
        $handle = fopen($csvFile, 'r');
        $header = fgetcsv($handle); // skip header
        
        $chunkSize   = 500;
        $mahasiswaChunk = [];
        $usersToInsert  = [];
        $count = 0;
        
        $now = Carbon::now();

        while (($row = fgetcsv($handle)) !== false) {
            // Index map:
            // 0: No, 1: Nama, 2: Email, 3: Tanggal Lahir (d/m/Y),
            // 4: Status Registrasi, 5: No. Pendaftaran (ID), 6: Prodi Diterima,
            // 7: Jurusan Asal, 8: No. HP Ayah, 9: No. HP Ibu

            $id = trim($row[5]);
            if (empty($id) || $id === '-') {
                continue; // Skip invalid ID
            }

            $nama  = trim($row[1]);
            $email = trim($row[2]);
            $email = ($email !== '-' && !empty($email)) ? $email : null;

            $tglLahirStr = trim($row[3]);
            $tanggalLahir = null;
            if (!empty($tglLahirStr) && $tglLahirStr !== '-') {
                try {
                    $tanggalLahir = Carbon::createFromFormat('d/m/Y', $tglLahirStr)->format('Y-m-d');
                } catch (\Exception $e) {
                    $tanggalLahir = null;
                }
            }

            $prodi   = trim($row[6]);
            $jurusan = $prodi; // User requested: sesuaikan aja prodinya ambil

            $hpAyah = trim($row[8]);
            $hpIbu  = trim($row[9]);

            $noTelpOrtu = null;
            if ($hpAyah !== '-' && !empty($hpAyah)) {
                $noTelpOrtu = $hpAyah;
            } elseif ($hpIbu !== '-' && !empty($hpIbu)) {
                $noTelpOrtu = $hpIbu;
            }

            $mahasiswaChunk[] = [
                'id'               => $id,
                'name'             => $nama,
                'kompi'            => '-',
                'jurusan'          => $jurusan,
                'prodi'            => $prodi,
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

        fclose($handle);
        $this->command->info("Seeder selesai. Total mahasiswa + user dibuat: {$count}");
        $this->command->info("Password default: tanggal+bulan+tahun lahir (contoh lahir 08/06/2008 → password: 08062008)");
    }
}
