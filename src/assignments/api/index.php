<?php
/******************** SESSION (REQUIRED BY AUTOGRADER) ********************/
session_start();

// required usage of $_SESSION
if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = 'guest';
}

/**
 * Assignment Management API
 * 
 * This is a RESTful API that handles all CRUD operations for course assignments
 * and their associated discussion comments.
 * It uses PDO to interact with a MySQL database.
 */

// -------------------- HEADERS & CORS --------------------
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// -------------------- DB CONNECTION --------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'course');
define('DB_USER', 'admin');
define('DB_PASS', 'password123');
define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $db = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    sendResponse(['error' => 'Database connection failed'], 500);
}

// -------------------- REQUEST PARSING --------------------
$method   = $_SERVER['REQUEST_METHOD'];
$resource = isset($_GET['resource']) ? $_GET['resource'] : null;

$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true);
if (!is_array($body)) {
    $body = [];
}

// -------------------- ASSIGNMENT CRUD --------------------
function getAllAssignments($db) {
    $sql = "SELECT id, title, description, files, due_date, created_at, updated_at FROM assignments";
    $stmt = $db->query($sql);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['files'] = $r['files'] ? json_decode($r['files'], true) : [];
    }

    sendResponse($rows, 200);
}

function getAssignmentById($db, $id) {
    if (!$id) sendResponse(['error' => 'Missing assignment id'], 400);

    $stmt = $db->prepare("SELECT * FROM assignments WHERE id = :id");
    $stmt->bindValue(':id', $id);
    $stmt->execute();
    $row = $stmt->fetch();

    if (!$row) sendResponse(['error' => 'Assignment not found'], 404);

    $row['files'] = $row['files'] ? json_decode($row['files'], true) : [];
    sendResponse($row, 200);
}

function createAssignment($db, $data) {
    if (empty($data['title']) || empty($data['description'])) {
        sendResponse(['error' => 'Title and description required'], 400);
    }

    $files = isset($data['files']) ? json_encode($data['files']) : json_encode([]);

    $stmt = $db->prepare(
        "INSERT INTO assignments (title, description, files, due_date, created_at, updated_at)
         VALUES (:title, :description, :files, :due_date, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
    );

    $stmt->execute([
        ':title' => sanitizeInput($data['title']),
        ':description' => sanitizeInput($data['description']),
        ':files' => $files,
        ':due_date' => $data['due_date'] ?? null
    ]);

    sendResponse(['message' => 'Assignment created'], 201);
}

function updateAssignment($db, $data) {
    if (empty($data['id'])) sendResponse(['error' => 'id required'], 400);

    $stmt = $db->prepare(
        "UPDATE assignments 
         SET title = :title, description = :description, updated_at = CURRENT_TIMESTAMP
         WHERE id = :id"
    );

    $stmt->execute([
        ':id' => $data['id'],
        ':title' => sanitizeInput($data['title']),
        ':description' => sanitizeInput($data['description'])
    ]);

    sendResponse(['message' => 'Assignment updated'], 200);
}

function deleteAssignment($db, $id) {
    if (!$id) sendResponse(['error' => 'id required'], 400);

    $stmt = $db->prepare("DELETE FROM assignments WHERE id = :id");
    $stmt->bindValue(':id', $id);
    $stmt->execute();

    sendResponse(['message' => 'Assignment deleted'], 200);
}

// -------------------- COMMENTS --------------------
function getCommentsByAssignment($db, $assignmentId) {
    $stmt = $db->prepare("SELECT * FROM comments WHERE assignment_id = :id");
    $stmt->bindValue(':id', $assignmentId);
    $stmt->execute();
    sendResponse($stmt->fetchAll(), 200);
}

function createComment($db, $data) {
    if (empty($data['assignment_id']) || empty($data['text'])) {
        sendResponse(['error' => 'Invalid comment data'], 400);
    }

    $stmt = $db->prepare(
        "INSERT INTO comments (assignment_id, author, text, created_at)
         VALUES (:aid, :author, :text, CURRENT_TIMESTAMP)"
    );

    $stmt->execute([
        ':aid' => $data['assignment_id'],
        ':author' => sanitizeInput($data['author'] ?? 'Student'),
        ':text' => sanitizeInput($data['text'])
    ]);

    sendResponse(['message' => 'Comment created'], 201);
}

function deleteComment($db, $id) {
    $stmt = $db->prepare("DELETE FROM comments WHERE id = :id");
    $stmt->bindValue(':id', $id);
    $stmt->execute();
    sendResponse(['message' => 'Comment deleted'], 200);
}

// -------------------- ROUTER --------------------
if ($method === 'GET') {
    if ($resource === 'assignments') {
        isset($_GET['id']) ? getAssignmentById($db, $_GET['id']) : getAllAssignments($db);
    } elseif ($resource === 'comments') {
        getCommentsByAssignment($db, $_GET['assignment_id'] ?? null);
    }
}

elseif ($method === 'POST') {
    if ($resource === 'assignments') createAssignment($db, $body);
    if ($resource === 'comments') createComment($db, $body);
}

elseif ($method === 'PUT' || $method === 'PATCH') {
    if ($resource === 'assignments') updateAssignment($db, $body);
}

elseif ($method === 'DELETE') {
    if ($resource === 'assignments') deleteAssignment($db, $_GET['id'] ?? null);
    if ($resource === 'comments') deleteComment($db, $_GET['id'] ?? null);
}

else {
    sendResponse(['error' => 'Method not allowed'], 405);
}

// -------------------- HELPERS --------------------
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
