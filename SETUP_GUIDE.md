# 🔧 MAPIA IoT System - Complete Setup Guide

## 📋 Overview

Sistem MAPIA mengintegrasikan:
- **ESP32 IoT Device** (sensor kelembapan + relay pompa)
- **EMQX Cloud MQTT Broker** (komunikasi real-time)
- **Laravel Backend** (API + MQTT Bridge)
- **Web Dashboard** (frontend untuk kontrol)

**Flow:** ESP32 → EMQX → Laravel → Database & WebSocket → Dashboard

---

## ✅ Prerequisites

### 1. Database Setup (Neon PostgreSQL)
Sudah ada di neon, migrasi sudah jalan.

### 2. EMQX Cloud Setup
- **Broker:** `x1517f89.ala.asia-southeast1.emqxsl.com`
- **Port:** `8883` (MQTTS) / `8084` (WebSocket)
- **Username:** `Mapia`
- **Password:** `Mapia123`

### 3. Local Environment
- PHP 8.3+
- Composer
- Node.js 18+
- Redis (untuk real-time broadcasting)

---

## 🚀 Installation Steps

### Step 1: Install Dependencies

```bash
cd D:\laragon\www\MAPIA-main.worktrees\agents-esp32-firmware-v4-0-configuration-update

# Install PHP dependencies
composer install

# Install Node dependencies
npm install
```

### Step 2: Setup Environment

File `.env` sudah dikonfigurasi dengan:

```env
# Database
DB_CONNECTION=sqlite (atau ganti ke postgresql untuk production)

# MQTT Configuration
MQTT_BROKER=x1517f89.ala.asia-southeast1.emqxsl.com
MQTT_PORT=8883
MQTT_WEBSOCKET_PORT=8084
MQTT_USERNAME=Mapia
MQTT_PASSWORD=Mapia123
MQTT_CLIENT_ID=mapia-laravel-
MQTT_KEEP_ALIVE=60

# Broadcasting (Real-time updates)
BROADCAST_DRIVER=redis
```

### Step 3: Run Migrations

```bash
php artisan migrate --force
```

### Step 4: Setup Laravel Broadcast

```bash
# Publish broadcasting config
php artisan vendor:publish --provider="Illuminate\Broadcasting\BroadcastServiceProvider"
```

---

## 🎯 Running the System

### Option A: Development Mode (All-in-One)

```bash
# Terminal 1 - Laravel Server
php artisan serve

# Terminal 2 - Queue Worker
php artisan queue:listen

# Terminal 3 - MQTT Listener (Long-running)
php artisan mqtt:listen

# Terminal 4 - Frontend Dev
npm run dev

# Terminal 5 (optional) - Logs
php artisan pail
```

### Option B: Using Composer Script

```bash
composer dev
```

Ini akan menjalankan semua proses dalam satu command (dengan concurrently).

### Option C: Production Mode

```bash
# Build frontend
npm run build

# Start Laravel in production
php artisan serve --host=0.0.0.0

# Start MQTT Listener as daemon/supervisor
# (Configure di /etc/supervisor/conf.d/mqtt-listener.conf)
```

---

## 📱 ESP32 Firmware Upload

### 1. Update Firmware Code

File baru: `esp32_firmware_v4.1.ino`

**Konfigurasi bagian atas:**

```cpp
#define WIFI_SSID       "Mattew's S25 Edge"
#define WIFI_PASSWORD   "uqrmntxbbbg92ay"
#define MQTT_BROKER    "x1517f89.ala.asia-southeast1.emqxsl.com"
#define MQTT_PORT      8883
#define MQTT_USER      "Mapia"
#define MQTT_PASS      "Mapia123"
#define SENSOR_DB_ID   1   // ID sensor di database
```

### 2. Upload to ESP32

Gunakan Arduino IDE:

1. Tools → Board → ESP32 (paling sesuai dengan versi)
2. Tools → Port → COM3 (atau port device Anda)
3. Sketch → Upload

