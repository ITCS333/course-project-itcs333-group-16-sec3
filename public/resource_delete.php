<?php
session_start();
require __DIR__ . '/../src/db.php';

$id = $_GET['id'];

$stmt = $pdo->prepare("DELETE FROM resources WHERE id = ?");
$stmt->execute([$id]);

header("Location: resources_list.php");
exit;
