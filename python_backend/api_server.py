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
    
    # For daily attendance, check schedule first
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
    
    # Continue with normal local state logic
    key = 'default'
    
    if key not in local_attendance_state:
        local_attendance_state[key] = {}
    
    session = local_attendance_state[key]
    
    if mahasiswa_id not in session:
        # Belum ada record → check_in
        session[mahasiswa_id] = {
            'check_in': datetime.now(), 
            'check_out': None,
            'is_late': is_late,
            'late_duration': late_duration
        }
        # Return tuple with late info for check_in
        return ('check_in', is_late, late_duration)
    
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
                'result': {'status': 'checked_in', 'time': time_str},
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
                'message': 'Mahasiswa not found with this QR code'
            }), 404

        # Cek apakah mahasiswa aktif
        if not mahasiswa.get('is_active'):
            return jsonify({
                'success': False,
                'message': f"Mahasiswa '{mahasiswa.get('name')}' ({qr_code_id}) tidak aktif. Silakan hubungi Administrator."
            }), 403

        actual_mahasiswa_id = mahasiswa['id']

        # Determine action berdasarkan in-memory state (BUKAN database)
        action = get_local_action(actual_mahasiswa_id, kegiatan_id)
        
        # Handle rejection (tuple response)
        if isinstance(action, tuple) and action[0] == 'rejected':
            _, reason, message = action
            return jsonify({
                'success': False,
                'message': message,
                'reason': reason
            }), 403
        
        # Handle check_in with late info (tuple response)
        if isinstance(action, tuple) and action[0] == 'check_in':
            _, is_late, late_duration = action
            return jsonify({
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
            })
        
        # Handle old string responses for backward compatibility
        if action == 'no_schedule':
            return jsonify({
                'success': False,
                'message': 'Tidak ada jadwal absensi untuk hari ini'
            }), 403
        
        if action == 'cooldown':
            return jsonify({
                'success': True,
                'message': f'Attendance ignored (cooldown)',
                'result': {'status': 'cooldown'},
                'mahasiswa': {
                    'id': mahasiswa['id'],
                    'name': mahasiswa['name']
                }
            })
        
        if action == 'already_checked_out':
            return jsonify({
                'success': True,
                'message': f'Sudah selesai absen (masuk & keluar) untuk sesi ini',
                'result': {'status': 'already_checked_out'},
                'mahasiswa': {
                    'id': mahasiswa['id'],
                    'name': mahasiswa['name']
                }
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
            'result': {'status': status_string, 'time': time_str},
            'mahasiswa': {
                'id': mahasiswa['id'],
                'name': mahasiswa['name'],
                'kompi': mahasiswa['kompi'],
                'jurusan': mahasiswa['jurusan']
            }
        })
    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500

@app.route('/api/python/reset-state', methods=['POST'])
def reset_attendance_state():
    """Reset in-memory attendance tracking (dipanggil saat Clear Data atau ganti kegiatan)"""
    try:
        data = request.json or {}
        kegiatan_id = data.get('kegiatan_id', None)
        reset_local_attendance(kegiatan_id)
        return jsonify({
            'success': True,
            'message': 'In-memory attendance state telah di-reset'
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
