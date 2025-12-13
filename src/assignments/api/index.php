<?php
session_start();
/**
 * Assignment Management API
 * 
 * This is a RESTful API that handles all CRUD operations for course assignments
 * and their associated discussion comments.
 * It uses PDO to interact with a MySQL database.
 * 
 * Database Table Structures (for reference):
 * 
 * Table: assignments
 * Columns:
 *   - id (INT, PRIMARY KEY, AUTO_INCREMENT)
 *   - title (VARCHAR(200))
 *   - description (TEXT)
 *   - due_date (DATE)
 *   - files (TEXT)
 *   - created_at (TIMESTAMP)
 *   - updated_at (TIMESTAMP)
 * 
 * Table: comments
 * Columns:
 *   - id (INT, PRIMARY KEY, AUTO_INCREMENT)
 *   - assignment_id (VARCHAR(50), FOREIGN KEY)
 *   - author (VARCHAR(100))
 *   - text (TEXT)
 *   - created_at (TIMESTAMP)
 * 
 * HTTP Methods Supported:
 *   - GET, POST, PUT, DELETE
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
    sendResponse(['error' => 'Database connection failed', 'details' => $e->getMessage()], 500);
}

// -------------------- REQUEST PARSING --------------------
$method = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? null;
$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true);
if (!is_array($body)) $body = [];

// -------------------- ASSIGNMENT CRUD --------------------
function getAllAssignments($db) {
    $stmt = $db->query("SELECT * FROM assignments");
    sendResponse($stmt->fetchAll(), 200);
}

function getAssignmentById($db, $id) {
    if (!$id) sendResponse(['error' => 'Missing id'], 400);
    $stmt = $db->prepare("SELECT * FROM assignments WHERE id = :id");
    $stmt->bindValue(':id', $id);
    $stmt->execute();
    $row = $stmt->fetch();
    if (!$row) sendResponse(['error' => 'Assignment not found'], 404);
    sendResponse($row, 200);
}

function createAssignment($db, $data) {
    if (empty($data['title']) || empty($data['description'])) {
        sendResponse(['error' => 'Title and description required'], 400);
    }
    $stmt = $db->prepare(
        "INSERT INTO assignments (title, description, created_at, updated_at)
         VALUES (:title, :description, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
    );
    $stmt->execute([
        ':title' => sanitizeInput($data['title']),
        ':description' => sanitizeInput($data['description'])
    ]);
    sendResponse(['message' => 'Assignment created'], 201);
}

function updateAssignment($db, $data) {
    if (empty($data['id'])) sendResponse(['error' => 'id required'], 400);
    $stmt = $db->prepare(
        "UPDATE assignments SET title=:title, description=:description, updated_at=CURRENT_TIMESTAMP WHERE id=:id"
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
    $stmt = $db->prepare("DELETE FROM assignments WHERE id=:id");
    $stmt->execute([':id' => $id]);
    sendResponse(['message' => 'Assignment deleted'], 200);
}

// -------------------- ROUTER --------------------
if ($method === 'GET' && $resource === 'assignments') {
    isset($_GET['id']) ? getAssignmentById($db, $_GET['id']) : getAllAssignments($db);
} elseif ($method === 'POST' && $resource === 'assignments') {
    createAssignment($db, $body);
} elseif (($method === 'PUT' || $method === 'PATCH') && $resource === 'assignments') {
    updateAssignment($db, $body);
} elseif ($method === 'DELETE' && $resource === 'assignments') {
    deleteAssignment($db, $_GET['id'] ?? null);
} else {
    sendResponse(['error' => 'Invalid request'], 400);
}

// -------------------- HELPERS --------------------
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
?>
