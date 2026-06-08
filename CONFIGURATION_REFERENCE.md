# ⚙️ MAPIA Configuration Reference

## 📋 System Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                   MAPIA IoT System v4.1                     │
└─────────────────────────────────────────────────────────────┘

    ESP32 Device              EMQX Cloud Broker          Laravel Backend
    ────────────              ─────────────────          ─────────────
    
    ┌──────────────┐          ┌────────────────┐        ┌──────────────┐
    │  Moisture    │          │   x1517f89...  │        │   Database   │
    │   Sensor     │          │   :8883 MQTTS  │◄──────►│  (PostgreSQL)│
    │              │          │   :8084 WS     │        │              │
    │ GPIO 34 ────┼─┐        └────────────────┘        └──────────────┘
    │              │ │MQTT Topics                               │
    │ Relay        │ │- mapia/sensor/+/data                     │
    │ GPIO 25 ────┼─┤- mapia/sensor/+/status                   │
    │              │ │- mapia/sensor/+/parameter    ┌──────────▼──────┐
    │ Button       │ │- mapia/actuator/+/pump      │  MQTT Listener   │
    │ GPIO 0  ────┼─│- mapia/sensor/+/mode        │ (Console Cmd)    │
    │              │ └──────────────────────────────┤ php artisan      │
    │ LED          │                                │ mqtt:listen      │
    │ GPIO 2  ────┼─────────────────────────────────┴──────────────────┘
    └──────────────┘                                        │
         │                                                  │
         │                 Sensor Data             Database Updates
         │              (via REST API)              (Events Broadcasting)
         │                   │                           │
         └───────────────────┼───────────────────────────┘
                             │
                             ▼
                    ┌─────────────────┐
                    │ Web Dashboard   │
                    │ (Blade/Blade)   │
                    │ Real-time via   │
                    │ WebSocket       │
                    └─────────────────┘
```

---

## 🔧 Hardware Configuration

### ESP32 Pinout

```cpp
#define PIN_MOISTURE    34    // ADC input (soil moisture sensor)
#define PIN_RELAY       25    // Digital output (pump relay)
#define PIN_BTN_MODE     0    // Digital input (boot button)
#define PIN_LED_STATUS   2    // Digital output (onboard LED)
```

### Sensor Calibration

**Moisture Sensor (Capacitive):**

```cpp
#define MOISTURE_DRY     2800    // ADC value when dry (air)
#define MOISTURE_WET     1200    // ADC value when wet (water)

// Calculation: percentage = (ADC - WET) / (DRY - WET) * 100
// Range: 0-100%
// Constrained to prevent invalid readings
```

### Relay Configuration

```cpp
// Active LOW (typical for most relay modules)
digitalWrite(PIN_RELAY, LOW);   // Pump ON
digitalWrite(PIN_RELAY, HIGH);  // Pump OFF

// Safety: Maximum pump runtime = 5 minutes (300000ms)
#define POMPA_MAX_DURASI 300000UL
```

---

## 🌐 Network Configuration

### WiFi Settings

```cpp
#define WIFI_SSID       "Mattew's S25 Edge"
#define WIFI_PASSWORD   "uqrmntxbbbg92ay"

// Retry Logic:
// - Attempt connection for 30 seconds
// - If failed, restart ESP32
// - Will retry on next boot
```

### MQTT Broker (EMQX Cloud)

```
Hostname: x1517f89.ala.asia-southeast1.emqxsl.com
Port:     8883 (MQTTS - TLS encrypted)
Port:     8084 (WebSocket - optional)

Credentials:
- Username: Mapia
- Password: Mapia123

Client ID: mapia-esp32-{MAC_ADDRESS}
Keep-Alive: 60 seconds

TLS Configuration:
- setUseTls(true)
- setVerifyPeer(false)  // Self-signed allowed
```

### MQTT Topics Schema

```
# Publishing (Device → Server)
mapia/sensor/{MAC}/data        → Sensor readings (QoS 1)
mapia/sensor/{MAC}/status      → Device status (QoS 1, Retained)
mapia/sensor/{MAC}/heartbeat   → Keep-alive (QoS 1)
mapia/sensor/{MAC}/alert       → Safety alerts (QoS 1)

