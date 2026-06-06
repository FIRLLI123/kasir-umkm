# PRD - Subscription, Trial, Register Company, dan Upgrade untuk Mobile

## 1. Overview

Backend sekarang sudah mendukung flow SaaS untuk aplikasi kasir UMKM:

* user baru bisa daftar usaha sendiri
* saat register, sistem otomatis membuat `company` dan `user owner`
* user baru mendapatkan trial `14 hari`
* saat trial tinggal `H-2`, backend mengirim indikator alert
* user bisa upgrade subscription melalui KlikQris
* saat pembayaran sukses, backend otomatis mengaktifkan subscription company via webhook

Dokumen ini dipakai tim Android Native agar implementasi mobile konsisten dengan backend production final.

---

## 2. Tujuan

Tim mobile harus mengimplementasikan flow yang:

* mudah dipahami user baru
* nyaman untuk upgrade subscription
* cepat memberi feedback saat pembayaran berhasil
* aman terhadap retry, refresh, dan reopen app
* sinkron penuh dengan backend tanpa logika pricing manual di app

---

## 3. Scope Fitur

Scope mobile yang wajib dibuat:

* halaman register usaha + user owner
* login
* baca status subscription dari login/profile
* tampilkan alert trial `H-2`
* tampilkan halaman daftar paket subscription
* buat checkout payment
* tampilkan QRIS / direct payment link
* cek status transaksi payment
* update UI sukses setelah payment berhasil

Scope yang tidak wajib di fase ini:

* Google Sign-In
* push notification
* email reminder
* cron-based reminder dari sisi mobile

---

## 4. Model Bisnis yang Berlaku

### Trial

* setiap company baru mendapat trial `14 hari`
* saat sisa trial `2 hari` atau kurang, backend mengirim `show_expiry_alert = true`

### Paket subscription

Mobile tidak boleh hardcode nominal sebagai sumber utama.

Paket harus diambil dari backend:

* `promo_20_hari` = `Rp30.000` untuk `20 hari`
* `promo_6_bulan` = `Rp150.000` untuk `180 hari`
* `lifetime` = `Rp300.000` selamanya

### Lifetime

Jika user membeli paket lifetime:

* status subscription tetap `active`
* `subscription_ends_at` akan `null`
* mobile harus menampilkan label seperti `Aktif Selamanya`

---

## 5. Konsep Data Subscription

Subscription mengikuti `company`, bukan user perorangan.

Artinya:

* 1 usaha = 1 company
* user login pertama adalah owner/admin
* data produk, customer, transaksi, dan setting tetap terisolasi berdasarkan `company_id`

Mobile tidak perlu mengirim `company_id` ke backend.

Backend akan menentukan company dari token user login.

---

## 6. Endpoint yang Dipakai Mobile

## POST `/api/register`

Dipakai saat user pertama kali membuat akun dan usaha.

Headers:

```text
Accept: application/json
Content-Type: application/json
```

Request body:

```json
{
  "company_name": "Toko Maju Jaya",
  "name": "Budi",
  "email": "budi@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "08123456789",
  "address": "Bandung",
  "device_id": "android-device-id",
  "device_name": "Samsung A15"
}
```

Behavior:

* backend membuat `company`
* backend membuat `user` role `ADMIN`
* backend memberi trial `14 hari`
* jika `device_name` dikirim, token login langsung dikembalikan

---

## POST `/api/login`

Headers:

```text
Accept: application/json
Content-Type: application/json
```

Request body:

```json
{
  "email": "budi@example.com",
  "password": "password123",
  "device_id": "android-device-id",
  "device_name": "Samsung A15"
}
```

Response penting:

* `token`
* `user`
* `company`
* `subscription`

---

## GET `/api/profile`

Headers:

```text
Accept: application/json
Authorization: Bearer <token>
```

Dipakai untuk refresh user session dan status subscription.

---

## GET `/api/subscription`

Headers:

```text
Accept: application/json
Authorization: Bearer <token>
```

Dipakai untuk refresh state subscription secara khusus.

---

## GET `/api/subscription/plans`

Headers:

```text
Accept: application/json
Authorization: Bearer <token>
```

Contoh response:

```json
{
  "success": true,
  "message": "Daftar paket subscription berhasil diambil",
  "data": {
    "trial_days": 14,
    "plans": [
      {
        "code": "promo_20_hari",
        "name": "Promo 20 Hari",
        "price": 30000,
        "duration_days": 20,
        "is_lifetime": false,
        "description": "Paket promo 20 hari seharga Rp30.000."
      },
      {
        "code": "promo_6_bulan",
        "name": "Promo 6 Bulan",
        "price": 150000,
        "duration_days": 180,
        "is_lifetime": false,
        "description": "Paket promo 6 bulan seharga Rp150.000."
      },
      {
        "code": "lifetime",
        "name": "Lifetime",
        "price": 300000,
        "duration_days": null,
        "is_lifetime": true,
        "description": "Paket lifetime seharga Rp300.000."
      }
    ]
  }
}
```

Catatan:

* mobile harus merender paket dari response ini
* harga dan durasi tidak boleh dianggap fixed di app

---

