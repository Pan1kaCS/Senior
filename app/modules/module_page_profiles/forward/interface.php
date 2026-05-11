<?php
// Profiles module - display player statistics from LevelsRanks
// Access restricted to profile owner via Steam session

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// === Steam Auth Check ===
if (!isset($_SESSION['steamid']) || empty($_SESSION['steamid'])) {
    http_response_code(403);
    die('<div style="text-align:center;padding:50px;color:var(--text-primary);">
        <h2>🔐 Требуется авторизация</h2>
        <p>Войдите через Steam, чтобы просмотреть профиль.</p>
        <a href="/auth/steam" style="display:inline-block;margin-top:20px;padding:12px 30px;
            background:var(--primary);color:white;text-decoration:none;border-radius:8px;">
            Войти через Steam
        </a>
    </div>');
}

// Get database configuration
$cfgFile = __DIR__ . '/../../../../storage/sessions/db.php';
if (!is_file($cfgFile)) {
    die('Configuration file not found');
}
$cfg = require $cfgFile;

// Connect to database
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
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// === Get steamid from route (path param) ===
$requestedSteamId = trim($_GET['steamid'] ?? $_REQUEST['steamid'] ?? '');
if (!$requestedSteamId || !preg_match('/^7656119\d{10}$/', $requestedSteamId)) {
    die('<div style="text-align:center;padding:50px;color:var(--text-primary);">
        <h2>❌ Некорректный SteamID</h2>
        <a href="/" style="color:var(--primary);">← На главную</a>
    </div>');
}

// === Security: Only allow viewing own profile ===
$userSteamId = (string)$_SESSION['steamid'];
if ($requestedSteamId !== $userSteamId) {
    http_response_code(403);
    die('<div style="text-align:center;padding:50px;color:var(--text-primary);">
        <h2>🔒 Доступ запрещён</h2>
        <p>Вы можете просматривать только свой профиль.</p>
        <a href="/profiles/' . urlencode($userSteamId) . '/" 
           style="display:inline-block;margin-top:20px;padding:12px 30px;
           background:var(--primary);color:white;text-decoration:none;border-radius:8px;">
            Перейти в мой профиль
        </a>
    </div>');
}

// === Load profile data ===
$profile = null;
$error = null;

try {
    $stmt = $pdo->prepare("SELECT * FROM {$prefix}base WHERE steam = ?");
    $stmt->execute([$requestedSteamId]);
    $profile = $stmt->fetch();
    
    if (!$profile) {
        $error = 'Профиль не найден в базе данных.';
    } else {
        // GeoIP data
        $stmt = $pdo->prepare("SELECT * FROM {$prefix}base_geoip WHERE steam = ?");
        $stmt->execute([$profile['steam']]);
        $geoip = $stmt->fetch();
        if ($geoip) {
            $profile['country_code'] = $geoip['country_code'] ?? 'ru';
            $profile['country'] = $geoip['country'] ?? 'Unknown';
            $profile['region'] = $geoip['region'] ?? '';
            $profile['city'] = $geoip['city'] ?? '';
        }
        
        // Calculated stats
        $profile['accuracy'] = $profile['shoots'] > 0 ? round(($profile['hits'] / $profile['shoots']) * 100, 1) : 0;
        $profile['kdr'] = $profile['deaths'] > 0 ? round($profile['kills'] / $profile['deaths'], 2) : (float)$profile['kills'];
        $profile['hsp'] = $profile['kills'] > 0 ? round(($profile['headshots'] / $profile['kills']) * 100, 1) : 0;
        $profile['playtime_hours'] = round($profile['playtime'] / 3600, 1);
        $profile['last_seen'] = $profile['lastconnect'] > 0 ? date('Y-m-d H:i:s', $profile['lastconnect']) : 'Никогда';
        
        // XP/Rank calculation for progress bar
        $currentRank = (int)$profile['rank'];
        $currentXP = (int)$profile['value'];
        $nextRankXP = $currentRank * 5000 + 1000; // Примерная формула: 6000, 11000, 16000...
        $xpPercent = min(100, round(($currentXP / $nextRankXP) * 100, 2));
        $profile['xp_percent'] = $xpPercent;
        $profile['next_rank_xp'] = $nextRankXP;
    }
} catch (Exception $e) {
    $error = 'Ошибка загрузки: ' . $e->getMessage();
}

// Steam avatar URL
$avatarUrl = "https://avatars.steamstatic.com/{$requestedSteamId}_full.jpg";
$profileName = htmlspecialchars($profile['name'] ?? 'Unknown');
$lastSeenText = $profile ? 
    ($profile['lastconnect'] > time() - 300 ? '🟢 Онлайн' : 'Был(а) в игре - ' . human_time_diff($profile['lastconnect'])) : 
    '';

function human_time_diff($timestamp) {
    $diff = time() - $timestamp;
    if ($diff < 60) return 'только что';
    if ($diff < 3600) return floor($diff/60) . ' мин. назад';
    if ($diff < 86400) return floor($diff/3600) . ' ч. назад';
    if ($diff < 2592000) return floor($diff/86400) . ' дн. назад';
    return 'более месяца назад';
}
?>

<!-- === Modern Profile UI (CyberShoke Style) === -->
<style>
:root {
    --primary: #f0b358;
    --primary-hover: #d49a42;
    --bg-dark: #1a1a2e;
    --bg-card: rgba(30, 30, 50, 0.6);
    --border: rgba(255,255,255,0.1);
    --text-primary: #ffffff;
    --text-secondary: rgba(255,255,255,0.7);
}
#profile-cover {
    height: 200px;
    background: linear-gradient(135deg, #2a2a4a 0%, #1a1a2e 100%);
    border-radius: 12px 12px 0 0;
    position: relative;
    overflow: hidden;
}
#profile-cover::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 60px;
    background: linear-gradient(transparent, var(--bg-card));
}
#profile-main {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 20px;
    padding: 20px;
    margin-top: -50px;
    position: relative;
    z-index: 2;
}
.avatar {
    width: 120px; height: 120px;
    border-radius: 50%;
    border: 4px solid var(--bg-dark);
    background: var(--bg-card);
    object-fit: cover;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
#name_player {
    font-size: 24px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 5px 0;
}
#online_player {
    color: var(--text-secondary);
    font-size: 14px;
}
.xp__wrapper {
    margin: 15px 0;
}
.xp-bar {
    height: 8px;
    background: rgba(255,255,255,0.1);
    border-radius: 4px;
    overflow: hidden;
    position: relative;
}
.loyalty__progressBar--3fut {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), #ffb347);
    border-radius: 4px;
    transition: width 0.3s ease;
}
.xp-bar-info {
    display: flex;
    gap: 8px;
    font-size: 13px;
    color: var(--text-secondary);
    margin-top: 6px;
}
.xp-bar-info-1, .xp-bar-info-2 {
    color: var(--text-primary);
    font-weight: 600;
}
.profile-socials {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}
.profile-socials a {
    width: 36px; height: 36px;
    border-radius: 8px;
    background: rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}
