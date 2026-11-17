-- ===== AI COURSE RECOMMENDER DATABASE SCHEMA =====
-- Run this file to create all necessary tables

-- Create database (if not exists)
CREATE DATABASE IF NOT EXISTS ai_courses CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ai_courses;

-- ===== USERS TABLE =====
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    skill_level ENUM('Beginner', 'Intermediate', 'Advanced') DEFAULT 'Beginner',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,
    INDEX idx_email (email),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== LEARNING PLANS TABLE =====
CREATE TABLE IF NOT EXISTS learning_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    domain VARCHAR(255) NOT NULL,
    skill_level ENUM('Beginner', 'Intermediate', 'Advanced') NOT NULL,
    weekly_hours VARCHAR(50) NOT NULL,
    goal TEXT NOT NULL,
    plan_data JSON NOT NULL,
    is_active TINYINT(1) DEFAULT 0,
    is_favorite TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== PROGRESS TRACKING TABLE =====
CREATE TABLE IF NOT EXISTS progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    step_index INT NOT NULL,
    topic_index INT NOT NULL,
    completed TINYINT(1) DEFAULT 0,
    completed_at TIMESTAMP NULL,
    notes TEXT,
    difficulty_rating TINYINT CHECK (difficulty_rating BETWEEN 1 AND 5),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES learning_plans(id) ON DELETE CASCADE,
    UNIQUE KEY unique_progress (plan_id, step_index, topic_index),
    INDEX idx_user_id (user_id),
    INDEX idx_plan_id (plan_id),
    INDEX idx_completed (completed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== SAVED RESOURCES TABLE =====
CREATE TABLE IF NOT EXISTS saved_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NULL,
    title VARCHAR(255) NOT NULL,
    url TEXT NOT NULL,
    resource_type ENUM('video', 'article', 'course', 'documentation', 'other') DEFAULT 'other',
    description TEXT,
    is_favorite TINYINT(1) DEFAULT 0,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES learning_plans(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_plan_id (plan_id),
    INDEX idx_resource_type (resource_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== USER NOTES TABLE =====
CREATE TABLE IF NOT EXISTS user_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    step_index INT NOT NULL,
    topic_index INT,
    note_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES learning_plans(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_plan_id (plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== SESSIONS TABLE (optional, for better session management) =====
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    user_data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_expires_at (expires_at),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== INSERT SAMPLE USER (for testing) =====
-- Demo login: demo@example.com / password  (for local testing only - change or delete before real use!)
INSERT INTO users (email, password_hash, name, skill_level) VALUES 
('demo@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Demo User', 'Beginner')
ON DUPLICATE KEY UPDATE email=email;

-- ===== VIEWS FOR ANALYTICS =====
CREATE OR REPLACE VIEW user_progress_summary AS
SELECT 
    u.id AS user_id,
    u.name,
    u.email,
    COUNT(DISTINCT lp.id) AS total_plans,
    COUNT(DISTINCT CASE WHEN lp.is_active = 1 THEN lp.id END) AS active_plans,
    COUNT(DISTINCT p.id) AS total_topics,
    COUNT(DISTINCT CASE WHEN p.completed = 1 THEN p.id END) AS completed_topics,
    ROUND(COUNT(DISTINCT CASE WHEN p.completed = 1 THEN p.id END) * 100.0 / NULLIF(COUNT(DISTINCT p.id), 0), 2) AS completion_percentage
FROM users u
LEFT JOIN learning_plans lp ON u.id = lp.user_id
LEFT JOIN progress p ON u.id = p.user_id
GROUP BY u.id, u.name, u.email;

-- ===== QUIZZES TABLE =====
-- quiz_type: 'topic' (5Q), 'step' (10Q), 'plan' (50Q)
-- step_index = -1 for plan-level quiz; topic_index = -1 for step/plan-level quiz
CREATE TABLE IF NOT EXISTS quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    quiz_type ENUM('topic','step','plan') NOT NULL DEFAULT 'step',
    step_index INT NOT NULL DEFAULT -1,
    topic_index INT NOT NULL DEFAULT -1,
    questions JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES learning_plans(id) ON DELETE CASCADE,
    UNIQUE KEY unique_quiz (plan_id, quiz_type, step_index, topic_index),
    INDEX idx_plan_id (plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== QUIZ ATTEMPTS TABLE =====
CREATE TABLE IF NOT EXISTS quiz_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    user_id INT NOT NULL,
    answers JSON NOT NULL,
    score INT NOT NULL,
    total_questions INT NOT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_quiz_id (quiz_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== ANSWER LOG TABLE =====
-- Permanent per-question answer history. Never deleted — survives quiz regeneration.
-- Used for weak/strong area analysis across ALL attempts ever made.
CREATE TABLE IF NOT EXISTS answer_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    quiz_type ENUM('topic','step','plan') NOT NULL,
    step_index INT NOT NULL DEFAULT -1,
    topic_index INT NOT NULL DEFAULT -1,
    question_text TEXT NOT NULL,
    correct_answer TINYINT NOT NULL,
    user_answer TINYINT NOT NULL,
    is_correct TINYINT(1) NOT NULL,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES learning_plans(id) ON DELETE CASCADE,
    INDEX idx_user_plan (user_id, plan_id),
    INDEX idx_answered_at (answered_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== SUCCESS MESSAGE =====
SELECT 'Database schema created successfully! ✓' AS message;