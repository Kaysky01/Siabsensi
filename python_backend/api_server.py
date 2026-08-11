"""
SIABSEN Python Backend for Laravel
Simplified API server for YOLO detection and camera processing
"""

import sys
import os
from pathlib import Path
import logging

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent))

# Setup logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s [%(levelname)s] %(name)s: %(message)s')
logger = logging.getLogger(__name__)

from flask import Flask, Response, jsonify, request, render_template
from flask_cors import CORS
import cv2
import numpy as np
import base64
import threading
import time
from datetime import datetime

# Import from app modules
from app.attendance_engine import create_system, AttendanceProcessor
from app.database_manager import DatabaseManager
from app.config_db import MYSQL_CONFIG, YOLO_SETTINGS, RTSP_SETTINGS, reload_settings

app = Flask(__name__, static_folder='static', template_folder='templates')
CORS(app)

# Initialize system (try to connect to database, but allow running without it)
try:
    db, yolo_processor, processor = create_system()
    # Extract the YOLO model from the processor for direct use in detect endpoint
    yolo = yolo_processor.model
    print("✓ Database connected successfully")
except Exception as e:
    print(f"⚠ Database connection failed: {e}")
    print("⚠ Running without database - QR detection will work but attendance recording won't")
    db = None
    # Load YOLO model only for detection
    from ultralytics import YOLO
    yolo = YOLO(str(YOLO_SETTINGS.get('model_path', 'models/yolov8n.pt')))
    processor = None

# Camera stream thread
camera_thread = None
camera_running = False

# In-memory attendance tracking (kegiatan_id -> {mahasiswa_id -> {'check_in': time, 'check_out': time}})
# Ini digunakan agar Python bisa ingat status tanpa menulis ke DB
local_attendance_state = {}

def get_local_action(mahasiswa_id, kegiatan_id):
    """Tentukan action berdasarkan in-memory state (bukan database).
    
    Returns: 
    - 'check_in', 'check_out', 'cooldown', 'already_checked_out' (success cases)
    - tuple ('rejected', reason, message) for rejection cases
    - tuple ('check_in', is_late, late_duration) for check_in with late info
    """
    # For kegiatan-based attendance, bypass schedule validation
    if kegiatan_id:
        key = str(kegiatan_id)
        
        if key not in local_attendance_state:
            local_attendance_state[key] = {}
        
        session = local_attendance_state[key]
        
        if mahasiswa_id not in session:
            # Belum ada record → check_in
            session[mahasiswa_id] = {'check_in': datetime.now(), 'check_out': None}
            return 'check_in'
        
        record = session[mahasiswa_id]
        
        if record['check_in'] and not record['check_out']:
            # Sudah check_in, belum check_out → cek cooldown
            elapsed = (datetime.now() - record['check_in']).total_seconds()
            cooldown_seconds = YOLO_SETTINGS.get('qr_cooldown', 30)
            if elapsed < cooldown_seconds:
                return 'cooldown'
            # Sudah lewat cooldown → check_out
            record['check_out'] = datetime.now()
            return 'check_out'
        
        if record['check_in'] and record['check_out']:
            # Sudah selesai (masuk & keluar)
            return 'already_checked_out'
        
        return 'check_in'
    
    # Continue with normal local state logic
    key = 'default'
    
    if key not in local_attendance_state:
        local_attendance_state[key] = {}
    
    session = local_attendance_state[key]
    
    # Check if user already has a record today
    if mahasiswa_id not in session:
        # Belum ada record → need check_in
        # Validate check-in time against schedule ONLY for check-in
        if db:
            schedule = db.get_today_schedule()
            if not schedule:
                logger.warning(f"[{mahasiswa_id}] No schedule configured for today (local mode)")
                return ('rejected', 'no_schedule', 'Tidak ada jadwal absensi untuk hari ini')
            
            # Validate check-in time against schedule
            from app.timezone_utils import get_current_time
            from app.time_validator import TimeValidator
            
            time_validator = TimeValidator(db)
            validation_result = time_validator.validate_check_in(get_current_time(), schedule)
            
            if not validation_result.get('allowed', False):
                reason = validation_result.get('reason', 'unknown')
                message = validation_result.get('message', 'Waktu absensi tidak valid')
                logger.warning(f"[{mahasiswa_id}] Check-in rejected in local mode: {reason} - {message}")
                
                # Return specific rejection message based on reason
                if reason == 'too_late':
                    return ('rejected', 'too_late', 'Absensi sudah ditutup')
                elif reason == 'too_early':
                    return ('rejected', 'too_early', message)
                else:
                    return ('rejected', reason, message)
            
            # Check if late (allowed but late)
            is_late = validation_result.get('is_late', False)
            late_duration = validation_result.get('late_duration', 0)
        else:
            # No database - assume not late
            is_late = False
            late_duration = 0
        
        # Create new check-in record
        session[mahasiswa_id] = {
            'check_in': datetime.now(), 
            'check_out': None,
            'is_late': is_late,
            'late_duration': late_duration
        }
        # Return tuple with late info for check_in
        return ('check_in', is_late, late_duration)
    
    # User already has a record - check status
    record = session[mahasiswa_id]
    
    if record['check_in'] and not record['check_out']:
        # Sudah check_in, belum check_out → validate check-out time
        # First check cooldown
        elapsed = (datetime.now() - record['check_in']).total_seconds()
        cooldown_seconds = YOLO_SETTINGS.get('qr_cooldown', 30)
        if elapsed < cooldown_seconds:
            return 'cooldown'
        
        # Cooldown passed - now validate check-out time against schedule
        if db:
            schedule = db.get_today_schedule()
            if not schedule:
                logger.warning(f"[{mahasiswa_id}] No schedule configured for check-out (local mode)")
                return ('rejected', 'no_schedule', 'Tidak ada jadwal absensi untuk hari ini')
            
            # Validate check-out time against schedule
            from app.timezone_utils import get_current_time
            from app.time_validator import TimeValidator
            
            time_validator = TimeValidator(db)
            validation_result = time_validator.validate_check_out(get_current_time(), schedule)
            
            if not validation_result.get('allowed', False):
                reason = validation_result.get('reason', 'unknown')
                message = validation_result.get('message', 'Waktu check-out tidak valid')
                logger.warning(f"[{mahasiswa_id}] Check-out rejected in local mode: {reason} - {message}")
                
                # Return specific rejection message based on reason
                return ('rejected', reason, message)
        
        # Validation passed - perform check-out
        record['check_out'] = datetime.now()
        return 'check_out'
    
    if record['check_in'] and record['check_out']:
        # Sudah selesai (masuk & keluar)
        return 'already_checked_out'
    
    return 'check_in'

