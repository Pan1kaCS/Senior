<?php
declare(strict_types=1);

// =====================
// Minimal, understandable site kernel for KS2 servers
// =====================

// Base paths
$BASE_DIR = __DIR__;
$APP_DIR = $BASE_DIR . '/app';
$PUBLIC_DIR = $BASE_DIR . '/public';

// Ensure UTF-8
header('Content-Type: text/html; charset=utf-8');

// Very small security hardening
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

// Load kernel services
require_once $APP_DIR . '/includes/functions.php';
require_once $APP_DIR . '/ext/Router.php';
require_once $APP_DIR . '/ext/Modules.php';
require_once $APP_DIR . '/ext/Graphics.php';
require_once $APP_DIR . '/auth/handlers.php';


// Make sure functions.php is loaded before any call
if (!function_exists('get_theme')) {
    throw new RuntimeException('Kernel function get_theme() is missing');
}


// Rate limit (simple)
if (function_exists('rate_limit')) {
    rate_limit('global', 120, 60);
}

// Anti XSS helpers
$theme = get_theme(); // from query ?theme=... or default

$router = new Router('/');
$router->map('GET', '/', 'home');
$router->map('GET', '/servers', 'servers');
$router->map('GET', '/about', 'about');
$router->map('GET', '/auth/steam', 'auth_steam_start');
$router->map('GET', '/auth/steam/', 'auth_steam_start');
$router->map('GET', '/auth/steam/callback', 'auth_steam_callback');
$router->map('GET', '/auth/steam/callback/', 'auth_steam_callback');
$router->map('POST', '/auth/logout', 'auth_logout');
$router->map('GET', '/admin', 'admin');
$router->map('GET', '/*', '404');



$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

$route = $router->match($method, $uri);

// Ensure storage directory exists (for configs like db.php)
if (!is_dir($BASE_DIR . '/storage')) {
    mkdir($BASE_DIR . '/storage', 0777, true);
}

// Direct handler routes (kernel pages are not backed by modules)
if ($route === 'auth_steam_start') {
    auth_steam_start();
    exit;
}
if ($route === 'auth_steam_callback') {
    // Ensure session for OpenID
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
        ]);
    }
    auth_steam_callback();
    exit;
}
if ($route === 'auth_logout') {
    auth_logout();
    exit;
}



$dbConfigFile = $BASE_DIR . '/storage/sessions/db.php';

$modules = new Modules($APP_DIR . '/modules', $APP_DIR . '/templates', $BASE_DIR . '/storage');
$modules->buildPageMap();

// If db.php is missing => show installer page
if (!is_file($dbConfigFile)) {
    http_response_code(200);
    $graphics = new Graphics($APP_DIR, $modules, $theme);
    // direct render installer module
    $modulesToRender = ['install_db'];
    $route = 'install_db';
    $modulesVar = $modulesToRender; // local to include templates
    // We bypass normal module map and render installer page
    ob_start();
    include $APP_DIR . '/modules/install_db/forward/render.php';
    $content = ob_get_clean();

    // minimal wrapper
    $title = 'Установка БД';
    ob_start();
    include $APP_DIR . '/page/interface/head.php';
    include $APP_DIR . '/page/interface/navbar.php';
    include $APP_DIR . '/page/interface/sidebar.php';
    include $APP_DIR . '/page/interface/container.php';
    $wrapperContent = ob_get_clean();

    echo "<!doctype html>" . $wrapperContent . $content;
    exit;
}

// Ensure installer can handle POST callbacks even when db.php is missing


$graphics = new Graphics($APP_DIR, $modules, $theme);


// Make $graphics available in template scope
// (included files don't automatically get access to $graphics unless variable exists in local scope)
$graphicsObj = $graphics;

try {
    $response = $graphicsObj->render($route);

} catch (Throwable $e) {
    http_response_code(500);
    $response = $graphics->render('500', ['error' => $e->getMessage()]);
}

echo $response;

    // Include navbar JavaScript after rendering the response
    echo '<script src="/app/page/interface/navbar.js"></script>';
