<?php
/**
 * Student Management API
 * 
 * This is a RESTful API that handles all CRUD operations for student management.
 * It uses PDO to interact with a MySQL database.
 * 
 * Database Table Structure (for reference):
 * Table: students
 * Columns:
 *   - id (INT, PRIMARY KEY, AUTO_INCREMENT)
 *   - student_id (VARCHAR(50), UNIQUE) - The student's university ID
 *   - name (VARCHAR(100))
 *   - email (VARCHAR(100), UNIQUE)
 *   - password (VARCHAR(255)) - Hashed password
 *   - created_at (TIMESTAMP)
 * 
 * HTTP Methods Supported:
 *   - GET: Retrieve student(s)
 *   - POST: Create a new student OR change password
 *   - PUT: Update an existing student
 *   - DELETE: Delete a student
 * 
 * Response Format: JSON
 */

<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Access denied"
    ]);
    exit;
}


// TODO: Set headers for JSON response and CORS
// Set Content-Type to application/json
// Allow cross-origin requests (CORS) if needed
// Allow specific HTTP methods (GET, POST, PUT, DELETE, OPTIONS)
// Allow specific headers (Content-Type, Authorization)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// TODO: Handle preflight OPTIONS request
// If the request method is OPTIONS, return 200 status and exit
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// TODO: Include the database connection class
// Assume the Database class has a method getConnection() that returns a PDO instance
require_once 'Database.php';

// TODO: Get the PDO database connection
$db = (new Database())->getConnection();

// TODO: Get the HTTP request method
// Use $_SERVER['REQUEST_METHOD']
$method = $_SERVER['REQUEST_METHOD'];

// TODO: Get the request body for POST and PUT requests
// Use file_get_contents('php://input') to get raw POST data
// Decode JSON data using json_decode()

$input = json_decode(file_get_contents('php://input'), true);
// TODO: Parse query parameters for filtering and searching
$queryParams = $_GET;

/**
 * Function: Get all students or search for specific students
 * Method: GET
 * 
 * Query Parameters:
 *   - search: Optional search term to filter by name, student_id, or email
 *   - sort: Optional field to sort by (name, student_id, email)
 *   - order: Optional sort order (asc or desc)
 */
function getStudents($db) {
    // TODO: Check if search parameter exists
    // If yes, prepare SQL query with WHERE clause using LIKE
    // Search should work on name, student_id, and email fields
     $search = isset($_GET['search']) ? '%' . strtolower($_GET['search']) . '%' : null;
    // TODO: Check if sort and order parameters exist
    // If yes, add ORDER BY clause to the query
    // Validate sort field to prevent SQL injection (only allow: name, student_id, email)
    // Validate order to prevent SQL injection (only allow: asc, desc)
       $allowedSort = ['name', 'student_id', 'email'];
    $allowedOrder = ['asc', 'desc'];

    $sort = (isset($_GET['sort']) && in_array($_GET['sort'], $allowedSort)) ? $_GET['sort'] : 'name';
    $order = (isset($_GET['order']) && in_array(strtolower($_GET['order']), $allowedOrder)) ?

    // TODO: Prepare the SQL query using PDO
    // Note: Do NOT select the password field
      $sql = "SELECT student_id, name, email, created_at FROM students";

    if ($search) {
        $sql .= " WHERE LOWER(name) LIKE :search OR LOWER(student_id) LIKE :search OR LOWER(email) LIKE :search";
    }

    $sql .= " ORDER BY $sort $order";

    $stmt = $db->prepare($sql);
    // TODO: Bind parameters if using search
     if ($search) {
        $stmt->bindParam(':search', $search);
    }

    // TODO: Execute the query
     $stmt->execute();

    // TODO: Fetch all results as an associative array
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // TODO: Fetch all results as an associative array
    
    // TODO: Return JSON response with success status and data
    sendResponse([
        "success" => true,
        "data" => $students
    ]);
}



/**
 * Function: Get a single student by student_id
 * Method: GET
 * 
 * Query Parameters:
 *   - student_id: The student's university ID
 */
