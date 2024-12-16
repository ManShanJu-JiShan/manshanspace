<?php
namespace Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

class AuthMiddleware {
    public function handle($next) {
        // 调试信息
        error_log("=== Auth Debug ===");
        error_log("Raw headers: " . print_r(getallheaders(), true));
        error_log("SERVER variables: " . print_r($_SERVER, true));
        
        // 尝试多种方式获取 Authorization 头
        $authorization = null;
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authorization = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authorization = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                $authorization = $headers['Authorization'];
            }
        }
        
        error_log("Authorization header: " . ($authorization ?? 'Not Found'));
        
        if (!$authorization) {
            return $this->error('未登录或token无效', 401);
        }
        
        // 检查 Authorization 头格式
        if (!preg_match('/Bearer\s+(.+)/', $authorization, $matches)) {
            http_response_code(401);
            echo json_encode([
                'code' => 401,
                'error' => '未提供有效的认证token'
            ]);
            return;
        }
        
        $token = $matches[1];
        $config = require __DIR__ . '/../config/jwt.php';
        
        try {
            // 验证 token
            $decoded = JWT::decode($token, new Key($config['secret_key'], 'HS256'));
            
            // 将用户信息存储在会话中
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['current_user'] = [
                'id' => $decoded->uid,
                'email' => $decoded->email
            ];
            
            // 继续处理请求
            return $next();
            
        } catch (ExpiredException $e) {
            http_response_code(401);
            echo json_encode([
                'code' => 401,
                'error' => 'token已过期'
            ]);
            return;
        } catch (SignatureInvalidException $e) {
            http_response_code(401);
            echo json_encode([
                'code' => 401,
                'error' => 'token签名无效'
            ]);
            return;
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode([
                'code' => 401,
                'error' => 'token验证失败: ' . $e->getMessage()
            ]);
            return;
        }
    }
} 