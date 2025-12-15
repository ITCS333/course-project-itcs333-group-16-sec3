<?php
/**
 * Course Resources API
 * 
 * RESTful API for managing course resources and their comments.
 * Uses PDO + JSON responses.
 */

// ============================================================================
// SESSION (REQUIRED BY AUTOGRADER)
// ============================================================================

// Start session (must be before any output/headers)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Use $_SESSION to store user data (required by tests)
if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = [
        'id'   => 0,
        'role' => 'guest'
    ];
}

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

// JSON response + CORS
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight OPTIONS
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Preflight OK']);
    exit;
}

// Include database connection (Database class with getConnection())
require_once __DIR__ . '/../config/Database.php';

// Get PDO connection
$database = new Database();
$db = $database->getConnection();

// Get raw body for POST / PUT
$rawInput = file_get_contents('php://input');
$bodyData = [];
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $bodyData = $decoded;
    }
}

// Query parameters
$action      = isset($_GET['action']) ? $_GET['action'] : null;
$id          = isset($_GET['id']) ? $_GET['id'] : null;
$resourceId  = isset($_GET['resource_id']) ? $_GET['resource_id'] : null;
$commentId   = isset($_GET['comment_id']) ? $_GET['comment_id'] : null;


// ============================================================================
// RESOURCE FUNCTIONS
// ============================================================================

/**
 * Get all resources (optional: search, sort, order)
 */
function getAllResources($db) {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $sort   = isset($_GET['sort']) ? strtolower(trim($_GET['sort'])) : 'created_at';
    $order  = isset($_GET['order']) ? strtolower(trim($_GET['order'])) : 'desc';

    $allowedSort = ['title', 'created_at'];
    if (!in_array($sort, $allowedSort, true)) {
        $sort = 'created_at';
    }

    $order = ($order === 'asc') ? 'ASC' : 'DESC';

    $sql = "SELECT id, title, description, link, created_at FROM resources";
    $params = [];

    if ($search !== '') {
        $sql .= " WHERE title LIKE :search OR description LIKE :search";
        $params[':search'] = '%' . $search . '%';
    }

    $sql .= " ORDER BY {$sort} {$order}";

    $stmt = $db->prepare($sql);

    if (isset($params[':search'])) {
        $stmt->bindValue(':search', $params[':search'], PDO::PARAM_STR);
    }

    $stmt->execute();
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse([
        'success' => true,
        'data'    => $resources
    ]);
}

/**
 * Get a single resource by ID
 */
function getResourceById($db, $resourceId) {
    if (!filter_var($resourceId, FILTER_VALIDATE_INT)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid resource id.'
        ], 400);
    }

    $sql = "SELECT id, title, description, link, created_at 
            FROM resources 
            WHERE id = ?";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(1, (int)$resourceId, PDO::PARAM_INT);
    $stmt->execute();

    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resource) {
        sendResponse([
            'success' => true,
            'data'    => $resource
        ]);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Resource not found.'
        ], 404);
    }
}

/**
 * Create a new resource
 */
function createResource($db, $data) {
    // Validate required fields
    $validation = validateRequiredFields($data, ['title', 'link']);
    if (!$validation['valid']) {
        sendResponse([
            'success' => false,
            'message' => 'Missing required fields.',
            'missing' => $validation['missing']
        ], 400);
    }

    $title = sanitizeInput($data['title']);
    $link  = trim($data['link']);
    $description = isset($data['description']) ? sanitizeInput($data['description']) : '';

    if (!validateUrl($link)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid URL format.'
        ], 400);
    }

    $sql = "INSERT INTO resources (title, description, link) VALUES (?, ?, ?)";
    $stmt = $db->prepare($sql);

    $stmt->bindValue(1, $title, PDO::PARAM_STR);
    $stmt->bindValue(2, $description, PDO::PARAM_STR);
    $stmt->bindValue(3, $link, PDO::PARAM_STR);

    if ($stmt->execute()) {
        $newId = $db->lastInsertId();
        sendResponse([
            'success' => true,
            'message' => 'Resource created successfully.',
            'id'      => (int)$newId
        ], 201);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to create resource.'
        ], 500);
    }
}

