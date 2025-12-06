<?php
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
 *   - GET: Retrieve assignment(s) or comment(s)
 *   - POST: Create a new assignment or comment
 *   - PUT: Update an existing assignment
 *   - DELETE: Delete an assignment or comment
 * 
 * Response Format: JSON
 */

// -------------------- HEADERS & CORS --------------------
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *"); // tighten in production
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Preflight request
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
$resource = isset($_GET['resource']) ? $_GET['resource'] : null;
$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true);
if (!is_array($body)) $body = [];

// -------------------- ASSIGNMENT CRUD FUNCTIONS --------------------

function getAllAssignments($db) {
    $sql = "SELECT id, title, description, files, due_date, created_at, updated_at FROM assignments";
    $params = [];
    $clauses = [];

    if (isset($_GET['search']) && strlen(trim($_GET['search'])) > 0) {
        $search = '%' . trim($_GET['search']) . '%';
        $clauses[] = "(title LIKE :search OR description LIKE :search)";
        $params[':search'] = $search;
    }

    if (!empty($clauses)) {
        $sql .= " WHERE " . implode(' AND ', $clauses);
    }

    $allowedSort = ['title', 'due_date', 'created_at'];
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
    if (!in_array($sort, $allowedSort)) $sort = 'created_at';

    $order = isset($_GET['order']) ? strtolower($_GET['order']) : 'asc';
    if (!in_array($order, ['asc', 'desc'])) $order = 'asc';

    $sql .= " ORDER BY {$sort} " . strtoupper($order);

    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();

    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['files'] = $r['files'] ? json_decode($r['files'], true) : [];
    }

    sendResponse($rows, 200);
}

function getAssignmentById($db, $assignmentId) {
    if (!$assignmentId) sendResponse(['error' => 'Missing assignment id'], 400);

    $stmt = $db->prepare("SELECT id, title, description, files, due_date, created_at, updated_at FROM assignments WHERE id = :id LIMIT 1");
    $stmt->bindValue(':id', $assignmentId);
    $stmt->execute();
    $row = $stmt->fetch();

    if (!$row) sendResponse(['error' => 'Assignment not found'], 404);

    $row['files'] = $row['files'] ? json_decode($row['files'], true) : [];

    sendResponse($row, 200);
}

function createAssignment($db, $data) {
    $title = isset($data['title']) ? sanitizeInput($data['title']) : '';
    $description = isset($data['description']) ? sanitizeInput($data['description']) : '';
    $due_date = isset($data['due_date']) ? $data['due_date'] : null;
    $files = isset($data['files']) && is_array($data['files']) ? $data['files'] : [];

    if ($title === '') sendResponse(['error' => 'Title is required'], 400);
    if ($description === '') sendResponse(['error' => 'Description is required'], 400);
    if ($due_date !== null && $due_date !== '' && !validateDate($due_date)) sendResponse(['error' => 'due_date must be YYYY-MM-DD'], 400);

    $filesJson = json_encode(array_values($files), JSON_UNESCAPED_UNICODE);

    $sql = "INSERT INTO assignments (title, description, files, due_date, created_at, updated_at) VALUES (:title, :description, :files, :due_date, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':title', $title);
    $stmt->bindValue(':description', $description);
    $stmt->bindValue(':files', $filesJson);
    $stmt->bindValue(':due_date', $due_date ? $due_date : null);

    try {
        $stmt->execute();
        $id = $db->lastInsertId();
        $created = [
            'id' => (int)$id,
            'title' => $title,
            'description' => $description,
            'files' => $files,
            'due_date' => $due_date
        ];
        sendResponse(['message' => 'Assignment created', 'assignment' => $created], 201);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Failed to create assignment', 'details' => $e->getMessage()], 500);
    }
}

function updateAssignment($db, $data) {
    if (!isset($data['id'])) sendResponse(['error' => 'id is required for update'], 400);
    $id = $data['id'];

    $stmt = $db->prepare("SELECT id FROM assignments WHERE id = :id");
    $stmt->bindValue(':id', $id);
    $stmt->execute();
    if (!$stmt->fetch()) sendResponse(['error' => 'Assignment not found'], 404);

    $fields = [];
    $params = [];

    if (isset($data['title'])) {
        $fields[] = 'title = :title';
        $params[':title'] = sanitizeInput($data['title']);
    }
    if (isset($data['description'])) {
        $fields[] = 'description = :description';
        $params[':description'] = sanitizeInput($data['description']);
    }
    if (isset($data['due_date'])) {
        if ($data['due_date'] !== '' && !validateDate($data['due_date'])) sendResponse(['error' => 'Invalid due_date format (YYYY-MM-DD)'], 400);
        $fields[] = 'due_date = :due_date';
        $params[':due_date'] = $data['due_date'] === '' ? null : $data['due_date'];
    }
    if (isset($data['files']) && is_array($data['files'])) {
        $fields[] = 'files = :files';
        $params[':files'] = json_encode(array_values($data['files']), JSON_UNESCAPED_UNICODE);
    }

    if (empty($fields)) sendResponse(['error' => 'No fields to update'], 400);

    $sql = "UPDATE assignments SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':id', $id);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);

    try {
        $stmt->execute();
        sendResponse(['message' => 'Assignment updated'], 200);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Failed to update assignment', 'details' => $e->getMessage()], 500);
    }
}

