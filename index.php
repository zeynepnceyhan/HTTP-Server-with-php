<?php

require 'vendor/autoload.php';
require_once 'constants.php';
require_once 'repetitivefunctions.php';
require_once 'user.php';
require_once 'match.php';
require_once 'leaderboard.php';
require_once 'simulation.php';
require_once 'middleware.php';
require_once 'friendship.php'; 

// Sınıfları başlat
$repetitiveFunctions = new RepetitiveFunctions();
$redis = $repetitiveFunctions->getRedis();
$leaderboard = new Leaderboard($redis, $repetitiveFunctions);
$userClass = new UserClass($redis, $repetitiveFunctions, $leaderboard);
$matchResult = new MatchResult($redis, $repetitiveFunctions);
$simulation = new Simulation($redis, $repetitiveFunctions, $userClass, $leaderboard);
$authMiddleware = new AuthMiddleware($repetitiveFunctions, $redis);
$friendship = new FriendshipClass($redis, $repetitiveFunctions, $authMiddleware); // Yeni eklenen sınıf

// İstek yöntemi ve verilerini yakala
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestData = json_decode(file_get_contents('php://input'), true) ?? [];

// İsteklerin kimlik doğrulamasını yap (kayıt ve giriş hariç)
if ($requestMethod === 'POST' || $requestMethod === 'GET') {
    $action = $requestData['action'] ?? $_GET['action'] ?? '';
    if ($action && !in_array($action, ['register', 'login'])) {
        $authMiddleware->authenticateRequest();
    }
}

// Route işleyicileri
function handleRequest(string $action, array $request, UserClass $userClass, MatchResult $matchResult, Leaderboard $leaderboard, RepetitiveFunctions $repetitiveFunctions, Simulation $simulation, FriendshipClass $friendship) {
    switch ($action) {
        case 'register':
            return handleRegister($request, $userClass, $repetitiveFunctions);

        case 'login':
            return handleLogin($request, $userClass, $repetitiveFunctions);

        case 'update':
            return handleUpdate($request, $userClass, $repetitiveFunctions);

        case 'matchresult':
            return handleMatchResult($request, $matchResult, $repetitiveFunctions);

        case 'simulate':
            return handleSimulate($request, $simulation, $repetitiveFunctions);

        case 'leaderboard':
            return handleLeaderboard($request, $leaderboard, $repetitiveFunctions);

        case 'userdetails':
            return handleUserDetails($request, $userClass, $repetitiveFunctions);

        case 'searchuser':
            return handleSearchUser($request, $friendship, $repetitiveFunctions);

        case 'sendfriendrequest':
            return handleSendFriendRequest($request, $friendship, $repetitiveFunctions);

        case 'listfriendrequests':
            return handleListFriendRequests($request, $friendship, $repetitiveFunctions);

        case 'acceptrejectfriendrequest':
            return handleAcceptRejectFriendRequest($request, $friendship, $repetitiveFunctions);

        case 'listfriends':
            return handleListFriends($request, $friendship, $repetitiveFunctions);

        default:
            return $repetitiveFunctions->handleLogError('Invalid action');
    }
}

// Yardımcı işlevler
function handleRegister(array $request, UserClass $userClass, RepetitiveFunctions $repetitiveFunctions) {
    if (!isset($request['username'], $request['password'], $request['name'], $request['surname'])) {
        return $repetitiveFunctions->handleLogError('Missing parameters');
    }
    $registration = $userClass->register($request['username'], $request['password'], $request['name'], $request['surname']);
    return $registration['status']
        ? $repetitiveFunctions->responseSuccess([
            'userId' => $registration['data']['userId'],
            'username' => $request['username'],
            'name' => $request['name'],
            'surname' => $request['surname'],
            'score' => 0 // Başlangıç skoru
        ])
        : $repetitiveFunctions->handleLogError('Registration failed');
}

function handleLogin(array $request, UserClass $userClass, RepetitiveFunctions $repetitiveFunctions) {
    if (!isset($request['username'], $request['password'])) {
        return $repetitiveFunctions->handleLogError('Missing parameters');
    }
    return $userClass->login($request['username'], $request['password']);
}

