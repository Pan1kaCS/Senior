<?php
/** @var Graphics $graphics */
require_once __DIR__ . '/../../includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<header class="topbar">
    <a href="/" class="logo">
        <div class="logo-icon">S</div>
        <span>SVOYSKIY</span>
    </a>

    <?php if (isset($_SESSION['steam']) && $_SESSION['steam']['steam_id64']): ?>
        <div class="user-dropdown">
            <button class="user-profile-btn">
                <img src="<?= h($_SESSION['steam']['avatar_url'] ?? 'https://avatars.steamstatic.com/') ?>" alt="Avatar" class="avatar">
                <span class="nickname"><?= h($_SESSION['steam']['nickname'] ?? 'Unknown') ?></span>
                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 256 256" class="Header_profile_arrow__1kSx1 text-gg-text-0" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg"><path d="M216.49,104.49l-80,80a12,12,0,0,1-17,0l-80-80a12,12,0,0,1,17-17L128,159l71.51-71.52a12,12,0,0,1,17,17Z"></path></svg>
            </button>
            <div class="dropdown-menu">
                <?php
// Check admin access
$cfgFile = __DIR__ . '/../../../../storage/sessions/db.php';
if (is_file($cfgFile)) {
    $cfg = require $cfgFile;
    $host = (string)$cfg['Core'][0]['HOST'];
    $user = (string)$cfg['Core'][0]['USER'];
    $pass = (string)$cfg['Core'][0]['PASS'];
    $dbName = (string)$cfg['Core'][0]['DB'][0]['DB'];
    $prefix = (string)($cfg['Core'][0]['DB'][0]['Prefix'][0]['table'] ?? 'lvl_');

    try {
        $dsn = 'mysql:host=' . $host . ';dbname=' . $dbName . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $steamId64 = $_SESSION['steam']['steam_id64'] ?? '';
        if ($steamId64) {
            $stmt = $pdo->prepare('SELECT id FROM ' . $prefix . 'web_admins WHERE steam_id64 = :steam_id64');
            $stmt->execute([':steam_id64' => $steamId64]);
            if ($stmt->fetch()) {
                echo '<a href="/admin" class="dropdown-item">Админ панель</a>';
            }
        }
    } catch (Exception $e) {
        error_log('Admin check failed: ' . $e->getMessage());
    }
}
?>
                <form method="post" action="/auth/logout">
                    <button type="submit" class="dropdown-item logout-btn">Выйти</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <a class="steam-btn" href="/auth/steam" aria-label="Войти через Steam">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M568 320C568 457 456.8 568 319.6 568C205.8 568 110 491.7 80.6 387.6L175.8 426.9C182.2 459 210.7 483.3 244.7 483.3C283.9 483.3 316.6 450.9 314.9 409.8L399.4 349.6C451.5 350.9 495.2 308.7 495.2 256.1C495.2 204.5 453.2 162.6 401.5 162.6C349.8 162.6 307.8 204.6 307.8 256.1L307.8 257.3L248.6 343C233.1 342.1 217.9 346.4 205.1 355.1L72 300.1C82.2 172.4 189.1 72 319.6 72C456.8 72 568 183 568 320zM227.7 448.3L197.2 435.7C202.8 447.3 212.5 456.5 224.4 461.5C251.3 472.7 282.2 459.9 293.4 433.1C298.8 420.1 298.9 405.8 293.5 392.8C288.1 379.8 278 369.6 265 364.2C252.1 358.8 238.3 359 226.1 363.6L257.6 376.6C277.4 384.8 286.8 407.5 278.5 427.3C270.2 447.2 247.5 456.5 227.7 448.3zM401.5 193.8C435.9 193.8 463.8 221.7 463.8 256.1C463.8 290.5 435.9 318.4 401.5 318.4C367.1 318.4 339.2 290.5 339.2 256.1C339.2 221.7 367.1 193.8 401.5 193.8zM401.6 302.8C427.4 302.8 448.4 281.8 448.4 256C448.4 230.2 427.4 209.2 401.6 209.2C375.8 209.2 354.8 230.2 354.8 256 401.5 193.8z"/></svg>
            Войти через Steam
        </a>
    <?php endif; ?>
</header>