function deleteAssignment($db, $assignmentId) {
    if (!$assignmentId) sendResponse(['error' => 'assignment id required'], 400);

    $stmt = $db->prepare("SELECT id FROM assignments WHERE id = :id");
    $stmt->bindValue(':id', $assignmentId);
    $stmt->execute();
    if (!$stmt->fetch()) sendResponse(['error' => 'Assignment not found'], 404);

    try {
        $db->beginTransaction();

        $stmt1 = $db->prepare("DELETE FROM comments WHERE assignment_id = :id");
        $stmt1->bindValue(':id', $assignmentId);
        $stmt1->execute();

        $stmt2 = $db->prepare("DELETE FROM assignments WHERE id = :id");
        $stmt2->bindValue(':id', $assignmentId);
        $stmt2->execute();

        $db->commit();
        sendResponse(['message' => 'Assignment and related comments deleted'], 200);
    } catch (PDOException $e) {
        $db->rollBack();
        sendResponse(['error' => 'Failed to delete assignment', 'details' => $e->getMessage()], 500);
    }
}

// -------------------- COMMENT CRUD FUNCTIONS --------------------

function getCommentsByAssignment($db, $assignmentId) {
    if (!$assignmentId) sendResponse(['error' => 'assignment_id is required'], 400);

    $stmt = $db->prepare("SELECT id, assignment_id, author, text, created_at FROM comments WHERE assignment_id = :aid ORDER BY created_at ASC");
    $stmt->bindValue(':aid', $assignmentId);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    sendResponse($rows, 200);
}

function createComment($db, $data) {
    $assignment_id = isset($data['assignment_id']) ? $data['assignment_id'] : null;
    $author = isset($data['author']) ? sanitizeInput($data['author']) : 'Student';
    $text = isset($data['text']) ? sanitizeInput($data['text']) : '';

    if (!$assignment_id) sendResponse(['error' => 'assignment_id is required'], 400);
    if (trim($text) === '') sendResponse(['error' => 'text is required'], 400);

    $stmt = $db->prepare("SELECT id FROM assignments WHERE id = :id");
    $stmt->bindValue(':id', $assignment_id);
    $stmt->execute();
    if (!$stmt->fetch()) sendResponse(['error' => 'Assignment not found'], 404);

    $sql = "INSERT INTO comments (assignment_id, author, text, created_at) VALUES (:aid, :author, :text, CURRENT_TIMESTAMP)";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':aid', $assignment_id);
    $stmt->bindValue(':author', $author);
    $stmt->bindValue(':text', $text);

    try {
        $stmt->execute();
        $id = $db->lastInsertId();
        $created = [
            'id' => (int)$id,
            'assignment_id' => $assignment_id,
            'author' => $author,
            'text' => $text
        ];
        sendResponse(['message' => 'Comment created', 'comment' => $created], 201);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Failed to create comment', 'details' => $e->getMessage()], 500);
    }
}

function deleteComment($db, $commentId) {
    if (!$commentId) sendResponse(['error' => 'comment id required'], 400);

    $stmt = $db->prepare("SELECT id FROM comments WHERE id = :id");
    $stmt->bindValue(':id', $commentId);
    $stmt->execute();
    if (!$stmt->fetch()) sendResponse(['error' => 'Comment not found'], 404);

    $stmt = $db->prepare("DELETE FROM comments WHERE id = :id");
    $stmt->bindValue(':id', $commentId);
    try {
        $stmt->execute();
        sendResponse(['message' => 'Comment deleted'], 200);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Failed to delete comment', 'details' => $e->getMessage()], 500);
    }
}

// -------------------- MAIN REQUEST ROUTER --------------------
try {
    if ($method === 'GET') {
        if ($resource === 'assignments') {
            if (isset($_GET['id'])) {
                getAssignmentById($db, $_GET['id']);
            } else {
                getAllAssignments($db);
            }
        } elseif ($resource === 'comments') {
            if (isset($_GET['assignment_id'])) {
                getCommentsByAssignment($db, $_GET['assignment_id']);
            } else {
                $stmt = $db->query("SELECT id, assignment_id, author, text, created_at FROM comments ORDER BY created_at ASC");
                sendResponse($stmt->fetchAll(), 200);
            }
        } else {
            sendResponse(['error' => 'Invalid resource'], 400);
        }

    } elseif ($method === 'POST') {
        if ($resource === 'assignments') {
            createAssignment($db, $body);
        } elseif ($resource === 'comments') {
            createComment($db, $body);
        } else {
            sendResponse(['error' => 'Invalid resource for POST'], 400);
        }

    } elseif ($method === 'PUT' || $method === 'PATCH') {
        if ($resource === 'assignments') {
            updateAssignment($db, $body);
        } else {
            sendResponse(['error' => 'PUT not supported for this resource'], 400);
        }

    } elseif ($method === 'DELETE') {
        if ($resource === 'assignments') {
            $id = isset($_GET['id']) ? $_GET['id'] : (isset($body['id']) ? $body['id'] : null);
            deleteAssignment($db, $id);
        } elseif ($resource === 'comments') {
            $id = isset($_GET['id']) ? $_GET['id'] : (isset($body['id']) ? $body['id'] : null);
            deleteComment($db, $id);
        } else {
            sendResponse(['error' => 'Invalid resource for DELETE'], 400);
        }
    } else {
        sendResponse(['error' => 'Method not supported'], 405);
    }
} catch (PDOException $e) {
    sendResponse(['error' => 'Database error', 'details' => $e->getMessage()], 500);
} catch (Exception $e) {
    sendResponse(['error' => 'Server error', 'details' => $e->getMessage()], 500);
}

// -------------------- HELPER FUNCTIONS --------------------
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    if (!is_array($data)) $data = ['message' => (string)$data];
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitizeInput($data) {
    if (!is_string($data)) return $data;
    $s = trim($data);
    $s = strip_tags($s);
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function validateAllowedValue($value, $allowedValues) {
    if (!is_array($allowedValues)) return false;
    return in_array($value, $allowedValues, true);
}

?>
