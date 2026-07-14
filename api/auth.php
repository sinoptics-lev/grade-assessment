<?php
/**
 * Grade Assessment - Auth API
 */

require_once __DIR__ . '/db.php';

$path = $_GET['path'] ?? '';

try {
    switch ($path) {
        case 'login': handleLogin(); break;
        case 'register': handleRegister(); break;
        case 'me': handleMe(); break;
        case 'refresh': handleRefresh(); break;
        default: jsonError('Неизвестный endpoint', 404);
    }
} catch (Exception $e) {
    jsonError('Внутренняя ошибка сервера', 500);
}

function handleLogin() {
    $data = getJsonInput();
    if (empty($data['username']) || empty($data['password'])) {
        jsonError('Логин и пароль обязательны');
    }
    
    $db = Database::getInstance();
    $admin = $db->fetchOne("SELECT * FROM admins WHERE username = ? AND is_active = TRUE", [$data['username']]);
    
    if (!$admin || !password_verify($data['password'], $admin['password_hash'])) {
        jsonError('Неверный логин или пароль', 401);
    }
    
    $db->update('admins', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$admin['id']]);
    
    $token = generateJWT([
        'admin_id' => $admin['id'],
        'username' => $admin['username'],
        'role' => 'admin'
    ]);
    
    jsonResponse([
        'success' => true,
        'token' => $token,
        'user' => ['id' => $admin['id'], 'username' => $admin['username'], 'full_name' => $admin['full_name']]
    ]);
}

function handleRegister() {
    $auth = checkAuth();
    $data = getJsonInput();
    
    if (empty($data['username']) || empty($data['password']) || empty($data['full_name'])) {
        jsonError('Логин, пароль и имя обязательны');
    }
    
    $db = Database::getInstance();
    $existing = $db->fetchOne("SELECT id FROM admins WHERE username = ?", [$data['username']]);
    if ($existing) jsonError('Пользователь уже существует');
    
    $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);
    $db->insert('admins', [
        'username' => $data['username'],
        'password_hash' => $passwordHash,
        'full_name' => $data['full_name'],
        'email' => $data['email'] ?? null
    ]);
    
    jsonResponse(['success' => true, 'message' => 'Администратор создан']);
}

function handleMe() {
    $auth = checkAuth();
    $db = Database::getInstance();
    $admin = $db->fetchOne("SELECT id, username, full_name, email, is_active, created_at, last_login FROM admins WHERE id = ?", [$auth['admin_id']]);
    if (!$admin) jsonError('Пользователь не найден', 404);
    
    jsonResponse(['success' => true, 'user' => $admin]);
}

function handleRefresh() {
    $auth = checkAuth();
    $token = generateJWT([
        'admin_id' => $auth['admin_id'],
        'username' => $auth['username'],
        'role' => $auth['role']
    ]);
    
    jsonResponse(['success' => true, 'token' => $token]);
}

function getJsonInput() {
    $json = file_get_contents('php://input');
    return json_decode($json, true) ?: [];
}
