<?php
/**
 * Grade Assessment - Assessment API
 * Управление сессиями оценки, вопросами и ответами
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/deepseek.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

try {
    switch ($path) {
        case 'start':
            handleStart();
            break;
        case 'question':
            handleGetQuestion();
            break;
        case 'answer':
            handleSubmitAnswer();
            break;
        case 'status':
            handleGetStatus();
            break;
        case 'complete':
            handleComplete();
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
 * Начинает новую сессию оценки
 */
function handleStart() {
    $data = getJsonInput();
    
    // Валидация
    if (empty($data['full_name']) || empty($data['position'])) {
        jsonError('Имя и позиция обязательны');
    }
    
    $db = Database::getInstance();
    
    // Создаем запись оценки
    $assessmentId = $db->insert('assessments', [
        'full_name' => trim($data['full_name']),
        'email' => trim($data['email'] ?? ''),
        'position' => trim($data['position']),
        'department' => trim($data['department'] ?? ''),
        'experience_years' => intval($data['experience_years'] ?? 0)
    ]);
    
    // Генерируем токен доступа
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $db->insert('access_tokens', [
        'assessment_id' => $assessmentId,
        'token' => $token,
        'expires_at' => $expires
    ]);
    
    // Генерируем первый вопрос через AI
    $ai = new DeepseekAPI();
    $result = $ai->generateFirstQuestion(
        $data['position'],
        $data['experience_years'] ?? 0
    );
    
    if (isset($result['error'])) {
        // Fallback: используем вопрос по умолчанию
        $question = getDefaultQuestion('hard', 2);
        logAIRequest($assessmentId, 'generate_first_question', $data, $result, $result['error']);
    } else {
        $question = $result['data'];
        logAIRequest($assessmentId, 'generate_first_question', $data, $result, null, $result['duration_ms'] ?? 0);
    }
    
    jsonResponse([
        'success' => true,
        'assessment_id' => $assessmentId,
        'access_token' => $token,
        'question_number' => 1,
        'total_questions' => 15,
        'question' => $question
    ]);
}

/**
 * Получает следующий вопрос
 */
function handleGetQuestion() {
    $data = getJsonInput();
    
    if (empty($data['assessment_id'])) {
        jsonError('ID оценки обязателен');
    }
    
    $db = Database::getInstance();
    $assessmentId = intval($data['assessment_id']);
    
    // Получаем данные оценки
    $assessment = $db->fetchOne("SELECT * FROM assessments WHERE id = ?", [$assessmentId]);
    if (!$assessment) {
        jsonError('Оценка не найдена', 404);
    }
    
    // Получаем предыдущие ответы
    $previousAnswers = $db->fetchAll(
        "SELECT * FROM answers WHERE assessment_id = ? ORDER BY id",
        [$assessmentId]
    );
    
    // Проверяем лимит вопросов
    if (count($previousAnswers) >= 15) {
        jsonResponse([
            'success' => true,
            'completed' => true,
            'message' => 'Все вопросы заданы'
        ]);
    }
    
    // Генерируем следующий вопрос через AI
    $ai = new DeepseekAPI();
    $result = $ai->generateNextQuestion($assessment, $previousAnswers);
    
    if (isset($result['error'])) {
        // Fallback: определяем категорию и сложность на основе предыдущих ответов
        $question = getAdaptiveFallbackQuestion($previousAnswers);
        logAIRequest($assessmentId, 'generate_next_question', $assessment, $result, $result['error']);
    } else {
        $question = $result['data'];
        logAIRequest($assessmentId, 'generate_next_question', $assessment, $result, null, $result['duration_ms'] ?? 0);
    }
    
    jsonResponse([
        'success' => true,
        'question_number' => count($previousAnswers) + 1,
        'total_questions' => 15,
        'question' => $question
    ]);
}

/**
 * Обрабатывает ответ пользователя
 */
