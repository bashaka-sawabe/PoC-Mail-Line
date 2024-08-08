# PoC-Mail-Line

ngrokの公式サイトにアクセスし、アカウントを作成してngrokをダウンロードします。

```
brew install ngrok/ngrok/ngrok
```

サインアップ後のauthtokenを用いて認証


```
ngrok config add-authtoken [authtoken]
```

サーバー起動

```
ngrok http http://localhost:8080
```

```
ngrok by @inconshreveable                                                                                                           (Ctrl+C to quit)

Session Status                online
Account                       Your Account Name (Plan: Free)
Version                       2.3.40
Region                        United States (us)
Web Interface                 http://127.0.0.1:4040
Forwarding                    http://********.ngrok.io -> http://localhost:8080
Forwarding                    https://********.ngrok.io -> http://localhost:8080

Connections                   ttl     opn     rt1     rt5     p50     p90
                              0       0       0.00    0.00    0.00    0.00
```


上記の例では、`https://12345678.ngrok.io` が公開URLです。

LINEログイン設定のコールバックURLに下記を設定します。
https://********/.ngrok.io/callback.php
