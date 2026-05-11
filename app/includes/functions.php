<?php
declare(strict_types=1);

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function get_theme(): string {
    // Change theme only by one file; kernel stays the same.
    // For now only "blackwhite" is shipped.
    $allowed = ['blackwhite'];
    $t = $_GET['theme'] ?? ($_SESSION['theme'] ?? 'blackwhite');
    if (!in_array($t, $allowed, true)) {
        $t = 'blackwhite';
    }
    $_SESSION['theme'] = $t;
    return $t;
}

function rate_limit(string $key, int $max, int $perSeconds): void {
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
 * @return bool
 */
function is_admin_user(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // ✅ Проверяем ВСЕ возможные варианты имени сессии
    $steamId64 = $_SESSION['steam']['steam_id64'] 
              ?? $_SESSION['user_admin'] 
              ?? $_SESSION['steamid'] 
              ?? '';
    
    if (empty($steamId64)) {
        return false;
    }
    
    global $pdo, $prefix;
    
    // Подключение к БД если ещё не подключены
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $cfgFile = __DIR__ . '/../../storage/sessions/db.php';
        if (!is_file($cfgFile)) return false;
        
        $cfg = require $cfgFile;
        
        // Поддержка разных форматов конфига
        $host = $cfg['Core'][0]['HOST'] ?? $cfg['host'] ?? '';
        $user = $cfg['Core'][0]['USER'] ?? $cfg['username'] ?? '';
        $pass = $cfg['Core'][0]['PASS'] ?? $cfg['password'] ?? '';
        $dbName = $cfg['Core'][0]['DB'][0]['DB'] ?? $cfg['database'] ?? '';
        $prefix = $cfg['Core'][0]['DB'][0]['Prefix'][0]['table'] ?? $cfg['prefix'] ?? 'lvl_';
        
        if (!$host || !$dbName) return false;
        
        try {
            $pdo = new PDO(
                "mysql:host=$host;dbname=$dbName;charset=utf8mb4",
                $user, $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (Exception $e) {
            error_log('DB connect failed: ' . $e->getMessage());
            return false;
        }
    }
    
    try {
        // ✅ Запрос БЕЗ "active = 1" — этой колонки нет в вашей таблице
        $stmt = $pdo->prepare(
            "SELECT id FROM {$prefix}web_admins WHERE steam_id64 = :sid LIMIT 1"
        );
        $stmt->execute([':sid' => $steamId64]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        error_log('Admin query failed: ' . $e->getMessage());
        return false;
    }
}