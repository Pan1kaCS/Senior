<?php

declare(strict_types=1);

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function get_theme(): string
{
    $allowed = ['blackwhite'];
    $t = $_GET['theme'] ?? ($_SESSION['theme'] ?? 'blackwhite');
    if (!in_array($t, $allowed, true)) {
        $t = 'blackwhite';
    }
    $_SESSION['theme'] = $t;
    return $t;
}

function rate_limit(string $key, int $max, int $perSeconds): void
{
    $bucket = 'rl_' . $key;
    $now = time();
    $window = $now - $perSeconds;
    if (!isset($_SESSION[$bucket])) {
        $_SESSION[$bucket] = ['count' => 0, 'since' => $now];
    }
    $since = (int)($_SESSION[$bucket]['since'] ?? $now);
    if ($since < $window) {
        $_SESSION[$bucket] = ['count' => 1, 'since' => $now];
        return;
    }
    $_SESSION[$bucket]['count'] = (int)($_SESSION[$bucket]['count'] ?? 0) + 1;
    if ((int)$_SESSION[$bucket]['count'] > $max) {
        http_response_code(429);
        echo 'Too many requests';
        exit;
    }
}

/**
 * Проверка: является ли текущий пользователь админом
 */
function is_admin_user(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $steamId64 = $_SESSION['steam']['steam_id64']
        ?? $_SESSION['user_admin']
        ?? $_SESSION['steamid']
        ?? '';

    if (empty($steamId64)) {
        return false;
    }

    // Кэш результата, чтобы navbar не делал запрос к БД на каждый запрос страницы
    $cache = $_SESSION['admin_check'] ?? null;
    if (
        is_array($cache)
        && ($cache['sid'] ?? null) === $steamId64
        && isset($cache['ts'])
        && (int)$cache['ts'] > (time() - 60)
    ) {
        return (bool)($cache['is_admin'] ?? false);
    }

    global $pdo, $prefix;

    // Настраиваем таймауты подключения
    $connectTimeoutSeconds = 2;
    $queryTimeoutSeconds = 2;

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $cfgFile = __DIR__ . '/../../storage/sessions/db.php';
        if (!is_file($cfgFile)) return false;
        $cfg = require $cfgFile;
        $host = $cfg['Core'][0]['HOST'] ?? $cfg['host'] ?? '';
        $user = $cfg['Core'][0]['USER'] ?? $cfg['username'] ?? '';
        $pass = $cfg['Core'][0]['PASS'] ?? $cfg['password'] ?? '';
        $dbName = $cfg['Core'][0]['DB'][0]['DB'] ?? $cfg['database'] ?? '';
        $prefix = $cfg['Core'][0]['DB'][0]['Prefix'][0]['table'] ?? $cfg['prefix'] ?? 'lvl_';
        if (!$host || !$dbName) return false;

        try {
            $dsn = "mysql:host=$host;dbname=$dbName;charset=utf8mb4";
            // connect_timeout работает на уровне MySQL драйвера
            $dsn .= ";connect_timeout=$connectTimeoutSeconds";

            $pdo = new PDO(
                $dsn,
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => $queryTimeoutSeconds,
                ]
            );
        } catch (Exception $e) {
            error_log('DB connect failed (admin): ' . $e->getMessage());
            $_SESSION['admin_check'] = ['sid' => $steamId64, 'ts' => time(), 'is_admin' => false];
            return false;
        }
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT id FROM {$prefix}web_admins WHERE steam_id64 = :sid LIMIT 1"
        );
        $stmt->execute([':sid' => $steamId64]);
        $isAdmin = (bool)$stmt->fetch();
        $_SESSION['admin_check'] = ['sid' => $steamId64, 'ts' => time(), 'is_admin' => $isAdmin];
        return $isAdmin;
    } catch (Exception $e) {
        error_log('Admin query failed: ' . $e->getMessage());
        $_SESSION['admin_check'] = ['sid' => $steamId64, 'ts' => time(), 'is_admin' => false];
        return false;
    }
}


