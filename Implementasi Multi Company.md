# PRD - Implementasi Multi Company (Multi Tenant) pada Kasir UMKM

## Latar Belakang

Saat ini aplikasi Kasir UMKM hanya digunakan untuk 1 bisnis (single company).

Ke depan aplikasi akan dijual dan digunakan oleh banyak UMKM yang berbeda menggunakan aplikasi dan server yang sama.

Diperlukan implementasi Multi Company (Multi Tenant) agar setiap UMKM memiliki data yang terisolasi dan tidak dapat melihat data UMKM lain.

Pendekatan yang digunakan adalah:

* Single Database
* Shared Tables
* Isolasi data menggunakan company_id

---

# Tujuan

1. Mendukung banyak UMKM dalam satu aplikasi.
2. Memastikan data antar UMKM tidak tercampur.
3. Memudahkan maintenance dan deployment.
4. Tetap menggunakan satu database MySQL.

---

# Arsitektur

## Sebelum

```text
User
 ├── Product
 ├── Customer
 ├── Sales
 └── Stock
```

Semua data berada dalam satu ruang data.

---

## Sesudah

```text
Company
 ├── Users
 ├── Products
 ├── Customers
 ├── Sales
 ├── Stock
 └── Settings
```

Setiap data memiliki relasi ke company.

---

# Table Baru

## companies

Buat tabel baru:

```sql
companies
```

Field:

| Field        | Type                  |
| ------------ | --------------------- |
| id           | bigint                |
| company_name | varchar(255)          |
| company_code | varchar(100)          |
| address      | text                  |
| phone        | varchar(50)           |
| email        | varchar(255)          |
| logo         | varchar(255) nullable |
| status       | tinyint               |
| created_at   | timestamp             |
| updated_at   | timestamp             |

Keterangan:

* company_code harus unique.
* status:

  * 1 = Active
  * 0 = Inactive

---

# Perubahan Table Existing

Tambahkan field:

```sql
company_id BIGINT UNSIGNED
```

pada tabel berikut:

## users

```sql
company_id
```

## products

```sql
company_id
```

## customers

```sql
company_id
```

## customer_groups

```sql
company_id
```

## product_prices

```sql
company_id
```

## sales_h

```sql
company_id
```

## sales_d

```sql
company_id
```

## stock_mutations

```sql
company_id
```

## payment_methods

```sql
company_id
```

## app_settings

```sql
company_id
```

---

# Foreign Key

Semua company_id harus mengarah ke:

```sql
companies.id
```

---

# User Login

Saat login berhasil, response API harus mengembalikan informasi company.

Contoh:

```json
{
    "token": "...",
    "user": {
        "id": 1,
        "name": "Admin",
        "company_id": 1
    },
    "company": {
        "id": 1,
        "company_name": "Toko Maju Jaya"
    }
}
```

---

# Data Isolation

Semua query data harus difilter berdasarkan company_id user login.

Contoh:

## Produk

Sebelum:

```php
Product::all();
```

Sesudah:

```php
Product::where(
    'company_id',
    auth()->user()->company_id
)->get();
```

---

## Customer

Sebelum:

```php
Customer::query();
```

Sesudah:

```php
Customer::where(
    'company_id',
    auth()->user()->company_id
);
```

---

# Auto Assign Company

Saat create data baru:

Produk
Customer
Penjualan
Mutasi Stok
Harga Produk

company_id harus otomatis diisi dari user yang login.

Contoh:

```php
$product->company_id =
auth()->user()->company_id;
```

Frontend tidak boleh mengirim company_id.

Backend yang menentukan.

---

# Validasi Security

User tidak boleh dapat:

* Melihat data company lain
* Mengedit data company lain
* Menghapus data company lain

Semua endpoint harus memvalidasi company_id.

Contoh:

Sebelum update:

```php
Product::find($id);
```

Sesudah:

```php
Product::where('id', $id)
       ->where(
           'company_id',
           auth()->user()->company_id
       )
       ->firstOrFail();
```

---

# Seeder Awal

Buat seeder:

## CompanySeeder

Data awal:

```text
ID : 1
Name : Demo Company
Code : DEMO
Status : Active
```

Semua data existing saat migrasi akan dipindahkan ke:

```text
company_id = 1
```

---

# Super Admin

Tambahkan role:

```text
SUPER_ADMIN
```

Hak akses:

* Kelola company
* Melihat semua company
* Melihat semua user
* Membuat company baru
* Menonaktifkan company

Role biasa:

```text
ADMIN
KASIR
OWNER
```

Hanya melihat data company masing-masing.

---

# API Baru

## Company List

GET

```text
/api/companies
```

Super Admin only.

---

## Create Company

POST

```text
/api/companies
```

---

## Update Company

PUT

```text
/api/companies/{id}
```

---

## Delete Company

DELETE

```text
/api/companies/{id}
```

Soft Delete lebih disarankan.

---

# Migration Existing Data

Semua data existing harus diupdate:

```sql
UPDATE users SET company_id = 1;
UPDATE products SET company_id = 1;
UPDATE customers SET company_id = 1;
UPDATE customer_groups SET company_id = 1;
UPDATE product_prices SET company_id = 1;
UPDATE sales_h SET company_id = 1;
UPDATE sales_d SET company_id = 1;
UPDATE stock_mutations SET company_id = 1;
UPDATE payment_methods SET company_id = 1;
UPDATE app_settings SET company_id = 1;
```

---

# Acceptance Criteria

1. User Company A tidak dapat melihat data Company B.
2. User Company A tidak dapat mengubah data Company B.
3. Semua data baru otomatis memiliki company_id.
4. Login mengembalikan informasi company.
5. Semua endpoint telah menggunakan filtering company_id.
6. Existing data tetap dapat digunakan setelah migrasi.
7. Super Admin dapat mengelola seluruh company.
8. Tidak ada perubahan pada alur bisnis existing selain penambahan company isolation.
