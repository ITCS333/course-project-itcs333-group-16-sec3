<?php
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
 *   - topic_id (VARCHAR(50), UNIQUE) - The topic's unique identifier (e.g., "topic_1234567890")
 *   - subject (VARCHAR(255)) - The topic subject/title
 *   - message (TEXT) - The main topic message
 *   - author (VARCHAR(100)) - The author's name
 *   - created_at (TIMESTAMP) - When the topic was created
 * 
 * Table: replies
 * Columns:
 *   - id (INT, PRIMARY KEY, AUTO_INCREMENT)
 *   - reply_id (VARCHAR(50), UNIQUE) - The reply's unique identifier (e.g., "reply_1234567890")
 *   - topic_id (VARCHAR(50)) - Foreign key to topics.topic_id
 *   - text (TEXT) - The reply message
 *   - author (VARCHAR(100)) - The reply author's name
 *   - created_at (TIMESTAMP) - When the reply was created
 * 
 * API Endpoints:
 * 
 * Topics:
 *   GET    /api/discussion.php?resource=topics              - Get all topics (with optional search)
 *   GET    /api/discussion.php?resource=topics&id={id}      - Get single topic
 *   POST   /api/discussion.php?resource=topics              - Create new topic
 *   PUT    /api/discussion.php?resource=topics              - Update a topic
 *   DELETE /api/discussion.php?resource=topics&id={id}      - Delete a topic
 * 
 * Replies:
 *   GET    /api/discussion.php?resource=replies&topic_id={id} - Get all replies for a topic
 *   POST   /api/discussion.php?resource=replies              - Create new reply
 *   DELETE /api/discussion.php?resource=replies&id={id}      - Delete a reply
 * 
 * Response Format: JSON
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

    $rows = $stmt->fetchAll();
    sendResponse($rows);
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
    foreach (['topic_id', 'subject', 'message', 'author'] as $field) {
        if (empty($data[$field])) sendResponse(['error' => "$field is required"], 400);
    }

    $topic_id = sanitizeInput($data['topic_id']);
    $subject = sanitizeInput($data['subject']);
    $message = sanitizeInput($data['message']);
    $author = sanitizeInput($data['author']);

    $stmt = $db->prepare("SELECT topic_id FROM topics WHERE topic_id = :id");
    $stmt->bindValue(':id', $topic_id);
    $stmt->execute();
    if ($stmt->fetch()) sendResponse(['error' => 'Topic ID already exists'], 409);

    $stmt = $db->prepare("INSERT INTO topics (topic_id, subject, message, author, created_at) VALUES (:id, :subject, :message, :author, CURRENT_TIMESTAMP)");
    $stmt->bindValue(':id', $topic_id);
    $stmt->bindValue(':subject', $subject);
    $stmt->bindValue(':message', $message);
    $stmt->bindValue(':author', $author);

    try {
        $stmt->execute();
        sendResponse(['message' => 'Topic created', 'topic_id' => $topic_id], 201);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Failed to create topic', 'details' => $e->getMessage()], 500);
    }
}

function updateTopic($db, $data) {
    if (empty($data['topic_id'])) sendResponse(['error' => 'topic_id required'], 400);

    $stmt = $db->prepare("SELECT topic_id FROM topics WHERE topic_id = :id");
    $stmt->bindValue(':id', $data['topic_id']);
    $stmt->execute();
    if (!$stmt->fetch()) sendResponse(['error' => 'Topic not found'], 404);

    $fields = [];
    $params = [':id' => $data['topic_id']];

    if (isset($data['subject'])) { $fields[] = "subject = :subject"; $params[':subject'] = sanitizeInput($data['subject']); }
    if (isset($data['message'])) { $fields[] = "message = :message"; $params[':message'] = sanitizeInput($data['message']); }

    if (!$fields) sendResponse(['error' => 'No fields to update'], 400);

    $sql = "UPDATE topics SET " . implode(', ', $fields) . " WHERE topic_id = :id";
    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);

    try {
        $stmt->execute();
        sendResponse(['message' => 'Topic updated']);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Failed to update topic', 'details' => $e->getMessage()], 500);
    }
}

function deleteTopic($db, $topicId) {
    if (!$topicId) sendResponse(['error' => 'topic_id required'], 400);

    $stmt = $db->prepare("SELECT topic_id FROM topics WHERE topic_id = :id");
    $stmt->bindValue(':id', $topicId);
    $stmt->execute();
    if (!$stmt->fetch()) sendResponse(['error' => 'Topic not found'], 404);

    try {
        $db->beginTransaction();
        $stmt1 = $db->prepare("DELETE FROM replies WHERE topic_id = :id");
        $stmt1->bindValue(':id', $topicId);
        $stmt1->execute();

        $stmt2 = $db->prepare("DELETE FROM topics WHERE topic_id = :id");
        $stmt2->bindValue(':id', $topicId);
        $stmt2->execute();
        $db->commit();

        sendResponse(['message' => 'Topic and replies deleted']);
    } catch (PDOException $e) {
        $db->rollBack();
        sendResponse(['error' => 'Failed to delete topic', 'details' => $e->getMessage()], 500);
    }
}

