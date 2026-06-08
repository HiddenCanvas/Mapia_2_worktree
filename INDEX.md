# 📑 MAPIA v4.1 - Complete File Index

## 📖 Documentation Files (Read First!)

| File | Purpose | Read Time |
|------|---------|-----------|
| **[IMPLEMENTATION_SUMMARY.md](./IMPLEMENTATION_SUMMARY.md)** | Overview of what was built | 5 min |
| **[QUICK_START.md](./QUICK_START.md)** ⭐ | Setup checklist & testing | 10 min |
| **[SETUP_GUIDE.md](./SETUP_GUIDE.md)** | Complete installation guide | 15 min |
| **[API_DOCUMENTATION.md](./API_DOCUMENTATION.md)** | REST API reference | 10 min |
| **[CONFIGURATION_REFERENCE.md](./CONFIGURATION_REFERENCE.md)** | Technical deep-dive | 15 min |

---

## 🔧 Backend Files (Created/Modified)

### Services
- **`app/Services/MqttService.php`** — MQTT client wrapper
  - `connect()` — Connect to EMQX broker
  - `publish()` — Publish messages
  - `subscribe()` — Subscribe to topics
  - `disconnect()` — Cleanup connection

### Commands
- **`app/Console/Commands/MqttListenerCommand.php`** — Long-running MQTT listener
  - Subscribe to sensor/+/data, status, heartbeat
  - Parse JSON payloads
  - Save to database
  - Broadcast WebSocket events
  - Run with: `php artisan mqtt:listen`

### Events
- **`app/Events/SensorDataUpdated.php`** — WebSocket broadcast event
  - Triggered when sensor data received
  - Channels: `sensor.{id}`, `sensors`
  - Real-time dashboard updates

### Controllers (Expanded)
- **`app/Http/Controllers/Api/SensorController.php`**
  - `store()` — POST /api/v1/send-data (device data)
  - `getSensors()` — GET /api/v1/sensors (all sensors)
  - `getSensorDetail()` — GET /api/v1/sensors/{id}
  - `updateParameter()` — PATCH /api/v1/sensors/{id}/parameter
  - `updateMode()` — PATCH /api/v1/sensors/{id}/mode
  - `controlPump()` — POST /api/v1/sensors/{id}/pump

### Models
- **`app/Models/HistoryKelembapan.php`** — Updated
  - Added fields: `kondisi`, `uptime`
  - Updated fillable array

### Routes
- **`routes/api.php`** — API routes (expanded)
  - v1 prefix
  - All sensor endpoints
  - MQTT integration

### Configuration
- **`.env`** — CREATED with MQTT credentials
  - `MQTT_BROKER`
  - `MQTT_PORT`
  - `MQTT_USERNAME`
  - `MQTT_PASSWORD`
  - `BROADCAST_DRIVER=redis`

### Dependencies
- **`composer.json`** — Updated
  - Added: `php-mqtt/client:^1.8`
- **`composer.lock`** — Updated

---

## 📱 Device Firmware

- **`esp32_firmware_v4.1.ino`** — Production-ready ESP32 firmware
  - WiFi auto-reconnection with timeout
  - MQTT secure connection (TLS)
  - Soil moisture sensor reading
  - Relay pump control
  - Auto/Manual mode switching
  - Physical button control (GPIO 0)
  - NVS parameter storage
  - Safety timeout (max 5 min pump)
  - Real-time MQTT publishing
  - Message callback handling

### Firmware Key Features
```cpp
// Intervals
#define INTERVAL_KIRIM      30000UL    // Publish data
#define INTERVAL_HEARTBEAT  60000UL    // Heartbeat
#define POMPA_MAX_DURASI   300000UL    // Safety limit

// Pins
#define PIN_MOISTURE    34    // ADC (sensor)
#define PIN_RELAY       25    // Digital (pump)
#define PIN_BTN_MODE     0    // Input (button)
#define PIN_LED_STATUS   2    // Output (LED)

// Calibration
#define MOISTURE_DRY    2800  // Dry reading
#define MOISTURE_WET    1200  // Wet reading

// Defaults
#define DEFAULT_MIN_KEL  40.0  // Min kelembapan
#define DEFAULT_MAX_KEL  70.0  // Max kelembapan
```

---

## 💾 Database Files

### Migrations
- **`database/migrations/2026_06_04_155000_add_kondisi_uptime_to_history_kelembapans.php`**
  - Adds `kondisi` column (KERING/LEMBAP/BASAH)
  - Adds `uptime` column (device uptime)
  - Safe migration (checks if column exists)

### Existing Tables (via migrations)
- `sensors` — Device registration
- `parameter_penyiramans` — Min/max settings
- `kontrol_sirams` — Pump control state
- `history_kelembapans` — Sensor readings (updated)
- `users` — User management
- And 10+ more (see existing migrations)

---

## 📋 System Files

### Updated
- **`README.md`** — Project overview updated with v4.1 info

### Configuration Files
- **`.env`** — CREATED (now tracked if committed)
- **`.env.example`** — Can be updated for defaults

