<?php
/**
 * Senior CMS - Main Entry Point
 * ФИНАЛЬНАЯ версия с правильным порядком выполнения
 */

declare(strict_types=1);

// === 🔍 Авто-определение базовой директории ===
function findBaseDir(string $currentDir): string {
    $pathsToCheck = [
        $currentDir,
        dirname($currentDir),
        dirname(dirname($currentDir)),
        $_SERVER['DOCUMENT_ROOT'] ?? '',
    ];
    
    foreach ($pathsToCheck as $path) {
        if (is_dir($path . '/app') && is_file($path . '/app/includes/functions.php')) {
            return realpath($path);
        }
    }
    return $currentDir;
}

$CURRENT_DIR = __DIR__;
$BASE_DIR = findBaseDir($CURRENT_DIR);

// === Константы путей ===
define('APP_DIR', $BASE_DIR . '/app');
define('STORAGE_DIR', $BASE_DIR . '/storage');
define('APP_START', microtime(true));

// === Проверка существования ключевых файлов ===
if (!is_dir(APP_DIR)) {
    die("❌ Ошибка: папка app не найдена по пути: " . APP_DIR);
}
if (!is_file(APP_DIR . '/includes/functions.php')) {
    die("❌ Ошибка: файл functions.php не найден");
}

// === Инициализация сессии ===
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'cookie_samesite' => 'Lax',
    ]);
}

// === Загрузка ядра ===
$requiredFiles = [
    APP_DIR . '/includes/functions.php',
    APP_DIR . '/ext/Router.php',
    APP_DIR . '/ext/Modules.php',
    APP_DIR . '/ext/Graphics.php',
    APP_DIR . '/auth/handlers.php',  // ← хендлеры авторизации
];

foreach ($requiredFiles as $file) {
    if (!is_file($file)) {
        die("❌ Критическая ошибка: файл не найден: $file");
    }
    require_once $file;
}

// === Проверка функций ядра ===
if (!function_exists('get_theme')) {
    die("❌ Ошибка: функция get_theme() не найдена");
}

// === 🔐 ПРЯМАЯ обработка авторизации — СРАЗУ после загрузки хендлеров! ===
// Это должно идти ДО инициализации роутера, иначе /auth/* перехватывается роутером
$authRoutes = [
    '/auth/steam' => 'auth_steam_start',
    '/auth/steam/callback' => 'auth_steam_callback',
    '/auth/logout' => 'auth_logout',
];

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (isset($authRoutes[$requestPath])) {
    $handler = $authRoutes[$requestPath];
    if (function_exists($handler)) {
        call_user_func($handler);  // внутри хендлера есть header() + exit
        exit;  // дублирующая защита — завершаем скрипт
    }
}

// === Дальше — обычный роутинг и рендеринг ===

// Rate Limit
if (function_exists('rate_limit')) {
    rate_limit('global', 120, 60);
}

// Тема
$theme = get_theme();

// Инициализация роутера и модулей
$router = new Router('/');
$modules = new Modules(APP_DIR . '/modules', APP_DIR . '/templates', STORAGE_DIR);

// Авто-регистрация роутов из модулей
foreach ($modules->getModulesList() as $module) {
    $page    = $module['page']    ?? '';
    $route   = $module['route']   ?? '';
    $methods = $module['methods'] ?? ['GET'];
    
    if (empty($page) || empty($route)) {
        error_log("⚠️ Skipping module with invalid config: " . json_encode($module));
        continue;
    }
    
    foreach ($methods as $method) {
        $router->map(strtoupper($method), $route, $page);
    }
}

// Fallback для 404
$router->map('GET', '/*', 'module_page_404');
$router->map('POST', '/*', 'module_page_404');

// Обработка запроса
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$route = $router->match($method, $uri);

// Обеспечение существования storage
if (!is_dir(STORAGE_DIR)) {
    mkdir(STORAGE_DIR, 0777, true);
}

// Проверка наличия БД (инсталлер)
$dbConfigFile = STORAGE_DIR . '/sessions/db.php';

if (!is_file($dbConfigFile) && $route !== 'install_db') {
    http_response_code(200);
    $graphics = new Graphics(APP_DIR, $modules, STORAGE_DIR, $theme);
    
    ob_start();
    if (is_file(APP_DIR . '/modules/install_db/forward/render.php')) {
        include APP_DIR . '/modules/install_db/forward/render.php';
    } else {
        echo '<div class="alert alert-warning">Модуль установки не найден</div>';
    }
    $content = ob_get_clean();
    
    $title = 'Установка БД';
    ob_start();
    include APP_DIR . '/page/interface/head.php';
    include APP_DIR . '/page/interface/navbar.php';
    include APP_DIR . '/page/interface/sidebar.php';
    include APP_DIR . '/page/interface/container.php';
    $wrapperContent = ob_get_clean();
    
    echo "<!DOCTYPE html><html><body>" . $wrapperContent . $content . "</body></html>";
    exit;
}

// Рендеринг страницы
$graphics = new Graphics(APP_DIR, $modules, STORAGE_DIR, $theme);

try {
    $response = $graphics->render($route ?? 'module_page_404');
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Render error: ' . $e->getMessage());
    $response = $graphics->renderError('Ошибка сервера: ' . $e->getMessage(), 500);
}

echo $response;

// JS для навбара
echo '<script src="/assets/js/navbar.js" defer></script>';