<?php
// test_db.php
require_once __DIR__ . '/backend/core/Database.php';

try {
    // 获取数据库实例
    $db = Core\Database::getInstance();
    
    // 测试连接
    $db->query("SELECT 1");
    if($db->execute()) {
        echo "✅ 数据库连接成功!\n";
        
        // 显示所有数据表
        $db->query("SHOW TABLES");
        $tables = $db->resultSet();
        
        echo "\n现有数据表:\n";
        foreach($tables as $table) {
            echo "- " . current($table) . "\n";
        }
    }
} catch (\Exception $e) {
    echo "❌ 数据库连接失败: " . $e->getMessage() . "\n";
}