<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Mahasiswa;
use App\Models\Attendance;
use Carbon\Carbon;

class CheckDailyAttendance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-daily-attendance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check daily attendance and mark alpha for students who havent attended today';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today()->format('Y-m-d');
        
        // Get all active students
        $activeStudents = Mahasiswa::where('is_active', 1)->get();
        
        $markedAlphaCount = 0;
        
        foreach ($activeStudents as $student) {
            // Check if student has attendance record for today
            $hasAttendance = Attendance::where('mahasiswa_id', $student->id)
                ->where('date', $today)
                ->exists();
            
            // If no attendance record for today, mark as alpha
            if (!$hasAttendance) {
                Attendance::create([
                    'mahasiswa_id' => $student->id,
                    'date' => $today,
                    'status' => 'alpha',
                    'check_in' => null,
                    'check_out' => null,
                    'created_at' => Carbon::now(),
                ]);
                
                $markedAlphaCount++;
                $this->info("Marked alpha for student: {$student->name} ({$student->id})");
            }
        }
        
        $this->info("Daily attendance check completed. Marked {$markedAlphaCount} students as alpha for today: {$today}");
        
        return Command::SUCCESS;
    }
}