.profile-socials a:hover {
    background: rgba(240, 179, 88, 0.2);
}
.profile-socials img {
    width: 20px; height: 20px;
}
#profile-nav {
    display: flex;
    gap: 5px;
    padding: 0 20px;
    border-bottom: 1px solid var(--border);
    overflow-x: auto;
}
#profile-nav a {
    padding: 12px 20px;
    color: var(--text-secondary);
    text-decoration: none;
    font-weight: 500;
    font-size: 14px;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
    white-space: nowrap;
}
#profile-nav a.active, #profile-nav a:hover {
    color: var(--primary);
    border-bottom-color: var(--primary);
}
#profile-content {
    padding: 20px;
}
.profile-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 15px;
}
.profile-stats-grid div {
    background: var(--bg-card);
    padding: 15px;
    border-radius: 10px;
    text-align: center;
    border: 1px solid var(--border);
}
.profile-stats-grid strong {
    display: block;
    font-size: 20px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 5px;
}
.profile-stats-grid p {
    margin: 0;
    font-size: 13px;
    color: var(--text-secondary);
}
.premium-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: linear-gradient(135deg, #f0b358, #d49a42);
    color: #1a1a2e;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s;
}
.premium-btn:hover {
    transform: translateY(-2px);
}
.rank-badge img {
    height: 32px;
    vertical-align: middle;
}
</style>

