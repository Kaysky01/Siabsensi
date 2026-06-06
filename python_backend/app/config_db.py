import os
import json
from pathlib import Path

# Konfigurasi MySQL (gunakan Laravel .env)
MYSQL_CONFIG = {
    'host': os.getenv('DB_HOST', '127.0.0.1'),
    'port': int(os.getenv('DB_PORT', 3306)),
    'user': os.getenv('DB_USERNAME', 'root'),
    'password': os.getenv('DB_PASSWORD', ''),
    'database': os.getenv('DB_DATABASE', 'siabsensi'),  # Default ke laravel
    'charset': 'utf8mb4',
    'collation': 'utf8mb4_unicode_ci'
}

# Load YOLO settings from Laravel project root
def load_yolo_settings():
    settings_path = Path(__file__).parent.parent.parent / 'yolo_settings.json'
    if settings_path.exists():
        with open(settings_path, 'r') as f:
            return json.load(f)
    return {
        'model_path': 'models/yolov8n.pt',
        'confidence': 0.45,
        'qr_cooldown': 30
    }

# Load RTSP settings from Laravel project root
def load_rtsp_settings():
    settings_path = Path(__file__).parent.parent.parent / 'rtsp_settings.json'
    if settings_path.exists():
        with open(settings_path, 'r') as f:
            return json.load(f)
    return {
        'frame_width': 1280,
        'frame_height': 720,
        'frame_fps': 30,
        'reconnect_delay': 5
    }

YOLO_SETTINGS = load_yolo_settings()
RTSP_SETTINGS = load_rtsp_settings()

def reload_settings():
    """Reload settings from JSON files"""
    global YOLO_SETTINGS, RTSP_SETTINGS
    YOLO_SETTINGS = load_yolo_settings()
    RTSP_SETTINGS = load_rtsp_settings()
    return {
        'yolo_settings': YOLO_SETTINGS,
        'rtsp_settings': RTSP_SETTINGS
    }

