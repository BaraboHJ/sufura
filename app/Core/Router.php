<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $path, string $method): void
    {
        $handler = $this->routes[$method][$path] ?? null;

        if ($handler) {
            $handler();
            return;
        }

        foreach ($this->routes[$method] ?? [] as $route => $routeHandler) {
            if (strpos($route, ':') === false) {
                continue;
            }

            $pattern = preg_replace('#:([a-zA-Z_][a-zA-Z0-9_]*)#', '([^/]+)', $route);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $path, $matches)) {
                array_shift($matches);
                $params = [];
                if (preg_match_all('#:([a-zA-Z_][a-zA-Z0-9_]*)#', $route, $paramNames)) {
                    foreach ($paramNames[1] as $index => $name) {
                        $params[$name] = $matches[$index] ?? null;
                    }
                }
                $routeHandler($params);
                return;
            }
        }

        http_response_code(404);
        echo 'Not Found';
    }
}
