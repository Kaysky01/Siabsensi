# Requirements Document: Jadwal Absensi dengan Validasi Waktu dan Keterangan Telat

## Introduction

Sistem ini menambahkan fitur jadwal absensi harian dengan validasi waktu check-in/check-out dan deteksi keterlambatan otomatis. Admin dapat mengatur jadwal per hari dalam seminggu dengan parameter waktu spesifik. Sistem akan menolak atau menerima absensi berdasarkan jadwal, mencatat durasi keterlambatan, dan memungkinkan admin untuk override status telat dengan alasan tertentu.

## Glossary

- **Attendance_System**: Sistem absensi QR Code berbasis Laravel + Python Backend
- **Admin**: User dengan role admin yang dapat mengatur jadwal dan melakukan override status telat
- **Mahasiswa**: User yang melakukan absensi check-in dan check-out via QR code
- **Schedule**: Jadwal absensi harian yang berisi parameter waktu untuk check-in dan check-out
- **Check_In_Validator**: Komponen yang memvalidasi waktu check-in berdasarkan jadwal
- **Check_Out_Validator**: Komponen yang memvalidasi waktu check-out berdasarkan jadwal
- **Lateness_Calculator**: Komponen yang menghitung durasi keterlambatan dalam menit
- **Override_Manager**: Komponen yang mengelola override status telat oleh admin
- **Python_Backend**: Backend Python (attendance_engine.py) yang memproses QR scan dan validasi waktu
- **Laravel_Admin**: Backend Laravel yang mengelola CRUD jadwal dan UI admin
- **Grace_Period**: Periode waktu setelah check_in_end dimana check-in masih diterima tetapi dianggap telat (40 menit)

## Requirements

### Requirement 1: Schedule Configuration Management

**User Story:** As an Admin, I want to configure weekly attendance schedules, so that the system can validate attendance based on daily time rules.

#### Acceptance Criteria

1. THE Admin SHALL be able to create schedule configurations for each day of the week (Monday to Sunday)
2. WHEN creating a schedule for a day, THE Laravel_Admin SHALL accept four time parameters: check_in_start, check_in_end, check_out_start, and check_out_end
3. THE Laravel_Admin SHALL validate that check_in_start < check_in_end < check_out_start < check_out_end
4. THE Admin SHALL be able to enable or disable a schedule for specific days
5. WHEN the system is first installed, THE Attendance_System SHALL NOT have any default schedules configured
6. WHEN a day has no active schedule or is disabled, THE Python_Backend SHALL reject all attendance attempts for that day with message "Tidak ada jadwal absensi untuk hari ini"
7. THE Laravel_Admin SHALL store schedule configurations in a database table with fields: day_of_week, check_in_start, check_in_end, check_out_start, check_out_end, is_active

### Requirement 2: Check-In Time Validation

**User Story:** As a Mahasiswa, I want my check-in to be validated against the daily schedule, so that I can only check-in during the allowed time window.

#### Acceptance Criteria

1. WHEN a Mahasiswa scans QR code for check-in, THE Check_In_Validator SHALL retrieve the schedule for the current day of week
2. IF the current time is before check_in_start, THEN THE Python_Backend SHALL reject the check-in with message "Absen masuk belum dibuka"
3. WHEN the current time is between check_in_start and check_in_end (inclusive), THE Python_Backend SHALL accept the check-in without late status
4. WHEN the current time is after check_in_end and within Grace_Period (40 minutes), THE Python_Backend SHALL accept the check-in with late status
5. IF the current time is beyond Grace_Period, THEN THE Python_Backend SHALL reject the check-in with message "Absen masuk sudah ditutup"
6. WHEN a check-in is accepted with late status, THE Lateness_Calculator SHALL calculate the duration in minutes from check_in_end to current time
7. THE Python_Backend SHALL store is_late (boolean) and late_duration (integer minutes) fields in the attendance record

### Requirement 3: Late Duration Calculation

**User Story:** As a system administrator, I want late duration to be calculated accurately, so that lateness records are precise for reporting.

#### Acceptance Criteria

1. WHEN a Mahasiswa checks-in after check_in_end, THE Lateness_Calculator SHALL compute late_duration as the difference in minutes between current_time and check_in_end
2. WHEN a Mahasiswa checks-in on-time or early, THE Python_Backend SHALL set late_duration to 0
3. THE Lateness_Calculator SHALL round up partial minutes to the next whole minute
4. WHEN a check-in is accepted as late, THE Python_Backend SHALL set is_late to TRUE and late_duration to the computed value
5. THE Python_Backend SHALL store late_duration as an integer field in the attendance table

### Requirement 4: Check-Out Time Validation

**User Story:** As a Mahasiswa, I want my check-out to be validated against the daily schedule, so that I can only check-out during the allowed time window.

