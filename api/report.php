<?php
/**
 * Grade Assessment - Report API
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/deepseek.php';

$path = $_GET['path'] ?? '';

try {
    switch ($path) {
        case 'get': handleGetReport(); break;
        case 'by-token': handleGetReportByToken(); break;
        case 'list': handleListReports(); break;
        case 'skill-matrix': handleGetSkillMatrix(); break;
        case 'answers': handleGetAnswers(); break;
        case 'export': handleExportReport(); break;
        case 'regenerate': handleRegenerateReport(); break;
        default: jsonError('Неизвестный endpoint', 404);
    }
} catch (Exception $e) {
    if (DEBUG) {
        jsonError($e->getMessage(), 500, $e->getTrace());
    } else {
        jsonError('Внутренняя ошибка сервера', 500);
    }
}

function handleGetReport() {
    $auth = checkAuth();
    $assessmentId = intval($_GET['assessment_id'] ?? 0);
    if (!$assessmentId) jsonError('ID оценки обязателен');
    
    $db = Database::getInstance();
    $assessment = $db->fetchOne("SELECT * FROM assessments WHERE id = ?", [$assessmentId]);
    if (!$assessment) jsonError('Оценка не найдена', 404);
    
    $answers = $db->fetchAll("SELECT * FROM answers WHERE assessment_id = ? ORDER BY id", [$assessmentId]);
    $skillMatrix = $db->fetchAll("SELECT * FROM skill_matrix WHERE assessment_id = ? ORDER BY skill_type, skill_name", [$assessmentId]);
    
    jsonResponse(['success' => true, 'report' => buildReportData($assessment, $answers, $skillMatrix)]);
}

function handleGetReportByToken() {
    $token = $_GET['token'] ?? '';
    if (empty($token)) jsonError('Токен обязателен');
    
    $db = Database::getInstance();
    $tokenData = $db->fetchOne(
        "SELECT at.*, a.*, at.id as token_id 
         FROM access_tokens at
         JOIN assessments a ON at.assessment_id = a.id
         WHERE at.token = ? AND at.expires_at > NOW()",
        [$token]
    );
    
    if (!$tokenData) jsonError('Отчёт не найден или срок доступа истёк', 404);
    
    $db->update('access_tokens', ['used_at' => date('Y-m-d H:i:s')], 'id = ?', [$tokenData['token_id']]);
    
    $assessmentId = $tokenData['assessment_id'];
    $assessment = $db->fetchOne("SELECT * FROM assessments WHERE id = ?", [$assessmentId]);
    $answers = $db->fetchAll("SELECT * FROM answers WHERE assessment_id = ? ORDER BY id", [$assessmentId]);
    $skillMatrix = $db->fetchAll("SELECT * FROM skill_matrix WHERE assessment_id = ? ORDER BY skill_type, skill_name", [$assessmentId]);
    
    jsonResponse([
        'success' => true,
        'report' => buildReportData($assessment, $answers, $skillMatrix),
        'access_info' => ['token_expires' => $tokenData['expires_at'], 'is_public' => true]
    ]);
}

function handleListReports() {
    $auth = checkAuth();
    $db = Database::getInstance();
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);
    
    $assessments = $db->fetchAll(
        "SELECT a.*, (SELECT COUNT(*) FROM answers WHERE assessment_id = a.id) as answered_count
         FROM assessments a ORDER BY a.created_at DESC LIMIT ? OFFSET ?",
        [$limit, $offset]
    );
    
    $total = $db->fetchOne("SELECT COUNT(*) as count FROM assessments");
    
    jsonResponse([
        'success' => true,
        'assessments' => $assessments,
        'total' => intval($total['count']),
        'limit' => $limit,
        'offset' => $offset
    ]);
}

function handleGetSkillMatrix() {
    $assessmentId = intval($_GET['assessment_id'] ?? 0);
    if (!$assessmentId) jsonError('ID оценки обязателен');
    
    $db = Database::getInstance();
    $skillMatrix = $db->fetchAll("SELECT * FROM skill_matrix WHERE assessment_id = ? ORDER BY skill_type, skill_name", [$assessmentId]);
    
    $grouped = ['hard' => [], 'soft' => []];
    foreach ($skillMatrix as $skill) {
        $grouped[$skill['skill_type']][] = $skill;
    }
    
    jsonResponse(['success' => true, 'skill_matrix' => $grouped]);
}

function handleGetAnswers() {
    $auth = checkAuth();
    $assessmentId = intval($_GET['assessment_id'] ?? 0);
    if (!$assessmentId) jsonError('ID оценки обязателен');
    
    $db = Database::getInstance();
    $answers = $db->fetchAll("SELECT * FROM answers WHERE assessment_id = ? ORDER BY id", [$assessmentId]);
    
    jsonResponse(['success' => true, 'answers' => $answers]);
}

function handleExportReport() {
    $format = $_GET['format'] ?? 'json';
    $token = $_GET['token'] ?? '';
    if (empty($token)) jsonError('Токен обязателен');
    
    $db = Database::getInstance();
    $tokenData = $db->fetchOne(
        "SELECT at.*, a.* FROM access_tokens at JOIN assessments a ON at.assessment_id = a.id
         WHERE at.token = ? AND at.expires_at > NOW()",
        [$token]
    );
    
    if (!$tokenData) jsonError('Отчёт не найден', 404);
    
    $assessmentId = $tokenData['assessment_id'];
    $assessment = $db->fetchOne("SELECT * FROM assessments WHERE id = ?", [$assessmentId]);
    $answers = $db->fetchAll("SELECT * FROM answers WHERE assessment_id = ? ORDER BY id", [$assessmentId]);
    $skillMatrix = $db->fetchAll("SELECT * FROM skill_matrix WHERE assessment_id = ? ORDER BY skill_type, skill_name", [$assessmentId]);
    
    $report = buildReportData($assessment, $answers, $skillMatrix);
    
    if ($format === 'csv') {
        exportAsCSV($report);
    } else {
        jsonResponse(['success' => true, 'report' => $report, 'export_format' => $format]);
    }
}

function handleRegenerateReport() {
    $auth = checkAuth();
    $data = getJsonInput();
    $assessmentId = intval($data['assessment_id'] ?? 0);
    if (!$assessmentId) jsonError('ID оценки обязателен');
    
    $db = Database::getInstance();
    $assessment = $db->fetchOne("SELECT * FROM assessments WHERE id = ?", [$assessmentId]);
    $answers = $db->fetchAll("SELECT * FROM answers WHERE assessment_id = ? ORDER BY id", [$assessmentId]);
    
    if (!$assessment || empty($answers)) {
        jsonError('Недостаточно данных для генерации отчёта', 400);
    }
    
    $db->query("DELETE FROM skill_matrix WHERE assessment_id = ?", [$assessmentId]);
    generateFinalReport($assessmentId, $assessment, $answers);
    
    $skillMatrix = $db->fetchAll("SELECT * FROM skill_matrix WHERE assessment_id = ? ORDER BY skill_type, skill_name", [$assessmentId]);
    $report = buildReportData($assessment, $answers, $skillMatrix);
    
    jsonResponse(['success' => true, 'message' => 'Отчёт перегенерирован', 'report' => $report]);
}

function buildReportData($assessment, $answers, $skillMatrix) {
    $totalScore = $assessment['total_score'] ?? 0;
    $maxScore = $assessment['max_possible_score'] ?? 1;
    $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100) : 0;
    
    $level = $assessment['final_level'] ?? 'junior';
    if (empty($level)) {
        if ($percentage >= 85) $level = 'lead';
        elseif ($percentage >= 70) $level = 'senior';
        elseif ($percentage >= 55) $level = 'middle';
        else $level = 'junior';
    }
    
    $levelNames = [
        'junior' => 'Junior (Начальный)',
        'middle' => 'Middle (Средний)',
        'senior' => 'Senior (Продвинутый)',
        'lead' => 'Lead (Экспертный)'
    ];
    
    $levelDescriptions = [
        'junior' => 'Специалист с базовыми знаниями, требует наставничества.',
        'middle' => 'Самостоятельный специалист, решает комплексные задачи.',
        'senior' => 'Экспертный уровень, менторит junior-специалистов.',
        'lead' => 'Ведущий специалист, определяет стратегию развития.'
    ];
    
    $hardSkills = array_values(array_filter($skillMatrix, fn($s) => $s['skill_type'] === 'hard'));
    $softSkills = array_values(array_filter($skillMatrix, fn($s) => $s['skill_type'] === 'soft'));
    
    $hardAvg = count($hardSkills) > 0 ? round(array_sum(array_column($hardSkills, 'score')) / count($hardSkills), 1) : 0;
    $softAvg = count($softSkills) > 0 ? round(array_sum(array_column($softSkills, 'score')) / count($softSkills), 1) : 0;
    
    $strengths = [];
    $weaknesses = [];
    
    foreach ($skillMatrix as $skill) {
        if ($skill['score'] >= 7) {
            $strengths[] = ['name' => $skill['skill_name'], 'score' => $skill['score'], 'description' => $skill['description']];
        } elseif ($skill['score'] <= 5) {
            $weaknesses[] = ['name' => $skill['skill_name'], 'score' => $skill['score'], 'recommendation' => $skill['recommendations']];
        }
    }
    
    usort($strengths, fn($a, $b) => $b['score'] <=> $a['score']);
    usort($weaknesses, fn($a, $b) => $a['score'] <=> $b['score']);
    
    return [
        'assessment_info' => [
            'id' => $assessment['id'],
            'full_name' => $assessment['full_name'],
            'position' => $assessment['position'],
            'department' => $assessment['department'],
            'experience_years' => $assessment['experience_years'],
            'completed_at' => $assessment['completed_at'],
            'created_at' => $assessment['created_at']
        ],
        'summary' => [
            'total_score' => $totalScore,
            'max_score' => $maxScore,
            'percentage' => $percentage,
            'questions_answered' => count($answers),
            'final_level' => ['code' => $level, 'name' => $levelNames[$level] ?? $level, 'description' => $levelDescriptions[$level] ?? '']
        ],
        'skill_matrix' => [
            'hard' => ['average' => $hardAvg, 'skills' => $hardSkills],
            'soft' => ['average' => $softAvg, 'skills' => $softSkills]
        ],
        'analysis' => [
            'strengths' => array_slice($strengths, 0, 5),
            'weaknesses' => array_slice($weaknesses, 0, 5),
            'hard_vs_soft' => [
                'hard_score' => $hardAvg,
                'soft_score' => $softAvg,
                'balance' => $hardAvg > $softAvg + 2 ? 'hard_dominant' : ($softAvg > $hardAvg + 2 ? 'soft_dominant' : 'balanced')
            ]
        ],
        'answers_detail' => array_map(function($ans) {
            $evaluation = json_decode($ans['ai_evaluation'] ?? '{}', true);
            return [
                'question' => $ans['question_text'],
                'category' => $ans['question_category'],
                'skill_type' => $ans['skill_type'] ?? 'hard',
                'difficulty' => $ans['difficulty_level'],
                'answer' => $ans['answer_text'],
                'score' => $ans['score_earned'],
                'max_score' => $ans['max_score'],
                'feedback' => $evaluation['feedback'] ?? '',
                'covered_topics' => $evaluation['covered_topics'] ?? [],
                'missed_topics' => $evaluation['missed_topics'] ?? [],
                'level_demonstrated' => $evaluation['level_demonstrated'] ?? ''
            ];
        }, $answers)
    ];
}

function generateFinalReport($assessmentId, $assessment, $answers) {
    $ai = new DeepseekAPI();
    $categories = [];
    
    foreach ($answers as $ans) {
        $cat = $ans['question_category'];
        if (!isset($categories[$cat])) {
            $categories[$cat] = ['scores' => [], 'type' => $ans['skill_type'] ?? 'hard'];
        }
        $categories[$cat]['scores'][] = $ans['score_earned'];
    }
    
    $db = Database::getInstance();
    
    foreach ($categories as $cat => $data) {
        $avg = round(array_sum($data['scores']) / count($data['scores']), 1);
        $level = $avg >= 8 ? 'expert' : ($avg >= 6 ? 'advanced' : ($avg >= 4 ? 'intermediate' : 'beginner'));
        
        $db->insert('skill_matrix', [
            'assessment_id' => $assessmentId,
            'skill_name' => $cat,
            'skill_type' => $data['type'],
            'skill_category' => $cat,
            'score' => $avg,
            'max_score' => 10,
            'level' => $level,
            'description' => "Уровень $level в $cat",
            'recommendations' => 'Продолжайте развиваться'
        ]);
    }
    
    $result = $ai->generateReport($assessment, $answers, []);
    
    if (!isset($result['error']) && is_array($result['data'])) {
        $reportData = $result['data'];
        if (!empty($reportData['final_level'])) {
            $db->update('assessments', ['final_level' => $reportData['final_level']], 'id = ?', [$assessmentId]);
        }
    } else {
        $totalScore = array_sum(array_column($answers, 'score_earned'));
        $maxScore = count($answers) * 10;
        $percentage = $maxScore > 0 ? ($totalScore / $maxScore) * 100 : 0;
        
        $level = 'junior';
        if ($percentage >= 85) $level = 'lead';
        elseif ($percentage >= 70) $level = 'senior';
        elseif ($percentage >= 55) $level = 'middle';
        
        $db->update('assessments', ['final_level' => $level], 'id = ?', [$assessmentId]);
    }
}

function exportAsCSV($report) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . $report['assessment_info']['id'] . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, ['Отчёт об оценке']);
    fputcsv($output, ['Имя', $report['assessment_info']['full_name']]);
    fputcsv($output, ['Позиция', $report['assessment_info']['position']]);
    fputcsv($output, ['Уровень', $report['summary']['final_level']['name']]);
    fputcsv($output, ['Общий балл', $report['summary']['percentage'] . '%']);
    fputcsv($output, []);
    
    fputcsv($output, ['Навык', 'Тип', 'Балл', 'Уровень']);
    foreach ($report['skill_matrix']['hard']['skills'] as $skill) {
        fputcsv($output, [$skill['skill_name'], 'Hard', $skill['score'], $skill['level']]);
    }
    foreach ($report['skill_matrix']['soft']['skills'] as $skill) {
        fputcsv($output, [$skill['skill_name'], 'Soft', $skill['score'], $skill['level']]);
    }
    
    fclose($output);
    exit;
}

function getJsonInput() {
    $json = file_get_contents('php://input');
    return json_decode($json, true) ?: [];
}
