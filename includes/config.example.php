<?php
// ============================================================
//  NutriAI — Config Template
//  Copy this file to config.php and fill in your values.
//  config.php is excluded from git — never commit real secrets.
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');          // Your MySQL username
define('DB_PASS', '');              // Your MySQL password
define('DB_NAME', 'nutriai_db');

define('GEMINI_API_KEY', 'sk-ant-YOUR_ANTHROPIC_API_KEY_HERE');
define('GEMINI_MODEL',   'gemini-sonnet-4-20250514');

define('APP_NAME', 'NutriAI');
define('APP_URL',  'http://localhost/nutriai-meal-planner');  // No trailing slash

// ── PDO Connection ──────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ── Session ─────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Helpers ─────────────────────────────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function currentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void {
    header('Location: ' . APP_URL . '/' . ltrim($path, '/'));
    exit;
}

function flashSet(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flashGet(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}