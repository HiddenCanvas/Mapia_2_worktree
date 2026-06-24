#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>

const char* ROOT_CA = R"EOF(-----BEGIN CERTIFICATE-----
MIIDjjCCAnagAwIBAgIQAzrx5qcRqaC7KGSxHQn65TANBgkqhkiG9w0BAQsFADBh
MQswCQYDVQQGEwJVUzEVMBMGA1UEChMMRGlnaUNlcnQgSW5jMRkwFwYDVQQLExB3
d3cuZGlnaWNlcnQuY29tMSAwHgYDVQQDExdEaWdpQ2VydCBHbG9iYWwgUm9vdCBH
MjAeFw0xMzA4MDExMjAwMDBaFw0zODAxMTUxMjAwMDBaMGExCzAJBgNVBAYTAlVT
MRUwEwYDVQQKEwxEaWdpQ2VydCBJbmMxGTAXBgNVBAsTEHd3dy5kaWdpY2VydC5j
b20xIDAeBgNVBAMTF0RpZ2lDZXJ0IEdsb2JhbCBSb290IEcyMIIBIjANBgkqhkiG
9w0BAQEFAAOCAQ8AMIIBCgKCAQEAuzfNNNx7a8myaJCtSnX/RrohCgiN9RlUyfuI
2/Ou8jqJkTx65qsGGmvPrC3oXgkkRLpimn7Wo6h+4FR1IAWsULecYxpsMNzaHxmx
1x7e/dfgy5SDN67sH0NO3Xss0r0upS/kqbitOtSZpLYl6ZtrAGCSYP9PIUkY92eQ
q2EGnI/yuum06ZIya7XzV+hdG82MHauVBJVJ8zUtluNJbd134/tJS7SsVQepj5Wz
tCO7TG1F8PapspUwtP1MVYwnSlcUfIKdzXOS0xZKBgyMUNGPHgm+F6HmIcr9g+UQ
vIOlCsRnKPZzFBQ9RnbDhxSJITRNrw9FDKZJobq7nMWxM4MphQIDAQABo0IwQDAP
BgNVHRMBAf8EBTADAQH/MA4GA1UdDwEB/wQEAwIBhjAdBgNVHQ4EFgQUTiJUIBiV
5uNu5g/6+rkS7QYXjzkwDQYJKoZIhvcNAQELBQADggEBAGBnKJRvDkhj6zHd6mcY
1Yl9PMWLSn/pvtsrF9+wX3N3KjITOYFnQoQj8kVnNeyIv/iPsGEMNKSuIEyExtv4
NeF22d+mQrvHRAiGfzZ0JFrabA0UWTW98kndth/Jsw1HKj2ZL7tcu7XUIOGZX1NG
Fdtom/DzMNU+MeKNhJ7jitralj41E6Vf8PlwUHBHQRFXGU7Aj64GxJUTFy8bJZ91
8rGOmaFvE7FBcf6IKshPECBV1/MUReXgRPTqh5Uykw7+U0b6LJ3/iyK5S9kJRaTe
pLiaWN0bfVKfjllDiIGknibVb63dDcY3fe0Dkhvld1927jyNxF1WW6LZZm6zNTfl
MrY=
-----END CERTIFICATE-----)EOF";

const char* WIFI_SSID     = "GGgaming";
const char* WIFI_PASSWORD = "77778888";

const char* MQTT_BROKER = "x1517f89.ala.asia-southeast1.emqxsl.com";
const int   MQTT_PORT   = 8883;
const char* MQTT_USER   = "Mapia";
const char* MQTT_PASS   = "Mapia123";

const int SENSOR_DB_ID = 1;

const int PIN_MOISTURE = 35;
const int PIN_RELAY    = 26;

// Relay diasumsikan benar di GPIO26. Mayoritas relay 5V aktif LOW.
// Jika fisik relay terbalik, ubah true menjadi false.
const bool RELAY_ACTIVE_LOW = true;

const unsigned long INTERVAL_KIRIM     = 10000;
const unsigned long INTERVAL_HEARTBEAT = 10000;

// Kalibrasi awal. Lihat raw di Serial Monitor lalu sesuaikan jika perlu.
const int MOISTURE_DRY = 2800;
const int MOISTURE_WET = 1200;
const float DUMMY_PH_VALUE = 6.5;

