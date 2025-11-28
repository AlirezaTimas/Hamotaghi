<?php
/**
 * Simple Router
 * Handles routing for MVC architecture
 */

declare(strict_types=1);

class Router
{
    private array $routes = [];
    private string $basePath = '';

    /**
     * Add route
     */
    public function add(string $method, string $path, callable|string $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler
        ];
    }

    /**
     * GET route
     */
    public function get(string $path, callable|string $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    /**
     * POST route
     */
    public function post(string $path, callable|string $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    /**
     * Dispatch request
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = $this->getCurrentPath();

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $this->matchPath($route['path'], $path)) {
                $handler = $route['handler'];
                
                if (is_string($handler) && strpos($handler, '@') !== false) {
                    [$controller, $method] = explode('@', $handler);
                    $controllerClass = $controller . 'Controller';
                    
                    if (class_exists($controllerClass)) {
                        $controllerInstance = new $controllerClass();
                        if (method_exists($controllerInstance, $method)) {
                            $controllerInstance->$method();
                            return;
                        }
                    }
                } elseif (is_callable($handler)) {
                    call_user_func($handler);
                    return;
                }
            }
        }

        // No route matched - return 404 or fallback to old system
        http_response_code(404);
        echo "صفحه یافت نشد";
    }

    /**
     * Get current request path
     */
    private function getCurrentPath(): string
    {
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($path, PHP_URL_PATH);
        $path = str_replace($this->basePath, '', $path);
        return rtrim($path, '/') ?: '/';
    }

    /**
     * Match route path with request path
     */
    private function matchPath(string $routePath, string $requestPath): bool
    {
        // Simple exact match for now
        return $routePath === $requestPath;
    }

    /**
     * Set base path
     */
    public function setBasePath(string $path): void
    {
        $this->basePath = $path;
    }
}