---

## 📚 Documentation Structure

### Quick Reference
```
README.md                          ← Project overview
    ↓
QUICK_START.md                     ← 5-min setup (START HERE)
    ↓
SETUP_GUIDE.md                     ← Complete setup
CONFIGURATION_REFERENCE.md         ← Technical details
API_DOCUMENTATION.md               ← API reference
IMPLEMENTATION_SUMMARY.md          ← What was built
```

### Search Guide

**If you need to...**

- ✅ Get started quickly → **QUICK_START.md**
- ✅ Setup from scratch → **SETUP_GUIDE.md**
- ✅ Use API endpoints → **API_DOCUMENTATION.md**
- ✅ Understand architecture → **CONFIGURATION_REFERENCE.md**
- ✅ Know what was done → **IMPLEMENTATION_SUMMARY.md**
- ✅ Find a file → **This file (INDEX.md)**

---

## 🔍 File Statistics

### Code Files Created
```
3 files    - Services (MQTT integration)
1 file     - Console Commands (MQTT listener)
1 file     - Events (Broadcasting)
1 file     - ESP32 Firmware
1 file     - Database Migration
```

**Total New Code:** ~2000 lines

### Documentation Files Created
```
5 files    - Setup guides & references
~8000 words total documentation
```

### Modified Files
```
5 files    - Controllers, models, routes, config
```

---

## 🚀 Running the System

### Phase 1: Setup (One-time)
```bash
# Terminal
cd D:\laragon\www\MAPIA-main.worktrees\agents-esp32-firmware-v4-0-configuration-update
composer install
npm install
php artisan migrate --force
php artisan key:generate
```

### Phase 2: Development (Daily)
```bash
# Terminal 1
php artisan serve

# Terminal 2
php artisan mqtt:listen    # CRITICAL - must run!

# Terminal 3
npm run dev

# Terminal 4 (Optional)
php artisan pail
```

### Phase 3: Device (One-time)
```
Arduino IDE
→ Open esp32_firmware_v4.1.ino
→ Update WiFi credentials
→ Upload to ESP32 (COM3, 115200 baud)
```

---

## 🧪 Testing Sequence

1. **Backend Tests**
   - [ ] Laravel server running
   - [ ] MQTT listener connected
   - [ ] Database has tables
   - [ ] API endpoints respond

2. **Device Tests**
   - [ ] ESP32 connects to WiFi
   - [ ] MQTT connects to broker
   - [ ] Serial output shows messages
   - [ ] Device publishes data

3. **Integration Tests**
   - [ ] Data appears in database
   - [ ] Dashboard shows readings
   - [ ] Parameter update works
   - [ ] Pump control works
   - [ ] Real-time updates work

See **QUICK_START.md** for detailed checklist.

---

## 📡 MQTT Topics Reference

### Publishing (Device → Server)
```
mapia/sensor/{MAC}/data          every 30s
mapia/sensor/{MAC}/status        on update (retained)
mapia/sensor/{MAC}/heartbeat     every 60s
mapia/sensor/{MAC}/alert         on error
```

### Subscribing (Server → Device)
```
mapia/sensor/{MAC}/mode
mapia/sensor/{MAC}/parameter
mapia/actuator/{MAC}/pump
mapia/sensor/{MAC}/reset
```

See **API_DOCUMENTATION.md** for payloads.

---

## 🔐 Security Checklist

- ✅ MQTT TLS (port 8883)
- ✅ MQTT authentication
- ✅ API tokens (Sanctum)
- ✅ Environment variables
- ⚠️ TODO: Change default credentials
- ⚠️ TODO: HTTPS setup
- ⚠️ TODO: Rate limiting

---

## 🎯 Next Actions

1. **Read Documentation**
   - Start with QUICK_START.md
   - Follow setup checklist

2. **Verify Setup**
   - Run all 3 terminals
   - Upload ESP32 firmware
   - Check each component

3. **Test Integration**
   - See QUICK_START.md "Integration Testing"
   - Verify complete flow

4. **Deploy**
   - See SETUP_GUIDE.md "Production Deployment"
   - Setup supervisor
   - Configure nginx

---

## 📞 Quick Links

| Resource | Location |
|----------|----------|
| Project Root | `D:\laragon\www\MAPIA-main.worktrees\agents-esp32-firmware-v4-0-configuration-update` |
| MQTT Service | `app/Services/MqttService.php` |
| API Controller | `app/Http/Controllers/Api/SensorController.php` |
| Routes | `routes/api.php` |
| Database Config | `.env` |
| ESP32 Firmware | `esp32_firmware_v4.1.ino` |

---

## 📊 Project Summary

- **Version:** 4.1
- **Date:** 2026-06-04
- **Status:** ✅ Ready for Testing
- **Files Created:** 11
- **Files Modified:** 5
- **Documentation:** 5 guides
- **Code:** ~2000 lines
- **Dependencies:** php-mqtt/client ^1.8

---

**Next Step:** Open **[QUICK_START.md](./QUICK_START.md)** → ⭐
