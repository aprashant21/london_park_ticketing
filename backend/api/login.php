<?php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['username']) || !isset($data['password'])) {
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit();
}

$username = sanitizeInput($data['username']);
$password = $data['password'];

$conn = getDBConnection();

// Get user from database
$stmt = $conn->prepare("SELECT id, username, email, password_hash, full_name, phone, address, photo_path, role FROM users WHERE username = ? OR email = ?");
$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    $stmt->close();
    $conn->close();
    exit();
}

$user = $result->fetch_assoc();
// Verify password
if (!password_verify($password, $user['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    $stmt->close();
    $conn->close();
    exit();
}

// Create token
$token = createToken($user['id'], $user['username'], $user['role']);

echo json_encode([
    'success' => true,
    'message' => 'Login successful',
    'token' => $token,
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'full_name' => $user['full_name'],
        'phone' => $user['phone'],
        'address' => $user['address'],
        'photo_path' => $user['photo_path'],
        'role' => $user['role']
    ]
]);

$stmt->close();
$conn->close();
?>
