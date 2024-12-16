<?php
// 数据库配置文件
return [
    // 远程数据库配置
    'host' => '124.221.84.145',
    'dbname' => 'manshan_space',
    'username' => 'manshan_space',
    'password' => '1d9b405f43fbcf66',  // 远程数据库密码

    // 本地数据库配置
    // 'host' => 'localhost',
    // 'dbname' => 'manshan_space',
    // 'username' => 'root',
    // 'password' => 'root',  // MAMP默认密码

    'charset' => 'utf8mb4',
    'port' => 3306,
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00'"
    ]
]; 