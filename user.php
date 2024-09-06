<?php

require 'vendor/autoload.php';
require_once 'constants.php';
require_once 'repetitivefunctions.php';
require_once 'leaderboard.php';

use Predis\Client as PredisClient;

class UserClass {
    private int $id;
    private string $username;
    private string $name;
    private string $password;
    private string $surname;
    private PredisClient $redisClient;
    private RepetitiveFunctions $repetitiveFunctions;
    private Leaderboard $leaderboard;

    public function __construct(PredisClient $redisClient, RepetitiveFunctions $repetitiveFunctions, Leaderboard $leaderboard, string $username = '', string $name = '', string $password = '', string $surname = '') {
        $this->redisClient = $redisClient;
        $this->repetitiveFunctions = $repetitiveFunctions;
        $this->leaderboard = $leaderboard;
        $this->setUsername($username);
        $this->setName($name);
        $this->setPassword($password);
        $this->setSurname($surname);
    }

    // Shortcuts for response error
    private function error(string $message): array {
        $this->repetitiveFunctions->handleLogError("Error: $message");
        return $this->repetitiveFunctions->responseError($message);
    }

    // Shortcuts for response success
    private function success(array $data): array {
        $this->repetitiveFunctions->handleLogError("Success: " . json_encode($data));
        return $this->repetitiveFunctions->responseSuccess($data);
    }

    // Shortcuts to access constants
    private function const(string $name) {
        return constant("ConstantsClass::$name");
    }

    // Getter and Setter for id
    function getId(): int {
        return $this->id;
    }

    function setId(int $id): void {
        $this->id = $id;
    }

    // Getter and Setter for username
    function getUsername(): string {
        return $this->username;
    }

    function setUsername(string $username): void {
        $this->username = $username;
    }

    // Getter and Setter for name
    function getName(): string {
        return $this->name;
    }

    function setName(string $name): void {
        $this->name = $name;
    }

    // Getter and Setter for password
    function getPassword(): string {
        return $this->password;
    }

    function setPassword(string $password): void {
        $this->password = $this->hashPass($password);
    }

    // Getter and Setter for surname
    function getSurname(): string {
        return $this->surname;
    }

    function setSurname(string $surname): void {
        $this->surname = $surname;
    }

    // Hash password
    private function hashPass(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    // Verify password
    private function verifyPass(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
    
    // Register user
    public function register(string $username, string $password, string $name, string $surname): array {
        try {
            $this->repetitiveFunctions->handleLogError("Starting registration process");

            if (!$this->redisClient) {
                throw new Exception("Redis client is not initialized");
            }

            $checkUsernameResponse = $this->repetitiveFunctions->checkUsername($username);
            if (!$checkUsernameResponse['status']) {
                return $this->error($checkUsernameResponse['message']);
            }

            $this->setUsername($username);
            $this->setName($name);
            $this->setPassword($password); // Ensure password is hashed here
            $this->setSurname($surname);
            $this->repetitiveFunctions->handleLogError("register process1");


            $nextUserId = $this->redisClient->incr($this->const('NEXT_USER_ID'));
            $this->setId($nextUserId);
            $this->repetitiveFunctions->handleLogError("register process2");

            $userJson = json_encode([
                'id' => $this->getId(),
                'username' => $this->getUsername(),
                'name' => $this->getName(),
                'surname' => $this->getSurname(),
                'password' => $this->getPassword()
            ]);
            $this->repetitiveFunctions->handleLogError("register process3");

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Error encoding user data: ' . json_last_error_msg());
            }
            $this->repetitiveFunctions->handleLogError("register process4");

            $this->redisClient->set($this->const('USER_PREFIX') . $this->getId(), $userJson);
            $this->redisClient->set($this->const('USERNAME_PREFIX') . $username, $this->getId());
            $this->repetitiveFunctions->handleLogError("register process5");

            return $this->success([
                'userId' => $this->getId(),
                'username' => $this->getUsername(),
                'name' => $this->getName(),
                'surname' => $this->getSurname()
            ]);

        } catch (Exception $e) {
            error_log('Error during registration: ' . $e->getMessage());
            return $this->error($e->getMessage());
        }
    }
    
    // Login user
    public function login(string $username, string $password) {
        try {
            $this->repetitiveFunctions->handleLogError("Starting login process");
    
            if (!$this->redisClient) {
                throw new Exception("Redis client is not initialized");
            }
    
            $userId = $this->redisClient->get($this->const('USERNAME_PREFIX') . $username);
            if (!$userId) {
                return $this->repetitiveFunctions->handleLogError("User not found!");
            }
    
            $userJson = $this->redisClient->get($this->const('USER_PREFIX') . $userId);
            if (!$userJson) {
                return $this->repetitiveFunctions->handleLogError("User data not found!");

            }
    
            $user = json_decode($userJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Error decoding JSON: ' . json_last_error_msg());
            }
    
            if (!isset($user['password']) || !$this->verifyPass($password, $user['password'])) {
                return $this->repetitiveFunctions->handleLogError("Invalid password!");
            }
    
            // Remove password from the user object
            unset($user['password']);
    
            // Generate and save token
            $token = bin2hex(random_bytes(16));
            $this->redisClient->set($this->const('TOKEN_PREFIX') . $token, $userId);
    
            return $this->success(['user' => $user, 'token' => $token]);
        } catch (Exception $e) {
            error_log('Error during login: ' . $e->getMessage());
            return $this->repetitiveFunctions->responseError($e->getMessage());
        }
    }
    
    // Get user details
    public function userDetails(int $id) {
        try {
            $this->repetitiveFunctions->handleLogError("Fetching user details for ID: $id");

            $userJson = $this->redisClient->get($this->const('USER_PREFIX') . $id);
            if (!$userJson) {
                return $this->repetitiveFunctions->responseError('User not found!');
            }

            $user = json_decode($userJson, true);
            $user['password'] = "";

            $this->repetitiveFunctions->handleLogError("User details fetched successfully");

            return $this->success($user);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    // Update user
    public function updateUser(int $userId, array $data) {
        try {
            $this->repetitiveFunctions->handleLogError("Updating user ID: $userId");

            $userKey = $this->const('USER_PREFIX') . $userId;
            $userJson = $this->redisClient->get($userKey);

            if (!$userJson) {
                return $this->repetitiveFunctions->handleLogError("User not found!");
            }

            $user = json_decode($userJson, true);

            // Update username
            if (isset($data['username']) && $data['username'] !== $user['username']) {
                $existingUsernameId = $this->redisClient->get($this->const('USERNAME_PREFIX') . $data['username']);
                if ($existingUsernameId && $existingUsernameId != $userId) {
                    return $this->repetitiveFunctions->handleLogError("Username already exists");
                }
                $this->redisClient->del($this->const('USERNAME_PREFIX') . $user['username']);
                $this->redisClient->set($this->const('USERNAME_PREFIX') . $data['username'], $userId);
                $user['username'] = $data['username'];
            }

            // Update password
            if (isset($data['password'])) {
                $user['password'] = $this->hashPass($data['password']);
            }
            $this->repetitiveFunctions->handleLogError("update1");

            // Update name and surname
            if (isset($data['name'])) {
                $user['name'] = $data['name'];
            }
            $this->repetitiveFunctions->handleLogError("update2");

            if (isset($data['surname'])) {
                $user['surname'] = $data['surname'];
            }
            $this->repetitiveFunctions->handleLogError("update3");

            // Save the updated user data
            $this->redisClient->set($userKey, json_encode($user));

            return $this->success(['message' => 'User updated successfully']);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
