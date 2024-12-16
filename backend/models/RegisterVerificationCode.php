<?php
/**
 * 注册验证码模型
 * 
 * 该类负责处理注册验证码的所有数据库操作，包括：
 * 1. 创建新的验证码记录
 * 2. 验证码的查询和验证
 * 3. 更新验证码状态（使用、过期等）
 * 4. 记录验证码使用情况（发送时间、使用时间、尝试次数等）
 * 
 * @package Models
 * @author ManShan Space
 * @version 1.0
 */
namespace Models;

use Core\Database;
use Core\VerificationCode;

class RegisterVerificationCode {
    /** @var Database 数据库实例 */
    private $db;
    
    /** @var string 数据表名 */
    private $table = 'register_verification_codes';
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * 创建新的验证码记录
     * 
     * @param string $email 邮箱地址
     * @param string $ipAddress IP地址
     * @param string $userAgent 用户代理信息
     * @return array|bool 成功返回验证码信息，失败返回false
     */
    public function create($email, $ipAddress, $userAgent) {
        try {
            // 生成验证码
            $codeInfo = VerificationCode::generateRegisterCode($email, $ipAddress, $userAgent);
            
            // 插入数据库
            $sql = "INSERT INTO {$this->table} 
                    (email, code, expires_at, ip_address, user_agent) 
                    VALUES 
                    (:email, :code, :expires_at, :ip_address, :user_agent)";
                    
            $this->db->query($sql);
            $this->db->bind(':email', $codeInfo['email']);
            $this->db->bind(':code', $codeInfo['code']);
            $this->db->bind(':expires_at', $codeInfo['expires_at']);
            $this->db->bind(':ip_address', $codeInfo['ip_address']);
            $this->db->bind(':user_agent', $codeInfo['user_agent']);
            
            if ($this->db->execute()) {
                // 添加ID到返回信息中
                $codeInfo['id'] = $this->db->lastInsertId();
                return $codeInfo;
            }
            
            return false;
            
        } catch (\Exception $e) {
            error_log("创建验证码失败：" . $e->getMessage());
            return false;
        }
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
     * 验证码比对
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
            error_log("验证码验证失败：" . $e->getMessage());
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
     * 
     * @param int $id 验证码ID
     * @return void
     */
    private function markAsUsed($id) {
        $sql = "UPDATE {$this->table} 
                SET status = 'used',
                    used_at = CURRENT_TIMESTAMP 
                WHERE id = :id";
                
        $this->db->query($sql);
        $this->db->bind(':id', $id);
        $this->db->execute();
    }
    
    /**
     * 验证注册验证码
     */
    public function verify($email, $code) {
        try {
            // 查找最新的未使用验证码
            $sql = "SELECT * FROM {$this->table} 
                    WHERE email = :email 
                    AND code = :code 
                    AND status = 'pending'
                    AND expires_at > NOW()
                    ORDER BY created_at DESC 
                    LIMIT 1";
                    
            $this->db->query($sql);
            $this->db->bind(':email', $email);
            $this->db->bind(':code', $code);
            
            $record = $this->db->single();
            
            if (!$record) {
                return ['error' => '验证码无效或已过期'];
            }
            
            // 更新验证码状态
            $sql = "UPDATE {$this->table} 
                    SET status = 'used', 
                        used_at = NOW() 
                    WHERE id = :id";
                    
            $this->db->query($sql);
            $this->db->bind(':id', $record['id']);
            
            if ($this->db->execute()) {
                return true;
            }
            
            return ['error' => '验证码验证失败'];
            
        } catch (\Exception $e) {
            error_log("注册验证码验证失败：" . $e->getMessage());
            return ['error' => '验证码验证失败'];
        }
    }
} 