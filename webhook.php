<?php
require 'vendor/autoload.php';

// タイムゾーンを日本時間に設定
date_default_timezone_set('Asia/Tokyo');

// 環境変数からデータベースの接続情報を取得
$host = getenv('MYSQL_HOST') ?: 'db';
$db = getenv('MYSQL_DATABASE');
$user = getenv('MYSQL_USER');
$pass = getenv('MYSQL_PASSWORD');
$charset = 'utf8mb4';

// PDOの設定
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = new PDO($dsn, $user, $pass, $options);

// Webhookの内容を取得
$input = file_get_contents('php://input');
$events = json_decode($input, true);

// ログファイルにデータを書き込む
file_put_contents('logs/webhook.log', date('Y-m-d H:i:s') . ' ' . print_r($events, true) . "\n", FILE_APPEND);

foreach ($events['events'] as $event) {
    $eventType = $event['type'];
    file_put_contents('logs/webhook.log', date('Y-m-d H:i:s') . " Event type: $eventType\n", FILE_APPEND);

    if ($eventType == 'follow') {
        $lineUserId = $event['source']['userId'];

        // フォローイベント時の処理
        file_put_contents('logs/webhook.log', date('Y-m-d H:i:s') . " User ID: $lineUserId followed\n", FILE_APPEND);

        // トークンを使用してユーザーIDを特定
        if (isset($_GET['token'])) {
            $token = $_GET['token'];

            $stmt = $pdo->prepare('SELECT user_id FROM pending_links WHERE token = ?');
            $stmt->execute([$token]);
            $pendingUser = $stmt->fetch();

            if ($pendingUser) {
                $userId = $pendingUser['user_id'];

                // DBにLINEユーザーIDとアララユーザーIDを紐づける
                $stmt = $pdo->prepare('UPDATE users SET line_id = ? WHERE id = ?');
                if ($stmt->execute([$lineUserId, $userId])) {
                    file_put_contents('logs/webhook.log', date('Y-m-d H:i:s') . " User ID: $userId and Line ID: $lineUserId linked\n", FILE_APPEND);

                    // pending_linksから削除
                    $stmt = $pdo->prepare('DELETE FROM pending_links WHERE token = ?');
                    $stmt->execute([$token]);
                } else {
                    file_put_contents('logs/webhook.log', date('Y-m-d H:i:s') . " Failed to link User ID: $userId and Line ID: $lineUserId\n", FILE_APPEND);
                }
            } else {
                file_put_contents('logs/webhook.log', date('Y-m-d H:i:s') . " No pending link found for token: $token\n", FILE_APPEND);
            }
        } else {
            file_put_contents('logs/webhook.log', date('Y-m-d H:i:s') . " No token provided\n", FILE_APPEND);
        }
    }
}
