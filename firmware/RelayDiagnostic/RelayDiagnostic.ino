// const int PIN_RELAY = 26;

// // Ubah ini kalau hasil test terbalik.
// // true  = relay ON saat GPIO LOW, relay OFF saat GPIO HIGH
// // false = relay ON saat GPIO HIGH, relay OFF saat GPIO LOW
// const bool RELAY_ACTIVE_LOW = true;

// void tulisRelay(bool nyala) {
//   int level;

//   if (RELAY_ACTIVE_LOW) {
//     level = nyala ? LOW : HIGH;
//   } else {
//     level = nyala ? HIGH : LOW;
//   }

//   digitalWrite(PIN_RELAY, level);

//   Serial.printf(
//     "PERINTAH=%s | GPIO%d=%s | activeLow=%s\n",
//     nyala ? "ON" : "OFF",
//     PIN_RELAY,
//     digitalRead(PIN_RELAY) == HIGH ? "HIGH" : "LOW",
//     RELAY_ACTIVE_LOW ? "true" : "false"
//   );
// }

// void setup() {
//   Serial.begin(115200);
//   delay(1000);

//   pinMode(PIN_RELAY, OUTPUT);
//   Serial.println("=== RELAY DIAGNOSTIC GPIO26 ===");

//   tulisRelay(false);
// }

// void loop() {
//   Serial.println("TEST: RELAY ON");
//   tulisRelay(true);
//   delay(3000);

//   Serial.println("TEST: RELAY OFF");
//   tulisRelay(false);
//   delay(3000);
// }
