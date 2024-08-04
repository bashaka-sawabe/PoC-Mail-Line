<?php
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
    $userId = $_POST['user_id'];
    $sendType = $_POST['send_type'];
    $message = $_POST['message'];
    $friendLink = $_POST['friend_link'];

    $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        echo 'User not found';
        exit;
    }

    // ユーザーごとに一意なトークンを生成
    $token = bin2hex(random_bytes(16));
    $friendLink = $friendLink . '?token=' . $token;

    // 生成されたリンクをメール本文に含める
    $message .= "\n\nClick the link to add as friend: " . $friendLink;

    // pending_linksテーブルにリンクとユーザーIDを保存
    $stmt = $pdo->prepare('INSERT INTO pending_links (user_id, token) VALUES (?, ?)');
    $stmt->execute([$userId, $token]);

    if ($sendType === 'email') {
        $toEmail = $user['email'];
        $fromEmail = $_POST['from_email'];
        $subject = $_POST['subject'];

        $mail = new PHPMailer\PHPMailer\PHPMailer();
        $mail->isSMTP();
        $mail->Host = getenv('SMTP_SERVER');
        $mail->Port = getenv('SMTP_PORT');
        $mail->SMTPAuth = false;

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