# Subscribing (Server → Device)
mapia/sensor/{MAC}/mode        → Mode control
mapia/sensor/{MAC}/parameter   → Parameter updates
mapia/actuator/{MAC}/pump      → Pump control
mapia/sensor/{MAC}/reset       → Remote restart
```

**MAC Address Format:** `68A86DXXXXXX` (uppercase, no colons)

---

## 💾 Laravel Configuration

### Database

```env
# .env
DB_CONNECTION=sqlite (development)
# For production: use PostgreSQL (Neon)
# DB_CONNECTION=pgsql
# DB_HOST=ep-xxx.neon.tech
# DB_PORT=5432
# DB_DATABASE=mapia
# DB_USERNAME=...
# DB_PASSWORD=...
```

### MQTT Service

```env
# .env
MQTT_BROKER=x1517f89.ala.asia-southeast1.emqxsl.com
MQTT_PORT=8883
MQTT_WEBSOCKET_PORT=8084
MQTT_USERNAME=Mapia
MQTT_PASSWORD=Mapia123
MQTT_CLIENT_ID=mapia-laravel-
MQTT_KEEP_ALIVE=60
```

### Broadcasting (Real-time)

```env
# .env
BROADCAST_DRIVER=redis
BROADCAST_CONNECTION=default

# For production with WebSocket
BROADCAST_DRIVER=redis
# Setup Pusher/Laravel Echo server separately
```

### Queue & Cache

```env
# .env
QUEUE_CONNECTION=database
CACHE_STORE=database

# For better performance:
QUEUE_CONNECTION=redis
CACHE_STORE=redis
```

---

## 📊 Database Schema

### sensors

```sql
CREATE TABLE sensors (
    id_sensor BIGINT PRIMARY KEY AUTO_INCREMENT,
    id_user BIGINT NOT NULL,
    nama_sensor VARCHAR(255),
    mac_address VARCHAR(255),
    status BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE
);
```

### parameter_penyiramans

```sql
CREATE TABLE parameter_penyiramans (
    id_parameter BIGINT PRIMARY KEY AUTO_INCREMENT,
    id_sensor BIGINT NOT NULL,
    min_kelembapan FLOAT,
    max_kelembapan FLOAT,
    min_ph BIGINT,
    max_ph BIGINT,
    FOREIGN KEY (id_sensor) REFERENCES sensors(id_sensor) ON DELETE CASCADE
);
```

### kontrol_sirams

```sql
CREATE TABLE kontrol_sirams (
    id_kontrol_siram BIGINT PRIMARY KEY AUTO_INCREMENT,
    id_sensor BIGINT NOT NULL,
    mode_auto BOOLEAN DEFAULT true,
    status_pompa BOOLEAN DEFAULT false,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (id_sensor) REFERENCES sensors(id_sensor) ON DELETE CASCADE
);
```

### history_kelembapans

```sql
CREATE TABLE history_kelembapans (
    id_history BIGINT PRIMARY KEY AUTO_INCREMENT,
    id_sensor BIGINT NOT NULL,
    kelembapan FLOAT,
    kondisi VARCHAR(255) DEFAULT 'UNKNOWN',
    uptime BIGINT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (id_sensor) REFERENCES sensors(id_sensor) ON DELETE CASCADE
);
```

---

## 🔄 Data Flow

### 1. Sensor Reading Cycle (Device)

```
┌─────────────────────────────────┐
│ Every 30 seconds (INTERVAL_KIRIM)
└─────────────────────────────────┘
           │
           ▼
┌──────────────────────┐
│ Read Moisture ADC    │
│ (10 samples average) │
└──────────────────────┘
           │
           ▼
┌──────────────────────┐
│ Calculate % value    │
│ Constrain 0-100%     │
└──────────────────────┘
           │
           ▼
┌──────────────────────┐
│ Check Auto Mode      │
│ - If dry → Pump ON   │
│ - If wet → Pump OFF  │
└──────────────────────┘
           │
           ▼
