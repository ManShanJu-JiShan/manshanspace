<?php
namespace Core;

use PDO;
use PDOException;

class Database {
    private static $instance = null;
    private $host = '124.221.84.145';
    private $db_name = 'manshan_space';
    private $username = 'manshan_space';
    private $password = '1d9b405f43fbcf66';
    private $conn;
    private $stmt;
    
    public function __construct() {
        // 数据库连接配置
        $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->db_name;
        $options = array(
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        );
        
        try {
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch(PDOException $e) {
            throw new \Exception("连接数据库失败: " . $e->getMessage());
        }
    }
    
    // 获取实例（单例模式）
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // 预处理语句
    public function query($sql) {
        $this->stmt = $this->conn->prepare($sql);
    }
    
    // 绑定参数
    public function bind($param, $value, $type = null) {
        if (is_null($type)) {
            switch (true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }
        $this->stmt->bindValue($param, $value, $type);
    }
    
    // 执行预处理语句
    public function execute() {
        return $this->stmt->execute();
    }
    
    // 获取单条记录
    public function single() {
        $this->execute();
        return $this->stmt->fetch();
    }
    
    // 获取所有记录
    public function resultSet() {
        $this->execute();
        return $this->stmt->fetchAll();
    }
    
    // 获取记录数
    public function rowCount() {
        return $this->stmt->rowCount();
    }
    
    // 获取最后插入的ID
    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }
} 
