<?php

// Configuration Settings for Telegram Bot & Gemini API

// Telegram Bot details
define('BOT_TOKEN', 'your_bot_token');
define('ADMIN_ID', 123456789); // Replace with your actual Admin Telegram ID
define('CHANNEL_ID', '@unifinduz'); // or -100XXXXXXX

// Gemini API Key
define('GEMINI_API_KEY', 'your_api_key');

// Database details
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_db_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_pass');

// Emoji Settings (Fallbacks provided if client doesn't support premium)
// Replace with your actual Premium Emoji IDs
define('EMOJI_MOVIE', '5375464961822695044'); // fallback: 🎬 or Premium ID
define('EMOJI_COUNTRY', '5188381825701021648');
define('EMOJI_LANG', '5454310635707853545');
define('EMOJI_GENRE', '5987802868734760945');
define('EMOJI_QUALITY', '5899757765743615694');
define('EMOJI_YEAR', '5967782394080530708');
define('EMOJI_IMDB', '5346242859039209592');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
