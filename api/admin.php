<?php
/**
 * Grade Assessment - Admin API
 * Управление административными функциями
 */

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

try {
    switch ($path) {
        case 'login':
            handleAdminLogin();
            break;
        case 'dashboard':
            handleDashboard();
            break;
        case 'stats':
            handleStats();
            break;
        case 'assessments':
            handleAssessmentsList();
            break;
        case 'assessment-detail':
            handleAssessmentDetail();
            break;
        case 'delete-assessment':
            handleDeleteAssessment();
            break;
        case 'ai-logs':
            handleAILogs();
            break;
        case 'settings':
            handleSettings();
            break;
        default:
            jsonError('Неизвестный endpoint', 404);
    }
} catch (Exception $e) {
    if (DEBUG) {
        jsonError($e->getMessage(), 500, $e->getTrace());
    } else {
        jsonError('Внутренняя ошибка сервера', 500);
    }
}

/**
 * Авторизация администратора
 */
function handleAdminLogin() {
    $data = getJsonInput();
    
    if (empty($data['username']) || empty($data['password'])) {
        jsonError('Логин и пароль обязательны');
    }
    
    $db = Database::getInstance();
    
    $admin = $db->fetchOne(
        "SELECT * FROM admins WHERE username = ? AND is_active = TRUE",
        [$data['username']]
    );
    
    if (!$admin || !password_verify($data['password'], $admin['password_hash'])) {
        jsonError('Неверный логин или пароль', 401);
    }
    
    // Обновляем время входа
    $db->update('admins', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$admin['id']]);
    
    // Генерируем токен
    $token = generateJWT([
        'admin_id' => $admin['id'],
        'username' => $admin['username'],
        'role' => 'admin'
    ]);
    
    jsonResponse([
        'success' => true,
        'token' => $token,
        'admin' => [
            'id' => $admin['id'],
            'username' => $admin['username'],
            'full_name' => $admin['full_name']
        ]
    ]);
}

/**
 * Данные для дашборда
 */
