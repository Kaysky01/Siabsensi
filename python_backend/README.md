# SIABSEN Python Backend

Python backend untuk integrasi YOLO detection dengan Laravel.

## Setup

### 1. Install Python Dependencies

```bash
cd python_backend
pip install -r requirements.txt
```

### 2. Configure Settings

Settings sudah otomatis di-load dari Laravel project root:
- `yolo_settings.json` - Konfigurasi YOLO model
- `rtsp_settings.json` - Konfigurasi kamera
- `.env` - Konfigurasi database Laravel

### 3. Jalankan Python Backend

```bash
cd python_backend
python api_server.py
```

Server akan berjalan di `http://127.0.0.1:5000`

## API Endpoints

### Status Check
```
GET /api/python/status
```
Cek status Python backend

### Camera Stream
```
GET /api/python/stream/<camera_id>
```
Stream kamera dengan YOLO detection

### QR Detection
```
POST /api/python/detect
```
Detect QR code dari image

### Record Attendance
```
POST /api/python/attendance
```
Record attendance ke database Laravel

## Integration dengan Laravel

1. Python backend berjalan di port 5000
2. Laravel berjalan di port 8000
3. Monitor page di Laravel connect ke Python backend untuk detection

## Troubleshooting

### Error: Module not found
Pastikan sudah install dependencies:
```bash
pip install -r requirements.txt
```

### Error: Database connection failed
Pastikan konfigurasi database di Laravel `.env` benar

### Error: Model not found
Pastikan file model ada di folder `models/`:
- `models/yolov8n.pt`
