<?php
/**
 * API Monitoring Keamanan
 * - Menerima upload gambar dari ESP32-CAM (binary/form-data)
 * - Menyimpan ke folder uploads, MySQL, dan Firebase
 * - Mengirim notifikasi Telegram saat trigger = 'pir'
 * - Endpoint reset status
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "Database.php";
header('Content-Type: application/json');

// ========== KONFIGURASI FIREBASE ==========
define('FIREBASE_DB_URL', 'https://camera-7dd22-default-rtdb.firebaseio.com/');   // Ganti!
define('FIREBASE_SECRET', '3q392aS4Ef81P59OyUV0xtZvHmV0tjNPBLmJeikO');                      // Ganti!

// ========== KONFIGURASI TELEGRAM ==========
define('TELEGRAM_BOT_TOKEN', '8539832294:AAFhCE40-uTetE0TCpCfKxfz8BCvCnLtMGs');   // Ganti!
define('TELEGRAM_CHAT_ID', '-1379836372');                                // Ganti!
define('WEB_BASE_URL', 'http://localhost/website_terbaru/');              // Ganti!

// ========== FUNGSI KIRIM KE FIREBASE ==========
function sendToFirebase($path, $data, $method = 'POST') {
    $url = FIREBASE_DB_URL . $path . '.json?auth=' . FIREBASE_SECRET;
    $json = json_encode($data);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) {
        error_log("Firebase Error ($httpCode): $response");
        return false;
    }
    return true;
}

function updateFirebaseStatus($status, $lastDetection = null) {
    $data = ['status' => $status];
    if ($lastDetection) $data['last_detection'] = $lastDetection;
    return sendToFirebase('status', $data, 'PUT');
}

function addSnapshotToFirebase($device_id, $image_url, $timestamp, $trigger_type) {
    $data = [
        'deviceId'     => $device_id,
        'imageUrl'     => $image_url,
        'timestamp'    => (int)$timestamp,
        'triggerType'  => $trigger_type,
        'detected'     => true
    ];
    return sendToFirebase('snapshots', $data, 'POST');
}

// ========== FUNGSI TELEGRAM ==========
function sendTelegramMessage($message) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => false
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode != 200) {
        error_log("Telegram Error: $response");
        return false;
    }
    return true;
}

// ========== HANDLER API ==========
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($method) {
    case 'GET':
        if ($action === 'status') {
            handleStatus();
        } elseif ($action === 'latest') {
            handleLatest();
        } elseif ($action === 'list') {
            handleList();
        } else {
            echo json_encode([
                'message' => 'API Monitoring Keamanan - Gunakan endpoint berikut:',
                'endpoints' => [
                    'GET' => [
                        '?action=status' => 'Cek status sistem',
                        '?action=latest' => 'Snapshot terbaru',
                        '?action=list'   => 'Daftar snapshot (tambah &limit=10)'
                    ],
                    'POST' => [
                        '/' => 'Upload gambar (binary JPEG atau multipart)',
                        '?action=reset' => 'Reset status ke aman'
                    ]
                ]
            ]);
        }
        break;
    case 'POST':
        $action = isset($_GET['action']) ? $_GET['action'] : 'upload';
        if ($action === 'reset') {
            handleReset();
        } else {
            handleUpload();
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method tidak diizinkan']);
}

$mysqli->close();

// ================= FUNGSI API =================

function handleStatus() {
    global $mysqli;
    $result = $mysqli->query("SELECT status, last_detection FROM system_status WHERE id = 1");
    if ($row = $result->fetch_assoc()) echo json_encode($row);
    else echo json_encode(['status' => 'aman', 'last_detection' => null]);
}

function handleLatest() {
    global $mysqli;
    $device = isset($_GET['device_id']) ? $mysqli->real_escape_string($_GET['device_id']) : null;
    if ($device) {
        $stmt = $mysqli->prepare("SELECT device_id, image_url, timestamp, trigger_type FROM image_captures WHERE device_id = ? ORDER BY timestamp DESC LIMIT 1");
        $stmt->bind_param("s", $device);
    } else {
        $stmt = $mysqli->prepare("SELECT device_id, image_url, timestamp, trigger_type FROM image_captures ORDER BY timestamp DESC LIMIT 1");
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'device_id'    => $row['device_id'],
            'image_url'    => $row['image_url'],
            'timestamp'    => (int)$row['timestamp'],
            'trigger_type' => $row['trigger_type']
        ]);
    } else echo json_encode(null);
    $stmt->close();
}

function handleList() {
    global $mysqli;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $device = isset($_GET['device_id']) ? $mysqli->real_escape_string($_GET['device_id']) : null;
    $trigger = isset($_GET['trigger']) ? $mysqli->real_escape_string($_GET['trigger']) : null;
    $sql = "SELECT device_id, image_url, timestamp, trigger_type FROM image_captures WHERE 1=1";
    $params = []; $types = "";
    if ($device) { $sql .= " AND device_id = ?"; $params[] = $device; $types .= "s"; }
    if ($trigger) { $sql .= " AND trigger_type = ?"; $params[] = $trigger; $types .= "s"; }
    $sql .= " ORDER BY timestamp DESC LIMIT ?";
    $params[] = $limit; $types .= "i";
    $stmt = $mysqli->prepare($sql);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'device_id'    => $row['device_id'],
            'image_url'    => $row['image_url'],
            'timestamp'    => (int)$row['timestamp'],
            'trigger_type' => $row['trigger_type']
        ];
    }
    echo json_encode($data);
    $stmt->close();
}

function handleUpload() {
    global $mysqli;
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    
    $device_id = isset($_SERVER['HTTP_X_DEVICE_ID']) ? preg_replace('/[^\w\-]/', '', $_SERVER['HTTP_X_DEVICE_ID']) : 'ESP32CAM_01';
    $trigger_type = isset($_SERVER['HTTP_X_TRIGGER']) ? $_SERVER['HTTP_X_TRIGGER'] : 'pir';
    if (!in_array($trigger_type, ['pir','manual','schedule'])) $trigger_type = 'pir';
    $timestamp = round(microtime(true) * 1000);
    
    // Proses file gambar
    $filename = null;
    if (!empty($_FILES['image']['tmp_name'])) {
        $tmp = $_FILES['image']['tmp_name'];
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $filename = sprintf("%s_%d_%s.%s", $device_id, $timestamp, $trigger_type, $ext);
        move_uploaded_file($tmp, $uploadDir . $filename);
    } else {
        $raw = file_get_contents('php://input');
        if ($raw === false || strlen($raw) < 100) {
            http_response_code(400);
            echo json_encode(['error' => 'Tidak ada data gambar']);
            return;
        }
        $filename = sprintf("%s_%d_%s.jpg", $device_id, $timestamp, $trigger_type);
        file_put_contents($uploadDir . $filename, $raw);
    }
    
    $baseUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
    $image_url = rtrim($baseUrl, '/') . '/uploads/' . $filename;
    
    // Simpan ke MySQL
    $stmt = $mysqli->prepare("INSERT INTO image_captures (device_id, image_url, trigger_type, timestamp) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $device_id, $image_url, $trigger_type, $timestamp);
    $stmt->execute();
    $stmt->close();
    $mysqli->query("UPDATE system_status SET status = 'bahaya', last_detection = $timestamp WHERE id = 1");
    
    // Sinkronisasi ke Firebase
    $fb_snapshot = addSnapshotToFirebase($device_id, $image_url, $timestamp, $trigger_type);
    $fb_status = updateFirebaseStatus('bahaya', $timestamp);
    
    // Kirim response JSON
    echo json_encode([
        'success' => true,
        'device_id' => $device_id,
        'image_url' => $image_url,
        'timestamp' => $timestamp,
        'trigger_type' => $trigger_type,
        'firebase_synced' => ($fb_snapshot && $fb_status)
    ]);
    
    // ===== NOTIFIKASI TELEGRAM (hanya untuk trigger PIR) =====
    if ($trigger_type === 'pir') {
        $webLink = rtrim(WEB_BASE_URL, '/') . '/index.html';
        $timeStr = date('d-m-Y H:i:s', floor($timestamp/1000));
        $message = "🚨 <b>PERGERAKAN TERDETEKSI!</b> 🚨\n"
                 . "📷 Device: <code>$device_id</code>\n"
                 . "⏰ Waktu: $timeStr\n"
                 . "🔗 <a href='$webLink'>Klik untuk lihat snapshot terbaru</a>";
        sendTelegramMessage($message);
    }
}

function handleReset() {
    global $mysqli;
    $mysqli->query("UPDATE system_status SET status = 'aman' WHERE id = 1");
    $fb_reset = updateFirebaseStatus('aman', null);
    echo json_encode(['success' => true, 'message' => 'Status direset ke aman', 'firebase_synced' => $fb_reset]);
}
?>