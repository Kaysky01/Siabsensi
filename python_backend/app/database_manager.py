import mysql.connector
from datetime import datetime, date
import logging
import sys
from pathlib import Path

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent.parent))

from app.config_db import MYSQL_CONFIG

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
        """Cari mahasiswa berdasarkan QR code"""
        query = "SELECT * FROM mahasiswa WHERE qr_code_id = %s AND is_active = 1"
        return self._execute(query, (qr_code_id,), fetch_one=True)

    def record_attendance(self, mahasiswa_id, action, camera_id, snapshot_path, confidence):
        """Record kehadiran"""
        today = date.today().isoformat()
        now = datetime.now()
        
        # Cek existing attendance
        existing = self._execute(
            "SELECT * FROM attendance WHERE mahasiswa_id = %s AND date = %s",
            (mahasiswa_id, today),
            fetch_one=True
        )

        # Format waktu yang standar dan kompatibel dengan Laravel
        time_str = now.strftime('%Y-%m-%d %H:%M:%S')
        just_time_str = now.strftime('%H:%M:%S')

        if action == 'check_in':
            if existing:
                logger.info(f"[{mahasiswa_id}] Sudah absen masuk hari ini.")
                return {'status': 'already_checked_in', 'time': existing['check_in']}
            
            self._execute("""
                INSERT INTO attendance (mahasiswa_id, check_in, date, status, camera_id, snapshot_path, yolo_confidence)
                VALUES (%s, %s, %s, 'hadir', %s, %s, %s)
            """, (mahasiswa_id, now.isoformat(), today, camera_id, snapshot_path, confidence))
            return {'status': 'checked_in', 'time': now.isoformat()}

        elif action == 'check_out':
            if not existing:
                logger.warning(f"[{mahasiswa_id}] Belum absen masuk!")
                return {'status': 'not_checked_in'}
            if existing['check_out']:
                return {'status': 'already_checked_out', 'time': existing['check_out']}
            
            self._execute("""
                UPDATE attendance SET check_out = %s, check_out_time = %s, snapshot_path = %s
                WHERE mahasiswa_id = %s AND date = %s
            """, (time_str, just_time_str, snapshot_path, mahasiswa_id, today))
            return {'status': 'checked_out', 'time': time_str}

    def update_camera_seen(self, cam_id):
        """Update last seen kamera"""
        self._execute(
            "UPDATE camera_streams SET last_seen = %s WHERE id = %s",
            (datetime.now().isoformat(), cam_id)
        )