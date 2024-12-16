<?php
namespace Core;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class BaseController {
    /**
     * 返回 JSON 响应
     */
    protected function json($data, $statusCode = 200) {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data);
        return true;
    }
    
    /**
     * 获取请求中的 JSON 数据
     */
    protected function getRequestJson() {
        $json = file_get_contents('php://input');
        return json_decode($json, true);
    }
    
    protected function error($message, $code = 400) {
        header('Content-Type: application/json');
        echo json_encode([
            'code' => $code,
            'error' => $message
        ]);
    }
    
    protected function getCurrentUser() {
        $headers = getallheaders();
        $auth = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        if (preg_match('/Bearer\s+(.+)/', $auth, $matches)) {
            $token = $matches[1];
            $config = require __DIR__ . '/../config/jwt.php';
            
            try {
                $decoded = JWT::decode($token, new Key($config['secret_key'], 'HS256'));
                return [
                    'id' => $decoded->uid,
                    'email' => $decoded->email
                ];
            } catch (\Exception $e) {
                return null;
            }
        }
        
        return null;
    }
} 