def reset_local_attendance(kegiatan_id=None):
    """Reset in-memory state (dipanggil saat clear data atau ganti kegiatan)"""
    global local_attendance_state
    if kegiatan_id:
        key = str(kegiatan_id)
        local_attendance_state.pop(key, None)
    else:
        local_attendance_state = {}

@app.route('/monitor', methods=['GET'])
def monitor_view():
    """Serve the local monitor frontend"""
    return render_template('monitor.html')

@app.route('/scanner-test', methods=['GET'])
def scanner_test_view():
    """Serve the local scanner test page"""
    return render_template('index.html')

@app.route('/cctv', methods=['GET'])
def cctv_view():
    """Serve the fullscreen CCTV frontend"""
    return render_template('cctv.html')

@app.route('/api/python/status', methods=['GET'])
def status():
    """Check if Python backend is running"""
    return jsonify({
        'success': True,
        'status': 'running',
        'yolo_model': str(YOLO_SETTINGS.get('model_path')),
        'confidence': YOLO_SETTINGS.get('confidence'),
        'rtsp_settings': RTSP_SETTINGS
    })

@app.route('/api/python/reload-settings', methods=['POST'])
def reload_settings_endpoint():
    """Reload YOLO and RTSP settings from JSON files"""
    try:
        settings = reload_settings()
        return jsonify({
            'success': True,
            'message': 'Settings reloaded successfully',
            'settings': settings
        })
    except Exception as e:
        return jsonify({
            'success': False,
            'message': f'Failed to reload settings: {str(e)}'
        }), 500

@app.route('/api/python/stream/<camera_id>', methods=['GET'])
def stream_camera(camera_id):
    """Stream camera with YOLO detection"""
    def generate_frames():
        camera = cv2.VideoCapture(int(camera_id) if camera_id.isdigit() else camera_id)
        
        try:
            while True:
                success, frame = camera.read()
                if not success:
                    break
                
                # YOLO detection here
                results = yolo(frame, conf=YOLO_SETTINGS.get('confidence', 0.45))
                
                # Draw bounding boxes
                for result in results:
                    for box in result.boxes:
                        x1, y1, x2, y2 = box.xyxy[0]
                        cv2.rectangle(frame, (int(x1), int(y1)), (int(x2), int(y2)), (0, 255, 0), 2)
                
                ret, buffer = cv2.imencode('.jpg', frame)
                frame_bytes = buffer.tobytes()
                
                yield (b'--frame\r\n'
                       b'Content-Type: image/jpeg\r\n\r\n' + frame_bytes + b'\r\n')
        finally:
            camera.release()
    
    return Response(generate_frames(), mimetype='multipart/x-mixed-replace; boundary=frame')

@app.route('/api/python/detect', methods=['POST'])
def detect_qr():
    """Detect QR code from image"""
    try:
        data = request.get_json(silent=True) or {}
        if not isinstance(data, dict):
            data = {}
        image_data = data.get('image')

        if not image_data:
            response = jsonify({'success': False, 'message': 'No image data'})
            response.headers.add('Access-Control-Allow-Origin', '*')
            return response, 400

        # Decode base64 image
        try:
            # Handle data URL format (data:image/jpeg;base64,...)
            if ',' in image_data:
                image_bytes = base64.b64decode(image_data.split(',')[1])
            else:
                image_bytes = base64.b64decode(image_data)

            if len(image_bytes) == 0:
                response = jsonify({'success': False, 'message': 'Empty image data after decode'})
                response.headers.add('Access-Control-Allow-Origin', '*')
                return response, 400

            nparr = np.frombuffer(image_bytes, np.uint8)
            frame = cv2.imdecode(nparr, cv2.IMREAD_COLOR)

            if frame is None:
                response = jsonify({'success': False, 'message': 'Failed to decode image'})
                response.headers.add('Access-Control-Allow-Origin', '*')
                return response, 400
        except Exception as decode_error:
            response = jsonify({'success': False, 'message': f'Image decode error: {str(decode_error)}'})
            response.headers.add('Access-Control-Allow-Origin', '*')
            return response, 400

        # YOLO detection for QR code papers
        try:
            yolo_results = yolo(frame, conf=YOLO_SETTINGS.get('confidence', 0.45), verbose=False)
            qr_papers = []
            max_confidence = 0.0
            for result in yolo_results:
                for box in result.boxes:
                    x1, y1, x2, y2 = box.xyxy[0]
                    conf = float(box.conf[0])
                    max_confidence = max(max_confidence, conf)
                    qr_papers.append({
                        'bbox': (int(x1), int(y1), int(x2), int(y2)),
                        'confidence': conf
                    })
            logger.info(f"YOLO detected {len(qr_papers)} QR papers, max confidence: {max_confidence}")
        except Exception as yolo_error:
            logger.error(f"YOLO detection failed: {yolo_error}")
            qr_papers = []
            max_confidence = 0.0

        # QR detection using pyzbar - ONLY within YOLO-detected ROIs
        from pyzbar.pyzbar import decode as qr_decode

        # Only decode QR codes within YOLO-detected ROIs
        # If YOLO detected no QR papers, return empty results
        if qr_papers:
            logger.info("Using YOLO-detected ROIs for QR decoding")
            decoded_objects = []
            for qr_paper in qr_papers:
                x1, y1, x2, y2 = qr_paper['bbox']
                roi = frame[y1:y2, x1:x2]
                if roi.size > 0:
                    roi_decoded = qr_decode(roi)
                    # Adjust coordinates to full frame
                    for obj in roi_decoded:
                        decoded_objects.append(obj)
        else:
            # YOLO-only mode: no fallback to pyzbar
            logger.info("YOLO detected no QR papers - returning empty results (YOLO-only mode)")
            decoded_objects = []

        # Build results from decoded objects
        results = []
        for obj in decoded_objects:
            try:
                qr_text = obj.data.decode('utf-8')
            except UnicodeDecodeError:
                qr_text = obj.data.decode('latin-1', errors='ignore')

            results.append({
                'data': qr_text,
                'type': str(obj.type),
                'rect': {
                    'left': obj.rect.left,
                    'top': obj.rect.top,
                    'width': obj.rect.width,
                    'height': obj.rect.height
                }
            })

        return jsonify({
            'success': True,
            'results': results,
            'yolo_detections': len(qr_papers),
            'max_confidence': max_confidence
        })
    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500

