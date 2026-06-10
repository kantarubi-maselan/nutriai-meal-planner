-- ============================================================
--  NutriAI Meal Planner — Full Database Schema
--  Run this in phpMyAdmin or MySQL CLI:
--  mysql -u root -p < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS nutriai_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE nutriai_db;

-- ── 1. USERS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(120)    NOT NULL,
    email         VARCHAR(180)    NOT NULL UNIQUE,
    password_hash VARCHAR(255)    NOT NULL,
    avatar        VARCHAR(255)    DEFAULT NULL,
    email_verified TINYINT(1)     DEFAULT 0,
    reset_token   VARCHAR(100)    DEFAULT NULL,
    reset_expires DATETIME        DEFAULT NULL,
    created_at    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── 2. USER PROFILES ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_profiles (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED NOT NULL UNIQUE,
    age              TINYINT UNSIGNED DEFAULT NULL,
    gender           ENUM('male','female','other') DEFAULT NULL,
    height_cm        DECIMAL(5,1) DEFAULT NULL,
    weight_kg        DECIMAL(5,1) DEFAULT NULL,
    goal_weight_kg   DECIMAL(5,1) DEFAULT NULL,
    fitness_goal     ENUM('weight_loss','weight_gain','maintain','muscle_build') DEFAULT 'maintain',
    activity_level   ENUM('sedentary','light','moderate','active','very_active') DEFAULT 'moderate',
    diet_type        ENUM('normal','halal','vegetarian','vegan','keto','paleo') DEFAULT 'normal',
    daily_calories   SMALLINT UNSIGNED DEFAULT NULL,
    allergies        TEXT DEFAULT NULL,          -- comma-separated
    excluded_foods   TEXT DEFAULT NULL,          -- comma-separated
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 3. MEAL PLANS ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS meal_plans (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    title           VARCHAR(200) DEFAULT 'My Meal Plan',
    goal            ENUM('weight_loss','weight_gain','maintain','muscle_build') DEFAULT 'maintain',
    diet_type       ENUM('normal','halal','vegetarian','vegan','keto','paleo') DEFAULT 'normal',
    target_calories SMALLINT UNSIGNED DEFAULT NULL,
    ingredients     TEXT DEFAULT NULL,           -- user-provided ingredients
    plan_json       LONGTEXT NOT NULL,           -- full AI-generated plan (JSON)
    ai_explanation  TEXT DEFAULT NULL,           -- AI "why this plan" text
    week_start      DATE DEFAULT NULL,
    is_active       TINYINT(1) DEFAULT 1,
    is_saved        TINYINT(1) DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 4. MEALS (individual entries inside a plan) ───────────────
CREATE TABLE IF NOT EXISTS meals (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id      INT UNSIGNED NOT NULL,
    day_number   TINYINT UNSIGNED DEFAULT 1,     -- 1–7
    meal_type    ENUM('breakfast','lunch','dinner','snack') NOT NULL,
    name         VARCHAR(200) NOT NULL,
    description  TEXT DEFAULT NULL,
    calories     SMALLINT UNSIGNED DEFAULT NULL,
    protein_g    DECIMAL(6,1) DEFAULT NULL,
    carbs_g      DECIMAL(6,1) DEFAULT NULL,
    fat_g        DECIMAL(6,1) DEFAULT NULL,
    fiber_g      DECIMAL(6,1) DEFAULT NULL,
    recipe_steps TEXT DEFAULT NULL,              -- JSON array of steps
    ingredients  TEXT DEFAULT NULL,              -- JSON array
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES meal_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 5. GROCERY LISTS ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS grocery_lists (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    plan_id    INT UNSIGNED DEFAULT NULL,
    title      VARCHAR(200) DEFAULT 'Grocery List',
    items_json LONGTEXT NOT NULL,                -- JSON: [{name, qty, unit, category, checked}]
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES meal_plans(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── 6. NUTRITION LOGS ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS nutrition_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    log_date    DATE NOT NULL,
    calories    SMALLINT UNSIGNED DEFAULT 0,
    protein_g   DECIMAL(6,1) DEFAULT 0,
    carbs_g     DECIMAL(6,1) DEFAULT 0,
    fat_g       DECIMAL(6,1) DEFAULT 0,
    fiber_g     DECIMAL(6,1) DEFAULT 0,
    water_ml    SMALLINT UNSIGNED DEFAULT 0,
    notes       TEXT DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_date (user_id, log_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 7. WEIGHT LOGS ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS weight_logs (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    log_date   DATE NOT NULL,
    weight_kg  DECIMAL(5,1) NOT NULL,
    note       VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_date (user_id, log_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 8. AI CHAT HISTORY ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS chat_history (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    role       ENUM('user','assistant') NOT NULL,
    message    TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 9. SAVED PLANS (bookmarks) ───────────────────────────────
CREATE TABLE IF NOT EXISTS saved_plans (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    plan_id    INT UNSIGNED NOT NULL,
    saved_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_plan (user_id, plan_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES meal_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Indexes for performance ───────────────────────────────────
CREATE INDEX idx_meal_plans_user   ON meal_plans(user_id);
CREATE INDEX idx_meals_plan        ON meals(plan_id);
CREATE INDEX idx_chat_user         ON chat_history(user_id);
CREATE INDEX idx_nutrition_user    ON nutrition_logs(user_id, log_date);
CREATE INDEX idx_weight_user       ON weight_logs(user_id, log_date);

-- ── Demo seed (optional) ──────────────────────────────────────
-- INSERT INTO users (name, email, password_hash) VALUES
--   ('Demo User', 'demo@nutriai.com', '$2y$12$...');  -- bcrypt hash