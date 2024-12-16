<?php
namespace Controllers;

use Core\BaseController;
use Core\Mailer;

class EmailController extends BaseController {
    private $mailer;
    
    public function __construct() {
        $this->mailer = new Mailer();
    }
    
    public function send() {
        try {
            // 添加调试信息
            error_log("开始处理邮件发送请求");
            
            $data = json_decode(file_get_contents('php://input'), true);
            error_log("接收到的数据: " . print_r($data, true));
            
            if (!isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                error_log("邮箱地址无效: " . ($data['email'] ?? 'undefined'));
                return $this->error('请提供有效的邮箱地址');
            }
            
            $to = $data['email'];
            $subject = 'ManShan Space 测试邮件';
            $body = '<h1>欢迎来到 ManShan Space</h1><p>这是一封测试邮件。</p>';
            
            error_log("准备发送邮件到: " . $to);
            $this->mailer->send($to, $subject, $body);
            
            error_log("邮件发送成功");
            return $this->json([
                'message' => '邮件发送成功'
            ]);
            
        } catch (\Exception $e) {
            error_log("邮件发送失败: " . $e->getMessage());
            return $this->error($e->getMessage());
        }
    }
}