### 3. Monitor Serial Output

```
Baud: 115200
```

Perhatikan output untuk debugging:

```
[WIFI] Connecting to "Mattew's S25 Edge"...
[WIFI] ✓ Connected! IP: 192.168.1.100
[MQTT] Connecting to x1517f89.ala.asia-southeast1.emqxsl.com:8883...
[MQTT] ✓ Connected to broker
[AUTO] Kering 35.5% < min 40.0% → Pompa ON
```

---

## 🔌 MQTT Topics Reference

Device akan publish & subscribe ke:

```
# Device publishes:
mapia/sensor/{MAC_ADDRESS}/data        → Kirim sensor readings
mapia/sensor/{MAC_ADDRESS}/status      → Status online + parameter
mapia/sensor/{MAC_ADDRESS}/heartbeat   → Keep-alive signal
mapia/sensor/{MAC_ADDRESS}/alert       → Error/safety alerts

# Device subscribes to (receive commands):
mapia/sensor/{MAC_ADDRESS}/mode        → Set mode (Otomatis/Manual)
mapia/sensor/{MAC_ADDRESS}/parameter   → Update min/max kelembapan
mapia/actuator/{MAC_ADDRESS}/pump      → Control pompa (ON/OFF)
mapia/sensor/{MAC_ADDRESS}/reset       → Remote restart
```

**Example MAC_ADDRESS:** `68A86DXXXXXX`

---

## 🌐 API Endpoints

Semua endpoint memerlukan autentikasi Sanctum (kecuali `/api/v1/send-data`).

### Public (No Auth Required)

```http
POST /api/v1/send-data
Content-Type: application/json

{
  "id_sensor": 1,
  "kelembapan": 65.5,
  "ph_tanah": 6.8
}
```

### Protected (Require Token)

#### Get All Sensors

```http
GET /api/v1/sensors
Authorization: Bearer {token}
```

**Response:**

```json
{
  "status": "success",
  "data": [
    {
      "id_sensor": 1,
      "id_user": 1,
      "nama_sensor": "Tanaman Tomat",
      "mac_address": "68A86D123456",
      "status": true,
      "parameter_penyiraman": {
        "min_kelembapan": 40.0,
        "max_kelembapan": 70.0
      },
      "kontrol_siram": {
        "mode_auto": true,
        "status_pompa": false
      }
    }
  ]
}
```

#### Get Sensor Detail

```http
GET /api/v1/sensors/{id}
Authorization: Bearer {token}
```

#### Update Parameter

```http
PATCH /api/v1/sensors/{id}/parameter
Authorization: Bearer {token}
Content-Type: application/json

{
  "min_kelembapan": 35.0,
  "max_kelembapan": 75.0
}
```

**Response:**

```json
{
  "status": "success",
  "message": "Parameter updated successfully",
  "data": {
    "id_parameter": 1,
    "id_sensor": 1,
    "min_kelembapan": 35.0,
    "max_kelembapan": 75.0
  }
}
```

#### Change Mode

```http
PATCH /api/v1/sensors/{id}/mode
Authorization: Bearer {token}
Content-Type: application/json

{
  "mode": "manual"
}
```

#### Control Pump (Manual Mode Only)

```http
POST /api/v1/sensors/{id}/pump
Authorization: Bearer {token}
Content-Type: application/json

{
  "action": "ON"
}
```

---

## 🔄 Real-time Updates (WebSocket)

Frontend menggunakan **Laravel Echo** untuk real-time updates.

### Setup Broadcasting

File: `config/broadcasting.php`

```php
'default' => env('BROADCAST_DRIVER', 'redis'),

'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
    ],
],
```

### Listen to Sensor Events (JavaScript)

