<?php

require_once 'repetitivefunctions.php';
require_once 'constants.php';

use Predis\Client as PredisClient;

class AuthMiddleware {
    private RepetitiveFunctions $repetitiveFunctions;
    private PredisClient $redisClient;

    public function __construct(RepetitiveFunctions $repetitiveFunctions, PredisClient $redisClient) {
        $this->repetitiveFunctions = $repetitiveFunctions;
        $this->redisClient = $redisClient;
    }
    public function getCurrentUserId() {
        return $_SESSION['userId'] ?? null;
    }

    public function isAuthenticated() {
        return isset($_SESSION['userId']);
    }

    // Token doğrulama
    public function authenticateRequest() {
        $headers = apache_request_headers();
        if (!isset($headers['Authorization'])) {
            $this->sendUnauthorizedResponse('Authorization token missing');
            return;
        }

        $token = $headers['Authorization'];
        $userId = $this->getUserIdFromToken($token);
        
        if (!$userId) {
            $this->sendUnauthorizedResponse('Invalid token');
            return;
        }

        // Kullanıcı ID'sini oturumda sakla
        $_SESSION['userId'] = $userId;
    }

    // Token'dan kullanıcı ID'si almak için
    private function getUserIdFromToken(string $token): ?int {
        $userId = $this->redisClient->get(ConstantsClass::TOKEN_PREFIX . $token);
        return $userId ? (int)$userId : null;
    }

    // Yetkisiz erişim için yanıt gönder
    private function sendUnauthorizedResponse(string $message) {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['status' => false, 'message' => $message]);
        exit();
    }
}
