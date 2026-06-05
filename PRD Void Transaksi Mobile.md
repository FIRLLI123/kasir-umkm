# PRD Singkat - Void Transaksi untuk Mobile

## 1. Overview

Fitur void transaksi sudah tersedia di backend dan wajib dipakai oleh mobile jika user ingin membatalkan transaksi yang sudah berhasil dibuat.

Behavior backend saat void:

* hanya transaksi dengan status `00` yang bisa di-void
* status header transaksi berubah menjadi `98`
* status detail transaksi berubah menjadi `98`
* alasan void disimpan
* user yang melakukan void disimpan
* waktu void disimpan
* stok barang otomatis kembali sesuai qty item transaksi
* mutasi stok `VOID` dibuat otomatis

---

## 2. Tujuan

Tim mobile harus mengimplementasikan flow void transaksi yang:

* aman
* jelas untuk user
* sinkron dengan backend
* otomatis refresh data transaksi dan stok setelah void berhasil

---

## 3. Status Transaksi

### Status di backend

* `00` = sukses
* `98` = void
* `99` = nonaktif

### Rule penting

Hanya transaksi dengan status `00` yang boleh di-void.

Jika transaksi sudah `98`, backend akan menolak request void ulang.

---

## 4. Endpoint

## POST `/api/sales/{id}/void`

Authentication:

* wajib login
* wajib Bearer Token

Headers:

```text
Accept: application/json
Authorization: Bearer <token>
Content-Type: application/json
```

---

## 5. Request Body

```json
{
  "void_reason": "Customer batal beli"
}
```

### Field

#### `void_reason`

* type: string
* required
* dipakai untuk audit alasan pembatalan transaksi

---

## 6. Response Success

```json
{
  "success": true,
  "message": "Transaksi berhasil di-void",
  "data": {
    "id": 1,
    "company_id": 1,
    "invoice_no": "INV-20260604-0001",
    "status": "98",
    "void_reason": "Customer batal beli",
    "void_by": 1,
    "void_at": "2026-06-04T10:20:00.000000Z",
    "details": [
      {
        "id": 1,
        "product_id": 1,
        "product_name_snapshot": "Aqua 600ml",
        "qty": "2.00",
        "status": "98"
      }
    ]
  }
}
```

---

## 7. Response Error

### Belum login

```json
{
  "success": false,
  "message": "Unauthorized. Silakan login terlebih dahulu.",
  "data": null
}
```

### Alasan void kosong

```json
{
  "success": false,
  "message": "Alasan void wajib diisi.",
  "data": null,
  "errors": {
    "void_reason": [
      "Alasan void wajib diisi."
    ]
  }
}
```

### Transaksi tidak ditemukan

```json
{
  "success": false,
  "message": "Transaksi penjualan tidak ditemukan.",
  "data": null
}
```

### Transaksi bukan status sukses

```json
{
  "success": false,
  "message": "Hanya transaksi sukses yang bisa di-void",
  "data": null
}
```

Catatan:

Jika user berasal dari company A dan mencoba void transaksi company B, backend juga akan menolak karena data terisolasi per `company_id`.

Mobile cukup tampilkan pesan:

```text
Transaksi penjualan tidak ditemukan.
```

---

## 8. Multi Company Behavior

Backend sudah memakai isolasi `company_id`.

Artinya:

* user hanya bisa void transaksi milik company sendiri
* user tidak bisa melihat atau mengubah transaksi company lain
* stok yang dikembalikan juga hanya memengaruhi produk milik company yang sama

Mobile tidak perlu mengirim `company_id`.

Backend otomatis menentukan company dari user login.

---

## 9. Dampak Void ke Stok

Saat void berhasil:

* qty produk dikembalikan ke stok
* histori mutasi stok akan bertambah dengan tipe `VOID`

Contoh:

Transaksi menjual:

* Aqua 2 pcs

Saat transaksi sukses:

* stok berkurang 2

Saat transaksi di-void:

* stok bertambah kembali 2

---

## 10. Flow UI yang Disarankan

### Dari halaman detail transaksi

1. user tap tombol `Void`
2. mobile tampilkan dialog konfirmasi
3. user wajib isi alasan void
4. mobile kirim request ke backend
5. jika sukses:
   * tampilkan notifikasi sukses
   * ubah status transaksi jadi `VOID`
   * refresh detail transaksi
   * refresh list transaksi jika perlu
   * refresh stok jika halaman stok terbuka / relevan

### Contoh dialog

Judul:

```text
Void Transaksi
```

Isi:

```text
Yakin ingin membatalkan transaksi ini?
```

Field:

```text
Alasan void
```

---

## 11. UX Rules

### Tombol Void hanya tampil jika

* status transaksi = `00`

### Tombol Void disembunyikan atau disabled jika

* status transaksi = `98`

### Setelah void berhasil

Tampilkan label status yang jelas, misalnya:

```text
VOID
```

dan tampilkan:

* alasan void
* siapa yang void
* waktu void

---

## 12. Refresh Data Setelah Void

Minimal mobile melakukan refresh ke endpoint berikut:

* `GET /api/sales/{id}`

Jika ada modul stok/laporan terbuka, disarankan refresh juga:

* `GET /api/stocks`
* `GET /api/stocks/{productId}/history`
* `GET /api/reports/daily`

---

## 13. Acceptance Criteria

Implementasi mobile dianggap sesuai jika:

1. User hanya bisa void transaksi status `00`
2. User wajib mengisi alasan void
3. Mobile memanggil `POST /api/sales/{id}/void`
4. Status transaksi berubah menjadi `98` setelah sukses
5. Detail transaksi ikut menampilkan status void
6. Mobile menampilkan pesan error backend dengan jelas
7. Data transaksi company lain tidak bisa di-void
8. Stok dianggap kembali setelah void sukses

---

## 14. Catatan untuk QA

Skenario test minimum:

1. Buat transaksi sukses
2. Void transaksi tersebut
3. Cek status transaksi berubah jadi `98`
4. Cek alasan void tampil
5. Cek stok produk kembali
6. Coba void ulang transaksi yang sama
7. Pastikan backend menolak

---

## 15. Kesimpulan

Flow void transaksi di backend saat ini:

* sudah aktif
* sudah mengembalikan stok
* sudah aman untuk multi-company

Tim mobile tinggal mengikuti endpoint dan flow di atas tanpa perlu logika `company_id` manual.
