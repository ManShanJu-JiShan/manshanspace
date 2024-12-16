<?php
namespace Models;

use Core\Database;

class UserModel {
    private $db;
    private $table = 'users';
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function create($data) {
        $sql = "INSERT INTO {$this->table} (email, password, nickname, created_at, updated_at) 
                VALUES (:email, :password, :nickname, :created_at, :updated_at)";
                
        try {
            $this->db->query($sql);
            $this->db->bind(':email', $data['email']);
            $this->db->bind(':password', $data['password']);
            $this->db->bind(':nickname', $data['nickname']);
            $this->db->bind(':created_at', $data['created_at']);
            $this->db->bind(':updated_at', $data['updated_at']);
            
            if ($this->db->execute()) {
                return $this->db->lastInsertId();
            }
            return false;
        } catch (\PDOException $e) {
            throw new \Exception("创建用户失败: " . $e->getMessage());
        }
    }
    
    public function findById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $this->db->query($sql);
        $this->db->bind(':id', $id);
        return $this->db->single();
    }
    
    public function getUserByEmail($email) {
        $sql = "SELECT * FROM {$this->table} WHERE email = :email";
        $this->db->query($sql);
        $this->db->bind(':email', $email);
        return $this->db->single();
    }
    
    /**
     * 更新用户信息
     * @param int $id 用户ID
     * @param array $data 要更新的数据
     * @return bool 更新是否成功
     */
    public function updateUser($id, $data) {
        // 构建更新字段
        $updateFields = [];
        $params = [];
        
        // 只允许更新特定字段
        $allowedFields = ['nickname', 'bio', 'avatar'];
        
        // 遍历数据，只处理允许更新的字段
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updateFields[] = "{$key} = :{$key}";
                $params[$key] = $value;
            }
        }
        
        // 如果没有可更新的字段，返回 false
        if (empty($updateFields)) {
            return false;
        }
        
        // 添加用户ID到参数中
        $params['id'] = $id;
        
        // 构建 SQL 语句
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = :id";
        
        // 执行更新
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * 更新用户密码
     * @param int $id 用户ID
     * @param string $newPassword 新密码
     * @return bool 更新是否成功
     */
    public function updatePassword($id, $newPassword) {
        $this->db->query("UPDATE users SET password = :password WHERE id = :id");
        
        $this->db->bind(':password', password_hash($newPassword, PASSWORD_DEFAULT));
        $this->db->bind(':id', $id);
        
        return $this->db->execute();
    }
    
    /**
     * 验证用户密码
     * @param int $id 用户ID
     * @param string $password 待验证的密码
     * @return bool 密码是否正确
     */
    public function verifyPassword($id, $password) {
        $this->db->query("SELECT password FROM users WHERE id = :id");
        $this->db->bind(':id', $id);
        $user = $this->db->single();
        
        if (!$user) {
            return false;
        }
        
        return password_verify($password, $user['password']);
    }
    
    public function update($id, $data) {
        $fields = [];
        $values = [];
        
        // 只更新允许的字段
        $allowedFields = ['nickname', 'bio'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = :{$field}";
                $values[":{$field}"] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        // 添加更新时间
        $fields[] = "updated_at = :updated_at";
        $values[":updated_at"] = date('Y-m-d H:i:s');
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $values[':id'] = $id;
        
        try {
            $this->db->query($sql);
            foreach ($values as $key => $value) {
                $this->db->bind($key, $value);
            }
            return $this->db->execute();
        } catch (\PDOException $e) {
            throw new \Exception("更新用户信息失败: " . $e->getMessage());
        }
    }
    
    public function updateAvatar($userId, $avatarPath) {
        $sql = "UPDATE {$this->table} SET avatar = :avatar, updated_at = :updated_at WHERE id = :id";
        
        try {
            $this->db->query($sql);
            $this->db->bind(':avatar', $avatarPath);
            $this->db->bind(':updated_at', date('Y-m-d H:i:s'));
            $this->db->bind(':id', $userId);
            
            return $this->db->execute();
        } catch (\PDOException $e) {
            throw new \Exception("更新头像失败: " . $e->getMessage());
        }
    }
} 