@app.route('/api/python/attendance', methods=['POST'])
def record_attendance():
    """Lookup mahasiswa & determine action — TIDAK menulis ke DB.
    
    Penulisan ke DB hanya dilakukan saat user menekan 'Sync ke Server'
    yang memanggil Laravel /api/sync endpoint.
    """
    try:
        data = request.json
        qr_code_id = data.get('mahasiswa_id')  # This is actually the QR code ID
        confidence = data.get('confidence', 0.0)
        kegiatan_id = data.get('kegiatan_id', None)

        from datetime import datetime
        now = datetime.now()
        time_str = now.strftime('%H:%M:%S')

        if db is None:
            # === MODE LOKAL TANPA DATABASE ===
            return jsonify({
                'success': True,
                'message': 'Disimpan di lokal (menunggu sync)',
                'result': {'status': 'checked_in', 'time': time_str, 'is_late': False, 'late_duration': 0},
                'mahasiswa': {
                    'id': qr_code_id,
                    'name': 'Mahasiswa (' + str(qr_code_id) + ')',
                    'kompi': 'Local',
                    'jurusan': '-'
                }
            })

        # === LOOKUP ONLY MODE ===
        # Cari mahasiswa di database (READ-ONLY)
        mahasiswa = db.get_mahasiswa_by_qr(qr_code_id)
        if not mahasiswa:
            return jsonify({
                'success': False,
                'message': 'Mahasiswa tidak ditemukan dengan QR code ini',
                'show_alert': True,
                'alert_type': 'error',
                'alert_title': 'QR Code Tidak Dikenali',
                'alert_text': f'QR Code "{qr_code_id}" tidak terdaftar di sistem.'
            }), 404

        # Cek apakah mahasiswa aktif
        if not mahasiswa.get('is_active'):
            return jsonify({
                'success': False,
                'message': f"Mahasiswa '{mahasiswa.get('name')}' tidak aktif.",
                'show_alert': True,
                'alert_type': 'warning',
                'alert_title': 'Mahasiswa Tidak Aktif',
                'alert_text': f"{mahasiswa.get('name')} tidak aktif. Silakan hubungi Administrator."
            }), 403

        actual_mahasiswa_id = mahasiswa['id']

        if db is not None and processor is not None:
            # === DATABASE MODE: Write directly to Local MySQL ===
            action, validation = processor._determine_action(actual_mahasiswa_id, kegiatan_id)
            
            if action == 'special_status':
                st = validation.get('reason', 'sakit').upper()
                return jsonify({
                    'success': True,
                    'message': f'Mahasiswa terdaftar {st} hari ini',
                    'result': {'status': validation.get('reason', 'sakit')},
                    'mahasiswa': {
                        'id': mahasiswa['id'],
                        'name': mahasiswa['name'],
                        'kompi': mahasiswa['kompi']
                    },
                    'show_alert': True,
                    'alert_type': 'info',
                    'alert_title': f'TERDAFTAR {st}',
                    'alert_text': f'{mahasiswa["name"]} sudah terdaftar {st} hari ini.'
                })
            
            # 1. Handle Rejections (when validation exists and allowed is False)
            if action in ['check_in', 'check_out'] and not validation.get('allowed', False):
                reason = validation.get('reason', 'unknown')
                message = validation.get('message', 'Waktu absensi tidak valid')
                
                alert_config = {
                    'show_alert': True,
                    'alert_type': 'error',
                    'alert_title': 'Absensi Ditolak',
                    'alert_text': message
                }
                
                if reason == 'no_schedule':
                    alert_config.update({
                        'alert_type': 'warning',
                        'alert_title': 'Tidak Ada Jadwal',
                        'alert_text': f'Tidak ada jadwal absensi untuk hari ini.\n\n{mahasiswa.get("name")} tidak dapat melakukan absensi.'
                    })
                elif reason == 'too_early':
                    if action == 'check_out':
                        alert_config.update({
                            'alert_type': 'info',
                            'alert_title': 'Sudah Absen Masuk',
                            'alert_text': f'{mahasiswa.get("name")} sudah absen masuk hari ini.\n\n{message}'
                        })
                    else:
                        alert_config.update({
                            'alert_type': 'warning',
                            'alert_title': 'Absen Masuk Belum Dibuka',
                            'alert_text': f'{message}\n\n{mahasiswa.get("name")} belum bisa absen saat ini.'
                        })
                elif reason == 'not_checked_in':
                    alert_config.update({
                        'alert_type': 'warning',
                        'alert_title': 'Pagi Belum Absen',
                        'alert_text': f'{mahasiswa.get("name")} belum melakukan absensi masuk pagi hari ini.\n\n{message}'
                    })
                elif reason == 'too_late':
                    alert_config.update({
                        'alert_title': 'Absensi Ditutup',
                        'alert_text': f'Waktu absensi hari ini sudah ditutup.\n\n{mahasiswa.get("name")} tidak dapat melakukan absensi lagi.'
                    })
                
                return jsonify({
                    'success': False,
                    'message': message,
                    'reason': reason,
                    'mahasiswa': {
                        'name': mahasiswa['name'],
                        'kompi': mahasiswa['kompi']
                    },
                    **alert_config
                }), 403

            # 2. Handle Cooldown
            if action == 'cooldown':
                remaining = validation.get('remaining_seconds', 0)
                return jsonify({
                    'success': True,
                    'message': 'Attendance ignored (cooldown)',
                    'result': {'status': 'cooldown'},
                    'mahasiswa': {
                        'id': mahasiswa['id'],
                        'name': mahasiswa['name'],
                        'kompi': mahasiswa['kompi']
                    },
                    'show_alert': True,
                    'alert_type': 'info',
                    'alert_title': 'Belum Bisa Check-out',
                    'alert_text': f'{mahasiswa["name"]} masih dalam masa tunggu.\n\nSilakan tunggu {remaining} detik lagi untuk check-out.'
                })

            # 3. Handle Already Completed Attendance
            if action == 'none':
                return jsonify({
                    'success': True,
                    'message': 'Sudah selesai absen (masuk & keluar) untuk sesi ini',
                    'result': {'status': 'already_checked_out'},
                    'mahasiswa': {
                        'id': mahasiswa['id'],
                        'name': mahasiswa['name'],
                        'kompi': mahasiswa['kompi']
                    },
                    'show_alert': True,
                    'alert_type': 'info',
                    'alert_title': 'Sudah Lengkap',
                    'alert_text': f'{mahasiswa["name"]} sudah menyelesaikan absensi hari ini (masuk & keluar).'
                })

            # 4. Action is allowed: write directly to local MySQL
            is_late = validation.get('is_late', False)
            late_duration = validation.get('late_duration', 0)

            att_result = db.record_attendance(
                mahasiswa_id=actual_mahasiswa_id,
                action=action,
                camera_id='WEB-SCANNER',
                snapshot_path=None,
                confidence=confidence,
                kegiatan_id=kegiatan_id,
                is_late=is_late,
                late_duration=late_duration
            )

            # Map the returned status
            db_status = att_result.get('status') if att_result else None
            
            if db_status == 'already_checked_in':
                return jsonify({
                    'success': True,
                    'message': f'{mahasiswa["name"]} sudah absen masuk hari ini.',
                    'result': {'status': 'already_checked_in', 'time': att_result.get('time')},
                    'mahasiswa': {
                        'id': mahasiswa['id'],
                        'name': mahasiswa['name'],
                        'kompi': mahasiswa['kompi']
                    },
                    'show_alert': True,
                    'alert_type': 'info',
                    'alert_title': 'Sudah Absen Masuk',
                    'alert_text': f'{mahasiswa["name"]} sudah absen masuk pada pukul {att_result.get("time")}.'
                })

            if db_status == 'already_checked_out':
                return jsonify({
                    'success': True,
                    'message': f'{mahasiswa["name"]} sudah selesai absen hari ini (masuk & keluar).',
                    'result': {'status': 'already_checked_out', 'time': att_result.get('time')},
                    'mahasiswa': {
                        'id': mahasiswa['id'],
                        'name': mahasiswa['name'],
                        'kompi': mahasiswa['kompi']
                    },
                    'show_alert': True,
                    'alert_type': 'info',
                    'alert_title': 'Sudah Lengkap',
                    'alert_text': f'{mahasiswa["name"]} sudah menyelesaikan absensi hari ini (masuk & keluar).'
                })

            if db_status == 'not_checked_in':
                return jsonify({
                    'success': False,
                    'message': f'{mahasiswa["name"]} belum absen masuk!',
                    'result': {'status': 'not_checked_in'},
                    'mahasiswa': {
                        'id': mahasiswa['id'],
                        'name': mahasiswa['name'],
                        'kompi': mahasiswa['kompi']
                    },
                    'show_alert': True,
                    'alert_type': 'warning',
                    'alert_title': 'Belum Absen Masuk',
                    'alert_text': f'{mahasiswa["name"]} belum melakukan absensi masuk hari ini. Silakan absen masuk terlebih dahulu.'
                }), 400

            # Normal success: checked_in or checked_out
            status_string = 'checked_in' if action == 'check_in' else 'checked_out'
            
            response_data = {
                'success': True,
                'message': 'Attendance recorded successfully in local DB',
                'result': {
                    'status': status_string,
                    'time': time_str,
                    'is_late': is_late,
                    'late_duration': late_duration
                },
                'mahasiswa': {
                    'id': mahasiswa['id'],
                    'name': mahasiswa['name'],
                    'kompi': mahasiswa['kompi'],
                    'jurusan': mahasiswa['jurusan']
                }
            }

            # Add warning alert if late
            if action == 'check_in' and is_late:
                response_data.update({
                    'show_alert': True,
                    'alert_type': 'warning',
                    'alert_title': '⚠️ Terlambat!',
                    'alert_text': f'{mahasiswa["name"]} terlambat {late_duration} menit.\n\nAbsensi masuk tetap dicatat.'
                })

            return jsonify(response_data)

        else:
            # === FALLBACK MODE: Local mode without database ===
            action = get_local_action(actual_mahasiswa_id, kegiatan_id)
            
            # Handle rejection (tuple response)
            if isinstance(action, tuple) and action[0] == 'rejected':
                _, reason, message = action
                
                # Customize alert based on rejection reason
                alert_config = {
                    'show_alert': True,
                    'alert_type': 'error',
                    'alert_title': 'Absensi Ditolak',
                    'alert_text': message
                }
                
                if reason == 'no_schedule':
                    alert_config.update({
                        'alert_type': 'warning',
                        'alert_title': 'Tidak Ada Jadwal',
                        'alert_text': f'Tidak ada jadwal absensi untuk hari ini.\n\n{mahasiswa.get("name")} tidak dapat melakukan absensi.'
                    })
                elif reason == 'too_early':
                    if action == 'check_out':
                        alert_config.update({
                            'alert_type': 'info',
                            'alert_title': 'Sudah Absen Masuk',
                            'alert_text': f'{mahasiswa.get("name")} sudah absen masuk hari ini.\n\n{message}'
                        })
                    else:
                        alert_config.update({
                            'alert_type': 'warning',
                            'alert_title': 'Absen Masuk Belum Dibuka',
                            'alert_text': f'{message}\n\n{mahasiswa.get("name")} belum bisa absen saat ini.'
                        })
                elif reason == 'not_checked_in':
                    alert_config.update({
                        'alert_type': 'warning',
                        'alert_title': 'Pagi Belum Absen',
                        'alert_text': f'{mahasiswa.get("name")} belum melakukan absensi masuk pagi hari ini.\n\n{message}'
                    })
                elif reason == 'too_late':
                    alert_config.update({
                        'alert_title': 'Absensi Ditutup',
                        'alert_text': f'Waktu absensi hari ini sudah ditutup.\n\n{mahasiswa.get("name")} tidak dapat melakukan absensi lagi.'
                    })
                
                return jsonify({
                    'success': False,
                    'message': message,
                    'reason': reason,
                    'mahasiswa': {
                        'name': mahasiswa['name'],
                        'kompi': mahasiswa['kompi']
                    },
                    **alert_config
                }), 403
            
            # Handle check_in with late info (tuple response)
            if isinstance(action, tuple) and action[0] == 'check_in':
                _, is_late, late_duration = action
                
                response_data = {
                    'success': True,
                    'message': 'Attendance recorded',
                    'result': {
                        'status': 'checked_in', 
                        'time': time_str,
                        'is_late': is_late,
                        'late_duration': late_duration
                    },
                    'mahasiswa': {
                        'id': mahasiswa['id'],
                        'name': mahasiswa['name'],
                        'kompi': mahasiswa['kompi'],
                        'jurusan': mahasiswa['jurusan']
                    }
                }
                
                # Add alert if late
                if is_late:
                    response_data.update({
                        'show_alert': True,
                        'alert_type': 'warning',
                        'alert_title': '⚠️ Terlambat!',
                        'alert_text': f'{mahasiswa["name"]} terlambat {late_duration} menit.\n\nAbsensi masuk tetap dicatat.'
                    })
                
                return jsonify(response_data)
            
            # Handle old string responses for backward compatibility
            if action == 'no_schedule':
                return jsonify({
                    'success': False,
                    'message': 'Tidak ada jadwal absensi untuk hari ini',
                    'show_alert': True,
                    'alert_type': 'warning',
                    'alert_title': 'Tidak Ada Jadwal',
                    'alert_text': f'Tidak ada jadwal absensi untuk hari ini.\n\n{mahasiswa.get("name")} tidak dapat melakukan absensi.',
                    'mahasiswa': {
                        'name': mahasiswa['name'],
                        'kompi': mahasiswa['kompi']
                    }
                }), 403
            
            if action == 'cooldown':
                # Get remaining cooldown time
                key = 'default'
                if key in local_attendance_state and actual_mahasiswa_id in local_attendance_state[key]:
                    record = local_attendance_state[key][actual_mahasiswa_id]
                    elapsed = (datetime.now() - record['check_in']).total_seconds()
                    remaining = YOLO_SETTINGS.get('qr_cooldown', 30) - elapsed
                    remaining_sec = max(0, int(remaining))
                else:
                    remaining_sec = 0
                
                return jsonify({
                    'success': True,
                    'message': f'Attendance ignored (cooldown)',
                    'result': {'status': 'cooldown'},
                    'mahasiswa': {
                        'id': mahasiswa['id'],
                        'name': mahasiswa['name'],
                        'kompi': mahasiswa['kompi']
                    },
                    'show_alert': True,
                    'alert_type': 'info',
                    'alert_title': 'Belum Bisa Check-out',
                    'alert_text': f'{mahasiswa["name"]} masih dalam masa tunggu.\n\nSilakan tunggu {remaining_sec} detik lagi untuk check-out.'
                })
            
            if action == 'already_checked_out':
                return jsonify({
                    'success': True,
                    'message': f'Sudah selesai absen (masuk & keluar) untuk sesi ini',
                    'result': {'status': 'already_checked_out'},
                    'mahasiswa': {
                        'id': mahasiswa['id'],
                        'name': mahasiswa['name'],
                        'kompi': mahasiswa['kompi']
                    },
                    'show_alert': True,
                    'alert_type': 'info',
                    'alert_title': 'Sudah Lengkap',
                    'alert_text': f'{mahasiswa["name"]} sudah menyelesaikan absensi hari ini (masuk & keluar).'
                })

            # action = 'check_in' atau 'check_out'

            # Map action to status that frontend expects ('checked_in', 'checked_out')
            status_string = action
            if action == 'check_in':
                status_string = 'checked_in'
            elif action == 'check_out':
                status_string = 'checked_out'

            return jsonify({
                'success': True,
                'message': 'Attendance recorded',
                'result': {'status': status_string, 'time': time_str, 'is_late': False, 'late_duration': 0},
                'mahasiswa': {
                    'id': mahasiswa['id'],
                    'name': mahasiswa['name'],
                    'kompi': mahasiswa['kompi'],
                    'jurusan': mahasiswa['jurusan']
                }
            })
    except Exception as e:
        logger.error(f"Attendance error: {e}")
        return jsonify({
            'success': False, 
            'message': str(e),
            'show_alert': True,
            'alert_type': 'error',
            'alert_title': 'Error',
            'alert_text': f'Terjadi kesalahan: {str(e)}'
        }), 500

