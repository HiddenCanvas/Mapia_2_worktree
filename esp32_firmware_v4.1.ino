// ╔══════════════════════════════════════════════════════════╗
// ║          MAPIA ESP32 — Firmware v4.1 (EMQX)              ║
// ║            Production-Ready Configuration                ║
// ╚══════════════════════════════════════════════════════════╝

#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>

// ╔══════════════════════════════════════════════════════════╗
// ║   ✏️   KONFIGURASI — EDIT BAGIAN INI SAJA                  ║
// ╚══════════════════════════════════════════════════════════╝

// ── WiFi ────────────────────────────────────────────────────
#define WIFI_SSID       "Mattew's S25 Edge"
#define WIFI_PASSWORD   "uqrmntxbbbg92ay"

// ── EMQX Cloud Broker ───────────────────────────────────────
#define MQTT_BROKER    "x1517f89.ala.asia-southeast1.emqxsl.com"
#define MQTT_PORT      8883
#define MQTT_USER      "Mapia"
#define MQTT_PASS      "Mapia123"

// ── Sensor ──────────────────────────────────────────────────
#define SENSOR_DB_ID    1

// ── Kalibrasi Kelembapan ─────────────────────────────────────
#define MOISTURE_DRY    2800
#define MOISTURE_WET    1200

// ── Parameter Penyiraman Default ─────────────────────────────
#define DEFAULT_MIN_KEL  40.0
#define DEFAULT_MAX_KEL  70.0

// ╚══════════════════════════════════════════════════════════╝

#define PIN_MOISTURE    34
#define PIN_RELAY       25
#define PIN_BTN_MODE     0
#define PIN_LED_STATUS   2

#define INTERVAL_KIRIM      30000UL
#define INTERVAL_HEARTBEAT  60000UL
#define POMPA_MAX_DURASI   300000UL
#define DEBOUNCE_MS           200UL
#define WIFI_TIMEOUT        30000UL
#define MQTT_TIMEOUT        10000UL

Preferences       prefs;
WiFiClientSecure  wifiClient;
PubSubClient      mqttClient(wifiClient);

// ── State ───────────────────────────────────────────────────
bool    modeAuto   = true;
bool    pumpOn     = false;
float   lastKel    = 0;
String  kondisi    = "?";
float   pMinKel    = DEFAULT_MIN_KEL;
float   pMaxKel    = DEFAULT_MAX_KEL;

String  macAddr;
String  tData, tPump, tMode, tParam, tStatus, tAlert, tHeart;

unsigned long tLastKirim   = 0;
unsigned long tLastHeart   = 0;
unsigned long tPumpStart   = 0;
unsigned long tLastBtn     = 0;
unsigned long tLastWiFi    = 0;
unsigned long tLastMqtt    = 0;
bool          btnLast      = HIGH;

// ════════════════════════════════════════════════════════════
// NVS — Simpan parameter
// ════════════════════════════════════════════════════════════
void simpanParameter() {
  prefs.begin("mapia", false);
  prefs.putFloat("minkel", pMinKel);
  prefs.putFloat("maxkel", pMaxKel);
  prefs.end();
  Serial.printf("[NVS] Parameter disimpan: %.1f – %.1f%%\n", pMinKel, pMaxKel);
}

void muatParameter() {
  prefs.begin("mapia", true);
  pMinKel = prefs.getFloat("minkel", DEFAULT_MIN_KEL);
  pMaxKel = prefs.getFloat("maxkel", DEFAULT_MAX_KEL);
  prefs.end();
  Serial.printf("[NVS] Parameter dimuat: %.1f – %.1f%%\n", pMinKel, pMaxKel);
}

// ════════════════════════════════════════════════════════════
// SENSOR KELEMBAPAN
// ════════════════════════════════════════════════════════════
float bacaKelembapan() {
  long total = 0;
  for (int i = 0; i < 10; i++) {
    total += analogRead(PIN_MOISTURE);
    delay(10);
  }
  int raw = total / 10;
  float pct = (float)(raw - MOISTURE_DRY) / (MOISTURE_WET - MOISTURE_DRY) * 100.0;
  pct = constrain(pct, 0.0, 100.0);
  return pct;
}

