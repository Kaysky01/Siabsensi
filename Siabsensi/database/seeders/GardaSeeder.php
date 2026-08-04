<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class GardaSeeder extends Seeder
{
    /**
     * Seed akun Garda (Penanggung Jawab Kompi) PKKMB 2026.
     * Jalankan dengan: php artisan db:seed --class=GardaSeeder
     */
    public function run(): void
    {
        $now = Carbon::now();
        $password = Hash::make('PKKMB2026!');

        $gardaList = [
            // Kompi 1
            ['username' => 'jesikasimbolon',         'full_name' => 'Jesika Simbolon',          'kompi' => 'KOMPI 1'],
            ['username' => 'ayukeisairmawati',        'full_name' => 'Ayu Keisa Irmawati',       'kompi' => 'KOMPI 1'],
            ['username' => 'destriamanda',            'full_name' => 'Destri Amanda',            'kompi' => 'KOMPI 1'],
            // Kompi 2
            ['username' => 'ivanlubis',               'full_name' => 'Ivan Lubis',               'kompi' => 'KOMPI 2'],
            ['username' => 'sonyaulfa',               'full_name' => 'Sonya Ulfa',               'kompi' => 'KOMPI 2'],
            // Kompi 3
            ['username' => 'fitriyanti',              'full_name' => 'Fitriyanti',               'kompi' => 'KOMPI 3'],
            ['username' => 'nursintia',               'full_name' => 'Nur Sintia',               'kompi' => 'KOMPI 3'],
            // Kompi 4
            ['username' => 'virnarokaren',            'full_name' => 'Virnarokaren',             'kompi' => 'KOMPI 4'],
            ['username' => 'elsyadyacantika',         'full_name' => 'Elsya Dya Cantika',        'kompi' => 'KOMPI 4'],
            ['username' => 'witriana',                'full_name' => 'Witriana',                 'kompi' => 'KOMPI 4'],
            // Kompi 5
            ['username' => 'meilinapratiwi',          'full_name' => 'Meilina Pratiwi',          'kompi' => 'KOMPI 5'],
            ['username' => 'ridhoakmalsembiring',     'full_name' => 'Ridho Akmal Sembiring',    'kompi' => 'KOMPI 5'],
            // Kompi 6
            ['username' => 'rindiafifani',            'full_name' => 'Rindi Afifani',            'kompi' => 'KOMPI 6'],
            ['username' => 'aitamapodasidabutar',     'full_name' => 'Aitama Poda Sidabutar',    'kompi' => 'KOMPI 6'],
            // Kompi 7
            ['username' => 'renandaputri',            'full_name' => 'Renanda Putri',            'kompi' => 'KOMPI 7'],
            ['username' => 'fanesasiskapertiwi',      'full_name' => 'Fanesa Siska Pertiwi',     'kompi' => 'KOMPI 7'],
            ['username' => 'rimakusumawatiputri',     'full_name' => 'Rima Kusumawati Putri',    'kompi' => 'KOMPI 7'],
            // Kompi 8
            ['username' => 'shevaghaniaramadhani',    'full_name' => 'Sheva Ghania Ramadhani',   'kompi' => 'KOMPI 8'],
            ['username' => 'ayulestari',              'full_name' => 'Ayu Lestari',              'kompi' => 'KOMPI 8'],
            ['username' => 'dwiyantoanugerah',        'full_name' => 'Dwiyanto Anugerah',        'kompi' => 'KOMPI 8'],
            // Kompi 9
            ['username' => 'reginasiburian',          'full_name' => 'Regina Siburian',          'kompi' => 'KOMPI 9'],
            ['username' => 'ramayanipratiwi',         'full_name' => 'Ramayani Pratiwi',         'kompi' => 'KOMPI 9'],
            ['username' => 'medisardiansyah',         'full_name' => 'Medi Sardiansyah',         'kompi' => 'KOMPI 9'],
            // Kompi 10
            ['username' => 'cristinangelinasamosir',  'full_name' => 'Cristin Angelina Samosir', 'kompi' => 'KOMPI 10'],
            ['username' => 'ighfiirlikhofiifah',      'full_name' => 'Ighfiirli Khofiifah',      'kompi' => 'KOMPI 10'],
            ['username' => 'pingkanagustina',         'full_name' => 'Pingkan Agustina',         'kompi' => 'KOMPI 10'],
            // Kompi 11
            ['username' => 'immanuelsihotang',        'full_name' => 'Immanuel Sihotang',        'kompi' => 'KOMPI 11'],
            ['username' => 'bungadahlyia',            'full_name' => 'Bunga Dahlyia',            'kompi' => 'KOMPI 11'],
            ['username' => 'lukvidadeviana',          'full_name' => 'Lukvida Deviana',          'kompi' => 'KOMPI 11'],
            // Kompi 12
            ['username' => 'atikadwirianaputri',      'full_name' => 'Atika Dwi Riana Putri',    'kompi' => 'KOMPI 12'],
            ['username' => 'tsaniaturrohmahnabilla',  'full_name' => 'Tsania Turrohmahnabilla',  'kompi' => 'KOMPI 12'],
            // Kompi 13
            ['username' => 'windymustifatumangger',   'full_name' => 'Windy Mustifa Tumangger',  'kompi' => 'KOMPI 13'],
            ['username' => 'monikasiagian',           'full_name' => 'Monika Siagian',           'kompi' => 'KOMPI 13'],
            ['username' => 'fitrasatria',             'full_name' => 'Fitra Satria',             'kompi' => 'KOMPI 13'],
            // Kompi 14
            ['username' => 'pestaulisianipar',        'full_name' => 'Pestauli Sianipar',        'kompi' => 'KOMPI 14'],
            ['username' => 'igede',                   'full_name' => 'I Gede',                   'kompi' => 'KOMPI 14'],
        ];

        $inserted = 0;
        $skipped  = 0;

        foreach ($gardaList as $garda) {
            $exists = DB::table('users')->where('username', $garda['username'])->exists();

            if ($exists) {
                $this->command->warn("  ⚠  Dilewati (sudah ada): {$garda['username']}");
                $skipped++;
                continue;
            }

            DB::table('users')->insert([
                'username'       => $garda['username'],
                'password'       => $password,
                'full_name'      => $garda['full_name'],
                'email'          => null,
                'role'           => 'garda',
                'assigned_kompi' => $garda['kompi'],
                'mahasiswa_id'   => null,
                'is_active'      => 1,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);

            $inserted++;
            $this->command->info("  ✓  {$garda['full_name']} ({$garda['kompi']})");
        }

        $this->command->newLine();
        $this->command->info("===========================================");
        $this->command->info("  Seeder GardaSeeder selesai:");
        $this->command->info("  ✓ Berhasil diinsert : {$inserted} akun");
        if ($skipped > 0) {
            $this->command->warn("  ⚠ Dilewati          : {$skipped} akun (sudah ada)");
        }
        $this->command->info("===========================================");
    }
}
