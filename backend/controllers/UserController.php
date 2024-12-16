<?php
namespace Controllers;

use Core\BaseController;
use Core\Database;
use Models\UserModel;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Models\User;

class UserController extends BaseController 
{
    private $userModel;
    
    public function __construct() {
        $this->userModel = new UserModel();
    }

    /**
     * 返回 JSON 响应
     * @param mixed $data 响应数据
     * @param int $statusCode HTTP状态码
     * @return bool
     */
    protected function json($data, $statusCode = 200) {
        return parent::json($data, $statusCode);
    }
    
    public function error($message, $code = 400) {
        header('Content-Type: application/json');
        echo json_encode([
            'code' => $code,
            'error' => $message
        ]);
    }
    
    public function create() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            // 简单的验证
            if (empty($data['email']) || empty($data['password']) || empty($data['nickname'])) {
                return $this->error('邮箱、密码和昵称不能为空');
            }
            
            // 检查邮箱是否已存在
            if ($this->userModel->getUserByEmail($data['email'])) {
                return $this->error('邮箱已被注册');
            }
            
            // 创建用户
            $this->userModel->createUser($data);
            
            return $this->json(['message' => '用户创建成功']);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    public function list() {
        try {
            $users = $this->userModel->findAll();
            return $this->json($users);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    public function login() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            // 验证必填字段
            if (empty($data['email']) || empty($data['password'])) {
                return $this->error('邮箱和密码不能为空');
            }
            
            // 查找用户
            $user = $this->userModel->getUserByEmail($data['email']);
            if (!$user) {
                return $this->error('用户不存在');
            }
            
            // 验证密码
            if (!password_verify($data['password'], $user['password'])) {
                return $this->error('密码错误');
            }
            
            // 生成 JWT token
            $config = require __DIR__ . '/../config/jwt.php';
            $time = time();
            $token = JWT::encode([
                'iss' => $config['issuer'],      // 签发者
                'aud' => $config['audience'],    // 接收者
                'iat' => $time,                  // 签发时间
                'exp' => $time + $config['expire_time'],  // 过期时间
                'uid' => $user['id'],            // 用户ID
                'email' => $user['email']        // 用户邮箱
            ], $config['secret_key'], 'HS256');
            
            // 移除密码后返回用户信息
            unset($user['password']);
            
            return $this->json([
                'message' => '登录成功',
                'token' => $token,
                'user' => $user
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * 获取用户信息
     * @param array $params 路由参数
     * @return void
     */
    public function getInfo($params) {
        try {
            // 验证用户ID
            $userId = $params['id'];
            if (!$userId) {
                return $this->error('用户ID不能为空');
            }
            
            // 获取当前登录用户信息
            $currentUser = $this->getCurrentUser();
            error_log("Current User in getInfo: " . print_r($currentUser, true));
            
            if (!$currentUser) {
                return $this->error('未登录或token无效', 401);
            }
            
            // 验证是否访问自己的信息
            if ($currentUser['id'] != $userId) {
                error_log("Access denied: current user {$currentUser['id']} trying to access user {$userId}");
                return $this->error('没有权限访问其他用户的息', 403);
            }
            
            // 查找用户
            $user = $this->userModel->findById($userId);
            if (!$user) {
                return $this->error('用户不存在', 404);
            }
            
            // 移除敏感信息
            unset($user['password']);
            
            return $this->json([
                'user' => $user
            ]);
            
        } catch (\Exception $e) {
            error_log("Error in getInfo: " . $e->getMessage());
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * 更新用户
     * @param int $id 用户ID
     * @return void
     */
    public function update($id = null) {
        try {
            // 验证用户ID是否存在
            if (!$id) {
                return $this->error('用户ID不能为空');
            }
            
            // 获取请求数据
            $data = json_decode(file_get_contents('php://input'), true);
            
            // 验证数据是否为空
            if (empty($data)) {
                return $this->error('更新数据不能为空');
            }
            
            // 检查是否包含不允许更新的字段
            $allowedFields = ['nickname', 'bio', 'avatar'];
            $invalidFields = array_diff(array_keys($data), $allowedFields);
            if (!empty($invalidFields)) {
                return $this->error('包含不允许更新的字段: ' . implode(', ', $invalidFields));
            }
            
            // 验证用户是否存在
            $user = $this->userModel->findById($id);
            if (!$user) {
                return $this->error('用户不存在', 404);
            }
            
            // 验证昵称
            if (isset($data['nickname'])) {
                if (mb_strlen($data['nickname'], 'UTF-8') < 2) {
                    return $this->error('昵称长度不能少于2个字');
                }
                if (mb_strlen($data['nickname'], 'UTF-8') > 50) {
                    return $this->error('昵称长度不能超过50个字符');
                }
            }
            
            // 验证简介
            if (isset($data['bio'])) {
                if (mb_strlen($data['bio'], 'UTF-8') > 500) {
                    return $this->error('简介长度不能超过500个字符');
                }
            }
            
            // 执行更新
            $result = $this->userModel->updateUser($id, $data);
            
            if ($result) {
                // 获取更新后的用户信息
                $updatedUser = $this->userModel->findById($id);
                unset($updatedUser['password']); // 移除敏感信息
                
                return $this->json([
                    'message' => '用户信息更新成功',
                    'user' => $updatedUser
                ]);
            } else {
                return $this->error('更新失败，请检查提交的数据');
            }
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * 更新用户密码
     * @param int $id 用户ID
     * @return void
     */
    public function updatePassword($id = null) {
        try {
            // 验证用户ID
            if (!$id) {
                return $this->error('用户ID不能为空');
            }
            
            // 获取请求数据
            $data = json_decode(file_get_contents('php://input'), true);
            
            // 验证必填字段
            if (empty($data['old_password']) || empty($data['new_password'])) {
                return $this->error('旧密码和新密码不能为空');
            }
            
            // 验证用户是否存在
            $user = $this->userModel->findById($id);
            if (!$user) {
                return $this->error('用户不存在', 404);
            }
            
            // 验证旧密码是否正确
            if (!$this->userModel->verifyPassword($id, $data['old_password'])) {
                return $this->error('旧密码不正确');
            }
            
            // 验证新密码格式
            if (strlen($data['new_password']) < 6) {
                return $this->error('新密码长度不能少于6个字符');
            }
            
            if (strlen($data['new_password']) > 20) {
                return $this->error('新密码长度不能超过20个字符');
            }
            
            // 验证新旧密码是否相同
            if ($data['old_password'] === $data['new_password']) {
                return $this->error('新密码不能与旧密码相同');
            }
            
            // 更新密码
            $result = $this->userModel->updatePassword($id, $data['new_password']);
            
            if ($result) {
                return $this->json([
                    'message' => '密码更新成功'
                ]);
            } else {
                return $this->error('密码更新失败');
            }
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * 上传用户头像
     * @param array $params 路由参数
     * @return void
     */
    public function uploadAvatar($params) {
        try {
            $userId = $params['id'];
            
            // 验证权限
            $currentUser = $this->getCurrentUser();
            if (!$currentUser || $currentUser['id'] != $userId) {
                return $this->error('没有权限更新其他用户的头像', 403);
            }
            
            // 验证文件上传
            if (!isset($_FILES['avatar'])) {
                return $this->error('没有上传文件');
            }
            
            $file = $_FILES['avatar'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                return $this->error('文件上传失败');
            }
            
            // 验证文件类型
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($file['type'], $allowedTypes)) {
                return $this->error('只允许上传 JPG、PNG 或 GIF 格式的图片');
            }
            
            // 验证文件大小（最大 2MB）
            if ($file['size'] > 2 * 1024 * 1024) {
                return $this->error('文件大小不能超过 2MB');
            }
            
            // 生成唯一文件名
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $extension;
            
            // 确保上传目录存在
            $uploadDir = __DIR__ . '/../../public/uploads/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // 移动文件到目标位置
            $targetPath = $uploadDir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                return $this->error('文件保存失败');
            }
            
            // 更新数据库中的头像路径
            $avatarPath = '/uploads/' . $filename;
            $success = $this->userModel->updateAvatar($userId, $avatarPath);
            
            if (!$success) {
                // 如果数据库更新失败，删除已上传的文件
                unlink($targetPath);
                return $this->error('更新头像信息失败');
            }
            
            // 获取更新后的用户信息
            $user = $this->userModel->findById($userId);
            unset($user['password']);
            
            return $this->json([
                'message' => '头像上传成功',
                'user' => $user
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    public function refreshToken() {
        try {
            // 获取当前用户信息
            $currentUser = parent::getCurrentUser();
            if (!$currentUser) {
                return $this->error('未登录或token无效', 401);
            }
            
            // 获取用户完整信息
            $user = $this->userModel->findById($currentUser['id']);
            if (!$user) {
                return $this->error('用户不存在', 404);
            }
            
            // 生成新的 token
            $config = require __DIR__ . '/../config/jwt.php';
            $time = time();
            $token = JWT::encode([
                'iss' => $config['issuer'],      // 签发者
                'aud' => $config['audience'],    // 接收者
                'iat' => $time,                  // 签发时间
                'exp' => $time + $config['expire_time'],  // 过期时间
                'uid' => $user['id'],            // 用户ID
                'email' => $user['email']        // 用户邮箱
            ], $config['secret_key'], 'HS256');
            
            // 移除敏感信息
            unset($user['password']);
            
            return $this->json([
                'message' => 'token刷新成功',
                'token' => $token,
                'user' => $user
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 用户注册
     */
    public function register() {
        try {
            $data = $this->getRequestJson();
            
            // 验证必填字段
            if (!isset($data['email']) || !isset($data['password']) || !isset($data['code'])) {
                return $this->json([
                    'code' => 400,
                    'message' => '缺少必要参数'
                ]);
            }
            
            // 验证邮箱格式
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return $this->json([
                    'code' => 400,
                    'message' => '邮箱格式不正确'
                ]);
            }
            
            // 验证密码长度
            if (strlen($data['password']) < 6) {
                return $this->json([
                    'code' => 400,
                    'message' => '密码长度不能小于6位'
                ]);
            }
            
            // 验证验证码
            $verifyCode = new \Models\RegisterVerificationCode();
            $verifyResult = $verifyCode->verify($data['email'], $data['code']);
            
            if (isset($verifyResult['error'])) {
                return $this->json([
                    'code' => 400,
                    'message' => $verifyResult['error']
                ]);
            }
            
            // 检查邮箱是否已注册
            if ($this->userModel->getUserByEmail($data['email'])) {
                return $this->json([
                    'code' => 400,
                    'message' => '该邮箱已注册'
                ]);
            }
            
            // 创建用户
            $result = $this->userModel->create([
                'email' => $data['email'],
                'password' => password_hash($data['password'], PASSWORD_DEFAULT),
                'nickname' => $data['email'],  // 默认使用邮箱作为昵称
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            if ($result) {
                return $this->json([
                    'code' => 200,
                    'data' => [
                        'message' => '注册成功'
                    ]
                ]);
            }
            
            return $this->json([
                'code' => 500,
                'message' => '注册失败'
            ]);
            
        } catch (\Exception $e) {
            error_log("用户注册失败：" . $e->getMessage());
            return $this->json([
                'code' => 500,
                'message' => '注册失败'
            ]);
        }
    }

    public function updateProfile($params) {
        try {
            $userId = $params['id'];
            
            // 验证权限
            $currentUser = $this->getCurrentUser();
            if (!$currentUser || $currentUser['id'] != $userId) {
                return $this->error('没有权限更新其他户的信息', 403);
            }
            
            // 获取请求数据
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                return $this->error('无效的请数据');
            }
            
            // 验证数据
            if (isset($data['nickname']) && strlen($data['nickname']) > 50) {
                return $this->error('昵称长度不能超过50个字符');
            }
            
            if (isset($data['bio']) && strlen($data['bio']) > 200) {
                return $this->error('简介长度不能超过200个字符');
            }
            
            // 更新用户信息
            $success = $this->userModel->update($userId, [
                'nickname' => $data['nickname'] ?? null,
                'bio' => $data['bio'] ?? null
            ]);
            
            if (!$success) {
                return $this->error('更新失败');
            }
            
            // 获取更新后的用户信息
            $user = $this->userModel->findById($userId);
            unset($user['password']); // 移除敏感信息
            
            return $this->json([
                'message' => '更新成功',
                'user' => $user
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 已登录用户修改密码
     */
    public function changePassword() {
        try {
            $data = $this->getRequestJson();
            
            // 验证必填字段
            if (!isset($data['old_password']) || !isset($data['new_password'])) {
                return $this->json([
                    'code' => 400,
                    'message' => '缺少必要参数'
                ]);
            }
            
            // 验证新密码长度
            if (strlen($data['new_password']) < 6) {
                return $this->json([
                    'code' => 400,
                    'message' => '新密码长度不能小于6位'
                ]);
            }
            
            // 获取当前用户ID
            $currentUser = $this->getCurrentUser();
            if (!$currentUser) {
                return $this->json([
                    'code' => 401,
                    'message' => '未登录或token无效'
                ]);
            }
            
            // 验证旧密码
            if (!$this->userModel->verifyPassword($currentUser['id'], $data['old_password'])) {
                return $this->json([
                    'code' => 400,
                    'message' => '原密码错误'
                ]);
            }
            
            // 更新密码
            if ($this->userModel->updatePassword($currentUser['id'], $data['new_password'])) {
                return $this->json([
                    'code' => 200,
                    'message' => '密码修改成功'
                ]);
            }
            
            return $this->json([
                'code' => 500,
                'message' => '密码修改失败'
            ]);
            
        } catch (\Exception $e) {
            error_log("修改密码失败：" . $e->getMessage());
            return $this->json([
                'code' => 500,
                'message' => '修改密码失败'
            ]);
        }
    }

    /**
     * 重置密码
     */
    public function resetPassword() {
        try {
            $data = $this->getRequestJson();
            
            // 验证必填字段
            if (!isset($data['email']) || !isset($data['code']) || !isset($data['new_password'])) {
                return $this->json([
                    'code' => 400,
                    'message' => '缺少必要参数'
                ]);
            }
            
            // 验证新密码长度
            if (strlen($data['new_password']) < 6) {
                return $this->json([
                    'code' => 400,
                    'message' => '新密码长度不能小于6位'
                ]);
            }
            
            // 验证验证码
            $resetCode = new \Models\PasswordResetCode();  // 使用正确的模型名
            if (!$resetCode->verify($data['email'], $data['code'])) {
                return $this->json([
                    'code' => 400,
                    'message' => '验证码无效或已过期'
                ]);
            }
            
            // 获取用户信息
            $user = $this->userModel->getUserByEmail($data['email']);
            if (!$user) {
                return $this->json([
                    'code' => 400,
                    'message' => '用户不存在'
                ]);
            }
            
            // 更新密码
            if ($this->userModel->updatePassword($user['id'], $data['new_password'])) {
                // 标记验证码为已使用
                $resetCode->markAsUsed($data['email'], $data['code']);
                
                return $this->json([
                    'code' => 200,
                    'message' => '密码重置成功'
                ]);
            }
            
            return $this->json([
                'code' => 500,
                'message' => '密码重置失败'
            ]);
            
        } catch (\Exception $e) {
            error_log("重置密码失败：" . $e->getMessage());
            return $this->json([
                'code' => 500,
                'message' => '重置密码失败'
            ]);
        }
    }

    /**
     * 获取当前登录用户ID
     */
    protected function getCurrentUserId() {
        return $_SERVER['USER_ID'] ?? null;
    }
} 