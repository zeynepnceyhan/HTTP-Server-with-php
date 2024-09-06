<?php

require 'vendor/autoload.php';
require_once 'constants.php';

use Predis\Client;

class RepetitiveFunctions {
    private Client $redis;

    // Constructor
    public function __construct() {
        $this->redis = $this->initRedis();
    }

    // Response success
    public function responseSuccess($data = null) : array {
        return [
            'status' => true,
            'data' => $data
        ];
    }
    
    // Response error
    public function responseError($message): array{
        header('Content-Type: application/json');
        echo json_encode([
            'status' => false,
            'message' => $message
        ]);
        $this->logError($message); // Log error message
        exit(); // Ensure no further processing happens
    }

    // Initialize Redis
    private function initRedis() {
        try {
            return new Client([
                'scheme' => 'tcp',
                'host'   => 'host.docker.internal',
                'port'   => 6379,
            ]);
        } catch (Exception $e) {
            $this->responseError("Failed to connect to Redis: " . $e->getMessage());
        }
    }

    // Check if the username exists
    public function checkUsername(string $username): array {
        if (!$this->redis) {
            return ['status' => false, 'message' => "Redis client is not initialized"];
        }
        if ($this->redis->exists(ConstantsClass::USERNAME_PREFIX . $username)) {
            return ['status' => false, 'message' => "Username already exists"];
        }
        
        return ['status' => true];
    }

    // Get Redis client
    public function getRedis(): Client {
        return $this->redis;
    }
    
    // Get user by ID
    public function getUserByID(int $id): array {
    if (!$this->redis) {
        return $this->responseError("Redis client is not initialized");
    }
    try {
        $userJson = $this->redis->get(ConstantsClass::USER_PREFIX . $id);
        if ($userJson === false) {
            return $this->responseError("User not found");
        }

        $user = json_decode($userJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Error decoding JSON: ' . json_last_error_msg());
        }

        return $this->responseSuccess($user);

    } catch (Exception $e) {
        $this->logError('Error fetching user by ID: ' . $e->getMessage());
        return $this->responseError($e->getMessage());
    }
}
    
    // Save user to Redis
    public function saveUser(array $user) {
        try {
            if (!$this->redis) {
                throw new Exception("Redis client is not initialized");
            }
            $userJson = json_encode($user);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Error encoding JSON: ' . json_last_error_msg());
            }
            $this->redis->set(ConstantsClass::USER_PREFIX . $user['id'], $userJson);
        } catch (Exception $e) {
            $this->logError('Error saving user: ' . $e->getMessage());
            return $this->responseError($e->getMessage());
        }
    }

    // Generate a random string
    public function generateRandomString($length = 10): string {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    // Generate a random name
    public function generateRandomName(): string {
        // Example list of names
        $names = ['John', 'Jane', 'Alex', 'Emily', 'Michael', 'Sarah'];
        return $names[array_rand($names)];
    }

    // Generate a random surname
    public function generateRandomSurname(): string {
        // Example list of surnames
        $surnames = ['Smith', 'Johnson', 'Williams', 'Jones', 'Brown', 'Davis'];
        return $surnames[array_rand($surnames)];
    }

    // Log error message to file
    private function logError(string $message) {
        file_put_contents('error_log.txt', date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
    }

    // Handle and log error message
    public function handleLogError($message) {
        $this->logError($message);
    }
}
