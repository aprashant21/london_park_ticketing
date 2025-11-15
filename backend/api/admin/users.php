<?php
require_once '../../config/database.php';

// Validate admin token
$tokenData = validateToken();
if (!$tokenData || $tokenData['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden. Admin access required.']);
    exit();
}

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // GET - Read all users
    $sql = "SELECT u.id, u.username, u.email, u.full_name, u.phone, u.address, u.photo_path, u.role, u.created_at,
            COUNT(DISTINCT b.id) as total_bookings,
            COALESCE(SUM(b.total_price), 0) as total_spent
            FROM users u
            LEFT JOIN bookings b ON u.id = b.user_id AND b.booking_status = 'confirmed'
            GROUP BY u.id
            ORDER BY u.created_at DESC";

    $result = $conn->query($sql);
    $users = [];

    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    echo json_encode([
        'success' => true,
        'users' => $users
    ]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // POST - Create new user (admin function)
    $data = json_decode(file_get_contents('php://input'), true);

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
    $role = isset($data['role']) ? sanitizeInput($data['role']) : 'user';

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit();
    }

    // Check if username exists
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

    // Check if email exists
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

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name, phone, address, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $username, $email, $password_hash, $full_name, $phone, $address, $role);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'User created successfully',
            'user_id' => $conn->insert_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create user']);
    }

    $stmt->close();

} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // PUT - Update user
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit();
    }

    $user_id = intval($data['id']);
    $updates = [];
    $params = [];
    $types = '';

    if (isset($data['username'])) {
        $updates[] = "username = ?";
        $params[] = sanitizeInput($data['username']);
        $types .= 's';
    }

    if (isset($data['email'])) {
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            exit();
        }
        $updates[] = "email = ?";
        $params[] = sanitizeInput($data['email']);
        $types .= 's';
    }

    if (isset($data['full_name'])) {
        $updates[] = "full_name = ?";
        $params[] = sanitizeInput($data['full_name']);
        $types .= 's';
    }

    if (isset($data['phone'])) {
        $updates[] = "phone = ?";
        $params[] = sanitizeInput($data['phone']);
        $types .= 's';
    }

    if (isset($data['address'])) {
        $updates[] = "address = ?";
        $params[] = sanitizeInput($data['address']);
        $types .= 's';
    }

    if (isset($data['role'])) {
        $updates[] = "role = ?";
        $params[] = sanitizeInput($data['role']);
        $types .= 's';
    }

    if (isset($data['password']) && !empty($data['password'])) {
        $updates[] = "password_hash = ?";
        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        $types .= 's';
    }

    if (empty($updates)) {
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        exit();
    }

    $params[] = $user_id;
    $types .= 'i';

    $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update user']);
    }

    $stmt->close();

} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // DELETE - Delete user
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit();
    }

    $user_id = intval($data['id']);

    // Prevent deleting own account
    if ($user_id == $tokenData['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
    }

    $stmt->close();
}

$conn->close();
?>