// LCD I2C: SDA GPIO21, SCL GPIO22. Jika tidak tampil, coba 0x3F.
LiquidCrystal_I2C lcd(0x27, 16, 2);

WiFiClientSecure wifiClient;
PubSubClient mqttClient(wifiClient);

String macAddress;
String topicData;
String topicStatus;
String topicHeartbeat;
String topicPump;
String topicMode;
String topicParameter;

bool modeAuto = true;
bool pumpStatus = false;
float kelembapanTerakhir = 0.0;

float minKelembapan = 40.0;
float maxKelembapan = 70.0;

unsigned long lastKirim = 0;
unsigned long lastHeartbeat = 0;

void updateLCD() {
  lcd.setCursor(0, 0);
  lcd.print("Lembab:");
  lcd.print(kelembapanTerakhir, 1);
  lcd.print("%");
  for (int i = 0; i < 4; i++) lcd.print(" ");

  lcd.setCursor(0, 1);
  lcd.print(pumpStatus ? "Pompa:ON  " : "Pompa:OFF ");
  lcd.print(modeAuto ? "[Auto]" : "[Man] ");
}

void tulisRelay(bool nyala) {
  pumpStatus = nyala;

  int level;
  if (RELAY_ACTIVE_LOW) {
    level = nyala ? LOW : HIGH;
  } else {
    level = nyala ? HIGH : LOW;
  }

  digitalWrite(PIN_RELAY, level);

  Serial.println(nyala ? "[POMPA] >>> ON <<<" : "[POMPA] --- OFF ---");
  Serial.printf("[RELAY] GPIO%d=%s activeLow=%s\n",
                PIN_RELAY,
                digitalRead(PIN_RELAY) == HIGH ? "HIGH" : "LOW",
                RELAY_ACTIVE_LOW ? "true" : "false");

  updateLCD();
}

void nyalakanPompa() {
  if (!pumpStatus) {
    Serial.println("[POMPA] Nyalakan pompa...");
    tulisRelay(true);
  } else {
    Serial.println("[POMPA] Sudah ON, skip.");
  }
}

void matikanPompa() {
  tulisRelay(false);
}

float bacaKelembapan() {
  long total = 0;

  for (int i = 0; i < 10; i++) {
    total += analogRead(PIN_MOISTURE);
    delay(10);
  }

  int raw = total / 10;
  float persen = map(raw, MOISTURE_DRY, MOISTURE_WET, 0, 100);
  persen = constrain(persen, 0.0, 100.0);

  kelembapanTerakhir = persen;
  Serial.printf("[SENSOR] raw=%d kelembapan=%.1f%%\n", raw, persen);
  return persen;
}

void publishJson(const String& topic, JsonDocument& doc, bool retained = false) {
  char payload[256];
  serializeJson(doc, payload, sizeof(payload));

  if (mqttClient.publish(topic.c_str(), payload, retained)) {
    Serial.printf("[MQTT] publish %s -> %s\n", topic.c_str(), payload);
  } else {
    Serial.printf("[MQTT] gagal publish %s state=%d\n", topic.c_str(), mqttClient.state());
  }
}

void kirimData(float kelembapan) {
  StaticJsonDocument<256> doc;
  doc["id_sensor"] = SENSOR_DB_ID;
  doc["kelembapan"] = round(kelembapan * 10) / 10.0;
  doc["ph_tanah"] = DUMMY_PH_VALUE;
  doc["pump"] = pumpStatus ? "ON" : "OFF";
  doc["mode"] = modeAuto ? "otomatis" : "manual";
  doc["uptime"] = millis() / 1000;
  publishJson(topicData, doc, false);
}

void kirimStatus(bool online) {
  StaticJsonDocument<160> doc;
  doc["online"] = online;
  doc["mac"] = macAddress;
  doc["pump"] = pumpStatus ? "ON" : "OFF";
  doc["mode"] = modeAuto ? "otomatis" : "manual";
  publishJson(topicStatus, doc, true);
}

void kirimHeartbeat() {
  StaticJsonDocument<160> doc;
  doc["rssi"] = WiFi.RSSI();
  doc["heap"] = ESP.getFreeHeap();
  doc["uptime"] = millis() / 1000;
  publishJson(topicHeartbeat, doc, false);
}

