<?php
session_start();
if (!isset($_SESSION['captcha_question'])) {
    $_SESSION['captcha_num1'] = rand(1, 10);
    $_SESSION['captcha_num2'] = rand(1, 10);
    $_SESSION['captcha_question'] = $_SESSION['captcha_num1'] . " + " . $_SESSION['captcha_num2'];
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = uniqid();
}
// List of boards
$boards = [
    'tech' => 'Technology',
    'general' => 'General',
    'random' => 'Random',
    'hacking' => 'Hacking',
    'markets' => 'Markets',
    'mental health' => 'Mental Health',
    '/rules' => 'Rules',
        
];


// Set theme based on user selection
if (isset($_GET['theme'])) {
    $theme = $_GET['theme'] === 'light' ? 'light' : 'dark';
    setcookie('theme', $theme, time() + (86400 * 30), "/"); // Save for 30 days
} else {
    $theme = $_COOKIE['theme'] ?? 'dark'; // Default to dark mode
}



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

// Handle voting (Upvote or Downvote)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vote']) && isset($_POST['post_id'])) {
    $postId = $_POST['post_id'];
    $voteType = $_POST['vote']; // 'up' or 'down'

    $postsFile = $uploadDir . 'posts.json';
    $posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];

    foreach ($posts as &$post) {
        if ($post['id'] === $postId) {
            // Initialize score if not set
            if (!isset($post['score'])) {
                $post['score'] = 0;
            }
            // Apply vote
            if ($voteType === 'up') {
                $post['score']++;
            } elseif ($voteType === 'down') {
                $post['score']--;
            }
            break;
        }
    }

    // Save updated votes
    file_put_contents($postsFile, json_encode($posts));

    // Redirect back to board
    header("Location: ?board=$currentBoard");
    exit;
}



// Handle form submission for posts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $commentName = htmlspecialchars($_POST['comment_name'] ?? 'Anonymous');
    $message = htmlspecialchars($_POST['message'] ?? '');
    $image = '';
    
 if (!isset($_POST['captcha']) || $_POST['captcha'] != ($_SESSION['captcha_num1'] + $_SESSION['captcha_num2'])) {
        die("❌ Error: Wrong CAPTCHA answer. Try again.");
    }

    // Reset CAPTCHA for the next post
    $_SESSION['captcha_num1'] = rand(1, 10);
    $_SESSION['captcha_num2'] = rand(1, 10);
    $_SESSION['captcha_question'] = $_SESSION['captcha_num1'] . " + " . $_SESSION['captcha_num2'];
