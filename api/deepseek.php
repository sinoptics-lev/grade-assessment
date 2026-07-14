<?php
/**
 * Grade Assessment - Deepseek API Integration
 */

require_once __DIR__ . '/db.php';

class DeepseekAPI {
    private $apiKey;
    private $apiUrl;
    private $model;
    
    public function __construct() {
        $this->apiKey = DEEPSEEK_API_KEY_FINAL;
        $this->apiUrl = DEEPSEEK_API_URL;
        $this->model = DEEPSEEK_MODEL;
    }
    
    private function callAPI($messages, $temperature = 0.7, $maxTokens = 2000) {
        if (empty($this->apiKey)) {
            return ['error' => 'API ключ Deepseek не настроен'];
        }
        
        $data = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'response_format' => ['type' => 'json_object']
        ];
        
        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $startTime = microtime(true);
        $response = curl_exec($ch);
        $duration = round((microtime(true) - $startTime) * 1000);
        
        if (curl_errno($ch)) {
            curl_close($ch);
            return ['error' => 'Ошибка соединения: ' . curl_error($ch)];
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return ['error' => 'API вернул ошибку HTTP ' . $httpCode, 'response' => $response];
        }
        
        $result = json_decode($response, true);
        if (!isset($result['choices'][0]['message']['content'])) {
            return ['error' => 'Неверный ответ от API', 'response' => $response];
        }
        
        $content = $result['choices'][0]['message']['content'];
        $parsed = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            if (preg_match('/\{.*\}/s', $content, $matches)) {
                $parsed = json_decode($matches[0], true);
            }
        }
        
        return [
            'success' => true,
            'data' => $parsed ?: $content,
            'raw' => $content,
            'duration_ms' => $duration,
            'usage' => $result['usage'] ?? null
        ];
    }
    
    public function generateFirstQuestion($position, $experience) {
        $systemPrompt = $this->getSystemPrompt();
        
        $userPrompt = "Начни оценку $position с опытом работы $experience лет. 
Сгенерируй ПЕРВЫЙ вопрос для определения уровня специалиста (junior/middle/senior/lead).
Верни JSON с полями: question, category, subcategory, difficulty, expected_topics, skill_type";

        return $this->callAPI([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ], 0.8);
    }
    
    public function generateNextQuestion($assessmentData, $previousAnswers) {
        $systemPrompt = $this->getSystemPrompt();
        
        $context = $this->buildContext($previousAnswers);
        $detectedLevel = $this->detectLevel($previousAnswers);
        
        $userPrompt = "Продолжи оценку специалиста на позицию {$assessmentData['position']}.
Текущий предполагаемый уровень: $detectedLevel.
Пройдено вопросов: " . count($previousAnswers) . "/15.
Контекст предыдущих ответов:\n$context\n
Сгенерируй СЛЕДУЮЩИЙ вопрос. Верни JSON с полями: question, category, subcategory, difficulty, expected_topics, skill_type, reasoning";

        return $this->callAPI([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ], 0.75);
    }
    
    public function evaluateAnswer($question, $answer, $expectedTopics, $difficulty) {
        $systemPrompt = "Ты эксперт по оценке компетенций аналитиков. Оцени ответ строго и объективно.";
        
        $userPrompt = "Оцени ответ по 10-балльной шкале.\n\nВопрос: $question\nОжидаемые темы: " . implode(', ', $expectedTopics) . "\nСложность: $difficulty/4\n\nОтвет: $answer\n\nВерни JSON: score (0-10), is_correct, feedback, covered_topics, missed_topics, level_demonstrated";

        return $this->callAPI([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ], 0.5, 1500);
    }
    
    public function generateReport($assessmentData, $allAnswers, $skillMatrix) {
        $systemPrompt = "Ты HR-эксперт. Создай подробный отчёт об оценке на русском языке.";
        
        $answersSummary = '';
        foreach ($allAnswers as $i => $ans) {
            $answersSummary .= ($i+1) . ". [{$ans['skill_type']}] {$ans['question_category']} — {$ans['score_earned']}/{$ans['max_score']}\n";
        }
        
        $userPrompt = "Создай отчёт. Данные:\n- Позиция: {$assessmentData['position']}\n- Опыт: {$assessmentData['experience_years']} лет\n- Балл: {$assessmentData['total_score']}/{$assessmentData['max_possible_score']}\n\nРезультаты:\n$answersSummary\n\nВерни JSON с полями: final_level, level_description, overall_summary, strengths[], weaknesses[], development_plan[], skill_matrix {hard{}, soft{}}, next_steps[]";

        return $this->callAPI([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ], 0.6, 3000);
    }
    
    private function getSystemPrompt() {
        return "Ты — экспертная система оценки компетенций бизнес- и системных аналитиков. 

Категории hard skills: сбор и анализ требований, моделирование процессов (BPMN, UML), прототипирование, работа с данными (SQL), техническая грамотность (API, архитектура), документирование (ТЗ, user stories).

Категории soft skills: коммуникация, управление стейкхолдерами, критическое мышление, презентация результатов, обучаемость.

Уровни: 1-Junior, 2-Middle, 3-Senior, 4-Lead.

Всегда отвечай на русском. Верни только JSON без дополнительного текста.";
    }
    
    private function buildContext($answers) {
        $context = '';
        foreach ($answers as $ans) {
            $context .= "[{$ans['skill_type']}] (сложность {$ans['difficulty_level']}): {$ans['question_text']}\n";
            $context .= "Оценка: {$ans['score_earned']}/{$ans['max_score']}\n\n";
        }
        return $context;
    }
    
    private function detectLevel($answers) {
        if (empty($answers)) return 'junior';
        
        $avgScore = array_sum(array_column($answers, 'score_earned')) / count($answers);
        $avgDifficulty = array_sum(array_column($answers, 'difficulty_level')) / count($answers);
        
        if ($avgScore >= 8 && $avgDifficulty >= 3) return 'lead';
        if ($avgScore >= 7 && $avgDifficulty >= 2.5) return 'senior';
        if ($avgScore >= 6 && $avgDifficulty >= 2) return 'middle';
        return 'junior';
    }
}

function logAIRequest($assessmentId, $type, $request, $response, $error = null, $duration = 0) {
    try {
        $db = Database::getInstance();
        $db->insert('ai_logs', [
            'assessment_id' => $assessmentId,
            'request_type' => $type,
            'request_data' => json_encode($request, JSON_UNESCAPED_UNICODE),
            'response_data' => json_encode($response, JSON_UNESCAPED_UNICODE),
            'error_message' => $error,
            'duration_ms' => $duration
        ]);
    } catch (Exception $e) {}
}
