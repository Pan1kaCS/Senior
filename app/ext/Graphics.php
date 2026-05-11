<?php
/**
 * Graphics Engine for Senior CMS
 * Отвечает за рендеринг страниц, модулей и шаблонов
 */

class Graphics
{
    private string $appDir;
    private Modules $modules;
    private string $theme;
    private string $storageDir;

    public function __construct(string $appDir, Modules $modules, string $storageDir, string $theme = 'blackwhite')
    {
        $this->appDir = rtrim($appDir, '/');
        $this->modules = $modules;
        $this->storageDir = rtrim($storageDir, '/');
        $this->theme = $theme;
    }

    /**
     * Основной метод рендеринга страницы
     */
    public function render(string $page, array $vars = []): string
    {
        // Проверяем существование модуля
        if (!$this->modules->hasModule($page)) {
            return $this->renderError("Модуль '$page' не найден", 404);
        }

        // Получаем метаданные модуля
        $moduleData = $this->modules->getModulesForPage($page);
        if (!$moduleData) {
            return $this->renderError("Не удалось загрузить данные модуля '$page'", 500);
        }

        // Проверка прав доступа (только для admin_only модулей)
        $meta = $moduleData['meta'] ?? [];
        if (!empty($meta['admin_only']) && !function_exists('is_admin_user')) {
            // Если функция не подключена — пробуем подключить
            $functionsFile = $this->appDir . '/includes/functions.php';
            if (is_file($functionsFile)) {
                require_once $functionsFile;
            }
        }

        if (!empty($meta['admin_only']) && function_exists('is_admin_user') && !is_admin_user()) {
            return $this->renderError("Доступ запрещён! Требуется авторизация администратора.", 403);
        }

        // Подготовка переменных для модуля
        $renderVars = array_merge([
            'graphics' => $this,
            'modules' => $this->modules,
            'theme' => $this->theme,
            'route' => $page,
            'appDir' => $this->appDir,
            'storageDir' => $this->storageDir,
        ], $vars);

        // Рендерим контент модуля
        $content = $this->modules->render($page, $renderVars);

        // Оборачиваем в общий шаблон
        return $this->wrapInLayout($content, $meta['title'] ?? 'Senior CMS');
    }

    /**
     * ✅ Метод для рендеринга ошибок (ранее отсутствовал!)
     */
    public function renderError(string $message, int $httpCode = 500): string
    {
        http_response_code($httpCode);
        
        $title = match($httpCode) {
            403 => 'Доступ запрещён',
            404 => 'Страница не найдена',
            500 => 'Внутренняя ошибка сервера',
            default => 'Ошибка'
        };

        $content = sprintf('
        <div class="error-page text-center py-5">
            <div class="display-1 text-danger mb-3">⚠️</div>
            <h2 class="h4 mb-3">%s</h2>
            <p class="text-muted mb-4">%s</p>
            <a href="/" class="btn btn-primary">← На главную</a>
        </div>
        ', htmlspecialchars($title), htmlspecialchars($message));

        return $this->wrapInLayout($content, $title);
    }

    /**
     * Оборачивает контент в общий шаблон (head + navbar + sidebar + footer)
     */
    private function wrapInLayout(string $content, string $title = 'Senior CMS'): string
    {
        ob_start();

        // Head
        $headFile = $this->appDir . '/page/interface/head.php';
        if (is_file($headFile)) {
            include $headFile;
        } else {
            echo $this->fallbackHead($title);
        }

        // Navbar
        $navbarFile = $this->appDir . '/page/interface/navbar.php';
        if (is_file($navbarFile)) {
            include_once $navbarFile;
        }

        // Sidebar
        $sidebarFile = $this->appDir . '/page/interface/sidebar.php';
        if (is_file($sidebarFile)) {
            include_once $sidebarFile;
        }

        // Main content
        echo '<main class="main"><div class="main__card">';
        echo $content;
        echo '</div></main>';

        // Footer
        $footerFile = $this->appDir . '/page/interface/footer.php';
        if (is_file($footerFile)) {
            include_once $footerFile;
        } else {
            echo $this->fallbackFooter();
        }

        return ob_get_clean();
    }

    /**
     * Fallback для head.php если файл не найден
     */
    private function fallbackHead(string $title): string
    {
        return sprintf('
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>%s</title>
            <link rel="stylesheet" href="/public/assets/themes/%s/base.css">
            <link rel="stylesheet" href="/public/assets/themes/%s/%s.css">
        </head>
        <body>
        ', htmlspecialchars($title), htmlspecialchars($this->theme), htmlspecialchars($this->theme), htmlspecialchars($this->theme));
    }

    /**
     * Fallback для footer.php если файл не найден
     */
    private function fallbackFooter(): string
    {
        return '
        <footer class="footer">
            <span class="footer__text">© ' . date('Y') . ' SVOYSKIY Project</span>
        </footer>
        </body></html>
        ';
    }

    /**
     * Геттер для темы
     */
    public function getTheme(): string
    {
        return $this->theme;
    }

    /**
     * Геттер для modules
     */
    public function getModules(): Modules
    {
        return $this->modules;
    }

    /**
     * Геттер для appDir
     */
    public function getAppDir(): string
    {
        return $this->appDir;
    }
}