#### Acceptance Criteria

1. WHEN a Mahasiswa scans QR code for check-out, THE Check_Out_Validator SHALL retrieve the schedule for the current day of week
2. IF the current time is before check_out_start, THEN THE Python_Backend SHALL reject the check-out with message "Belum waktunya check-out"
3. WHEN the current time is between check_out_start and check_out_end (inclusive), THE Python_Backend SHALL accept the check-out
4. IF the current time is after check_out_end, THEN THE Python_Backend SHALL reject the check-out with message "Waktu check-out sudah ditutup"
5. THE Python_Backend SHALL NOT set any late status flags for check-out (no is_late for check-out)

### Requirement 5: Late Status Override by Admin

**User Story:** As an Admin, I want to override late status for specific attendance records, so that I can handle exceptional circumstances with documented reasons.

#### Acceptance Criteria

1. WHEN viewing attendance history, THE Laravel_Admin SHALL display an "Hapus Status Telat" button for each attendance record where is_late is TRUE and late_overridden is FALSE
2. WHEN an Admin clicks "Hapus Status Telat", THE Laravel_Admin SHALL display a form requiring override_reason (text field)
3. THE Laravel_Admin SHALL require override_reason to be non-empty before accepting the override
4. WHEN an Admin submits a valid override, THE Override_Manager SHALL set late_overridden to TRUE, store the admin username in overridden_by, and store the reason in override_reason
5. THE Laravel_Admin SHALL preserve the original is_late and late_duration values even after override
6. WHEN an attendance record has late_overridden set to TRUE, THE Laravel_Admin SHALL display an override indicator (icon or badge) in the attendance history
7. THE Override_Manager SHALL record the override timestamp in the attendance record

### Requirement 6: Student Dashboard Late Status Display

**User Story:** As a Mahasiswa, I want to see my late status clearly on my dashboard, so that I am aware of my attendance record.

#### Acceptance Criteria

1. WHEN a Mahasiswa views their dashboard, THE Attendance_System SHALL display a red "TELAT X menit" badge for attendance records where is_late is TRUE and late_overridden is FALSE
2. WHEN late_overridden is TRUE, THE Attendance_System SHALL NOT display the late badge to the Mahasiswa
3. THE Attendance_System SHALL calculate total late occurrences for the current period and display it on the dashboard
4. WHEN displaying attendance history to Mahasiswa, THE Attendance_System SHALL show late_duration in a dedicated column for records with is_late TRUE

### Requirement 7: Admin Attendance History with Override Controls

**User Story:** As an Admin, I want to view attendance history with late status and override controls, so that I can manage attendance records effectively.

#### Acceptance Criteria

1. WHEN an Admin views attendance history, THE Laravel_Admin SHALL display columns for: Mahasiswa name, check-in time, check-out time, late status, late duration, and override status
2. WHEN an attendance record has is_late TRUE and late_overridden FALSE, THE Laravel_Admin SHALL display the late duration in minutes and show an actionable "Hapus Status Telat" button
3. WHEN an attendance record has late_overridden TRUE, THE Laravel_Admin SHALL display an override indicator with tooltip showing overridden_by and override_reason
4. THE Laravel_Admin SHALL allow filtering attendance history by late status (all, late only, overridden only)
5. THE Laravel_Admin SHALL display override history including timestamp, admin username, and reason

### Requirement 8: Schedule Validation UI for Admin

**User Story:** As an Admin, I want a user interface to manage weekly schedules, so that I can configure attendance rules easily.

#### Acceptance Criteria

1. THE Laravel_Admin SHALL provide a schedule management page accessible to Admin role only
2. WHEN an Admin opens the schedule page, THE Laravel_Admin SHALL display the current schedule for all seven days of the week
3. FOR EACH day, THE Laravel_Admin SHALL display input fields for check_in_start, check_in_end, check_out_start, and check_out_end in HH:MM format
4. THE Laravel_Admin SHALL provide a toggle to enable or disable the schedule for each day
5. WHEN an Admin saves a schedule, THE Laravel_Admin SHALL validate time order constraints (check_in_start < check_in_end < check_out_start < check_out_end)
6. IF validation fails, THEN THE Laravel_Admin SHALL display an error message indicating which constraint was violated
7. THE Laravel_Admin SHALL display the Grace_Period value (40 minutes) as an informational note on the schedule page

### Requirement 9: Database Schema Extensions

**User Story:** As a system architect, I want the database schema to support schedule and late tracking features, so that all required data is persisted correctly.

#### Acceptance Criteria

