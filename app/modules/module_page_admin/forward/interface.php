<?php
/**
* Admin Panel Interface
* Модуль: module_page_admin
* Исправлено: запрос соответствует реальной структуре таблицы lvl_web_admins
*/
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Берём SteamID64 из корректного ключа сессии
$steamId64 = $_SESSION['steam']['steam_id64'] ?? null;
if (!$steamId64) {
    http_response_code(403);
    die('<div class="alert alert-danger mt-4"><i class="bi bi-exclamation-triangle-fill"></i> <strong>Доступ запрещён!</strong> Требуется авторизация.</div>');
}

// 2. Инициализация БД
global $pdo, $prefix;
$dbPrefix = $prefix ?? 'lvl_';

if (!($pdo instanceof PDO)) {
    $cfgFile = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/storage/sessions/db.php';
    if (!is_file($cfgFile)) {
        die('Ошибка: Не найден конфигурационный файл БД.');
    }
    $cfg = require $cfgFile;
    $host = $cfg['Core'][0]['HOST'] ?? '';
    $user = $cfg['Core'][0]['USER'] ?? '';
    $pass = $cfg['Core'][0]['PASS'] ?? '';
    $dbName = $cfg['Core'][0]['DB'][0]['DB'] ?? '';
    $dbPrefix = $cfg['Core'][0]['DB'][0]['Prefix'][0]['table'] ?? 'lvl_';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (PDOException $e) {
        die('Ошибка подключения к БД: ' . htmlspecialchars($e->getMessage()));
    }
}

// 3. Проверка прав администратора
$isAdmin = false;
try {
    // 🔧 ИСПРАВЛЕНО: Запрос соответствует вашей структуре таблицы (только id)
    $stmt = $pdo->prepare("SELECT id FROM {$dbPrefix}web_admins WHERE steam_id64 = :sid LIMIT 1");
    $stmt->execute([':sid' => $steamId64]);
    if ($stmt->fetch()) {
        $isAdmin = true;
    }
} catch (Exception $e) {
    // Теперь ошибка SQL не скроется, а выведется явно для отладки
    die('❌ SQL Error: ' . htmlspecialchars($e->getMessage()) . '<br><a href="/">← На главную</a>');
}

if (!$isAdmin) {
    http_response_code(403);
    die('<div class="alert alert-danger mt-4"><i class="bi bi-shield-x"></i> <strong>Ошибка доступа!</strong> Ваш SteamID (' . htmlspecialchars($steamId64) . ') не найден в таблице администраторов.</div>
         <div class="text-center mt-3"><a href="/" class="btn btn-secondary">← На главную</a></div>');
}

// === КОНТЕНТ АДМИН-ПАНЕЛИ ===
$formMessage = '';
$formMessageType = 'info';

// Добавление сервера
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_server'])) {
    $serverName = trim($_POST['server_name'] ?? '');
    $serverMod  = trim($_POST['server_mod'] ?? '');
    $serverIp   = trim($_POST['server_ip'] ?? '');
    
    if (empty($serverName) || empty($serverIp)) {
        $formMessage = 'Ошибка: Название и IP сервера обязательны';
        $formMessageType = 'danger';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO {$dbPrefix}web_servers (name, mod, ip, created_at) VALUES (:name, :mod, :ip, NOW())");
            $stmt->execute([':name' => $serverName, ':mod' => $serverMod, ':ip' => $serverIp]);
            $formMessage = '✅ Сервер успешно добавлен';
            $formMessageType = 'success';
        } catch (Exception $e) {
            $formMessage = 'Ошибка: ' . htmlspecialchars($e->getMessage());
            $formMessageType = 'danger';
        }
    }
}

// Удаление сервера
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_server'])) {
    $serverId = (int)($_POST['server_id'] ?? 0);
    if ($serverId > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM {$dbPrefix}web_servers WHERE id = :id");
            $stmt->execute([':id' => $serverId]);
            $formMessage = '🗑️ Сервер удалён';
            $formMessageType = 'warning';
        } catch (Exception $e) {
            $formMessage = 'Ошибка удаления: ' . htmlspecialchars($e->getMessage());
            $formMessageType = 'danger';
        }
    }
}

