<?php
/**
 * Grade Assessment - API Router
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

try {
    initDatabase();
} catch (Exception $e) {}

$path = $_GET['path'] ?? '';
$segments = explode('/', trim($path, '/'));
$resource = $segments[0] ?? '';

switch ($resource) {
    case 'assessment': require_once __DIR__ . '/assessment.php'; break;
    case 'report': require_once __DIR__ . '/report.php'; break;
    case 'admin': require_once __DIR__ . '/admin.php'; break;
    case 'auth': require_once __DIR__ . '/auth.php'; break;
    case 'test':
        jsonResponse([
            'success' => true,
            'message' => 'API Grade Assessment работает',
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'deepseek_configured' => !empty(DEEPSEEK_API_KEY_FINAL)
        ]);
        break;
    default: jsonError('Доступные ресурсы: assessment, report, admin, auth, test', 404);
}