function handleSubmitAnswer() {
    $data = getJsonInput();
    
    if (empty($data['assessment_id']) || empty($data['question'])) {
        jsonError('ID оценки и вопрос обязательны');
    }
    
    $db = Database::getInstance();
    $assessmentId = intval($data['assessment_id']);
    
    // Проверяем существование оценки
    $assessment = $db->fetchOne("SELECT * FROM assessments WHERE id = ?", [$assessmentId]);
    if (!$assessment) {
        jsonError('Оценка не найдена', 404);
    }
    
    // Сохраняем ответ
    $answerData = [
        'assessment_id' => $assessmentId,
        'question_text' => $data['question']['question'] ?? $data['question'],
        'question_category' => $data['question']['category'] ?? 'general',
        'question_subcategory' => $data['question']['subcategory'] ?? '',
        'difficulty_level' => intval($data['question']['difficulty'] ?? 2),
        'answer_text' => $data['answer'] ?? '',
        'max_score' => 10
    ];
    
    // Оцениваем ответ через AI
    if (!empty($data['answer'])) {
        $ai = new DeepseekAPI();
        $expectedTopics = $data['question']['expected_topics'] ?? [];
        $difficulty = $data['question']['difficulty'] ?? 2;
        
        $result = $ai->evaluateAnswer(
            $answerData['question_text'],
            $data['answer'],
            $expectedTopics,
            $difficulty
        );
        
        if (!isset($result['error']) && is_array($result['data'])) {
            $evalData = $result['data'];
            $answerData['score_earned'] = intval($evalData['score'] ?? 5);
            $answerData['is_correct'] = ($answerData['score_earned'] >= 6);
            $answerData['ai_evaluation'] = json_encode([
                'feedback' => $evalData['feedback'] ?? '',
                'covered_topics' => $evalData['covered_topics'] ?? [],
                'missed_topics' => $evalData['missed_topics'] ?? [],
                'level_demonstrated' => $evalData['level_demonstrated'] ?? 'middle'
            ], JSON_UNESCAPED_UNICODE);
            
            logAIRequest($assessmentId, 'evaluate_answer', $answerData, $result, null, $result['duration_ms'] ?? 0);
        } else {
            // Ручная оценка если AI недоступен
            $answerData['score_earned'] = estimateScoreManually($data['answer']);
            $answerData['is_correct'] = ($answerData['score_earned'] >= 6);
            logAIRequest($assessmentId, 'evaluate_answer', $answerData, $result, $result['error'] ?? 'Fallback');
        }
    }
    
    $db->insert('answers', $answerData);
    
    // Обновляем общий счет
    updateAssessmentScore($assessmentId);
    
    jsonResponse([
        'success' => true,
        'score' => $answerData['score_earned'],
        'max_score' => $answerData['max_score'],
        'evaluation' => json_decode($answerData['ai_evaluation'] ?? '{}', true),
        'next_question_available' => true
    ]);
}

/**
 * Получает статус оценки
 */
function handleGetStatus() {
    $assessmentId = intval($_GET['assessment_id'] ?? 0);
    
    if (!$assessmentId) {
        jsonError('ID оценки обязателен');
    }
    
    $db = Database::getInstance();
    
    $assessment = $db->fetchOne("SELECT * FROM assessments WHERE id = ?", [$assessmentId]);
    if (!$assessment) {
        jsonError('Оценка не найдена', 404);
    }
    
    $answers = $db->fetchAll(
        "SELECT * FROM answers WHERE assessment_id = ? ORDER BY id",
        [$assessmentId]
    );
    
    $answeredCount = count($answers);
    $totalScore = array_sum(array_column($answers, 'score_earned'));
    $maxScore = $answeredCount * 10;
    
    jsonResponse([
        'success' => true,
        'assessment' => $assessment,
        'progress' => [
            'answered' => $answeredCount,
            'total' => 15,
            'percent' => round($answeredCount / 15 * 100)
        ],
        'current_score' => $totalScore,
        'max_score_so_far' => $maxScore,
        'estimated_level' => estimateCurrentLevel($answers)
    ]);
}

/**
 * Завершает оценку и генерирует отчёт
 */