## POST `/api/subscription/checkout`

Headers:

```text
Accept: application/json
Authorization: Bearer <token>
Content-Type: application/json
```

Request body:

```json
{
  "plan_code": "promo_20_hari",
  "keterangan": "Upgrade paket promo"
}
```

Contoh response penting:

```json
{
  "success": true,
  "message": "Transaksi pembayaran berhasil dibuat",
  "data": {
    "transaction": {
      "invoice_no": "SUB-5-20260606025553-ABCD",
      "status": "pending"
    },
    "plan": {
      "code": "promo_20_hari",
      "name": "Promo 20 Hari",
      "price": 30000,
      "duration_days": 20,
      "is_lifetime": false
    },
    "gateway": {
      "order_id": "SUB-5-20260606025553-ABCD",
      "status": "PENDING",
      "qris_url": "https://...",
      "qris_image": "data:image/png;base64,...",
      "direct_url": "https://klikqris.com/payqris/...",
      "redirect_url": null,
      "total_amount": "30021.00",
      "expired_at": "2026-06-06 03:25:53",
      "signature": "..."
    }
  }
}
```

Catatan penting:

* tampilkan `gateway.total_amount`, bukan `plan.price`
* jika ada `qris_image`, tampilkan langsung
* jika ada `direct_url`, sediakan tombol `Buka Pembayaran`

---

## GET `/api/subscription/transactions/{orderId}/status`

Headers:

```text
Accept: application/json
Authorization: Bearer <token>
```

Dipakai untuk:

* manual refresh status payment
* polling ringan saat user sedang di halaman pembayaran
* recovery jika webhook berhasil tetapi user masih membuka layar checkout

---

## 7. Bentuk Data Subscription dari Backend

Field penting di object `subscription`:

* `status` = `trial`, `active`, `expired`
* `is_active` = boolean
* `is_lifetime` = boolean
* `trial_starts_at`
* `trial_ends_at`
* `subscription_starts_at`
* `subscription_ends_at`
* `ends_at`
* `days_remaining`
* `show_expiry_alert`
* `message`

Contoh:

```json
{
  "status": "trial",
  "is_active": true,
  "is_lifetime": false,
  "trial_starts_at": "2026-06-06 02:48:16",
  "trial_ends_at": "2026-06-20 02:48:16",
  "subscription_starts_at": null,
  "subscription_ends_at": null,
  "ends_at": "2026-06-20 02:48:16",
  "days_remaining": 2,
  "show_expiry_alert": true,
  "message": "Masa trial akan segera berakhir."
}
```

---

## 8. Flow UX yang Disarankan

### A. Register

1. user buka app
2. user pilih `Daftar`
3. isi:
   * nama usaha
   * nama user
   * email
   * password
   * konfirmasi password
   * no hp
   * alamat
4. jika sukses:
   * simpan token
   * masuk ke dashboard
   * tampilkan info trial aktif

### B. Dashboard

Saat dashboard dibuka:

1. baca `subscription` dari login response atau `GET /api/profile`
2. jika `show_expiry_alert = true`, tampilkan alert ringan di atas dashboard
3. jika `status = expired`, tampilkan CTA utama `Upgrade Sekarang`

### C. Alert Trial H-2

Konten yang disarankan:

Judul:

```text
Masa Trial Hampir Habis
```

Isi:

```text
Sisa masa trial Anda 2 hari. Upgrade sekarang agar operasional toko tetap lancar.
```

Tombol:

* `Upgrade Sekarang`
* `Nanti Saja`

Catatan:

* jika user menutup alert, tidak masalah
* alert boleh muncul lagi saat app dibuka ulang selama masih H-2 dan belum upgrade

### D. Halaman Upgrade Subscription

UI harus senyaman mungkin:

* tampilkan 3 kartu paket yang jelas
* highlight paket yang paling menarik
* tampilkan harga besar dan durasi singkat
* tampilkan label `Paling Hemat` untuk paket 6 bulan atau `Best Value` jika diinginkan
* tampilkan label `Sekali Bayar` untuk lifetime

Contoh urutan tampilan:

1. Paket 20 Hari
2. Paket 6 Bulan
3. Lifetime

Setelah user pilih paket:

* tombol CTA besar: `Lanjut Bayar`

### E. Halaman Pembayaran

Setelah checkout berhasil:

* tampilkan `total_amount`
* tampilkan QRIS image
* tampilkan tombol `Buka Halaman Pembayaran` jika `direct_url` ada
* tampilkan countdown expired
* tampilkan status awal `Menunggu Pembayaran`

Tombol yang disarankan:

* `Saya Sudah Bayar`
* `Cek Status`
* `Buka Pembayaran`

### F. Setelah Pembayaran Berhasil

Ini sangat penting untuk kenyamanan user.

Begitu status transaksi berubah menjadi `paid`:

* tampilkan state sukses penuh
* langsung refresh `GET /api/subscription` atau `GET /api/profile`
* update badge / label subscription di dashboard
* tampilkan masa aktif baru
* jika lifetime, tampilkan `Aktif Selamanya`

Contoh pesan sukses:

