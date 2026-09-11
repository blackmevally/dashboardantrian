<?php
/**
 * Filter data untuk tampil di display.
 *
 * $poli_filter   : kode poli. Kosong = semua poli.
 * $dokter_filter : kode dokter. Kosong = semua dokter yang sesuai filter poli.
 */
$poli_filter   = ""; // Kode poli
$dokter_filter = ""; // Kode dokter (bisa dikosongkan jika ingin semua)

return [
    'poli_filter'   => trim($poli_filter),
    'dokter_filter' => trim($dokter_filter),
];
