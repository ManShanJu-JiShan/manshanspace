<?php
namespace Core;

class VerificationCode {
    // 验证码配置
    const LENGTH = 6;                    // 验证码长度
    const EXPIRE_MINUTES = 10;           // 有效期（分钟）
    const MAX_ATTEMPTS = 3;              // 最大尝试次数
    const RESEND_INTERVAL = 60;          // 重发间隔（秒）
    
    /**
     * 生成注册验证码
     * @param string $email 邮箱
     * @param string $ipAddress IP地址
     * @param string $userAgent 用户代理
     * @return array 验证码信息
     */
    public static function generateRegisterCode($email, $ipAddress, $userAgent) {
        $datetime = new \DateTime('now', new \DateTimeZone('Asia/Shanghai'));
        $datetime->modify('+10 minutes');
        
        return [
            'email' => $email,
            'code' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'expires_at' => $datetime->format('Y-m-d H:i:s'),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ];
    }
    
    /**
     * 生成密码重置验证码
     * @param string $email 邮箱
     * @param string $ipAddress IP地址
     * @param string $userAgent 用户代理
     * @return array 验证码信息
     */
    public static function generatePasswordResetCode($email, $ipAddress, $userAgent) {
        $datetime = new \DateTime('now', new \DateTimeZone('Asia/Shanghai'));
        $datetime->modify('+10 minutes');
        
        return [
            'email' => $email,
            'code' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'expires_at' => $datetime->format('Y-m-d H:i:s'),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ];
    }
} 