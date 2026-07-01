import mysql.connector
from datetime import datetime, date
import logging
import sys
from pathlib import Path

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent.parent))

from app.config_db import MYSQL_CONFIG
from app.timezone_utils import get_current_time, get_current_date, format_datetime

logger = logging.getLogger('DatabaseManager')

class DatabaseManager:
    def __init__(self):
        logger.info(f"Menggunakan MySQL: {MYSQL_CONFIG['host']}:{MYSQL_CONFIG['port']}/{MYSQL_CONFIG['database']}")
        self._init_db()

    def _get_conn(self):
        """Dapatkan koneksi MySQL"""
        return mysql.connector.connect(**MYSQL_CONFIG)

    def _execute(self, query, params=None, fetch_one=False, fetch_all=False):
        """
        Execute query MySQL with proper parameterization
        
        SECURITY: Uses parameterized queries to prevent SQL injection
        Always use %s placeholders in queries, never string formatting
        """
        conn = self._get_conn()
        cursor = conn.cursor()
        
        try:
            # Execute with parameters (already using %s placeholders)
            if params:
                cursor.execute(query, params)
            else:
                cursor.execute(query)
            
            result = None
            if fetch_one:
                row = cursor.fetchone()
                if row:
                    columns = [desc[0] for desc in cursor.description]
                    result = dict(zip(columns, row))
            elif fetch_all:
                rows = cursor.fetchall()
                columns = [desc[0] for desc in cursor.description]
                result = [dict(zip(columns, row)) for row in rows]
            else:
                conn.commit()
                result = cursor.lastrowid
            
            return result
        except mysql.connector.Error as e:
            # Log error untuk debugging
            logger.error(f"Database error: {e}")
            logger.error(f"Query: {query}")
            logger.error(f"Params: {params}")
            conn.rollback()
            raise
        finally:
            cursor.close()
            conn.close()

    def _init_db(self):
        """Inisialisasi database MySQL"""
        conn = self._get_conn()
        cursor = conn.cursor()
        
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS mahasiswa (
                id VARCHAR(50) PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                kompi VARCHAR(100) NOT NULL,
                jurusan VARCHAR(100) NOT NULL,
                prodi VARCHAR(100),
                email VARCHAR(255),
                no_telp_mahasiswa VARCHAR(20),
                no_telp_ortu VARCHAR(20),
                qr_code_id VARCHAR(100) UNIQUE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                is_active TINYINT(1) DEFAULT 1,
                INDEX idx_qr_code (qr_code_id),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """)
        
        # Add missing columns to mahasiswa table if they don't exist
        try:
            cursor.execute("ALTER TABLE mahasiswa ADD COLUMN no_telp_mahasiswa VARCHAR(20)")
        except mysql.connector.Error:
            pass  # Column already exists
            
        try:
            cursor.execute("ALTER TABLE mahasiswa ADD COLUMN no_telp_ortu VARCHAR(20)")
        except mysql.connector.Error:
            pass  # Column already exists

        try:
            cursor.execute("ALTER TABLE mahasiswa ADD COLUMN prodi VARCHAR(100)")
        except mysql.connector.Error:
            pass  # Column already exists
        
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS attendance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                mahasiswa_id VARCHAR(50) NOT NULL,
                check_in DATETIME,
                check_out DATETIME,
                date DATE NOT NULL,
                status VARCHAR(20) DEFAULT 'present',
                camera_id VARCHAR(50),
                snapshot_path TEXT,
                yolo_confidence FLOAT,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_date (date),
                INDEX idx_mahasiswa (mahasiswa_id),
                INDEX idx_checkin (check_in)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """)
        
        # Add missing columns to attendance table if they don't exist
        try:
            cursor.execute("ALTER TABLE attendance ADD COLUMN check_in_time TIME")
        except mysql.connector.Error:
            pass  # Column already exists
            
        try:
            cursor.execute("ALTER TABLE attendance ADD COLUMN check_out_time TIME")
        except mysql.connector.Error:
            pass  # Column already exists
            
        try:
            cursor.execute("ALTER TABLE attendance ADD COLUMN kegiatan_id BIGINT UNSIGNED NULL")
        except mysql.connector.Error:
            pass  # Column already exists
        
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS camera_streams (
                id VARCHAR(50) PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                rtsp_url TEXT NOT NULL,
                location VARCHAR(255),
                is_active TINYINT(1) DEFAULT 1,
                last_seen DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """)
        
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS system_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                level VARCHAR(20),
                message TEXT,
                camera_id VARCHAR(50),
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_timestamp (timestamp),
                INDEX idx_level (level)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """)
        
        # Tabel untuk pengajuan izin/sakit
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS izin_submissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                mahasiswa_id VARCHAR(50) NOT NULL,
                submission_type ENUM('izin', 'sakit') NOT NULL,
                date DATE NOT NULL,
                keterangan TEXT NOT NULL,
                bukti_path TEXT,
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                verified_by VARCHAR(100),
                verified_at DATETIME,
                rejection_reason TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_mahasiswa (mahasiswa_id),
                INDEX idx_date (date),
                INDEX idx_status (status),
                INDEX idx_created (created_at),
                FOREIGN KEY (mahasiswa_id) REFERENCES mahasiswa(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """)
        
        # Tabel untuk pengajuan kehadiran manual
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS kehadiran_submissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                mahasiswa_id VARCHAR(50) NOT NULL,
                date DATE NOT NULL,
                check_in_time TIME NOT NULL,
                check_out_time TIME NOT NULL,
                keterangan TEXT NOT NULL,
                bukti_path TEXT NOT NULL,
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                verified_by VARCHAR(100),
                verified_at DATETIME,
                rejection_reason TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_mahasiswa (mahasiswa_id),
                INDEX idx_date (date),
                INDEX idx_status (status),
                INDEX idx_created (created_at),
                FOREIGN KEY (mahasiswa_id) REFERENCES mahasiswa(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """)
        
        # Tabel untuk riwayat sertifikat
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS sertifikat_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                mahasiswa_id VARCHAR(50) NOT NULL,
                periode TEXT NOT NULL,
                template VARCHAR(50) NOT NULL,
                total_hadir INT NOT NULL,
                persentase DECIMAL(5,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_mahasiswa (mahasiswa_id),
                INDEX idx_created (created_at),
                FOREIGN KEY (mahasiswa_id) REFERENCES mahasiswa(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """)
        
        conn.commit()
        cursor.close()
        conn.close()
        logger.info("Database MySQL diinisialisasi.")

    def get_mahasiswa_by_qr(self, qr_code_id: str):
        """Cari mahasiswa berdasarkan QR code (termasuk yang non-aktif)"""
        query = "SELECT * FROM mahasiswa WHERE qr_code_id = %s"
        return self._execute(query, (qr_code_id,), fetch_one=True)
        
    def get_active_kegiatan(self):
        """Ambil daftar kegiatan PKKMB yang sedang aktif dari tabel kegiatan"""
        try:
            query = "SELECT id, nama, tanggal_pelaksanaan FROM kegiatan WHERE is_active = 1 ORDER BY tanggal_pelaksanaan ASC"
            return self._execute(query, fetch_all=True)
        except Exception as e:
            logger.error(f"Failed to get active kegiatan: {e}")
            return []

    def record_attendance(self, mahasiswa_id, action, camera_id, snapshot_path, confidence, kegiatan_id=None, is_late=False, late_duration=0):
        """Record kehadiran dengan late tracking (timezone-aware)"""
        today = get_current_date().isoformat()
        
        # Jika ada kegiatan_id, ambil tanggal pelaksanaannya dari DB agar absen sinkron dengan jadwal kegiatan
        if kegiatan_id:
            kegiatan = self._execute("SELECT tanggal_pelaksanaan FROM kegiatan WHERE id = %s", (kegiatan_id,), fetch_one=True)
            if kegiatan and kegiatan['tanggal_pelaksanaan']:
                # handle if tanggal_pelaksanaan is a datetime.date object or string
                if hasattr(kegiatan['tanggal_pelaksanaan'], 'isoformat'):
                    today = kegiatan['tanggal_pelaksanaan'].isoformat()
                else:
                    today = str(kegiatan['tanggal_pelaksanaan'])

        now = get_current_time()
        
        # Cek existing attendance
        if kegiatan_id:
            existing = self._execute(
                "SELECT * FROM attendance WHERE mahasiswa_id = %s AND kegiatan_id = %s",
                (mahasiswa_id, kegiatan_id),
                fetch_one=True
            )
        else:
            existing = self._execute(
                "SELECT * FROM attendance WHERE mahasiswa_id = %s AND date = %s",
                (mahasiswa_id, today),
                fetch_one=True
            )

        # Format waktu yang standar dan kompatibel dengan Laravel/MySQL (timezone-aware)
        time_str = format_datetime(now, '%Y-%m-%d %H:%M:%S')
        just_time_str = now.strftime('%H:%M:%S')

        if action == 'check_in':
            if existing:
                logger.info(f"[{mahasiswa_id}] Sudah absen masuk untuk kegiatan/hari ini.")
                
                # Pastikan check_in diformat ke string bersih
                checkin_time = existing['check_in']
                if hasattr(checkin_time, 'strftime'):
                    checkin_time = checkin_time.strftime('%H:%M:%S')
                elif checkin_time:
                    checkin_time = str(checkin_time)
                else:
                    checkin_time = just_time_str
                    
                return {'status': 'already_checked_in', 'time': checkin_time}
            
            self._execute("""
                INSERT INTO attendance (mahasiswa_id, check_in, check_in_time, date, status, camera_id, snapshot_path, yolo_confidence, kegiatan_id, is_late, late_duration)
                VALUES (%s, %s, %s, %s, 'hadir', %s, %s, %s, %s, %s, %s)
            """, (mahasiswa_id, time_str, just_time_str, today, camera_id, snapshot_path, confidence, kegiatan_id, is_late, late_duration))
            return {'status': 'checked_in', 'time': just_time_str, 'is_late': is_late, 'late_duration': late_duration}

        elif action == 'check_out':
            if not existing:
                logger.warning(f"[{mahasiswa_id}] Belum absen masuk!")
                return {'status': 'not_checked_in'}
            if existing['check_out']:
                checkout_time = existing['check_out']
                if hasattr(checkout_time, 'strftime'):
                    checkout_time = checkout_time.strftime('%H:%M:%S')
                elif checkout_time:
                    checkout_time = str(checkout_time)
                else:
                    checkout_time = just_time_str
                return {'status': 'already_checked_out', 'time': checkout_time}
            
            if kegiatan_id:
                self._execute("""
                    UPDATE attendance SET check_out = %s, check_out_time = %s, snapshot_path = %s
                    WHERE mahasiswa_id = %s AND kegiatan_id = %s
                """, (time_str, just_time_str, snapshot_path, mahasiswa_id, kegiatan_id))
            else:
                self._execute("""
                    UPDATE attendance SET check_out = %s, check_out_time = %s, snapshot_path = %s
                    WHERE mahasiswa_id = %s AND date = %s
                """, (time_str, just_time_str, snapshot_path, mahasiswa_id, today))
            return {'status': 'checked_out', 'time': just_time_str}

    def update_camera_seen(self, cam_id):
        """Update last seen kamera"""
        self._execute(
            "UPDATE camera_streams SET last_seen = %s WHERE id = %s",
            (datetime.now().isoformat(), cam_id)
        )

    # ========== ATTENDANCE SCHEDULE METHODS ==========
    
    def get_schedule_for_day(self, day_of_week: int):
        """
        Get schedule for specific day of week (ISO-8601: 1=Monday, 7=Sunday)
        Returns dict with schedule details or None if no active schedule
        """
        query = """
            SELECT id, day_of_week, check_in_start, check_in_end, 
                   check_out_start, check_out_end, is_active
            FROM attendance_schedules
            WHERE day_of_week = %s AND is_active = 1
            LIMIT 1
        """
        return self._execute(query, (day_of_week,), fetch_one=True)
    
    def get_today_schedule(self):
        """
        Get schedule for today based on current day of week
        Returns dict with schedule or None
        """
        from datetime import datetime
        # ISO-8601: Monday=1, Sunday=7
        today_dow = datetime.now().isoweekday()
        return self.get_schedule_for_day(today_dow)
    
    def get_all_schedules(self):
        """Get all schedules (active and inactive) ordered by day"""
        query = """
            SELECT id, day_of_week, check_in_start, check_in_end,
                   check_out_start, check_out_end, is_active
            FROM attendance_schedules
            ORDER BY day_of_week ASC
        """
        return self._execute(query, fetch_all=True)
    
    def get_system_config(self, config_key: str, default_value=None):
        """Get system config value by key"""
        query = "SELECT config_value FROM system_config WHERE config_key = %s"
        result = self._execute(query, (config_key,), fetch_one=True)
        return result['config_value'] if result else default_value
    
    def get_grace_period_minutes(self) -> int:
        """Get grace period in minutes from system config"""
        value = self.get_system_config('attendance_grace_period_minutes', '40')
        try:
            return int(value)
        except (ValueError, TypeError):
            logger.warning(f"Invalid grace period value: {value}, using default 40")
            return 40