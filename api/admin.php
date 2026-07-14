<?php
/**
 * Grade Assessment - Admin API
 */

require_once __DIR__ . '/db.php';

$path = $_GET['path'] ?? '';

try {
    switch ($path) {
        case 'login': handleAdminLogin(); break;
        case 'dashboard': handleDashboard(); break;
        case 'stats': handleStats(); break;
        case 'assessments': handleAssessmentsList(); break;
        case 'assessment-detail': handleAssessmentDetail(); break;
        case 'delete-assessment': handleDeleteAssessment(); break;
        case 'ai-logs': handleAILogs(); break;
        case 'settings': handleSettings(); break;
        default: jsonError('Неизвестный endpoint', 404);
    }
} catch (Exception $e) {
    if (DEBUG) jsonError($e->getMessage(), 500, $e->getTrace());
    else jsonError('Внутренняя ошибка', 500);
}

function handleAdminLogin() {
    $data = getJsonInput();
    if (empty($data['username']) || empty($data['password'])) jsonError('Логин и пароль обязательны');
    
    $db = Database::getInstance();
    $admin = $db->fetchOne("SELECT * FROM admins WHERE username = ? AND is_active = TRUE", [$data['username']]);
    
    if (!$admin || !password_verify($data['password'], $admin['password_hash'])) {
        jsonError('Неверный логин или пароль', 401);
    }
    
    $db->update('admins', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$admin['id']]);
    
    $token = generateJWT(['admin_id' => $admin['id'], 'username' => $admin['username'], 'role' => 'admin']);
    
    jsonResponse([
        'success' => true,
        'token' => $token,
        'admin' => ['id' => $admin['id'], 'username' => $admin['username'], 'full_name' => $admin['full_name']]
    ]);
}

function handleDashboard() {
    $auth = checkAuth();
    $db = Database::getInstance();
    
    $totalStats = $db->fetchOne("SELECT COUNT(*) as total, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN status='in_progress' THEN 1 ELSE 0 END) as in_progress, AVG(total_score/NULLIF(max_possible_score,0)*100) as avg_score, COUNT(DISTINCT position) as unique_positions FROM assessments");
    
    $levelDistribution = $db->fetchAll("SELECT final_level, COUNT(*) as count FROM assessments WHERE final_level IS NOT NULL GROUP BY final_level");
    
    $recentAssessments = $db->fetchAll("SELECT a.*, (SELECT COUNT(*) FROM answers WHERE assessment_id = a.id) as answered_count FROM assessments a ORDER BY created_at DESC LIMIT 10");
    
    $dailyActivity = $db->fetchAll("SELECT DATE(created_at) as date, COUNT(*) as count FROM assessments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY date");
    
    jsonResponse([
        'success' => true,
        'stats' => [
            'total' => intval($totalStats['total']),
            'completed' => intval($totalStats['completed']),
            'in_progress' => intval($totalStats['in_progress']),
            'avg_score' => round(floatval($totalStats['avg_score'] ?? 0), 1),
            'unique_positions' => intval($totalStats['unique_positions'])
        ],
        'level_distribution' => $levelDistribution,
        'recent_assessments' => $recentAssessments,
        'daily_activity' => $dailyActivity
    ]);
}

function handleStats() {
    $auth = checkAuth();
    $db = Database::getInstance();
    
    $skillStats = $db->fetchAll("SELECT skill_name, skill_type, AVG(score) as avg_score, COUNT(*) as evaluations_count FROM skill_matrix GROUP BY skill_name, skill_type ORDER BY skill_type, avg_score DESC");
    
    jsonResponse(['success' => true, 'skill_stats' => $skillStats]);
}

function handleAssessmentsList() {
    $auth = checkAuth();
    $db = Database::getInstance();
    
    $limit = intval($_GET['limit'] ?? 20);
    $offset = intval($_GET['offset'] ?? 0);
    
    $assessments = $db->fetchAll(
        "SELECT a.*, (SELECT COUNT(*) FROM answers WHERE assessment_id = a.id) as answered_count FROM assessments a ORDER BY created_at DESC LIMIT ? OFFSET ?",
        [$limit, $offset]
    );
    
    $total = $db->fetchOne("SELECT COUNT(*) as count FROM assessments");
    
    jsonResponse(['success' => true, 'assessments' => $assessments, 'total' => intval($total['count'])]);
}

function handleAssessmentDetail() {
    $auth = checkAuth();
    $assessmentId = intval($_GET['id'] ?? 0);
    if (!$assessmentId) jsonError('ID обязателен');
    
    $db = Database::getInstance();
    $assessment = $db->fetchOne("SELECT a.*, (SELECT COUNT(*) FROM answers WHERE assessment_id = a.id) as answered_count FROM assessments a WHERE a.id = ?", [$assessmentId]);
    if (!$assessment) jsonError('Не найдено', 404);
    
    $answers = $db->fetchAll("SELECT * FROM answers WHERE assessment_id = ? ORDER BY id", [$assessmentId]);
    $skillMatrix = $db->fetchAll("SELECT * FROM skill_matrix WHERE assessment_id = ? ORDER BY skill_type, skill_name", [$assessmentId]);
    $aiLogs = $db->fetchAll("SELECT * FROM ai_logs WHERE assessment_id = ? ORDER BY created_at DESC", [$assessmentId]);
    
    jsonResponse(['success' => true, 'assessment' => $assessment, 'answers' => $answers, 'skill_matrix' => $skillMatrix, 'ai_logs' => $aiLogs]);
}

function handleDeleteAssessment() {
    $auth = checkAuth();
    $data = getJsonInput();
    $assessmentId = intval($data['id'] ?? 0);
    if (!$assessmentId) jsonError('ID обязателен');
    
    $db = Database::getInstance();
    $db->query("DELETE FROM assessments WHERE id = ?", [$assessmentId]);
    
    jsonResponse(['success' => true, 'message' => 'Оценка удалена']);
}

function handleAILogs() {
    $auth = checkAuth();
    $db = Database::getInstance();
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);
    
    $logs = $db->fetchAll("SELECT al.*, a.full_name, a.position FROM ai_logs al LEFT JOIN assessments a ON al.assessment_id = a.id ORDER BY al.created_at DESC LIMIT ? OFFSET ?", [$limit, $offset]);
    $total = $db->fetchOne("SELECT COUNT(*) as count FROM ai_logs");
    
    jsonResponse(['success' => true, 'logs' => $logs, 'total' => intval($total['count'])]);
}

function handleSettings() {
    $auth = checkAuth();
    jsonResponse([
        'success' => true,
        'settings' => [
            'deepseek_api_configured' => !empty(DEEPSEEK_API_KEY_FINAL),
            'deepseek_model' => DEEPSEEK_MODEL,
            'version' => '1.0.0'
        ]
    ]);
}

function getJsonInput() {
    $json = file_get_contents('php://input');
    return json_decode($json, true) ?: [];
}
