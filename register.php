<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// register.php
header('Content-Type: application/json');

define('REGISTRATION_CODE', 'qualitas2025');

$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';
$sentCode = $input['code'] ?? '';

if (empty($username) || empty($password) || empty($sentCode)) {
    echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios.']);
    exit;
}

if ($sentCode !== REGISTRATION_CODE) {
    echo json_encode(['success' => false, 'message' => 'El código de registro es incorrecto.']);
    exit;
}

$usersFile = 'users.json';
if (!file_exists($usersFile)) {
    echo json_encode(['success' => false, 'message' => 'Error de configuración del servidor.']);
    exit;
}
$usersData = json_decode(file_get_contents($usersFile), true);

foreach ($usersData as $user) {
    if (strtolower($user['username']) === strtolower($username)) {
        echo json_encode(['success' => false, 'message' => 'Este nombre de usuario ya está en uso.']);
        exit;
    }
}

$newHash = password_hash($password, PASSWORD_DEFAULT);

$usersData[] = [
    'username' => $username,
    'hash' => $newHash
];

if (file_put_contents($usersFile, json_encode($usersData, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true, 'message' => 'Usuario registrado con éxito.']);
} else {
    echo json_encode(['success' => false, 'message' => 'No se pudo guardar el nuevo usuario.']);
}
?>