function handleComplete() {
    $data = getJsonInput();
    $assessmentId = intval($data['assessment_id'] ?? 0);
    
    if (!$assessmentId) {
        jsonError('ID оценки обязателен');
    }
    
    $db = Database::getInstance();
    
    $assessment = $db->fetchOne("SELECT * FROM assessments WHERE id = ?", [$assessmentId]);
    if (!$assessment) {
        jsonError('Оценка не найдена', 404);
    }
    
    // Получаем все ответы
    $answers = $db->fetchAll(
        "SELECT * FROM answers WHERE assessment_id = ? ORDER BY id",
        [$assessmentId]
    );
    
    if (count($answers) < 5) {
        jsonError('Необходимо ответить минимум на 5 вопросов');
    }
    
    // Обновляем статус
    $totalScore = array_sum(array_column($answers, 'score_earned'));
    $maxScore = count($answers) * 10;
    
    // Рассчитываем баллы по типам навыков
    $hardScores = array_filter($answers, fn($a) => ($a['skill_type'] ?? 'hard') === 'hard');
    $softScores = array_filter($answers, fn($a) => ($a['skill_type'] ?? 'hard') === 'soft');
    
    $hardAvg = count($hardScores) > 0 
        ? round(array_sum(array_column($hardScores, 'score_earned')) / count($hardScores) * 10) 
        : 0;
    $softAvg = count($softScores) > 0 
        ? round(array_sum(array_column($softScores, 'score_earned')) / count($softScores) * 10) 
        : 0;
    
    $db->update('assessments', [
        'status' => 'completed',
        'total_score' => $totalScore,
        'max_possible_score' => $maxScore,
        'hard_skills_score' => $hardAvg,
        'soft_skills_score' => $softAvg,
        'completed_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$assessmentId]);
    
    // Генерируем отчёт через AI
    generateFinalReport($assessmentId, $assessment, $answers);
    
    // Получаем токен доступа
    $token = $db->fetchOne(
        "SELECT token FROM access_tokens WHERE assessment_id = ? ORDER BY id DESC LIMIT 1",
        [$assessmentId]
    );
    
    jsonResponse([
        'success' => true,
        'message' => 'Оценка завершена',
        'report_url' => '/report?token=' . ($token['token'] ?? ''),
        'redirect' => '/report/' . ($token['token'] ?? '')
    ]);
}

// ==================== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ====================

function getJsonInput() {
    $json = file_get_contents('php://input');
    return json_decode($json, true) ?: [];
}

function updateAssessmentScore($assessmentId) {
    $db = Database::getInstance();
    $answers = $db->fetchAll(
        "SELECT * FROM answers WHERE assessment_id = ?",
        [$assessmentId]
    );
    
    $totalScore = array_sum(array_column($answers, 'score_earned'));
    $maxScore = count($answers) * 10;
    
    $db->update('assessments', [
        'total_score' => $totalScore,
        'max_possible_score' => $maxScore
    ], 'id = ?', [$assessmentId]);
}

function estimateCurrentLevel($answers) {
    if (empty($answers)) return 'junior';
    
    $avgScore = array_sum(array_column($answers, 'score_earned')) / count($answers);
    $avgDifficulty = array_sum(array_column($answers, 'difficulty_level')) / count($answers);
    
    if ($avgScore >= 8 && $avgDifficulty >= 3) return 'lead';
    if ($avgScore >= 7 && $avgDifficulty >= 2.5) return 'senior';
    if ($avgScore >= 6 && $avgDifficulty >= 2) return 'middle';
    return 'junior';
}

function estimateScoreManually($answer) {
    $length = mb_strlen($answer);
    if ($length < 20) return 2;
    if ($length < 50) return 4;
    if ($length < 100) return 6;
    if ($length < 200) return 8;
    return 9;
}

function generateFinalReport($assessmentId, $assessment, $answers) {
    $ai = new DeepseekAPI();
    
    // Формируем матрицу навыков
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
        
        $skillMatrix[] = [
            'skill_name' => $cat,
            'skill_type' => $data['type'],
            'skill_category' => $cat,
            'score' => $avg,
            'max_score' => 10,
            'level' => $level,
            'description' => getSkillDescription($cat, $level),
            'recommendations' => getSkillRecommendations($cat, $level)
        ];
    }
    
    // Сохраняем матрицу навыков
    $db = Database::getInstance();
    foreach ($skillMatrix as $skill) {
        $db->insert('skill_matrix', array_merge($skill, ['assessment_id' => $assessmentId]));
    }
    
    // Получаем AI-отчёт
    $result = $ai->generateReport($assessment, $answers, $skillMatrix);
    
    if (!isset($result['error']) && is_array($result['data'])) {
        $reportData = $result['data'];
        
        // Обновляем оценку с финальным уровнем
        if (!empty($reportData['final_level'])) {
            $db->update('assessments', [
                'final_level' => $reportData['final_level']
            ], 'id = ?', [$assessmentId]);
        }
        
        logAIRequest($assessmentId, 'generate_report', $assessment, $result, null, $result['duration_ms'] ?? 0);
    } else {
        // Fallback: определяем уровень на основе среднего балла
        $totalScore = array_sum(array_column($answers, 'score_earned'));
        $maxScore = count($answers) * 10;
        $percentage = $maxScore > 0 ? ($totalScore / $maxScore) * 100 : 0;
        
        $level = 'junior';
        if ($percentage >= 85) $level = 'lead';
        elseif ($percentage >= 70) $level = 'senior';
        elseif ($percentage >= 55) $level = 'middle';
        
        $db->update('assessments', ['final_level' => $level], 'id = ?', [$assessmentId]);
        logAIRequest($assessmentId, 'generate_report', $assessment, $result, $result['error'] ?? 'Fallback');
    }
}

function getDefaultQuestion($type, $difficulty) {
    $questions = [
        'hard' => [
            [
                'question' => 'Опишите процесс сбора требований от стейкхолдеров. Какие техники используете и почему?',
                'category' => 'сбор_требований',
                'subcategory' => 'elicitation',
                'difficulty' => 2,
                'expected_topics' => ['интервью', 'опросники', 'workshop', 'observation', 'прототипирование'],
                'skill_type' => 'hard'
            ],
            [
                'question' => 'Чем отличается User Story от Use Case? Приведите примеры использования каждого подхода.',
                'category' => 'документирование',
                'subcategory' => 'user_stories',
                'difficulty' => 2,
                'expected_topics' => ['user story', 'use case', 'формат', 'критерии приемки'],
                'skill_type' => 'hard'
            ]
        ],
        'soft' => [
            [
                'question' => 'Как вы взаимодействуете с командой разработки, когда получаете от них pushback по требованиям?',
                'category' => 'коммуникация',
                'subcategory' => 'negotiation',
                'difficulty' => 2,
                'expected_topics' => ['переговоры', 'компромисс', 'обоснование', 'данные'],
                'skill_type' => 'soft'
            ]
        ]
    ];
    
    $typeQuestions = $questions[$type] ?? $questions['hard'];
    return $typeQuestions[array_rand($typeQuestions)];
}

function getAdaptiveFallbackQuestion($previousAnswers) {
    // Определяем слабые области
    $categoryScores = [];
    foreach ($previousAnswers as $ans) {
        $cat = $ans['question_category'];
        if (!isset($categoryScores[$cat])) {
            $categoryScores[$cat] = ['total' => 0, 'count' => 0];
        }
        $categoryScores[$cat]['total'] += $ans['score_earned'];
        $categoryScores[$cat]['count']++;
    }
    
    // Ищем категорию с низким средним баллом
    $weakCategory = null;
    $minAvg = 11;
    foreach ($categoryScores as $cat => $data) {
        $avg = $data['total'] / $data['count'];
        if ($avg < $minAvg) {
            $minAvg = $avg;
            $weakCategory = $cat;
        }
    }
    
    // Чередуем типы навыков
    $lastHardSoft = end($previousAnswers);
    $nextType = ($lastHardSoft['skill_type'] ?? 'hard') === 'hard' ? 'soft' : 'hard';
    
    return getDefaultQuestion($nextType, $minAvg < 5 ? 1 : 2);
}

function getSkillDescription($skill, $level) {
    $descriptions = [
        'сбор_требований' => [
            'beginner' => 'Базовое понимание техник сбора требований',
            'intermediate' => 'Уверенное применение основных техник',
            'advanced' => 'Мастерское владение всеми техниками',
            'expert' => 'Экспертный уровень, обучает других'
        ],
        'моделирование' => [
            'beginner' => 'Базовые навыки моделирования',
            'intermediate' => 'Уверенное создание диаграмм',
            'advanced' => 'Сложные модели процессов',
            'expert' => 'Архитектурное моделирование'
        ]
    ];
    
    return $descriptions[$skill][$level] ?? "Уровень $level в $skill";
}

function getSkillRecommendations($skill, $level) {
    $recs = [
        'сбор_требований' => [
            'beginner' => 'Пройдите курс по техникам сбора требований, попрактикуйтесь в проведении интервью',
            'intermediate' => 'Изучите продвинутые техники: workshop facilitation, observation',
            'advanced' => 'Освойте управление конфликтными требованиями, научитесь других',
            'expert' => 'Развивайте экспертизу в предметной области'
        ],
        'моделирование' => [
            'beginner' => 'Изучите BPMN и UML нотации, потренируйтесь на простых примерах',
            'intermediate' => 'Освойте сложные паттерны моделирования, инструменты',
            'advanced' => 'Развивайте архитектурное моделирование',
            'expert' => 'Создавайте собственные методологии'
        ]
    ];
    
    return $recs[$skill][$level] ?? 'Продолжайте развиваться в этом направлении';
}