if (empty($message)) {
    die("Error: You must enter text in the message field.");
}
    $image = '';
    $userId = $_SESSION['user_id'];

    // Secure file upload handling
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

    if (!empty($_FILES['image']['name'])) {
        $fileTmpPath = $_FILES['image']['tmp_name'];
        $fileName = $_FILES['image']['name'];
        $fileType = mime_content_type($fileTmpPath);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Ensure the uploaded file is a valid image
        if (!in_array($fileType, $allowedTypes) || !in_array($fileExt, $allowedExtensions)) {
            die("Invalid file type. Only JPG, PNG, and GIF allowed.");
        }

        // Generate a random, unique filename
        $newFileName = uniqid() . '.' . $fileExt;
        $imagePath = $uploadDir . $newFileName;

        // Move the uploaded file
        if (move_uploaded_file($fileTmpPath, $imagePath)) {
            $image = $imagePath;
        } else {
            die("File upload failed.");
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
    $commentText = htmlspecialchars($_POST['comment']);

    // Prevent blank comments
    if (empty($commentText)) {
        die("Error: You must enter text in the comment field.");
    }

    $postsFile = $uploadDir . 'posts.json';
    
    // Check if the posts file exists and is valid JSON
    if (file_exists($postsFile)) {
        $jsonContent = file_get_contents($postsFile);
        $posts = json_decode($jsonContent, true);
        
        // If JSON is corrupted, reset to an empty array
        if (!is_array($posts)) {
            $posts = [];
        }
    } else {
        $posts = [];
    }

    // Flag to check if the post was found and updated
    $postUpdated = false;

    // Update only the correct post
    foreach ($posts as &$post) {
        if ($post['id'] === $postId) {
            if (!isset($post['comments'])) {
                $post['comments'] = [];
            }
            $post['comments'][] = [
                'name' => $commentName,
                'comment' => $commentText,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            $postUpdated = true;
            break; // Stop loop after updating the correct post
        }
    }

    // Save the updated posts if the target post was found
    if ($postUpdated) {
        file_put_contents($postsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

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
/* Target only vote buttons without affecting comments */
.vote-button {
    background: none !important; /* Remove background */
    color: #fff; /* Keep text visible */
    border: none; /* Remove borders */
    padding: 3px 6px;
    margin: 2px;
    cursor: pointer;
    font-size: 12px;
    width: 40px;
    text-align: center;
}
.reply-button {
    background: #5a5a5a; /* Dark Grey Background */
    color: #fff; /* White Text */
    border: 2px solid #777; /* Slight Border */
    padding: 8px 15px; /* Comfortable Padding */
    margin: 5px 0;
    cursor: pointer;
    border-radius: 5px; /* Rounded Edges */
    font-size: 14px;
    transition: all 0.2s ease-in-out;
}

.reply-button:hover {
    background: #777; /* Lighter grey on hover */
    border-color: #aaa;
    transform: scale(1.05); /* Slightly enlarges when hovered */
}

.reply-button:active {
    transform: scale(0.95); /* Shrinks slightly when clicked */
}
.view-thread-button {
    display: inline-block;
    background: #444; /* Dark Grey Background */
    color: #fff; /* White Text */
    border: 2px solid #777; /* Slight Border */
    padding: 6px 12px; /* Comfortable Padding */
    margin: 5px 0;
    cursor: pointer;
    border-radius: 5px; /* Rounded Edges */
    font-size: 14px;
    text-decoration: none; /* Remove default underline */
    transition: all 0.2s ease-in-out;
}

.view-thread-button:hover {
    background: #777; /* Lighter grey on hover */
    border-color: #aaa;
    transform: scale(1.05); /* Slightly enlarges when hovered */
}

.view-thread-button:active {
    transform: scale(0.95); /* Shrinks slightly when clicked */
}

footer {
    background: #111; /* Dark background */
    color: #ddd;
    text-align: center;
    padding: 15px;
    margin-top: 20px;
    font-size: 14px;
    border-top: 1px solid #333;
}

footer a {
    color: #ff8800; /* Highlighted color for links */
    text-decoration: none;
}

footer a:hover {
    text-decoration: underline;
}

.xmr-address {
    font-family: monospace;
    background: #222;
    padding: 5px;
    border-radius: 3px;
    display: inline-block;
    word-break: break-all;
}


:root {
    --bg-color: #222;
    --text-color: #ddd;
    --post-bg: #444;
    --comment-bg: #333;
    --button-bg: #555;
    --button-text: #fff;
}

.light-mode {
    --bg-color: #f4f4f4;
    --text-color: #222;
    --post-bg: #fff;
    --comment-bg: #eee;
    --button-bg: #ccc;
    --button-text: #222;
}

/* Apply theme */
body {
    background-color: var(--bg-color);
    color: var(--text-color);
}

.post, .comment {
    background-color: var(--post-bg);
    margin: 10px auto; 
    padding: 10px; 
    width: 90%; 
    max-width: 500px;
}

button, .theme-button {
    background: var(--button-bg);
    color: var(--button-text);
    padding: 8px 15px;
    text-decoration: none;
    border-radius: 5px;
    display: inline-block;
}

button:hover, .theme-button:hover {
    opacity: 0.8;
}




    </style>
</head>
<body>
<header>
    <h1>TorBoard</h1>
    <nav>
        <?php foreach ($boards as $key => $name): ?>


            <a href="?board=<?= $key ?>"><?= $name ?></a>
        <?php endforeach; ?>
<a href="chat.php">Chat Room</a>

    </nav>
<a href="?theme=light" class="theme-button">Light Mode</a> |
<a href="?theme=dark" class="theme-button">Dark Mode</a>

</header>
<main>
<h2>Board: <?= $boards[$currentBoard] ?></h2>
    <form action="" method="post" enctype="multipart/form-data">

        <textarea name="message" placeholder="Write your message..." required></textarea>
        <input type="file" name="image">
        <!-- CAPTCHA Field -->
    <label>What is <?= $_SESSION['captcha_question'] ?>?</label>
    <input type="number" name="captcha" required>
         <button type="submit">Post</button>
    </form>

<?php foreach (array_reverse($posts) as $post): ?>
    <div class="post">
        <strong><?= 'Anonymous' ?></strong><br>
        <p><?= nl2br($post['message']) ?></p>
        <?php if (!empty($post['image'])): ?>
            <img src="/<?= $post['image'] ?>" alt="Uploaded Image">
        <?php endif; ?>
        <br><small><?= $post['timestamp'] ?></small>
        <br>
        <br><strong>Score: <?= $post['score'] ?? 0 ?></strong>

        <!-- Upvote & Downvote Buttons -->
        <form action="" method="post" style="display:inline;">
            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
            <input type="hidden" name="vote" value="up">
            <button type="submit" class="vote-button">👍</button>
        </form>

        <form action="" method="post" style="display:inline;">
            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
            <input type="hidden" name="vote" value="down">
            <button type="submit" class="vote-button">👎</button>
        </form>

        <br>

        <!-- Show Only Latest 5 Comments -->
        <?php 
        $totalComments = count($post['comments']);
        if ($totalComments > 0) {
            echo "<h4>Latest Comments:</h4>";
            $displayedComments = array_slice($post['comments'], -5);
	   
        }
        ?>

        <?php foreach ($displayedComments as $comment): ?>
            <div class="comment">
               <strong><?= 'Anonymous:' ?></strong> <?= nl2br($comment['comment']) ?><br>
                <small><?= $comment['timestamp'] ?></small>
            </div>
        <?php endforeach; ?>

        <!-- Show "View Thread" if More Than 5 Comments -->
        <?php if ($totalComments > 5): ?>
            <a href="thread.php?id=<?= $post['id'] ?>&board=<?= $currentBoard ?>" class="view-thread-button">View All Comments (<?= $totalComments ?>)</a>
        <?php else: ?>
            <a href="thread.php?id=<?= $post['id'] ?>&board=<?= $currentBoard ?>" class="view-thread-button">View Thread</a>
        <?php endif; ?>

        <!-- Comment Form -->
        <form class="comment-form" action="" method="post">
            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
            <input type="text" name="comment" placeholder="Add a comment..." required>
            <button type="submit">Comment</button>
        </form>
    </div>
<?php endforeach; ?>
</main>
<footer>
    <p>Support TorBoard: Donate XMR</p>
    <p>Monero Wallet: <span class="xmr-address">48v8nM1t6eLEiWjjMy5jidNRtbGufTU8pFtoEM6eCgw63kjAL1cYiuea5QirEbLZrEUc54PNEMetRJeCznWVfSXVLwz5LTy</span></p>
    <p>Join the discussion on <a href="http://dreadytofatroptsdj6io7l3xptbet6onoyno2yv7jicoxknyazubrad.onion/d/TorBoard" target="_blank">Dread (/d/TorBoard)</a></p>
</footer>
<body class="<?= $theme === 'light' ? 'light-mode' : '' ?>">

</body>
</html>