String getKondisi(float k) {
  if (k < pMinKel) return "KERING";
  if (k > pMaxKel) return "BASAH";
  return "LEMBAP";
}

// ════════════════════════════════════════════════════════════
// RELAY / POMPA
// ════════════════════════════════════════════════════════════
void nyalakanPompa() {
  if (pumpOn) return;
  digitalWrite(PIN_RELAY, LOW);
  pumpOn = true;
  tPumpStart = millis();
  Serial.println("[POMPA] >>> ON <<<");
  for (int i = 0; i < 2; i++) {
    digitalWrite(PIN_LED_STATUS, LOW);
    delay(80);
    digitalWrite(PIN_LED_STATUS, HIGH);
    delay(80);
  }
}

void matikanPompa() {
  if (!pumpOn) return;
  digitalWrite(PIN_RELAY, HIGH);
  pumpOn = false;
  Serial.printf("[POMPA] >>> OFF <<< (nyala %lu detik)\n", (millis() - tPumpStart) / 1000);
}

void cekBatasPompa() {
  if (pumpOn && (millis() - tPumpStart >= POMPA_MAX_DURASI)) {
    Serial.println("[SAFETY] Pompa > 5 menit! Dimatikan paksa.");
    matikanPompa();
    mqttClient.publish(tAlert.c_str(), "{\"jenis\":\"SAFETY\",\"pesan\":\"Pompa dimatikan paksa >5 menit\"}", false);
  }
}

// ════════════════════════════════════════════════════════════
// TOMBOL FISIK
// ════════════════════════════════════════════════════════════
void cekTombol() {
  bool btnNow = digitalRead(PIN_BTN_MODE);
  if (btnLast == HIGH && btnNow == LOW) {
    if (millis() - tLastBtn > DEBOUNCE_MS) {
      tLastBtn = millis();
      modeAuto = !modeAuto;
      matikanPompa();

      Serial.println("════════════════════════════════");
      Serial.printf("  MODE → %s\n", modeAuto ? "OTOMATIS 🤖" : "MANUAL 👋");
      Serial.println("════════════════════════════════");

      mqttClient.publish(tMode.c_str(), modeAuto ? "Otomatis" : "Manual", false);
    }
  }
  btnLast = btnNow;
}

// ════════════════════════════════════════════════════════════
// LOGIKA OTOMATIS
// ════════════════════════════════════════════════════════════
void prosesOtomatis(float kel) {
  if (!modeAuto) return;
  if (kel < pMinKel) {
    Serial.printf("[AUTO] Kering %.1f%% < min %.1f%% → Pompa ON\n", kel, pMinKel);
    nyalakanPompa();
  } else if (kel >= pMaxKel) {
    Serial.printf("[AUTO] Cukup %.1f%% >= max %.1f%% → Pompa OFF\n", kel, pMaxKel);
    matikanPompa();
  }
}

// ════════════════════════════════════════════════════════════
// MQTT PUBLISH
// ════════════════════════════════════════════════════════════
void kirimData(float kel) {
  StaticJsonDocument<200> doc;
  doc["kelembapan"] = round(kel * 10) / 10.0;
  doc["id_sensor"]  = SENSOR_DB_ID;
  doc["pump"]       = pumpOn ? "ON" : "OFF";
  doc["mode"]       = modeAuto ? "otomatis" : "manual";
  doc["kondisi"]    = kondisi;
  doc["uptime"]     = millis() / 1000;

  char buf[200];
  serializeJson(doc, buf);
  bool ok = mqttClient.publish(tData.c_str(), buf, false);
  Serial.printf("[MQTT] %s Kirim data: %s\n", ok ? "✓" : "✗", buf);
}

void kirimStatus() {
  StaticJsonDocument<256> doc;
  doc["online"]   = true;
  doc["pump"]     = pumpOn ? "ON" : "OFF";
  doc["mode"]     = modeAuto ? "otomatis" : "manual";
  doc["kel"]      = round(lastKel * 10) / 10.0;
  doc["kondisi"]  = kondisi;
  doc["min_kel"]  = pMinKel;
  doc["max_kel"]  = pMaxKel;
  doc["rssi"]     = WiFi.RSSI();
  doc["uptime"]   = millis() / 1000;

  char buf[256];
  serializeJson(doc, buf);
  mqttClient.publish(tStatus.c_str(), buf, true);
  Serial.printf("[MQTT] ✓ Status published\n");
}

