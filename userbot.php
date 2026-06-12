<?php
require_once 'config.php';
date_default_timezone_set('Asia/Tashkent');

// Browser yopilsa ham script ishlashda davom etishi uchun (Shared hosting qoidasi)
ignore_user_abort(true);
set_time_limit(0);

function logU($msg)
{
    // Log yozish tizimi o'chirib qo'yildi (Dastur tezligini va xotirani tejash uchun)
}

// Xavfsizlik: Boshqalar Queue ni ishga tushurmasligi uchun
$isQueue = (isset($_GET['action']) && $_GET['action'] === 'process_queue' && isset($_GET['key']) && $_GET['key'] === md5(BOT_TOKEN));

if (!file_exists('madeline.php')) {
    if (!$isQueue)
        echo "<h2>MadelineProto avtomatik yuklanmoqda... Kuting (10-20 soniya ketishi mumkin)</h2>";
    copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
}
include 'madeline.php';

$settings = new \danog\MadelineProto\Settings();
$settings->getLogger()->setLevel(\danog\MadelineProto\Logger::NOTICE);

$MadelineProto = new \danog\MadelineProto\API('session.madeline', $settings);

// Asosiy Web Avtorizatsiya!
// Agar siz brauzerdan ulasangiz, u o'zining chiroyli Login oynasini ko'rsatadi.
$MadelineProto->start();

// Qolgan jarayonlar faqatgina bot orqali Queue ishlaganda chaqiriladi
if ($isQueue) {
    $queueFile = __DIR__ . '/cache/queue.json';

    if (!file_exists($queueFile)) {
        exit;
    }

    $queueData = file_get_contents($queueFile);
    $queue = json_decode($queueData, true) ?? [];

    if (empty($queue)) {
        logU("Queue is empty.");
        exit;
    }

    // Clear queue to allow new jobs
    file_put_contents($queueFile, json_encode([]));

    foreach ($queue as $job) {
        try {
            $botUsername = '@postmakerAutobot';

            // Xabarni aniq shu chatdan qidirib topish uchun getHistory ishlatamiz.
            // Eng so'nggi 10 ta xabarni olib, ichidan ID ni izlaymiz (bu eng to'g'ri va xavfsiz usul!).
            $history = $MadelineProto->messages->getHistory([
                'peer' => $botUsername,
                'limit' => 10
            ]);

            $msgObj = null;
            if (!empty($history['messages'])) {
                foreach ($history['messages'] as $m) {
                    // ID raqamlar orqali qidirmaymiz, chunki Bot o'zicha xabarni "35" desa,
                    // UserBot unga o'zining "356088" ID'sini yopishtirgan bo'ladi!
                    // Eng ishonchli usul - eng oxirgi tashlangan VIDEO (media) ni olish!
                    if (isset($m['media'])) {
                        $msgObj = $m;
                        break;
                    }
                }
            }

            if (!$msgObj) {
                logU("Error: Chatda hech qanday video (media) topilmadi. DUMP: " . json_encode($history));
                continue;
            }

            if (!isset($msgObj['media'])) {
                logU("Error: Xabarda video yoki media yo'q. DUMP: " . json_encode($msgObj));
                continue;
            }

            // Telegram HTML parser qoidasiga ko'ra <tg-emoji> hecham <a> (silka) tegining ichida bo'lishi mumkin emas. Ikkita har xil turdagi funksiya ustma-ust tushishi ulardan birini o'chiradi.
            // Shuning uchun Premium Emojini silka yozuvidan tashqariga olib chiqdik!
            $btnEmoji = '<tg-emoji emoji-id="5188234920639632382">🟢</tg-emoji>';
            $finalCaption = $job['caption'] . "\n\n👉 {$btnEmoji} <a href=\"https://t.me/postmakerAutobot?start=" . $job['newId'] . "\"><b>TOMOSHA QILISH</b></a> {$btnEmoji}";

            $MadelineProto->messages->sendMedia([
                'peer' => CHANNEL_ID,
                'media' => $msgObj['media'],
                'message' => $finalCaption,
                'parse_mode' => 'HTML'
            ]);

            logU("✅ Muvaffaqiyatli kanalga tushdi as UserBot: " . CHANNEL_ID);

            $MadelineProto->messages->sendMessage([
                'peer' => $botUsername,
                'message' => "⚡️ **USERBOT ORQALI KANALGA YETIB BORDI!** ID: " . $job['newId'] . "\n\nEndi postda barcha Premium emojilar ochiq holatda!",
                'parse_mode' => 'Markdown'
            ]);

        } catch (\Exception $e) {
            logU("❌ XATOLIK: " . $e->getMessage());
        }
    }

    exit;
}

// Web UI orqali Login muvaffaqiyatli bo'lsa
if (!$isQueue) {
    echo "<h1>✅ MUVAFAQIYATLI! MadelineProto hisobingiz tizimga kirdi va sessiya saqlandi!</h1>";
    echo "<p>Agar Web Login oynasida hamma narsani tasdiqlagan bo'lsangiz, bu oyna brauzeringizda qolishi umuman shart emas. Yopib yuborishingiz mumkin!</p>";
    echo "<b>Kino bot testingizni boshlashingiz mumkin...</b>";
}
