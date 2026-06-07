# Technical Spec - Backend Multi Input Produk Tahap 1

## 1. Tujuan

Menyiapkan backend agar tim mobile dapat mengimplementasikan:

* multi input produk
* quick add produk dari kasir
* scan barcode untuk `product_code`

Spesifikasi ini fokus pada fase awal dan memanfaatkan struktur existing:

* `products`
* `product_prices`
* `stock_mutations`
* `StockService`

---

## 2. Endpoint Tahap 1

### A. Bulk create products

`POST /api/products/bulk`

Tujuan:

* membuat banyak produk dalam satu request

### B. Quick create product

`POST /api/products/quick-create`

Tujuan:

* membuat 1 produk secara ringkas dari halaman kasir

### C. Support existing endpoint

`POST /api/products`

Boleh tetap dipertahankan untuk form detail / admin flow, tetapi mobile fase awal direkomendasikan memakai dua endpoint baru di atas.

---

## 3. Aturan Data

### Company isolation

Semua data harus otomatis memakai `company_id` dari user login.

Client tidak boleh mengirim `company_id`.

### Product code

* `product_code` boleh kosong
* jika kosong, backend generate otomatis
* jika diisi, harus unik per `company_id`
* barcode diperlakukan sebagai `product_code`

### Harga jual

Backend saat ini menyimpan harga di tabel `product_prices` berdasarkan `customer_group_id`.

Untuk tahap 1:

* request cukup mengirim 1 `selling_price`
* backend menyimpan harga ke customer group default company

Default group yang direkomendasikan:

* group code `USER`

Jika customer group `USER` tidak ditemukan, backend mengembalikan error terstruktur.

---

## 4. Request Contract

### A. POST `/api/products/bulk`

#### Headers

```text
Accept: application/json
Authorization: Bearer <token>
Content-Type: application/json
```

#### Request body

```json
{
  "items": [
    {
      "product_name": "Aqua 600ml",
      "product_code": "899000100001",
      "unit": "PCS",
      "cost_price": 2500,
      "selling_price": 3000,
      "stock": 24
    },
    {
      "product_name": "Teh Botol",
      "product_code": null,
      "unit": "BOTOL",
      "cost_price": 4000,
      "selling_price": 5000,
      "stock": 12
    }
  ]
}
```

#### Validasi

* `items` wajib array
* `items` minimal 1 baris
* `items.*.product_name` wajib
* `items.*.product_code` nullable, string, max 50, unik per company
* `items.*.unit` nullable, string, max 50
* `items.*.cost_price` nullable, numeric, min 0
* `items.*.selling_price` wajib, numeric, min 0
* `items.*.stock` nullable, numeric, min 0

Catatan:

* `cost_price` default `0`
* `stock` default `0`

### B. POST `/api/products/quick-create`

#### Request body

```json
{
  "product_name": "Mie Goreng",
  "product_code": null,
  "unit": "PCS",
  "cost_price": 2800,
  "selling_price": 3500,
  "stock": 10
}
```

Validasi sama dengan 1 item pada endpoint bulk.

---

## 5. Response Contract

### A. Response success bulk

Gunakan pola partial success agar 1 baris gagal tidak menggagalkan seluruh request.

Status code direkomendasikan:

* `200 OK` jika ada hasil diproses
* `422` hanya untuk request yang secara struktur tidak valid

Contoh:

```json
{
  "success": true,
  "message": "Bulk create produk selesai diproses",
  "data": {
    "summary": {
      "total": 3,
      "success": 2,
      "failed": 1
    },
    "items": [
      {
        "row": 1,
        "success": true,
        "message": "Produk berhasil dibuat",
        "product": {
          "id": 10,
          "product_name": "Aqua 600ml",
          "product_code": "899000100001",
          "stock": 24
        }
      },
      {
        "row": 2,
        "success": false,
        "message": "Kode produk sudah terdaftar",
        "errors": {
          "product_code": [
            "Kode produk sudah terdaftar"
          ]
        }
      }
    ]
  }
}
```

### B. Response success quick create

Status code:

* `201 Created`

Contoh:

```json
{
  "success": true,
  "message": "Produk berhasil dibuat",
  "data": {
    "id": 11,
    "product_name": "Mie Goreng",
    "product_code": "PRD-20260606-0001",
    "unit": "PCS",
    "cost_price": "2800.00",
    "stock": "10.00",
    "status": "00",
    "prices": [
      {
        "customer_group_id": 1,
        "selling_price": "3500.00",
        "status": "00"
      }
    ]
  }
}
```

