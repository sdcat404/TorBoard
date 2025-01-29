<?php
session_start();

// List of boards
$boards = [
    'tech' => 'Technology',
    'general' => 'General',
    'random' => 'Random',
    'hacking' => 'Hacking',
    'markets' => 'Markets',
];

// Determine the current board
$currentBoard = $_GET['board'] ?? 'general';
if (!array_key_exists($currentBoard, $boards)) {
    $currentBoard = 'general';
}

// Create the uploads directory for the board if it doesn't exist
$uploadDir = "uploads/$currentBoard/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Handle form submission for posts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $name = htmlspecialchars($_POST['name'] ?? 'Anonymous');
    $message = htmlspecialchars($_POST['message'] ?? '');
    $image = '';

    // Handle file upload
    if (!empty($_FILES['image']['name'])) {
        $imagePath = $uploadDir . basename($_FILES['image']['name']);
        if (move_uploaded_file($_FILES['image']['tmp_name'], $imagePath)) {
            $image = $imagePath;
        }
    }

    // Save the post
    $post = [
        'id' => uniqid(),
        'name' => $name,
        'message' => $message,
        'image' => $image,
        'timestamp' => date('Y-m-d H:i:s'),
        'comments' => []
    ];
    $postsFile = $uploadDir . 'posts.json';
    $posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];
    $posts[] = $post;
    file_put_contents($postsFile, json_encode($posts));

    // Redirect to prevent duplicate submissions
    header("Location: ?board=$currentBoard");
    exit;
}

// Handle form submission for comments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    $postId = $_POST['post_id'];
    $commentName = htmlspecialchars($_POST['comment_name'] ?? 'Anonymous');
    $comment = htmlspecialchars($_POST['comment']);
    
    $postsFile = $uploadDir . 'posts.json';
    $posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];
    
    foreach ($posts as &$post) {
        if ($post['id'] === $postId) {
            $post['comments'][] = [
                'name' => $commentName,
                'comment' => $comment,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            break;
        }
    }
    
    file_put_contents($postsFile, json_encode($posts));
    header("Location: ?board=$currentBoard");
    exit;
}

// Load posts for the current board
$postsFile = $uploadDir . 'posts.json';
$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TorBoard - <?= $boards[$currentBoard] ?></title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #222; color: #ddd; margin: 0; padding: 0; }
        header { background: #333; padding: 10px 0; text-align: center; }
        header a { color: #fff; margin: 0 10px; text-decoration: none; font-weight: bold; }
        header a:hover { text-decoration: underline; }
        form { margin: 20px auto; padding: 10px; width: 90%; max-width: 500px; background: #333; border-radius: 10px; }
        input, textarea, button { width: 100%; margin: 5px 0; padding: 5px; font-size: 14px; }
        button { background: #444; color: #fff; border: none; cursor: pointer; }
        .post { margin: 10px auto; padding: 10px; width: 90%; max-width: 500px; background: #444; border-radius: 10px; text-align: left; }
        img { max-width: 100%; height: auto; margin-top: 10px; }
        .comment { margin-left: 20px; font-size: 0.9em; color: #bbb; background: #333; padding: 5px; border-radius: 5px; }
        .comment-form { margin-top: 10px; }
    </style>
</head>
<body>
<header>
    <h1>TorBoard</h1>
    <nav>
        <?php foreach ($boards as $key => $name): ?>
            <a href="?board=<?= $key ?>"><?= $name ?></a>
        <?php endforeach; ?>
    </nav>
</header>
<main>
    <h2>Board: <?= $boards[$currentBoard] ?></h2>
    <form action="" method="post" enctype="multipart/form-data">
        <input type="text" name="name" placeholder="Name (optional)">
        <textarea name="message" placeholder="Write your message..." required></textarea>
        <input type="file" name="image">
        <button type="submit">Post</button>
    </form>
    
    <?php foreach (array_reverse($posts) as $post): ?>
        <div class="post">
            <strong><?= $post['name'] ?></strong><br>
            <p><?= nl2br($post['message']) ?></p>
            <?php if (!empty($post['image'])): ?>
                <img src="<?= $post['image'] ?>" alt="Uploaded Image">
            <?php endif; ?>
            <br><small><?= $post['timestamp'] ?></small>
            
            <?php foreach ($post['comments'] as $comment): ?>
                <div class="comment">
                    <strong><?= $comment['name'] ?></strong>: <?= nl2br($comment['comment']) ?><br>
                    <small><?= $comment['timestamp'] ?></small>
                </div>
            <?php endforeach; ?>
            
            <form class="comment-form" action="" method="post">
                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                <input type="text" name="comment_name" placeholder="Name (optional)">
                <input type="text" name="comment" placeholder="Add a comment..." required>
                <button type="submit">Comment</button>
            </form>
        </div>
    <?php endforeach; ?>
</main>
</body>
</html>
