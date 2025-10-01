<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

// Parse JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
  respond(false, 'Invalid JSON payload.');
}

$identifier = isset($data['identifier']) ? trim($data['identifier']) : '';
$password = isset($data['password']) ? $data['password'] : '';

if ($identifier === '' || $password === '') {
  respond(false, 'Identifier and password are required.');
}

try {
  $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  // Ensure sessions table exists for DB fallback
  $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
    token VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    expires_at DATETIME NOT NULL,
    INDEX (user_id),
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // Find user by username or email
  $stmt = $pdo->prepare('SELECT id, username, email, password FROM users WHERE username = ? OR email = ? LIMIT 1');
  $stmt->execute([$identifier, $identifier]);
  $user = $stmt->fetch();
  if (!$user) {
    respond(false, 'Invalid credentials.');
  }

  if (!password_verify($password, $user['password'])) {
    respond(false, 'Invalid credentials.');
  }

  $token = bin2hex(random_bytes(32));
  $sessionKey = 'session:' . $token;
  $sessionData = json_encode([
    'user_id' => (int)$user['id'],
    'username' => $user['username'],
    'email' => $user['email']
  ]);

  // Try Redis first; if Redis class or server is unavailable, fall back to DB
  $usedRedis = false;
  if (class_exists('Redis')) {
    try {
      $redis = new Redis();
      $redis->connect($redisHost, $redisPort, 1.0);
      $redis->setex($sessionKey, $sessionTTL, $sessionData);
      $usedRedis = true;
    } catch (Throwable $e) {
      // fall through to DB fallback
    }
  }

  if (!$usedRedis) {
    $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
      ->modify('+' . $sessionTTL . ' seconds')
      ->format('Y-m-d H:i:s');
    $stmt = $pdo->prepare('INSERT INTO sessions (token, user_id, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([$token, (int)$user['id'], $expiresAt]);
  }

  respond(true, 'Login successful.', ['token' => $token]);
} catch (Throwable $e) {
  respond(false, 'Server error: ' . $e->getMessage());
}


