 <?php
header('Content-Type: application/json; charset=utf-8');
session_start();

// Paths
$WEEKS_FILE = __DIR__ . '/weeks.json';
$COMMENTS_FILE = __DIR__ . '/comments.json';

// Helpers
function read_json_file($path) {
    if (!file_exists($path)) return [];
    $text = file_get_contents($path);
    $data = json_decode($text, true);
    return is_array($data) ? $data : [];
}

function write_json_file($path, $data) {
    $tmp = $path . '.tmp';
    $fp = fopen($tmp, 'w');
    if (!$fp) return false;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return false; }
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    rename($tmp, $path);
    return true;
}

function require_admin() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden: admin only']);
        exit;
    }
}

function require_login() {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: login required']);
        exit;
    }
}

// Routing
$action = $_GET['action'] ?? $_POST['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

// --- WEEKS ---
if ($action === 'weeks' && $method === 'GET') {
    $weeks = read_json_file($WEEKS_FILE);
    echo json_encode($weeks);
    exit;
}

if ($action === 'week' && $method === 'GET') {
    $id = (string)($_GET['id'] ?? '');
    $weeks = read_json_file($WEEKS_FILE);
    foreach ($weeks as $w) {
        if ((string)$w['id'] === $id) { echo json_encode($w); exit; }
    }
    http_response_code(404); echo json_encode(['error'=>'Not found']); exit;
}

if ($action === 'week_create' && $method === 'POST') {
    require_admin();
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) { http_response_code(400); echo json_encode(['error'=>'bad input']); exit; }

    $weeks = read_json_file($WEEKS_FILE);
    $title = trim($input['title'] ?? '');
    if ($title === '') { http_response_code(400); echo json_encode(['error'=>'title required']); exit; }
    $startDate = trim($input['startDate'] ?? '');
    $description = trim($input['description'] ?? '');
    $links = is_array($input['links'] ?? null) ? $input['links'] : [];

    $id = 'week_' . (count($weeks)+1) . '_' . time();
    $new = ['id'=>$id,'title'=>$title,'startDate'=>$startDate,'description'=>$description,'links'=>$links];
    $weeks[] = $new;
    if (write_json_file($WEEKS_FILE,$weeks)) { echo json_encode($new); exit; }
    http_response_code(500); echo json_encode(['error'=>'write failed']); exit;
}

if ($action === 'week_update' && in_array($method,['POST','PUT'])) {
    require_admin();
    $id = (string)($_GET['id'] ?? '');
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$id || !is_array($input)) { http_response_code(400); echo json_encode(['error'=>'bad input']); exit; }

    $weeks = read_json_file($WEEKS_FILE);
    $found = false;
    foreach ($weeks as &$w) {
        if ((string)$w['id'] === $id) {
            $w['title'] = $input['title'] ?? $w['title'];
            $w['startDate'] = $input['startDate'] ?? $w['startDate'];
            $w['description'] = $input['description'] ?? $w['description'];
            $w['links'] = is_array($input['links'] ?? null) ? $input['links'] : $w['links'];
            $found = true; break;
        }
    }
    if (!$found) { http_response_code(404); echo json_encode(['error'=>'not found']); exit; }
    if (write_json_file($WEEKS_FILE,$weeks)) { echo json_encode(['ok'=>true]); exit; }
    http_response_code(500); echo json_encode(['error'=>'write failed']); exit;
}

// ✅ هنا الصلاحية للـ POST و DELETE عشان الاختبارات تمشي
if ($action === 'week_delete' && in_array($method,['POST','DELETE'])) {
    require_admin();
    $id = (string)($_GET['id'] ?? '');
    if (!$id) { http_response_code(400); echo json_encode(['error'=>'id required']); exit; }

    $weeks = read_json_file($WEEKS_FILE);
    $new = []; $found=false;
    foreach ($weeks as $w) {
        if ((string)$w['id'] === $id) { $found=true; continue; }
        $new[] = $w;
    }
    if (!$found) { http_response_code(404); echo json_encode(['error'=>'not found']); exit; }
    if (!write_json_file($WEEKS_FILE,$new)) { http_response_code(500); echo json_encode(['error'=>'write failed']); exit; }

    $comments = read_json_file($COMMENTS_FILE);
    if (isset($comments[$id])) { unset($comments[$id]); write_json_file($COMMENTS_FILE,$comments); }

    echo json_encode(['ok'=>true]); exit;
}

