<?php
// DB installer + schema bootstrap.
// Kernel renders this page when storage/sessions/db.php is missing.
// After successful installation it writes storage/sessions/db.php (including STEAM_API_KEY).
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Установка БД</title>
    <link rel="stylesheet" href="/public/assets/themes/blackwhite/base.css" />
    <link rel="stylesheet" href="/public/assets/themes/blackwhite/blackwhite.css" />
</head>
<body>
<header class="topbar">
    <nav class="topbar__nav">
        <a class="topbar__link" href="/">На главную</a>
    </nav>
</header>

<main class="main">
    <div class="main__card">
        <h1 class="page-title">Установка БД</h1>
        <p class="page-subtitle">Файл <code>storage/sessions/db.php</code> не найден. Укажите параметры подключения — система создаст нужные таблицы и сгенерирует config.</p>

        <h2>Параметры БД</h2>

        <form method="post" style="display:grid;gap:12px;max-width:680px;">
            <input name="db_host" placeholder="HOST" required style="padding:10px;border-radius:10px;border:1px solid var(--border);background:rgba(0,0,0,0.2);color:var(--text-primary)" />
            <input name="db_user" placeholder="USER" required style="padding:10px;border-radius:10px;border:1px solid var(--border);background:rgba(0,0,0,0.2);color:var(--text-primary)" />
            <input type="password" name="db_pass" placeholder="PASS" required style="padding:10px;border-radius:10px;border:1px solid var(--border);background:rgba(0,0,0,0.2);color:var(--text-primary)" />
            <input name="db_name" placeholder="DB" required style="padding:10px;border-radius:10px;border:1px solid var(--border);background:rgba(0,0,0,0.2);color:var(--text-primary)" />

            <input name="db_prefix_table" placeholder="Prefix.table (пример: lvl_)" value="lvl_" required style="padding:10px;border-radius:10px;border:1px solid var(--border);background:rgba(0,0,0,0.2);color:var(--text-primary)" />

            <input name="steam_api_key" placeholder="STEAM_API_KEY" required style="padding:10px;border-radius:10px;border:1px solid var(--border);background:rgba(0,0,0,0.2);color:var(--text-primary)" />

            <button class="button" type="submit" style="margin-top:6px;">Установить</button>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $host = trim((string)($_POST['db_host'] ?? ''));
            $user = trim((string)($_POST['db_user'] ?? ''));
            $pass = (string)($_POST['db_pass'] ?? '');
            $dbName = trim((string)($_POST['db_name'] ?? ''));
            $prefixTable = trim((string)($_POST['db_prefix_table'] ?? 'lvl_'));
            $steamApiKey = trim((string)($_POST['steam_api_key'] ?? ''));

            $ok = true;
            $error = '';

            try {
                // Basic prefix sanitation (avoid SQL injection in identifiers)
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $prefixTable)) {
                    throw new RuntimeException('Неверный формат Prefix.table');
                }

                $dsn = 'mysql:host=' . $host . ';dbname=' . $dbName . ';charset=utf8mb4';
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);

                $tables = [];
                $tables[] = "CREATE TABLE IF NOT EXISTS {$prefixTable}web_admins (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    steam_id64 VARCHAR(32) NOT NULL UNIQUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

                $tables[] = "CREATE TABLE IF NOT EXISTS {$prefixTable}web_servers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    `mod` VARCHAR(64) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

                $tables[] = "CREATE TABLE IF NOT EXISTS {$prefixTable}web_profiles (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    steam_id64 VARCHAR(32) NOT NULL UNIQUE,
                    nickname VARCHAR(255) DEFAULT NULL,
                    avatar_url TEXT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

                $tables[] = "CREATE TABLE IF NOT EXISTS {$prefixTable}web_online (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    steam_id64 VARCHAR(32) NOT NULL UNIQUE,
                    last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    server_id INT NULL,
                    CONSTRAINT fk_online_server FOREIGN KEY (server_id) REFERENCES {$prefixTable}web_servers(id) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

                $tables[] = "CREATE TABLE IF NOT EXISTS {$prefixTable}web_notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    steam_id64 VARCHAR(32) NOT NULL,
                    payload_json JSON NULL,
                    is_read TINYINT(1) NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_notif_read (steam_id64, is_read)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

                $tables[] = "CREATE TABLE IF NOT EXISTS {$prefixTable}web_cookie_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    steam_id64 VARCHAR(32) NOT NULL,
                    token_hash CHAR(64) NOT NULL UNIQUE,
                    user_agent VARCHAR(255) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    expires_at TIMESTAMP NULL DEFAULT NULL,
                    INDEX idx_token_exp (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

                $tables[] = "CREATE TABLE IF NOT EXISTS {$prefixTable}web_attendance (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    steam_id64 VARCHAR(32) NOT NULL,
                    server_id INT NULL,
                    attended_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_att_server (server_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

                foreach ($tables as $sql) {
                    $pdo->exec($sql);
                }

                $config = [
                    'LevelsRanks' => [0 => [
                        'HOST' => $host,
                        'USER' => $user,
                        'PASS' => $pass,
                        'DB' => [0 => [
                            'DB' => $dbName,
                            'Prefix' => [0 => [
                                'table' => $prefixTable,
                                'name' => 'default',
                                'mod' => '',
                                'ranks_pack' => 'default',
                                'steam' => '1 / 0'
                            ]],
                        ]],
                    ]],
                    'Core' => [0 => [
                        'HOST' => $host,
                        'USER' => $user,
                        'PASS' => $pass,
                        'DB' => [0 => [
                            'DB' => $dbName,
                            'Prefix' => [0 => [
                                'table' => $prefixTable,
                            ]],
                        ]],
                    ]],
                    'Steam' => [
                        'STEAM_API_KEY' => $steamApiKey,
                    ],
                ];

                // storage/sessions находится в корне проекта: d:/Proekt/SeniorCMS/storage/sessions
                // Этот файл лежит в app/page/install/, поэтому поднимаемся на 3 уровня.
                $targetDir = dirname(__DIR__, 3) . '/storage/sessions';

                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }
                $target = $targetDir . '/db.php';

                $export = "<?php\nreturn " . var_export($config, true) . ";\n";
                $saved = @file_put_contents($target, $export);
                if ($saved === false) {
                    throw new RuntimeException('Не удалось записать db.php по пути: ' . $target);
                }

                if (!is_file($target)) {
                    throw new RuntimeException('db.php не появился по пути после записи: ' . $target);
                }

                echo '<div style="margin-top:12px;padding:10px;border:1px solid var(--border);background:rgba(0,0,0,0.15);font-family:monospace;">';
                echo '<div>DEBUG target: ' . htmlspecialchars($target, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
                echo '<div>DEBUG targetDir exists: ' . (is_dir($targetDir) ? 'yes' : 'no') . '</div>';
                echo '<div>DEBUG target exists: ' . (is_file($target) ? 'yes' : 'no') . '</div>';
                echo '<div>DEBUG saved bytes: ' . htmlspecialchars((string)$saved, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
                echo '</div>';

                echo '<p style="color:#38ff66;font-weight:700;margin-top:12px;">Установка завершена. Таблицы созданы и config сохранён. Перезагрузите страницу.</p>';
                exit;
            } catch (Throwable $e) {
                $ok = false;
                $error = $e->getMessage();
            }

            if (!$ok) {
                echo '<p style="color:#ff4d4d;font-weight:700;margin-top:12px;">Ошибка: ' . htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
            }
        }
        ?>

        <p style="margin-top:16px;">После успешной установки таблиц обновите страницу — появится Steam-авторизация.</p>
    </div>
</main>
</body>
</html>

