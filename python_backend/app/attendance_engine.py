import cv2
import numpy as np
import json
import time
import threading
import logging
import qrcode
import base64
from datetime import datetime, date
from pathlib import Path
from pyzbar.pyzbar import decode as qr_decode
from ultralytics import YOLO
from PIL import Image
import io
import os
import sys
from pathlib import Path

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent.parent))

from app.database_manager import DatabaseManager
from app.config_db import YOLO_SETTINGS, RTSP_SETTINGS
from app.time_validator import TimeValidator
from app.timezone_utils import get_current_time, get_current_date, format_datetime

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(name)s: %(message)s',
    handlers=[
        logging.FileHandler('logs/attendance.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger('AttendanceEngine')

SNAPSHOT_DIR = Path('data/snapshots')
QR_DIR = Path('data/qrcodes')

SNAPSHOT_DIR.mkdir(parents=True, exist_ok=True)
QR_DIR.mkdir(parents=True, exist_ok=True)
Path('data').mkdir(parents=True, exist_ok=True)
Path('logs').mkdir(exist_ok=True)

# Load settings from config_db (Laravel integration)
MODEL_PATH = Path(YOLO_SETTINGS.get('model_path', 'models/yolov8n.pt'))
YOLO_CONF_THRESHOLD = YOLO_SETTINGS.get('confidence', 0.45)
QR_COOLDOWN = YOLO_SETTINGS.get('qr_cooldown', 30)
FRAME_WIDTH = RTSP_SETTINGS.get('frame_width', 1280)
FRAME_HEIGHT = RTSP_SETTINGS.get('frame_height', 720)
FRAME_FPS = RTSP_SETTINGS.get('frame_fps', 30)

class QRCodeGenerator:
    @staticmethod
    def generate(qr_data: str, mahasiswa_name: str, save_path: Path) -> str:
        qr = qrcode.QRCode(
            version=2,
            error_correction=qrcode.constants.ERROR_CORRECT_H,
            box_size=12,
            border=4
        )
        qr.add_data(qr_data)
        qr.make(fit=True)

        img = qr.make_image(fill_color="#1a1a2e", back_color="white")

        from PIL import ImageDraw, ImageFont
        draw = ImageDraw.Draw(img)
        w, h = img.size
        try:
            font = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf", 18)
        except:
            font = ImageFont.load_default()

        text = mahasiswa_name.upper()
        bbox = draw.textbbox((0, 0), text, font=font)
        text_w = bbox[2] - bbox[0]
        draw.text(((w - text_w) // 2, h - 30), text, fill="#1a1a2e", font=font)

        filename = save_path / f"{qr_data}.png"
        img.save(filename)

        buffer = io.BytesIO()
        img.save(buffer)
        return base64.b64encode(buffer.getvalue()).decode('utf-8')

    @staticmethod
    def decode_frame(frame: np.ndarray) -> list:
        gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
        decoded = qr_decode(gray)
        results = []
        for obj in decoded:
            data = obj.data.decode('utf-8')
            pts = obj.polygon
            if len(pts) == 4:
                pts_arr = np.array([[p.x, p.y] for p in pts], dtype=np.int32)
                results.append({'data': data, 'polygon': pts_arr})
        return results

class WebcamStream:
    """
    Webcam stream handler untuk capture dari webcam lokal.
    Mendukung built-in webcam dan USB webcam eksternal.
    """
    def __init__(self, camera_id: str, camera_index: int, name: str = ''):
        self.camera_id = camera_id
        self.camera_index = camera_index  # 0 = built-in, 1,2,3 = USB webcam
        self.name = name or f"Camera {camera_index}"
        self.cap = None
        self.frame = None
        self.running = False
        self.lock = threading.Lock()
        self.fps = 0
        self.connected = False
        self._thread = None
        self._frame_count = 0
        self._last_fps_time = time.time()

    def _connect(self):
        """Connect to webcam"""
        if self.cap:
            self.cap.release()

        # Try DirectShow backend first (more stable on Windows)
        # Then fallback to default backend
        backends = [
            (cv2.CAP_DSHOW, "DirectShow"),  # Windows DirectShow (more stable)
            (cv2.CAP_ANY, "Default")         # Default backend
        ]
        
        for backend, backend_name in backends:
            try:
                logger.info(f"[{self.camera_id}] Mencoba backend {backend_name}...")
                self.cap = cv2.VideoCapture(self.camera_index, backend)
                
                if self.cap.isOpened():
                    # Set webcam properties
                    self.cap.set(cv2.CAP_PROP_FRAME_WIDTH, FRAME_WIDTH)
                    self.cap.set(cv2.CAP_PROP_FRAME_HEIGHT, FRAME_HEIGHT)
                    self.cap.set(cv2.CAP_PROP_FPS, FRAME_FPS)
                    self.cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
                    
                    # Test read frame
                    ret, test_frame = self.cap.read()
                    if ret and test_frame is not None:
                        self.connected = True
                        actual_fps = self.cap.get(cv2.CAP_PROP_FPS)
                        actual_width = int(self.cap.get(cv2.CAP_PROP_FRAME_WIDTH))
                        actual_height = int(self.cap.get(cv2.CAP_PROP_FRAME_HEIGHT))
                        logger.info(f"[{self.camera_id}] ✅ Webcam terhubung: {self.name} (Index: {self.camera_index}, Backend: {backend_name})")
                        logger.info(f"[{self.camera_id}] Resolution: {actual_width}x{actual_height}, FPS: {actual_fps:.1f}")
                        return True
                    else:
                        logger.warning(f"[{self.camera_id}] Backend {backend_name} opened but can't read frame")
                        self.cap.release()
                        
            except Exception as e:
                logger.warning(f"[{self.camera_id}] Backend {backend_name} failed: {e}")
                if self.cap:
                    self.cap.release()
                continue
        
        # All backends failed
        self.connected = False
        logger.error(f"[{self.camera_id}] ❌ Gagal membuka webcam index {self.camera_index} dengan semua backend")
        return False

    def _read_loop(self):
        """Main loop untuk membaca frame dari webcam"""
        while self.running:
            if not self.connected:
                if not self._connect():
                    logger.error(f"[{self.camera_id}] Webcam tidak tersedia, menunggu 5 detik...")
                    time.sleep(5)
                    continue

            ret, frame = self.cap.read()
            if not ret:
                logger.warning(f"[{self.camera_id}] Gagal membaca frame, mencoba reconnect...")
                self.connected = False
                time.sleep(1)
                continue

            with self.lock:
                self.frame = frame

            # Calculate FPS
            self._frame_count += 1
            elapsed = time.time() - self._last_fps_time
            if elapsed >= 1.0:
                self.fps = self._frame_count / elapsed
                self._frame_count = 0
                self._last_fps_time = time.time()

    def start(self):
        """Start webcam stream"""
        self.running = True
        self._thread = threading.Thread(target=self._read_loop, daemon=True)
        self._thread.start()
        logger.info(f"[{self.camera_id}] Webcam stream dimulai: {self.name}")

    def stop(self):
        """Stop webcam stream"""
        self.running = False
        if self.cap:
            self.cap.release()
        logger.info(f"[{self.camera_id}] Webcam stream dihentikan: {self.name}")

    def get_frame(self) -> tuple[bool, np.ndarray | None]:
        """Get latest frame from webcam"""
        with self.lock:
            if self.frame is None:
                return False, None
            return True, self.frame.copy()

    def save_snapshot(self, mahasiswa_id: str) -> str:
        ret, frame = self.get_frame()
        if not ret:
            return ''
        ts = get_current_time().strftime('%Y%m%d_%H%M%S')
        filename = SNAPSHOT_DIR / f"{mahasiswa_id}_{self.camera_id}_{ts}.jpg"
        cv2.imwrite(str(filename), frame, [cv2.IMWRITE_JPEG_QUALITY, 85])
        return str(filename)

class YOLOProcessor:
    def __init__(self, model_path: Path):
        logger.info("Memuat model YOLO...")
        self.model = YOLO(str(model_path))
        # Class ID untuk QR code paper (akan dilatih custom)
        # Setelah training, ganti dengan class ID yang sesuai
        self.qr_paper_class_id = 0  # Default class 0, sesuaikan setelah training
        logger.info(f"Model YOLO siap. Target class: QR Paper (ID: {self.qr_paper_class_id})")

    def detect_qr_papers(self, frame: np.ndarray) -> list[dict]:
        """
        Deteksi QR code paper menggunakan YOLO custom model.
        Setelah melatih model, fungsi ini akan mendeteksi kertas QR code.
        """
        results = self.model(frame, conf=YOLO_CONF_THRESHOLD, classes=[self.qr_paper_class_id], verbose=False)
        qr_papers = []
        for r in results:
            for box in r.boxes:
                x1, y1, x2, y2 = map(int, box.xyxy[0])
                conf = float(box.conf[0])
                area = (x2 - x1) * (y2 - y1)
                qr_papers.append({
                    'bbox': (x1, y1, x2, y2),
                    'confidence': conf,
                    'area': area
                })
        return qr_papers

    def draw_detections(self, frame: np.ndarray, qr_papers: list, qr_decoded: list) -> np.ndarray:
        """
        Menggambar bounding box untuk QR paper yang terdeteksi dan QR yang berhasil di-decode.
        """
        display = frame.copy()
        H, W = display.shape[:2]

        # Header bar
        cv2.rectangle(display, (0, 0), (W, 45), (45, 90, 180), -1)
        cv2.putText(display, f"SISTEM ABSENSI QR CODE  |  {get_current_time().strftime('%Y-%m-%d  %H:%M:%S')}",
                    (12, 28), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)

        # Draw YOLO detected QR papers
        for qr in qr_papers:
            x1, y1, x2, y2 = qr['bbox']
            conf = qr['confidence']
            # Warna berdasarkan confidence
            color = (0, 220, 80) if conf > 0.7 else (60, 200, 255)
            cv2.rectangle(display, (x1, y1), (x2, y2), color, 3)
            
            label = f"QR Paper {conf:.0%}"
            lw, lh = cv2.getTextSize(label, cv2.FONT_HERSHEY_SIMPLEX, 0.6, 2)[0]
            cv2.rectangle(display, (x1, y1 - lh - 10), (x1 + lw + 8, y1), color, -1)
            cv2.putText(display, label, (x1 + 4, y1 - 5),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 0, 0), 2)

        # Draw decoded QR codes (pyzbar)
        for qr in qr_decoded:
            pts = qr['polygon']
            cv2.polylines(display, [pts], True, (0, 255, 255), 3)
            center = pts.mean(axis=0).astype(int)
            
            # Tampilkan status "QR VALID" dengan background
            text = "QR VALID"
            tw, th = cv2.getTextSize(text, cv2.FONT_HERSHEY_SIMPLEX, 0.7, 2)[0]
            cv2.rectangle(display, (center[0] - tw//2 - 5, center[1] - th - 5), 
                         (center[0] + tw//2 + 5, center[1] + 5), (0, 255, 255), -1)
            cv2.putText(display, text, (center[0] - tw//2, center[1]),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 0, 0), 2)

        return display

class AttendanceProcessor:
    # Jeda minimum antara check-in dan check-out (dalam detik): Dihilangkan sesuai request
    CHECK_OUT_MIN_SECONDS = 0

    def __init__(self, db: DatabaseManager, yolo: YOLOProcessor):
        self.db = db
        self.yolo = yolo
        self.time_validator = TimeValidator(db)  # Initialize time validator
        self.cameras: dict[str, WebcamStream] = {}  # Changed from RTSPCameraStream
        self._qr_cooldowns: dict[str, float] = {}
        self._processing = False
        self._lock = threading.Lock()
        self.latest_frames = {}

    def add_camera(self, camera_id: str, camera_index: int, name: str = '', location: str = ''):
        """
        Add webcam to the system.
        
        Args:
            camera_id: Unique identifier for the camera (e.g., 'CAM-01')
            camera_index: Webcam index (0 = built-in, 1,2,3 = USB webcam)
            name: Display name for the camera
            location: Physical location of the camera
        """
        stream = WebcamStream(camera_id, camera_index, name)
        stream.start()
        self.cameras[camera_id] = stream
        # Store camera_index as string in database for compatibility
        self.db.add_camera(camera_id, name or f"Camera {camera_index}", str(camera_index), location)
        logger.info(f"Webcam '{name}' (Index: {camera_index}) ditambahkan sebagai {camera_id}.")

    def _is_qr_cooldown(self, qr_data: str) -> bool:
        last_scan = self._qr_cooldowns.get(qr_data, 0)
        return (time.time() - last_scan) < QR_COOLDOWN

    def _set_qr_cooldown(self, qr_data: str):
        self._qr_cooldowns[qr_data] = time.time()

    def _determine_action(self, mahasiswa_id: str, kegiatan_id=None) -> tuple[str, dict]:
        """
        Tentukan apakah mahasiswa harus check_in, check_out, cooldown, atau none.
        Returns tuple: (action, validation_result)
        
        Jika kegiatan_id diberikan, bypass schedule validation (untuk absensi kegiatan persesi).
        Jika tidak, validate terhadap daily schedule.
        """
        # Log untuk debugging
        logger.info(f"[_determine_action] mahasiswa_id={mahasiswa_id}, kegiatan_id={kegiatan_id}")
        
        # For kegiatan-based attendance, bypass schedule validation
        if kegiatan_id:
            logger.info(f"[{mahasiswa_id}] KEGIATAN MODE - Bypass schedule validation (kegiatan_id={kegiatan_id})")
            row = self.db._execute(
                "SELECT check_in, check_out FROM attendance WHERE mahasiswa_id=%s AND kegiatan_id=%s",
                (mahasiswa_id, kegiatan_id),
                fetch_one=True
            )
            if not row or not row['check_in']:
                return ('check_in', {'allowed': True, 'is_late': False, 'late_duration': 0, 'bypass_schedule': True})
            if row['check_in'] and not row['check_out']:
                elapsed_seconds = (get_current_time() - row['check_in']).total_seconds()
                if elapsed_seconds < self.CHECK_OUT_MIN_SECONDS:
                    remaining = int(self.CHECK_OUT_MIN_SECONDS - elapsed_seconds)
                    return ('cooldown', {'reason': 'cooldown', 'remaining_seconds': remaining})
                return ('check_out', {'allowed': True, 'bypass_schedule': True})
            return ('none', {'reason': 'already_complete'})
        
        # For daily attendance, validate against schedule
        logger.info(f"[{mahasiswa_id}] DAILY MODE - Validating against schedule")
        today = date.today().isoformat()
        row = self.db._execute(
            "SELECT check_in, check_out FROM attendance WHERE mahasiswa_id=%s AND date=%s",
            (mahasiswa_id, today),
            fetch_one=True
        )
        
        # Get today's schedule - MUST check if exists first
        schedule = self.db.get_today_schedule()
        logger.info(f"[{mahasiswa_id}] Schedule for today: {schedule}")
        
        # CRITICAL: Reject if no schedule exists for today
        if not schedule:
            logger.warning(f"[{mahasiswa_id}] No schedule configured for today")
            # Return appropriate rejection based on whether user needs check-in or check-out
            if not row or not row['check_in']:
                return ('check_in', {
                    'allowed': False,
                    'is_late': False,
                    'late_duration': 0,
                    'reason': 'no_schedule',
                    'message': 'Tidak ada jadwal absensi untuk hari ini'
                })
            else:
                return ('check_out', {
                    'allowed': False,
                    'reason': 'no_schedule',
                    'message': 'Tidak ada jadwal absensi untuk hari ini'
                })
        
        # Determine action
        if not row or not row['check_in']:
            # Need check-in - validate time
            validation = self.time_validator.validate_check_in(get_current_time(), schedule)
            logger.info(f"[{mahasiswa_id}] Check-in validation result: {validation}")
            return ('check_in', validation)
        
        if row['check_in'] and not row['check_out']:
            # Need check-out - but check cooldown first
            check_in_val = row['check_in']
            if isinstance(check_in_val, str):
                try:
                    check_in_time = datetime.fromisoformat(check_in_val.replace(' ', 'T'))
                except Exception:
                    check_in_time = datetime.strptime(check_in_val, '%Y-%m-%d %H:%M:%S')
            elif hasattr(check_in_val, 'hour'):
                check_in_time = check_in_val
            else:
                check_in_time = get_current_time()
            
            elapsed_seconds = (get_current_time() - check_in_time).total_seconds()
            if elapsed_seconds < self.CHECK_OUT_MIN_SECONDS:
                remaining = int(self.CHECK_OUT_MIN_SECONDS - elapsed_seconds)
                logger.info(f"[{mahasiswa_id}] Cooldown check-out: sisa {remaining // 60} menit {remaining % 60} detik.")
                return ('cooldown', {'reason': 'cooldown', 'remaining_seconds': remaining})
            
            # Validate check-out time
            validation = self.time_validator.validate_check_out(get_current_time(), schedule)
            logger.info(f"[{mahasiswa_id}] Check-out validation result: {validation}")
            return ('check_out', validation)
        
        # Already complete
        return ('none', {'reason': 'already_complete'})

    def process_frame(self, camera_id: str, frame: np.ndarray) -> dict:
        """
        Proses frame untuk deteksi QR code paper dan decode QR.
        Logika baru: Deteksi QR paper dengan YOLO, lalu decode QR code di area tersebut.
        """
        result = {
            'camera_id': camera_id,
            'timestamp': get_current_time().isoformat(),
            'qr_papers_detected': 0,
            'qr_scanned': None,
            'attendance': None
        }

        # Deteksi QR paper menggunakan YOLO
        qr_papers = self.yolo.detect_qr_papers(frame)
        result['qr_papers_detected'] = len(qr_papers)

        qr_decoded = []
        
        # Jika ada QR paper terdeteksi, coba decode QR code
        if qr_papers:
            # Decode QR code dari seluruh frame
            qr_results = QRCodeGenerator.decode_frame(frame)
            qr_decoded = qr_results
            
            for qr in qr_results:
                qr_data = qr['data']
                
                # Cek cooldown
                if self._is_qr_cooldown(qr_data):
                    continue

                # Cari mahasiswa berdasarkan QR code
                mahasiswa = self.db.get_mahasiswa_by_qr(qr_data)
                if not mahasiswa:
                    logger.warning(f"QR tidak dikenal: {qr_data}")
                    continue

                # Tentukan action (check_in atau check_out) dengan validasi waktu
                action, validation = self._determine_action(mahasiswa['id'])
                
                # Handle rejection
                if action in ['check_in', 'check_out'] and not validation.get('allowed', False):
                    logger.warning(f"[{mahasiswa['name']}] {action.upper()} DITOLAK: {validation.get('message', 'Unknown reason')}")
                    self._set_qr_cooldown(qr_data)  # Set cooldown to prevent spam
                    continue
                
                if action == 'none':
                    logger.info(f"[{mahasiswa['name']}] Sudah selesai absen hari ini.")
                    self._set_qr_cooldown(qr_data)
                    continue
                
                if action == 'cooldown':
                    remaining = validation.get('remaining_seconds', 0)
                    logger.info(f"[{mahasiswa['name']}] Ditolak: masih dalam jeda {remaining} detik sejak check-in.")
                    self._set_qr_cooldown(qr_data)
                    continue

                # Ambil confidence tertinggi dari QR paper yang terdeteksi
                max_conf = max(p['confidence'] for p in qr_papers) if qr_papers else 0.0
                
                # Simpan snapshot
                snapshot = self.cameras[camera_id].save_snapshot(mahasiswa['id'])
                
                # Extract late info from validation
                is_late = validation.get('is_late', False)
                late_duration = validation.get('late_duration', 0)

                # Record attendance with late tracking
                att_result = self.db.record_attendance(
                    mahasiswa['id'], action, camera_id, snapshot, max_conf,
                    kegiatan_id=None,  # For daily attendance, kegiatan_id is None
                    is_late=is_late,
                    late_duration=late_duration
                )

                # Set cooldown
                self._set_qr_cooldown(qr_data)
                self.db.update_camera_seen(camera_id)

                result['qr_scanned'] = qr_data
                result['attendance'] = {
                    'mahasiswa': mahasiswa,
                    'action': action,
                    'result': att_result,
                    'confidence': max_conf,
                    'is_late': is_late,
                    'late_duration': late_duration,
                    'validation_message': validation.get('message', '')
                }

                log_msg = f"[{camera_id}] {mahasiswa['name']} — {action.upper()} | QR Conf: {max_conf:.2%}"
                if is_late:
                    log_msg += f" | TELAT {late_duration} menit"
                logger.info(log_msg)
                break  # Proses satu QR per frame

        # Draw detections pada frame
        display_frame = self.yolo.draw_detections(frame, qr_papers, qr_decoded)
        with self._lock:
            self.latest_frames[camera_id] = display_frame

        return result

    def run_continuous(self, camera_id: str, callback=None):
        cam = self.cameras.get(camera_id)
        if not cam:
            raise ValueError(f"Kamera {camera_id} tidak ditemukan.")

        logger.info(f"[{camera_id}] Memulai continuous processing...")
        # Hitung frame interval berdasarkan target FPS
        # Untuk 30 FPS = 1/30 = 0.033 detik per frame
        frame_interval = 1.0 / FRAME_FPS

        while True:
            ret, frame = cam.get_frame()
            if not ret:
                time.sleep(0.5)
                continue

            result = self.process_frame(camera_id, frame)

            if result.get('attendance') and callback:
                callback(result)

            time.sleep(frame_interval)

    def start_all(self, callback=None):
        for cam_id in self.cameras:
            t = threading.Thread(
                target=self.run_continuous,
                args=(cam_id, callback),
                daemon=True,
                name=f"processor-{cam_id}"
            )
            t.start()
            logger.info(f"[{cam_id}] Processing thread dimulai.")

    def stop_all(self):
        for cam in self.cameras.values():
            cam.stop()

def create_system() -> tuple[DatabaseManager, YOLOProcessor, AttendanceProcessor]:
    db = DatabaseManager()
    yolo = YOLOProcessor(MODEL_PATH)
    processor = AttendanceProcessor(db, yolo)
    return db, yolo, processor

if __name__ == '__main__':
    print("="*60)
    print("  SISTEM ABSENSI QR CODE PAPER + YOLO + WEBCAM")
    print("  Mode: Deteksi QR Code Paper (Custom YOLO Model)")
    print("="*60)
    print("\n⚠️  CATATAN PENTING:")
    print("  - Model YOLO default (yolov8n.pt) belum dilatih untuk QR paper")
    print("  - Silakan latih model custom dengan dataset QR code paper")
    print("  - Setelah training, ganti model di models/yolov8n.pt")
    print("  - Update qr_paper_class_id di YOLOProcessor sesuai hasil training")
    print("="*60 + "\n")

    db, yolo, processor = create_system()

    qr_id = db.add_mahasiswa('MHS001', 'Budi Santoso', 'A', 'Teknik Informatika')
    db.add_mahasiswa('MHS002', 'Siti Rahayu', 'B', 'Sistem Informasi')

    # Webcam (built-in atau USB)
    # Index 0 = Webcam built-in laptop
    # Index 1, 2, 3 = USB webcam eksternal
    processor.add_camera(
        camera_id='CAM-01',
        camera_index=0,  # 0 = built-in webcam
        name='Webcam Built-in',
        location='Lobby Lantai 1'
    )

    def on_attendance(result):
        att = result['attendance']
        mhs = att['mahasiswa']
        action = "MASUK" if att['action'] == 'check_in' else "KELUAR"
        print(f"\n✅ ABSEN {action}: {mhs['name']} ({mhs['kompi']}) "
              f"— QR Conf: {att['confidence']:.1%} @ {result['camera_id']}")

    processor.start_all(callback=on_attendance)
    print("\nSistem berjalan. Tekan Ctrl+C untuk berhenti.\n")

    try:
        while True:
            time.sleep(10)
    except KeyboardInterrupt:
        print("\n\nMenghentikan sistem...")
        processor.stop_all()