@app.route('/api/python/attendance/today', methods=['GET'])
def get_today_attendance():
    """Ambil data attendance hari ini (atau tanggal aktif terbaru) dari local DB untuk ditampilkan di monitor"""
    try:
        if db is None:
            return jsonify({'success': True, 'data': [], 'source': 'no_db'})

        today = datetime.now().strftime('%Y-%m-%d')
        kegiatan_id = request.args.get('kegiatan_id', None)
        date_param = request.args.get('date', None)

        if date_param:
            target_date = date_param
        else:
            target_date = today
            # Jika hari ini tidak ada data absensi, gunakan tanggal absensi terbaru yang ada di database
            if kegiatan_id:
                count_res = db._execute("SELECT COUNT(*) as c FROM attendance WHERE kegiatan_id = %s AND date = %s", (kegiatan_id, today), fetch_one=True)
                if not count_res or count_res.get('c', 0) == 0:
                    max_d = db._execute("SELECT MAX(date) as max_date FROM attendance WHERE kegiatan_id = %s", (kegiatan_id,), fetch_one=True)
                    if max_d and max_d.get('max_date'):
                        target_date = str(max_d['max_date'])
            else:
                count_res = db._execute("SELECT COUNT(*) as c FROM attendance WHERE date = %s AND kegiatan_id IS NULL", (today,), fetch_one=True)
                if not count_res or count_res.get('c', 0) == 0:
                    max_d = db._execute("SELECT MAX(date) as max_date FROM attendance WHERE kegiatan_id IS NULL", fetch_one=True)
                    if max_d and max_d.get('max_date'):
                        target_date = str(max_d['max_date'])

        if kegiatan_id:
            rows = db._execute("""
                SELECT a.id, a.mahasiswa_id, a.check_in, a.check_out, a.status,
                       a.date, a.is_late, a.late_duration, a.kegiatan_id, a.is_synced,
                       COALESCE(m.name, a.mahasiswa_id) as name,
                       COALESCE(m.kompi, '-') as kompi,
                       COALESCE(m.jurusan, '-') as jurusan
                FROM attendance a
                LEFT JOIN mahasiswa m ON a.mahasiswa_id = m.id
                WHERE a.kegiatan_id = %s AND a.date = %s
                ORDER BY a.id DESC
            """, (kegiatan_id, target_date), fetch_all=True)
        else:
            rows = db._execute("""
                SELECT a.id, a.mahasiswa_id, a.check_in, a.check_out, a.status,
                       a.date, a.is_late, a.late_duration, a.kegiatan_id, a.is_synced,
                       COALESCE(m.name, a.mahasiswa_id) as name,
                       COALESCE(m.kompi, '-') as kompi,
                       COALESCE(m.jurusan, '-') as jurusan
                FROM attendance a
                LEFT JOIN mahasiswa m ON a.mahasiswa_id = m.id
                WHERE a.date = %s AND a.kegiatan_id IS NULL
                ORDER BY a.id DESC
            """, (target_date,), fetch_all=True)

        def fmt_time(val):
            if val is None:
                return None
            from datetime import timedelta
            if isinstance(val, timedelta):
                total = int(val.total_seconds())
                return f"{total//3600:02d}:{(total%3600)//60:02d}:{total%60:02d}"
            if hasattr(val, 'strftime'):
                return val.strftime('%H:%M:%S')
            return str(val)

        result = []
        for r in (rows or []):
            result.append({
                'id': 'db_' + str(r['id']),
                'mahasiswa_id': r['mahasiswa_id'],
                'name': r['name'],
                'kompi': r['kompi'],
                'jurusan': r.get('jurusan', ''),
                'kegiatan_id': r.get('kegiatan_id'),
                'check_in': fmt_time(r['check_in']),
                'check_out': fmt_time(r['check_out']),
                'status': r.get('status') or ('hadir' if r['check_in'] else 'alpha'),
                'date': str(r['date']) if r['date'] else target_date,
                'is_late': bool(r.get('is_late', False)),
                'late_duration': r.get('late_duration', 0),
                'synced': bool(r.get('is_synced', 0))  # status sync ke server
            })

        return jsonify({
            'success': True,
            'data': result,
            'source': 'local_db',
            'count': len(result),
            'date': target_date
        })

    except Exception as e:
        logger.error(f"Get today attendance error: {e}")
        return jsonify({'success': False, 'message': str(e), 'data': []}), 500


