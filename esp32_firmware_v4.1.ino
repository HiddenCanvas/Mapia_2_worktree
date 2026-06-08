#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>

// ─── SERTIFIKAT EMQX CLOUD ───
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

// ─── KONFIGURASI ───
const char* WIFI_SSID     = "Habib ilham";
const char* WIFI_PASSWORD = "h4b1b1lh4m";

// Broker EMQX Cloud
const char* MQTT_BROKER   = "x1517f89.ala.asia-southeast1.emqxsl.com";
const int   MQTT_PORT     = 8883;
const char* MQTT_USER     = "Mapia";      
const char* MQTT_PASS     = "Mapia123";   

const int   SENSOR_DB_ID  = 1;

const int PIN_MOISTURE    = 34;
const int PIN_PH          = 35;
const int PIN_RELAY       = 26;

const unsigned long INTERVAL_KIRIM = 30000;
const int MOISTURE_DRY    = 2800;
const int MOISTURE_WET    = 1200;
const float PH_OFFSET     = 0.00;

// ─── VARIABEL GLOBAL ───
WiFiClientSecure  wifiClient;
PubSubClient      mqttClient(wifiClient);

String  macAddress;
String  topicPublish;
String  topicPump;
String  topicMode;

bool    modeAuto    = true;
bool    pumpStatus  = false;

float   minKelembapan = 40.0;
float   maxKelembapan = 70.0;
float   minPh         = 5.5;
float   maxPh         = 7.0;

unsigned long lastKirim = 0;

// ─── FUNGSI SENSOR & AKTUATOR ───
float bacaKelembapan() {
  long total = 0;
  for (int i = 0; i < 10; i++) {
    total += analogRead(PIN_MOISTURE);
    delay(10);
  }
  int raw = total / 10;
  float persen = map(raw, MOISTURE_DRY, MOISTURE_WET, 0, 100);
  persen = constrain(persen, 0.0, 100.0);
  Serial.printf("[SENSOR] Moisture RAW: %d → %.1f%%\n", raw, persen);
  return persen;
}

float bacaPh() {
  long total = 0;
  for (int i = 0; i < 10; i++) {
    total += analogRead(PIN_PH);
    delay(10);
  }
  int raw = total / 10;
  float voltage = raw * (3.3 / 4095.0);
  float ph = 7.0 + ((2.5 - voltage) / 0.18) + PH_OFFSET;
  ph = constrain(ph, 0.0, 14.0);
  Serial.printf("[SENSOR] pH RAW: %d | Voltage: %.3fV → pH: %.2f\n", raw, voltage, ph);
  return ph;
}

void nyalakanPompa() {
  if (!pumpStatus) {
    digitalWrite(PIN_RELAY, LOW);
    pumpStatus = true;
    Serial.println("[POMPA] >>> MENYALA <<<");
  }
}

void matikanPompa() {
  if (pumpStatus) {
    digitalWrite(PIN_RELAY, HIGH);
    pumpStatus = false;
    Serial.println("[POMPA] >>> MATI <<<");
  }
}

void prosesOtomatis(float kelembapan, float ph) {
  if (!modeAuto) return;
  bool phAman = (ph >= minPh && ph <= maxPh);
  if (kelembapan < minKelembapan && phAman) {
    Serial.printf("[AUTO] Kelembapan %.1f%% < min %.1f%% — Pompa ON\n", kelembapan, minKelembapan);
    nyalakanPompa();
  } else if (kelembapan >= maxKelembapan) {
    Serial.printf("[AUTO] Kelembapan %.1f%% >= max %.1f%% — Pompa OFF\n", kelembapan, maxKelembapan);
    matikanPompa();
  } else if (!phAman) {
    Serial.printf("[AUTO] pH %.2f di luar rentang [%.1f–%.1f] — Pompa OFF\n", ph, minPh, maxPh);
    matikanPompa();
  }
}

void kirimData(float kelembapan, float ph) {
  StaticJsonDocument<200> doc;
  doc["kelembapan"] = round(kelembapan * 10) / 10.0;
  doc["ph_tanah"]   = round(ph * 100) / 100.0;
  doc["id_sensor"]  = SENSOR_DB_ID;
  doc["pump"]       = pumpStatus ? "ON" : "OFF";
  doc["mode"]       = modeAuto ? "otomatis" : "manual";
  char payload[200];
  serializeJson(doc, payload);
  bool berhasil = mqttClient.publish(topicPublish.c_str(), payload, false);
  if (berhasil) {
    Serial.printf("[MQTT] Terkirim → %s\n", payload);
  } else {
    Serial.println("[MQTT] GAGAL kirim data!");
  }
}