┌──────────────────────┐
│ Publish to MQTT:     │
│ mapia/sensor/+/data  │
│ {kelembapan, ...}    │
└──────────────────────┘
```

### 2. MQTT Listener (Backend)

```
┌──────────────────────────┐
│ MQTT Listener Command    │
│ php artisan mqtt:listen  │
└──────────────────────────┘
           │
           ▼
┌──────────────────────────┐
│ Subscribe to Topics:     │
│ - mapia/sensor/+/data    │
│ - mapia/sensor/+/status  │
│ - mapia/sensor/+/heart..│
└──────────────────────────┘
           │
           ▼
┌──────────────────────────┐
│ Message Received         │
│ Parse JSON payload       │
└──────────────────────────┘
           │
           ▼
┌──────────────────────────┐
│ Save to Database:        │
│ history_kelembapans      │
└──────────────────────────┘
           │
           ▼
┌──────────────────────────┐
│ Broadcast Event:         │
│ SensorDataUpdated        │
└──────────────────────────┘
           │
           ▼
┌──────────────────────────┐
│ WebSocket to Dashboard   │
│ (Real-time display)      │
└──────────────────────────┘
```

### 3. Web Control Flow

```
┌──────────────────────┐
│ Dashboard Action     │
│ (User clicks button) │
└──────────────────────┘
           │
           ▼
┌──────────────────────┐
│ API Request:         │
│ PATCH /api/v1/..     │
└──────────────────────┘
           │
           ▼
┌──────────────────────┐
│ Laravel Controller   │
│ Update Database      │
└──────────────────────┘
           │
           ▼
┌──────────────────────┐
│ Publish to MQTT:     │
│ Command Topic        │
└──────────────────────┘
           │
           ▼
┌──────────────────────┐
│ ESP32 Receives       │
│ Via onMqttMessage()  │
└──────────────────────┘
           │
           ▼
┌──────────────────────┐
│ Execute Command:     │
│ Relay ON/OFF         │
└──────────────────────┘
           │
           ▼
┌──────────────────────┐
│ Confirm via Status   │
│ Publish /status      │
└──────────────────────┘
```

---

## 🔐 Security Considerations

### MQTT Security

```
✅ DONE:
- TLS 1.2 encryption (port 8883)
- Authentication (username/password)
- Topic-based access control

⚠️ TODO (Production):
- Change default credentials
- Implement ACL (Access Control Lists)
- Setup certificate pinning
- Enable MQTT 5.0 security features
```

### API Security

```
✅ DONE:
- Laravel Sanctum tokens
- CORS protection
- CSRF tokens (in forms)

⚠️ TODO (Production):
- Rate limiting
- Input validation
- SQL injection prevention (using ORM)
- XSS protection
- HTTPS only
```

---

## 🚨 Monitoring & Alerts

### Key Metrics

```
1. Device Connectivity
   - Last heartbeat received
   - RSSI signal strength
   - Connection uptime

2. Sensor Health
   - Kelembapan range (0-100%)
   - Kondisi (KERING/LEMBAP/BASAH)
   - Data freshness

3. Pump Status
   - Current state (ON/OFF)
   - Total runtime
   - Safety limits (max 5 min)

4. System Health
   - Heap memory available
   - MQTT connection status
   - API response time
```

### Alert Conditions

```
🔴 Critical:
- Device offline > 5 minutes
- Pump running > 5 minutes
- Kelembapan < 0% or > 100%

🟡 Warning:
- RSSI < -85 dBm
- Heap < 50KB
- API response time > 1s
```

---

## 🔄 Update & Maintenance

### Firmware Updates

Device tidak support OTA (Over-The-Air). Update requires:

1. Restart device
2. Re-upload via Arduino IDE
3. Check serial output for errors

### Database Maintenance

```bash
# Backup
mysqldump -u user -p mapia > backup.sql

# Optimize tables
OPTIMIZE TABLE history_kelembapans;

# Cleanup old data (optional)
DELETE FROM history_kelembapans 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

---

## 📱 Mobile & Remote Access

### Access Restrictions

```
Current:
- Local network only (http://192.168.x.x:8000)

Production:
- Setup reverse proxy (nginx)
- Enable HTTPS
- Configure domain name
- Use authentication
```

---

**Last Updated:** 2026-06-04
**Version:** 4.1
**Status:** Production-Ready for Testing