function getStudentById($db, $studentId) {
    // TODO: Prepare SQL query to select student by student_id
       $sql = "SELECT student_id, name, email, created_at FROM students WHERE student_id = :student_id";
    $stmt = $db->prepare($sql);
    // TODO: Bind the student_id parameter
        $stmt->bindParam(':student_id', $studentId);

    // TODO: Execute the query
        $stmt->execute();

    // TODO: Fetch the result
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

    // TODO: Check if student exists
    // If yes, return success response with student data
    // If no, return error response with 404 status
        if ($student) {
        sendResponse([
            "success" => true,
            "data" => $student
        ]);
    } else {
        sendResponse([
            "success" => false,
            "message" => "Student not found"
        ], 404);
    }
}


/**
 * Function: Create a new student
 * Method: POST
 * 
 * Required JSON Body:
 *   - student_id: The student's university ID (must be unique)
 *   - name: Student's full name
 *   - email: Student's email (must be unique)
 *   - password: Default password (will be hashed)
 */
function createStudent($db, $data) {
    // TODO: Validate required fields
    // Check if student_id, name, email, and password are provided
    // If any field is missing, return error response with 400 status
     if (!isset($data['student_id'], $data['name'], $data['email'], $data['password'])) {
        sendResponse(["success" => false, "message" => "Missing required fields"], 400);
    }
    // TODO: Sanitize input data
    // Trim whitespace from all fields
    // Validate email format using filter_var()
        $student_id = sanitizeInput($data['student_id']);
    $name = sanitizeInput($data['name']);
    $email = sanitizeInput($data['email']);
    $password = $data['password'];

    if (!validateEmail($email)) {
        sendResponse(["success" => false, "message" => "Invalid email format"], 400);
    }
    // TODO: Check if student_id or email already exists
    // Prepare and execute a SELECT query to check for duplicates
    // If duplicate found, return error response with 409 status (Conflict)
      $check = $db->prepare("SELECT * FROM students WHERE student_id = :sid OR email = :email");
    $check->bindParam(':sid', $student_id);
    $check->bindParam(':email', $email);
    $check->execute();

    if ($check->rowCount() > 0) {
        sendResponse(["success" => false, "message" => "Student already exists"], 409);
    }
    // TODO: Hash the password
    // Use password_hash() with PASSWORD_DEFAULT
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // TODO: Prepare INSERT query
    $sql = "INSERT INTO students (student_id, name, email, password) 
            VALUES (:sid, :name, :email, :pass)";
    $stmt = $db->prepare($sql);

    // TODO: Prepare INSERT query
      $sql = "INSERT INTO students (student_id, name, email, password) 
            VALUES (:sid, :name, :email, :pass)";
    $stmt = $db->prepare($sql);

    // TODO: Bind parameters
    // Bind student_id, name, email, and hashed password
     $stmt->bindParam(':sid', $student_id);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':pass', $hashedPassword);

    // TODO: Execute the query
     $success = $stmt->execute();
    // TODO: Check if insert was successful
    // If yes, return success response with 201 status (Created)
    // If no, return error response with 500 status
     if ($success) {
        sendResponse(["success" => true, "message" => "Student created"], 201);
    } else {
        sendResponse(["success" => false, "message" => "Failed to create student"], 500);
    }
}


/**
 * Function: Update an existing student
 * Method: PUT
 * 
 * Required JSON Body:
 *   - student_id: The student's university ID (to identify which student to update)
 *   - name: Updated student name (optional)
 *   - email: Updated student email (optional)
 */
