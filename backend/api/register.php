<?php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['username', 'email', 'password', 'full_name', 'phone'];
foreach ($required as $field) {
    if (!isset($data[$field]) || empty(trim($data[$field]))) {
        echo json_encode(['success' => false, 'message' => ucfirst($field) . ' is required']);
        exit();
    }
}

$username = sanitizeInput($data['username']);
$email = sanitizeInput($data['email']);
$password = $data['password'];
$full_name = sanitizeInput($data['full_name']);
$phone = sanitizeInput($data['phone']);
$address = isset($data['address']) ? sanitizeInput($data['address']) : '';

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit();
}

// Validate password strength (minimum 6 characters)
if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
    exit();
}

$conn = getDBConnection();

// Check if username already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Username already exists']);
    $stmt->close();
    $conn->close();
    exit();
}
$stmt->close();

// Check if email already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Email already exists']);
    $stmt->close();
    $conn->close();
    exit();
}
$stmt->close();

// Hash password
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Handle photo upload if provided
$photo_path = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array(strtolower($file_extension), $allowed_extensions)) {
        $photo_name = uniqid() . '_' . time() . '.' . $file_extension;
        $photo_path = $upload_dir . $photo_name;

        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
            $photo_path = null;
        }
    }
}

// Insert new user
$stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name, phone, address, photo_path, role) VALUES (?, ?, ?, ?, ?, ?, ?, 'user')");
$stmt->bind_param("sssssss", $username, $email, $password_hash, $full_name, $phone, $address, $photo_path);

if ($stmt->execute()) {
    $user_id = $conn->insert_id;

    // Create token
    $token = createToken($user_id, $username, 'user');

    echo json_encode([
        'success' => true,
        'message' => 'Registration successful',
        'token' => $token,
        'user' => [
            'id' => $user_id,
            'username' => $username,
            'email' => $email,
            'full_name' => $full_name,
            'role' => 'user'
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>
