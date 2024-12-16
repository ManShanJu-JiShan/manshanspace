<?php
namespace Controllers;

class TestController extends BaseController {
    public function index() {
        $this->json([
            'message' => '接口测试成功',
            'time' => date('Y-m-d H:i:s')
        ]);
    }
} 