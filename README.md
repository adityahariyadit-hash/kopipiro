# ☕ Kopipiro Coffee Shop

Aplikasi web pemesanan Kopipiro untuk studi kasus perkuliahan (Week 9–10 Case Study Project). Dibangun menggunakan PHP, MySQL, HTML, CSS, JavaScript, dan REST API berbasis JSON. Dapat dijalankan secara lokal menggunakan XAMPP.

## Fitur

### Customer
- Melihat katalog produk Coffee, Non Coffee, dan Food.
- Register dan login.
- Menambahkan beberapa produk ke keranjang.
- Checkout dengan rincian subtotal, diskon, dan total.
- Customer yang login memperoleh diskon 10%; guest membayar harga normal.

### Owner
- Dashboard ringkasan jumlah order, omzet, dan rata-rata transaksi.
- Grafik penjualan bulanan.
- Menambah produk dan mengunggah gambar.
- Menonaktifkan produk.
- Melihat seluruh order.
- Menugaskan order kepada kasir.
- Mengunduh laporan transaksi dalam format CSV yang dapat dibuka dengan Microsoft Excel.

### Kasir
- Melihat order yang ditugaskan kepada akun kasir tersebut saja.
- Memperbarui status order.

## Teknologi
- PHP 8 atau lebih baru
- MySQL / MariaDB
- HTML5, CSS3, JavaScript
- REST API (JSON)
- Chart.js (CDN)
- XAMPP

## Menjalankan di XAMPP

1. Install dan jalankan XAMPP.
2. Salin folder repository ini ke `C:\xampp\htdocs\kopipiro_xampp`.
3. Jalankan modul **Apache** dan **MySQL**.
4. Buka `http://localhost/phpmyadmin`.
5. Import file `database.sql`.
6. Buka `http://localhost/kopipiro_xampp/`.
7. Daftarkan akun melalui tombol **Login / Register**.
8. Jadikan akun owner melalui phpMyAdmin → tab SQL:

   ```sql
   USE kopipiro;
   UPDATE users
   SET role = 'owner'
   WHERE email = 'emailanda@example.com';
   ```

9. Daftarkan akun kasir melalui halaman Register, lalu ubah role-nya:

   ```sql
   UPDATE users
   SET role = 'cashier'
   WHERE email = 'kasir@example.com';
   ```

10. Logout lalu login kembali agar session memuat role terbaru.

> Registrasi publik hanya membuat akun customer. Role owner/kasir harus ditetapkan secara manual oleh pengelola database.

## Struktur proyek

```text
kopipiro_xampp/
├── api/
│   └── index.php          # REST API
├── assets/
│   ├── app.js             # Interaksi katalog, login, cart, checkout
│   ├── admin.js           # Dashboard owner/kasir
│   └── style.css          # Styling aplikasi
├── uploads/
│   └── .gitkeep           # Folder penyimpanan gambar produk
├── config.php             # Koneksi database dan helper session
├── database.sql           # Database dan data produk awal
├── dashboard.php          # Dashboard owner/kasir
├── index.php              # Halaman customer
├── README.md
├── LICENSE
└── .gitignore
```

## REST API

Base URL lokal:

```text
http://localhost/kopipiro_xampp/api/index.php?action=
```

| Method | Action | Akses | Keterangan |
|---|---|---|---|
| GET | `products` | Publik | Daftar produk dan rating |
| POST | `register` | Publik | Registrasi customer |
| POST | `login` | Publik | Login |
| POST | `logout` | Session | Logout |
| GET | `me` | Session | Informasi pengguna aktif |
| POST | `checkout` | Publik / customer | Membuat pesanan |
| GET | `orders` | Owner / kasir | Daftar order sesuai role |
| GET | `cashiers` | Owner | Daftar akun kasir |
| POST | `assign` | Owner | Menugaskan kasir ke order |
| POST | `status` | Owner / kasir | Mengubah status order |
| GET | `summary` | Owner | Ringkasan dan grafik penjualan |
| GET | `report` | Owner | Unduh laporan CSV |
| GET | `admin_products` | Owner | Daftar produk untuk admin |
| POST | `add_product` | Owner | Tambah produk dan upload gambar |
| POST | `delete_product` | Owner | Menonaktifkan produk |

### Contoh: mengambil katalog produk

```javascript
fetch('api/index.php?action=products')
  .then(response => response.json())
  .then(result => console.log(result.products));
```

### Contoh: login

```javascript
fetch('api/index.php?action=login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    email: 'owner@example.com',
    password: 'password'
  })
})
  .then(response => response.json())
  .then(console.log);
```

### Contoh: checkout

```javascript
fetch('api/index.php?action=checkout', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    customer_name: 'Budi',
    customer_phone: '08123456789',
    items: [
      { product_id: 1, quantity: 2 },
      { product_id: 4, quantity: 1 }
    ]
  })
})
  .then(response => response.json())
  .then(console.log);
```

## Aturan diskon dan transaksi

- Diskon 10% hanya diberikan untuk pengguna dengan role `customer` yang sedang login.
- Pengunjung tanpa login mendapatkan diskon 0%.
- Harga produk diambil ulang dari database oleh backend ketika checkout.
- Total transaksi dihitung di backend; nominal harga dari browser tidak dipercaya.
- Pesanan dan item pesanan disimpan dalam satu transaksi database.

## Catatan pengembangan

Project ini merupakan starter project untuk pembelajaran, bukan aplikasi siap produksi. Sebelum deployment publik, lakukan hal berikut:

- Tambahkan perlindungan CSRF untuk request berbasis session.
- Terapkan HTTPS, secure session cookie, dan regenerasi session ID saat login.
- Batasi percobaan login (rate limiting) dan validasi input lebih ketat.
- Jangan tampilkan detail exception database kepada pengguna di production.
- Tambahkan fitur edit produk, manajemen stok, pembayaran, dan form ulasan/rating.
- Atur hak akses file upload dan batasi eksekusi script di folder `uploads`.
- Simpan kredensial database melalui environment/config rahasia, bukan repository publik.

## Lisensi

MIT. Lihat file `LICENSE`.
