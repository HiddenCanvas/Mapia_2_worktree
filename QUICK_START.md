# 🚀 MAPIA IoT - Quick Start Checklist

Ikuti checklist ini untuk menjalankan sistem MAPIA secara lengkap.

## ✅ Pre-Deployment Checklist

### Backend Setup

- [ ] Clone repository dan masuk folder
- [ ] Jalankan `composer install` (MQTT client sudah ditambahkan)
- [ ] Copy `.env` dari `.env.example`
- [ ] Verifikasi EMQX credentials di `.env`:
  ```
  MQTT_BROKER=x1517f89.ala.asia-southeast1.emqxsl.com
  MQTT_PORT=8883
  MQTT_USERNAME=Mapia
  MQTT_PASSWORD=Mapia123
  ```
- [ ] Jalankan `php artisan migrate --force`
- [ ] Jalankan `npm install`
- [ ] Generate APP_KEY: `php artisan key:generate`

### Database Verification

```bash
# Test database connection
php artisan db

# Verify tables created
php artisan tinker
> DB::table('sensors')->count()
> DB::table('parameter_penyiramans')->count()
> DB::table('history_kelembapans')->count()
```

### ESP32 Device Preparation

- [ ] Download & install Arduino IDE
- [ ] Install ESP32 Board Package
- [ ] Copy file `esp32_firmware_v4.1.ino`
- [ ] Update 4 konfigurasi:
  - [ ] WIFI_SSID
  - [ ] WIFI_PASSWORD
  - [ ] MQTT_USER
  - [ ] MQTT_PASS
- [ ] Verify PIN Configuration:
  - [ ] PIN_RELAY = 25
  - [ ] PIN_MOISTURE = 34
  - [ ] PIN_BTN_MODE = 0 (GPIO0)
- [ ] Calibrate moisture sensor (MOISTURE_DRY, MOISTURE_WET)

---

## 🎯 Development Environment

### Terminal 1: Laravel Server

```bash
cd D:\laragon\www\MAPIA-main.worktrees\agents-esp32-firmware-v4-0-configuration-update
php artisan serve
```

Output:

```
Laravel development server started on http://127.0.0.1:8000
```

### Terminal 2: MQTT Listener (Long-running)

```bash
php artisan mqtt:listen
```

Harus melihat output seperti:

```
🟢 MQTT Listener started...
[MQTT] ✓ Connected to broker: x1517f89.ala.asia-southeast1.emqxsl.com:8883
✓ Subscribed to topics
Listening for messages... Press Ctrl+C to stop
```

### Terminal 3: Node Dev Server

```bash
npm run dev
```

### Terminal 4 (Optional): Logs

```bash
php artisan pail
```

---

## 📱 ESP32 Upload & Testing

### Step 1: Upload Firmware

1. Open Arduino IDE
2. Paste code dari `esp32_firmware_v4.1.ino`
3. Board: ESP32 Dev Module (or similar)
4. Port: COM3 (or your device port)
5. Click Upload

### Step 2: Monitor Serial Output

Baud Rate: **115200**

Expected output:

```
[WIFI] Connecting to "Mattew's S25 Edge"...
[WIFI] ✓ Connected! IP: 192.168.1.100
[MQTT] Connecting to x1517f89.ala.asia-southeast1.emqxsl.com:8883...
╔══════════════════════════════════════╗
║  ✅  MQTT CONNECTED!                 ║
║  Broker: x1517f89.ala.asia...        ║
╚══════════════════════════════════════╝

[SENSOR] ADC:2500 → Kelembapan:62.5%
[AUTO] Cukup 62.5% >= max 70.0% → Pompa OFF
[MQTT] ✓ Status published
♥ Heartbeat — RSSI:-55 dBm | Heap:102400
```

### Step 3: Verify Data Flow

**Check 1: Is device publishing data?**

```bash
# Terminal - check Laravel logs
php artisan pail

# Should see:
# [MQTT] Data saved - Sensor: 1 | Kelembapan: 62.5%
```

**Check 2: Is data in database?**

```bash
php artisan tinker
> DB::table('history_kelembapans')->latest()->first()
# Should show latest sensor reading
```

**Check 3: Test API endpoint**

```bash
curl http://localhost:8000/api/v1/sensors/1
```

Should return sensor data.

---

## 🌐 Web Dashboard Testing