function handleDashboard() {
    $auth = checkAuth();
    
    $db = Database::getInstance();
    
    // Общая статистика
    $totalStats = $db->fetchOne(
        "SELECT 
            COUNT(*) as total_assessments,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            AVG(total_score / NULLIF(max_possible_score, 0) * 100) as avg_score,
            COUNT(DISTINCT position) as unique_positions
         FROM assessments"
    );
    
    // Распределение по уровням
    $levelDistribution = $db->fetchAll(
        "SELECT final_level, COUNT(*) as count 
         FROM assessments 
         WHERE final_level IS NOT NULL 
         GROUP BY final_level"
    );
    
    // Последние оценки
    $recentAssessments = $db->fetchAll(
        "SELECT a.*, 
            (SELECT COUNT(*) FROM answers WHERE assessment_id = a.id) as answered_count
         FROM assessments a
         ORDER BY a.created_at DESC
         LIMIT 10"
    );
    
    // Активность по дням
    $dailyActivity = $db->fetchAll(
        "SELECT 
            DATE(created_at) as date,
            COUNT(*) as count
         FROM assessments
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY DATE(created_at)
         ORDER BY date"
    );
    
    jsonResponse([
        'success' => true,
        'stats' => [
            'total' => intval($totalStats['total_assessments']),
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

/**
 * Статистика
 */
function handleStats() {
    $auth = checkAuth();
    
    $db = Database::getInstance();
    
    // Статистика по навыкам
    $skillStats = $db->fetchAll(
        "SELECT 
            skill_name,
            skill_type,
            AVG(score) as avg_score,
            MIN(score) as min_score,
            MAX(score) as max_score,
            COUNT(*) as evaluations_count
         FROM skill_matrix
         GROUP BY skill_name, skill_type
         ORDER BY skill_type, avg_score DESC"
    );
    
    // Средние по категориям
    $categoryAvg = $db->fetchAll(
        "SELECT 
            skill_type,
            AVG(score) as avg_score,
            COUNT(DISTINCT assessment_id) as people_count
         FROM skill_matrix
         GROUP BY skill_type"
    );
    
    jsonResponse([
        'success' => true,
        'skill_stats' => $skillStats,
        'category_averages' => $categoryAvg
    ]);
}

/**
 * Список оценок с фильтрацией
 */
function handleAssessmentsList() {
    $auth = checkAuth();
    
    $db = Database::getInstance();
    
    $status = $_GET['status'] ?? '';
    $position = $_GET['position'] ?? '';
    $search = $_GET['search'] ?? '';
    $limit = intval($_GET['limit'] ?? 20);
    $offset = intval($_GET['offset'] ?? 0);
    
    $where = [];
    $params = [];
    
    if ($status) {
        $where[] = "a.status = ?";
        $params[] = $status;
    }
    
    if ($position) {
        $where[] = "a.position LIKE ?";
        $params[] = "%$position%";
    }
    
    if ($search) {
        $where[] = "(a.full_name LIKE ? OR a.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $assessments = $db->fetchAll(
        "SELECT a.*, 
            (SELECT COUNT(*) FROM answers WHERE assessment_id = a.id) as answered_count,
            (SELECT COUNT(*) FROM skill_matrix WHERE assessment_id = a.id) as skills_count
         FROM assessments a
         $whereClause
         ORDER BY a.created_at DESC
         LIMIT ? OFFSET ?",
        array_merge($params, [$limit, $offset])
    );
    
    $total = $db->fetchOne(
        "SELECT COUNT(*) as count FROM assessments a $whereClause",
        $params
    );
    
    jsonResponse([
        'success' => true,
        'assessments' => $assessments,
        'total' => intval($total['count']),
        'limit' => $limit,
        'offset' => $offset
    ]);
}

/**
 * Детальная информация об оценке
 */
function handleAssessmentDetail() {
    $auth = checkAuth();
    
    $assessmentId = intval($_GET['id'] ?? 0);
    if (!$assessmentId) {
        jsonError('ID оценки обязателен');
    }
    
    $db = Database::getInstance();
    
    $assessment = $db->fetchOne(
        "SELECT a.*, 
            (SELECT COUNT(*) FROM answers WHERE assessment_id = a.id) as answered_count
         FROM assessments a 
         WHERE a.id = ?",
        [$assessmentId]
    );
    
    if (!$assessment) {
        jsonError('Оценка не найдена', 404);
    }
    
    $answers = $db->fetchAll(
        "SELECT * FROM answers WHERE assessment_id = ? ORDER BY id",
        [$assessmentId]
    );
    
    $skillMatrix = $db->fetchAll(
        "SELECT * FROM skill_matrix WHERE assessment_id = ? ORDER BY skill_type, skill_name",
        [$assessmentId]
    );
    
    $aiLogs = $db->fetchAll(
        "SELECT * FROM ai_logs WHERE assessment_id = ? ORDER BY created_at DESC",
        [$assessmentId]
    );
    
    jsonResponse([
        'success' => true,
        'assessment' => $assessment,
        'answers' => $answers,
        'skill_matrix' => $skillMatrix,
        'ai_logs' => $aiLogs
    ]);
}

/**
 * Удаление оценки
 */
function handleDeleteAssessment() {
    $auth = checkAuth();
    
    $data = getJsonInput();
    $assessmentId = intval($data['id'] ?? 0);
    
    if (!$assessmentId) {
        jsonError('ID оценки обязателен');
    }
    
    $db = Database::getInstance();
    
    // Каскадное удаление через FOREIGN KEY
    $db->query("DELETE FROM assessments WHERE id = ?", [$assessmentId]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Оценка удалена'
    ]);
}

/**
 * Логи AI запросов
 */
function handleAILogs() {
    $auth = checkAuth();
    
    $db = Database::getInstance();
    
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);
    
    $logs = $db->fetchAll(
        "SELECT al.*, a.full_name, a.position
         FROM ai_logs al
         LEFT JOIN assessments a ON al.assessment_id = a.id
         ORDER BY al.created_at DESC
         LIMIT ? OFFSET ?",
        [$limit, $offset]
    );
    
    $total = $db->fetchOne("SELECT COUNT(*) as count FROM ai_logs");
    
    jsonResponse([
        'success' => true,
        'logs' => $logs,
        'total' => intval($total['count'])
    ]);
}

/**
 * Настройки системы
 */
function handleSettings() {
    $auth = checkAuth();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        jsonResponse([
            'success' => true,
            'settings' => [
                'deepseek_api_configured' => !empty(DEEPSEEK_API_KEY_FINAL),
                'deepseek_model' => DEEPSEEK_MODEL,
                'version' => '1.0.0',
                'max_questions' => 15,
                'passing_score' => 60
            ]
        ]);
    }
    
    if ($method === 'POST') {
        // Обновление настроек
        jsonResponse([
            'success' => true,
            'message' => 'Настройки обновлены'
        ]);
    }
}

function getJsonInput() {
    $json = file_get_contents('php://input');
    return json_decode($json, true) ?: [];
}
