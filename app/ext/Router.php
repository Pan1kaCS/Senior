<?php
/**
 * Simple Router for Senior CMS
 * Поддерживает методы: GET, POST, PUT, DELETE, PATCH
 * Авто-логирование конфликтов маршрутов
 */

class Router
{
    /**
     * @var array<string, string> Маршруты: "METHOD:/path" => "module_page_name"
     */
    private array $routes = [];

    /**
     * @var string Базовый путь для нормализации URI
     */
    private string $basePath;

    public function __construct(string $basePath = '/')
    {
        $this->basePath = rtrim($basePath, '/') . '/';
    }

    /**
     * Регистрация маршрута
     * 
     * @param string $method HTTP-метод (GET, POST, etc.)
     * @param string $route Путь (например, "/admin" или "/*")
     * @param string $page Имя модуля (например, "module_page_admin")
     * @return void
     */
    public function map(string $method, string $route, string $page): void
    {
        // Нормализация: убираем дублирующие слеши, добавляем ведущий
        $route = '/' . trim($route, '/');
        if ($route === '/') {
            $route = '/';
        }
        
        $method = strtoupper($method);
        $key = $method . ':' . $route;

        // 🔍 Проверка на конфликт маршрутов
        if (isset($this->routes[$key])) {
            $existing = $this->routes[$key];
            error_log("⚠️ Route conflict: $key already mapped to '$existing', trying to add '$page'");
            // Не перезаписываем — первый зарегистрированный модуль имеет приоритет
            // Это помогает избежать случайной подмены маршрутов
            return;
        }

        $this->routes[$key] = $page;
    }

    /**
     * Поиск модуля для запрошенного метода и пути
     * 
     * @param string $method HTTP-метод запроса
     * @param string $uri Запрошенный URI (например, "/admin")
     * @return string|null Имя модуля или null если не найдено
     */
    public function match(string $method, string $uri): ?string
    {
        $method = strtoupper($method);
        
        // Нормализация URI: убираем query string, добавляем ведущий слеш
        $uri = '/' . trim(parse_url($uri, PHP_URL_PATH) ?? '', '/');
        if ($uri === '') {
            $uri = '/';
        }

        // 1. Точное совпадение
        $key = $method . ':' . $uri;
        if (isset($this->routes[$key])) {
            return $this->routes[$key];
        }

        // 2. Проверка wildcard маршрутов (/*)
        $wildcardKey = $method . ':/*';
        if (isset($this->routes[$wildcardKey])) {
            return $this->routes[$wildcardKey];
        }

        // 3. Проверка только по пути (игнорируя метод) — для простых случаев
        $keyAnyMethod = 'ANY:' . $uri;
        if (isset($this->routes[$keyAnyMethod])) {
            return $this->routes[$keyAnyMethod];
        }

        // 4. Wildcard для любого метода
        if (isset($this->routes['ANY:/*'])) {
            return $this->routes['ANY:/*'];
        }

        // Не найдено
        return null;
    }

    /**
     * Возвращает все зарегистрированные маршруты (для отладки)
     * 
     * @return array<string, string>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Быстрая регистрация группы маршрутов
     * 
     * @param array $routes Массив ['GET' => ['/home' => 'module_page_home'], ...]
     * @return self
     */
    public function registerBatch(array $routes): self
    {
        foreach ($routes as $method => $paths) {
            if (!is_array($paths)) continue;
            foreach ($paths as $path => $page) {
                $this->map($method, $path, $page);
            }
        }
        return $this;
    }

    /**
     * Алиас для map() — более семантичный
     */
    public function add(string $method, string $route, string $page): self
    {
        $this->map($method, $route, $page);
        return $this;
    }

    /**
     * GET-маршрут (сокращение)
     */
    public function get(string $route, string $page): self
    {
        $this->map('GET', $route, $page);
        return $this;
    }

    /**
     * POST-маршрут (сокращение)
     */
    public function post(string $route, string $page): self
    {
        $this->map('POST', $route, $page);
        return $this;
    }
}