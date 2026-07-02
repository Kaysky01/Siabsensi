<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MahasiswaDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
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
        
        $chunkSize = 500;
        $dataChunk = [];
        $count = 0;
        
        while (($row = fgetcsv($handle)) !== false) {
            // Index map:
            // 0: No, 1: Nama, 2: Email, 3: Tanggal Lahir, 4: Status Registrasi
            // 5: No. Pendaftaran (ID), 6: Prodi Diterima, 7: Jurusan Asal
            // 8: No. HP Ayah, 9: No. HP Ibu
            
            $id = trim($row[5]);
            if (empty($id) || $id === '-') {
                continue; // Skip invalid ID
            }
            
            $nama = trim($row[1]);
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
            
            $prodi = trim($row[6]);
            // User requested: "untuk jurusan,sesuaikan aja gapapa prodinya ambil"
            $jurusan = $prodi;
            
            $hpAyah = trim($row[8]);
            $hpIbu = trim($row[9]);
            
            $noTelpOrtu = null;
            if ($hpAyah !== '-' && !empty($hpAyah)) {
                $noTelpOrtu = $hpAyah;
            } elseif ($hpIbu !== '-' && !empty($hpIbu)) {
                $noTelpOrtu = $hpIbu;
            }
            
            $dataChunk[] = [
                'id' => $id,
                'name' => $nama,
                'kompi' => '-', // Default
                'jurusan' => $jurusan,
                'prodi' => $prodi,
                'tanggal_lahir' => $tanggalLahir,
                'email' => $email,
                'no_telp_mahasiswa' => null,
                'no_telp_ortu' => $noTelpOrtu,
                'qr_code_id' => $id, // qr_code_id matches id
                'is_active' => 1,
                'created_at' => Carbon::now(),
            ];
            
            $count++;
            
            if (count($dataChunk) === $chunkSize) {
                DB::table('mahasiswa')->upsert($dataChunk, ['id'], [
                    'name', 'kompi', 'jurusan', 'prodi', 'tanggal_lahir', 
                    'email', 'no_telp_ortu', 'qr_code_id', 'is_active'
                ]);
                $dataChunk = [];
                $this->command->info("Inserted {$count} records...");
            }
        }
        
        // Insert remaining rows
        if (count($dataChunk) > 0) {
            DB::table('mahasiswa')->upsert($dataChunk, ['id'], [
                'name', 'kompi', 'jurusan', 'prodi', 'tanggal_lahir', 
                'email', 'no_telp_ortu', 'qr_code_id', 'is_active'
            ]);
            $this->command->info("Inserted {$count} records...");
        }
        
        fclose($handle);
        $this->command->info("Seeder selesai. Total mahasiswa ditambahkan: {$count}");
    }
}
