<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// Database config - adjust for your environment
$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'user_system';

// Session config - Redis preferred, DB fallback
$redisHost = '127.0.0.1';
$redisPort = 6379;
$sessionTTL = 3600; // 1 hour

function respond($success, $message = '', $extra = []) {
  echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
  exit;
}

function getRedisClient($redisHost, $redisPort) {
  if (!class_exists('Redis')) return null;
  try {
    $redis = new Redis();
    $redis->connect($redisHost, $redisPort, 1.0);
    return $redis;
  } catch (Throwable $e) {
    return null;
  }
}

function getPdo($dbHost, $dbName, $dbUser, $dbPass) {
  return new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
}

function validateToken($token, $redis, $pdo) {
  if (!$token) return [false, null];
  // Try Redis first
  if ($redis) {
    $sessionKey = 'session:' . $token;
    $payload = $redis->get($sessionKey);
    if ($payload) {
      global $sessionTTL;
      $redis->expire($sessionKey, $sessionTTL);
      $data = json_decode($payload, true);
      return [true, $data];
    }
  }
  // Fallback to DB
  $stmt = $pdo->prepare('SELECT user_id, expires_at FROM sessions WHERE token = ? LIMIT 1');
  $stmt->execute([$token]);
  $row = $stmt->fetch();
  if (!$row) return [false, null];
  $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
  if ($now > new DateTimeImmutable($row['expires_at'], new DateTimeZone('UTC'))) {
    // expired; cleanup
    $pdo->prepare('DELETE FROM sessions WHERE token = ?')->execute([$token]);
    return [false, null];
  }
  // refresh TTL by extending expiration
  global $sessionTTL;
  $newExpiresAt = $now->modify('+' . $sessionTTL . ' seconds')->format('Y-m-d H:i:s');
  $pdo->prepare('UPDATE sessions SET expires_at = ? WHERE token = ?')->execute([$newExpiresAt, $token]);
  return [true, ['user_id' => (int)$row['user_id']]];
}

try {
  $redis = getRedisClient($redisHost, $redisPort);
  $pdo = getPdo($dbHost, $dbName, $dbUser, $dbPass);

  // Ensure sessions table exists for DB fallback
  $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
    token VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    expires_at DATETIME NOT NULL,
    INDEX (user_id),
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = isset($_GET['token']) ? trim($_GET['token']) : '';
    list($ok, $session) = validateToken($token, $redis, $pdo);
    if (!$ok) respond(false, 'Invalid or expired token.', ['code' => 'INVALID_TOKEN']);

    $stmt = $pdo->prepare('SELECT username, email, age, dob, contact FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$session['user_id']]);
    $user = $stmt->fetch();
    if (!$user) respond(false, 'User not found.');

    respond(true, 'Profile fetched.', ['user' => $user]);
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) respond(false, 'Invalid JSON payload.');

    $token = isset($data['token']) ? trim($data['token']) : '';
    list($ok, $session) = validateToken($token, $redis, $pdo);
    if (!$ok) respond(false, 'Invalid or expired token.', ['code' => 'INVALID_TOKEN']);

    $age = array_key_exists('age', $data) ? $data['age'] : null;
    $dob = array_key_exists('dob', $data) ? $data['dob'] : null;
    $contact = array_key_exists('contact', $data) ? trim((string)$data['contact']) : null;

    $stmt = $pdo->prepare('UPDATE users SET age = ?, dob = ?, contact = ? WHERE id = ?');
    $stmt->execute([
      $age !== null ? $age : null,
      $dob !== null ? $dob : null,
      $contact !== null ? $contact : null,
      $session['user_id']
    ]);

    respond(true, 'Profile updated.');
  }

  if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $token = isset($_REQUEST['token']) ? trim($_REQUEST['token']) : '';
    if ($token) {
      if ($redis) {
        $redis->del('session:' . $token);
      }
      $pdo->prepare('DELETE FROM sessions WHERE token = ?')->execute([$token]);
    }
    respond(true, 'Logged out.');
  }

  respond(false, 'Unsupported method.');
} catch (Throwable $e) {
  respond(false, 'Server error: ' . $e->getMessage());
}


