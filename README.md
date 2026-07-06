# 🛡️ Security Monitor Pro - Pemantauan Sistem Keamanan Ruangan

![Platform](https://img.shields.io/badge/Platform-ESP32--CAM-blue)
![Firebase](https://img.shields.io/badge/Firebase-Realtime%20DB%20%26%20Storage-orange)
![Telegram](https://img.shields.io/badge/Telegram-Bot%20API-blue)
![License](https://img.shields.io/badge/License-MIT-green)

## 📌 Deskripsi Proyek

**Security Monitor Pro** adalah sistem pemantauan keamanan ruangan berbasis **Internet of Things (IoT)** yang dirancang sebagai solusi keamanan yang ekonomis, responsif, dan mudah diimplementasikan. Sistem ini menggabungkan perangkat keras ESP32-CAM dan sensor PIR dengan platform cloud Firebase serta notifikasi instan melalui Telegram.

Proyek ini merupakan bagian dari penelitian Tugas Akhir/Skripsi yang membahas implementasi IoT untuk keamanan ruangan, termasuk analisis trafik jaringan menggunakan Wireshark untuk mengukur efisiensi bandwidth dan keandalan sistem.

---

## 🚀 Fitur Utama

| **Fitur** | **Deskripsi** |
| :--- | :--- |
| **Deteksi Gerakan Real-time** | Sensor PIR HC-SR501 mendeteksi pergerakan manusia secara akurat hingga jarak 5 meter. |
| **Pengambilan Gambar Otomatis** | ESP32-CAM mengambil gambar (JPEG) saat gerakan terdeteksi. |
| **Upload ke Firebase Storage** | Gambar otomatis diunggah ke Firebase Storage dengan nama file unik berbasis timestamp. |
| **Notifikasi Telegram Instan** | Mengirim pesan peringatan + link gambar/dashboard ke pengguna melalui Bot Telegram (via Cloudflare Worker). |
| **Dashboard WebView (Real-time)** | Menampilkan snapshot terbaru, galeri history, status keamanan (Aman/Bahaya), serta live streaming video dari ESP32-CAM. |
| **Penyimpanan Data Historis** | Menyimpan riwayat deteksi (path gambar, timestamp, status) di Firebase Realtime Database. |
| **Analisis Trafik Jaringan** | Pengukuran throughput upload/download menggunakan Wireshark untuk evaluasi performa sistem. |

---

## 🧰 Teknologi yang Digunakan

| **Komponen** | **Teknologi / Tools** |
| :--- | :--- |
| **Mikrokontroler & Kamera** | ESP32-CAM (OV2640) |
| **Sensor** | PIR HC-SR501 |
| **Bahasa Pemrograman** | C++ (Arduino IDE), JavaScript, HTML, CSS |
| **Cloud Database** | Firebase Realtime Database |
| **Cloud Storage** | Firebase Storage (gs://camera-7dd22...) |
| **Notifikasi** | Telegram Bot API + Cloudflare Worker (Proxy) |
| **Hosting Dashboard** | GitHub Pages |
| **Analisis Jaringan** | Wireshark |

---

## ⚙️ Arsitektur Sistem

```mermaid
graph LR
    PIR[Sensor PIR] -->|Deteksi| ESP32[ESP32-CAM]
    ESP32 -->|Capture Foto| LFS[LittleFS]
    LFS -->|Upload| Storage[Firebase Storage]
    ESP32 -->|Simpan Metadata| DB[Firebase Realtime DB]
    DB -->|Baca Data| Web[Dashboard WebView]
    ESP32 -->|Kirim Notifikasi| TG[Telegram Bot API]
    TG -->|Forward| User[Pengguna Telegram]
    Web -->|Akses| User
