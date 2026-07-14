<?php
/**
 * Grade Assessment - Configuration
 */

define('DEBUG', false);

define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'grade_assessment');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', 'utf8mb4');

define('DEEPSEEK_API_KEY', $_ENV['DEEPSEEK_API_KEY'] ?? '');
define('DEEPSEEK_API_URL', 'https://api.deepseek.com/v1/chat/completions');
define('DEEPSEEK_MODEL', 'deepseek-chat');

define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'your-secret-key-change-in-production');
define('JWT_EXPIRE_HOURS', 24);

$allowedOrigins = [
    'http://localhost:3000',
    'http://localhost:5173',
    'https://sin.h1n.ru',
    '*'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
if (in_array($origin, $allowedOrigins) || in_array('*', $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function loadEnvFile($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, '"\'');
        if (!isset($_ENV[$key])) {
            $_ENV[$key] = $value;
        }
    }
}

loadEnvFile(__DIR__ . '/../.env');
loadEnvFile(__DIR__ . '/../api.env');

if (!empty($_ENV['DEEPSEEK_API_KEY'])) {
    define('DEEPSEEK_API_KEY_FINAL', $_ENV['DEEPSEEK_API_KEY']);
} else {
    define('DEEPSEEK_API_KEY_FINAL', DEEPSEEK_API_KEY);
}

if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError($message, $statusCode = 400, $details = null) {
    $response = ['error' => true, 'message' => $message];
    if ($details !== null) {
        $response['details'] = $details;
    }
    jsonResponse($response, $statusCode);
}

function checkAuth() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        jsonError('Требуется авторизация', 401);
    }
    
    $token = $matches[1];
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        jsonError('Неверный токен', 401);
    }
    
    $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
    if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) {
        jsonError('Токен истёк', 401);
    }
    
    return $payload;
}

function generateJWT($data) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $time = time();
    $payload = json_encode(array_merge($data, [
        'iat' => $time,
        'exp' => $time + (JWT_EXPIRE_HOURS * 3600)
    ]));
    
    $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', "$base64Header.$base64Payload", JWT_SECRET, true);
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return "$base64Header.$base64Payload.$base64Signature";
}
