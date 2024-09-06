<?php

require 'vendor/autoload.php';
require_once 'constants.php';
require_once 'repetitivefunctions.php';

use Predis\Client as PredisClient;

class Leaderboard {
    private PredisClient $redis;
    private RepetitiveFunctions $repetitiveFunctions;

    public function __construct(PredisClient $redis, RepetitiveFunctions $repetitiveFunctions) {
        $this->redis = $redis;
        $this->repetitiveFunctions = $repetitiveFunctions;
    }

    private function error(string $message) {
        return $this->repetitiveFunctions->responseError($message);
    }

    private function success(array $data) {
        return $this->repetitiveFunctions->responseSuccess($data);
    }

    public function storeUserData(string $userID, array $userData) {
        try {
            $this->redis->set("user:$userID", json_encode($userData));
        } catch (Exception $e) {
            $this->repetitiveFunctions->handleLogError("Error storing user data for $userID: " . $e->getMessage());
        }
    }

    public function getUserData(string $userID) {
        try {
            $user = $this->redis->get("user:$userID");
            if ($user === null) {
                return $this->error("User data not found for userID: $userID");
            }
            return json_decode($user, true);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function getLeaderboard(int $page, int $count) {
        if ($page < 1) $page = 1;
        if ($count < 1) $count = 10;

        $start = ($page - 1) * $count;
        $end = $start + $count - 1;

        try {
            $users = $this->redis->zrevrange('leaderboard', $start, $end, ['withscores' => true]);

            if (empty($users)) {
                return $this->success([]);
            }

            $leaderboard = [];
            $rank = $start + 1;

            foreach ($users as $userID => $score) {
                $user = $this->redis->get("user:$userID");
                if ($user === null) {
                    $this->repetitiveFunctions->handleLogError("User data not found for userID: $userID");
                    continue;
                }

                $userData = json_decode($user, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $leaderboard[] = [
                        'id' => $userID,
                        'username' => $userData['username'] ?? 'Unknown',
                        'rank' => $rank,
                        'score' => $score
                    ];
                    $rank++;
                } else {
                    $this->repetitiveFunctions->handleLogError('Invalid JSON data for user: ' . $userID);
                }
            }

            return $this->success($leaderboard);

        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    // Update user scores after a match
    public function updateMatchScores(string $winnerID, string $loserID, int $winnerScore, int $loserScore) {
        try {
            // Increment scores for the winner and loser
            $this->redis->zincrby('leaderboard', $winnerScore, $winnerID);
            $this->redis->zincrby('leaderboard', $loserScore, $loserID);

            // Store or update user data
            $winnerData = $this->getUserData($winnerID);
            $loserData = $this->getUserData($loserID);

            // Assuming that the user data contains a score field
            $winnerData['score'] = $this->redis->zscore('leaderboard', $winnerID);
            $loserData['score'] = $this->redis->zscore('leaderboard', $loserID);

            $this->storeUserData($winnerID, $winnerData);
            $this->storeUserData($loserID, $loserData);

            return $this->success(['message' => 'Scores updated successfully']);

        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
