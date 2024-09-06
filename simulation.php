<?php

require 'vendor/autoload.php';
require_once 'constants.php';
require_once 'repetitivefunctions.php';
require_once 'user.php';
require_once 'leaderboard.php';

use Predis\Client as PredisClient;

class Simulation {
    private PredisClient $redis;
    private RepetitiveFunctions $repetitiveFunctions;
    private UserClass $userClass;
    private Leaderboard $leaderboard;

    public function __construct(PredisClient $redis, RepetitiveFunctions $repetitiveFunctions, UserClass $userClass, Leaderboard $leaderboard) {
        $this->redis = $redis;
        $this->repetitiveFunctions = $repetitiveFunctions;
        $this->userClass = $userClass;
        $this->leaderboard = $leaderboard;
    }

    public function run(int $userCount) {
        if ($userCount < 1) {
            $this->repetitiveFunctions->handleLogError("Invalid user count: $userCount");
            return $this->repetitiveFunctions->responseError('Invalid user count');
        }

        // Kullanıcıları oluştur
        $this->repetitiveFunctions->handleLogError("Starting user creation process");
        $users = $this->createUsers($userCount);
        if (!$users) {
            $this->repetitiveFunctions->handleLogError('User creation failed');
            return $this->repetitiveFunctions->responseError('User creation failed');
        }

        $this->repetitiveFunctions->handleLogError("User creation completed successfully");

        // Simülasyon sonuçlarını oluştur
        $this->repetitiveFunctions->handleLogError("Starting match simulation process");
        $simulationResult = $this->simulateMatches($users);
        
        $this->repetitiveFunctions->handleLogError("Simulation completed successfully");
        return $this->repetitiveFunctions->responseSuccess($simulationResult);
    }

    private function createUsers(int $userCount): array {
        $this->repetitiveFunctions->handleLogError("Creating users, count: $userCount");
        $users = [];
    
        for ($i = 1; $i <= $userCount; $i++) {
            $this->repetitiveFunctions->handleLogError("Creating user $i");
            
            $username = $this->generateUniqueUsername();
            $password = password_hash('fixedpassword', PASSWORD_BCRYPT); // Şifreyi hashleyin
            $name = $this->repetitiveFunctions->generateRandomName();
            $surname = $this->repetitiveFunctions->generateRandomSurname();
    
            try {
                // Kullanıcıyı Redis'e ekleyin
                $userId = rand(1, 1000);  // Daha benzersiz ID
                $userKey = "user:$userId";
                
                $this->redis->hmset($userKey, [
                    'username' => $username,
                    'password' => $password,
                    'name' => $name,
                    'surname' => $surname,
                    'score' => 0 // Başlangıçta skor sıfır
                ]);
    
                // Kullanıcı bilgilerini dizine ekleyin
                $users[] = [
                    'userId' => $userId,
                    'username' => $username,
                    'name' => $name,
                    'surname' => $surname
                ];
    
                $this->repetitiveFunctions->handleLogError("User created with ID: $userId");
    
            } catch (Exception $e) {
                $this->repetitiveFunctions->handleLogError("Exception during user creation: " . $e->getMessage());
                break; // Hata durumunda döngüyü kır
            }
        }
    
        return $users;
    }
  
    private function generateUniqueUsername(): string {
        $this->repetitiveFunctions->handleLogError("Generating unique username");
        do {
            $username = 'player_' . $this->repetitiveFunctions->generateRandomString(6);
            $checkResponse = $this->repetitiveFunctions->checkUsername($username);
        } while (!$checkResponse['status']);

        return $username;
    }

    private function simulateMatches(array $users): array {
        $matches = [];
        $userIds = array_column($users, 'userId');

        $this->repetitiveFunctions->handleLogError("Simulating matches");

        for ($i = 0; $i < count($userIds); $i++) {
            for ($j = $i + 1; $j < count($userIds); $j++) {
                $userId1 = $userIds[$i];
                $userId2 = $userIds[$j];
                $score1 = rand(0, 5);
                $score2 = rand(0, 5);

                $match = [
                    'user1' => $userId1,
                    'user2' => $userId2,
                    'score1' => $score1,
                    'score2' => $score2
                ];

                $matches[] = $match;

                if ($score1 > $score2) {
                    $this->redis->zincrby("leaderboard", 3, "user:{$userId1}");
                    $this->redis->zincrby("leaderboard", 0, "user:{$userId2}");
                    $this->repetitiveFunctions->handleLogError("User $userId1 wins against $userId2");
                } elseif ($score1 < $score2) {
                    $this->redis->zincrby("leaderboard", 0, "user:{$userId1}");
                    $this->redis->zincrby("leaderboard", 3, "user:{$userId2}");
                    $this->repetitiveFunctions->handleLogError("User $userId2 wins against $userId1");
                } else {
                    $this->redis->zincrby("leaderboard", 1, "user:{$userId1}");
                    $this->redis->zincrby("leaderboard", 1, "user:{$userId2}");
                    $this->repetitiveFunctions->handleLogError("Match between $userId1 and $userId2 ended in a draw");
                }
            }
        }

        $updatedUsers = [];
        foreach ($users as $user) {
            $userId = $user['userId'];
            $score = $this->redis->zscore("leaderboard", "user:{$userId}") ?: 0;
            $updatedUsers[] = [
                'userId' => $userId,
                'username' => $user['username'],
                'name' => $user['name'],
                'surname' => $user['surname'],
                'score' => $score
            ];
        }

        $this->repetitiveFunctions->handleLogError("Match simulation completed");

        return [
            'matches' => $matches,
            'users' => $updatedUsers
        ];
    }
}
