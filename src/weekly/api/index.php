 <?php
/**
 * api.php - Weekly Course Breakdown API
 * Fully implemented version with CRUD operations for weeks and comments
 */

// ============================================================================
// SETUP AND CONFIGURATION
// ============================================================================
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include the database connection class
require_once 'config.php'; // Make sure this file returns a $db PDO instance
$db = $db ?? null;
if (!$db) {
    sendError("Database connection failed", 500);
}

// Get HTTP method
$method = $_SERVER['REQUEST_METHOD'];

// Get request body for POST and PUT
$inputData = json_decode(file_get_contents('php://input'), true);

// Get resource type (?resource=weeks or ?resource=comments)
$resource = $_GET['resource'] ?? 'weeks';

// ============================================================================
// WEEKS CRUD OPERATIONS
// ============================================================================
function getAllWeeks($db) {
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? 'start_date';
    $order = strtolower($_GET['order'] ?? 'asc');
    
    $allowedSortFields = ['title', 'start_date', 'created_at'];
    $sort = isValidSortField($sort, $allowedSortFields) ? $sort : 'start_date';
    $order = ($order === 'desc') ? 'DESC' : 'ASC';
    
    $sql = "SELECT week_id, title, start_date, description, links, created_at FROM weeks";
    $params = [];
    
    if (!empty($search)) {
        $sql .= " WHERE title LIKE ? OR description LIKE ?";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm];
    }
    
    $sql .= " ORDER BY $sort $order";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Decode links JSON
    foreach ($weeks as &$week) {
        $week['links'] = json_decode($week['links'], true);
    }
    
    sendResponse(['success' => true, 'data' => $weeks]);
}

function getWeekById($db, $weekId) {
    if (!$weekId) sendError("week_id is required", 400);
    
    $stmt = $db->prepare("SELECT week_id, title, start_date, description, links, created_at FROM weeks WHERE week_id = ?");
    $stmt->execute([$weekId]);
    $week = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$week) sendError("Week not found", 404);
    
    $week['links'] = json_decode($week['links'], true);
    sendResponse(['success' => true, 'data' => $week]);
}