### Access Dashboard

```
http://localhost:8000
```

Login with credentials dari database.

### Create Test User

```bash
php artisan tinker
> User::create([
    'id_user' => null,
    'name' => 'Admin',
    'email' => 'admin@mapia.local',
    'password' => bcrypt('admin123'),
  ])
```

### Create Test Sensor

```php
> Sensor::create([
    'id_user' => 1,
    'nama_sensor' => 'Tanaman Test',
    'mac_address' => '68A86D123456',
    'status' => true,
  ])
```

---

## 🔌 API Testing

### Generate API Token

```bash
php artisan tinker
> $token = User::first()->createToken('Test Token')->plainTextToken
> echo $token
```

### Test Get Sensors

```bash
curl -X GET http://localhost:8000/api/v1/sensors \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### Test Update Parameter

```bash
curl -X PATCH http://localhost:8000/api/v1/sensors/1/parameter \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"min_kelembapan": 35.0, "max_kelembapan": 75.0}'
```

---

## 🧪 Integration Testing

### End-to-End Flow

1. **Device sends data:**
   ```
   ESP32 → MQTT → Laravel → Database
   ```
   ✅ Check: Data appears in `history_kelembapans` table

2. **Web updates parameter:**
   ```
   Dashboard → API → MQTT → Device
   ```
   ✅ Check: Device receives and logs parameter update

3. **Manual control:**
   ```
   Dashboard → API → MQTT → Device Relay
   ```
   ✅ Check: Pump turns ON/OFF in response

4. **Real-time updates:**
   ```
   Device → MQTT → Laravel → WebSocket → Dashboard
   ```
   ✅ Check: Dashboard updates without page refresh

---

## 🐛 Troubleshooting Quick Fixes

### MQTT Connection Failed

```
[MQTT] ✗ Failed (state=4)
```

**Fix:**

```bash
# Verify credentials in .env
# Verify EMQX broker is online
# Check firewall port 8883

# Test connection with MQTT client
# Or check logs
php artisan pail
```

### Data Not Appearing in Database

```bash
# Check if mqtt:listen is running
ps aux | grep mqtt:listen

# Check Laravel logs
php artisan pail

# Verify sensor ID exists
php artisan tinker
> Sensor::find(1)
```

### Real-time Updates Not Working

```bash
# Check Redis running
redis-cli ping
# Should return: PONG

# Verify BROADCAST_DRIVER=redis in .env

# Clear config cache
php artisan config:clear
```

### Device Can't Connect to WiFi

1. Check SSID & password correct
2. Device in range
3. Check Serial Output for details
4. Reset device (GPIO 0 button)

---

## 📊 Monitoring & Logs

### View Real-time Logs

```bash
php artisan pail
```

### MQTT Traffic Monitoring

```bash
# Using MQTT.js (if installed globally)
mqtt sub -h x1517f89.ala.asia-southeast1.emqxsl.com \
  -p 8883 \
  -u Mapia \
  -P Mapia123 \
  'mapia/sensor/+/data' \
  --insecure
```

Or use MQTT Explorer client (UI tool).

### Database Queries

```bash
php artisan tinker

# Latest sensor readings
> DB::table('history_kelembapans')->latest()->take(10)->get()

# Count records
> DB::table('history_kelembapans')->count()

# Device status
> Sensor::with('kontrolSiram', 'parameterPenyiraman')->find(1)
```

---

## 🚀 Production Deployment

### Before Going Live

- [ ] Update MQTT credentials
- [ ] Enable HTTPS
- [ ] Setup environment for production
- [ ] Configure supervisor for mqtt:listen
- [ ] Setup Redis on production
- [ ] Configure backup strategy
- [ ] Test failover scenarios
- [ ] Setup monitoring & alerts

### Supervisor Config

File: `/etc/supervisor/conf.d/mqtt-listener.conf`

```ini
[program:mqtt-listener]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan mqtt:listen
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/mqtt-listener.log
user=www-data
```

Restart supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start mqtt-listener:*
```

---

## 📞 Support

See:
- `SETUP_GUIDE.md` - Complete setup
- `API_DOCUMENTATION.md` - API details
- `esp32_firmware_v4.1.ino` - Device firmware

---

**Last Updated:** 2026-06-04
**Status:** ✅ Ready for Development/Testing
