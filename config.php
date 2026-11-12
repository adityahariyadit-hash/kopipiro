<?php
// Konfigurasi database Kopipiro untuk XAMPP
define('DB_HOST', 'localhost');
define('DB_NAME', 'kopipiro');
define('DB_USER', 'root');
define('DB_PASS', '');

if (session_status() === PHP_SESSION_NONE) session_start();

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}
function json_out($data, int $status=200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(): array {
    $u = current_user();
    if (!$u) json_out(['success'=>false,'message'=>'Silakan login terlebih dahulu.'], 401);
    return $u;
}
function require_role(array $roles): array {
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) json_out(['success'=>false,'message'=>'Akses ditolak.'], 403);
    return $u;
}
function money($n): string { return 'Rp'.number_format((float)$n, 0, ',', '.'); }