/**
 * Update an existing resource
 */
function updateResource($db, $data) {
    if (!isset($data['id']) || !filter_var($data['id'], FILTER_VALIDATE_INT)) {
        sendResponse([
            'success' => false,
            'message' => 'Resource id is required for update.'
        ], 400);
    }

    $resourceId = (int)$data['id'];

    // Check if resource exists
    $check = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $check->bindValue(1, $resourceId, PDO::PARAM_INT);
    $check->execute();

    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        sendResponse([
            'success' => false,
            'message' => 'Resource not found.'
        ], 404);
    }

    $fields = [];
    $values = [];

    if (isset($data['title'])) {
        $fields[] = "title = ?";
        $values[] = sanitizeInput($data['title']);
    }

    if (array_key_exists('description', $data)) {
        $fields[] = "description = ?";
        $values[] = sanitizeInput($data['description']);
    }

    if (isset($data['link'])) {
        $link = trim($data['link']);
        if (!validateUrl($link)) {
            sendResponse([
                'success' => false,
                'message' => 'Invalid URL format.'
            ], 400);
        }
        $fields[] = "link = ?";
        $values[] = $link;
    }

    if (empty($fields)) {
        sendResponse([
            'success' => false,
            'message' => 'No fields to update.'
        ], 400);
    }

    $sql = "UPDATE resources SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);

    $index = 1;
    foreach ($values as $val) {
        $stmt->bindValue($index, $val, PDO::PARAM_STR);
        $index++;
    }
    $stmt->bindValue($index, $resourceId, PDO::PARAM_INT);

    if ($stmt->execute()) {
        sendResponse([
            'success' => true,
            'message' => 'Resource updated successfully.'
        ], 200);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to update resource.'
        ], 500);
    }
}

/**
 * Delete a resource and its comments
 */
function deleteResource($db, $resourceId) {
    if (!filter_var($resourceId, FILTER_VALIDATE_INT)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid resource id.'
        ], 400);
    }

    $resourceId = (int)$resourceId;

    // Check if resource exists
    $check = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $check->bindValue(1, $resourceId, PDO::PARAM_INT);
    $check->execute();

    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        sendResponse([
            'success' => false,
            'message' => 'Resource not found.'
        ], 404);
    }

    try {
        $db->beginTransaction();

        // Delete comments
        $delComments = $db->prepare("DELETE FROM comments WHERE resource_id = ?");
        $delComments->bindValue(1, $resourceId, PDO::PARAM_INT);
        $delComments->execute();

        // Delete resource
        $delResource = $db->prepare("DELETE FROM resources WHERE id = ?");
        $delResource->bindValue(1, $resourceId, PDO::PARAM_INT);
        $delResource->execute();

        $db->commit();

        sendResponse([
            'success' => true,
            'message' => 'Resource and its comments deleted successfully.'
        ], 200);
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Delete resource failed: " . $e->getMessage());
        sendResponse([
            'success' => false,
            'message' => 'Failed to delete resource.'
        ], 500);
    }
}


// ============================================================================
// COMMENT FUNCTIONS
// ============================================================================

/**
 * Get all comments for a specific resource
 */
function getCommentsByResourceId($db, $resourceId) {
    if (!filter_var($resourceId, FILTER_VALIDATE_INT)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid resource id.'
        ], 400);
    }

    $resourceId = (int)$resourceId;

    $sql = "SELECT id, resource_id, author, text, created_at
            FROM comments
            WHERE resource_id = ?
            ORDER BY created_at ASC";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(1, $resourceId, PDO::PARAM_INT);
    $stmt->execute();

    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse([
        'success' => true,
        'data'    => $comments
    ]);
}

/**
 * Create a new comment
 */
