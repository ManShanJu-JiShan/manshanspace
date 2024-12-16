<?php
namespace Controllers;

class BaseController {
    protected $db;
    
    public function __construct() {
        $this->db = \Core\Database::getInstance()->getConnection();
    }
    
    protected function json($data, $code = 200) {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode([
            'code' => $code,
            'data' => $data
        ]);
        exit;
    }
    
    protected function error($message, $code = 400) {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode([
            'code' => $code,
            'error' => $message
        ]);
        exit;
    }
}