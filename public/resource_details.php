<?php
session_start();
require __DIR__ . '/../src/db.php';

$id = $_GET['id'];

// Fetch resource
$stmt = $pdo->prepare("SELECT * FROM resources WHERE id = ?");
$stmt->execute([$id]);
$resource = $stmt->fetch();

// Fetch comments
$stmt2 = $pdo->prepare("SELECT * FROM resource_comments WHERE resource_id = ? ORDER BY created_at DESC");
$stmt2->execute([$id]);
$comments = $stmt2->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
<title>Resource Details</title>
</head>
<body>

<h1><?= htmlspecialchars($resource['title']) ?></h1>
<p><?= htmlspecialchars($resource['description']) ?></p>

<?php if (!empty($resource['link'])): ?>
  <p><a href="<?= htmlspecialchars($resource['link']) ?>" target="_blank">Open Resource</a></p>
<?php endif; ?>

<hr>

<h3>Comments</h3>

<?php foreach ($comments as $c): ?>
  <div>
    <strong><?= htmlspecialchars($c['user_name']) ?>:</strong>
    <p><?= htmlspecialchars($c['comment']) ?></p>
  </div>
<?php endforeach; ?>

<hr>

<h3>Add Comment</h3>

<form method="POST" action="add_comment.php">
  <input type="hidden" name="resource_id" value="<?= $id ?>">

  <label>Your Name</label><br>
  <input type="text" name="user_name" required><br><br>

  <label>Comment</label><br>
  <textarea name="comment" required></textarea><br><br>

  <button type="submit">Post Comment</button>
</form>

<br>
<a href="resources_list.php">Back</a>

</body>
</html>
