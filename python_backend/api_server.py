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

from flask import Flask, Response, jsonify, request
from flask_cors import CORS
import cv2
import numpy as np
import base64
import threading
import time
from datetime import datetime

# Import from app modules
from app.attendance_engine import create_system
from app.config_db import MYSQL_CONFIG, YOLO_SETTINGS, RTSP_SETTINGS, reload_settings

app = Flask(__name__)

# Initialize system (try to connect to database, but allow running without it)
try:
    db, yolo, processor = create_system()
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

@app.route('/api/python/status', methods=['GET', 'OPTIONS'])
def status():
    """Check if Python backend is running"""
    # Add CORS headers
    if request.method == 'OPTIONS':
        response = jsonify('')
        response.headers.add('Access-Control-Allow-Origin', '*')
        response.headers.add('Access-Control-Allow-Headers', 'Content-Type')
        response.headers.add('Access-Control-Allow-Methods', 'GET, OPTIONS')
        return response, 200

    response = jsonify({
        'success': True,
        'status': 'running',
        'yolo_model': str(YOLO_SETTINGS.get('model_path')),
        'confidence': YOLO_SETTINGS.get('confidence'),
        'rtsp_settings': RTSP_SETTINGS
    })
    response.headers.add('Access-Control-Allow-Origin', '*')
    return response

@app.route('/api/python/reload-settings', methods=['POST', 'OPTIONS'])
def reload_settings_endpoint():
    """Reload YOLO and RTSP settings from JSON files"""
    # Add CORS headers
    if request.method == 'OPTIONS':
        response = jsonify('')
        response.headers.add('Access-Control-Allow-Origin', '*')
        response.headers.add('Access-Control-Allow-Headers', 'Content-Type')
        response.headers.add('Access-Control-Allow-Methods', 'POST, OPTIONS')
        return response, 200

    try:
        settings = reload_settings()
        response = jsonify({
            'success': True,
            'message': 'Settings reloaded successfully',
            'settings': settings
        })
        response.headers.add('Access-Control-Allow-Origin', '*')
        return response
    except Exception as e:
        response = jsonify({
            'success': False,
            'message': f'Failed to reload settings: {str(e)}'
        })
        response.headers.add('Access-Control-Allow-Origin', '*')
        return response, 500

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

@app.route('/api/python/detect', methods=['POST', 'OPTIONS'])
def detect_qr():
    """Detect QR code from image"""
    # Add CORS headers
    response = None
    if request.method == 'OPTIONS':
        response = jsonify('')
        response.headers.add('Access-Control-Allow-Origin', '*')
        response.headers.add('Access-Control-Allow-Headers', 'Content-Type')
        response.headers.add('Access-Control-Allow-Methods', 'POST, OPTIONS')
        return response, 200

    try:
        data = request.json
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
            for result in yolo_results:
                for box in result.boxes:
                    x1, y1, x2, y2 = box.xyxy[0]
                    conf = float(box.conf[0])
                    qr_papers.append({
                        'bbox': (int(x1), int(y1), int(x2), int(y2)),
                        'confidence': conf
                    })
        except Exception as yolo_error:
            logger.warning(f"YOLO detection failed: {yolo_error}, falling back to pyzbar only")
            qr_papers = []

        # QR detection using pyzbar
        from pyzbar.pyzbar import decode as qr_decode

        # If YOLO detected QR papers, decode only in those areas
        # Otherwise decode the entire frame
        if qr_papers:
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
            # Fallback to full frame detection
            decoded_objects = qr_decode(frame)

        results = []
        for obj in decoded_objects:
            results.append({
                'data': obj.data.decode('utf-8'),
                'type': obj.type,
                'rect': {
                    'left': obj.rect.left,
                    'top': obj.rect.top,
                    'width': obj.rect.width,
                    'height': obj.rect.height
                }
            })

        response = jsonify({
            'success': True,
            'results': results,
            'yolo_detections': len(qr_papers)
        })
        response.headers.add('Access-Control-Allow-Origin', '*')
        return response
    except Exception as e:
        response = jsonify({'success': False, 'message': str(e)})
        response.headers.add('Access-Control-Allow-Origin', '*')
        return response, 500

@app.route('/api/python/attendance', methods=['POST', 'OPTIONS'])
def record_attendance():
    """Record attendance to Laravel database"""
    # Add CORS headers
    if request.method == 'OPTIONS':
        response = jsonify('')
        response.headers.add('Access-Control-Allow-Origin', '*')
        response.headers.add('Access-Control-Allow-Headers', 'Content-Type')
        response.headers.add('Access-Control-Allow-Methods', 'POST, OPTIONS')
        return response, 200

    try:
        if db is None:
            response = jsonify({
                'success': False,
                'message': 'Database not connected - attendance recording disabled'
            })
            response.headers.add('Access-Control-Allow-Origin', '*')
            return response, 503

        data = request.json
        qr_code_id = data.get('mahasiswa_id')  # This is actually the QR code ID, not mahasiswa ID

        # Look up mahasiswa by QR code
        mahasiswa = db.get_mahasiswa_by_qr(qr_code_id)
        if not mahasiswa:
            response = jsonify({
                'success': False,
                'message': 'Mahasiswa not found with this QR code'
            })
            response.headers.add('Access-Control-Allow-Origin', '*')
            return response, 404

        # Use the actual mahasiswa_id from the database
        actual_mahasiswa_id = mahasiswa['id']

        # Record attendance using DatabaseManager method
        # Use NULL for camera_id to avoid foreign key constraint error
        result = db.record_attendance(
            actual_mahasiswa_id,
            'check_in',
            None,  # camera_id (NULL to avoid foreign key constraint)
            None,  # snapshot_path
            0.0     # confidence
        )

        response = jsonify({
            'success': True,
            'message': 'Attendance recorded',
            'result': result,
            'mahasiswa': {
                'id': mahasiswa['id'],
                'name': mahasiswa['name'],
                'kelompok': mahasiswa['kelompok'],
                'jurusan': mahasiswa['jurusan']
            }
        })
        response.headers.add('Access-Control-Allow-Origin', '*')
        return response
    except Exception as e:
        response = jsonify({'success': False, 'message': str(e)})
        response.headers.add('Access-Control-Allow-Origin', '*')
        return response, 500

if __name__ == '__main__':
    print("=" * 60)
    print("  SIABSEN Python Backend for Laravel")
    print("  Starting Flask API Server...")
    print("=" * 60)
    print()
    print("Server akan berjalan di:")
    print("  - http://127.0.0.1:5000")
    print()
    print("Tekan Ctrl+C untuk menghentikan server")
    print("=" * 60)
    print()
    
    app.run(
        host='127.0.0.1',
        port=5000,
        debug=True,
        threaded=True
    )