// ============================================================================
// 🔁 SteamID Конвертеры (добавлено)
// ============================================================================

/**
 * Конвертирует SteamID64 → SteamID2 (STEAM_X:Y:Z)
 */
function steam64_to_steam2($steam64): string|false
{
    $steam64 = (string)$steam64;
    if (!preg_match('/^7656119\d{10}$/', $steam64)) {
        return false;
    }
    $base = 76561197960265728;
    $accountId = (int)$steam64 - $base;
    if ($accountId < 0) return false;
    $authServer = $accountId % 2;
    $authId = intdiv($accountId, 2);
    return "STEAM_1:{$authServer}:{$authId}";
}

/**
 * Конвертирует SteamID2 → SteamID64
 */
function steam2_to_steam64(string $steam2): string|false
{
    if (!preg_match('/^STEAM_\d+:(\d):(\d+)$/', $steam2, $matches)) {
        return false;
    }
    $authServer = (int)$matches[1];
    $authId = (int)$matches[2];
    $accountId = $authServer + ($authId * 2);
    return (string)(76561197960265728 + $accountId);
}

/**
 * Конвертирует SteamID64 → SteamID3 ([U:1:XXXXXX])
 */
function steam64_to_steam3(string $steam64): string|false
{
    $steam64 = (string)$steam64;
    if (!preg_match('/^7656119\d{10}$/', $steam64)) return false;
    $accountId = (int)$steam64 - 76561197960265728;
    return "[U:1:{$accountId}]";
}

/**
 * Универсальный конвертер: любой формат → любой формат
 */
function convert_steamid(string $input, string $targetFormat = 'steam2'): string|false
{
    $input = trim($input);

    // Определяем входной формат
    if (preg_match('/^7656119\d{10}$/', $input)) {
        $currentFormat = 'steam64';
    } elseif (preg_match('/^STEAM_\d+:\d:\d+$/', $input)) {
        $currentFormat = 'steam2';
    } elseif (preg_match('/^\[U:1:\d+\]$/', $input)) {
        $currentFormat = 'steam3';
    } else {
        return false;
    }

    if ($currentFormat === $targetFormat) {
        return $input;
    }

    switch ($targetFormat) {
        case 'steam2':
            if ($currentFormat === 'steam64') return steam64_to_steam2($input);
            if ($currentFormat === 'steam3') {
                $accountId = (int)preg_replace('/\[U:1:(\d+)\]/', '$1', $input);
                $steam64 = (string)(76561197960265728 + $accountId);
                return steam64_to_steam2($steam64);
            }
            break;
        case 'steam64':
            if ($currentFormat === 'steam2') return steam2_to_steam64($input);
            if ($currentFormat === 'steam3') {
                $accountId = (int)preg_replace('/\[U:1:(\d+)\]/', '$1', $input);
                return (string)(76561197960265728 + $accountId);
            }
            break;
        case 'steam3':
            if ($currentFormat === 'steam64') return steam64_to_steam3($input);
            if ($currentFormat === 'steam2') {
                $steam64 = steam2_to_steam64($input);
                return $steam64 ? steam64_to_steam3($steam64) : false;
            }
            break;
    }
    return false;
}

/**
 * Проверка: валидный ли SteamID в любом формате
 */
function is_valid_steamid(string $input): bool
{
    $input = trim($input);
    return preg_match('/^7656119\d{10}$/', $input) ||
        preg_match('/^STEAM_\d+:\d:\d+$/', $input) ||
        preg_match('/^\[U:1:\d+\]$/', $input);
}

/**
 * Получить аватар Steam по любому формату SteamID
 */
function get_steam_avatar(string $steamid, string $size = 'full'): string|false
{
    $steam64 = is_valid_steamid($steamid) ? convert_steamid($steamid, 'steam64') : false;
    if (!$steam64) return false;
    $sizes = ['small' => '', 'medium' => '_medium', 'full' => '_full'];
    $suffix = $sizes[$size] ?? '_full';
    return "https://avatars.steamstatic.com/{$steam64}{$suffix}.jpg";
}
