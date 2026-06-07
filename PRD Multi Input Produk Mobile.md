# PRD Singkat - Multi Input Produk Mobile

## 1. Overview

Fitur ini dibuat untuk mempercepat onboarding merchant baru dan operasional toko saat data produk belum lengkap.

Masalah utama user saat ini:

* produk masih kosong saat pertama kali pakai aplikasi
* input produk satu per satu di mobile terasa lambat
* saat ada pelanggan datang, kasir bisa terhambat karena barang belum terdaftar
* sebagian user tidak punya file Excel awal

Solusi fase awal difokuskan ke input massal langsung dari aplikasi mobile tanpa bergantung pada dashboard web.

---

## 2. Tujuan

1. Mempercepat input banyak produk langsung dari mobile.
2. Memungkinkan kasir menambah produk cepat saat transaksi berlangsung.
3. Mengurangi ketergantungan pada file Excel.
4. Tetap kompatibel dengan struktur backend saat ini.

---

## 3. Scope Tahap 1

Tahap 1 mencakup:

* multi input produk di mobile
* quick add produk dari halaman kasir
* dukungan scan barcode untuk mengisi `product_code`

Belum termasuk di tahap 1:

* import Excel mobile
* OCR foto faktur
* pengaturan banyak harga jual per customer group langsung dari layar multi input

---

## 4. User Story

### Onboarding merchant baru

Sebagai pemilik toko, saya ingin menginput banyak produk sekaligus dari HP agar aplikasi bisa langsung dipakai jualan.

### Quick add saat kasir

Sebagai kasir, saya ingin bisa menambah produk baru langsung dari halaman kasir agar transaksi tidak tertunda.

### Barcode

Sebagai user, saya ingin scan barcode untuk mengisi `product_code` lebih cepat dibanding mengetik manual.

---

## 5. Flow Utama

### A. Multi input produk

1. User buka menu `Produk`
2. User pilih aksi `Tambah Banyak`
3. Mobile menampilkan list form dengan banyak baris input
4. User bisa tambah atau hapus baris
5. User isi data dasar produk
6. User submit semua baris sekaligus
7. Mobile tampilkan hasil:
   * baris berhasil
   * baris gagal
   * alasan gagal per baris

### B. Quick add dari kasir

1. User sedang di halaman transaksi
2. Produk yang dicari tidak ditemukan
3. User pilih `Tambah Produk Cepat`
4. Mobile tampilkan modal / halaman ringkas
5. User isi data minimum
6. Produk tersimpan
7. Produk otomatis bisa dipilih ke keranjang

### C. Scan barcode

1. User menekan ikon scan pada field `product_code`
2. Kamera dibuka
3. Hasil scan mengisi `product_code`
4. User tetap bisa edit manual jika perlu

---

## 6. Field Input Tahap 1

### Multi input

Field per baris:

* `product_name` wajib
* `product_code` opsional
* `selling_price` wajib
* `cost_price` opsional, default `0`
* `stock` opsional, default `0`
* `unit` opsional

### Quick add

Field minimum:

* `product_name` wajib
* `selling_price` wajib

Field tambahan:

* `product_code` opsional
* `cost_price` opsional
* `stock` opsional
* `unit` opsional

Catatan:

Jika `product_code` kosong, backend akan generate otomatis.

---

## 7. Aturan UX

### Multi input

* default tampil 3 baris kosong
* ada tombol `Tambah Baris`
* ada tombol `Hapus`
* jika 1 baris gagal, baris lain tetap bisa berhasil
* tampilkan error langsung di baris terkait

### Quick add

* flow harus ringkas
* setelah sukses, produk otomatis dipilih ke transaksi aktif
* jangan pindahkan user jauh dari halaman kasir

### Barcode

* scan bersifat opsional
* jika hasil scan duplikat, backend menolak
* mobile tampilkan pesan bahwa kode produk sudah dipakai

---

## 8. Harga Jual Tahap 1

Backend saat ini mendukung harga produk per `customer_group`.

Agar flow mobile tetap sederhana di tahap 1:

* mobile hanya mengisi satu `selling_price` utama
* backend akan menyimpan harga tersebut ke customer group default company

Customer group default yang direkomendasikan:

* `USER`

Jika di masa depan dibutuhkan banyak level harga dari layar produk, fitur itu dibuat di tahap lanjutan.

---

## 9. Behavior Validasi

### Validasi per baris

* `product_name` wajib
* `selling_price` wajib dan minimal `0`
* `cost_price` minimal `0`
* `stock` minimal `0`
* `product_code` harus unik per company jika diisi

### Behavior hasil submit

* backend mengembalikan hasil per baris
* mobile menampilkan jumlah sukses dan gagal
* user bisa perbaiki baris gagal tanpa menginput ulang semua data

---

## 10. State Setelah Sukses

### Multi input

Setelah submit berhasil:

* produk baru muncul di list produk
* stok awal tersimpan jika diisi
* harga jual utama tersimpan

### Quick add

Setelah submit berhasil:

* produk langsung bisa dipilih di halaman kasir
* jika stok diisi, stok awal langsung terbentuk

---

## 11. Error Handling

Mobile harus bisa menampilkan pesan berikut dengan jelas:

* nama produk wajib diisi
* harga jual wajib diisi
* kode produk sudah terdaftar
* stok tidak boleh kurang dari 0
* harga tidak boleh kurang dari 0

Jika sebagian baris gagal saat multi input:

* tampilkan baris yang gagal
* pertahankan input user pada baris tersebut

---

## 12. Out of Scope Tahap 1

Belum dikerjakan pada fase ini:

* import file Excel dari mobile
* auto parsing dari foto faktur
* sinkronisasi supplier / purchase order
* edit banyak harga customer group sekaligus

---

## 13. Acceptance Criteria

1. User bisa menambah banyak produk dalam satu submit dari mobile.
2. User bisa menambah produk cepat dari halaman kasir.
3. `product_code` bisa diisi manual, scan barcode, atau generate otomatis dari backend.
4. Produk tetap bisa dibuat walau `product_code` kosong.
5. Harga jual utama tersimpan ke struktur harga backend.
6. Stok awal tersimpan jika user mengisi `stock`.
7. Jika sebagian baris gagal, baris lain tetap bisa berhasil.
8. Mobile bisa menampilkan hasil sukses / gagal per baris.

---

## 14. Tahap Lanjutan

Setelah tahap 1 stabil, roadmap berikutnya:

1. import Excel mobile
2. scan barcode massal
3. OCR foto faktur dengan preview hasil
4. penerimaan barang massal berbasis supplier

---

## 15. Kesimpulan

Fokus fase awal bukan pada file import, tetapi pada percepatan input produk langsung dari aplikasi mobile.

Pendekatan ini paling relevan dengan kondisi user:

* tidak punya dashboard web
* tidak selalu punya file Excel
* butuh kasir tetap jalan walau master produk belum lengkap
