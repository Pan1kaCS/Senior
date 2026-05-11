<?php
declare(strict_types=1);

final class Router {
    private string $basePath;

    /** @var array<int, array{method:string, pattern:string, route:string}> */
    private array $routes = [];

    public function __construct(string $basePath = '/') {
        $this->basePath = $basePath ?: '/';
    }

    public function map(string $method, string $pattern, string $route): void {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'route' => $route,
        ];
    }

    public function match(string $method, string $uri): string {
        $method = strtoupper($method);
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        foreach ($this->routes as $r) {
            if ($r['method'] !== $method) {
                continue;
            }

            $pattern = $r['pattern'];
            if ($pattern === '/*') {
                return $r['route'];
            }

            // Convert "/*" to wildcard, otherwise exact match.
            if (strpos($pattern, '*') !== false) {
                $regex = '#^' . str_replace(['.', '*'], ['\\.', '.*'], $pattern) . '$#u';
                if (preg_match($regex, $path) === 1) {
                    return $r['route'];
                }
            } else {

                if ($pattern === $path) {
                    return $r['route'];
                }
            }
        }

        return '404';
    }
}