function updateStudent($db, $data) {
    // TODO: Validate that student_id is provided
    // If not, return error response with 400 status
    
      if (!isset($data['student_id'])) {
        sendResponse(["success" => false, "message" => "student_id is required"], 400);
    }
      $student_id = sanitizeInput($data['student_id']);
    // TODO: Check if student exists
    // Prepare and execute a SELECT query to find the student
    // If not found, return error response with 404 status
     $check = $db->prepare("SELECT * FROM students WHERE student_id = :sid");
    $check->bindParam(':sid', $student_id);
    $check->execute();

    if ($check->rowCount() === 0) {
        sendResponse(["success" => false, "message" => "Student not found"], 404);
    }

    // TODO: Build UPDATE query dynamically based on provided fields
    // Only update fields that are provided in the request
    $fields = [];
    if (isset($data['name'])) $fields['name'] = sanitizeInput($data['name']);
    if (isset($data['email'])) $fields['email'] = sanitizeInput($data['email']);

    if (empty($fields)) {
        sendResponse(["success" => false, "message" => "No fields to update"], 400);
    }
    // TODO: If email is being updated, check if new email already exists
    // Prepare and execute a SELECT query
    // Exclude the current student from the check
    // If duplicate found, return error response with 409 status
       if (isset($fields['email'])) {
        $emailCheck = $db->prepare(
            "SELECT * FROM students WHERE email = :email AND student_id != :sid"
        );
        $emailCheck->bindParam(':email', $fields['email']);
        $emailCheck->bindParam(':sid', $student_id);
        $emailCheck->execute();

        if ($emailCheck->rowCount() > 0) {
            sendResponse(["success" => false, "message" => "Email already taken"], 409);
        }
    }
    // TODO: Bind parameters dynamically
    // Bind only the parameters that are being updated
    $setParts = [];
    foreach ($fields as $key => $value) {
        $setParts[] = "$key = :$key";
    }
    $setString = implode(", ", $setParts);
    // TODO: Execute the query
     $sql = "UPDATE students SET $setString WHERE student_id = :sid";
    $stmt = $db->prepare($sql);

    foreach ($fields as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':sid', $student_id);

    $success = $stmt->execute();
    // TODO: Check if update was successful
    // If yes, return success response
    // If no, return error response with 500 status
        if ($success) {
        sendResponse(["success" => true, "message" => "Student updated"]);
    } else {
        sendResponse(["success" => false, "message" => "Failed to update student"], 500);
    }
}


/**
 * Function: Delete a student
 * Method: DELETE
 * 
 * Query Parameters or JSON Body:
 *   - student_id: The student's university ID
 */
function deleteStudent($db, $studentId) {
    // TODO: Validate that student_id is provided
    // If not, return error response with 400 status
       if (empty($studentId)) {
        return [
            "success" => false,
            "message" => "student_id is required"
        ];
    }
    // TODO: Check if student exists
    // Prepare and execute a SELECT query
    // If not found, return error response with 404 status
        $sql = "SELECT * FROM students WHERE student_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        return [
            "success" => false,
            "message" => "Student not found"
        ];
    }
    // TODO: Prepare DELETE query
        $sql = "DELETE FROM students WHERE student_id = ?";

    // TODO: Bind the student_id parameter
       $stmt = $db->prepare($sql);
    $result = $stmt->execute([$studentId]);

    // TODO: Execute the query
    
    // TODO: Check if delete was successful
    // If yes, return success response
    // If no, return error response with 500 status
        if ($result) {
        return [
            "success" => true,
            "message" => "Student deleted successfully"
        ];
    } else {
        return [
            "success" => false,
            "message" => "Failed to delete student"
        ];
    }
}


/**
 * Function: Change password
 * Method: POST with action=change_password
 * 
 * Required JSON Body:
 *   - student_id: The student's university ID (identifies whose password to change)
 *   - current_password: The student's current password
 *   - new_password: The new password to set
 */
