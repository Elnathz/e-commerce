# TDD Changes Tracker

Gunakan file ini untuk mencatat setiap perubahan struktur *database* atau arsitektur sistem yang diimplementasikan selama proses *coding*, agar tidak lupa diperbarui di dokumen TDD (Technical Design Document).

## Sprint 2 - Product Catalog
1. **[PENDING] Database Schema**: Menambahkan kolom `product_variant_id` (Tipe: `unsignedBigInteger`, Nullable, Foreign Key ke `product_variants.id`) pada tabel `product_images`.
   - *Alasan*: Untuk mengakomodasi fitur di mana satu varian produk (misal: warna Merah) bisa memiliki lebih dari satu foto spesifik (tampak depan, belakang, samping), bukan hanya 1 foto.

## Sprint 3 - Storefront & Public Routes
2. **[PENDING] Routing Architecture**: Menambahkan dan merestrukturisasi rute untuk antarmuka publik (Pembeli):
   - `GET /` -> Digunakan sebagai halaman utama toko sekaligus katalog produk (menyerupai *grid* Shopee).
   - `GET /products/{slug}` -> Halaman detail produk untuk pembeli.
   - Menghapus fungsi *redirect* otomatis ke `/admin/dashboard` saat login. Semua *user* (termasuk Admin) akan diarahkan ke `/` setelah *login*. Akses Admin dipindahkan ke menu *dropdown* profil di *Storefront*.
   - *Alasan*: Menyatukan pengalaman pengguna awal di halaman publik, dan membuat arsitektur rute etalase yang lebih standar untuk e-commerce (langsung menampilkan produk di *root url*).
