#include <Wire.h>

void setup() {
  Serial.begin(115200);
  delay(1000);

  Wire.begin(21, 22);

  Serial.println();
  Serial.println("=== I2C SCANNER ESP32 ===");
  Serial.println("SDA = GPIO21, SCL = GPIO22");
}

void loop() {
  byte error;
  byte address;
  int deviceCount = 0;

  Serial.println("Scanning...");

  for (address = 1; address < 127; address++) {
    Wire.beginTransmission(address);
    error = Wire.endTransmission();

    if (error == 0) {
      Serial.print("I2C device ditemukan di alamat 0x");
      if (address < 16) Serial.print("0");
      Serial.println(address, HEX);
      deviceCount++;
    }
  }

  if (deviceCount == 0) {
    Serial.println("Tidak ada device I2C ditemukan.");
  }

  Serial.println();
  delay(3000);
}