void prosesOtomatis(float kelembapan) {
  if (!modeAuto) {
    Serial.println("[AUTO] Mode manual aktif, skip.");
    return;
  }

  Serial.printf("[AUTO] %.1f%% min=%.1f max=%.1f\n", kelembapan, minKelembapan, maxKelembapan);

  if (kelembapan < minKelembapan) {
    Serial.println("[AUTO] KERING -> pompa ON");
    nyalakanPompa();
  } else if (kelembapan >= maxKelembapan) {
    Serial.println("[AUTO] BASAH -> pompa OFF");
    matikanPompa();
  } else {
    Serial.println("[AUTO] NORMAL -> pompa OFF");
    matikanPompa();
  }
}

void handlePumpCommand(String pesan) {
  pesan.trim();
  pesan.toUpperCase();

  Serial.printf("[PUMP CMD] '%s' mode=%s\n", pesan.c_str(), modeAuto ? "Auto" : "Manual");

  if (pesan == "ON") {
    if (modeAuto) {
      Serial.println("[PUMP CMD] Ditolak - mode otomatis aktif.");
      return;
    }

    nyalakanPompa();
    kirimStatus(true);
    return;
  }

  if (pesan == "OFF") {
    matikanPompa();
    kirimStatus(true);
    return;
  }

  Serial.printf("[PUMP CMD] Tidak dikenal: '%s'\n", pesan.c_str());
}

void handleModeCommand(String pesan) {
  pesan.trim();

  String lower = pesan;
  lower.toLowerCase();

  Serial.printf("[MODE CMD] '%s'\n", pesan.c_str());

  if (lower == "otomatis" || lower == "auto") {
    modeAuto = true;
    matikanPompa();
    Serial.println("[MODE CMD] Mode OTOMATIS");
  } else if (lower == "manual") {
    modeAuto = false;
    matikanPompa();
    Serial.println("[MODE CMD] Mode MANUAL");
  } else {
    Serial.printf("[MODE CMD] Tidak dikenal: '%s'\n", pesan.c_str());
    return;
  }

  updateLCD();
  kirimStatus(true);
}

void handleParameterCommand(String pesan) {
  StaticJsonDocument<256> doc;
  DeserializationError err = deserializeJson(doc, pesan);

  if (err) {
    Serial.printf("[PARAM] JSON error: %s\n", err.c_str());
    return;
  }

  if (doc.containsKey("min_kel")) minKelembapan = doc["min_kel"].as<float>();
  if (doc.containsKey("max_kel")) maxKelembapan = doc["max_kel"].as<float>();

  Serial.printf("[PARAM] min=%.1f max=%.1f\n", minKelembapan, maxKelembapan);
}

void onMqttMessage(char* topic, byte* payload, unsigned int length) {
  String topicStr(topic);
  String pesan;

  for (unsigned int i = 0; i < length; i++) {
    pesan += (char)payload[i];
  }

  Serial.printf("[MQTT] <- %s : %s\n", topicStr.c_str(), pesan.c_str());

  if (topicStr == topicPump) {
    handlePumpCommand(pesan);
  } else if (topicStr == topicMode) {
    handleModeCommand(pesan);
  } else if (topicStr == topicParameter) {
    handleParameterCommand(pesan);
  } else {
    Serial.printf("[MQTT] Topic tidak dikenal: %s\n", topicStr.c_str());
  }
}

void koneksiWifi() {
  if (WiFi.status() == WL_CONNECTED) return;

  lcd.clear();
  lcd.setCursor(0, 0); lcd.print("Connecting WiFi");
  lcd.setCursor(0, 1); lcd.print(WIFI_SSID);

  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.printf("[WIFI] Konek ke %s", WIFI_SSID);

  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");

    if (millis() - start > 30000) {
      Serial.println("\n[WIFI] Timeout -> restart");
      ESP.restart();
    }
  }

  Serial.printf("\n[WIFI] IP=%s RSSI=%d dBm\n",
                WiFi.localIP().toString().c_str(),
                WiFi.RSSI());

  lcd.clear();
  lcd.setCursor(0, 0); lcd.print("WiFi Terhubung");
  lcd.setCursor(0, 1); lcd.print(WiFi.localIP().toString());
  delay(1500);
}

