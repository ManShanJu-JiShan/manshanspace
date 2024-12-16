<?php
namespace Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mailer;
    
    public function __construct() {
        $config = require __DIR__ . '/../config/mail.php';
        
        $this->mailer = new PHPMailer(true);
        
        // 服务器设置
        $this->mailer->isSMTP();
        $this->mailer->Host = $config['host'];
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $config['username'];
        $this->mailer->Password = $config['password'];
        $this->mailer->SMTPSecure = $config['encryption'];
        $this->mailer->Port = $config['port'];
        
        // 发件人
        $this->mailer->setFrom($config['username'], $config['from_name']);
        
        // 字符集
        $this->mailer->CharSet = 'UTF-8';
        
        // 开启调试
        $this->mailer->SMTPDebug = 2;  // 开发时使用，生产环境应该设为0
    }
    
    public function send($to, $subject, $body) {
        try {
            $this->mailer->addAddress($to);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            
            return $this->mailer->send();
        } catch (Exception $e) {
            throw new \Exception('邮件发送失败：' . $this->mailer->ErrorInfo);
        }
    }
}