// Получение списка серверов
$servers = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM {$dbPrefix}web_servers ORDER BY created_at DESC");
    $stmt->execute();
    $servers = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Failed to fetch servers: ' . $e->getMessage());
}

// Получение логов
$logs = [];
$logsDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/storage/logs';
if (is_dir($logsDir)) {
    $logFiles = glob($logsDir . '/*.log');
    usort($logFiles, fn($a, $b) => filemtime($b) - filemtime($a));
    foreach (array_slice($logFiles, 0, 5) as $logFile) {
        $logs[] = [
            'filename' => basename($logFile),
            'filesize' => filesize($logFile),
            'filetime' => filemtime($logFile),
            'content'  => implode("\n", array_slice(array_reverse(file($logFile, FILE_IGNORE_NEW_LINES)), 0, 50))
        ];
    }
}
?>
<!-- === HTML ИНТЕРФЕЙС === -->
<div class="admin-panel container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="bi bi-shield-lock text-warning"></i> Панель администратора</h2>
        <span class="badge bg-secondary"><i class="bi bi-person"></i> <?= htmlspecialchars($_SESSION['steam']['personaname'] ?? 'Admin') ?></span>
    </div>

    <?php if ($formMessage): ?>
    <div class="alert alert-<?= $formMessageType ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($formMessage) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white"><i class="bi bi-server"></i> Управление серверами</div>
                <div class="card-body">
                    <form method="POST" class="mb-4">
                        <div class="mb-2"><label class="form-label">Название сервера *</label><input type="text" name="server_name" class="form-control" required></div>
                        <div class="mb-2"><label class="form-label">IP:Port *</label><input type="text" name="server_ip" class="form-control" placeholder="192.168.1.1:27015" required></div>
                        <div class="mb-3"><label class="form-label">Мод</label><input type="text" name="server_mod" class="form-control" placeholder="csgo, tf2, etc."></div>
                        <button type="submit" name="add_server" class="btn btn-success w-100"><i class="bi bi-plus-circle"></i> Добавить сервер</button>
                    </form>
                    <h6 class="mb-3">Существующие серверы (<?= count($servers) ?>)</h6>
                    <?php if (empty($servers)): ?>
                        <p class="text-muted small">Серверы не настроены</p>
                    <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($servers as $srv): ?>
                        <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div><strong><?= htmlspecialchars($srv['name']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($srv['ip']) ?> <?= $srv['mod'] ? '• ' . htmlspecialchars($srv['mod']) : '' ?></small></div>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Удалить сервер?');"><input type="hidden" name="server_id" value="<?= $srv['id'] ?>"><button type="submit" name="delete_server" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white"><i class="bi bi-journal-text"></i> Системные логи</div>
                <div class="card-body">
                    <?php if (empty($logs)): ?>
                        <p class="text-muted">Лог-файлы не найдены</p>
                    <?php else: ?>
                    <div class="accordion" id="logsAccordion">
                        <?php foreach ($logs as $idx => $log): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#log<?= $idx ?>"><small>📄 <?= htmlspecialchars($log['filename']) ?> • <?= round($log['filesize']/1024, 1) ?> KB • <?= date('d.m H:i', $log['filetime']) ?></small></button></h2>
                            <div id="log<?= $idx ?>" class="accordion-collapse collapse" data-bs-parent="#logsAccordion"><div class="accordion-body"><pre class="bg-light p-2 rounded small" style="max-height:200px;overflow:auto;"><?= htmlspecialchars($log['content']) ?></pre></div></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 text-center text-muted small"><i class="bi bi-clock"></i> Время генерации: <?= defined('APP_START') ? round((microtime(true) - APP_START) * 1000) . ' мс' : 'N/A' ?><?php if (defined('APP_VERSION')): ?> • Версия: <?= APP_VERSION ?><?php endif; ?></div>
</div>

<style>
.admin-panel .card { border: none; }
.admin-panel .accordion-button:not(.collapsed) { background-color: #e7f1ff; color: #0d6efd; }
</style>