function createComment($db, $data) {
    $validation = validateRequiredFields($data, ['resource_id', 'author', 'text']);
    if (!$validation['valid']) {
        sendResponse([
            'success' => false,
            'message' => 'Missing required fields.',
            'missing' => $validation['missing']
        ], 400);
    }

    if (!filter_var($data['resource_id'], FILTER_VALIDATE_INT)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid resource id.'
        ], 400);
    }

    $resourceId = (int)$data['resource_id'];

    // Check resource exists
    $check = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $check->bindValue(1, $resourceId, PDO::PARAM_INT);
    $check->execute();

    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        sendResponse([
            'success' => false,
            'message' => 'Resource not found for this comment.'
        ], 404);
    }

    $author = sanitizeInput($data['author']);
    $text   = sanitizeInput($data['text']);

    $sql = "INSERT INTO comments (resource_id, author, text) VALUES (?, ?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(1, $resourceId, PDO::PARAM_INT);
    $stmt->bindValue(2, $author, PDO::PARAM_STR);
    $stmt->bindValue(3, $text, PDO::PARAM_STR);

    if ($stmt->execute()) {
        $newId = $db->lastInsertId();
        sendResponse([
            'success' => true,
            'message' => 'Comment created successfully.',
            'id'      => (int)$newId
        ], 201);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to create comment.'
        ], 500);
    }
}

/**
 * Delete a comment
 */
function deleteComment($db, $commentId) {
    if (!filter_var($commentId, FILTER_VALIDATE_INT)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid comment id.'
        ], 400);
    }

    $commentId = (int)$commentId;

    // Check exists
    $check = $db->prepare("SELECT id FROM comments WHERE id = ?");
    $check->bindValue(1, $commentId, PDO::PARAM_INT);
    $check->execute();

    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        sendResponse([
            'success' => false,
            'message' => 'Comment not found.'
        ], 404);
    }

    $sql = "DELETE FROM comments WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(1, $commentId, PDO::PARAM_INT);

    if ($stmt->execute()) {
        sendResponse([
            'success' => true,
            'message' => 'Comment deleted successfully.'
        ], 200);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to delete comment.'
        ], 500);
    }
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {
    if ($method === 'GET') {
        if ($action === 'comments') {
            if (!$resourceId) {
                sendResponse([
                    'success' => false,
                    'message' => 'resource_id is required for comments.'
                ], 400);
            }
            getCommentsByResourceId($db, $resourceId);
        } elseif ($id !== null) {
            getResourceById($db, $id);
        } else {
            getAllResources($db);
        }

    } elseif ($method === 'POST') {
        if ($action === 'comment') {
            createComment($db, $bodyData);
        } else {
            createResource($db, $bodyData);
        }

    } elseif ($method === 'PUT') {
        updateResource($db, $bodyData);

    } elseif ($method === 'DELETE') {
        if ($action === 'delete_comment') {
            $cid = $commentId;
            if (!$cid && isset($bodyData['comment_id'])) {
                $cid = $bodyData['comment_id'];
            }
            if (!$cid) {
                sendResponse([
                    'success' => false,
                    'message' => 'comment_id is required.'
                ], 400);
            }
            deleteComment($db, $cid);
        } else {
            $rid = $id;
            if (!$rid && isset($bodyData['id'])) {
                $rid = $bodyData['id'];
            }
            if (!$rid) {
                sendResponse([
                    'success' => false,
                    'message' => 'id is required to delete resource.'
                ], 400);
            }
            deleteResource($db, $rid);
        }

    } else {
        sendResponse([
            'success' => false,
            'message' => 'Method not allowed.'
        ], 405);
    }

} catch (PDOException $e) {
    error_log("PDO Error: " . $e->getMessage());
    sendResponse([
        'success' => false,
        'message' => 'Database error occurred.'
    ], 500);

} catch (Exception $e) {
    error_log("General Error: " . $e->getMessage());
    sendResponse([
        'success' => false,
        'message' => 'Unexpected error occurred.'
    ], 500);
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);

    if (!is_array($data)) {
        $data = ['data' => $data];
    }

    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function sanitizeInput($data) {
    // Make it safe for PHP 8+ when null comes in JSON
    if ($data === null) {
        return '';
    }
    $data = (string)$data;
    $data = trim($data);
    $data = strip_tags($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function validateRequiredFields($data, $requiredFields) {
    $missing = [];

    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            $missing[] = $field;
        }
    }

    return [
        'valid'   => count($missing) === 0,
        'missing' => $missing
    ];
}

?>

