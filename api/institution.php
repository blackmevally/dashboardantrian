<?php
/**
 * Read-only institution identity from SIMRS/Khanza setting table.
 * Returns nama_instansi and logo as base64 for the public display.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

date_default_timezone_set('Asia/Jakarta');

require_once dirname(__DIR__) . '/config/config.php';

try {
    $db = db_connect();
    $sql = "SELECT nama_instansi, alamat_instansi, kabupaten, propinsi, kontak, email, wallpaper, logo FROM setting LIMIT 1";
    $result = mysqli_query($db, $sql);
    if (!$result) {
        throw new RuntimeException('Setting query failed');
    }

    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_close($db);

    if (!$row) {
        echo json_encode([
            'ok' => true,
            'nama_instansi' => '',
            'alamat_instansi' => '',
            'kabupaten' => '',
            'propinsi' => '',
            'kontak' => '',
            'email' => '',
            'logo' => null,
            'logo_mime' => null
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $logo = null;
    $logoMime = null;
    if (!empty($row['logo'])) {
        $logo = base64_encode($row['logo']);
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $logoMime = finfo_buffer($finfo, $row['logo']);
                finfo_close($finfo);
            }
        }
        if (!$logoMime) $logoMime = 'image/png';
    }

    echo json_encode([
        'ok' => true,
        'nama_instansi' => (string)$row['nama_instansi'],
        'alamat_instansi' => (string)($row['alamat_instansi'] ?? ''),
        'kabupaten' => (string)($row['kabupaten'] ?? ''),
        'propinsi' => (string)($row['propinsi'] ?? ''),
        'kontak' => (string)$row['kontak'],
        'email' => (string)$row['email'],
        'logo' => $logo,
        'logo_mime' => $logoMime
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'nama_instansi' => '',
        'logo' => null,
        'logo_mime' => null,
        'error' => 'Institution data unavailable'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
