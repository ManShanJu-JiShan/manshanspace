<?php
/**
 * 验证码控制器
 * 
 * 处理所有验证码相关的请求，包括：
 * 1. 发送注册验证码
 * 2. 发送密码重置验证码
 * 3. 验证码状态查询
 * 4. 错误处理和响应
 * 
 * @package Controllers
 * @author ManShan Space
 * @version 1.0
 */
namespace Controllers;

use Core\BaseController;
use Core\Mailer;
use Models\RegisterVerificationCode;
use Models\PasswordResetCode;

class VerificationController extends BaseController {
    /** @var Mailer 邮件发送实例 */
    private $mailer;
    
    public function __construct() {
        $this->mailer = new Mailer();
    }
    
    /**
     * 发送验证码
     * 支持注册和密码重置两种类型
     * 
     * @return void
     */
    public function sendCode() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            // 验证请求数据
            if (!isset($data['email']) || !isset($data['type'])) {
                return $this->error('邮箱地址和验证码类型不能为空');
            }
            
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return $this->error('邮箱地址格式不正确');
            }
            
            $email = $data['email'];
            $type = $data['type'];
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'];
            
            // 根据类型处理验证码
            switch ($type) {
                case 'register':
                    return $this->handleRegisterCode($email, $ipAddress, $userAgent);
                    
                case 'reset_password':
                    return $this->handlePasswordResetCode($email, $ipAddress, $userAgent);
                    
                default:
                    return $this->error('不支持的验证码类型');
            }
            
        } catch (\Exception $e) {
            error_log("发送验证码失败：" . $e->getMessage());
            return $this->error('发送验证码失败');
        }
    }
    
    /**
     * 处理注册验证码
     * 
     * @param string $email 邮箱地址
     * @param string $ipAddress IP地址
     * @param string $userAgent 用户代理
     * @return void
     */
    private function handleRegisterCode($email, $ipAddress, $userAgent) {
        $model = new RegisterVerificationCode();
        
        // 创建验证码
        $codeInfo = $model->create($email, $ipAddress, $userAgent);
        
        if (!$codeInfo || isset($codeInfo['error'])) {
            return $this->error($codeInfo['error'] ?? '创建验证码失败');
        }
        
        // 发送邮件
        $subject = 'ManShan Space - 注册验证码';
        $body = $this->getRegisterEmailTemplate($codeInfo['code']);
        
        try {
            $this->mailer->send($email, $subject, $body);
            $model->markAsSent($codeInfo['id']);
            
            return $this->json([
                'message' => '验证码已发送到您的邮箱'
            ]);
            
        } catch (\Exception $e) {
            error_log("发送注册验证码邮件失败：" . $e->getMessage());
            return $this->error('发送验证码失败，请稍后重试');
        }
    }
    
    /**
     * 处理密码重置验证码
     * 
     * @param string $email 邮箱地址
     * @param string $ipAddress IP地址
     * @param string $userAgent 用户代理
     * @return void
     */
    private function handlePasswordResetCode($email, $ipAddress, $userAgent) {
        $model = new PasswordResetCode();
        
        // 创建验证码
        $codeInfo = $model->create($email, $ipAddress, $userAgent);
        
        if (!$codeInfo || isset($codeInfo['error'])) {
            return $this->error($codeInfo['error'] ?? '创建验证码失败');
        }
        
        // 发送邮件
        $subject = 'ManShan Space - 密码重置验证码';
        $body = $this->getPasswordResetEmailTemplate($codeInfo['code']);
        
        try {
            $this->mailer->send($email, $subject, $body);
            $model->markAsSent($codeInfo['id']);
            
            return $this->json([
                'message' => '验证码已发送到您的邮箱'
            ]);
            
        } catch (\Exception $e) {
            error_log("发送密码重置验证码邮件失败：" . $e->getMessage());
            return $this->error('发送验证码失败，请稍后重试');
        }
    }
    
    /**
     * 获取注册邮件模板
     * 
     * @param string $code 验证码
     * @return string
     */
    private function getRegisterEmailTemplate($code) {
        return "<h1>欢迎注册 ManShan Space</h1>
                <p>您的注册验证码是：<strong>{$code}</strong></p>
                <p>验证码有效期为10分钟，请尽快使用。</p>
                <p>如果这不是您的操作，请忽略此邮件。</p>";
    }
    
    /**
     * 获取密码重置邮件模板
     * 
     * @param string $code 验证码
     * @return string
     */
    private function getPasswordResetEmailTemplate($code) {
        return "<h1>ManShan Space 密码重置</h1>
                <p>您的密码重置验证码是：<strong>{$code}</strong></p>
                <p>验证码有效期为10分钟，请尽快使用。</p>
                <p>如果这不是您的操作，请立即检查账号安全。</p>";
    }
    
    /**
     * 验证验证码
     */
    public function checkCode() {
        try {
            $data = $this->getRequestJson();
            
            // 验证必填字段
            if (!isset($data['email']) || !isset($data['code']) || !isset($data['type'])) {
                return $this->json([
                    'code' => 400,
                    'message' => '缺少必要参数'
                ]);
            }
            
            // 根据类型选择验证码模型
            $model = $this->getVerificationModel($data['type']);
            if (!$model) {
                return $this->json([
                    'code' => 400,
                    'message' => '无效的验证类型'
                ]);
            }
            
            // 验证验证码
            $result = $model->verify($data['email'], $data['code']);
            
            if (isset($result['error'])) {
                return $this->json([
                    'code' => 400,
                    'message' => $result['error']
                ]);
            }
            
            return $this->json([
                'code' => 200,
                'data' => [
                    'message' => '验证成功'
                ]
            ]);
            
        } catch (\Exception $e) {
            error_log("验证码验证失败：" . $e->getMessage());
            return $this->json([
                'code' => 500,
                'message' => '验证码验证失败'
            ]);
        }
    }
    
    /**
     * 根据类型获取验证码模型
     */
    private function getVerificationModel($type) {
        switch ($type) {
            case 'register':
                return new RegisterVerificationCode();
            case 'reset_password':
                return new PasswordResetCode();
            default:
                return null;
        }
    }
} 