@app.route('/api/python/reset-state', methods=['POST'])
def reset_attendance_state():
    """Reset in-memory attendance tracking (dipanggil saat Clear Data atau ganti kegiatan)"""
    try:
        data = request.json or {}
        kegiatan_id = data.get('kegiatan_id', None)
        reset_local_attendance(kegiatan_id)
        
        return jsonify({
            'success': True,
            'message': 'In-memory attendance state telah di-reset (data local DB tetap dipertahankan)'
        })
    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500

@app.route('/api/python/process-video', methods=['POST'])
def process_video():
    try:
        data = request.get_json(silent=True) or {}
        video_path = data.get('video_path')
        action = data.get('action', 'check_in')

        if not video_path or not os.path.exists(video_path):
            return jsonify({'success': False, 'message': 'Video file not found'}), 400

        cap = cv2.VideoCapture(video_path)
        if not cap.isOpened():
            return jsonify({'success': False, 'message': 'Failed to open video file'}), 400

        total_frames = int(cap.get(cv2.CAP_PROP_FRAME_COUNT))
        fps = cap.get(cv2.CAP_PROP_FPS)
        process_interval = max(1, int(fps * 0.5))  # Process 2 frames per second

        local_db = processor.db if processor is not None else DatabaseManager()
        local_processor = processor if processor is not None else AttendanceProcessor(local_db, None)

        recorded = []
        skipped = []
        frame_idx = 0

        while True:
            ret, frame = cap.read()
            if not ret:
                break

            if frame_idx % process_interval == 0:
                try:
                    yolo_results = yolo(frame, conf=YOLO_SETTINGS.get('confidence', 0.45), verbose=False)
                    qr_papers = []
                    for r in yolo_results:
                        for box in r.boxes:
                            x1, y1, x2, y2 = box.xyxy[0]
                            qr_papers.append({
                                'bbox': (int(x1), int(y1), int(x2), int(y2)),
                                'confidence': float(box.conf[0])
                            })

                    if qr_papers:
                        from pyzbar.pyzbar import decode as qr_decode
                        for qp in qr_papers:
                            x1, y1, x2, y2 = qp['bbox']
                            roi = frame[y1:y2, x1:x2]
                            if roi.size > 0:
                                decoded = qr_decode(roi)
                                for obj in decoded:
                                    qr_data = obj.data.decode('utf-8')
                                    mahasiswa = local_db.get_mahasiswa_by_qr(qr_data)
                                    if not mahasiswa or not mahasiswa.get('is_active'):
                                        continue

                                    mhs_id = mahasiswa['id']
                                    det_action = local_processor._determine_action(mhs_id)
                                    if det_action in ['none', 'cooldown']:
                                        skipped.append({'name': mahasiswa['name'], 'reason': f'sudah {action}' if det_action == 'none' else 'masih cooldown'})
                                        continue

                                    if det_action != action:
                                        continue

                                    att_result = local_db.record_attendance(mhs_id, det_action, None, None, qp['confidence'])
                                    recorded.append({
                                        'name': mahasiswa['name'],
                                        'mahasiswa_id': mhs_id,
                                        'action': det_action,
                                        'confidence': qp['confidence']
                                    })
                except Exception as e:
                    logger.error(f"Frame {frame_idx}: {e}")

            frame_idx += 1

        cap.release()

        unique_mhs = len(set(r['mahasiswa_id'] for r in recorded))

        return jsonify({
            'success': True,
            'data': {
                'recorded_count': len(recorded),
                'skipped_count': len(skipped),
                'unique_mahasiswa': unique_mhs,
                'detections': recorded,
                'skipped_mahasiswa': skipped,
            }
        })

    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500

