<?php
session_start();

// Get thread ID
$threadId = $_GET['id'] ?? null;
$board = $_GET['board'] ?? 'general';

// Validate thread ID
if (!$threadId || !preg_match('/^[a-zA-Z0-9]+$/', $threadId)) {
    die("Invalid thread ID.");
}

// Load posts
$postsFile = "uploads/$board/posts.json";
$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];

$thread = null;
foreach ($posts as &$post) {
    if ($post['id'] === $threadId) {
        $thread = &$post;
        break;
    }
}

if (!$thread) {
    die("Thread not found.");
}

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    $commentText = htmlspecialchars($_POST['comment']);

    // Prevent blank comments
    if (empty($commentText)) {
        die("Error: You must enter text in the comment field.");
    }

    $thread['comments'][] = [
        'name' => ['Anonymous:'],
        'comment' => $commentText,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    file_put_contents($postsFile, json_encode($posts));
    header("Location: thread.php?id=$threadId&board=$board");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thread - <?= $thread['message'] ?></title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #222; color: #ddd; margin: 0; padding: 0; }
        .post, .comment { background: #444; padding: 10px; margin: 10px; border-radius: 5px; }
        .comment { margin-left: 20px; font-size: 0.9em; color: #bbb; }
    </style>
</head>
<body>
    <h1>Thread: <?= nl2br($thread['message']) ?></h1>
    <?php if (!empty($thread['image'])): ?>
        <img src="/<?= $thread['image'] ?>" alt="Uploaded Image">
    <?php endif; ?>
    <p><small>Posted by: <?= $thread['name'] ?> | <?= $thread['timestamp'] ?></small></p>

    <h2>Replies:</h2>
    <?php foreach ($thread['comments'] as $comment): ?>
        <div class="comment">
<strong><?= 'Anonymous:' ?></strong>
            <?= nl2br($comment['comment']) ?><br>
            <small><?= $comment['timestamp'] ?></small>
        </div>
    <?php endforeach; ?>

    <h3>Reply to this thread:</h3>
    <form action="" method="post">
        <textarea name="comment" placeholder="Write your reply..." required></textarea>
        <button type="submit">Reply</button>
    </form>

    <br><a href="index.php?board=<?= $board ?>">← Back to Board</a>
</body>
</html>

