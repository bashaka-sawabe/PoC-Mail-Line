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

// デバッグ: トークン取得のレスポンスをログに出力
file_put_contents('logs/response.log', gmdate('Y-m-d H:i:s')." TOKEN RESPONSE: ".print_r($json, true)."\n", FILE_APPEND);

$id_token = $json['id_token'];
$access_token = $json['access_token'];

// IDトークンの検証
$verify_url = 'https://api.line.me/oauth2/v2.1/verify';
$verify_data = [
    'id_token' => $id_token,
    'client_id' => $client_id
];

$options = [
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query($verify_data)
    ]
];
$context = stream_context_create($options);
$verify_response = file_get_contents($verify_url, false, $context);
$verify_json = json_decode($verify_response, true);

// デバッグ: IDトークン検証のレスポンスをログに出力
file_put_contents('logs/response.log', gmdate('Y-m-d H:i:s')." VERIFY RESPONSE: ".print_r($verify_json, true)."\n", FILE_APPEND);

// デバッグ: nonceの検証
file_put_contents('logs/nonce.log', gmdate('Y-m-d H:i:s')." SESSION NONCE: ".$_SESSION['nonce']."\nVERIFY NONCE: ".(isset($verify_json['nonce']) ? $verify_json['nonce'] : "NULL")."\n", FILE_APPEND);

if (!isset($verify_json['nonce']) || $verify_json['nonce'] !== $_SESSION['nonce']) {
    echo "Invalid nonce";
    exit;
}

// LINEユーザー情報取得
$line_id = $verify_json['sub'];

// プロフィール取得リクエスト
$profile_url = 'https://api.line.me/v2/profile';
$headers = [
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json'
];

$ch = curl_init($profile_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$profile_response = curl_exec($ch);
curl_close($ch);

$profile_data = json_decode($profile_response, true);

// デバッグ: プロフィール取得リクエストのレスポンスをログに出力
file_put_contents('logs/response.log', gmdate('Y-m-d H:i:s')." PROFILE RESPONSE: ".print_r($profile_data, true)."\n", FILE_APPEND);

// LINEの公式アカウントの友達でない場合
if (!isset($profile_data['userId']) || empty($profile_data['userId'])) {
  echo "LINEアカウントが友達追加されていないため、紐付けできませんでした。";
  exit;
}

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