@app.route('/api/python/backup', methods=['POST'])
def backup_to_excel():
    """Backup local sync data to Excel file"""
    try:
        data = request.json.get('data', [])
        if not data:
            return jsonify({'success': False, 'message': 'No data provided'}), 400
            
        import pandas as pd
        import os
        from datetime import datetime
        
        # Format the data for Excel
        df = pd.DataFrame(data)
        
        # Ensure backups directory exists
        backup_dir = os.path.join(str(Path(__file__).parent), 'backups')
        os.makedirs(backup_dir, exist_ok=True)
        
        # Generate filename with timestamp
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        filename = f"absensi_backup_{timestamp}.xlsx"
        filepath = os.path.join(backup_dir, filename)
        
        # Save to Excel
        df.to_excel(filepath, index=False)
        
        return jsonify({
            'success': True,
            'message': f'Backup berhasil disimpan ke {filename}',
            'filename': filename
        })
    except Exception as e:
        logger.error(f"Backup failed: {e}")
        return jsonify({'success': False, 'message': f'Gagal backup: {str(e)}'}), 500

@app.route('/api/python/backup', methods=['DELETE'])
def clear_backup_files():
    """Hapus semua file backup excel lokal"""
    try:
        import os
        import glob
        
        backup_dir = os.path.join(str(Path(__file__).parent), 'backups')
        files = glob.glob(os.path.join(backup_dir, '*.xlsx'))
        
        deleted = 0
        for f in files:
            try:
                os.remove(f)
                deleted += 1
            except Exception as e:
                logger.error(f"Gagal menghapus file {f}: {e}")
                
        return jsonify({
            'success': True,
            'message': f'Berhasil menghapus {deleted} file backup'
        })
    except Exception as e:
        logger.error(f"Clear backups failed: {e}")
        return jsonify({'success': False, 'message': f'Gagal menghapus backup: {str(e)}'}), 500

