<?php
declare(strict_types=1);

final class Graphics {
    private string $appDir;
    private Modules $modules;
    private string $theme;

    public function __construct(string $appDir, Modules $modules, string $theme) {
        $this->appDir = $appDir;
        $this->modules = $modules;
        $this->theme = $theme;
    }

    /**
     * @param array<string, mixed> $vars
     */
    public function render(string $route, array $vars = []): string {
        $title = $this->getTitle($route, $vars);
        http_response_code($this->getStatusCode($route));

        $moduleNames = $this->modules->getModulesForPage($route);

        // Provide locals for included templates
        $modules = $moduleNames;
        $graphics = $this;
        $title = $title;

        ob_start();
        include $this->appDir . '/page/interface/head.php';
        include $this->appDir . '/page/interface/navbar.php';
        include $this->appDir . '/page/interface/sidebar.php';
        include $this->appDir . '/page/interface/container.php';
        $content = ob_get_clean();

        ob_start();
        include $this->appDir . '/page/footer.php';
        $footer = ob_get_clean();

        return $content . $footer;
    }

    private function getStatusCode(string $route): int {
        switch ($route) {
            case '404':
                return 404;
            case '500':
                return 500;
            default:
                return 200;
        }
    }

    private function getTitle(string $route, array $vars): string {
        switch ($route) {
            case 'home':
                return 'KS2 Servers';
            case 'servers':
                return 'Servers';
            case 'about':
                return 'About';
            case '404':
                return 'Not Found';
            case '500':
                return 'Server Error';
            default:
                return 'KS2 Servers';
        }
    }


    public function renderModule(string $module, array $vars = []): void {
        $file = $this->appDir . '/modules/' . $module . '/forward/render.php';
        if (is_file($file)) {
            include $file;
        }
    }

    public function getThemeCssHref(): string {
        // Must match URL structure used in templates: /assets/... -> public/assets/...
        return '/public/assets/themes/' . rawurlencode($this->theme) . '/blackwhite.css';
    }

}


