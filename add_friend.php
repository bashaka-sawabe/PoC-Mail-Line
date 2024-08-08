<?php
date_default_timezone_set('Asia/Tokyo');
session_start();

// デバッグ: セッション内容をログに出力
file_put_contents('logs/session.log', gmdate('Y-m-d H:i:s')." SESSION ID: ".session_id()."\n".print_r($_SESSION, true), FILE_APPEND);

if (isset($_GET['user_id']) && isset($_GET['token'])) {
    $userId = $_GET['user_id'];
    $token = $_GET['token'];

    // トークンの検証
    if (isset($_SESSION['tokens'][$userId]) && $_SESSION['tokens'][$userId] === $token) {
        // LINEのOAuth認証ページにリダイレクト
        $client_id = getenv('LINE_CLIENT_ID');
        $redirect_uri = urlencode(getenv('BASE_URL') . '/callback.php'); // BASE_URLを使用
        $state = bin2hex(random_bytes(16)); // CSRF対策用のランダムな文字列
        $nonce = bin2hex(random_bytes(16)); // Replay Attack対策用のランダムな文字列

        $_SESSION['state'] = $state;
        $_SESSION['nonce'] = $nonce;
        $_SESSION['user_id'] = $userId; // ユーザーIDをセッションに保存

        // デバッグ: セッション内容をログに出力
        file_put_contents('logs/session.log', gmdate('Y-m-d H:i:s')." after setting state and nonce SESSION ID: ".session_id()."\n".print_r($_SESSION, true), FILE_APPEND);

        $line_auth_url = "https://access.line.me/oauth2/v2.1/authorize?response_type=code&client_id={$client_id}&redirect_uri={$redirect_uri}&state={$state}&scope=profile%20openid%20email&nonce={$nonce}";

        header("Location: {$line_auth_url}");
        exit;
    } else {
        echo 'Invalid token';
    }
} else {
    echo 'User ID and token are required';
}