function createWeek($db, $data) {
    $weekId = sanitizeInput($data['week_id'] ?? '');
    $title = sanitizeInput($data['title'] ?? '');
    $startDate = $data['start_date'] ?? '';
    $description = sanitizeInput($data['description'] ?? '');
    $links = is_array($data['links'] ?? null) ? json_encode($data['links']) : json_encode([]);
    
    if (!$weekId || !$title || !$startDate || !$description) sendError("week_id, title, start_date, description are required", 400);
    if (!validateDate($startDate)) sendError("Invalid date format, expected YYYY-MM-DD", 400);
    
    $check = $db->prepare("SELECT 1 FROM weeks WHERE week_id = ?");
    $check->execute([$weekId]);
    if ($check->fetch()) sendError("week_id already exists", 409);
    
    $stmt = $db->prepare("INSERT INTO weeks (week_id, title, start_date, description, links, created_at, updated_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
    if ($stmt->execute([$weekId, $title, $startDate, $description, $links])) {
        getWeekById($db, $weekId);
    } else {
        sendError("Failed to create week", 500);
    }
}

function updateWeek($db, $data) {
    $weekId = sanitizeInput($data['week_id'] ?? '');
    if (!$weekId) sendError("week_id is required", 400);
    
    $stmt = $db->prepare("SELECT * FROM weeks WHERE week_id = ?");
    $stmt->execute([$weekId]);
    if (!$stmt->fetch()) sendError("Week not found", 404);
    
    $fields = [];
    $params = [];
    
    if (!empty($data['title'])) { $fields[] = "title = ?"; $params[] = sanitizeInput($data['title']); }
    if (!empty($data['start_date'])) {
        if (!validateDate($data['start_date'])) sendError("Invalid date format", 400);
        $fields[] = "start_date = ?"; $params[] = $data['start_date'];
    }
    if (!empty($data['description'])) { $fields[] = "description = ?"; $params[] = sanitizeInput($data['description']); }
    if (isset($data['links']) && is_array($data['links'])) { $fields[] = "links = ?"; $params[] = json_encode($data['links']); }
    
    if (empty($fields)) sendError("No fields to update", 400);
    
    $fields[] = "updated_at = CURRENT_TIMESTAMP";
    $sql = "UPDATE weeks SET " . implode(", ", $fields) . " WHERE week_id = ?";
    $params[] = $weekId;
    
    $stmt = $db->prepare($sql);
    if ($stmt->execute($params)) {
        getWeekById($db, $weekId);
    } else {
        sendError("Failed to update week", 500);
    }
}

function deleteWeek($db, $weekId) {
    if (!$weekId) sendError("week_id is required", 400);
    
    $stmt = $db->prepare("SELECT 1 FROM weeks WHERE week_id = ?");
    $stmt->execute([$weekId]);
    if (!$stmt->fetch()) sendError("Week not found", 404);
    
    // Delete associated comments
    $stmt = $db->prepare("DELETE FROM comments WHERE week_id = ?");
    $stmt->execute([$weekId]);
    
    // Delete week
    $stmt = $db->prepare("DELETE FROM weeks WHERE week_id = ?");
    if ($stmt->execute([$weekId])) {
        sendResponse(['success' => true, 'message' => "Week and associated comments deleted"]);
    } else {
        sendError("Failed to delete week", 500);
    }
}

// ============================================================================
// COMMENTS CRUD OPERATIONS
// ============================================================================
function getCommentsByWeek($db, $weekId) {
    if (!$weekId) sendError("week_id is required", 400);
    
    $stmt = $db->prepare("SELECT id, week_id, author, text, created_at FROM comments WHERE week_id = ? ORDER BY created_at ASC");
    $stmt->execute([$weekId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendResponse(['success' => true, 'data' => $comments]);
}

function createComment($db, $data) {
    $weekId = sanitizeInput($data['week_id'] ?? '');
    $author = sanitizeInput($data['author'] ?? '');
    $text = sanitizeInput($data['text'] ?? '');
    
    if (!$weekId || !$author || !$text) sendError("week_id, author, text are required", 400);
    
    $stmt = $db->prepare("SELECT 1 FROM weeks WHERE week_id = ?");
    $stmt->execute([$weekId]);
    if (!$stmt->fetch()) sendError("Week not found", 404);
    
    $stmt = $db->prepare("INSERT INTO comments (week_id, author, text, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
    if ($stmt->execute([$weekId, $author, $text])) {
        $commentId = $db->lastInsertId();
        $stmt = $db->prepare("SELECT id, week_id, author, text, created_at FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);
        sendResponse(['success' => true, 'data' => $comment], 201);
    } else {
        sendError("Failed to create comment", 500);
    }
}

function deleteComment($db, $commentId) {
    if (!$commentId) sendError("id is required", 400);
    
    $stmt = $db->prepare("SELECT 1 FROM comments WHERE id = ?");
    $stmt->execute([$commentId]);
    if (!$stmt->fetch()) sendError("Comment not found", 404);
    
    $stmt = $db->prepare("DELETE FROM comments WHERE id = ?");
    if ($stmt->execute([$commentId])) {
        sendResponse(['success' => true, 'message' => "Comment deleted"]);
    } else {
        sendError("Failed to delete comment", 500);
    }
}

// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================
try {
    if ($resource === 'weeks') {
        if ($method === 'GET') {
            $weekId = $_GET['week_id'] ?? null;
            if ($weekId) getWeekById($db, $weekId);
            else getAllWeeks($db);
        } elseif ($method === 'POST') { createWeek($db, $inputData);
        } elseif ($method === 'PUT') { updateWeek($db, $inputData);
        } elseif ($method === 'DELETE') {
            $weekId = $_GET['week_id'] ?? ($inputData['week_id'] ?? null);
            deleteWeek($db, $weekId);
        } else { http_response_code(405); sendError("Method Not Allowed", 405); }
    } elseif ($resource === 'comments') {
        if ($method === 'GET') {
            $weekId = $_GET['week_id'] ?? null;
            getCommentsByWeek($db, $weekId);
        } elseif ($method === 'POST') { createComment($db, $inputData);
        } elseif ($method === 'DELETE') {
            $commentId = $_GET['id'] ?? ($inputData['id'] ?? null);
            deleteComment($db, $commentId);
        } else { http_response_code(405); sendError("Method Not Allowed", 405); }
    } else {
        http_response_code(400);
        sendError("Invalid resource. Use 'weeks' or 'comments'", 400);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    sendError("Database error occurred", 500);
} catch (Exception $e) {
    error_log($e->getMessage());
    sendError("Server error occurred", 500);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function sendError($message, $statusCode = 400) {
    sendResponse(['success' => false, 'error' => $message], $statusCode);
}

function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function isValidSortField($field, $allowedFields) {
    return in_array($field, $allowedFields);
}
?>
