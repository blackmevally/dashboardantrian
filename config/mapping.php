<?php
/**
 * Filter data untuk tampil di display.
 *
 * Gunakan kode poli persis seperti pada tabel SIMRS `jadwal`.
 * $poli_filter   : satu atau beberapa kode poli, dipisahkan koma.
 * $dokter_filter : satu atau beberapa kode dokter, dipisahkan koma.
 * Kosong = semua.
 */
$poli_filter   = "'U003','U053','INT','OBG','U0004','U0027'";
$dokter_filter = "";

return [
    'poli_filter'   => trim($poli_filter),
    'dokter_filter' => trim($dokter_filter),
];
