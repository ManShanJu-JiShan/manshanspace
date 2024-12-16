<?php
return [
    // JWT 密钥，用于签名令牌（在实际项目中应该使用更复杂的密钥）
    'secret_key' => 'your-secret-key-manshan-space',
    
    // 访问令牌过期时间（改为 2 分钟）
    'expire_time' => 12000,
    
    // 令牌签发者
    'issuer' => 'ManShanSpace',
    
    // 令牌接收者
    'audience' => 'ManShanSpaceUsers',
    
    // 允许提前刷新的时间（30秒）
    'refresh_ttl' => 30
]; 