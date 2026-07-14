<?php
/**
 * Grade Assessment - Installation Script
 */

require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');

$step = $_GET['step'] ?? 'check';

try {
    switch ($step) {
        case 'check': showCheckPage(); break;
        case 'install': doInstall(); break;
        case 'success': showSuccessPage(); break;
        default: showCheckPage();
    }
} catch (Exception $e) {
    echo '<div style="color: red; padding: 20px;"><h2>Ошибка</h2><p>' . htmlspecialchars($e->getMessage()) . '</p></div>';
}

function showCheckPage() {
    $checks = [
        'PHP >= 8.0' => PHP_VERSION_ID >= 80000,
        'PDO' => extension_loaded('pdo'),
        'PDO MySQL' => extension_loaded('pdo_mysql'),
        'cURL' => extension_loaded('curl'),
        'JSON' => extension_loaded('json'),
        'MBString' => extension_loaded('mbstring'),
        'Файл .env' => file_exists(__DIR__ . '/../.env') || file_exists(__DIR__ . '/../api.env'),
    ];
    $allPassed = !in_array(false, $checks, true);
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Установка</title>
<style>body{font-family:Arial,sans-serif;max-width:600px;margin:50px auto;padding:20px;}
.check-item{padding:10px;margin:5px 0;border-radius:5px;}
.pass{background:#d4edda;color:#155724;}
.fail{background:#f8d7da;color:#721c24;}
.btn{display:inline-block;padding:12px 24px;background:#007bff;color:white;text-decoration:none;border-radius:5px;margin-top:20px;}
.btn:hover{background:#0056b3;}</style></head><body>
<h1>Установка Grade Assessment</h1>
<h2>Проверка</h2>
<?php foreach ($checks as $name => $passed): ?>
<div class="check-item <?= $passed ? 'pass' : 'fail' ?>"><?= $passed ? '✓' : '✗' ?> <?= htmlspecialchars($name) ?></div>
<?php endforeach; ?>
<?php if ($allPassed): ?>
<p>Все требования выполнены!</p><a href="install.php?step=install" class="btn">Установить</a>
<?php else: ?>
<p>Исправьте ошибки. Создайте .env файл:</p>
<pre style="background:#f5f5f5;padding:15px;border-radius:5px;">DB_HOST=localhost
DB_NAME=grade_assessment
DB_USER=root
DB_PASS=пароль
DEEPSEEK_API_KEY=ключ
JWT_SECRET=секрет</pre>
<?php endif; ?>
</body></html>
<?php }

function doInstall() {
    initDatabase();
    header('Location: install.php?step=success');
    exit;
}

function showSuccessPage() { ?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Готово</title>
<style>body{font-family:Arial,sans-serif;max-width:600px;margin:50px auto;padding:20px;text-align:center;}
.success{color:#28a745;font-size:72px;margin:20px 0;}
.btn{display:inline-block;padding:12px 24px;background:#007bff;color:white;text-decoration:none;border-radius:5px;margin:10px;}
.info-box{background:#f8f9fa;padding:20px;border-radius:10px;margin:20px 0;text-align:left;}
code{background:#e9ecef;padding:2px 6px;border-radius:3px;}</style></head><body>
<div class="success">✓</div>
<h1>Установка завершена!</h1>
<div class="info-box">
<h3>Данные для входа:</h3>
<p><strong>Логин:</strong> <code>admin</code></p>
<p><strong>Пароль:</strong> <code>password</code></p>
<p style="color:#dc3545;">⚠ Смените пароль после первого входа!</p>
</div>
<a href="/" class="btn">К приложению</a>
<a href="/admin" class="btn" style="background:#28a745;">В админ-панель</a>
<p style="margin-top:30px;color:#6c757d;font-size:14px;">Удалите api/install.php для безопасности.</p>
</body></html>
<?php }
