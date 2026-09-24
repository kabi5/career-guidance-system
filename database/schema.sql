CREATE DATABASE career_guidance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE career_guidance;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(120) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('learner','teacher','admin') DEFAULT 'learner',
    grade VARCHAR(10),
    school VARCHAR(150),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE learner_profiles (
    user_id INT PRIMARY KEY,
    subjects TEXT,                 -- comma-separated
    maths_mark DECIMAL(5,2),
    science_mark DECIMAL(5,2),
    english_mark DECIMAL(5,2),
    other_marks TEXT,
    aspirations TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    riasec_scores TEXT,           -- JSON: {"R":12,"I":18,...}
    top_interest VARCHAR(20),
    taken_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE recommendations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    career_title VARCHAR(150),
    match_score DECIMAL(5,2),
    explanation TEXT,
    recommended_subjects TEXT,
    pathway TEXT,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE careers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    riasec_code VARCHAR(6),        -- e.g. "IRE"
    required_subjects TEXT,
    skills TEXT,
    demand_level ENUM('low','medium','high') DEFAULT 'medium',
    description TEXT
);

CREATE TABLE assessment_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question TEXT NOT NULL,
    riasec_dimension CHAR(1) NOT NULL,   -- R, I, A, S, E, C
    weight TINYINT DEFAULT 1
);