void kirimHeartbeat() {
  StaticJsonDocument<100> doc;
  doc["online"] = true;
  doc["uptime"] = millis() / 1000;
  doc["rssi"]   = WiFi.RSSI();
  doc["heap"]   = ESP.getFreeHeap();

  char buf[100];
  serializeJson(doc, buf);
  mqttClient.publish(tHeart.c_str(), buf, false);
  Serial.printf("[MQTT] ♥ Heartbeat — RSSI:%d dBm | Heap:%u\n", WiFi.RSSI(), ESP.getFreeHeap());
}

// ════════════════════════════════════════════════════════════
// MQTT CALLBACK
// ════════════════════════════════════════════════════════════
void onMqttMessage(char* topic, byte* payload, unsigned int len) {
  String t   = String(topic);
  String msg = "";
  for (unsigned int i = 0; i < len; i++) msg += (char)payload[i];
  msg.trim();

  Serial.printf("[MQTT] ← %s = %s\n", topic, msg.c_str());

  // ── PUMP CONTROL ────────────────────────────────────────
  if (t == tPump) {
    if (!modeAuto) {
      if      (msg == "ON")  { nyalakanPompa(); }
      else if (msg == "OFF") { matikanPompa(); }
    } else {
      Serial.println("[POMPA] Ignored — Auto mode active");
    }
    return;
  }

  // ── MODE CONTROL ────────────────────────────────────────
  if (t == tMode) {
    bool prev = modeAuto;
    if      (msg == "Otomatis" || msg == "AUTO")   modeAuto = true;
    else if (msg == "Manual"   || msg == "MANUAL") modeAuto = false;
    if (modeAuto != prev) {
      matikanPompa();
      Serial.printf("[MODE] Changed → %s\n", modeAuto ? "OTOMATIS" : "MANUAL");
      kirimStatus();
    }
    return;
  }

  // ── PARAMETER UPDATE ────────────────────────────────────
  if (t == tParam) {
    StaticJsonDocument<128> doc;
    DeserializationError err = deserializeJson(doc, msg);
    if (!err) {
      bool changed = false;
      if (doc.containsKey("min_kel")) {
        pMinKel = constrain(doc["min_kel"].as<float>(), 0, 99);
        changed = true;
      }
      if (doc.containsKey("max_kel")) {
        pMaxKel = constrain(doc["max_kel"].as<float>(), 1, 100);
        changed = true;
      }
      if (pMinKel >= pMaxKel) {
        pMinKel = DEFAULT_MIN_KEL;
        pMaxKel = DEFAULT_MAX_KEL;
      }
      if (changed) {
        simpanParameter();
        Serial.printf("[PARAM] Updated → [%.1f – %.1f%%]\n", pMinKel, pMaxKel);
        kirimStatus();
      }
    }
    return;
  }

  // ── RESTART ────────────────────────────────────────────
  String tReset = "mapia/sensor/" + macAddr + "/reset";
  if (t == tReset && msg == "RESTART") {
    Serial.println("[CMD] Remote restart requested...");
    delay(500);
    ESP.restart();
  }
}

// ════════════════════════════════════════════════════════════
// WIFI KONEKSI (dengan retry logic)
// ════════════════════════════════════════════════════════════
bool koneksiWifi() {
  if (WiFi.status() == WL_CONNECTED) {
    return true;
  }

  unsigned long now = millis();
  if (now - tLastWiFi < WIFI_TIMEOUT) {
    return false;
  }
  tLastWiFi = now;

  WiFi.disconnect(true);
  delay(300);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  Serial.printf("[WIFI] Connecting to '%s'...", WIFI_SSID);
  
  unsigned long startTime = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - startTime < WIFI_TIMEOUT) {
    delay(500);
    Serial.print(".");
    digitalWrite(PIN_LED_STATUS, !digitalRead(PIN_LED_STATUS));
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.printf("\n[WIFI] ✓ Connected! IP: %s\n", WiFi.localIP().toString().c_str());
    return true;
  } else {
    Serial.println("\n[WIFI] ✗ Timeout");
    return false;
  }
}

