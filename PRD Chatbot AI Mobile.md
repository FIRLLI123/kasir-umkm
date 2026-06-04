# PRD Singkat - Fitur Chatbot AI Mobile

## 1. Overview

Mobile app akan memiliki fitur chatbot AI yang terhubung ke backend Laravel.

Mobile tidak boleh memanggil provider AI secara langsung.

Arsitektur yang dipakai:

```text
Mobile App -> Backend Laravel -> Groq API
```

Keuntungan:

* API key aman di backend
* respons bisa dikontrol oleh backend
* company user login tetap terisolasi
* prompt dan behavior chatbot bisa disesuaikan tanpa update mobile

---

## 2. Tujuan

Fitur chatbot AI dipakai untuk:

* membantu user memahami aplikasi
* menjawab pertanyaan umum tentang kasir
* memberi bantuan ringan terkait stok, penjualan, laporan, dan penggunaan aplikasi
* menampilkan informasi pembuat aplikasi jika ditanya user

---

## 3. Scope V1

### Included

* chat text-based
* riwayat percakapan dikirim dari mobile ke backend
* response teks dari AI
* prompt dasar aplikasi kasir
* info pembuat aplikasi jika ditanya user

### Not Included

* voice input/output
* upload file/gambar
* akses langsung AI ke database
* function calling
* streaming response
* memory permanen lintas sesi

---

## 4. Endpoint

## POST `/api/ai/chat`

Authentication:

* wajib login Sanctum
* gunakan Bearer Token

Headers:

```text
Accept: application/json
Authorization: Bearer <token>
Content-Type: application/json
```

---

## 5. Request Body

### Minimal

```json
{
  "message": "Halo, kamu bisa bantu apa?"
}
```

### Full Payload

```json
{
  "message": "Bagaimana cara membaca laporan penjualan harian?",
  "history": [
    {
      "role": "user",
      "content": "Saya baru belajar aplikasi ini"
    },
    {
      "role": "assistant",
      "content": "Baik, saya bisa bantu jelaskan fitur-fiturnya"
    }
  ],
  "system_prompt": "Kamu adalah asisten training kasir. Jawab singkat dan jelas.",
  "temperature": 0.3
}
```

---

## 6. Field Request

### `message`

* type: string
* required
* max: 5000 karakter

### `history`

* type: array
* optional
* max: 20 item

Format item:

```json
{
  "role": "user|assistant",
  "content": "isi pesan"
}
```

### `system_prompt`

* type: string
* optional
* dipakai jika mobile ingin menyesuaikan gaya jawaban

### `temperature`

* type: number
* optional
* range: 0 - 2

---

## 7. Response Success

```json
{
  "success": true,
  "message": "Chat AI berhasil diproses",
  "data": {
    "reply": "Laporan penjualan harian menampilkan total transaksi, penjualan bersih, modal, dan margin pada hari tertentu.",
    "model": "llama-3.3-70b-versatile",
    "usage": {
      "prompt_tokens": 100,
      "completion_tokens": 40,
      "total_tokens": 140
    }
  }
}
```

---

## 8. Response Error

### Belum login

```json
{
  "success": false,
  "message": "Unauthorized. Silakan login terlebih dahulu.",
  "data": null
}
```

### Message kosong

```json
{
  "success": false,
  "message": "Pesan wajib diisi.",
  "data": null,
  "errors": {
    "message": [
      "Pesan wajib diisi."
    ]
  }
}
```

### API key backend belum diset

```json
{
  "success": false,
  "message": "GROQ_API_KEY belum diset di backend.",
  "data": null
}
```

### Provider AI gagal

```json
{
  "success": false,
  "message": "Gagal menghubungi layanan AI.",
  "data": null
}
```

---

## 9. Behavior Chatbot Saat Ini

Prompt default backend sudah diarahkan agar:

* memahami konteks aplikasi kasir UMKM
* menjawab singkat, jelas, dan relevan
* tahu company user yang sedang login
* bisa menjelaskan aplikasi ini jika ditanya
* menyebut pembuat aplikasi adalah Programmer Firlli bila diminta
* mengarahkan ke:
  * Portfolio: `https://firlli.vercel.app/`
  * WhatsApp: `082249495858`

---

## 10. Aturan Mobile

Mobile harus:

* mengirim `message`
* boleh mengirim `history`
* menyimpan riwayat chat di sisi mobile untuk sesi aktif
* tidak mengirim API key provider AI
* tidak memanggil Groq langsung dari mobile

Mobile tidak perlu:

* mengirim `company_id`
* mengirim `user_id`
* menghitung token usage

---

## 11. UX Recommendation

### Halaman Chat

Tampilkan:

* bubble chat user
* bubble chat AI
* loading state saat AI sedang berpikir
* error toast/dialog jika request gagal

### Empty State

Contoh quick prompt:

* "Jelaskan fitur aplikasi ini"
* "Bagaimana cara input penjualan?"
* "Bagaimana cara melihat laporan?"
* "Bagaimana cara cek stok?"

### Retry

Jika gagal request:

* tampilkan tombol kirim ulang
* jangan hilangkan pesan user terakhir

---

## 12. Acceptance Criteria

Fitur chatbot mobile dianggap sesuai jika:

1. Mobile berhasil memanggil `POST /api/ai/chat`
2. Token login dipakai di request chatbot
3. User bisa kirim pesan teks
4. AI mengembalikan jawaban teks
5. Riwayat chat bisa dikirim ulang ke backend
6. Error backend bisa ditampilkan dengan jelas
7. Mobile tidak pernah menyimpan atau memakai API key Groq

---

## 13. Rekomendasi Implementasi Mobile

1. Buat model request dan response `ai/chat`
2. Tambahkan repository/service API chat
3. Tambahkan halaman chat sederhana
4. Simpan history sementara di memori lokal aplikasi
5. Tambahkan loading, retry, dan error state
6. Tambahkan quick actions / pertanyaan cepat

---

## 14. Konsep Pengembangan Lanjutan

Versi saat ini masih general assistant.

Ke depan chatbot bisa dibuat lebih kontekstual terhadap data kasir per company dengan pendekatan backend yang aman, dijelaskan di dokumen:

`Konsep Chatbot AI Kontekstual.md`
