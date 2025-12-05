# 🎥 Fake Video CDN Shortlink & Redirector

**Script PHP Shortlink Sederhana dengan Penyamaran Ekstensi .MP4**

Script ini memungkinkan Anda membuat link redirect yang terlihat seperti file video (contoh: `https://cdn.domain.com/video-viral.mp4`), padahal sebenarnya adalah link redirect ke URL lain (Affiliate, CPA, dll). Dilengkapi dengan **Auto-Installer**, **Admin Panel**, dan **Bot Protection**.

![PHP Badge](https://img.shields.io/badge/PHP-7.4%2B-blue)
![License](https://img.shields.io/badge/License-MIT-green)

## ✨ Fitur Utama

* 🚀 **Fake Extension:** URL shortlink berakhiran `.mp4` agar terlihat seperti file video asli.
* 📦 **One-File Installer:** Cukup upload `install.php`, sistem akan membuat database, config, dan file admin secara otomatis.
* 🛡️ **Bot Protection:** Mendeteksi bot (Googlebot, Facebook, WhatsApp, dll) dan menyembunyikan link asli (menampilkan halaman kosong/404), hanya manusia yang diredirect.
* 📊 **Simple Admin Panel:** Dashboard untuk membuat, mengedit, menghapus, dan melihat statistik klik.
* ⚡ **Lightweight:** Tanpa framework berat, hanya native PHP + MySQL.
* 🔄 **Smart Redirect:** Menggunakan header 302 untuk redirect cepat.

## 📋 Persyaratan Server

* **PHP:** Versi 7.4 atau 8.x
* **Database:** MySQL atau MariaDB
* **Web Server:** Apache (Support .htaccess) ATAU Nginx (Perlu konfigurasi manual)

## 🛠️ Cara Instalasi

### 1. Upload File
Upload file `install.php` ke folder root subdomain atau domain Anda (misal: folder `cdn`).

### 2. Jalankan Installer
Buka browser dan akses:
`https://domain-anda.com/install.php`

### 3. Konfigurasi
Isi formulir yang tersedia:
* **Database Host, User, Pass, Name:** Sesuai detail database Anda.
* **Admin Password:** Password untuk login ke dashboard nanti.
* **Main Redirect:** Link tujuan jika seseorang mengakses root domain tanpa kode.

Klik **INSTALL SEKARANG**.

### 4. Selesai
Jika sukses, file `install.php` bisa dihapus. Login ke admin panel di `admin.php`.

---

## ⚙️ Konfigurasi Server (PENTING)

Agar link `.mp4` dapat diproses oleh PHP, Anda perlu memastikan konfigurasi URL Rewrite berjalan.

### A. Untuk Pengguna aaPanel / Nginx
File `.htaccess` yang digenerate otomatis **TIDAK AKAN BEKERJA** di Nginx. Anda harus menambah rule ini secara manual:

1. Masuk ke **aaPanel** > **Website** > **[Domain Anda]**.
2. Pilih menu **URL Rewrite**.
3. Masukkan kode berikut dan Simpan:

```nginx
location / {
    rewrite ^/([a-zA-Z0-9-]+)\.mp4$ /index.php?code=$1 last;
}
