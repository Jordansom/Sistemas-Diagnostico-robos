<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// login.php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Usuario o contraseña no proporcionados.']);
    exit;
}

$usersFile = 'users.json';
if (!file_exists($usersFile)) {
    echo json_encode(['success' => false, 'message' => 'Error de configuración (no se encuentra users.json).']);
    exit;
}
$usersData = json_decode(file_get_contents($usersFile), true);

$userFound = null;
foreach ($usersData as $user) {
    if (strtolower($user['username']) === strtolower($username)) {
        $userFound = $user;
        break;
    }
}

if ($userFound && isset($userFound['hash']) && password_verify($password, $userFound['hash'])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos.']);
}
?>