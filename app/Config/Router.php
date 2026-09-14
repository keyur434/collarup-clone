<?php

class Router
{
    private $routes = [];
    private $middlewares = [];

    public function get($path, $handler, $middleware = [])
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post($path, $handler, $middleware = [])
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    private function add($method, $path, $handler, $middleware)
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch()
    {
        $uri = $this->getUri();
        $method = $_SERVER['REQUEST_METHOD'];

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            $pattern = $this->convertPath($route['path']);
            if (preg_match($pattern, $uri, $matches)) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (!is_int($key)) {
                        $params[$key] = $value;
                    }
                }
                foreach ($route['middleware'] as $mw) {
                    call_user_func($mw);
                }
                return $this->invoke($route['handler'], $params);
            }
        }

        http_response_code(404);
        echo view('partials.404');
    }

    private function getUri()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $uri = parse_url($uri, PHP_URL_PATH);
        $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        if ($base !== '/' && strpos($uri, $base) === 0) {
            $uri = substr($uri, strlen($base));
        }
        $uri = preg_replace('#^/index\.php#', '', $uri);
        return '/' . trim($uri, '/');
    }

    private function convertPath($path)
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    private function invoke($handler, $params)
    {
        if (is_callable($handler)) {
            return call_user_func_array($handler, $params);
        }
        if (is_string($handler) && strpos($handler, '@') !== false) {
            list($class, $method) = explode('@', $handler);
            $file = BASE_PATH . '/app/Controllers/' . $class . '.php';
            if (!file_exists($file)) {
                throw new RuntimeException("Controller not found: $class");
            }
            require_once $file;
            $controller = new $class();
            return call_user_func_array([$controller, $method], $params);
        }
        throw new RuntimeException('Invalid route handler');
    }
}
