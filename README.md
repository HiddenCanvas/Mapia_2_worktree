# MAPIA (Monitoring & Automation of Pepaya California) 🌿

[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-Neon-4169E1?style=for-the-badge&logo=postgresql)](https://neon.tech)
[![IoT](https://img.shields.io/badge/IoT-ESP32-000000?style=for-the-badge&logo=espressif)](https://www.espressif.com/)
[![MQTT](https://img.shields.io/badge/MQTT-EMQX-00B96B?style=for-the-badge&logo=mqtt)](https://www.emqx.io/)

**MAPIA** adalah platform IoT lengkap untuk monitoring dan otomatisasi perawatan tanaman Pepaya California. Sistem ini mengintegrasikan ESP32, EMQX Cloud MQTT Broker, dan Laravel backend untuk real-time monitoring dan smart automation.

---

## 🚀 Fitur Utama

✅ **Real-time Monitoring** — Pantau kelembapan tanah secara langsung dengan update real-time via WebSocket

✅ **Smart Irrigation** — Otomatisasi penyiraman dengan parameter yang dapat dikonfigurasi (min/max kelembapan)

✅ **Manual Control** — Kontrol pompa manual dari dashboard web

✅ **IoT Integration** — Koneksi secure ke EMQX Cloud MQTT Broker dengan TLS encryption

✅ **Data Analytics** — Riwayat sensor tersimpan di PostgreSQL untuk analisis

✅ **RESTful API** — API lengkap untuk integrasi dengan sistem lain

✅ **Web Dashboard** — Interface modern dengan Blade templates & Tailwind CSS

---

## 🏗️ Sistem Architecture

```
ESP32 Device ──MQTT──> EMQX Cloud Broker ──> Laravel Backend ──> Web Dashboard
   (Sensor)    (TLS)    (Cloud)              (API + WebSocket)    (Real-time UI)
```

### Komponen Utama

| Komponen | Teknologi | Purpose |
|----------|-----------|---------|
| **IoT Device** | ESP32 + Sensor Kelembapan | Baca kelembapan tanah, kontrol pompa |
| **Broker** | EMQX Cloud | Komunikasi real-time antar device & server |
| **Backend** | Laravel 13 + PHP 8.3 | API, MQTT Bridge, Database |
| **Database** | PostgreSQL (Neon) | Penyimpanan data sensor & parameter |
| **Frontend** | Blade + Tailwind CSS | Dashboard user interface |

---

## 📋 File & Dokumentasi

### 🚀 Quick Start

- **[QUICK_START.md](./QUICK_START.md)** ⭐ START HERE
  - Checklist lengkap setup
  - Testing procedures
  - Troubleshooting quick fixes

### 📖 Dokumentasi Lengkap

- **[SETUP_GUIDE.md](./SETUP_GUIDE.md)** — Setup komprehensif
  - Prerequisites & instalasi
  - Configuration details
  - Running the system
  - Debugging guide

- **[API_DOCUMENTATION.md](./API_DOCUMENTATION.md)** — REST API Reference
  - Semua endpoints
  - Request/response examples
  - MQTT topics
  - cURL testing

- **[CONFIGURATION_REFERENCE.md](./CONFIGURATION_REFERENCE.md)** — Technical Reference
  - Hardware pinout
  - Network config
  - Database schema
  - Security checklist

### 📱 Firmware

- **[esp32_firmware_v4.1.ino](./esp32_firmware_v4.1.ino)** — ESP32 Firmware
  - Production-ready code
  - WiFi + MQTT connection
  - Sensor reading logic
  - Auto-reconnection

---

## ⚙️ Quick Setup (5 Minutes)

### 1. Backend Setup

```bash
# Install dependencies
composer install
npm install

# Setup database
php artisan migrate --force

# Generate app key (if not done)
php artisan key:generate
```

### 2. Start Services

```bash
# Terminal 1: Laravel Server
php artisan serve

# Terminal 2: MQTT Listener (long-running)
php artisan mqtt:listen

# Terminal 3: Frontend Dev
npm run dev
```

### 3. ESP32 Upload

1. Download `esp32_firmware_v4.1.ino`
2. Open in Arduino IDE
3. Update WiFi credentials
4. Upload to ESP32 (COM3, 115200 baud)

### 4. Verify

- Laravel running on `http://localhost:8000` ✅
- MQTT Listener receiving messages ✅
- ESP32 showing sensor readings ✅

**See [QUICK_START.md](./QUICK_START.md) for detailed checklist.**

---

## 🔧 Konfigurasi EMQX

Broker Anda sudah siap di:

```
Hostname: x1517f89.ala.asia-southeast1.emqxsl.com
Port:     8883 (MQTTS)
Username: Mapia
Password: Mapia123
```

Ini sudah dikonfigurasi di `.env`. Tidak perlu ubah untuk development.

---

## 📊 Database Schema

Migrasi sudah jalan. Tabel utama:

```
sensors ─── parameter_penyiramans
 │       └── kontrol_sirams
 └───────── history_kelembapans
```

Lihat [CONFIGURATION_REFERENCE.md](./CONFIGURATION_REFERENCE.md#-database-schema) untuk SQL lengkap.

---

## 🔌 MQTT Topics

Device publish ke:

```
mapia/sensor/{MAC}/data        → Sensor readings
mapia/sensor/{MAC}/status      → Device status (retained)
mapia/sensor/{MAC}/heartbeat   → Keep-alive signal
```

Server publish ke device:

```
mapia/sensor/{MAC}/mode        → Change mode (Auto/Manual)
mapia/sensor/{MAC}/parameter   → Update parameter
mapia/actuator/{MAC}/pump      → Pump control (ON/OFF)
```

Lihat [API_DOCUMENTATION.md](./API_DOCUMENTATION.md#-mqtt-messages) untuk detail.

---

## 🌐 API Endpoints

### Public (No Auth)

```bash
POST /api/v1/send-data
# Device send sensor data
```

### Protected (Require Token)

```bash
GET    /api/v1/sensors                    # Get all sensors
GET    /api/v1/sensors/{id}               # Get sensor detail
PATCH  /api/v1/sensors/{id}/parameter     # Update parameter
PATCH  /api/v1/sensors/{id}/mode          # Change mode
POST   /api/v1/sensors/{id}/pump          # Control pump
```

**Example:**

```bash
# Get all sensors
curl -X GET http://localhost:8000/api/v1/sensors \
  -H "Authorization: Bearer {token}"

# Update parameter
curl -X PATCH http://localhost:8000/api/v1/sensors/1/parameter \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"min_kelembapan": 35.0, "max_kelembapan": 75.0}'
```

Lihat [API_DOCUMENTATION.md](./API_DOCUMENTATION.md) untuk complete reference.

---

## 🛠️ Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13, PHP 8.3 |
| Database | PostgreSQL (Neon.tech) |
| IoT Broker | EMQX Cloud (MQTT) |
| Real-time | Laravel Echo + Redis |
| Frontend | Blade + Tailwind CSS + Alpine.js |
| Hardware | ESP32, Soil Moisture Sensor, Relay |

---

## 📱 Files Created/Updated

### Services (Backend)
- ✅ `app/Services/MqttService.php` — MQTT client wrapper

### Commands
- ✅ `app/Console/Commands/MqttListenerCommand.php` — Long-running MQTT listener

### Controllers
- ✅ `app/Http/Controllers/Api/SensorController.php` — API endpoints (expanded)

### Events
- ✅ `app/Events/SensorDataUpdated.php` — WebSocket broadcast event

### Models
- ✅ `app/Models/HistoryKelembapan.php` — Updated with new fields

### Migrations
- ✅ `database/migrations/2026_06_04_155000_add_kondisi_uptime_to_history_kelembapans.php`

### Routes
- ✅ `routes/api.php` — API routes expanded

### Configuration
- ✅ `.env` — MQTT credentials added
- ✅ `composer.json` — php-mqtt/client dependency

### Firmware
- ✅ `esp32_firmware_v4.1.ino` — Production-ready firmware

### Documentation
- ✅ `QUICK_START.md` — Quick setup guide
- ✅ `SETUP_GUIDE.md` — Complete setup
- ✅ `API_DOCUMENTATION.md` — API reference
- ✅ `CONFIGURATION_REFERENCE.md` — Technical reference

---

## 🚀 Development Workflow

### Start Development

```bash
# Follow QUICK_START.md checklist
```

### Make Changes

```bash
# Edit code, test locally
php artisan serve
php artisan mqtt:listen
npm run dev
```

### Test Integration

```bash
# See QUICK_START.md "Integration Testing" section
# Verify end-to-end flow
```

### Deploy to Production

```bash
# See SETUP_GUIDE.md "Production Deployment"
# Setup supervisor for mqtt:listen
# Configure Redis
# Setup nginx reverse proxy
```

---

## 🐛 Troubleshooting

### Common Issues

| Issue | Solution |
|-------|----------|
| MQTT connection failed | Check credentials in `.env`, verify EMQX broker online |
| Data not appearing | Ensure `php artisan mqtt:listen` is running |
| Real-time updates not working | Check Redis running, verify `BROADCAST_DRIVER=redis` |
| Device can't connect WiFi | Check SSID/password, verify device in range, check serial output |

**See [QUICK_START.md](./QUICK_START.md#-troubleshooting-quick-fixes) for more.**

---

## 📞 Documentation Links

- 🚀 **[QUICK_START.md](./QUICK_START.md)** — 5-minute setup checklist
- 📖 **[SETUP_GUIDE.md](./SETUP_GUIDE.md)** — Complete installation guide
- 📡 **[API_DOCUMENTATION.md](./API_DOCUMENTATION.md)** — REST API reference
- ⚙️ **[CONFIGURATION_REFERENCE.md](./CONFIGURATION_REFERENCE.md)** — Technical details
- 💾 **[esp32_firmware_v4.1.ino](./esp32_firmware_v4.1.ino)** — Device firmware

---

## ✅ Pre-Deployment Checklist

- [ ] All dependencies installed (`composer install`, `npm install`)
- [ ] Database migrated (`php artisan migrate --force`)
- [ ] `.env` configured with EMQX credentials
- [ ] ESP32 firmware uploaded with correct WiFi credentials
- [ ] Local testing passed (see [QUICK_START.md](./QUICK_START.md))
- [ ] API endpoints responding
- [ ] MQTT messages flowing
- [ ] Real-time dashboard updating
- [ ] Database receiving sensor data

---

## 🔐 Security Notes

### Current Status

✅ MQTT TLS encrypted (port 8883)
✅ Laravel Sanctum authentication
✅ Database credentials in `.env`

### Production Checklist

- [ ] Change MQTT credentials
- [ ] Change database password
- [ ] Enable HTTPS
- [ ] Setup rate limiting
- [ ] Configure CORS properly
- [ ] Use strong API tokens
- [ ] Setup monitoring & alerts

See [CONFIGURATION_REFERENCE.md](./CONFIGURATION_REFERENCE.md#-security-considerations) for details.

---

## 📊 Next Steps

1. **Follow [QUICK_START.md](./QUICK_START.md)** — Setup & verify system
2. **Test all endpoints** — Use cURL/Postman as shown in [API_DOCUMENTATION.md](./API_DOCUMENTATION.md)
3. **Upload ESP32 firmware** — Use Arduino IDE with `esp32_firmware_v4.1.ino`
4. **Verify MQTT flow** — Check device → server → database → dashboard
5. **Deploy to production** — See [SETUP_GUIDE.md](./SETUP_GUIDE.md#production-deployment)

---

## 📚 Resources

- [Laravel 13 Documentation](https://laravel.com/docs)
- [EMQX Documentation](https://docs.emqx.io/)
- [ESP32 Arduino](https://docs.espressif.com/projects/arduino-esp32/)
- [MQTT Protocol](https://mqtt.org/)

---

**Version:** 4.1  
**Last Updated:** 2026-06-04  
**Status:** ✅ Production Ready for Testing

🎉 **Ready to go! Start with [QUICK_START.md](./QUICK_START.md)**