```javascript
// resources/js/bootstrap.js

import Echo from 'laravel-echo';
window.Echo = new Echo({
    broadcaster: 'redis',
    host: window.location.hostname,
    port: 6379,
    encrypted: false,
});

// Listen to sensor updates
window.Echo.channel('sensor.1').listen('.sensor-updated', (e) => {
    console.log('Sensor update:', e);
    // Update UI dengan data terbaru
    updateSensorUI(e);
});

// Listen to all sensors
window.Echo.channel('sensors').listen('.sensor-updated', (e) => {
    console.log('Any sensor updated:', e);
});
```

---

## 📊 Database Schema

### Tabel Utama

```
sensors
├── id_sensor (PK)
├── id_user (FK)
├── nama_sensor
├── mac_address
├── status
└── timestamps

parameter_penyiramans
├── id_parameter (PK)
├── id_sensor (FK)
├── min_kelembapan
├── max_kelembapan
└── timestamps

kontrol_sirams
├── id_kontrol_siram (PK)
├── id_sensor (FK)
├── mode_auto
├── status_pompa
└── timestamps

history_kelembapans
├── id_history (PK)
├── id_sensor (FK)
├── kelembapan
├── kondisi
├── uptime
└── timestamps
```

---

## 🐛 Troubleshooting

### ESP32 tidak bisa connect ke WiFi

```
[WIFI] Timeout 30s → Restart
```

**Solusi:**

1. Pastikan SSID & password di firmware benar
2. ESP32 dalam jangkauan WiFi
3. Cek serial output untuk error

### MQTT Connection Failed

```
[MQTT] Failed (state=4)
```

**State codes:**

- `-4` = Connection lost
- `-3` = Connection failed
- `-2` = Not connected
- `-1` = Connection refused
- `0` = Success
- `1-5` = Protocol errors

**Solusi:**

1. Verify EMQX broker credentials
2. Check firewall/port access
3. Pastikan TLS certificate valid
4. Restart device

### Data tidak masuk ke database

**Cek:**

1. Apakah `mqtt:listen` command berjalan?

```bash
php artisan mqtt:listen
```

2. Cek logs:

```bash
php artisan pail
```

3. Verify MQTT topics benar sesuai MAC address

### Real-time updates tidak bekerja

**Cek:**

1. Redis running?

```bash
# Windows
redis-server

# Atau via Laragon
```

2. `BROADCAST_DRIVER=redis` di `.env`?

3. Laravel Echo client terinisialisasi di frontend?

---

## 🔐 Security Notes

### Production Checklist

- [ ] Change MQTT credentials
- [ ] Update WiFi credentials di firmware
- [ ] Use strong database password
- [ ] Enable HTTPS/TLS
- [ ] Setup proper authentication & authorization
- [ ] Configure CORS properly
- [ ] Use environment variables untuk credentials
- [ ] Setup rate limiting di API
- [ ] Monitor MQTT traffic
- [ ] Regular backups

---

## 📞 Support & Debugging

### Enable Verbose Logging

```bash
# .env
LOG_LEVEL=debug

# Watch logs
php artisan pail

# Or check log file
tail -f storage/logs/laravel.log
```

### MQTT Debugging

Gunakan MQTT Client (misal. MQTT Explorer):

1. Connect ke broker
2. Subscribe ke `mapia/sensor/+/+`
3. Monitor semua messages

### API Testing

Gunakan Postman atau Curl:

```bash
# Get all sensors
curl -X GET http://localhost:8000/api/v1/sensors

# Send sensor data
curl -X POST http://localhost:8000/api/v1/send-data \
  -H "Content-Type: application/json" \
  -d '{"id_sensor": 1, "kelembapan": 65.5, "ph_tanah": 6.8}'
```

---

## 📚 Additional Resources

- [EMQX Documentation](https://docs.emqx.io/)
- [Laravel Broadcasting](https://laravel.com/docs/11.x/broadcasting)
- [Arduino ESP32](https://docs.espressif.com/projects/arduino-esp32/)
- [MQTT Protocol](https://mqtt.org/)

---

**Last Updated:** 2026-06-04
**Firmware Version:** v4.1
**Backend Version:** 1.0