function handleUpdate(array $request, UserClass $userClass, RepetitiveFunctions $repetitiveFunctions) {
    if (!isset($request['id'])) {
        return $repetitiveFunctions->handleLogError('Missing parameters');
    }
    return $userClass->updateUser((int)$request['id'], $request);
}

function handleMatchResult(array $request, MatchResult $matchResult, RepetitiveFunctions $repetitiveFunctions) {
    if (!isset($request['userid1'], $request['userid2'], $request['score1'], $request['score2'])) {
        return $repetitiveFunctions->handleLogError('Missing parameters');
    }
    return $matchResult->processResult($request['userid1'], $request['userid2'], $request['score1'], $request['score2']);
}

function handleSimulate(array $request, Simulation $simulation, RepetitiveFunctions $repetitiveFunctions) {
    if (!isset($request['usercount'])) {
        return $repetitiveFunctions->handleLogError('Missing usercount parameter');
    }
    $userCount = (int)$request['usercount'];
    if ($userCount < 1) {
        return $repetitiveFunctions->handleLogError('Invalid usercount');
    }
    return $simulation->run($userCount);
}

function handleLeaderboard(array $request, Leaderboard $leaderboard, RepetitiveFunctions $repetitiveFunctions) {
    $page = isset($request['page']) ? (int)$request['page'] : 1;
    $count = isset($request['count']) ? (int)$request['count'] : 10;
    return $leaderboard->getLeaderboard($page, $count);
}

function handleUserDetails(array $request, UserClass $userClass, RepetitiveFunctions $repetitiveFunctions) {
    if (!isset($request['id'])) {
        return $repetitiveFunctions->handleLogError('Missing user ID parameter');
    }
    return $userClass->userDetails((int)$request['id']);
}

function handleSearchUser(array $request, FriendshipClass $friendship, RepetitiveFunctions $repetitiveFunctions) {
    if (!isset($request['username'])) {
        return $repetitiveFunctions->handleLogError('Missing username parameter');
    }
    return $friendship->searchUser($request['username']);
}

function handleSendFriendRequest(array $request, FriendshipClass $friendship, RepetitiveFunctions $repetitiveFunctions) {
    if (!isset($request['targetUserId'])) {
        return $repetitiveFunctions->handleLogError('Missing targetUserId parameter');
    }

    // Arkadaşlık talebi gönderme işlemi
    return $friendship->sendFriendRequest((int)$request['targetUserId']);
}


function handleListFriendRequests(array $request, FriendshipClass $friendship, RepetitiveFunctions $repetitiveFunctions) {
    // 'page' ve 'count' parametrelerini GET isteğinden al
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $count = isset($_GET['count']) ? (int)$_GET['count'] : 10;

    // Sayfa ve sayfa başına öğe sayısı geçerli mi kontrol et
    if ($page < 1 || $count < 1) {
        return $repetitiveFunctions->handleLogError('Invalid pagination parameters');
    }

    return $friendship->listFriendRequests($page, $count);
}

function handleAcceptRejectFriendRequest(array $request, FriendshipClass $friendship, RepetitiveFunctions $repetitiveFunctions) {
    if (!isset($request['targetUserId'], $request['status'])) {
        return $repetitiveFunctions->handleLogError('Missing parameters');
    }
    
    $targetUserId = (int)$request['targetUserId'];
    $status = $request['status'];

    return $friendship->acceptRejectFriendRequest($targetUserId, $status);
}


function handleListFriends(array $request, FriendshipClass $friendship, RepetitiveFunctions $repetitiveFunctions) {
    $page = isset($request['page']) ? (int)$request['page'] : 1;
    $count = isset($request['count']) ? (int)$request['count'] : 10;
    return $friendship->listFriends($page, $count);
}

// İstekleri işle
if ($requestMethod === 'POST' || $requestMethod === 'GET') {
    $action = $requestData['action'] ?? $_GET['action'] ?? '';
    echo json_encode(handleRequest($action, $requestData, $userClass, $matchResult, $leaderboard, $repetitiveFunctions, $simulation, $friendship));
} else {
    echo json_encode($repetitiveFunctions->handleLogError('Unsupported request method'));
}

