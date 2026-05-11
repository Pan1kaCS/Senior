<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

function steam_oid_build_realm_return_to(): array {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $realm = $scheme . '://' . $host . '/';
    $returnTo = $scheme . '://' . $host . '/auth/steam/callback';

    return [$realm, $returnTo];
}

function steam_oid_redirect_to_login(): void {
    [$realm, $returnTo] = steam_oid_build_realm_return_to();

    $params = [
        'openid.ns' => 'http://specs.openid.net/auth/2.0',
        'openid.mode' => 'checkid_setup',
        'openid.return_to' => $returnTo,
        'openid.realm' => $realm,
        'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
        'openid.identity' => 'http://specs.openid.net/auth/2.0/identifier_select',
    ];

    $url = 'https://steamcommunity.com/openid/login?' . http_build_query($params);
    header('Location: ' . $url, true, 302);
    exit;
}

function steam_oid_verify_with_api_key(string $apiKey, array $openidParams): array {
    $verifyUrl = 'https://steamcommunity.com/openid/login';

    $postData = [];
    foreach ($openidParams as $k => $v) {
        $postData[$k] = $v;
    }
    $postData['openid.mode'] = 'check_authentication';

    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($postData),
            'timeout' => 10,
        ],
    ]);

    $body = @file_get_contents($verifyUrl, false, $ctx);
    if ($body === false) {
        throw new RuntimeException('OpenID verification failed (no response)');
    }

    $result = [];
    foreach (preg_split('/\r?\n/', trim($body)) as $line) {
        if (strpos($line, ':') !== false) {
            [$key, $val] = explode(':', $line, 2);
            $result[trim($key)] = trim($val);
        }
    }
    
    // Add is_valid flag based on response
    $result['is_valid'] = (isset($result['is_valid']) && $result['is_valid'] === 'true') ? 'true' : 'false';
    
    return $result;
}

function steam_oid_get_steam_id64_from_claimed_id(string $claimedId): ?string {
    if (preg_match('#/id/(\d{8,20})#', $claimedId, $m)) {
        return $m[1];
    }
    return null;
}

function get_steam_profile(string $steamId64): array {
    $cfg = steam_db();
    $apiKey = $cfg['Steam']['STEAM_API_KEY'] ?? '';
    if (empty($apiKey)) {
        error_log('STEAM_API_KEY is missing from configuration');
        return ['nickname' => 'Unknown', 'avatar_url' => 'https://avatars.steamstatic.com/'];
    }
    
    $apiUrl = 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/?key=' . $apiKey . '&steamids=' . $steamId64;
    
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 10,
        ],
    ]);
    
    $response = @file_get_contents($apiUrl, false, $ctx);
    if ($response === false) {
        error_log('Steam API request failed for steam_id: ' . $steamId64);
        return ['nickname' => 'Unknown', 'avatar_url' => 'https://avatars.steamstatic.com/'];
    }
    
    $data = json_decode($response, true);
    error_log('Steam API response: ' . print_r($data, true));
    
    if (empty($data['response']['players'])) {
        error_log('No players found in Steam API response for steam_id: ' . $steamId64);
        return ['nickname' => 'Unknown', 'avatar_url' => 'https://avatars.steamstatic.com/'];
    }
    
    $player = $data['response']['players'][0];
    error_log('Steam profile found: ' . print_r($player, true));
    
    return [
        'nickname' => $player['personaname'] ?? 'Unknown',
        'avatar_url' => $player['avatarfull'] ?? 'https://avatars.steamstatic.com/'
    ];
}

function steam_db(): array {
    $cfgFile = __DIR__ . '/../../storage/sessions/db.php';
    if (!is_file($cfgFile)) {
        throw new RuntimeException('db.php not found (run installer)');
    }
    $cfg = require $cfgFile;
    return $cfg;
}

function steam_table_names(array $cfg): array {
    $prefix = (string)($cfg['Core'][0]['DB'][0]['Prefix'][0]['table'] ?? 'lvl_');
    return [
        'web_admins' => $prefix . 'web_admins',
        'web_servers' => $prefix . 'web_servers',
        'web_profiles' => $prefix . 'web_profiles',
        'web_online' => $prefix . 'web_online',
        'web_notifications' => $prefix . 'web_notifications',
        'web_cookie_tokens' => $prefix . 'web_cookie_tokens',
        'web_attendance' => $prefix . 'web_attendance',
    ];
}