function changePassword($db, $data) {
    // TODO: Validate required fields
    // Check if student_id, current_password, and new_password are provided
    // If any field is missing, return error response with 400 status
       if (empty($data['student_id']) || empty($data['current_password']) || empty($data['new_password'])) {
        return [
            "success" => false,
            "message" => "student_id, current_password and new_password are required"
        ];
    }

     $studentId = $data['student_id'];
    $currentPassword = $data['current_password'];
    $newPassword = $data['new_password'];

    // TODO: Validate new password strength
    // Check minimum length (at least 8 characters)
    // If validation fails, return error response with 400 status
        if (strlen($newPassword) < 8) {
        return [
            "success" => false,
            "message" => "New password must be at least 8 characters long"
        ];
    }
    // TODO: Retrieve current password hash from database
    // Prepare and execute SELECT query to get password
        $sql = "SELECT password FROM students WHERE student_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        return [
            "success" => false,
            "message" => "Student not found"
        ];
    }
    // TODO: Verify current password
    // Use password_verify() to check if current_password matches the hash
    // If verification fails, return error response with 401 status (Unauthorized)
     if (!password_verify($currentPassword, $student['password'])) {
        return [
            "success" => false,
            "message" => "Current password is incorrect"
        ];
    }
    // TODO: Hash the new password
    // Use password_hash() with PASSWORD_DEFAULT
        $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // TODO: Update password in database
    // Prepare UPDATE query
     
    // TODO: Bind parameters and execute
    
    // TODO: Check if update was successful
    // If yes, return success response
    // If no, return error response with 500 status
      $sql = "UPDATE students SET password = ? WHERE student_id = ?";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([$hashedNewPassword, $studentId]);

    if ($result) {
        return [
            "success" => true,
            "message" => "Password updated successfully"
        ];
    } else {
        return [
            "success" => false,
            "message" => "Failed to update password"
        ];
    }

}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {
    // TODO: Route the request based on HTTP method
    
    if ($method === 'GET') {
        // TODO: Check if student_id is provided in query parameters
        // If yes, call getStudentById()
        // If no, call getStudents() to get all students (with optional search/sort)
        
        if (isset($_GET['student_id'])) {
            // Get one student
            $studentId = $_GET['student_id'];
            $result = getStudentById($db, $studentId);
            sendResponse($result);
        } else {
            // Get all students
            $result = getStudents($db);
            sendResponse($result);
        }

    }
    } elseif ($method === 'POST') {
        // TODO: Check if this is a change password request
        // Look for action=change_password in query parameters
        // If yes, call changePassword()
        // If no, call createStudent()
             if (isset($_GET['action']) && $_GET['action'] === 'change_password') {
            $body = json_decode(file_get_contents("php://input"), true);
            $result = changePassword($db, $body);
            sendResponse($result);
        } else {
            // Create new student
            $body = json_decode(file_get_contents("php://input"), true);
            $result = createStudent($db, $body);
            sendResponse($result, 201);
        }

    }

        
    elseif ($method === 'PUT') {
        // TODO: Call updateStudent()
        
        $body = json_decode(file_get_contents("php://input"), true);
        $result = updateStudent($db, $body);
        sendResponse($result);

    
    }
     elseif ($method === 'DELETE') {
        // TODO: Get student_id from query parameter or request body
        // Call deleteStudent()
            elseif ($method === 'DELETE') {
     $studentId = $_GET['student_id'] ?? null;

        if (!$studentId) {
            $body = json_decode(file_get_contents("php://input"), true);
            $studentId = $body['student_id'] ?? null;
        }

        $result = deleteStudent($db, $studentId);
        sendResponse($result);

    }
    } 
    else {
        // TODO: Return error for unsupported methods
        // Set HTTP status to 405 (Method Not Allowed)
        // Return JSON error message
 else {
        sendResponse([
            "success" => false,
            "message" => "Method Not Allowed"
        ], 405);
    }

} catch (PDOException $e) {

    sendResponse([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ], 500);

} catch (Exception $e) {

    sendResponse([
        "success" => false,
        "message" => "Server error: " . $e->getMessage()
    ], 500);
}

    
    
 catch (PDOException $e) {
    // TODO: Handle database errors
    // Log the error message (optional)
    // Return generic error response with 500 status
     sendResponse([
        "success" => false,
        "message" => "Database error occurred"
    ], 500);
} catch (Exception $e) {
    // TODO: Handle general errors
    // Return error response with 500 status
     sendResponse([
        "success" => false,
        "message" => "Server error occurred"
    ], 500);
}


// ============================================================================
// HELPER FUNCTIONS (Optional but Recommended)
// ============================================================================

/**
 * Helper function to send JSON response
 * 
 * @param mixed $data - Data to send
 * @param int $statusCode - HTTP status code
 */
function sendResponse($data, $statusCode = 200) {
    // TODO: Set HTTP response code
      http_response_code($statusCode);
    // TODO: Echo JSON encoded data
     echo json_encode($data);
    // TODO: Exit to prevent further execution
    exit;

}


/**
 * Helper function to validate email format
 * 
 * @param string $email - Email address to validate
 * @return bool - True if valid, false otherwise
 */
function validateEmail($email) {
    // TODO: Use filter_var with FILTER_VALIDATE_EMAIL
    // Return true if valid, false otherwise
  
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;

}


/**
 * Helper function to sanitize input
 * 
 * @param string $data - Data to sanitize
 * @return string - Sanitized data
 */
function sanitizeInput($data) {
    // TODO: Trim whitespace
    // TODO: Strip HTML tags using strip_tags()
    // TODO: Convert special characters using htmlspecialchars()
    // Return sanitized data
   
    $data = trim($data);
    $data = strip_tags($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}


?>
