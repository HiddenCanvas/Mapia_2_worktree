# 📡 MAPIA IoT API Documentation

## 🔑 Authentication

Gunakan **Laravel Sanctum** untuk API authentication:

```bash
# Generate API Token (untuk testing)
php artisan tinker
> User::first()->createToken('API Token')->plainTextToken
```

Gunakan token di header:

```http
Authorization: Bearer {token}
```

---

## 📌 API Endpoints

### 1. Get All Sensors

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
      "created_at": "2026-04-28T12:15:52.000000Z",
      "updated_at": "2026-06-04T10:30:00.000000Z",
      "parameter_penyiraman": {
        "id_parameter": 1,
        "id_sensor": 1,
        "min_kelembapan": 40.0,
        "max_kelembapan": 70.0
      },
      "kontrol_siram": {
        "id_kontrol_siram": 1,
        "id_sensor": 1,
        "mode_auto": true,
        "status_pompa": false,
        "created_at": "2026-04-28T12:15:52.000000Z",
        "updated_at": "2026-06-04T10:30:00.000000Z"
      }
    }
  ]
}
```

---

### 2. Get Sensor Detail + Latest Data

```http
GET /api/v1/sensors/{id}
Authorization: Bearer {token}
```

**Response:**

```json
{
  "status": "success",
  "data": {
    "sensor": {
      "id_sensor": 1,
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
      },
      "history_kelembapans": [
        {
          "id_history": 100,
          "kelembapan": 65.5,
          "kondisi": "LEMBAP",
          "uptime": 1200,
          "created_at": "2026-06-04T15:30:00.000000Z"
        }
      ]
    },
    "latest_data": {
      "id_history": 100,
      "id_sensor": 1,
      "kelembapan": 65.5,
      "kondisi": "LEMBAP",
      "uptime": 1200,
      "created_at": "2026-06-04T15:30:00.000000Z"
    }
  }
}
```

---

### 3. Update Parameter Penyiraman

Update `min_kelembapan` dan `max_kelembapan` untuk sensor.

**Endpoint:**

```http
PATCH /api/v1/sensors/{id}/parameter
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**

```json
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

**Behavior:**

- Parameter langsung dikirim ke device via MQTT topic: `mapia/sensor/{MAC}/parameter`
- Device akan menerima dan menyimpan ke NVS storage
- Update akan ditampilkan di dashboard real-time

**Validation Rules:**

- `min_kelembapan`: 0-99
- `max_kelembapan`: 1-100
- `min_kelembapan` HARUS < `max_kelembapan`

---

### 4. Change Mode (Otomatis/Manual)

```http
PATCH /api/v1/sensors/{id}/mode
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**

```json
{
  "mode": "manual"
}
```

**Valid Values:**

- `"otomatis"` - Mode otomatis (device kontrol pompa sendiri)
- `"manual"` - Mode manual (pompa dikontrol via API)

**Response:**

```json
{
  "status": "success",
  "message": "Mode updated successfully",
  "data": {
    "id_kontrol_siram": 1,
    "id_sensor": 1,
    "mode_auto": false,
    "status_pompa": false,
    "created_at": "2026-04-28T12:15:52.000000Z",
    "updated_at": "2026-06-04T15:45:00.000000Z"
  }
}
```

**Behavior:**

- Mode dikirim via MQTT: `mapia/sensor/{MAC}/mode` = "Otomatis" atau "Manual"
- Pompa otomatis dimatikan saat switching ke Manual
- Status diupdate real-time ke dashboard

---

### 5. Control Pump (ON/OFF)

Hanya bekerja dalam **mode manual**.