@app.route('/api/python/kegiatan', methods=['GET'])
def get_kegiatan():
    """Mengambil daftar kegiatan aktif dari Laravel DB"""
    try:
        if db is None:
            return jsonify({'success': False, 'message': 'Database not connected'}), 500
        
        kegiatans = db.get_active_kegiatan()
        return jsonify({
            'success': True,
            'data': kegiatans
        })
    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500

def convert_timedelta_to_str(obj):
    """Convert timedelta objects to HH:MM:SS string format for JSON serialization"""
    from datetime import timedelta
    
    if obj is None:
        return None
    
    if isinstance(obj, timedelta):
        # Convert timedelta to HH:MM:SS format
        total_seconds = int(obj.total_seconds())
        hours = total_seconds // 3600
        minutes = (total_seconds % 3600) // 60
        seconds = total_seconds % 60
        return f"{hours:02d}:{minutes:02d}:{seconds:02d}"
    
    if isinstance(obj, dict):
        return {key: convert_timedelta_to_str(value) for key, value in obj.items()}
    
    if isinstance(obj, list):
        return [convert_timedelta_to_str(item) for item in obj]
    
    return obj

@app.route('/api/python/debug/schedule', methods=['GET'])
def debug_schedule():
    """Debug endpoint to check schedule"""
    try:
        if db is None:
            return jsonify({'success': False, 'message': 'Database not connected'}), 500
        
        from datetime import datetime
        today = datetime.now().strftime('%Y-%m-%d')
        
        # Get today's schedule
        schedule = db.get_today_schedule()
        
        # Get all schedules
        all_schedules = db.get_all_schedules()
        
        # Get grace period
        grace_period = db.get_grace_period_minutes()
        
        # Convert timedelta objects to strings
        schedule = convert_timedelta_to_str(schedule)
        all_schedules = convert_timedelta_to_str(all_schedules)
        
        return jsonify({
            'success': True,
            'today': today,
            'today_schedule': schedule,
            'all_schedules': all_schedules,
            'grace_period_minutes': grace_period
        })
    except Exception as e:
        logger.error(f"Debug schedule error: {e}")
        return jsonify({'success': False, 'message': str(e)}), 500

def _get_laravel_sync_service(req):
    """Helper untuk mendapatkan instance LaravelSyncService dengan re-import dinamis"""
    import importlib
    import app.laravel_sync
    importlib.reload(app.laravel_sync)
    from app.laravel_sync import LaravelSyncService

    laravel_url = req.args.get('laravel_url', None)
    if not laravel_url:
        laravel_url = 'https://pkkmb.polinela.ac.id'

    return LaravelSyncService(laravel_url, verify_ssl=False)


@app.route('/api/python/sync/test-connection', methods=['GET'])
def test_laravel_connection():
    """Test koneksi ke Laravel API"""
    try:
        sync_service = _get_laravel_sync_service(request)
        result = sync_service.test_connection()
        
        if result['success']:
            return jsonify(result)
        else:
            return jsonify(result), 500
            
    except Exception as e:
        logger.error(f"Test connection error: {e}")
        return jsonify({
            'success': False,
            'message': f'Error: {str(e)}'
        }), 500

@app.route('/api/python/sync/mahasiswa', methods=['GET'])
def sync_mahasiswa_from_laravel():
    """Sinkronisasi data mahasiswa dari Laravel API ke database lokal"""
    try:
        sync_service = _get_laravel_sync_service(request)
        
        # Fetch data from Laravel
        fetch_result = sync_service.fetch_mahasiswa()
        if not fetch_result['success']:
            return jsonify(fetch_result), 500
        
        # Sync to local database
        sync_result = sync_service.sync_mahasiswa_to_local(fetch_result['data'])
        
        return jsonify({
            'success': True,
            'message': f'Sinkronisasi mahasiswa berhasil',
            'stats': sync_result
        })
        
    except Exception as e:
        logger.error(f"Sync mahasiswa error: {e}")
        return jsonify({
            'success': False,
            'message': f'Error: {str(e)}'
        }), 500

@app.route('/api/python/sync/schedules', methods=['GET'])
def sync_schedules_from_laravel():
    """Sinkronisasi data jadwal PKKMB dari Laravel API ke database lokal"""
    try:
        sync_service = _get_laravel_sync_service(request)
        
        # Fetch data from Laravel
        fetch_result = sync_service.fetch_schedules()
        if not fetch_result['success']:
            return jsonify(fetch_result), 500
        
        # Sync to local database
        sync_result = sync_service.sync_schedules_to_local(fetch_result['data'])
        
        return jsonify({
            'success': True,
            'message': f'Sinkronisasi jadwal berhasil',
            'stats': sync_result
        })
        
    except Exception as e:
        logger.error(f"Sync schedules error: {e}")
        return jsonify({
            'success': False,
            'message': f'Error: {str(e)}'
        }), 500

