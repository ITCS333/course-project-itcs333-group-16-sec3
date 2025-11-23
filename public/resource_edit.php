<?php
session_start();
require __DIR__ . '/../src/db.php';

$id = $_GET['id'];

// Fetch resource
$stmt = $pdo->prepare("SELECT * FROM resources WHERE id = ?");
$stmt->execute([$id]);
$resource = $stmt->fetch();

if (!$resource) {
    die("Resource not found");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $link = $_POST['link'];

    $update = $pdo->prepare("UPDATE resources SET title=?, description=?, link=? WHERE id=?");
    $update->execute([$title, $description, $link, $id]);

    header("Location: resources_list.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Edit Resource</title>
</head>
<body>

<h1>Edit Resource</h1>

<form method="POST">
  <label>Title</label><br>
  <input type="text" name="title" value="<?= htmlspecialchars($resource['title']) ?>" required><br><br>

  <label>Description</label><br>
  <textarea name="description" required><?= htmlspecialchars($resource['description']) ?></textarea><br><br>

  <label>Link</label><br>
  <input type="text" name="link" value="<?= htmlspecialchars($resource['link']) ?>"><br><br>

  <button type="submit">Save Changes</button>
</form>

<br>
<a href="resources_list.php">Back</a>

</body>
</html>