---

## 6. Behavior Backend

### A. Pembuatan produk

Untuk setiap item:

1. validasi data
2. tentukan `product_code`
3. create data produk dengan `stock = 0`
4. create `product_prices` untuk customer group default
5. jika `stock > 0`, panggil `StockService->applyInitialStock(...)`
6. return hasil per item

### B. Partial success

Pada bulk create:

* gunakan loop per item
* setiap item diproses dalam transaction terpisah
* kegagalan pada 1 item tidak rollback item lain

### C. Auto generate product_code

Jika `product_code` kosong, backend generate kode.

Format rekomendasi:

```text
PRD-YYYYMMDD-XXXX
```

Contoh:

```text
PRD-20260606-0001
```

Atau alternatif yang lebih sederhana:

```text
PRD-000001
```

Syarat utama:

* unik per company
* tidak bentrok dengan barcode manual

### D. Default price group resolver

Buat helper / service untuk mencari customer group default:

1. cari `group_code = USER` dalam company aktif user
2. jika tidak ada, ambil customer group aktif pertama berdasarkan `id`
3. jika tetap tidak ada, return error konfigurasi

Fallback nomor 2 direkomendasikan agar sistem lebih tahan terhadap data lama yang belum rapi.

---

## 7. Struktur Implementasi yang Disarankan

### Controller

Tambahkan method pada `ProductController`:

* `bulkStore(Request $request)`
* `quickStore(Request $request)`

### Service

Disarankan buat service baru:

* `ProductBulkService`

Tanggung jawab:

* validasi item level
* generate `product_code`
* resolve customer group default
* create product
* create selling price utama
* apply stok awal
* format hasil partial success

### Reuse existing

Tetap gunakan:

* `StockService`

Opsional refactor:

* ekstrak logika create product single dari `ProductController::store()` ke service bersama agar tidak duplikasi

---

## 8. Routing

Tambahkan route baru di `routes/api.php` dalam group `auth:sanctum`:

```php
Route::post('/products/bulk', [ProductController::class, 'bulkStore']);
Route::post('/products/quick-create', [ProductController::class, 'quickStore']);
```

---

## 9. Validasi Error yang Perlu Distandarkan

Pesan yang disarankan:

* `Nama produk wajib diisi.`
* `Harga jual wajib diisi.`
* `Kode produk sudah terdaftar.`
* `Harga modal tidak boleh kurang dari 0.`
* `Harga jual tidak boleh kurang dari 0.`
* `Stok tidak boleh kurang dari 0.`
* `Customer group default belum tersedia.`

Untuk bulk create, error harus dikembalikan per `row`.

---

## 10. Dampak ke Mobile

### Multi input

Mobile dapat:

* kirim banyak baris sekaligus
* tampilkan hasil per baris
* retry hanya baris yang gagal

### Quick add

Mobile dapat:

* create produk baru tanpa pindah flow jauh dari kasir
* setelah sukses, insert produk ke state pencarian / keranjang

### Barcode

Mobile cukup kirim hasil scan ke `product_code`.

Backend yang bertugas memvalidasi duplikasi.

---

## 11. Acceptance Criteria Backend

1. Backend menyediakan endpoint `POST /api/products/bulk`.
2. Backend menyediakan endpoint `POST /api/products/quick-create`.
3. `product_code` bisa kosong dan akan digenerate otomatis.
4. `product_code` tetap unik per company.
5. `selling_price` utama tersimpan ke `product_prices`.
6. `stock` awal memicu pembuatan mutasi `INITIAL`.
7. Bulk create mendukung partial success.
8. Semua data tetap terisolasi per `company_id`.

---

## 12. Tahap Setelah Ini

Setelah tahap 1 selesai dan dipakai mobile, backend bisa lanjut ke:

1. `POST /api/products/import`
2. upload file Excel / CSV dari mobile
3. preview hasil import
4. OCR faktur dengan preview item

---

## 13. Kesimpulan

Tahap 1 backend sebaiknya fokus pada kemampuan input produk yang paling terasa manfaatnya untuk mobile:

* bulk create
* quick create
* auto `product_code`
* pemetaan harga jual utama ke customer group default

Dengan scope ini, tim mobile bisa langsung mulai implementasi tanpa menunggu fitur import file atau OCR.