// --- COMMENTS ---
if ($action === 'comments' && $method === 'GET') {
    $week_id = (string)($_GET['week_id'] ?? '');
    $comments = read_json_file($COMMENTS_FILE);
    echo json_encode($week_id === '' ? $comments : ($comments[$week_id] ?? []));
    exit;
}

if ($action === 'comment_add' && $method === 'POST') {
    require_login();
    $input = json_decode(file_get_contents('php://input'), true);
    $week_id = (string)($input['week_id'] ?? '');
    $text = trim($input['text'] ?? '');
    if ($week_id === '' || $text==='') { http_response_code(400); echo json_encode(['error'=>'week_id and text required']); exit; }

    $comments = read_json_file($COMMENTS_FILE);
    if (!isset($comments[$week_id])) $comments[$week_id]=[];
    $entry=['id'=>'c_'.time().'_'.rand(1000,9999),'author'=>$_SESSION['user'],'text'=>$text,'created_at'=>date('c')];
    $comments[$week_id][]=$entry;
    if (write_json_file($COMMENTS_FILE,$comments)) { echo json_encode($entry); exit; }
    http_response_code(500); echo json_encode(['error'=>'write failed']); exit;
}

if ($action==='comment_delete' && $method==='POST') {
    require_login();
    $input=json_decode(file_get_contents('php://input'),true);
    $week_id=(string)($input['week_id']??''); $comment_id=($input['comment_id']??null);
    if ($week_id === '' || !$comment_id) { http_response_code(400); echo json_encode(['error'=>'week_id/comment_id required']); exit; }

    $comments=read_json_file($COMMENTS_FILE);
    if(!isset($comments[$week_id])) { http_response_code(404); echo json_encode(['error'=>'not found']); exit; }

    $found=false;
    foreach($comments[$week_id] as $i=>$c){
        if($c['id']===$comment_id){
            if(($_SESSION['role']??'')==='admin' || ($_SESSION['user']??'')===$c['author']){
                array_splice($comments[$week_id],$i,1);
                $found=true; break;
            } else { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }
        }
    }
    if(!$found){ http_response_code(404); echo json_encode(['error'=>'not found']); exit; }
    if(write_json_file($COMMENTS_FILE,$comments)){ echo json_encode(['ok'=>true]); exit; }
    http_response_code(500); echo json_encode(['error'=>'write failed']); exit;
}

if($action==='comment_edit' && $method==='POST'){
    require_login();
    $input=json_decode(file_get_contents('php://input'),true);
    $week_id=(string)($input['week_id']??''); $comment_id=$input['comment_id']??null;
    $text=trim($input['text']??'');
    if($week_id === '' || !$comment_id || $text===''){ http_response_code(400); echo json_encode(['error'=>'bad input']); exit; }

    $comments=read_json_file($COMMENTS_FILE);
    if(!isset($comments[$week_id])){ http_response_code(404); echo json_encode(['error'=>'not found']); exit; }

    $found=false;
    foreach($comments[$week_id] as $i=>$c){
        if($c['id']===$comment_id){
            if(($_SESSION['role']??'')==='admin' || ($_SESSION['user']??'')===$c['author']){
                $comments[$week_id][$i]['text']=$text;
                $comments[$week_id][$i]['edited_at']=date('c');
                $found=true; break;
            } else { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }
        }
    }
    if(!$found){ http_response_code(404); echo json_encode(['error'=>'not found']); exit; }
    if(write_json_file($COMMENTS_FILE,$comments)){ echo json_encode(['ok'=>true]); exit; }
    http_response_code(500); echo json_encode(['error'=>'write failed']); exit;
}

// default invalid
http_response_code(400); echo json_encode(['error'=>'invalid action']); exit;

