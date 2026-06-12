<?php
require_once 'config.php';

// Batafsil log yozish uchun maxsus funksiya
function writeLog($message, $data = null) {
    // Log yozish tizimi o'chirib qo'yildi (Dastur tezligini va xotirani tejash uchun)
    // Agar kelajakda xatolik chiqib qolsa, shu funksiya ichiga qatorlarni qaytarishingiz mumkin.
}

// Useful function to make Telegram API requests
function sendTelegramRequest($method, $data = []) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $decoded = json_decode($response, true);
    return $decoded;
}

// 1. Webhook parsing
$rawUpdate = file_get_contents('php://input');
$update = json_decode($rawUpdate, true);

if (!$update) {
    exit;
}

if (!isset($update['message'])) {
    exit;
}

$message = $update['message'];

// 2. Security checks
if (!isset($message['chat']['type']) || $message['chat']['type'] !== 'private') {
    exit;
}

if (!isset($message['from']['id']) || $message['from']['id'] != ADMIN_ID) {
    exit;
}

// Check if only text was sent
if (!isset($message['video']) || !isset($message['caption'])) {
    
    if (isset($message['text'])) {
        if (strpos($message['text'], '/start') === 0) {
            sendTelegramRequest('sendMessage', [
                'chat_id' => ADMIN_ID,
                'text' => "🎬 Salom Admin!\n\nKino joylash uchun menga **video faylni** yuboring va **caption (izoh)** qismiga kinoning to'liq nomini yozing."
            ]);
        } else {
            sendTelegramRequest('sendMessage', [
                'chat_id' => ADMIN_ID,
                'text' => "❌ Iltimos, kino nomini shunchaki xabar qilib yozmang!\n\n**VIDEO FAYL** yuboring va kino nomini o'sha videoning **izohi (caption)** qismiga yozing."
            ]);
        }
    } else {
    }
    exit; // Agar video va caption bo'lmasa, dasturni to'xtatish
}

// 3. Extract Video Data & Title
$file_id = $message['video']['file_id'];
$caption = $message['caption'];
$title = trim(preg_replace('/\(\d{4}\)/', '', $caption));

// 4. Cache Mechanism
$cacheFile = __DIR__ . '/cache/movies.json';
if (!file_exists($cacheFile)) {
    if (!is_dir(__DIR__ . '/cache')) {
        mkdir(__DIR__ . '/cache', 0755, true);
    }
    file_put_contents($cacheFile, json_encode([]));
}

$cacheContent = file_get_contents($cacheFile);
$cacheData = json_decode($cacheContent, true) ?? [];

$movieMetadata = null;