<div id="routerpages">
<div id="profile" class="page_profile_new" style="max-width: 1000px; margin: 0 auto; background: var(--bg-card); border-radius: 12px; overflow: hidden;">

    <!-- Cover -->
    <div id="profile-cover"></div>
    
    <!-- Main Info -->
    <div id="profile-main">
        <div id="information">
            <img class="avatar" src="<?= $avatarUrl ?>" alt="<?= $profileName ?>" onerror="this.src='https://avatars.steamstatic.com/default_full.jpg'">
        </div>
        <div>
            <div id="name_player"><?= $profileName ?></div>
            <div id="online_player"><?= $lastSeenText ?></div>
            
            <!-- XP Bar -->
            <?php if ($profile): ?>
            <span class="xp__wrapper">
                <div class="xp-bar">
                    <div class="loyalty__progressBar--3fut" id="profile_user_width_xp" style="width: <?= $profile['xp_percent'] ?>%;"></div>
                </div>
                <div class="xp-bar-info">
                    <span class="xp-bar-info-1" id="profile_user_value_new"><?= number_format($profile['value']) ?></span>
                    <span class="xp-bar-info-3">&nbsp;/&nbsp;</span>
                    <span class="xp-bar-info-2" id="profile_user_max_xp">
                        <?= number_format($profile['next_rank_xp']) ?> XP 
                        <span>(Уровень <?= $profile['rank'] ?>)</span>
                    </span>
                </div>
            </span>
            <?php endif; ?>
            
            <!-- Socials -->
            <div class="profile-socials-wrapper">
                <div class="profile-socials">
                    <a target="_blank" href="https://steamcommunity.com/profiles/<?= $requestedSteamId ?>">
                        <img src="https://cloud.cybershoke.net/img/socials/profile-icons/steam.svg" alt="Steam" title="Steam">
                    </a>
                    <!-- Добавьте другие соцсети при необходимости -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Navigation -->
    <nav id="profile-nav">
        <a href="/profiles/<?= $requestedSteamId ?>/" class="active">Профиль</a>
        <a href="/profiles/<?= $requestedSteamId ?>/friends">🔒 Друзья</a>
        <a href="/profiles/<?= $requestedSteamId ?>/inventory">Инвентарь</a>
        <a href="/profiles/<?= $requestedSteamId ?>/achievements">Достижения</a>
        <a href="/profiles/<?= $requestedSteamId ?>/settings">Настройки</a>
    </nav>
    
    <!-- Content -->
    <div id="profile-content">
        <?php if ($error): ?>
            <div style="padding: 20px; color: #ff6b6b; text-align: center;">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
        <?php elseif ($profile): ?>
        <div class="profile-content-block" id="profile-stats">
            <div class="profile-stats-grid">
                <div>
                    <strong id="p-profile-top">#<?= $profile['rank'] ?></strong>
                    <p>Место</p>
                </div>
                <div>
                    <strong id="p-profile-country">
                        <img src="/assets/flags/<?= strtolower($profile['country_code'] ?? 'ru') ?>.png" 
                             alt="<?= htmlspecialchars($profile['country'] ?? '') ?>" 
                             style="width:24px;height:16px;border-radius:3px;vertical-align:middle;">
                    </strong>
                    <p>Страна</p>
                </div>
                <div>
                    <strong id="p-profile-kd"><?= $profile['kdr'] ?></strong>
                    <p>K/D</p>
                </div>
                <div>
                    <strong id="p-profile-kills"><?= number_format($profile['kills']) ?></strong>
                    <p>Убийства</p>
                </div>
                <div>
                    <strong id="p-profile-hs"><?= $profile['hsp'] ?>%</strong>
                    <p>В голову</p>
                </div>
                <div>
                    <strong id="p-profile-accuracy"><?= $profile['accuracy'] ?>%</strong>
                    <p>Точность</p>
                </div>
                <div>
                    <strong id="p-profile-hours"><?= $profile['playtime_hours'] ?>ч</strong>
                    <p>Время игры</p>
                </div>
            </div>
        </div>
        
        <!-- Detailed Stats (collapsible) -->
        <div style="margin-top: 25px; padding: 15px; background: rgba(0,0,0,0.2); border-radius: 10px;">
            <details style="cursor: pointer;">
                <summary style="font-weight: 600; color: var(--text-primary);">📊 Детальная статистика</summary>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-top: 15px; font-size: 14px;">
                    <div><span style="color:var(--text-secondary)">Ассисты:</span> <strong style="color:var(--text-primary)"><?= $profile['assists'] ?></strong></div>
                    <div><span style="color:var(--text-secondary)">Выстрелов:</span> <strong style="color:var(--text-primary)"><?= number_format($profile['shoots']) ?></strong></div>
                    <div><span style="color:var(--text-secondary)">Попаданий:</span> <strong style="color:var(--text-primary)"><?= number_format($profile['hits']) ?></strong></div>
                    <div><span style="color:var(--text-secondary)">Раунды ↑:</span> <strong style="color:var(--text-primary)"><?= $profile['round_win'] ?></strong></div>
                    <div><span style="color:var(--text-secondary)">Раунды ↓:</span> <strong style="color:var(--text-primary)"><?= $profile['round_lose'] ?></strong></div>
                    <div><span style="color:var(--text-secondary)">Последний вход:</span> <strong style="color:var(--text-primary)"><?= $profile['last_seen'] ?></strong></div>
                </div>
            </details>
        </div>
        <?php endif; ?>
    </div>
    
</div>
</div>