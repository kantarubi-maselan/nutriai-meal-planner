# 🥗 NutriAI — AI Meal Planner

> A full-stack PHP web application that generates personalised meal plans using AI. Built with a pastel-themed UI, MySQL database, and a complete nutrition tracking suite.

![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=flat&logo=mysql&logoColor=white)

---

## ✨ Features

| Feature | Description |
|---|---|
| 🧠 **AI Meal Generator** | Gemini AI creates personalised daily or weekly meal plans based on your goal, diet type, and available ingredients |
| 🕌 **Diet-Aware** | Supports Halal, Vegetarian, Vegan, Keto, Paleo, and Normal diets |
| 🔥 **Calorie Control** | Set a daily calorie target; AI respects it across every meal |
| 🛒 **Grocery Lists** | Auto-generated shopping lists grouped by category with checkbox tracking |
| 📅 **Weekly Planner** | 7-day calendar view with per-day calorie bars and meal detail drawer |
| 📊 **Nutrition Insights** | Chart.js charts for calorie trend, macros, and weight progress |
| 💾 **Saved Plans** | Save, duplicate, reuse, and delete past AI-generated plans |
| ⚙️ **User Profiles** | Height, weight, BMI calculator, TDEE estimator, allergies, fitness goals |
| 🤖 **AI Chat Assistant** | Conversational Gemini-powered nutritionist — ask anything about food |
| 🔐 **Auth System** | Secure registration, login, and password reset with bcrypt hashing |

---

## 📸 Pages

```
/ ................. Landing page (public)
/login.php ........ Login / Register / Forgot password
/dashboard.php .... Main hub with today's meals & stats
/generator.php .... 5-step AI meal plan generator
/results.php ...... Generated plan with nutrition breakdown
/weekly.php ....... 7-day calendar planner
/grocery.php ...... Grocery list with categories & export
/nutrition.php .... Charts & nutrition logging
/saved-plans.php .. Plan history & management
/profile.php ...... User profile & preferences
/chat.php ......... AI chat assistant
```

---

## 🛠️ Tech Stack

- **Backend** — PHP 8.0+ with PDO (MySQL)
- **Database** — MySQL 5.7+
- **AI** — Gemini API 
- **Frontend** — Vanilla HTML/CSS/JS (no framework)
- **Charts** — Chart.js 4.4 (CDN)
- **Fonts** — Playfair Display + DM Sans (Google Fonts)

---

## 🚀 Getting Started

### Prerequisites

- PHP 8.0 or higher
- MySQL 5.7 or higher
- A web server (Apache / Nginx) or XAMPP / WAMP / Laragon
- `curl` extension enabled in PHP
- An [Gemini API key]

---

### 1. Clone the repository

```bash
git clone https://github.com/YOUR_USERNAME/nutriai-meal-planner.git
cd nutriai-meal-planner
```

---

### 2. Set up the database

Open **phpMyAdmin** (or your MySQL client) and run:

```bash
mysql -u root -p < schema.sql
```

Or paste the contents of `schema.sql` into the phpMyAdmin SQL tab and click **Go**.

This creates the `nutriai_db` database with all 9 tables.

---

### 3. Configure the app

Open `includes/config.php` and update:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');           // your MySQL username
define('DB_PASS', '');               // your MySQL password
define('DB_NAME', 'nutriai_db');

define('GEMINI_API_KEY', 'sk-ant-YOUR_KEY_HERE');  // ← Anthropic API key

define('APP_URL', 'http://localhost/nutriai-meal-planner');  // ← your local URL
```

> ⚠️ **Never commit your real API key.** The `.gitignore` excludes `config.php` — keep it that way.

---

### 4. Serve the project

**Using XAMPP / WAMP / Laragon:**

Place the project folder inside your web root:
- XAMPP → `C:/xampp/htdocs/nutriai-meal-planner`
- WAMP → `C:/wamp64/www/nutriai-meal-planner`
- Laragon → `C:/laragon/www/nutriai-meal-planner`

Then visit: `http://localhost/nutriai-meal-planner`

**Using PHP built-in server:**

```bash
php -S localhost:8000
```

Then visit: `http://localhost:8000`

---

### 5. You're live! 🎉

1. Visit the landing page and click **Get Started Free**
2. Register an account
3. Fill in your **Profile & Preferences** (`/profile.php`)
4. Click **Generate My Plan** and watch Gemini AI work
5. Explore the **Weekly Planner**, **Grocery List**, and **AI Chat**

---

## 📁 Project Structure

```
nutriai-meal-planner/
│
├── index.php               # Landing page
├── login.php               # Login / Register / Forgot password
├── logout.php              # Session destroy
├── dashboard.php           # Main hub
├── generator.php           # Meal plan generator (5-step form)
├── results.php             # Generated plan display
├── weekly.php              # 7-day calendar view
├── grocery.php             # Grocery list manager
├── nutrition.php           # Nutrition charts & logging
├── saved-plans.php         # Saved plan history
├── profile.php             # User profile & preferences
├── chat.php                # AI chat assistant
├── schema.sql              # Full MySQL database schema
│
├── includes/
│   ├── config.php          # DB config, API key, helpers  ← excluded from git
│   ├── config.example.php  # Safe template to commit
│   ├── header.php          # Shared HTML head + sidebar nav
│   └── footer.php          # Shared footer + JS
│
├── api/
│   ├── generate-plan.php   # Gemini API: meal plan generation
│   └── chat-save.php       # Save chat messages to DB
│
└── assets/
    ├── css/
    │   └── style.css       # Global pastel stylesheet
    └── js/
        └── main.js         # Global JS (tabs, toggles, animations)
```

---

## 🗄️ Database Schema

| Table | Purpose |
|---|---|
| `users` | Account credentials and info |
| `user_profiles` | Health stats, goals, diet preferences |
| `meal_plans` | AI-generated plan metadata + raw JSON |
| `meals` | Individual meal entries per plan/day |
| `grocery_lists` | Shopping lists linked to plans |
| `nutrition_logs` | Daily calorie & macro tracking |
| `weight_logs` | Weight history for progress charts |
| `chat_history` | AI chat message history per user |
| `saved_plans` | Bookmarked plan references |

---

## 🔑 Environment & Security

- Passwords are hashed with **bcrypt** (cost 12)
- All user input is sanitised via `htmlspecialchars` before output
- DB queries use **PDO prepared statements** — no SQL injection risk
- The real `config.php` is **excluded from version control** via `.gitignore`
- Copy `includes/config.example.php` → `includes/config.php` and fill in your values



---

## 🙏 Credits

- Charts by [Chart.js](https://chartjs.org)
- Fonts by [Google Fonts](https://fonts.google.com) — Playfair Display & DM Sans

---

*Built with 🥗 *