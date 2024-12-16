<?php
namespace Core;

class Router {
    private $handlers = [];
    private $middleware = null;
    private const METHOD_POST = 'POST';
    private const METHOD_GET = 'GET';
    private const METHOD_PUT = 'PUT';
    
    public function get($path, $handler) {
        $this->addHandler(self::METHOD_GET, $path, $handler);
        return $this;
    }
    
    public function post($path, $handler) {
        $this->addHandler(self::METHOD_POST, $path, $handler);
        return $this;
    }
    
    public function put($path, $handler) {
        $this->addHandler(self::METHOD_PUT, $path, $handler);
        return $this;
    }
    
    private function addHandler($method, $path, $handler) {
        $this->handlers[] = [
            'path' => $path,
            'method' => $method,
            'handler' => $handler,
            'middleware' => $this->middleware
        ];
        $this->middleware = null;
    }
    
    public function middleware($middleware) {
        $this->middleware = $middleware;
        return $this;
    }
    
    public function handle() {
        $requestUri = parse_url($_SERVER['REQUEST_URI']);
        $requestPath = $requestUri['path'];
        $method = $_SERVER['REQUEST_METHOD'];
        
        // 调试信息
        error_log("=== Router Debug ===");
        error_log("Request Headers: " . print_r(getallheaders(), true));
        error_log("Request Method: " . $method);
        error_log("Request Path: " . $requestPath);
        
        $callback = null;
        $params = [];
        
        foreach ($this->handlers as $handler) {
            error_log("Checking handler: " . $handler['method'] . " " . $handler['path']);
            $result = $this->matchPath($requestPath, $handler['path']);
            if ($result !== false && $method === $handler['method']) {
                error_log("Found matching handler!");
                $callback = $handler['handler'];
                $params = $result;
                $middleware = $handler['middleware'];
                break;
            }
        }
        
        if (!$callback) {
            error_log("No handler found");
            header("HTTP/1.0 404 Not Found");
            return $this->jsonResponse(['code' => 404, 'error' => '路由未找到']);
        }
        
        if ($middleware) {
            error_log("Executing middleware: " . $middleware);
            $next = function() use ($callback, $params) {
                return $this->executeHandler($callback, $params);
            };
            $middlewareInstance = new $middleware();
            return $middlewareInstance->handle($next);
        }
        
        return $this->executeHandler($callback, $params);
    }
    
    private function matchPath($requestPath, $routePath) {
        $basePath = '/ManShanSpace/public';
        if (strpos($requestPath, $basePath) === 0) {
            $requestPath = substr($requestPath, strlen($basePath));
        }
        
        error_log("Matching request path: " . $requestPath);
        error_log("Against route path: " . $routePath);
        
        $requestParts = explode('/', trim($requestPath, '/'));
        $routeParts = explode('/', trim($routePath, '/'));
        
        error_log("Request parts: " . print_r($requestParts, true));
        error_log("Route parts: " . print_r($routeParts, true));
        
        if (count($requestParts) !== count($routeParts)) {
            error_log("Parts count mismatch");
            return false;
        }
        
        $params = [];
        for ($i = 0; $i < count($routeParts); $i++) {
            if (preg_match('/\{(\w+)\}/', $routeParts[$i], $matches)) {
                $params[$matches[1]] = $requestParts[$i];
            } elseif ($routeParts[$i] !== $requestParts[$i]) {
                error_log("Mismatch at part {$i}: {$routeParts[$i]} != {$requestParts[$i]}");
                return false;
            }
        }
        
        error_log("Match found with params: " . print_r($params, true));
        return $params;
    }
    
    private function executeHandler($handler, $params = []) {
        if (is_array($handler)) {
            $className = $handler[0];
            $method = $handler[1];
            
            $controller = new $className();
            
            if (!empty($params)) {
                return $controller->$method(['id' => $params['id']]);
            } else {
                return $controller->$method();
            }
        }
        return call_user_func_array($handler, []);
    }
    
    private function jsonResponse($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}