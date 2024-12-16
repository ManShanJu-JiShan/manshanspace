<?php
// 显示所有错误
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 添加 Composer 自动加载
require_once __DIR__ . '/../vendor/autoload.php';

// 允许跨域请求
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, Authorization, X-Requested-With');
header('Access-Control-Expose-Headers: Authorization');
header('Access-Control-Allow-Credentials: true');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('HTTP/1.1 200 OK');
    exit(0);
}

// 手动获取 Authorization header
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $auth = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
} else {
    $headers = getallheaders();
    $auth = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    if ($auth) {
        $_SERVER['HTTP_AUTHORIZATION'] = $auth;
    }
}

// 调试信息
error_log("Authorization in index.php: " . ($auth ?? 'Not Set'));

// 路由配置
use Core\Router;
use Controllers;
use Middleware\AuthMiddleware;

$router = new Router();

// 用户相关路由（不需要认证）
$router->post('/api/user/register', [Controllers\UserController::class, 'register']);
$router->post('/api/login', [Controllers\UserController::class, 'login']);
$router->post('/api/user/reset-password', [Controllers\UserController::class, 'resetPassword']);

// 需要认证的路由
$router->middleware(AuthMiddleware::class)
    ->get('/api/users/{id}', [Controllers\UserController::class, 'getInfo']);

$router->middleware(AuthMiddleware::class)
    ->put('/api/users/{id}/profile', [Controllers\UserController::class, 'updateProfile']);

$router->middleware(AuthMiddleware::class)
    ->post('/api/users/{id}/avatar', [Controllers\UserController::class, 'uploadAvatar']);

// 修改密码路由（需要认证）
$router->middleware(AuthMiddleware::class)
    ->put('/api/user/change-password', [Controllers\UserController::class, 'changePassword']);

// 重置密码路由（不需要认证）
$router->post('/api/user/reset-password', [Controllers\UserController::class, 'resetPassword']);

$router->middleware(AuthMiddleware::class)
    ->post('/api/refresh-token', [Controllers\UserController::class, 'refreshToken']);

// 邮件发送路由
$router->post('/api/send-email', [Controllers\EmailController::class, 'send']);

// 验证码相关路由
$router->post('/api/verify/send-code', [Controllers\VerificationController::class, 'sendCode']);
$router->post('/api/verify/check-code', [Controllers\VerificationController::class, 'checkCode']);

// 调试信息
echo "=== Debug Info ===\n";
echo "Request Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "Authorization: " . ($auth ?? 'Not Set') . "\n";
echo "Current User: \n";
echo "===================\n";

$router->handle();
