<?php
// Admin panel render script
// Check if user is authenticated and is an admin

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['steam']) || !isset($_SESSION['steam']['steam_id64'])) {
    header('Location: /', true, 302);
    exit;
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

    // Check if user is an admin
    $steamId64 = $_SESSION['steam']['steam_id64'];
    $stmt = $pdo->prepare('SELECT id FROM ' . $prefix . 'web_admins WHERE steam_id64 = :steam_id64');
    $stmt->execute([':steam_id64' => $steamId64]);

    if (!$stmt->fetch()) {
        // User is not an admin
        http_response_code(403);
        die('Access denied: You do not have administrator privileges');
    }

} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// Get logs from storage/logs directory
$logsDir = __DIR__ . '/../../../../storage/logs';
$logs = [];

if (is_dir($logsDir)) {
    $logFiles = glob($logsDir . '/*.log');

    // Sort files by modification time, newest first
    usort($logFiles, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    // Get content of recent log files (limit to 5 files)
    $logFiles = array_slice($logFiles, 0, 5);

    foreach ($logFiles as $logFile) {
        $filename = basename($logFile);
        $filetime = filemtime($logFile);
        $filesize = filesize($logFile);

        // Read last 50 lines of each log file
        $lines = [];
        $handle = fopen($logFile, 'r');
        if ($handle) {
            // Go to end of file
            fseek($handle, 0, SEEK_END);
            $pos = ftell($handle);
            $linesRead = 0;
            $line = '';

            // Read backwards until we have 50 lines or reach beginning of file
            while ($pos >= 0 && $linesRead < 50) {
                fseek($handle, $pos);
                $ch = fgetc($handle);
                if ($ch === "\n" || $pos === 0) {
                    if ($pos === 0) {
                        $line = $ch . $line;
                    }
                    if (!empty($line)) {
                        $lines[] = ($pos === 0 ? '' : "\n") . rtrim($line, "\n");
                        $linesRead++;
                    }
                    $line = '';
                } else {
                    $line = $ch . $line;
                }
                $pos--;
            }
            fclose($handle);

            // Reverse lines to get them in chronological order
            $lines = array_reverse($lines);
        }

        $logs[] = [
            'filename' => $filename,
            'filesize' => $filesize,
            'filetime' => $filetime,
            'content' => implode('\n', $lines)
        ];
    }
}

// Get existing servers from database
try {
    $stmt = $pdo->prepare('SELECT * FROM ' . $prefix . 'web_servers ORDER BY name');
    $stmt->execute();
    $servers = $stmt->fetchAll();
} catch (Exception $e) {
    $servers = [];
    error_log('Error fetching servers: ' . $e->getMessage());
}

// Handle form submission for adding new server
$formMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_server'])) {
    $serverName = trim((string)($_POST['server_name'] ?? ''));
    $serverMod = trim((string)($_POST['server_mod'] ?? ''));

    if (empty($serverName)) {
        $formMessage = 'Error: Server name is required';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO ' . $prefix . 'web_servers (name, mod) VALUES (:name, :mod)');
            $stmt->execute([
                ':name' => $serverName,
                ':mod' => $serverMod
            ]);
            $formMessage = 'Server added successfully';

            // Refresh servers list
            $stmt = $pdo->prepare('SELECT * FROM ' . $prefix . 'web_servers ORDER BY name');
            $stmt->execute();
            $servers = $stmt->fetchAll();

        } catch (Exception $e) {
            $formMessage = 'Error adding server: ' . $e->getMessage();
        }
    }
}

// === Только контент модуля ===
?>

<h1 class="page-title">Admin Panel</h1>
<p class="page-subtitle">Manage servers and view system logs</p>

<?php if ($formMessage): ?>
    <div style="padding: 12px; margin: 12px 0; background: rgba(255, 0, 0, 0.1); border: 1px solid #ff4d4d; border-radius: 8px; color: #ff4d4d;">
        <?= htmlspecialchars($formMessage) ?>
    </div>
<?php endif; ?>

<h2>Add New Server</h2>
<form method="post" style="display:grid;gap:12px;max-width:680px;">
    <input type="text" name="server_name" placeholder="Server Name" required style="padding:10px;border-radius:10px;border:1px solid var(--border);background:rgba(0,0,0,0.2);color:var(--text-primary)">
    <input type="text" name="server_mod" placeholder="Mod (optional)" style="padding:10px;border-radius:10px;border:1px solid var(--border);background:rgba(0,0,0,0.2);color:var(--text-primary)">
    <input type="hidden" name="add_server" value="1">
    <button class="button" type="submit" style="margin-top:6px;">Add Server</button>
</form>

<h2>Existing Servers</h2>
<?php if (empty($servers)): ?>
    <p>No servers configured yet.</p>
<?php else: ?>
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;margin-top:12px;">
            <thead>
                <tr style="background:rgba(255,255,255,0.05);">
                    <th style="padding:12px;text-align:left;border-bottom:1px solid var(--border);">Name</th>
                    <th style="padding:12px;text-align:left;border-bottom:1px solid var(--border);">Mod</th>
                    <th style="padding:12px;text-align:left;border-bottom:1px solid var(--border);">ID</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($servers as $server): ?>
                <tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:12px;"><?= htmlspecialchars($server['name']) ?></td>
                    <td style="padding:12px;"><?= htmlspecialchars($server['mod'] ?? '') ?></td>
                    <td style="padding:12px;">#<?= htmlspecialchars($server['id']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<h2>System Logs</h2>
<?php if (empty($logs)): ?>
    <p>No log files found.</p>
<?php else: ?>
    <?php foreach ($logs as $log): ?>
    <div style="margin-top:12px;">
        <h3 style="margin:0 0 8px 0;">
            <?= htmlspecialchars($log['filename']) ?>
            <span style="color:var(--text-secondary);font-size:14px;">
                (<?= htmlspecialchars(date('Y-m-d H:i:s', $log['filetime'])) ?>, <?= number_format($log['filesize']/1024, 1) ?> KB)
            </span>
        </h3>
        <pre style="background:rgba(0,0,0,0.2);padding:12px;border-radius:8px;overflow-x:auto;font-family:monospace;font-size:12px;max-height:300px;overflow-y:auto;">
<?= htmlspecialchars($log['content']) ?>
        </pre>
    </div>
    <?php endforeach; ?>
<?php endif; ?>