<?php
date_default_timezone_set('Asia/Tokyo');
session_start();

// デバッグ: セッション内容をログに出力
file_put_contents('logs/session.log', gmdate('Y-m-d H:i:s')." SESSION ID: ".session_id()."\n".print_r($_SESSION, true), FILE_APPEND);

$client_id = getenv('LINE_CLIENT_ID');
$client_secret = getenv('LINE_CLIENT_SECRET');
$redirect_uri = getenv('BASE_URL') . '/callback.php'; // BASE_URLを使用
$code = $_GET['code'];
$state = $_GET['state'];

// stateの検証
if ($_SESSION['state'] !== $state) {
    echo "Invalid state";
    exit;
}

// トークン取得
$token_url = 'https://api.line.me/oauth2/v2.1/token';
$data = [
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => $redirect_uri,
    'client_id' => $client_id,
    'client_secret' => $client_secret
];

$options = [
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query($data)
    ]
];
$context = stream_context_create($options);
$response = file_get_contents($token_url, false, $context);
$json = json_decode($response, true);

$id_token = $json['id_token'];
$access_token = $json['access_token'];

// IDトークンの検証
$segments = explode('.', $id_token);
$header = json_decode(base64_decode($segments[0]), true);
$payload = json_decode(base64_decode($segments[1]), true);

// デバッグ: nonceの検証
file_put_contents('logs/nonce.log', gmdate('Y-m-d H:i:s')." SESSION NONCE: ".$_SESSION['nonce']."\nPAYLOAD NONCE: ".(isset($payload['nonce']) ? $payload['nonce'] : "NULL")."\n", FILE_APPEND);

if (!isset($payload['nonce']) || $payload['nonce'] !== $_SESSION['nonce']) {
    echo "Invalid nonce";
    exit;
}

// LINEユーザー情報取得
$user_info = json_decode(base64_decode($segments[1]), true);
$line_id = $user_info['sub'];

// 友達追加確認
$friend_check_url = 'https://api.line.me/v2/bot/profile/' . $line_id;
$headers = [
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json'
];

$ch = curl_init($friend_check_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$friend_response = curl_exec($ch);
curl_close($ch);

$friend_data = json_decode($friend_response, true);

if (isset($friend_data['userId'])) {
    // プロフィール取得リクエスト
    $profile_url = 'https://api.line.me/v2/bot/profile/' . $line_id;
    $ch = curl_init($profile_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $profile_response = curl_exec($ch);
    curl_close($ch);

    $profile_data = json_decode($profile_response, true);

    if (isset($profile_data['userId'])) {
        $line_user_id = $profile_data['userId'];

        // 自社システムのユーザーと紐付け
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

        $userId = $_SESSION['user_id'];

        $sql = "UPDATE users SET line_id = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$line_user_id, $userId]);

        if ($stmt->rowCount() > 0) {
            echo "LINEアカウントが正常に紐付けられました。";
        } else {
            echo "ユーザーが見つかりませんでした。";
        }

        // ステートメントを明示的に破棄
        $stmt = null;
        $pdo = null;
    } else {
        echo "LINEプロフィールの取得に失敗しました。";
    }
} else {
    echo "友達追加されていません。";
}
