<?php
/**
 * Авто-обнаружение модулей с префиксом module_page_*
 * Содержит все методы, ожидаемые Graphics.php и index.php
 */
class Modules 
{
    private string $modulesDir;
    private array $discoveredModules = [];

    public function __construct(string $modulesDir) {
        $this->modulesDir = rtrim($modulesDir, '/');
        $this->discoverModules();
    }

    private function discoverModules(): void {
        $pattern = $this->modulesDir . '/module_page_*';
        $dirs = glob($pattern, GLOB_ONLYDIR);
        if (!$dirs) return;

        foreach ($dirs as $dir) {
            $moduleName = basename($dir);
            $descFile = $dir . '/description.json';
            $interfaceFile = $dir . '/forward/interface.php';

            // 1. Проверяем обязательные файлы
            if (!is_file($descFile) || !is_file($interfaceFile)) {
                error_log("⚠️ Module $moduleName missing description.json or forward/interface.php");
                continue;
            }

            // 2. Валидируем JSON
            $meta = json_decode(file_get_contents($descFile), true);
            if (!is_array($meta) || empty($meta['page']) || empty($meta['route'])) {
                error_log("⚠️ Module $moduleName invalid description.json (missing 'page' or 'route')");
                continue;
            }
            
            // 3. Проверяем что page соответствует названию директории
            if ($meta['page'] !== $moduleName) {
                error_log("⚠️ Module $moduleName: 'page' in description.json does not match directory name");
                continue;
            }

            // 4. Регистрируем модуль
            $this->discoveredModules[$moduleName] = [
                'meta'      => $meta,
                'path'      => $dir,
                'interface' => $interfaceFile
            ];
        }
    }

    /**
     * Возвращает данные модуля по имени страницы (вызывается из Graphics.php)
     */
    public function getModulesForPage(string $pageName): ?array {
        return $this->discoveredModules[$pageName] ?? null;
    }

    /**
     * Список всех модулей для роутинга и админки
     */
    public function getModulesList(): array {
        $list = [];
        foreach ($this->discoveredModules as $name => $data) {
            $list[] = [
                'page'       => $data['meta']['page'],
                'title'      => $data['meta']['title'] ?? $name,
                'route'      => $data['meta']['route'],
                'methods'    => $data['meta']['methods'] ?? ['GET'],
                'admin_only' => $data['meta']['admin_only'] ?? false
            ];
        }
        return $list;
    }

    /**
     * Рендерит интерфейс модуля
     */
    public function render(string $moduleName, array $vars = []): string {
        $module = $this->getModulesForPage($moduleName);
        if (!$module) {
            return "<div class='alert alert-danger'>Модуль <code>$moduleName</code> не найден</div>";
        }
        
        extract($vars, EXTR_SKIP);
        ob_start();
        include $module['interface'];
        return ob_get_clean();
    }

    /**
     * Проверка существования модуля
     */
    public function hasModule(string $name): bool {
        return isset($this->discoveredModules[$name]);
    }
}