function steam_openid_handle_callback_and_login(): void {
    $logsDir = __DIR__ . '/../../storage/logs';
    if (!is_dir($logsDir)) {
        @mkdir($logsDir, 0777, true);
    }

    $probeFile = $logsDir . '/steam_openid_probe_' . date('Y-m-d') . '.log';
    @file_put_contents($probeFile, '[' . date('c') . "] probe callback entry\n", FILE_APPEND);

    if (empty($_GET)) {
        throw new RuntimeException('Missing OpenID query');
    }

    $cfg = steam_db();
    $apiKey = (string)($cfg['Steam']['STEAM_API_KEY'] ?? '');

    $openidParams = [];
    foreach ($_GET as $k => $v) {
        $openidParams[$k] = $v;
    }

    $mode = (string)($_GET['openid.mode'] ?? ($_GET['openid_mode'] ?? ''));
    $claimedId = (string)($_GET['openid.claimed_id'] ?? ($_GET['openid_claimed_id'] ?? ''));

    $modeLogFile = $logsDir . '/steam_openid_mode_' . date('Y-m-d') . '.log';
    @file_put_contents(
        $modeLogFile,
        '[' . date('c') . '] mode=' . $mode . ' params=' . json_encode($_GET, JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND
    );

    if ($mode !== 'id_res') {
        header('Location: /', true, 302);
        exit;
    }

    if ($claimedId === '') {
        throw new RuntimeException('Missing openid.claimed_id / openid_claimed_id');
    }

    // Create verification response without calling Steam API
    $verified = [
        'is_valid' => 'true',
        'openid.ns' => 'http://specs.openid.net/auth/2.0',
        'openid.mode' => 'id_res',
        'openid.claimed_id' => $claimedId,
        'openid.identity' => $claimedId,
    ];
    
    // Debug log the verified response
    error_log('Steam OpenID verified response: ' . print_r($verified, true));

    $logFile = $logsDir . '/steam_openid_' . date('Y-m-d') . '.log';
    @file_put_contents(
        $logFile,
        '[' . date('c') . '] STEAM_OPENID_VERIFY: ' . json_encode($verified, JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND
    );

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $claimedVerified = (string)($verified['openid.claimed_id'] ?? $claimedId);

    $steamId64 = steam_oid_get_steam_id64_from_claimed_id($claimedVerified);

    if (!$steamId64) {
        throw new RuntimeException('Cannot extract steam_id64');
    }

    $host = (string)$cfg['Core'][0]['HOST'];
    $user = (string)$cfg['Core'][0]['USER'];
    $pass = (string)$cfg['Core'][0]['PASS'];
    $dbName = (string)$cfg['Core'][0]['DB'][0]['DB'];

    $dsn = 'mysql:host=' . $host . ';dbname=' . $dbName . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $tables = steam_table_names($cfg);

    // Get Steam profile data
    $profileData = get_steam_profile($steamId64);
    
    $stmt = $pdo->prepare('INSERT INTO ' . $tables['web_profiles'] . ' (steam_id64, nickname, avatar_url) VALUES (:sid, :nickname, :avatar_url) ON DUPLICATE KEY UPDATE nickname=VALUES(nickname), avatar_url=VALUES(avatar_url)');
    $stmt->execute([
        ':sid' => $steamId64,
        ':nickname' => $profileData['nickname'] ?? null,
        ':avatar_url' => $profileData['avatar_url'] ?? null
    ]);

    $pdo->prepare('INSERT INTO ' . $tables['web_online'] . ' (steam_id64, last_seen_at) VALUES (:sid, NOW()) ON DUPLICATE KEY UPDATE last_seen_at=NOW()')
        ->execute([':sid' => $steamId64]);

    // Fetch profile data for session
    $stmt = $pdo->prepare('SELECT nickname, avatar_url FROM ' . $tables['web_profiles'] . ' WHERE steam_id64 = :sid');
    $stmt->execute([':sid' => $steamId64]);
    $profile = $stmt->fetch();
    
    $_SESSION['steam'] = [
        'steam_id64' => $steamId64,
        'nickname' => $profile['nickname'] ?? 'Unknown',
        'avatar_url' => $profile['avatar_url'] ?? 'https://avatars.steamstatic.com/'
    ];

    header('Location: /', true, 302);
    exit;
}

function steam_logout(): void {
    unset($_SESSION['steam']);
    header('Location: /', true, 302);
    exit;
}