@app.route('/api/python/sync/kegiatan', methods=['GET'])
def sync_kegiatan_from_laravel():
    """Sinkronisasi data kegiatan dari Laravel API ke database lokal"""
    try:
        sync_service = _get_laravel_sync_service(request)
        
        # Fetch data from Laravel
        fetch_result = sync_service.fetch_kegiatan()
        if not fetch_result['success']:
            return jsonify(fetch_result), 500
        
        # Sync to local database
        sync_result = sync_service.sync_kegiatan_to_local(fetch_result['data'])
        
        return jsonify({
            'success': True,
            'message': f'Sinkronisasi kegiatan berhasil',
            'stats': sync_result
        })
        
    except Exception as e:
        logger.error(f"Sync kegiatan error: {e}")
        return jsonify({
            'success': False,
            'message': f'Error: {str(e)}'
        }), 500

@app.route('/api/python/sync/system-config', methods=['GET'])
def sync_system_config_from_laravel():
    """Sinkronisasi system config (termasuk toleransi keterlambatan) dari Laravel API ke database lokal"""
    try:
        sync_service = _get_laravel_sync_service(request)
        
        # Fetch data from Laravel
        fetch_result = sync_service.fetch_system_config()
        if not fetch_result['success']:
            return jsonify(fetch_result), 500
        
        # Sync to local database
        sync_result = sync_service.sync_system_config_to_local(fetch_result['data'])
        
        return jsonify({
            'success': True,
            'message': 'Sinkronisasi toleransi keterlambatan & konfigurasi sistem berhasil',
            'stats': sync_result
        })
        
    except Exception as e:
        logger.error(f"Sync system config error: {e}")
        return jsonify({
            'success': False,
            'message': f'Error: {str(e)}'
        }), 500

@app.route('/api/python/sync/pull-attendance', methods=['GET'])
def sync_attendance_from_laravel():
    """Sinkronisasi data kehadiran hari ini dari Laravel API ke database lokal"""
    try:
        sync_service = _get_laravel_sync_service(request)
        
        # Fetch data from Laravel
        fetch_result = sync_service.fetch_attendance()
        if not fetch_result['success']:
            return jsonify(fetch_result), 500
        
        # Sync to local database
        sync_result = sync_service.sync_attendance_to_local(fetch_result['data'])
        
        return jsonify({
            'success': True,
            'message': 'Sinkronisasi data kehadiran berhasil',
            'stats': sync_result
        })
        
    except Exception as e:
        logger.error(f"Sync attendance error: {e}")
        return jsonify({
            'success': False,
            'message': f'Error: {str(e)}'
        }), 500


@app.route('/api/python/sync/all', methods=['GET'])
def sync_all_from_laravel():
    """Sinkronisasi semua data (mahasiswa, schedules, kegiatan) dari Laravel ke database lokal"""
    try:
        sync_service = _get_laravel_sync_service(request)
        
        logger.info(f"Starting full sync from Laravel: {sync_service.base_url}")
        
        # Sync all data
        result = sync_service.sync_all()
        
        if result['success']:
            return jsonify({
                'success': True,
                'message': 'Sinkronisasi semua data berhasil!',
                'results': result
            })
        else:
            return jsonify({
                'success': False,
                'message': result.get('message', 'Sinkronisasi gagal'),
                'results': result
            }), 500
        
    except Exception as e:
        logger.error(f"Sync all error: {e}")
        return jsonify({
            'success': False,
            'message': f'Error: {str(e)}'
        }), 500


@app.route('/api/python/sync/attendance', methods=['POST'])
def push_attendance_to_laravel():
    """Proxy pushing local attendance data to Laravel server in chunks to prevent WAF / payload size 406 error"""
    try:
        from app.laravel_sync import LaravelSyncService
        
        data_payload = request.json or {}
        records = data_payload.get('data', [])
        target_url = request.args.get('laravel_url', 'https://pkkmb.polinela.ac.id')
        
        if not records:
            return jsonify({'success': True, 'message': 'Synced 0 records', 'synced_count': 0, 'rejected_count': 0, 'rejection_reasons': []}), 200
            
        logger.info(f"Pushing {len(records)} attendance records in chunks to: {target_url}")
        
        # Try target_url first, fallback to local server if target_url fails
        try:
            sync_service = LaravelSyncService(target_url, verify_ssl=False)
            test_resp = sync_service.session.get(f"{sync_service.base_url}/api/kegiatan", timeout=5, verify=False)
        except Exception:
            logger.warning(f"Target URL {target_url} not responsive, falling back to http://127.0.0.1:8000")
            sync_service = LaravelSyncService("http://127.0.0.1:8000", verify_ssl=False)
            
        chunk_size = 50
        total_synced = 0
        total_rejected = 0
        all_rejection_reasons = []
        
        for i in range(0, len(records), chunk_size):
            chunk = records[i:i + chunk_size]
            try:
                resp = sync_service.session.post(
                    f"{sync_service.base_url}/api/sync",
                    json={'data': chunk},
                    timeout=30,
                    verify=False
                )
                if resp.status_code == 200:
                    res_json = resp.json()
                    chunk_synced = res_json.get('synced_count', 0)
                    total_synced += chunk_synced
                    total_rejected += res_json.get('rejected_count', 0)
                    all_rejection_reasons.extend(res_json.get('rejection_reasons', []))
                    if db and chunk_synced > 0:
                        db.mark_attendance_synced(chunk)
                else:
                    logger.warning(f"Chunk {i//chunk_size + 1} returned status {resp.status_code}")
            except Exception as chunk_err:
                logger.error(f"Error sending chunk {i//chunk_size + 1}: {chunk_err}")
                
        return jsonify({
            'success': True,
            'message': f'Synced {total_synced} records to production',
            'synced_count': total_synced,
            'rejected_count': total_rejected,
            'rejection_reasons': all_rejection_reasons
        }), 200

    except Exception as e:
        logger.error(f"Push attendance sync error: {e}")
        return jsonify({
            'success': False,
            'message': f'Gagal terhubung ke server Laravel: {str(e)}'
        }), 500


if __name__ == '__main__':
    print("=" * 60)
    print("  SIABSEN Python Backend for Laravel")
    print("  Starting Flask API Server...")
    print("=" * 60)
    print()
    print("Server akan berjalan di:")
    print("  - http://0.0.0.0:5000")
    print()
    print("Tekan Ctrl+C untuk menghentikan server")
    print("=" * 60)
    print()
    
    app.run(
        host='0.0.0.0',
        port=5000,
        debug=True,
        threaded=True
    )