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

$username = isset($data['username']) ? trim($data['username']) : '';
$email = isset($data['email']) ? trim($data['email']) : '';
$password = isset($data['password']) ? $data['password'] : '';
$age = isset($data['age']) ? $data['age'] : null;
$dob = isset($data['dob']) ? $data['dob'] : null;
$contact = isset($data['contact']) ? trim($data['contact']) : null;

if ($username === '' || $email === '' || $password === '') {
  respond(false, 'Username, email and password are required.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  respond(false, 'Invalid email address.');
}

try {
  $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  // Ensure table exists (idempotent for ease of setup)
  $pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    age INT NULL,
    dob DATE NULL,
    contact VARCHAR(15) NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // Check uniqueness
  $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
  $stmt->execute([$username, $email]);
  if ($stmt->fetch()) {
    respond(false, 'Username or email already exists.');
  }

  $passwordHash = password_hash($password, PASSWORD_BCRYPT);

  $stmt = $pdo->prepare('INSERT INTO users (username, email, password, age, dob, contact) VALUES (?, ?, ?, ?, ?, ?)');
  $stmt->execute([
    $username,
    $email,
    $passwordHash,
    $age !== null ? $age : null,
    $dob !== null ? $dob : null,
    $contact !== null ? $contact : null
  ]);

  respond(true, 'User registered successfully.');
} catch (PDOException $e) {
  respond(false, 'Database error: ' . $e->getMessage());
}