void siapkanTopics() {
  macAddress = WiFi.macAddress();
  macAddress.replace(":", "");
  macAddress.toUpperCase();

  topicData = "mapia/sensor/" + macAddress + "/data";
  topicStatus = "mapia/sensor/" + macAddress + "/status";
  topicHeartbeat = "mapia/sensor/" + macAddress + "/heartbeat";
  topicPump = "mapia/actuator/" + macAddress + "/pump";
  topicMode = "mapia/sensor/" + macAddress + "/mode";
  topicParameter = "mapia/sensor/" + macAddress + "/parameter";

  Serial.printf("[INFO] MAC : %s\n", macAddress.c_str());
  Serial.printf("[INFO] Data: %s\n", topicData.c_str());
  Serial.printf("[INFO] Pump: %s\n", topicPump.c_str());
  Serial.printf("[INFO] Mode: %s\n", topicMode.c_str());
  Serial.printf("[INFO] Param: %s\n", topicParameter.c_str());
}

void koneksiMqtt() {
  if (mqttClient.connected()) return;

  lcd.clear();
  lcd.setCursor(0, 0); lcd.print("Connecting MQTT");
  lcd.setCursor(0, 1); lcd.print("EMQX broker...");

  String clientId = "mapia-esp32-" + macAddress + "-" + String((uint32_t)ESP.getEfuseMac(), HEX);

  int retry = 0;
  while (!mqttClient.connected()) {
    retry++;
    Serial.printf("[MQTT] Konek percobaan %d\n", retry);

    if (mqttClient.connect(clientId.c_str(), MQTT_USER, MQTT_PASS)) {
      Serial.println("[MQTT] Terhubung!");

      mqttClient.subscribe(topicPump.c_str(), 1);
      mqttClient.subscribe(topicMode.c_str(), 1);
      mqttClient.subscribe(topicParameter.c_str(), 1);

      kirimStatus(true);

      lcd.clear();
      lcd.setCursor(0, 0); lcd.print("MQTT Terhubung");
      lcd.setCursor(0, 1); lcd.print("MAPIA Siap");
      delay(1200);
      updateLCD();
    } else {
      Serial.printf("[MQTT] Gagal state=%d\n", mqttClient.state());
      lcd.clear();
      lcd.setCursor(0, 0); lcd.print("MQTT Gagal");
      lcd.setCursor(0, 1); lcd.print("Retry ");
      lcd.print(retry);
      delay(3000);
    }
  }
}

void setup() {
  Serial.begin(115200);
  delay(500);

  Serial.println();
  Serial.println("=== MAPIA ESP32 FINAL ===");

  Wire.begin(21, 22);
  lcd.init();
  lcd.backlight();
  lcd.setCursor(0, 0); lcd.print("MAPIA v1.0");
  lcd.setCursor(0, 1); lcd.print("Booting...");
  delay(1000);

  pinMode(PIN_RELAY, OUTPUT);
  matikanPompa();

  analogSetAttenuation(ADC_11db);

  koneksiWifi();
  siapkanTopics();

  wifiClient.setCACert(ROOT_CA);
  mqttClient.setServer(MQTT_BROKER, MQTT_PORT);
  mqttClient.setCallback(onMqttMessage);
  mqttClient.setKeepAlive(60);
  mqttClient.setBufferSize(512);

  koneksiMqtt();

  Serial.println("[READY] MAPIA siap");
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[WIFI] Putus -> reconnect");
    koneksiWifi();
  }

  if (!mqttClient.connected()) {
    Serial.println("[MQTT] Putus -> reconnect");
    koneksiMqtt();
  }

  mqttClient.loop();

  unsigned long now = millis();

  if (now - lastHeartbeat >= INTERVAL_HEARTBEAT) {
    lastHeartbeat = now;
    kirimHeartbeat();
  }

  if (now - lastKirim >= INTERVAL_KIRIM) {
    lastKirim = now;

    float kelembapan = bacaKelembapan();
    prosesOtomatis(kelembapan);
    kirimData(kelembapan);
    updateLCD();

    Serial.printf("[DATA] lembab=%.1f%% pompa=%s mode=%s uptime=%lus\n",
                  kelembapan,
                  pumpStatus ? "ON" : "OFF",
                  modeAuto ? "Otomatis" : "Manual",
                  millis() / 1000);
  }
}
