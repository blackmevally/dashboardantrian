<?php
/** Read-only logo endpoint from SIMRS/Khanza setting.logo. */
require_once dirname(__DIR__) . '/config/config.php';

try {
    $db = db_connect();
    $result = mysqli_query($db, "SELECT logo FROM setting LIMIT 1");
    if (!$result) {
        http_response_code(404);
        exit;
    }
    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_close($db);

    $logo = $row['logo'] ?? null;
    if ($logo === null || $logo === '') {
        http_response_code(404);
        exit;
    }

    $mime = 'image/png';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_buffer($finfo, $logo);
            finfo_close($finfo);
            if ($detected && strpos($detected, 'image/') === 0) $mime = $detected;
        }
    }

    header('Content-Type: ' . $mime);
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo $logo;
} catch (Throwable $e) {
    http_response_code(500);
}
