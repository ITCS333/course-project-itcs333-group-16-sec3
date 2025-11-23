<?php
session_start();
require __DIR__ . '/../src/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $link = $_POST['link'];

    $stmt = $pdo->prepare("INSERT INTO resources (title, description, link) VALUES (?, ?, ?)");
    $stmt->execute([$title, $description, $link]);

    header("Location: resources_list.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Add Resource</title>
</head>
<body>
<h1>Add New Resource</h1>

<form method="POST">
  <label>Title</label><br>
  <input type="text" name="title" required><br><br>

  <label>Description</label><br>
  <textarea name="description" required></textarea><br><br>

  <label>Link</label><br>
  <input type="text" name="link"><br><br>

  <button type="submit">Add</button>
</form>

<br>
<a href="resources_list.php">Back</a>

</body>
</html>