void onMqttMessage(char* topic, byte* payload, unsigned int length) {
  String topicStr(topic);
  String pesan = "";
  for (unsigned int i = 0; i < length; i++) pesan += (char)payload[i];
  Serial.printf("[MQTT] Terima | Topic: %s | Pesan: %s\n", topic, pesan.c_str());

  if (topicStr == topicPump) {
    if (!modeAuto) {
      if (pesan == "ON")  nyalakanPompa();
      if (pesan == "OFF") matikanPompa();
    } else {
      Serial.println("[MQTT] Perintah pompa diabaikan — mode otomatis aktif");
    }
  }

  if (topicStr == topicMode) {
    if (pesan == "Otomatis") {
      modeAuto = true;
      matikanPompa();
      Serial.println("[MODE] Beralih ke OTOMATIS");
    } else if (pesan == "Manual") {
      modeAuto = false;
      matikanPompa();
      Serial.println("[MODE] Beralih ke MANUAL");
    }
  }

  String topicParameter = "mapia/sensor/" + macAddress + "/parameter";
  if (topicStr == topicParameter) {
    StaticJsonDocument<200> doc;
    DeserializationError err = deserializeJson(doc, pesan);
    if (!err) {
      if (doc.containsKey("min_kel"))  minKelembapan = doc["min_kel"].as<float>();
      if (doc.containsKey("max_kel"))  maxKelembapan = doc["max_kel"].as<float>();
      if (doc.containsKey("min_ph"))   minPh         = doc["min_ph"].as<float>();
      if (doc.containsKey("max_ph"))   maxPh         = doc["max_ph"].as<float>();
      Serial.printf("[PARAM] Update: kel [%.1f–%.1f%%] pH [%.1f–%.1f]\n",
                    minKelembapan, maxKelembapan, minPh, maxPh);
    }
  }
}

// ─── JALUR KONEKSI ───
void koneksiWifi() {
  WiFi.disconnect(true);
  delay(500);
  WiFi.mode(WIFI_STA);
  delay(500);
  Serial.printf("\n[WIFI] Menghubungkan ke: %s\n", WIFI_SSID);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
    if (millis() - start > 30000) {
      Serial.println("\n[WIFI] TIMEOUT — restart...");
      delay(3000);
      ESP.restart();
    }
  }
  Serial.printf("\n[WIFI] Terhubung! IP: %s | RSSI: %d dBm\n",
                WiFi.localIP().toString().c_str(), WiFi.RSSI());
}

void koneksiMqtt() {
  String clientId = "mapia-esp32-" + macAddress;
  clientId.replace(":", "");
  Serial.printf("[MQTT] Menghubungkan ke %s:%d ...\n", MQTT_BROKER, MQTT_PORT);
  int percobaan = 0;
  
  while (!mqttClient.connected() && percobaan < 5) {
    if (mqttClient.connect(clientId.c_str(), MQTT_USER, MQTT_PASS)) {
        Serial.println("[MQTT] Terhubung ke EMQX Cloud!"); 
        mqttClient.subscribe(topicPump.c_str());
        mqttClient.subscribe(topicMode.c_str());
    } else {
        Serial.printf("[MQTT] Gagal (state=%d), coba lagi...\n", mqttClient.state());
        delay(3000);
        percobaan++;
    }
  }
  
  if (!mqttClient.connected()) {
    Serial.println("[MQTT] Tidak bisa terhubung — restart...");
    delay(5000);
    ESP.restart();
  }
}

// ─── SETUP ───
void setup() {
  Serial.begin(115200);
  delay(1000);
  
  // Konfigurasi Hardware Pin
  pinMode(PIN_RELAY, OUTPUT);
  digitalWrite(PIN_RELAY, HIGH); // Default Mati (Aktif LOW)
  pinMode(PIN_MOISTURE, INPUT);
  pinMode(PIN_PH, INPUT);

  // Hubungkan ke Wifi & Ambil Mac Address untuk generate topik unik
  koneksiWifi();
  macAddress = WiFi.macAddress();

  topicPublish = "mapia/sensor/" + macAddress + "/data";
  topicPump    = "mapia/actuator/" + macAddress + "/pump";
  topicMode    = "mapia/sensor/" + macAddress + "/mode";

  Serial.printf("[INFO] Topic: %s\n", topicPublish.c_str());

  // Pasang Sertifikat Root DigiCert ke SSL Client
  wifiClient.setCACert(ROOT_CA);

  mqttClient.setServer(MQTT_BROKER, MQTT_PORT);
  mqttClient.setCallback(onMqttMessage);
  mqttClient.setKeepAlive(60);
  mqttClient.setBufferSize(512);

  koneksiMqtt();

  Serial.println("\n[READY] Sistem siap beroperasi!\n");
}

// ─── LOOP ───
void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[WIFI] Putus — reconnect...");
    koneksiWifi();
  }
  if (!mqttClient.connected()) {
    Serial.println("[MQTT] Putus — reconnect...");
    koneksiMqtt();
  }
  mqttClient.loop();

  unsigned long sekarang = millis();
  if (sekarang - lastKirim >= INTERVAL_KIRIM) {
    lastKirim = sekarang;
    float kelembapan = bacaKelembapan();
    float ph         = bacaPh();
    Serial.printf("\n[DATA] Kelembapan: %.1f%% | pH: %.2f | Pompa: %s | Mode: %s\n",
                  kelembapan, ph,
                  pumpStatus ? "ON" : "OFF",
                  modeAuto ? "Otomatis" : "Manual");
    prosesOtomatis(kelembapan, ph);
    kirimData(kelembapan, ph);
  }
}