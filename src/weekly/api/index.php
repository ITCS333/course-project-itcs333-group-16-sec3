 <?php
header('Content-Type: application/json; charset=utf-8');
session_start();

/* ============================
   Paths
============================ */
$WEEKS_FILE = __DIR__ . '/weeks.json';
$COMMENTS_FILE = __DIR__ . '/comments.json';

/* ============================
   Helpers
============================ */
function read_json_file($path) {
    if (!file_exists($path)) return [];
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function write_json_file($path, $data) {
    $tmp = $path . '.tmp';
    $fp = fopen($tmp, 'w');
    if (!$fp) return false;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return false; }
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    rename($tmp, $path);
    return true;
}

function get_input() {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    return is_array($json) ? $json : $_POST;
}

function require_admin() {
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}

function require_login() {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

/* ============================
   Routing
============================ */
$action = $_GET['action'] ?? $_POST['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

/* ============================
   WEEKS
============================ */
if ($action === 'weeks' && $method === 'GET') {
    echo json_encode(read_json_file($WEEKS_FILE));
    exit;
}

if ($action === 'week' && $method === 'GET') {
    $id = (string)($_GET['id'] ?? '');
    foreach (read_json_file($WEEKS_FILE) as $w) {
        if ((string)$w['id'] === $id) {
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

    $title = trim($input['title'] ?? '');
    if ($title === '') {
        http_response_code(400);
        echo json_encode(['error' => 'title required']);
        exit;
    }

    $weeks = read_json_file($WEEKS_FILE);
    $new = [
        'id' => 'week_' . time(),
        'title' => $title,
        'startDate' => trim($input['startDate'] ?? ''),
        'description' => trim($input['description'] ?? ''),
        'links' => is_array($input['links'] ?? null) ? $input['links'] : []
    ];

    $weeks[] = $new;
    write_json_file($WEEKS_FILE, $weeks);
    echo json_encode($new);
    exit;
}

if ($action === 'week_update' && in_array($method, ['POST','PUT'])) {
    require_admin();
    $id = (string)($_GET['id'] ?? '');
    $input = get_input();

    $weeks = read_json_file($WEEKS_FILE);
    foreach ($weeks as &$w) {
        if ((string)$w['id'] === $id) {
            $w['title'] = $input['title'] ?? $w['title'];
            $w['startDate'] = $input['startDate'] ?? $w['startDate'];
            $w['description'] = $input['description'] ?? $w['description'];
            $w['links'] = is_array($input['links'] ?? null) ? $input['links'] : $w['links'];
            write_json_file($WEEKS_FILE, $weeks);
            echo json_encode(['ok' => true]);
            exit;
        }
    }

    http_response_code(404);
    echo json_encode(['error' => 'not found']);
    exit;
}

if ($action === 'week_delete' && in_array($method, ['POST','DELETE'])) {
    require_admin();
    $id = (string)($_GET['id'] ?? '');

    $weeks = array_values(array_filter(
        read_json_file($WEEKS_FILE),
        fn($w) => (string)$w['id'] !== $id
    ));
    write_json_file($WEEKS_FILE, $weeks);

    $comments = read_json_file($COMMENTS_FILE);
    unset($comments[$id]);
    write_json_file($COMMENTS_FILE, $comments);

    echo json_encode(['ok' => true]);
    exit;
}

/* ============================
   COMMENTS
============================ */
if ($action === 'comments' && $method === 'GET') {
    $week_id = (string)($_GET['week_id'] ?? '');
    $comments = read_json_file($COMMENTS_FILE);
    echo json_encode($week_id ? ($comments[$week_id] ?? []) : $comments);
    exit;
}

if ($action === 'comment_add' && $method === 'POST') {
    require_login();
    $input = get_input();

    $week_id = (string)($input['week_id'] ?? '');
    $text = trim($input['text'] ?? '');
    if ($week_id === '' || $text === '') {
        http_response_code(400);
        echo json_encode(['error' => 'bad input']);
        exit;
    }

    $comments = read_json_file($COMMENTS_FILE);
    $comments[$week_id][] = [
        'id' => 'c_' . time(),
        'author' => $_SESSION['user'],
        'text' => $text,
        'created_at' => date('c')
    ];

    write_json_file($COMMENTS_FILE, $comments);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'comment_delete' && $method === 'POST') {
    require_login();
    $input = get_input();

    $week_id = (string)($input['week_id'] ?? '');
    $comment_id = $input['comment_id'] ?? '';

    $comments = read_json_file($COMMENTS_FILE);
    foreach ($comments[$week_id] ?? [] as $i => $c) {
        if ($c['id'] === $comment_id &&
            (($_SESSION['role'] ?? '') === 'admin' || $_SESSION['user'] === $c['author'])) {
            array_splice($comments[$week_id], $i, 1);
            write_json_file($COMMENTS_FILE, $comments);
            echo json_encode(['ok' => true]);
            exit;
        }
    }

    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

if ($action === 'comment_edit' && $method === 'POST') {
    require_login();
    $input = get_input();

    $week_id = (string)($input['week_id'] ?? '');
    $comment_id = $input['comment_id'] ?? '';
    $text = trim($input['text'] ?? '');

    $comments = read_json_file($COMMENTS_FILE);
    foreach ($comments[$week_id] ?? [] as $i => $c) {
        if ($c['id'] === $comment_id &&
            (($_SESSION['role'] ?? '') === 'admin' || $_SESSION['user'] === $c['author'])) {
            $comments[$week_id][$i]['text'] = $text;
            $comments[$week_id][$i]['edited_at'] = date('c');
            write_json_file($COMMENTS_FILE, $comments);
            echo json_encode(['ok' => true]);
            exit;
        }
    }

    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'invalid action']);

