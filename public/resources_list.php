<?php
session_start();
require __DIR__ . '/../src/db.php';

// Fetch all resources from database
$stmt = $pdo->query("SELECT id, title, description, link FROM resources ORDER BY created_at DESC");
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Course Resources</title>

  <style>
    body {
      font-family: "Segoe UI", Arial, sans-serif;
      background: #f5f7fa;
      margin: 0;
      padding: 0;
      color: #2c3e50;
    }

    /* NAVBAR */
    nav {
      background: #34495e;
      padding: 12px 0;
      text-align: center;
    }

    nav a {
      color: white;
      margin: 0 18px;
      text-decoration: none;
      font-weight: 500;
    }

    nav a:hover {
      text-decoration: underline;
    }

    header {
      background: #2c3e50;
      color: white;
      padding: 25px;
      text-align: center;
    }

    .container {
      width: 90%;
      max-width: 900px;
      margin: 30px auto;
      background: white;
      padding: 25px;
      border-radius: 10px;
      box-shadow: 0 3px 8px rgba(0,0,0,0.1);
    }

    h2 {
      border-left: 5px solid #2c3e50;
      padding-left: 10px;
      margin-top: 30px;
    }

    label { font-weight: bold; display: block; margin-top: 12px; }

    input, textarea {
      width: 100%;
      padding: 10px;
      margin-top: 6px;
      border: 1px solid #d0d0d0;
      border-radius: 6px;
    }

    button {
      margin-top: 12px;
      padding: 10px 18px;
      background: #2c3e50;
      color: white;
      border: none;
      border-radius: 6px;
      cursor: pointer;
    }

    .resource-box {
      padding: 15px;
      border-radius: 8px;
      border: 1px solid #dcdcdc;
      margin-top: 15px;
    }

    .hidden { display: none; }

    .comment {
      background: #eef2f7;
      padding: 8px 12px;
      border-radius: 6px;
      margin-top: 10px;
    }

    a { color: #2c3e50; font-weight: bold; }
  </style>
</head>
<body>

  <!-- NAV -->
  <nav>
    <a href="#">Home</a>
    <a href="#">Resources</a>
    <a href="#">Assignments</a>
    <a href="#">Weeks</a>
    <a href="#">Discussion</a>
    <a href="#">Logout</a>
  </nav>

  <!-- HEADER -->
  <header>
    <h1>📚 Course Resources</h1>
    <p>Course Materials & Discussion Section</p>
  </header>

  <div class="container">

    <!-- ADMIN VIEW -->
    <h2>Admin View</h2>
    <p>The teacher can add, edit, and delete course resources (Full CRUD).</p>

    <form method="POST" action="resource_add.php">
      <label>Resource Title</label>
      <input type="text" name="title" required>

      <label>Description</label>
      <textarea name="description" rows="3" required></textarea>

      <label>Resource Link</label>
      <input type="text" name="link">

      <button type="submit">Add Resource</button>
    </form>

    <!-- STUDENT VIEW -->
    <h2>Available Resources</h2>
    <p>Students can view resources and open details.</p>

    <?php foreach ($resources as $resource): ?>
      <div class="resource-box">
        <strong><?= htmlspecialchars($resource['title']) ?></strong>
        <p><?= htmlspecialchars($resource['description']) ?></p>

        <?php if (!empty($resource['link'])): ?>
          <p><a href="<?= htmlspecialchars($resource['link']) ?>" target="_blank">Open Resource</a></p>
        <?php endif; ?>

        <a href="resource_details.php?id=<?= $resource['id'] ?>">Details</a> |
        <a href="resource_edit.php?id=<?= $resource['id'] ?>">Edit</a> |
        <a href="resource_delete.php?id=<?= $resource['id'] ?>">Delete</a>
      </div>
    <?php endforeach; ?>

  </div>

</body>
</html>

