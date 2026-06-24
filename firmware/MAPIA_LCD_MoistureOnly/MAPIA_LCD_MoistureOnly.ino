#include <Wire.h>
#include <LiquidCrystal_I2C.h>

const int PIN_MOISTURE = 35;

// LCD I2C ESP32 default: SDA = GPIO21, SCL = GPIO22.
// Kalau LCD kosong, coba ganti 0x27 menjadi 0x3F.
LiquidCrystal_I2C lcd(0x27, 16, 2);

// Kalibrasi awal sensor. Nanti sesuaikan dari nilai raw di Serial Monitor.
const int MOISTURE_DRY = 2800;
const int MOISTURE_WET = 1200;

unsigned long lastRead = 0;
const unsigned long READ_INTERVAL = 1000;

float bacaKelembapan(int *rawOut) {
  long total = 0;

  for (int i = 0; i < 10; i++) {
    total += analogRead(PIN_MOISTURE);
    delay(10);
  }

  int raw = total / 10;
  float persen = map(raw, MOISTURE_DRY, MOISTURE_WET, 0, 100);
  persen = constrain(persen, 0.0, 100.0);

  if (rawOut != nullptr) {
    *rawOut = raw;
  }

  return persen;
}

void tampilkanLCD(float kelembapan, int raw) {
  lcd.setCursor(0, 0);
  lcd.print("Lembab: ");
  lcd.print(kelembapan, 1);
  lcd.print("%   ");

  lcd.setCursor(0, 1);
  lcd.print("Raw: ");
  lcd.print(raw);
  lcd.print("        ");
}

void setup() {
  Serial.begin(115200);
  delay(500);

  Serial.println();
  Serial.println("=== MAPIA LCD + SENSOR KELEMBAPAN ===");

  Wire.begin(21, 22);
  lcd.init();
  lcd.backlight();

  analogSetAttenuation(ADC_11db);

  lcd.setCursor(0, 0);
  lcd.print("MAPIA LCD Ready");
  lcd.setCursor(0, 1);
  lcd.print("Sensor GPIO35");

  delay(1500);
  lcd.clear();
}

void loop() {
  unsigned long now = millis();

  if (now - lastRead >= READ_INTERVAL) {
    lastRead = now;

    int raw = 0;
    float kelembapan = bacaKelembapan(&raw);

    Serial.printf("[MOISTURE] raw=%d | kelembapan=%.1f%%\n", raw, kelembapan);
    tampilkanLCD(kelembapan, raw);
  }
}
