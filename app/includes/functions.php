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