if (isset($cacheData[$title])) {
    // Read from Cache
    $movieMetadata = $cacheData[$title];
} else {
    // 5. Gemini API Interaction
    
    $geminiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=" . GEMINI_API_KEY;
    
    $prompt = "You are a movie database AI.\n\nFind information about the movie: \"$title\"\n\nReturn ONLY JSON in this format:\n{\n  \"title\": \"\",\n  \"country\": \"\",\n  \"year\": \"\",\n  \"genre\": \"\",\n  \"rating\": \"\",\n  \"plot_uz\": \"\"\n}\n\nRules:\n- All fields MUST be in Uzbek language\n- \"plot_uz\" must be a short Uzbek description (max 3-4 sentences)\n- If data not found, return \"Noma'lum\"\n- Do NOT include any explanation\n- JSON only";

    $geminiPayload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.4,
            "maxOutputTokens" => 1500
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $geminiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($geminiPayload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $responseJson = curl_exec($ch);
    $geminiHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $responseData = json_decode($responseJson, true);
    
    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        $text = $responseData['candidates'][0]['content']['parts'][0]['text'];
        
        // Clean JSON format issues
        $text = trim($text);
        $text = preg_replace('/```json|```/', '', $text);
        $movieData = json_decode($text, true);
        
        if ($movieData && is_array($movieData) && isset($movieData['title'])) {
            $movieMetadata = $movieData;
            
            // Save to JSON cache
            $cacheData[$title] = $movieMetadata;
            file_put_contents($cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
        }
    } else {
    }

    if (!$movieMetadata) {
        // Fallback Data if Gemini Fails
        $movieMetadata = [
            "title" => $title,
            "country" => "Noma'lum",
            "year" => "Noma'lum",
            "genre" => "Noma'lum",
            "rating" => "-",
            "plot_uz" => "Ma'lumot topilmadi"
        ];
    }
}

// 6. DB - Safe Increment using Transaction
$newId = 1357; // Default Fallback
try {
    $pdo->beginTransaction();
    $stmt = $pdo->query("SELECT last_movie_id FROM settings FOR UPDATE");
    $row = $stmt->fetch();
    
    if ($row) {
        $currentId = (int)$row['last_movie_id'];
        $newId = $currentId + 1;
        $pdo->query("UPDATE settings SET last_movie_id = $newId");
    } else {
        $pdo->query("INSERT INTO settings (id, last_movie_id) VALUES (1, $newId)");
    }
    
    $pdo->commit();
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $newId = rand(2000, 9999); 
}

// 7. Format caption safely and support fallback standard emojis 
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

$meta_title = e($movieMetadata['title'] ?: $title);
$meta_country = e($movieMetadata['country']);
$meta_genre = e($movieMetadata['genre']);
$meta_year = e($movieMetadata['year']);
$meta_rating = e($movieMetadata['rating']);
$meta_plot = e($movieMetadata['plot_uz']);

function renderEmoji($emojiId, $fallbackText) {
    if (is_numeric($emojiId)) {
        return '<tg-emoji emoji-id="' . $emojiId . '">' . $fallbackText . '</tg-emoji>';
    }
    return $fallbackText;
}

$captionText = <<<HTML
__MOVIE__ Nomi: {$meta_title}
━━━━━━━━━━━━━━━
__COUNTRY__ Davlati: {$meta_country}
__LANG__ Tili: O‘zbek
__GENRE__ Janr: {$meta_genre}
__QUALITY__ Sifati: 1080p
__YEAR__ Yili: {$meta_year}
__IMDB__ IMDb: {$meta_rating}

Kino haqida <tg-emoji emoji-id="5386723342415836997">👇</tg-emoji>

<blockquote>
{$meta_plot}
</blockquote>
HTML;

$captionText = strtr($captionText, [
    '__MOVIE__' => renderEmoji(EMOJI_MOVIE, '🎬'),
    '__COUNTRY__' => renderEmoji(EMOJI_COUNTRY, '🌍'),
    '__LANG__' => renderEmoji(EMOJI_LANG, '🇺🇿'),
    '__GENRE__' => renderEmoji(EMOJI_GENRE, '🎭'),
    '__QUALITY__' => renderEmoji(EMOJI_QUALITY, '📀'),
    '__YEAR__' => renderEmoji(EMOJI_YEAR, '📅'),
    '__IMDB__' => renderEmoji(EMOJI_IMDB, '⭐'),
]);

// 8. UserBot Queue tizimiga yuborish
$queueFile = __DIR__ . '/cache/queue.json';
$queueData = file_exists($queueFile) ? json_decode(file_get_contents($queueFile), true) : [];
if (!is_array($queueData)) $queueData = [];

$queueData[] = [
    'message_id' => $message['message_id'],
    'caption' => $captionText,
    'newId' => $newId
];

file_put_contents($queueFile, json_encode($queueData));

// UserBot faylini fonda (background - oynasiz) ishga tushiramiz
// Shared Hosting uchun asinxron HTTP (sayt o'zi o'ziga tashrif buyurishi) chaqiruvi:
$botUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/bot.php';
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$triggerUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($botUri) . "/userbot.php?action=process_queue&key=" . md5(BOT_TOKEN);

$ch2 = curl_init($triggerUrl);
// Faqat 1 soniya kutamiz. UserBot ishga tushib oladi va orqada ishlayveradi (ignore_user_abort tufayli)
curl_setopt($ch2, CURLOPT_TIMEOUT, 1);
curl_setopt($ch2, CURLOPT_NOSIGNAL, 1);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
curl_exec($ch2);
curl_close($ch2);
?>

