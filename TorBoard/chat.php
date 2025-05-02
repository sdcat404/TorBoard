<?php
session_start();

// Generate a session-based user ID if not set
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = uniqid();
}

$chatFile = 'uploads/chat.json';
$maxMessages = 50; // Limit to last 50 messages

// Handle message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim(htmlspecialchars($_POST['message']));
    if (strlen($message) < 2 || strlen($message) > 300) { // Set min & max length
    die("Error: Message must be between 2 and 300 characters.");
}

    if (!empty($message)) {
        $messages = file_exists($chatFile) ? json_decode(file_get_contents($chatFile), true) : [];



// Handle message cooldown
$lastMessageTime = $_SESSION['last_message_time'] ?? 0;
$currentTime = time();

// Enforce 10-second delay per user
if ($currentTime - $lastMessageTime < 5) { 
    die("Error: Please wait 5 seconds before sending another message.");
}

// Update last message time
$_SESSION['last_message_time'] = $currentTime;



        
        // Add new message
        $messages[] = [
            'user_id' => $_SESSION['user_id'],
            'message' => $message,
            'timestamp' => date('H:i:s')
        ];

        // Limit stored messages to avoid growing too large
        if (count($messages) > $maxMessages) {
            $messages = array_slice($messages, -$maxMessages);
        }

        file_put_contents($chatFile, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    header("Location: chat.php");
    exit;
}

// Load messages
$messages = file_exists($chatFile) ? json_decode(file_get_contents($chatFile), true) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anonymous Chat</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #222; color: #ddd; text-align: center; }
        .chat-box { width: 80%; max-width: 500px; margin: 20px auto; background: #333; padding: 15px; border-radius: 10px; }
        .message { padding: 5px; border-bottom: 1px solid #555; text-align: left; }
        .user-id { font-size: 0.8em; color: #888; }
        .timestamp { font-size: 0.7em; color: #bbb; float: right; }
        input, button { width: 90%; margin: 5px 0; padding: 5px; font-size: 14px; }
        button { background: #444; color: #fff; border: none; cursor: pointer; }
        button:hover { background: #555; }
    </style>
</head>
<body>

    <h1>Anonymous Chat Room</h1>

    <div class="chat-box">
        <?php if (!empty($messages)): ?>
            <?php foreach ($messages as $msg): ?>
                <div class="message">
                    <span class="user-id">Anon (<?= substr($msg['user_id'], -6) ?>):</span> 
                    <?= nl2br($msg['message']) ?>
                    <span class="timestamp"><?= $msg['timestamp'] ?></span>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No messages yet. Be the first to say something!</p>
        <?php endif; ?>
    </div>

    <form action="chat.php" method="post">
        <input type="text" name="message" placeholder="Type a message..." required minlength="2" maxlength="300">
        <button type="submit">Send</button>
    </form>

    <br>
    <a href="index.php">← Back to TorBoard</a>

</body>
</html>

