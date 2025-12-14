  <?php
header('Content-Type: application/json; charset=utf-8');
session_start();

$WEEKS_FILE = __DIR__ . '/weeks.json';
$COMMENTS_FILE = __DIR__ . '/comments.json';

function read_json_file($path) {
    if (!file_exists($path)) return [];
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function write_json_file($path, $data) {
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function get_input() {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    return is_array($json) ? $json : $_POST;
}

function require_login() {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

function require_admin() {
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

/* ===== WEEKS ===== */

if ($action === 'weeks' && $method === 'GET') {
    echo json_encode(read_json_file($WEEKS_FILE));
    exit;
}

if ($action === 'week' && $method === 'GET') {
    $id = $_GET['id'] ?? '';
    foreach (read_json_file($WEEKS_FILE) as $w) {
        if ($w['id'] == $id) {
            echo json_encode($w);
            exit;
        }
    }
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

if ($action === 'week_create' && $method === 'POST') {
    require_admin();
    $input = get_input();

    $weeks = read_json_file($WEEKS_FILE);
    $week = [
        'id' => 'week_' . time(),
        'title' => $input['title'] ?? '',
        'startDate' => $input['startDate'] ?? '',
        'description' => $input['description'] ?? '',
        'links' => $input['links'] ?? []
    ];
    $weeks[] = $week;
    write_json_file($WEEKS_FILE, $weeks);
    echo json_encode($week);
    exit;
}

if ($action === 'week_update' && $method === 'POST') {
    require_admin();
    $id = $_GET['id'] ?? '';
    $input = get_input();
    $weeks = read_json_file($WEEKS_FILE);

    foreach ($weeks as &$w) {
        if ($w['id'] == $id) {
            $w = array_merge($w, $input);
            write_json_file($WEEKS_FILE, $weeks);
            echo json_encode(['ok' => true]);
            exit;
        }
    }

    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

if ($action === 'week_delete' && $method === 'POST') {
    require_admin();
    $id = $_GET['id'] ?? '';

    $weeks = array_values(array_filter(
        read_json_file($WEEKS_FILE),
        fn($w) => $w['id'] != $id
    ));
    write_json_file($WEEKS_FILE, $weeks);

    $comments = read_json_file($COMMENTS_FILE);
    unset($comments[$id]);
    write_json_file($COMMENTS_FILE, $comments);

    echo json_encode(['ok' => true]);
    exit;
}

/* ===== COMMENTS ===== */

if ($action === 'comments' && $method === 'GET') {
    $week_id = $_GET['week_id'] ?? '';
    $comments = read_json_file($COMMENTS_FILE);
    echo json_encode($week_id ? ($comments[$week_id] ?? []) : $comments);
    exit;
}

if ($action === 'comment_add' && $method === 'POST') {
    require_login();
    $input = get_input();

    $comment = [
        'id' => 'c_' . time(),
        'author' => $_SESSION['user'],
        'text' => $input['text'] ?? '',
        'created_at' => date('c')
    ];

    $comments = read_json_file($COMMENTS_FILE);
    $comments[$input['week_id']][] = $comment;
    write_json_file($COMMENTS_FILE, $comments);

    echo json_encode($comment);
    exit;
}

if ($action === 'comment_delete' && $method === 'POST') {
    require_login();
    $input = get_input();

    $comments = read_json_file($COMMENTS_FILE);
    foreach ($comments[$input['week_id']] ?? [] as $i => $c) {
        if ($c['id'] === $input['comment_id']) {
            array_splice($comments[$input['week_id']], $i, 1);
            write_json_file($COMMENTS_FILE, $comments);
            echo json_encode(['ok' => true]);
            exit;
        }
    }

    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Invalid action']);
