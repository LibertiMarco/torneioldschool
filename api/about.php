<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../includi/db.php';
require_once __DIR__ . '/../includi/about_content.php';
echo json_encode(about_content($staffData, $staffCategories), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
