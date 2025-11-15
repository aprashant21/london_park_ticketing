<?php
/**
 * Admin Update User - UPDATE Operation
 * Updates an existing user's information
 */

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Validate admin token
$tokenData = validateToken();
if (!$tokenData || $tokenData['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden. Admin access required.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

// Validate user ID
if (!isset($data['id']) || empty($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit();
}

$user_id = intval($data['id']);

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit();
}

$conn = getDBConnection();

// Check if user exists
$stmt = $conn->prepare("SELECT id, username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    $stmt->close();
    $conn->close();
    exit();
}
$stmt->close();

// Build update query dynamically based on provided fields
$updates = [];
$params = [];
$types = '';

if (isset($data['username']) && !empty(trim($data['username']))) {
    $username = sanitizeInput($data['username']);

    // Check if username is taken by another user
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->bind_param("si", $username, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already taken']);
        $stmt->close();
        $conn->close();
        exit();
    }
    $stmt->close();

    $updates[] = "username = ?";
    $params[] = $username;
    $types .= 's';
}

if (isset($data['email']) && !empty(trim($data['email']))) {
    $email = sanitizeInput($data['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        $conn->close();
        exit();
    }

    // Check if email is taken by another user
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already taken']);
        $stmt->close();
        $conn->close();
        exit();
    }
    $stmt->close();

    $updates[] = "email = ?";
    $params[] = $email;
    $types .= 's';
}

if (isset($data['full_name']) && !empty(trim($data['full_name']))) {
    $updates[] = "full_name = ?";
    $params[] = sanitizeInput($data['full_name']);
    $types .= 's';
}

if (isset($data['phone']) && !empty(trim($data['phone']))) {
    $updates[] = "phone = ?";
    $params[] = sanitizeInput($data['phone']);
    $types .= 's';
}

if (isset($data['address'])) {
    $updates[] = "address = ?";
    $params[] = sanitizeInput($data['address']);
    $types .= 's';
}

if (isset($data['role']) && !empty($data['role'])) {
    $role = sanitizeInput($data['role']);

    if (!in_array($role, ['user', 'admin'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid role']);
        $conn->close();
        exit();
    }

    $updates[] = "role = ?";
    $params[] = $role;
    $types .= 's';
}

if (isset($data['password']) && !empty($data['password'])) {
    if (strlen($data['password']) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        $conn->close();
        exit();
    }

    $updates[] = "password_hash = ?";
    $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
    $types .= 's';
}

// Check if there are any fields to update
if (empty($updates)) {
    echo json_encode(['success' => false, 'message' => 'No fields to update']);
    $conn->close();
    exit();
}

// Add user_id to params
$params[] = $user_id;
$types .= 'i';

// Build and execute update query
$sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error]);
    $conn->close();
    exit();
}

$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'User updated successfully',
        'affected_rows' => $stmt->affected_rows
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update user: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
