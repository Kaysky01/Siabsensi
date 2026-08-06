<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TimdisSeeder extends Seeder
{
    /**
     * Seed akun PJ Timdis (Penanggung Jawab Tim Kedisiplinan) PKKMB 2026.
     * Jalankan dengan: php artisan db:seed --class=TimdisSeeder
     */
    public function run(): void
    {
        $now = Carbon::now();
        $defaultPassword = Hash::make('PKKMB2026!');

        $timdisList = [
            [
                'kompi'     => 'KOMPI 1',
                'full_name' => 'Indriyani, S.E., M.Ak.',
                'username'  => 'indriyani',
            ],
            [
                'kompi'     => 'KOMPI 2',
                'full_name' => 'Dea Rizki Widiana, S.T.P., M.Sc.',
                'username'  => 'dearizkiwidiana',
            ],
            [
                'kompi'     => 'KOMPI 3',
                'full_name' => 'drh. Bagas Pria Prasetyo, M.Sc',
                'username'  => 'bagaspriaprasetyo',
            ],
            [
                'kompi'     => 'KOMPI 4',
                'full_name' => 'Mutia Rizkia Shaffira, S.Tr.Pt., M.Tr.P',
                'username'  => 'mutiarizkiashaffira',
            ],
            [
                'kompi'     => 'KOMPI 5',
                'full_name' => 'Muhammad Mujahid, S.I.K.,M.Si',
                'username'  => 'muhammadmujahid',
            ],
            [
                'kompi'     => 'KOMPI 6',
                'full_name' => 'Fathurrahman Kurniawan Ikhsan, S.Kom., M.T.I',
                'username'  => 'fathurrahmankurniawanikhsan',
            ],
            [
                'kompi'     => 'KOMPI 7',
                'full_name' => 'Ahmad Rofi\'i, S.Kom., M.T.I.',
                'username'  => 'ahmadrofii',
            ],
            [
                'kompi'     => 'KOMPI 8',
                'full_name' => 'Joni Frengki Samosir, S.TP., M.T.P',
                'username'  => 'jonifrengkisamosir',
            ],
            [
                'kompi'     => 'KOMPI 9',
                'full_name' => 'Ir. Adam Wisnu Murti, S.T., M.T.',
                'username'  => 'adamwisnumurti',
            ],
            [
                'kompi'     => 'KOMPI 10',
                'full_name' => 'Soleh Ade Kusuma, S.Pt',
                'username'  => 'solehadekusuma',
            ],
            [
                'kompi'     => 'KOMPI 11',
                'full_name' => 'Wahyu, S.Pi., M.Si',
                'username'  => 'wahyu',
            ],
            [
                'kompi'     => 'KOMPI 12',
                'full_name' => 'Ir. Hendri Gustian, S.TP., M.T',
                'username'  => 'hendrigustian',
            ],
            [
                'kompi'     => 'KOMPI 13',
                'full_name' => 'Prisma Yunia Putri, S.S., M.A.',
                'username'  => 'prismayuniaputri',
            ],
            [
                'kompi'     => 'KOMPI 14',
                'full_name' => 'Syaifuddin Muhammad Mirza, S.I.Kom., M.A.',
                'username'  => 'syaifuddinmuhammadmirza',
            ],
        ];

        $inserted = 0;
        $updated  = 0;

        foreach ($timdisList as $timdis) {
            $user = DB::table('users')->where('username', $timdis['username'])->first();

            if ($user) {
                DB::table('users')->where('username', $timdis['username'])->update([
                    'full_name'      => $timdis['full_name'],
                    'role'           => 'timdis',
                    'assigned_kompi' => $timdis['kompi'],
                    'is_active'      => 1,
                    'updated_at'     => $now,
                ]);
                $updated++;
                $this->command->warn("  ↻  Diperbarui (sudah ada): {$timdis['username']} -> {$timdis['full_name']} ({$timdis['kompi']})");
            } else {
                DB::table('users')->insert([
                    'username'       => $timdis['username'],
                    'password'       => $defaultPassword,
                    'full_name'      => $timdis['full_name'],
                    'email'          => null,
                    'role'           => 'timdis',
                    'assigned_kompi' => $timdis['kompi'],
                    'mahasiswa_id'   => null,
                    'is_active'      => 1,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
                $inserted++;
                $this->command->info("  ✓  {$timdis['full_name']} ({$timdis['kompi']}) [Username: {$timdis['username']}]");
            }
        }

        $this->command->newLine();
        $this->command->info("===========================================");
        $this->command->info("  Seeder TimdisSeeder selesai:");
        $this->command->info("  ✓ Berhasil diinsert  : {$inserted} akun");
        if ($updated > 0) {
            $this->command->warn("  ↻ Berhasil diperbarui: {$updated} akun");
        }
        $this->command->info("  Default Password    : PKKMB2026!");
        $this->command->info("===========================================");
    }
}
