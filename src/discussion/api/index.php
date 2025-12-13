<?php
/******************** SESSION (REQUIRED) ********************/
session_start();

if (!isset($_SESSION['discussion_user'])) {
    $_SESSION['discussion_user'] = 'guest';
}

/**
 * Discussion Board API
 * 
 * This is a RESTful API that handles all CRUD operations for the discussion board.
 * It manages both discussion topics and their replies.
 * It uses PDO to interact with a MySQL database.
 * 
 * Database Table Structures (for reference):
 * 
 * Table: topics
 * Columns:
 *   - id (INT, PRIMARY KEY, AUTO_INCREMENT)
 *   - topic_id (VARCHAR(50), UNIQUE)
 *   - subject (VARCHAR(255))
 *   - message (TEXT)
 *   - author (VARCHAR(100))
 *   - created_at (TIMESTAMP)
 * 
 * Table: replies
 * Columns:
 *   - id (INT, PRIMARY KEY, AUTO_INCREMENT)
 *   - reply_id (VARCHAR(50), UNIQUE)
 *   - topic_id (VARCHAR(50))
 *   - text (TEXT)
 *   - author (VARCHAR(100))
 *   - created_at (TIMESTAMP)
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

// ======================= TOPICS FUNCTIONS =======================

function getAllTopics($db) {
    $sql = "SELECT topic_id, subject, message, author, created_at FROM topics";
    $params = [];
    $clauses = [];

    if (!empty($_GET['search'])) {
        $search = '%' . trim($_GET['search']) . '%';
        $clauses[] = "(subject LIKE :search OR message LIKE :search OR author LIKE :search)";
        $params[':search'] = $search;
    }

    if ($clauses) {
        $sql .= " WHERE " . implode(' AND ', $clauses);
    }

    $allowedSort = ['subject', 'author', 'created_at'];
    $sort = $_GET['sort'] ?? 'created_at';
    if (!in_array($sort, $allowedSort)) $sort = 'created_at';

    $order = strtolower($_GET['order'] ?? 'desc');
    if (!in_array($order, ['asc', 'desc'])) $order = 'desc';

    $sql .= " ORDER BY $sort " . strtoupper($order);

    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();

    sendResponse($stmt->fetchAll());
}

function getTopicById($db, $topicId) {
    if (!$topicId) sendResponse(['error' => 'Missing topic id'], 400);

    $stmt = $db->prepare("SELECT topic_id, subject, message, author, created_at FROM topics WHERE topic_id = :id LIMIT 1");
    $stmt->bindValue(':id', $topicId);
    $stmt->execute();

    $row = $stmt->fetch();
    if (!$row) sendResponse(['error' => 'Topic not found'], 404);

    sendResponse($row);
}

function createTopic($db, $data) {
    foreach (['topic_id','subject','message','author'] as $f) {
        if (empty($data[$f])) sendResponse(['error'=>"$f is required"],400);
    }

    $stmt = $db->prepare(
        "INSERT INTO topics (topic_id, subject, message, author, created_at)
         VALUES (:id,:subject,:message,:author,CURRENT_TIMESTAMP)"
    );

    $stmt->execute([
        ':id'      => sanitizeInput($data['topic_id']),
        ':subject' => sanitizeInput($data['subject']),
        ':message' => sanitizeInput($data['message']),
        ':author'  => sanitizeInput($data['author'])
    ]);

    sendResponse(['message'=>'Topic created'],201);
}

function updateTopic($db, $data) {
    if (empty($data['topic_id'])) sendResponse(['error'=>'topic_id required'],400);

    $stmt = $db->prepare(
        "UPDATE topics SET subject=:subject, message=:message WHERE topic_id=:id"
    );

    $stmt->execute([
        ':id'      => $data['topic_id'],
        ':subject' => sanitizeInput($data['subject']),
        ':message' => sanitizeInput($data['message'])
    ]);

    sendResponse(['message'=>'Topic updated']);
}

function deleteTopic($db, $topicId) {
    if (!$topicId) sendResponse(['error'=>'topic_id required'],400);

    $db->prepare("DELETE FROM replies WHERE topic_id=:id")->execute([':id'=>$topicId]);
    $db->prepare("DELETE FROM topics WHERE topic_id=:id")->execute([':id'=>$topicId]);

    sendResponse(['message'=>'Topic deleted']);
}

// ======================= REPLIES =======================

function getRepliesByTopicId($db, $topicId) {
    if (!$topicId) sendResponse(['error'=>'topic_id required'],400);

    $stmt = $db->prepare(
        "SELECT reply_id, text, author, created_at FROM replies WHERE topic_id=:id"
    );
    $stmt->execute([':id'=>$topicId]);

    sendResponse($stmt->fetchAll());
}

function createReply($db, $data) {
    foreach (['reply_id','topic_id','text','author'] as $f) {
        if (empty($data[$f])) sendResponse(['error'=>"$f is required"],400);
    }

    $stmt = $db->prepare(
        "INSERT INTO replies (reply_id, topic_id, text, author, created_at)
         VALUES (:rid,:tid,:text,:author,CURRENT_TIMESTAMP)"
    );

    $stmt->execute([
        ':rid'    => sanitizeInput($data['reply_id']),
        ':tid'    => sanitizeInput($data['topic_id']),
        ':text'   => sanitizeInput($data['text']),
        ':author' => sanitizeInput($data['author'])
    ]);

    sendResponse(['message'=>'Reply created'],201);
}

function deleteReply($db, $replyId) {
    if (!$replyId) sendResponse(['error'=>'reply_id required'],400);

    $db->prepare("DELETE FROM replies WHERE reply_id=:id")->execute([':id'=>$replyId]);
    sendResponse(['message'=>'Reply deleted']);
}

// ======================= ROUTER =======================

if ($method === 'GET') {
    if ($resource === 'topics') {
        isset($_GET['id']) ? getTopicById($db,$_GET['id']) : getAllTopics($db);
    } elseif ($resource === 'replies') {
        getRepliesByTopicId($db,$_GET['topic_id'] ?? null);
    }
}

elseif ($method === 'POST') {
    if ($resource === 'topics') createTopic($db,$body);
    elseif ($resource === 'replies') createReply($db,$body);
}

elseif ($method === 'PUT' || $method === 'PATCH') {
    if ($resource === 'topics') updateTopic($db,$body);
}

elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if ($resource === 'topics') deleteTopic($db,$id);
    elseif ($resource === 'replies') deleteReply($db,$id);
}

function sendResponse($data,$code=200){
    http_response_code($code);
    echo json_encode($data,JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitizeInput($v){
    return htmlspecialchars(strip_tags(trim($v)),ENT_QUOTES,'UTF-8');
}

function isValidResource($resource) {
    $allowed = ['topics', 'replies'];
    return in_array($resource, $allowed);
}

?>
