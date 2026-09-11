# Dashboard Antrian RSPM

Dashboard realtime untuk menampilkan nomor antrean yang sedang dipanggil di ruang tunggu RSU Permata Medika Kebumen.

## Prinsip
- Aplikasi terpisah dari `pasienrspm`.
- Read-only terhadap database SIMRS/Khanza.
- Tidak menyimpan data pasien.
- Hanya menampilkan poli, dokter, dan nomor yang sedang dipanggil.
- Mapping menggunakan kombinasi `kd_poli + kd_dokter`.

## Struktur awal
- `index.php` — dashboard 1-page fullscreen.
- `api/queue.php` — API read-only.
- `config/config.php` — koneksi database lokal, tanpa password di repository.
- `config/mapping.php` — mapping poli/dokter yang ditampilkan.
- `assets/css/display.css` — desain dashboard.
- `assets/js/display.js` — polling realtime.

## Status Khanza
- `0` = menunggu
- `1` = berikutnya
- `2` = sedang dipanggil
- `3` = sudah dipanggil/sebelumnya

Dashboard hanya memakai status `2` untuk nomor yang sedang dipanggil.
