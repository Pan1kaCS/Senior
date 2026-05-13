<?php

declare(strict_types=1);

// Функции для модуля профилей.
// Ядро не подхватывает assets автоматически, поэтому здесь логика/данные.

function profiles_get_steamid_from_route(string $route, array $query): ?string
{

    // Ожидаем маршрут вида: /profiles/7656119XXXXXXXX
    // В $route попадает имя страницы (module_page_profiles), поэтому тут читаем URI из GET.
    // В твоём Router не передаётся capture-параметр, поэтому используем query-параметры, если есть,
    // или пытаемся восстановить из $_SERVER['REQUEST_URI'].

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($uri, PHP_URL_PATH);
    if (!is_string($path)) $path = '';

    // /profiles/<steamid64>
    if (preg_match('#^/profiles/(7656119\d{10})$#', $path, $m)) {
        return $m[1];
    }

    // fallback: ?steamid=...
    $sid = $query['steamid'] ?? null;
    if (is_string($sid) && preg_match('/^7656119\d{10}$/', $sid)) return $sid;

    return null;
}

function profiles_fetch_stats(?string $steamid64): array
{
    // Ник/аватар берём без БД: из steam-сессии, а если пусто — подгружаем по Steam Web API.
    if (!$steamid64) {
        return [


            'steamid64' => null,
            'avatar' => null,
            'nickname' => '',
            'online' => null,
            'country_flag' => '/storage/img/flags/ru.svg',
            'faceit_elo' => 0,
            'kd' => 0,
            'kills' => 0,
            'headshot_percent' => 0,
            'hours' => 0,
            'place' => 0,
            'xp_progress_percent' => 0,
            'xp_value' => 0,
            'xp_max' => 0,
            'level' => 0,
            'rank_text' => '',
        ];
    }

    // Подключаем БД и префикс из ядра (в проекте обычно глобально доступно $pdo и $prefix).
    global $pdo, $prefix;

    $avatar = get_steam_avatar($steamid64, 'full') ?: "https://avatars.steamstatic.com/{$steamid64}_full.jpg";

    // Никнейм берём из Steam-сессии/рендера, если доступно
    $nicknameFromSession = $_SESSION['steam']['nickname'] ?? $_SESSION['steam_id64_nickname'] ?? $_SESSION['steamid_nickname'] ?? '';

    // Иногда nickname лежит в другой форме/ключе — попробуем вытащить из steam_id64 тоже.
    if ($nicknameFromSession === '' && isset($_SESSION['steam_id64'])) {
        $sid = (string)$_SESSION['steam_id64'];
        // fallback: если ты где-то сохранял nickname через steamid, его сюда нужно добавить.
    }
    $result = [
        'steamid64' => $steamid64,
        'avatar' => $avatar,
        'nickname' => is_string($nicknameFromSession) && $nicknameFromSession !== '' ? $nicknameFromSession : '',

        'online' => null,

        'country_flag' => '/storage/img/flags/ru.svg',
        'faceit_elo' => 0,
        'kd' => 0,
        'kills' => 0,
        'headshot_percent' => 0,
        'hours' => 0,
        'place' => 0,
        'xp_progress_percent' => 0,
        'xp_value' => 0,
        'xp_max' => 0,
        'level' => 0,
        'rank_text' => '',
    ];

    // Никнейм берём строго из Steam-сессии.


    // Т.к. в твоём проекте неизвестны точные таблицы статистики профилей,



    // делаем безопасный fallback: пробуем типовые таблицы LevelsRanks по префиксу.
    // Если таблиц нет — вернём нули.

    $result = [
        'steamid64' => $steamid64,
        'avatar' => $avatar,
        'nickname' => is_string($nicknameFromSession) && $nicknameFromSession !== '' ? $nicknameFromSession : '',

        'online' => null,
        'country_flag' => '/storage/img/flags/ru.svg',
        'faceit_elo' => 0,
        'kd' => 0,
        'kills' => 0,
        'headshot_percent' => 0,
        'hours' => 0,
        'place' => 0,
        'xp_progress_percent' => 0,
        'xp_value' => 0,
        'xp_max' => 0,
        'level' => 0,
        'rank_text' => '',
    ];

    // Если нет БД — сразу вернуть дефолт.
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return $result;
    }

    try {
        // Попытка 1: таблица игроков и статы (примерные названия)
        // Важно: если этих таблиц нет — выбросится исключение и сработает fallback.

        // Никнейм: пробуем несколько вариантов схемы (в разных сборках разные имена таблиц/полей)
        $nickname = null;

        $queries = [
            "SELECT nickname FROM {$prefix}players WHERE steamid64 = :sid LIMIT 1",
            "SELECT name FROM {$prefix}players WHERE steamid64 = :sid LIMIT 1",
            "SELECT nickname FROM {$prefix}users WHERE steamid64 = :sid LIMIT 1",
            "SELECT name FROM {$prefix}users WHERE steamid64 = :sid LIMIT 1",
        ];

        foreach ($queries as $sql) {
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':sid' => $steamid64]);
                $val = $stmt->fetchColumn();
                if ($val !== false && $val !== null && (string)$val !== '') {
                    $nickname = (string)$val;
                    break;
                }
            } catch (Throwable $e) {
                // игнорируем, пробуем следующий вариант
            }
        }

        if ($nickname !== null) {
            $result['nickname'] = $nickname;
        }


        // XP/уровень
        $stmt = $pdo->prepare("SELECT xp, xp_max, level FROM {$prefix}players_xp WHERE steamid64 = :sid LIMIT 1");
        $stmt->execute([':sid' => $steamid64]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $xp = (int)($row['xp'] ?? 0);
            $xpMax = (int)($row['xp_max'] ?? 0);
            $level = (int)($row['level'] ?? 0);
            $result['xp_value'] = $xp;
            $result['xp_max'] = $xpMax;
            $result['level'] = $level;
            $result['xp_progress_percent'] = $xpMax > 0 ? (int)round(($xp / $xpMax) * 100) : 0;
        }

        // Место
        $stmt = $pdo->prepare("SELECT place FROM {$prefix}players_rank WHERE steamid64 = :sid LIMIT 1");
        $stmt->execute([':sid' => $steamid64]);
        $place = $stmt->fetchColumn();
        if ($place !== false && $place !== null) $result['place'] = (int)$place;

        // КД/килы/хедшоты/часы
        $stmt = $pdo->prepare(
            "SELECT kd, kills, headshot_percent, hours FROM {$prefix}players_stats WHERE steamid64 = :sid LIMIT 1"
        );
        $stmt->execute([':sid' => $steamid64]);
        $s = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($s) {
            $result['kd'] = (float)($s['kd'] ?? 0);
            $result['kills'] = (int)($s['kills'] ?? 0);
            $result['headshot_percent'] = (int)round((float)($s['headshot_percent'] ?? 0));
            $result['hours'] = (int)round((float)($s['hours'] ?? 0));
        }

        // Faceit ELO
        $stmt = $pdo->prepare("SELECT elo FROM {$prefix}players_faceit WHERE steamid64 = :sid LIMIT 1");
        $stmt->execute([':sid' => $steamid64]);
        $elo = $stmt->fetchColumn();
        if ($elo !== false && $elo !== null) $result['faceit_elo'] = (int)$elo;
    } catch (Throwable $e) {
        // Не ломаем страницу — просто оставляем дефолты.
        error_log('profiles_fetch_stats error: ' . $e->getMessage());
    }

    return $result;
}

function profiles_e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
