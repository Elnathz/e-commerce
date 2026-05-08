# TDD Changes Tracker

Gunakan file ini untuk mencatat setiap perubahan struktur *database* atau arsitektur sistem yang diimplementasikan selama proses *coding*, agar tidak lupa diperbarui di dokumen TDD (Technical Design Document).

## Sprint 2 - Product Catalog
1. **[PENDING] Database Schema**: Menambahkan kolom `product_variant_id` (Tipe: `unsignedBigInteger`, Nullable, Foreign Key ke `product_variants.id`) pada tabel `product_images`.
   - *Alasan*: Untuk mengakomodasi fitur di mana satu varian produk (misal: warna Merah) bisa memiliki lebih dari satu foto spesifik (tampak depan, belakang, samping), bukan hanya 1 foto.
