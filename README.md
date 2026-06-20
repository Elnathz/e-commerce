<div align="center">

# 🛒 Elnathz E-Commerce

**Platform Marketplace E-Commerce Indonesia — Dibangun dengan VILT Stack**

[![Laravel](https://img.shields.io/badge/Laravel_12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![Vue.js](https://img.shields.io/badge/Vue_3-35495E?style=for-the-badge&logo=vue.js&logoColor=4FC08D)](https://vuejs.org)
[![Inertia.js](https://img.shields.io/badge/Inertia.js-9553E9?style=for-the-badge&logo=inertia&logoColor=white)](https://inertiajs.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)](https://tailwindcss.com)
[![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com)
[![PHPUnit](https://img.shields.io/badge/PHPUnit-366488?style=for-the-badge&logo=php&logoColor=white)](https://phpunit.de)
[![Vitest](https://img.shields.io/badge/Vitest-6E9F18?style=for-the-badge&logo=vitest&logoColor=white)](https://vitest.dev)

</div>

---

## 📖 Tentang Proyek

**MegaMart E-Commerce** adalah aplikasi *marketplace* e-commerce yang dirancang khusus untuk konteks Indonesia. Proyek ini dibangun dengan arsitektur **VILT Stack** (Vue 3 + Inertia.js + Laravel 12 + Tailwind CSS), menggabungkan pengalaman pengguna *Single Page Application* (SPA) dengan keandalan *backend* penuh milik ekosistem Laravel.

Proyek ini bukan sekadar toko online biasa. Ini adalah sistem *marketplace* lengkap dengan *business logic* yang matang, mencakup siklus hidup pesanan penuh mulai dari keranjang belanja hingga pengembalian barang, integrasi pembayaran dan pengiriman nyata dengan provider Indonesia, serta *dashboard* operasional berbasis data untuk admin.

---

## 🗺️ Arsitektur Sistem

Aplikasi ini menggunakan **Laravel Monolith** dengan pemisahan tanggung jawab yang jelas melalui beberapa pola arsitektur:

```
┌─────────────────────────────────────────────────────────┐
│                   Browser (Vue 3 SPA)                   │
│         Storefront  │  Customer Area  │  Admin Panel    │
└─────────────────────┬───────────────────────────────────┘
                      │ Inertia.js (No API, No AJAX manual)
┌─────────────────────▼───────────────────────────────────┐
│               Laravel 12 (Backend Monolith)             │
│                                                         │
│  Controllers → Services → Events → Listeners           │
│                    │           │                        │
│               (Business)  (Observers,                  │
│                            Notifications,               │
│                            Queue Jobs)                  │
└─────────────────────┬───────────────────────────────────┘
                      │
        ┌─────────────┴──────────────┐
        │ MySQL          │  Redis     │
        │ (Data Utama)   │  (Cache)   │
        └────────────────┴────────────┘
```

**Pola Arsitektur yang Digunakan:**

- **Service Layer Pattern** — Logika bisnis dienkapsulasi di `app/Services/`, bukan di Controller.
- **Event-Driven Architecture** — Setiap transisi *state* penting (pesanan dibayar, retur disetujui, dll.) didispatch melalui Event agar dapat diproses oleh *listener* dan observer secara terpisah.
- **Queue-Driven Background Processing** — Notifikasi, *export* data, dan tugas berat lainnya diproses di *background* melalui Laravel Queue.
- **Idempotent Notification System** — Sistem notifikasi menggunakan tabel `notification_dispatches` untuk mencegah pengiriman email duplikat.

---

## ✨ Fitur Lengkap

### 🏪 Storefront (Halaman Publik & Pelanggan)

- **Halaman Beranda** — Hero Slides yang dapat dikonfigurasi admin, dengan link ke produk atau kategori tertentu.
- **Katalog Produk** — Pencarian produk, filter kategori, tampilan grid responsif.
- **Halaman Detail Produk** — Galeri gambar produk, pemilihan varian, ulasan & rating pelanggan dengan gambar bukti.
- **Keranjang Belanja** — Manajemen item keranjang *real-time* dengan kalkulasi harga otomatis.
- **Checkout Lengkap** — Pemilihan alamat pengiriman, kalkulasi ongkos kirim *real-time*, aplikasi kode voucher/promosi, dan ringkasan pesanan.
- **Flash Sale** — Item flash sale dengan harga dan stok terpisah, tampil di halaman produk.
- **Manajemen Pesanan** — Riwayat pesanan, detail pesanan, konfirmasi penerimaan barang.
- **Sistem Retur** — Pengajuan retur barang dengan unggah foto bukti (hingga 5 foto), pelacakan status retur secara *real-time*.
- **Ulasan & Rating** — Menulis ulasan setelah pesanan selesai, mengedit ulasan, unggah foto ulasan.
- **Manajemen Profil & Alamat** — Data diri pengguna dan manajemen banyak alamat pengiriman.

### 🔐 Autentikasi

- Registrasi, Login, Logout dengan Laravel Breeze.
- Manajemen sesi aman berbasis Laravel Sanctum.

### ⚙️ Admin Panel (Operasional)

Admin dashboard dirancang sebagai **panel kontrol operasional**, bukan sekadar laporan statis. Setiap widget menjawab pertanyaan: *"Apa yang perlu dilakukan sekarang?"*

| Modul Admin                  | Deskripsi                                                                                                    |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------ |
| **Dashboard**          | Widget operasional berprioritas: pelanggaran SLA, pesanan belum diproses, pengiriman tertunda, stok kritis   |
| **Manajemen Produk**   | CRUD produk + varian (ukuran/warna), multi-gambar per produk/varian                                          |
| **Manajemen Kategori** | Hierarki kategori produk                                                                                     |
| **Manajemen Pesanan**  | Proses pesanan, ubah status pengiriman, lihat detail lengkap                                                 |
| **Manajemen Retur**    | Review pengajuan retur, proses inspeksi, setujui/tolak, kelola restock                                       |
| **Manajemen Ulasan**   | Moderasi ulasan, balas ulasan pelanggan, hapus ulasan tidak sesuai                                           |
| **Manajemen Promosi**  | Buat kode voucher dengan kuota, tipe diskon (persen/nominal), validasi masa berlaku, rekonsiliasi penggunaan |
| **Flash Sale**         | Buat & kelola event flash sale dengan harga dan stok khusus per produk                                       |
| **Hero Slides**        | Manajemen banner beranda dengan link ke produk/kategori, pengaturan urutan                                   |
| **Analytics**          | Grafik revenue, tren pesanan, ranking produk, status pembayaran                                              |
| **Activity Log**       | Audit trail seluruh aksi admin                                                                               |
| **Export Data**        | Export data pesanan/produk ke file (diproses via queue)                                                      |
| **Pengaturan Situs**   | Konfigurasi global aplikasi                                                                                  |

---

## 💰 Integrasi Pembayaran & Pengiriman

Proyek ini terintegrasi langsung dengan provider nyata yang umum digunakan di Indonesia:

### Pembayaran

- **[iPaymu](https://ipaymu.com)** — Gateway pembayaran Indonesia, mendukung transfer bank, virtual account, dan dompet digital.

### Pengiriman

Sistem pengiriman menggunakan abstraksi `ShippingProviderInterface`, sehingga mudah mengganti atau menambah provider baru.

- **[RajaOngkir](https://rajaongkir.com)** — Kalkulasi ongkos kirim dari ratusan ekspedisi Indonesia.
- **[Komerce](https://komerce.id)** — Aggregator layanan pengiriman.

---

## 🧮 Domain Bisnis Inti

### Alur Pesanan (Order Lifecycle)

```
Keranjang → Checkout → Pembayaran Pending
   → Pembayaran Dikonfirmasi → Diproses → Dikirim
   → Selesai (Pelanggan Konfirmasi Penerimaan)
   → [Opsional] Retur Diajukan → Inspeksi → Selesai/Ditolak
```

### Sistem Promosi

- Promosi memiliki lifecycle: `reserved → confirmed → released`
- Validasi kuota menggunakan `lockForUpdate()` untuk mencegah *race condition* saat banyak pengguna memakai kode yang sama secara bersamaan.
- Rekonsiliasi otomatis via job `ReconcilePromotionUsages`.

### Sistem Retur & Refund

- Kalkulasi refund mempertimbangkan diskon voucher yang diberikan secara proporsional (*prorated*).
- Formula: `discount_ratio = order.discount_amount / order.subtotal`
- Ongkos kirim **tidak** dikembalikan.
- Mendukung retur parsial (sebagian item dalam satu pesanan).

### Logika Keuangan

Semua kalkulasi yang menyentuh: pesanan, pembayaran, promosi, retur, refund, dan revenue analytics diperlakukan dengan standar ketat: tidak ada penyederhanaan, setiap perubahan harus dianalisis dampak bisnisnya.

---

## 🛠️ Teknologi yang Digunakan

### Backend

| Teknologi                                        | Versi | Kegunaan                                          |
| ------------------------------------------------ | ----- | ------------------------------------------------- |
| [Laravel](https://laravel.com)                      | ^12.0 | Framework backend utama                           |
| [Inertia.js](https://inertiajs.com) (Laravel)       | ^2.0  | Jembatan backend-frontend tanpa REST API terpisah |
| [Laravel Sanctum](https://laravel.com/docs/sanctum) | ^4.0  | Otentikasi sesi                                   |
| [Ziggy](https://github.com/tightenco/ziggy)         | ^2.0  | Named routing Laravel tersedia di Vue             |
| [Intervention Image](https://image.intervention.io) | ^4.1  | Manipulasi & optimasi gambar upload               |
| MySQL                                            | -     | Database utama                                    |
| Redis                                            | -     | Cache & queue                                     |

### Frontend

| Teknologi                              | Versi | Kegunaan                             |
| -------------------------------------- | ----- | ------------------------------------ |
| [Vue.js 3](https://vuejs.org)             | ^3.4  | Framework UI (Composition API)       |
| [Inertia.js](https://inertiajs.com) (Vue) | ^2.0  | SPA tanpa pengelolaan routing manual |
| [Tailwind CSS](https://tailwindcss.com)   | ^3/4  | Utility-first styling                |
| [Chart.js](https://www.chartjs.org)       | ^4.5  | Visualisasi data analytics           |
| [Vue-Chartjs](https://vue-chartjs.org)    | ^5.3  | Wrapper Chart.js untuk Vue           |
| [Vite](https://vitejs.dev)                | ^7.0  | Bundler frontend                     |

### Testing

| Teknologi      | Kegunaan                               |
| -------------- | -------------------------------------- |
| PHPUnit        | Unit & Feature testing backend         |
| Vitest         | Unit testing frontend (Vue components) |
| Vue Test Utils | Testing utilitas komponen Vue          |

---

## 🚀 Cara Menjalankan Proyek

### Persyaratan

- **PHP** >= 8.2
- **Composer**
- **Node.js** >= 18 & **NPM**
- **MySQL** (atau SQLite untuk development lokal)
- **Redis** (opsional untuk development, wajib untuk production queue)

### Langkah Instalasi

**1. Clone repositori**

```bash
git clone https://github.com/Elnathz/e-commerce.git
cd e-commerce
```

**2. Install dependensi**

```bash
composer install
npm install
```

**3. Konfigurasi environment**

```bash
cp .env.example .env
```

Buka `.env` dan sesuaikan konfigurasi berikut:

```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=elnathz_ecommerce
DB_USERNAME=root
DB_PASSWORD=

# Payment (iPaymu)
IPAYMU_VA=your_virtual_account
IPAYMU_API_KEY=your_api_key
IPAYMU_URL=https://sandbox.ipaymu.com/api/v2

# Shipping (RajaOngkir)
RAJAONGKIR_API_KEY=your_api_key

# Queue (untuk background jobs)
QUEUE_CONNECTION=database  # atau redis untuk production
```

**4. Generate application key**

```bash
php artisan key:generate
```

**5. Jalankan migrasi & seeder**

```bash
php artisan migrate --seed
```

**6. Jalankan development server**

Jalankan semua server sekaligus (Laravel + Queue + Vite + Log Watcher):

```bash
composer dev
```

Atau jalankan secara terpisah:

```bash
# Terminal 1: Backend
php artisan serve

# Terminal 2: Frontend
npm run dev

# Terminal 3: Queue Worker (untuk notifikasi & export)
php artisan queue:listen --tries=1
```

Akses aplikasi di: **http://localhost:8000**

---

## 🧪 Pengujian

Proyek ini menggunakan standar testing modern di dua lapisan.

**Backend (PHPUnit)**

```bash
php artisan test
# atau
composer test
```

**Frontend (Vitest)**

```bash
npm run test
```

---

## 📁 Struktur Direktori Penting

```
e-commerce/
├── app/
│   ├── Http/Controllers/
│   │   ├── Admin/          # Controller khusus admin panel
│   │   └── ...             # Controller storefront & customer
│   ├── Models/             # 30+ Eloquent Model (Order, Product, Promotion, dll.)
│   ├── Services/           # Business logic layer
│   │   ├── Payment/        # IPaymuService
│   │   ├── Shipping/       # RajaOngkir & Komerce
│   │   ├── CartService.php
│   │   ├── PromotionService.php
│   │   ├── FlashSaleService.php
│   │   └── AnalyticsDashboardService.php
│   ├── Events/             # Business events (OrderPaid, ReturnApproved, dll.)
│   ├── Listeners/          # Event handlers
│   ├── Jobs/               # Background jobs (Export, Notifikasi)
│   └── Notifications/      # Kelas notifikasi (Email, dll.)
├── database/
│   └── migrations/         # 55+ migration files
├── resources/js/
│   ├── Pages/
│   │   ├── Admin/          # UI Admin Panel (Dashboard, Products, Orders, dll.)
│   │   ├── Storefront/     # UI Pelanggan (Catalog, Cart, Checkout, dll.)
│   │   ├── Orders/         # Halaman order tracking
│   │   └── Returns/        # Halaman pengajuan & tracking retur
│   ├── Components/         # Komponen Vue yang dapat digunakan ulang
│   └── Layouts/            # Layout aplikasi
└── tests/                  # Suite testing
```

---

## 📜 Lisensi

Proyek ini bersifat privat dan merupakan karya studi. Seluruh hak dilindungi.