```http
POST /api/v1/sensors/{id}/pump
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**

```json
{
  "action": "ON"
}
```

**Valid Values:**

- `"ON"` - Nyalakan pompa
- `"OFF"` - Matikan pompa

**Response - Success:**

```json
{
  "status": "success",
  "message": "Pump control sent successfully",
  "action": "ON"
}
```

**Response - Error (Auto Mode):**

```json
{
  "status": "error",
  "message": "Cannot control pump in automatic mode"
}
```

**Behavior:**

- Command dikirim via MQTT: `mapia/actuator/{MAC}/pump` = "ON" atau "OFF"
- Pompa akan respond dalam 1-2 detik
- Status pompa akan di-update di database
- Real-time update ke dashboard via WebSocket

**Safety Features:**

- Pompa auto-shutdown setelah 5 menit (POMPA_MAX_DURASI)
- Safety alert dikirim ke `mapia/sensor/{MAC}/alert`

---

### 6. Send Sensor Data (IoT Device)

Endpoint untuk ESP32 mengirim data sensor.

```http
POST /api/v1/send-data
Content-Type: application/json
```

**⚠️ No Authentication Required** (device tidak punya token)

**Request Body:**

```json
{
  "id_sensor": 1,
  "kelembapan": 65.5,
  "ph_tanah": 6.8
}
```

**Response:**

```json
{
  "status": "success",
  "message": "Data sensor berhasil disimpan",
  "data": {
    "id_sensor": 1,
    "kelembapan": 65.5,
    "ph_tanah": 6.8,
    "created_at": "2026-06-04T15:30:00.000000Z"
  }
}
```

**Validation:**

- `id_sensor` HARUS ada di tabel sensors
- `kelembapan` & `ph_tanah` harus numeric

---

## 🔄 MQTT Messages

### Device → Server (PUBLISH)

#### Data Message

```
Topic: mapia/sensor/{MAC_ADDRESS}/data
Payload: {
  "kelembapan": 65.5,
  "id_sensor": 1,
  "pump": "ON",
  "mode": "otomatis",
  "kondisi": "LEMBAP",
  "uptime": 120
}
```

#### Status Message

```
Topic: mapia/sensor/{MAC_ADDRESS}/status
Payload: {
  "online": true,
  "pump": "ON",
  "mode": "otomatis",
  "kel": 65.5,
  "kondisi": "LEMBAP",
  "min_kel": 40.0,
  "max_kel": 70.0,
  "rssi": -55,
  "uptime": 120
}
Retain: true (persistent)
```

#### Heartbeat

```
Topic: mapia/sensor/{MAC_ADDRESS}/heartbeat
Payload: {
  "online": true,
  "uptime": 120,
  "rssi": -55,
  "heap": 102400
}
Interval: Setiap 60 detik
```

---

### Server → Device (PUBLISH)

#### Mode Control

```
Topic: mapia/sensor/{MAC_ADDRESS}/mode
Payload: "Otomatis" atau "Manual"
```

#### Parameter Update

```
Topic: mapia/sensor/{MAC_ADDRESS}/parameter
Payload: {
  "min_kel": 40.0,
  "max_kel": 70.0
}
```

#### Pump Control

```
Topic: mapia/actuator/{MAC_ADDRESS}/pump
Payload: "ON" atau "OFF"
```

#### Remote Restart

```
Topic: mapia/sensor/{MAC_ADDRESS}/reset
Payload: "RESTART"
```

---

## 🧪 Testing dengan cURL

### Get Sensors

```bash
curl -X GET http://localhost:8000/api/v1/sensors \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### Update Parameter

```bash
curl -X PATCH http://localhost:8000/api/v1/sensors/1/parameter \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "min_kelembapan": 35.0,
    "max_kelembapan": 75.0
  }'
```

### Change to Manual Mode

```bash
curl -X PATCH http://localhost:8000/api/v1/sensors/1/mode \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"mode": "manual"}'
```

### Turn On Pump

```bash
curl -X POST http://localhost:8000/api/v1/sensors/1/pump \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"action": "ON"}'
```

### Send Sensor Data

```bash
curl -X POST http://localhost:8000/api/v1/send-data \
  -H "Content-Type: application/json" \
  -d '{
    "id_sensor": 1,
    "kelembapan": 65.5,
    "ph_tanah": 6.8
  }'
```

---

## ⚠️ Error Handling

### 404 - Not Found

```json
{
  "status": "error",
  "message": "Sensor not found"
}
```

### 422 - Validation Failed

```json
{
  "status": "error",
  "message": "The min_kelembapan must be less than max_kelembapan"
}
```

### 500 - Server Error

```json
{
  "status": "error",
  "message": "Internal Server Error"
}
```

---

## 📊 Database Relations

```
Sensor (1) ─── (1) ParameterPenyiraman
   ├─ (1) ─── (1) KontrolSiram
   └─ (1) ─── (M) HistoryKelembapan
       ├─ kelembapan value
       ├─ kondisi (KERING/LEMBAP/BASAH)
       └─ timestamp
```

---

## 🔗 Real-time WebSocket

### Listen to Sensor Updates

**JavaScript (Frontend):**

```javascript
import Echo from 'laravel-echo';

// Setup Echo
window.Echo = new Echo({
    broadcaster: 'redis',
    host: window.location.hostname,
    port: 6379
});

// Listen to specific sensor
window.Echo.channel(`sensor.1`).listen('.sensor-updated', (event) => {
    console.log('Sensor 1 updated:', event);
    // {
    //   sensorId: 1,
    //   kelembapan: 65.5,
    //   kondisi: 'LEMBAP',
    //   pump: 'ON',
    //   mode: 'otomatis',
    //   timestamp: '2026-06-04T15:30:00Z'
    // }
});

// Listen to all sensors
window.Echo.channel('sensors').listen('.sensor-updated', (event) => {
    console.log('Any sensor updated:', event);
});
```

---

## 📝 Rate Limiting

API tidak memiliki rate limiting default. Untuk production, tambahkan:

```php
// In routes/api.php
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/v1/sensors', [SensorController::class, 'getSensors']);
    // ... other endpoints
});
```

---

**Last Updated:** 2026-06-04
**Version:** 1.0