// ======================= REPLIES FUNCTIONS =======================
function getRepliesByTopicId($db, $topicId) {
    if (!$topicId) sendResponse(['error' => 'topic_id required'], 400);

    $stmt = $db->prepare("SELECT reply_id, topic_id, text, author, created_at FROM replies WHERE topic_id = :id ORDER BY created_at ASC");
    $stmt->bindValue(':id', $topicId);
    $stmt->execute();

    $rows = $stmt->fetchAll();
    sendResponse($rows);
}

function createReply($db, $data) {
    foreach (['reply_id','topic_id','text','author'] as $field) {
        if (empty($data[$field])) sendResponse(['error' => "$field is required"], 400);
    }

    $reply_id = sanitizeInput($data['reply_id']);
    $topic_id = sanitizeInput($data['topic_id']);
    $text = sanitizeInput($data['text']);
    $author = sanitizeInput($data['author']);

    $stmt = $db->prepare("SELECT topic_id FROM topics WHERE topic_id = :id");
    $stmt->bindValue(':id', $topic_id);
    $stmt->execute();
    if (!$stmt->fetch()) sendResponse(['error' => 'Parent topic not found'], 404);

    $stmt = $db->prepare("SELECT reply_id FROM replies WHERE reply_id = :id");
    $stmt->bindValue(':id', $reply_id);
    $stmt->execute();
    if ($stmt->fetch()) sendResponse(['error' => 'Reply ID already exists'], 409);

    $stmt = $db->prepare("INSERT INTO replies (reply_id, topic_id, text, author, created_at) VALUES (:id, :tid, :text, :author, CURRENT_TIMESTAMP)");
    $stmt->bindValue(':id', $reply_id);
    $stmt->bindValue(':tid', $topic_id);
    $stmt->bindValue(':text', $text);
    $stmt->bindValue(':author', $author);

    try {
        $stmt->execute();
        sendResponse(['message' => 'Reply created', 'reply_id' => $reply_id], 201);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Failed to create reply', 'details' => $e->getMessage()], 500);
    }
}

function deleteReply($db, $replyId) {
    if (!$replyId) sendResponse(['error' => 'reply_id required'], 400);

    $stmt = $db->prepare("SELECT reply_id FROM replies WHERE reply_id = :id");
    $stmt->bindValue(':id', $replyId);
    $stmt->execute();
    if (!$stmt->fetch()) sendResponse(['error' => 'Reply not found'], 404);

    $stmt = $db->prepare("DELETE FROM replies WHERE reply_id = :id");
    $stmt->bindValue(':id', $replyId);
    try {
        $stmt->execute();
        sendResponse(['message' => 'Reply deleted']);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Failed to delete reply', 'details' => $e->getMessage()], 500);
    }
}

// ======================= ROUTER =======================
try {
    if ($method === 'GET') {
        if ($resource === 'topics') {
            if (!empty($_GET['id'])) getTopicById($db, $_GET['id']);
            else getAllTopics($db);
        } elseif ($resource === 'replies') {
            if (!empty($_GET['topic_id'])) getRepliesByTopicId($db, $_GET['topic_id']);
            else sendResponse([], 200);
        } else sendResponse(['error'=>'Invalid resource'],400);

    } elseif ($method === 'POST') {
        if ($resource === 'topics') createTopic($db, $body);
        elseif ($resource === 'replies') createReply($db, $body);
        else sendResponse(['error'=>'Invalid resource for POST'],400);

    } elseif ($method === 'PUT' || $method === 'PATCH') {
        if ($resource === 'topics') updateTopic($db, $body);
        else sendResponse(['error'=>'PUT not supported for this resource'],400);

    } elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? ($body['id'] ?? null);
        if ($resource === 'topics') deleteTopic($db, $id);
        elseif ($resource === 'replies') deleteReply($db, $id);
        else sendResponse(['error'=>'Invalid resource for DELETE'],400);

    } else sendResponse(['error'=>'Method not supported'],405);

} catch (PDOException $e) {
    sendResponse(['error'=>'Database error','details'=>$e->getMessage()],500);
} catch (Exception $e) {
    sendResponse(['error'=>'Server error','details'=>$e->getMessage()],500);
}

// ======================= HELPER FUNCTIONS =======================
function sendResponse($data, $statusCode=200) {
    http_response_code($statusCode);
    if (!is_array($data)) $data=['message'=> (string)$data];
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitizeInput($data) {
    if (!is_string($data)) return $data;
    $s = trim($data);
    $s = strip_tags($s);
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function isValidResource($resource) {
    $allowed = ['topics', 'replies'];
    return in_array($resource, $allowed);
}

?>
