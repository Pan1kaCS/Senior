<?php
/** @var Graphics $graphics */
require_once __DIR__ . '/../../includes/functions.php';
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
    <div class="user-dropdown" id="userDropdown">
        <button class="user-profile-btn" type="button" aria-haspopup="true" aria-expanded="false">
            <img src="<?= h($_SESSION['steam']['avatar_url'] ?? 'https://avatars.steamstatic.com/') ?>" alt="Avatar" class="avatar">
            <span class="nickname"><?= h($_SESSION['steam']['nickname'] ?? 'Unknown') ?></span>
            <svg class="dropdown-arrow" stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 256 256" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg"><path d="M216.49,104.49l-80,80a12,12,0,0,1-17,0l-80-80a12,12,0,0,1,17-17L128,159l71.51-71.52a12,12,0,0,1,17,17Z"></path></svg>
        </button>
        <div class="dropdown-menu">
            <?php
            $showAdminLink = false;
            if (function_exists('is_admin_user')) {
                $showAdminLink = is_admin_user();
            } else {
                $cfgFile = __DIR__ . '/../../../../storage/sessions/db.php';
                if (is_file($cfgFile) && !empty($_SESSION['steam']['steam_id64'])) {
                    try {
                        $cfg = require $cfgFile;
                        $host = (string)($cfg['Core'][0]['HOST'] ?? '');
                        $user = (string)($cfg['Core'][0]['USER'] ?? '');
                        $pass = (string)($cfg['Core'][0]['PASS'] ?? '');
                        $dbName = (string)($cfg['Core'][0]['DB'][0]['DB'] ?? '');
                        $prefix = (string)($cfg['Core'][0]['DB'][0]['Prefix'][0]['table'] ?? 'lvl_');
                        if ($host && $dbName) {
                            $pdo = new PDO(
                                'mysql:host=' . $host . ';dbname=' . $dbName . ';charset=utf8mb4',
                                $user, $pass,
                                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                            );
                            $stmt = $pdo->prepare('SELECT id FROM ' . $prefix . 'web_admins WHERE steam_id64 = :sid LIMIT 1');
                            $stmt->execute([':sid' => $_SESSION['steam']['steam_id64']]);
                            $showAdminLink = (bool)$stmt->fetch();
                        }
                    } catch (Exception $e) {
                        error_log('Admin check fallback failed: ' . $e->getMessage());
                    }
                }
            }
            if ($showAdminLink):
            ?>
            <a href="/admin" class="dropdown-item">
                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 16 16" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg"><path d="M8.5 1.5A1.5 1.5 0 0 1 10 0h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h6c-.314.418-.5.937-.5 1.5v6h-6a.5.5 0 0 0 0 1h7.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5z"/></svg>
                Админ панель
            </a>
            <a href="/profiles" class="dropdown-item">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1 1H3a1 1 0 0 1-1-1c0-1 1-4 6-4 5 0 6 3 6 4 0 1-1 1-1 1z"/>
                </svg>
                Профили
            </a>
            <?php endif; ?>
            <form method="post" action="/auth/logout">
                <button type="submit" class="dropdown-item logout-btn">Выйти</button>
            </form>
        </div>
    </div>
    <?php else: ?>
    <a class="steam-btn" href="/auth/steam" aria-label="Войти через Steam">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M568 320C568 457 456.8 568 319.6 568C205.8 568 110 491.7 80.6 387.6L175.8 426.9C182.2 459 210.7 483.3 244.7 483.3C283.9 483.3 316.6 450.9 314.9 409.8L399.4 349.6C451.5 350.9 495.2 308.7 495.2 256.1C495.2 204.5 453.2 162.6 401.5 162.6C349.8 162.6 307.8 204.6 307.8 256.1L307.8 257.3L248.6 343C233.1 342.1 217.9 346.4 205.1 355.1L72 300.1C82.2 172.4 189.1 72 319.6 72C456.8 72 568 183 568 320zM227.7 448.3L197.2 435.7C202.8 447.3 212.5 456.5 224.4 461.5C251.3 472.7 282.2 459.9 293.4 433.1C298.8 420.1 298.9 405.8 293.5 392.8C288.1 379.8 278 369.6 265 364.2C252.1 358.8 238.3 359 226.1 363.6L257.6 376.6C277.4 384.8 286.8 407.5 278.5 427.3C270.2 447.2 247.5 456.5 227.7 448.3zM401.5 193.8C435.9 193.8 463.8 221.7 463.8 256.1C463.8 290.5 435.9 318.4 401.5 318.4C367.1 318.4 339.2 290.5 339.2 256.1C339.2 221.7 367.1 193.8 401.5 193.8zM401.6 302.8C427.4 302.8 448.4 281.8 448.4 256C448.4 230.2 427.4 209.2 401.6 209.2C375.8 209.2 354.8 230.2 354.8 256 401.5 193.8z"/></svg>
        Войти через Steam
    </a>
    <?php endif; ?>
</header>

<style>
/* Стили для топбара */
.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 24px;
    background: #0a0a0a;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.logo {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    color: white;
    font-weight: bold;
    font-size: 20px;
}

.logo-icon {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

/* Dropdown стили */
.user-dropdown { 
    position: relative; 
}

.user-profile-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 6px 12px;
    border-radius: 30px;
    transition: background 0.2s;
    color: white;
}

.user-profile-btn:hover {
    background: rgba(255,255,255,0.1);
}

.avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
}

.nickname {
    color: white;
    font-weight: 500;
}

.dropdown-arrow {
    transition: transform 0.2s;
}

.user-dropdown.open .dropdown-arrow {
    transform: rotate(180deg);
}

/* Dropdown menu - исправлено! */
.user-dropdown .dropdown-menu {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    z-index: 1000;
    background: #1a1a1a;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    padding: 8px 0;
    min-width: 180px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.15s ease;
}

.user-dropdown.open .dropdown-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 16px;
    color: #e0e0e0;
    text-decoration: none;
    transition: background 0.2s;
    width: 100%;
    text-align: left;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
    font-family: inherit;
}

.dropdown-item:hover {
    background: rgba(255,255,255,0.1);
    color: white;
}

.logout-btn {
    color: #ff6b6b;
}

.logout-btn:hover {
    background: rgba(255,107,107,0.1);
}

.steam-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #1b2838;
    color: white;
    text-decoration: none;
    padding: 8px 16px;
    border-radius: 4px;
    transition: background 0.2s;
}

.steam-btn svg {
    width: 20px;
    height: 20px;
    fill: white;
}

.steam-btn:hover {
    background: #2c3e50;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropdown = document.getElementById('userDropdown');
    if (!dropdown) return;
    
    const btn = dropdown.querySelector('.user-profile-btn');
    const menu = dropdown.querySelector('.dropdown-menu');
    
    if (!btn || !menu) return;

    function openDropdown() {
        dropdown.classList.add('open');
        btn.setAttribute('aria-expanded', 'true');
    }

    function closeDropdown() {
        dropdown.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
    }

    function toggleDropdown(e) {
        e.preventDefault();
        e.stopPropagation();
        if (dropdown.classList.contains('open')) {
            closeDropdown();
        } else {
            openDropdown();
        }
    }

    btn.addEventListener('click', toggleDropdown);

    // Закрытие при клике вне дропдауна
    document.addEventListener('click', function(e) {
        if (!dropdown.contains(e.target)) {
            closeDropdown();
        }
    });

    // Закрытие по Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && dropdown.classList.contains('open')) {
            closeDropdown();
        }
    });
});
</script>