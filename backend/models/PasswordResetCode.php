<?php
/**
 * 密码重置验证码模型
 * 
 * 该类负责处理密码重置验证码的所有数据库操作，包括：
 * 1. 创建密码重置验证码
 * 2. 验证码的查询和验证
 * 3. 更新验证码状态
 * 4. 记录重置验证码的使用情况
 * 5. 防止重置密码的滥用
 * 
 * @package Models
 * @author ManShan Space
 * @version 1.0
 */
namespace Models;

use Core\Database;
use Core\VerificationCode;

class PasswordResetCode {
    /** @var Database 数据库实例 */
    private $db;
    
    /** @var string 数据表名 */
    private $table = 'password_reset_codes';
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * 创建密码重置验证码
     * 
     * @param string $email 邮箱地址
     * @param string $ipAddress IP地址
     * @param string $userAgent 用户代理信息
     * @return array|bool 成功返回验证码信息，失败返回false
     */
    public function create($email, $ipAddress, $userAgent) {
        try {
            // 检查是否存在未过期的验证码
            if ($this->hasActiveCode($email)) {
                return ['error' => '已存在有效的验证码，请稍后再试'];
            }
            
            // 生成新验证码
            $datetime = new \DateTime('now', new \DateTimeZone('Asia/Shanghai'));
            $datetime->modify('+10 minutes');
            
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            
            $sql = "INSERT INTO {$this->table} 
                    (email, code, expires_at, ip_address, user_agent) 
                    VALUES 
                    (:email, :code, :expires_at, :ip_address, :user_agent)";
                    
            $this->db->query($sql);
            $this->db->bind(':email', $email);
            $this->db->bind(':code', $code);
            $this->db->bind(':expires_at', $datetime->format('Y-m-d H:i:s'));
            $this->db->bind(':ip_address', $ipAddress);
            $this->db->bind(':user_agent', $userAgent);
            
            if ($this->db->execute()) {
                return [
                    'id' => $this->db->lastInsertId(),
                    'email' => $email,
                    'code' => $code,
                    'expires_at' => $datetime->format('Y-m-d H:i:s')
                ];
            }
            
            return false;
            
        } catch (\Exception $e) {
            error_log("创建密码重置验证码失败：" . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 检查是否存在有效的验证码
     * 
     * @param string $email 邮箱地址
     * @return bool 是否存在有效验证码
     */
    private function hasActiveCode($email) {
        $sql = "SELECT COUNT(*) as count 
                FROM {$this->table} 
                WHERE email = :email 
                AND status = 'pending' 
                AND expires_at > CURRENT_TIMESTAMP";
                
        $this->db->query($sql);
        $this->db->bind(':email', $email);
        $result = $this->db->single();
        
        return $result['count'] > 0;
    }
    
    /**
     * 标记验证码为已发送
     * 
     * @param int $id 验证码ID
     * @return bool 是否更新成功
     */
    public function markAsSent($id) {
        $sql = "UPDATE {$this->table} 
                SET sent_at = CURRENT_TIMESTAMP 
                WHERE id = :id";
                
        $this->db->query($sql);
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }
    
    /**
     * 验证重置密码的验证码
     * 
     * @param string $email 邮箱地址
     * @param string $code 验证码
     * @param string $ipAddress IP地址
     * @return array|bool 验证成功返回true，失败返回错误信息
     */
    public function verifyCode($email, $code, $ipAddress) {
        try {
            // 更新过期状态
            $this->updateExpiredCodes();
            
            // 查找有效的验证码
            $sql = "SELECT * FROM {$this->table} 
                   WHERE email = :email 
                   AND status = 'pending'
                   AND attempts < :max_attempts 
                   ORDER BY created_at DESC 
                   LIMIT 1";
            
            $this->db->query($sql);
            $this->db->bind(':email', $email);
            $this->db->bind(':max_attempts', VerificationCode::MAX_ATTEMPTS);
            
            $record = $this->db->single();
            
            if (!$record) {
                return ['error' => '验证码不存在或已失效'];
            }
            
            // 更新尝试信息
            $this->updateAttempt($record['id'], $ipAddress);
            
            // 验证码比对
            if ($record['code'] === $code) {
                $this->markAsUsed($record['id']);
                return true;
            }
            
            return ['error' => '验证码错误'];
            
        } catch (\Exception $e) {
            error_log("密码重置验证码验证失败：" . $e->getMessage());
            return ['error' => '验证码验证失败'];
        }
    }
    
    /**
     * 更新过期的验证码状态
     * 
     * @return void
     */
    private function updateExpiredCodes() {
        $sql = "UPDATE {$this->table} 
                SET status = 'expired' 
                WHERE status = 'pending' 
                AND expires_at < CURRENT_TIMESTAMP";
        
        $this->db->query($sql);
        $this->db->execute();
    }
    
    /**
     * 更新验证尝试信息
     * 
     * @param int $id 验证码ID
     * @param string $ipAddress IP地址
     * @return void
     */
    private function updateAttempt($id, $ipAddress) {
        $sql = "UPDATE {$this->table} 
                SET attempts = attempts + 1,
                    last_attempt_at = CURRENT_TIMESTAMP,
                    ip_address = :ip_address
                WHERE id = :id";
                
        $this->db->query($sql);
        $this->db->bind(':id', $id);
        $this->db->bind(':ip_address', $ipAddress);
        $this->db->execute();
    }
    
    /**
     * 标记验证码为已使用
     */
    public function markAsUsed($email, $code) {
        $this->db->query("UPDATE password_reset_codes 
                         SET status = 'used', 
                             used_at = NOW() 
                         WHERE email = :email 
                         AND code = :code 
                         AND status = 'pending'");
        
        $this->db->bind(':email', $email);
        $this->db->bind(':code', $code);
        
        return $this->db->execute();
    }
    
    /**
     * 验证重置密码验证码
     */
    public function verify($email, $code) {
        $this->db->query("SELECT * FROM password_reset_codes 
                         WHERE email = :email 
                         AND code = :code 
                         AND status = 'pending' 
                         AND expires_at > NOW()");
        
        $this->db->bind(':email', $email);
        $this->db->bind(':code', $code);
        
        return $this->db->single() !== false;
    }
} 