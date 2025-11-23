<?php
session_start();
require __DIR__ . '/../src/db.php';

$resource_id = $_POST['resource_id'];
$user_name = $_POST['user_name'];
$comment = $_POST['comment'];

$stmt = $pdo->prepare("INSERT INTO resource_comments (resource_id, user_name, comment) VALUES (?, ?, ?)");
$stmt->execute([$resource_id, $user_name, $comment]);

header("Location: resource_details.php?id=" . $resource_id);
exit;
