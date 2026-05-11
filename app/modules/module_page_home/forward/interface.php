<?php
/**
 * Модуль: module_page_home
 * Контент для главной страницы
 * 
 * Доступные переменные: $graphics, $modules, $route, $theme
 */
?>

<!-- 🔥 ТОЛЬКО контент, без обёртки! -->
<div class="home-hero">
    <h1 class="home__title">Добро пожаловать на SVOYSKIY</h1>
    <p class="home__subtitle">Лучшие серверы Counter-Strike 2</p>
</div>

<div class="servers-grid">
    <?php
    // Пример: вывод серверов (если есть БД)
    global $pdo, $prefix;
    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM {$prefix}web_servers WHERE active = 1 LIMIT 10");
            $stmt->execute();
            $servers = $stmt->fetchAll();
            
            foreach ($servers as $srv):
    ?>
        <div class="server-card">
            <div class="server-card__header">
                <h3><?= htmlspecialchars($srv['name']) ?></h3>
                <span class="badge badge-<?= htmlspecialchars($srv['mod'] ?? 'default') ?>">
                    <?= htmlspecialchars($srv['mod'] ?? 'CS2') ?>
                </span>
            </div>
            <div class="server-card__body">
                <p><strong>IP:</strong> <code><?= htmlspecialchars($srv['ip']) ?></code></p>
                <p><strong>Игроки:</strong> <span class="players-count">0/32</span></p>
            </div>
            <div class="server-card__footer">
                <button class="btn btn-primary btn-copy-ip" data-ip="<?= htmlspecialchars($srv['ip']) ?>">
                    📋 Скопировать IP
                </button>
                <a href="steam://connect/<?= htmlspecialchars($srv['ip']) ?>" class="btn btn-success">
                    🎮 Подключиться
                </a>
            </div>
        </div>
    <?php 
            endforeach;
        } catch (Exception $e) {
            echo '<p class="text-muted">Не удалось загрузить серверы</p>';
            error_log('Home servers fetch: ' . $e->getMessage());
        }
    } else {
        echo '<p class="text-muted">База данных не подключена</p>';
    }
    ?>
</div>

<!-- Скрипты только для этого модуля -->
<script>
document.querySelectorAll('.btn-copy-ip').forEach(btn => {
    btn.addEventListener('click', function() {
        const ip = this.dataset.ip;
        navigator.clipboard.writeText(ip).then(() => {
            const original = this.textContent;
            this.textContent = '✅ Скопировано!';
            setTimeout(() => this.textContent = original, 2000);
        });
    });
});
</script>

<!-- Стили только для этого модуля -->
<style>
.home-hero { text-align: center; padding: 2rem 0; }
.home__title { font-size: 2.5rem; margin-bottom: 0.5rem; }
.home__subtitle { color: var(--text-secondary); margin-bottom: 2rem; }
.servers-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem; }
.server-card { border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; background: var(--card-bg); }
.server-card__header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.server-card__body { margin-bottom: 1rem; }
.server-card__footer { display: flex; gap: 0.5rem; }
.btn-copy-ip { flex: 1; }
</style>