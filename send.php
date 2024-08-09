<?php
date_default_timezone_set('Asia/Tokyo');
require 'vendor/autoload.php';

$host = getenv('MYSQL_HOST') ?: 'db';
$db = getenv('MYSQL_DATABASE');
$user = getenv('MYSQL_USER');
$pass = getenv('MYSQL_PASSWORD');
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start(); // セッションを開始

    $userId = $_POST['user_id'];
    $sendType = $_POST['send_type'];
    $message = $_POST['message'];
    $baseUrl = getenv('BASE_URL');

    $stmt = $pdo->prepare('SELECT email, line_id FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    // ステートメントを明示的に破棄
    $stmt = null;

    if (!$user) {
        echo 'User not found';
        exit;
    }

    // ユーザーごとに一意なトークンを生成
    $token = bin2hex(random_bytes(16));
    $friendLink = $baseUrl . '/add_friend.php?user_id=' . $userId . '&token=' . $token;

    // 生成されたリンクをメール本文に含める
    $message .= "\n\nClick the link to add as friend: " . $friendLink;

    // ユーザーごとのトークンを一時的にセッションに保存
    $_SESSION['tokens'][$userId] = $token;

    // デバッグ: セッション内容をログに出力
    file_put_contents('logs/session.log', gmdate('Y-m-d H:i:s')." SESSION ID: ".session_id()."\n".print_r($_SESSION, true), FILE_APPEND);

    if ($sendType === 'email') {
        $toEmail = $user['email'];
        $fromEmail = getenv('SMTP_USER'); // 環境変数から送信者のメールアドレスを取得
        $subject = $_POST['subject'];

        $mail = new PHPMailer\PHPMailer\PHPMailer();
        $mail->isSMTP();
        $mail->Host = getenv('SMTP_SERVER'); // GmailのSMTPサーバー
        $mail->Port = getenv('SMTP_PORT'); // 587 (TLS) または 465 (SSL)
        $mail->SMTPAuth = true; // SMTP認証を有効にする
        $mail->Username = getenv('SMTP_USER'); // Gmailアドレス
        $mail->Password = getenv('SMTP_PASSWORD'); // Gmailのアプリパスワード
        $mail->SMTPSecure = 'tls'; // TLS暗号化を使用

        $mail->SMTPDebug = 3; // デバッグ出力のレベルを設定
        $mail->Debugoutput = function($str, $level) {
            file_put_contents('logs/phpmailer.log', gmdate('Y-m-d H:i:s')."\t".$level.":\t".$str."\n", FILE_APPEND);
        };

        $mail->setFrom($fromEmail);
        $mail->addAddress($toEmail);
        $mail->Subject = $subject;
        $mail->Body = $message;

        if ($mail->send()) {
            echo 'Email sent successfully';
        } else {
            echo 'Email sending failed: ' . $mail->ErrorInfo;
        }
    } elseif ($sendType === 'line') {
        $toLine = $user['line_id'];
        $lineToken = getenv('LINE_CHANNEL_ACCESS_TOKEN');

        $data = [
            'to' => $toLine,
            'messages' => [
                [
                    'type' => 'text',
                    'text' => $message
                ]
            ]
        ];

        $ch = curl_init('https://api.line.me/v2/bot/message/push');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $lineToken
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $result = curl_exec($ch);
        curl_close($ch);

        if ($result) {
            echo 'LINE message sent successfully';
        } else {
            echo 'LINE message sending failed';
        }
    }
} else {
    echo 'Invalid request method';
}
