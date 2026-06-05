"""
SIABSEN Python Backend for Laravel
Simplified API server for YOLO detection and camera processing
"""

import sys
import os
from pathlib import Path

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent))

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
from app.config_db import MYSQL_CONFIG, YOLO_SETTINGS, RTSP_SETTINGS

app = Flask(__name__)
CORS(app, 
     supports_credentials=True,
     origins=['http://127.0.0.1:8000', 'http://localhost:8000'],
     allow_headers=['Content-Type', 'Authorization'],
     methods=['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']
)

# Initialize system
db, yolo, processor = create_system()

# Camera stream thread
camera_thread = None
camera_running = False

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
        data = request.json
        image_data = data.get('image')
        
        if not image_data:
            return jsonify({'success': False, 'message': 'No image data'}), 400
        
        # Decode base64 image
        image_bytes = base64.b64decode(image_data.split(',')[1])
        nparr = np.frombuffer(image_bytes, np.uint8)
        frame = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
        
        # QR detection
        from pyzbar.pyzbar import decode as qr_decode
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
        
        return jsonify({
            'success': True,
            'results': results
        })
    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500

@app.route('/api/python/attendance', methods=['POST'])
def record_attendance():
    """Record attendance to Laravel database"""
    try:
        data = request.json
        mahasiswa_id = data.get('mahasiswa_id')
        status = data.get('status', 'present')
        
        # Insert into Laravel database
        query = """
        INSERT INTO attendances (mahasiswa_id, date, status, check_in, created_at, updated_at)
        VALUES (%s, %s, %s, %s, %s, %s)
        """
        
        today = datetime.now().strftime('%Y-%m-%d')
        now = datetime.now().strftime('%H:%M:%S')
        
        db.execute_query(query, (mahasiswa_id, today, status, now, now, now))
        
        return jsonify({
            'success': True,
            'message': 'Attendance recorded'
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