// ════════════════════════════════════════════════════════════
// MQTT KONEKSI (dengan retry logic)
// ════════════════════════════════════════════════════════════
bool koneksiMqtt() {
  if (mqttClient.connected()) {
    return true;
  }

  unsigned long now = millis();
  if (now - tLastMqtt < MQTT_TIMEOUT) {
    return false;
  }
  tLastMqtt = now;

  String clientId = "mapia-esp32-" + macAddr;
  String willPayload = "{\"online\":false,\"pump\":\"OFF\"}";

  Serial.printf("[MQTT] Connecting to %s:%d...\n", MQTT_BROKER, MQTT_PORT);

  if (mqttClient.connect(
      clientId.c_str(),
      MQTT_USER, MQTT_PASS,
      tStatus.c_str(), 1, true, willPayload.c_str()
  )) {
    Serial.println("╔══════════════════════════════════════╗");
    Serial.println("║  ✅  MQTT CONNECTED!                 ║");
    Serial.printf("║  Broker: %-30s║\n", MQTT_BROKER);
    Serial.println("╚══════════════════════════════════════╝\n");
    digitalWrite(PIN_LED_STATUS, HIGH);

    mqttClient.subscribe(tPump.c_str());
    mqttClient.subscribe(tMode.c_str());
    mqttClient.subscribe(tParam.c_str());
    mqttClient.subscribe(("mapia/sensor/" + macAddr + "/reset").c_str());

    kirimStatus();
    return true;
  } else {
    Serial.printf("[MQTT] ✗ Failed (state=%d)\n", mqttClient.state());
    return false;
  }
}

// ════════════════════════════════════════════════════════════
// SETUP
// ════════════════════════════════════════════════════════════
void setup() {
  Serial.begin(115200);
  delay(1000);

  pinMode(PIN_RELAY,      OUTPUT);
  pinMode(PIN_LED_STATUS, OUTPUT);
  pinMode(PIN_BTN_MODE,   INPUT_PULLUP);
  digitalWrite(PIN_RELAY,      HIGH);
  digitalWrite(PIN_LED_STATUS, LOW);

  analogReadResolution(12);
  analogSetAttenuation(ADC_11db);

  muatParameter();
  koneksiWifi();

  macAddr = WiFi.macAddress();
  macAddr.replace(":", "");
  
  tData   = "mapia/sensor/"   + macAddr + "/data";
  tPump   = "mapia/actuator/" + macAddr + "/pump";
  tMode   = "mapia/sensor/"   + macAddr + "/mode";
  tParam  = "mapia/sensor/"   + macAddr + "/parameter";
  tStatus = "mapia/sensor/"   + macAddr + "/status";
  tAlert  = "mapia/sensor/"   + macAddr + "/alert";
  tHeart  = "mapia/sensor/"   + macAddr + "/heartbeat";

  wifiClient.setInsecure();
  mqttClient.setServer(MQTT_BROKER, MQTT_PORT);
  mqttClient.setCallback(onMqttMessage);
  mqttClient.setKeepAlive(60);
  mqttClient.setBufferSize(512);

  koneksiMqtt();
}

// ════════════════════════════════════════════════════════════
// LOOP
// ════════════════════════════════════════════════════════════
void loop() {
  // ── Maintain WiFi & MQTT ────────────────────────────────
  if (!koneksiWifi()) {
    digitalWrite(PIN_LED_STATUS, LOW);
    delay(100);
    return;
  }

  if (!koneksiMqtt()) {
    digitalWrite(PIN_LED_STATUS, LOW);
    delay(100);
    return;
  }

  mqttClient.loop();
  cekTombol();
  cekBatasPompa();

  unsigned long now = millis();

  // ── Publish sensor data ─────────────────────────────────
  if (now - tLastKirim >= INTERVAL_KIRIM) {
    tLastKirim = now;
    lastKel = bacaKelembapan();
    kondisi = getKondisi(lastKel);

    prosesOtomatis(lastKel);
    kirimData(lastKel);
  }

  // ── Publish heartbeat ───────────────────────────────────
  if (now - tLastHeart >= INTERVAL_HEARTBEAT) {
    tLastHeart = now;
    kirimHeartbeat();
  }

  delay(10);
}