1. THE Attendance_System SHALL create a weekly_schedules table with columns: id, day_of_week (0-6 or Monday-Sunday), check_in_start, check_in_end, check_out_start, check_out_end, is_active, created_at, updated_at
2. THE Attendance_System SHALL add columns to the attendance table: is_late (boolean default FALSE), late_duration (integer default 0), late_overridden (boolean default FALSE), overridden_by (string nullable), override_reason (text nullable), override_timestamp (datetime nullable)
3. THE Attendance_System SHALL create database indexes on: attendance.is_late, attendance.late_overridden, attendance.date for query performance
4. THE Attendance_System SHALL provide a migration script to add these schema changes to existing installations

### Requirement 10: Integration Between Laravel and Python Backend

**User Story:** As a system integrator, I want seamless communication between Laravel and Python backend for schedule retrieval, so that validation works in real-time during QR scan.

#### Acceptance Criteria

1. WHEN Python_Backend needs to validate attendance time, THE Python_Backend SHALL query the weekly_schedules table for the current day of week
2. THE Python_Backend SHALL cache schedule data for the current day to minimize database queries
3. WHEN a schedule is updated by Admin, THE Laravel_Admin SHALL invalidate the schedule cache
4. THE Python_Backend SHALL handle cases where no schedule exists for a day by rejecting all attendance with appropriate message
5. THE Python_Backend SHALL log all validation decisions (accept/reject with reason) to the attendance log file

### Requirement 11: Grace Period Configuration

**User Story:** As an Admin, I want the grace period to be configurable, so that I can adjust the lateness tolerance based on institutional policy.

#### Acceptance Criteria

1. THE Laravel_Admin SHALL store grace_period_minutes as a system configuration setting (default 40 minutes)
2. THE Admin SHALL be able to update grace_period_minutes through the schedule management UI
3. WHEN Check_In_Validator calculates the late acceptance window, THE Check_In_Validator SHALL use the configured grace_period_minutes value
4. THE Laravel_Admin SHALL validate that grace_period_minutes is between 0 and 120 minutes
5. WHEN grace_period_minutes is changed, THE Python_Backend SHALL reload the configuration without requiring restart

### Requirement 12: Reporting and Analytics

**User Story:** As an Admin, I want to generate reports on late attendance, so that I can analyze attendance patterns and identify trends.

#### Acceptance Criteria

1. THE Laravel_Admin SHALL provide a late attendance report showing total late occurrences per Mahasiswa for a date range
2. THE Laravel_Admin SHALL calculate average late_duration per Mahasiswa and display it in the report
3. THE Laravel_Admin SHALL allow filtering the report by kompi, jurusan, and date range
4. THE Laravel_Admin SHALL export the late attendance report to CSV format
5. THE Laravel_Admin SHALL display a summary showing: total late occurrences, total overrides, and average late duration for the selected period

### Requirement 13: Schedule Absence Handling

**User Story:** As a Mahasiswa, I want clear feedback when there is no schedule for the current day, so that I understand why I cannot check-in.

#### Acceptance Criteria

1. WHEN a Mahasiswa attempts check-in on a day with no active schedule, THE Python_Backend SHALL return a response with status "rejected" and message "Tidak ada jadwal absensi untuk hari ini"
2. THE Python_Backend SHALL log schedule absence events to the attendance log
3. THE Attendance_System SHALL NOT create any attendance record when schedule is absent
4. THE Python_Backend SHALL apply the same schedule absence check for both check-in and check-out attempts

### Requirement 14: Time Zone Consistency

**User Story:** As a system administrator, I want all time comparisons to use a consistent timezone, so that validation is accurate regardless of server configuration.

#### Acceptance Criteria

1. THE Python_Backend SHALL use the timezone configured in the Laravel application for all time comparisons
2. THE Python_Backend SHALL convert schedule times to the application timezone before comparison
3. THE Lateness_Calculator SHALL use timezone-aware datetime objects for duration calculation
4. THE Laravel_Admin SHALL display all times in the application timezone with timezone indicator
5. THE Attendance_System SHALL store all timestamps in UTC in the database and convert to application timezone for display and validation

### Requirement 15: Kegiatan Independence

**User Story:** As a system architect, I want daily schedule validation to be independent of Kegiatan (event-based attendance), so that both features can coexist without interference.

#### Acceptance Criteria

1. WHEN an attendance record has kegiatan_id set, THE Python_Backend SHALL bypass daily schedule validation
2. WHEN an attendance record has kegiatan_id NULL, THE Python_Backend SHALL apply daily schedule validation
3. THE Attendance_System SHALL NOT apply late status or time validation to Kegiatan-based attendance
4. THE Laravel_Admin SHALL clearly indicate in the UI when an attendance is Kegiatan-based versus daily schedule-based
5. THE Attendance_System SHALL allow both daily attendance and Kegiatan attendance to exist for the same Mahasiswa on the same date without conflict
