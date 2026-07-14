-- Grade Assessment - Database Schema
-- MySQL 8.0+

CREATE TABLE IF NOT EXISTS assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    position VARCHAR(255) NOT NULL,
    department VARCHAR(255),
    experience_years INT DEFAULT 0,
    status ENUM('in_progress', 'completed', 'aborted') DEFAULT 'in_progress',
    final_level VARCHAR(50),
    total_score INT DEFAULT 0,
    max_possible_score INT DEFAULT 0,
    hard_skills_score INT DEFAULT 0,
    soft_skills_score INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_category VARCHAR(100) NOT NULL,
    question_subcategory VARCHAR(100),
    difficulty_level INT NOT NULL DEFAULT 1,
    answer_text TEXT,
    is_correct BOOLEAN DEFAULT NULL,
    score_earned INT DEFAULT 0,
    max_score INT DEFAULT 10,
    ai_evaluation TEXT,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
    INDEX idx_assessment (assessment_id),
    INDEX idx_category (question_category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS skill_matrix (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    skill_name VARCHAR(255) NOT NULL,
    skill_type ENUM('hard', 'soft') NOT NULL,
    skill_category VARCHAR(100) NOT NULL,
    score INT DEFAULT 0,
    max_score INT DEFAULT 10,
    level ENUM('beginner', 'intermediate', 'advanced', 'expert') DEFAULT 'beginner',
    description TEXT,
    recommendations TEXT,
    FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
    INDEX idx_assessment (assessment_id),
    INDEX idx_type (skill_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    email VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS access_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT,
    request_type VARCHAR(100) NOT NULL,
    request_data TEXT,
    response_data TEXT,
    error_message TEXT,
    duration_ms INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_assessment (assessment_id),
    INDEX idx_type (request_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO admins (username, password_hash, full_name) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Администратор');
