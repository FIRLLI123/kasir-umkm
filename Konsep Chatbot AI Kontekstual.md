# Konsep Chatbot AI Kontekstual untuk Kasir UMKM

## 1. Tujuan

Chatbot berikutnya tidak hanya menjawab secara umum, tetapi juga bisa memahami konteks bisnis user berdasarkan company yang login.

Contoh target:

* "Berapa penjualan hari ini?"
* "Produk terlaris minggu ini apa?"
* "Stok Aqua tinggal berapa?"
* "Metode pembayaran yang paling sering dipakai apa?"

Namun implementasi harus tetap aman:

* user hanya boleh melihat data company sendiri
* AI tidak boleh bebas query seluruh database
* semua data harus difilter backend lebih dulu

---

## 2. Pendekatan Terbaik

Pendekatan terbaik bukan memberi AI akses database langsung.

Yang aman:

```text
Mobile -> Backend -> Backend baca data terstruktur -> Backend kirim konteks ke AI -> AI jawab
```

Artinya:

1. user bertanya
2. backend klasifikasikan intent
3. backend ambil data company user yang relevan
4. backend bentuk context ringkas
5. context dikirim ke model AI
6. AI menyusun jawaban natural

---

## 3. Arsitektur yang Disarankan

### Layer 1 - Intent Detection

Backend mendeteksi pertanyaan masuk ke kategori apa, misalnya:

* penjualan harian
* laporan mingguan
* stok produk
* produk terlaris
* customer terbaik
* bantuan penggunaan aplikasi

### Layer 2 - Data Fetcher

Jika intent butuh data internal:

* backend query database
* query selalu filter `company_id` dari user login
* hasil dibatasi dan diringkas

### Layer 3 - Prompt Builder

Backend membangun prompt seperti:

```text
User berasal dari company Demo Company.
Berikut data penjualan hari ini:
- total transaksi: 14
- total penjualan: 1250000
- margin: 230000
Jawab pertanyaan user dengan singkat dan jelas.
```

### Layer 4 - AI Response

AI hanya menerima konteks yang sudah aman dan terkontrol.

---

## 4. Contoh Use Case yang Layak Dibuat

### 4.1 Tanya Penjualan Hari Ini

Input user:

```text
Berapa penjualan hari ini?
```

Backend ambil:

* total transaksi
* gross sales
* net sales
* margin

Jawaban AI:

```text
Hari ini ada 14 transaksi dengan total penjualan bersih Rp1.250.000 dan margin Rp230.000.
```

### 4.2 Tanya Produk Terlaris

Input user:

```text
Produk terlaris minggu ini apa?
```

Backend ambil:

* top 5 produk
* qty terjual

### 4.3 Tanya Stok Produk

Input user:

```text
Stok Aqua berapa sekarang?
```

Backend cari:

* produk yang paling cocok
* current stock

### 4.4 Tanya Laporan Metode Pembayaran

Input user:

```text
Pembayaran paling banyak pakai apa bulan ini?
```

Backend ambil:

* ranking payment method

---

## 5. Data yang Boleh Dipakai AI

AI boleh menerima context seperti:

* nama company
* total transaksi
* total penjualan
* margin
* stok produk
* nama produk terlaris
* customer dengan transaksi tertinggi

AI tidak sebaiknya menerima data mentah berlebihan seperti:

* seluruh tabel users
* semua transaksi detail tanpa ringkasan
* token, password, API key
* data sensitif internal yang tidak perlu

---

## 6. Security Rules

### Wajib

* semua query pakai `company_id` user login
* jangan beri model akses SQL langsung
* batasi jumlah data yang dikirim ke AI
* log request AI jika perlu untuk audit
* filter data sensitif sebelum dikirim ke AI

### Jangan

* kirim seluruh isi tabel ke AI
* beri AI kemampuan eksekusi query mentah tanpa kontrol
* percaya penuh pada jawaban AI untuk angka tanpa data backend

---

## 7. Roadmap Implementasi Bertahap

### Phase 1

Chatbot umum aplikasi

Status:

* sudah ada

### Phase 2

Intent-based contextual chatbot

Fitur:

* penjualan hari ini
* stok produk
* top produk
* laporan singkat

### Phase 3

Hybrid assistant

Fitur:

* AI bisa menggabungkan jawaban umum + data internal
* quick insight otomatis untuk owner/admin

### Phase 4

Action assistant terbatas

Contoh:

* buat ringkasan laporan harian
* bantu interpretasi tren penjualan

Tetap bukan write access langsung ke database.

---

## 8. Saran Endpoint Backend Nanti

Untuk versi kontekstual, backend bisa tetap memakai:

`POST /api/ai/chat`

Tetapi di backend ditambah mode:

* `general`
* `contextual`

Atau tambah endpoint baru:

* `POST /api/ai/chat/contextual`

Request contoh:

```json
{
  "message": "Berapa penjualan hari ini?"
}
```

Backend yang memutuskan context apa yang perlu diambil.

---

## 9. Contoh Prompt Kontekstual

```text
Kamu adalah asisten bisnis untuk company Demo Company.
Gunakan hanya data berikut:

Tanggal: 2026-06-04
Total transaksi hari ini: 14
Penjualan bersih: 1250000
Margin: 230000

Jawab pertanyaan user dengan singkat, akurat, dan jangan mengarang data lain.
Pertanyaan user: Berapa penjualan hari ini?
```

---

## 10. Rekomendasi untuk Langkah Berikutnya

Langkah terbaik setelah chatbot dasar stabil:

1. buat intent sederhana berbasis keyword
2. hubungkan ke report/stok service yang sudah ada
3. kirim summary data ke AI, bukan raw table
4. batasi dulu ke 3-5 use case paling penting

Use case pertama yang paling layak:

* penjualan hari ini
* stok produk
* top 5 produk terlaris
* laporan metode pembayaran

---

## 11. Kesimpulan

Bisa banget membuat chatbot AI yang paham konteks kasir dan company user.

Cara terbaik:

* backend tetap jadi pengendali data
* AI hanya menerima context yang sudah diringkas
* semua akses data tetap aman per company

Dengan arsitektur ini, chatbot akan terasa pintar tanpa mengorbankan keamanan tenant dan struktur backend yang sudah ada.
