<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message Sender</title>
    <link rel="stylesheet" href="styles.css">
    <script>
        function toggleFields() {
            const type = document.querySelector('input[name="send_type"]:checked').value;
            document.getElementById('emailFields').style.display = type === 'email' ? 'block' : 'none';
            document.getElementById('lineFields').style.display = type === 'line' ? 'block' : 'none';
        }
    </script>
</head>
<body>
    <div class="container">
        <h1>Send Message</h1>
        <form action="send.php" method="POST">
            <label>User ID: <input type="number" name="user_id" required></label><br>
            <div class="radio-group">
                <label><input type="radio" name="send_type" value="email" onclick="toggleFields()" checked>Email</label>
                <label><input type="radio" name="send_type" value="line" onclick="toggleFields()">LINE</label>
            </div>
            <div id="emailFields">
                <label>From Email: <input type="email" name="from_email" required></label><br>
                <label>Subject: <input type="text" name="subject" required></label><br>
            </div>
            <div id="lineFields" style="display:none;"></div>
            <label>Message: <textarea name="message" required></textarea></label><br>
            <label>LINE友達追加リンク: <input type="text" name="friend_link" required></label><br>
            <input type="submit" value="Send">
        </form>
        <br>
    </div>
</body>
</html>