```text
Pembayaran berhasil. Langganan Anda sudah aktif.
```

Contoh CTA:

* `Kembali ke Dashboard`

Jika berhasil tanpa user keluar layar, jangan hanya pakai toast. Tampilkan success state yang jelas di halaman pembayaran.

---

## 9. UX Rules Penting

### Untuk kenyamanan user

* jangan paksa user memahami istilah teknis seperti `invoice_no`, `signature`, atau `webhook`
* tampilkan bahasa sederhana
* tampilkan harga final yang harus dibayar
* tampilkan status progress dengan jelas

### Untuk paket

* gunakan data backend sebagai sumber kebenaran
* jangan biarkan user input nominal manual

### Untuk payment success

* jika status sudah `paid`, jangan tampilkan tombol bayar lagi
* langsung transisikan UI ke sukses

### Untuk lifetime

* jika subscription `active` dan `is_lifetime = true`, mobile boleh menyembunyikan CTA upgrade

---

## 10. Polling / Refresh Strategy

Backend sudah punya webhook, tetapi mobile tetap perlu refresh status agar UX responsif.

Strategi yang disarankan:

1. Setelah checkout berhasil, simpan `order_id`
2. Saat user berada di halaman pembayaran:
   * polling `GET /api/subscription/transactions/{orderId}/status` tiap `10-15 detik`
   * berhenti jika status `paid` atau `expired`
3. Saat user menekan `Saya Sudah Bayar`, lakukan `check status` langsung
4. Setelah status `paid`, panggil `GET /api/subscription`

Polling jangan terlalu agresif agar hemat baterai dan bandwidth.

---

## 11. Error Handling

### Register gagal

Tampilkan error validasi per field jika tersedia.

### Checkout gagal

Tampilkan pesan backend apa adanya jika aman dibaca user.

Fallback:

```text
Gagal membuat pembayaran. Coba lagi beberapa saat.
```

### Status payment masih pending

Tampilkan:

```text
Pembayaran belum kami terima. Silakan selesaikan pembayaran lalu cek lagi.
```

### Payment expired

Tampilkan:

```text
Waktu pembayaran habis. Buat pembayaran baru untuk melanjutkan upgrade.
```

CTA:

* `Buat Pembayaran Baru`

### Subscription expired

Jika backend mengirim `status = expired`:

* tampilkan blocking state yang jelas
* arahkan user ke halaman upgrade

---

## 12. State yang Harus Ditangani Mobile

### Subscription state

* `trial`
* `active`
* `expired`

### Payment transaction state

* `pending`
* `paid`
* `expired`
* `failed`
* `unknown`

### Visual recommendation

* `trial` = biru / informatif
* `active` = hijau
* `expired` = merah / oranye
* `pending` = kuning

---

## 13. Acceptance Criteria

Implementasi mobile dianggap selesai jika:

1. User baru bisa register company + owner user dari app
2. Setelah register, user langsung masuk dan melihat status trial `14 hari`
3. Mobile membaca dan menampilkan object `subscription`
4. Alert trial muncul saat `show_expiry_alert = true`
5. Mobile mengambil paket dari `GET /api/subscription/plans`
6. Mobile melakukan checkout memakai `plan_code`
7. Mobile menampilkan QRIS / link pembayaran dengan nyaman
8. Mobile dapat mengecek status transaksi payment
9. Setelah payment sukses, UI langsung menampilkan status berhasil
10. Setelah success, data subscription di-refresh dan masa aktif baru tampil
11. Jika paket lifetime dibeli, UI menampilkan aktif selamanya
12. Jika payment expired, user bisa membuat pembayaran baru

---

## 14. Catatan untuk QA

Skenario minimum:

1. Register user baru dan cek trial `14 hari`
2. Simulasikan user trial H-2 dan cek alert muncul
3. Ambil daftar paket dan cek 3 paket tampil benar
4. Checkout paket `promo_20_hari`
5. Bayar dan tunggu status menjadi `paid`
6. Cek UI sukses muncul tanpa harus login ulang
7. Cek dashboard menampilkan subscription aktif
8. Checkout paket lifetime pada akun test lain
9. Cek `is_lifetime = true`
10. Cek CTA upgrade tersembunyi atau diturunkan prioritasnya pada akun lifetime

---

## 15. Catatan Teknis untuk Tim Android Native

Disarankan membuat lapisan state terpisah:

* `AuthState`
* `SubscriptionState`
* `PaymentState`

Data penting yang harus disimpan:

* bearer token
* user profile
* company profile
* latest subscription snapshot
* current checkout `order_id`

Disarankan juga:

* gunakan model response yang toleran terhadap field tambahan
* jangan hardcode harga, durasi, atau label paket
* siapkan refresh state setelah app kembali dari browser / external payment page

---

## 16. Kesimpulan

Backend sudah siap untuk flow berikut:

* register usaha baru
* trial `14 hari`
* warning `H-2`
* upgrade via KlikQris
* aktivasi otomatis via webhook
* paket promo dan lifetime

Tim Android Native tinggal mengikuti endpoint dan UX rules di atas agar pengalaman user terasa halus, jelas, dan meyakinkan saat upgrade subscription.
