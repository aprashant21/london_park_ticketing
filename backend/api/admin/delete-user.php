
<?php
/**
 * Admin Delete User - DELETE Operation
 * Deletes a user from the system
 */

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
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

// Prevent deleting own account
if ($user_id == $tokenData['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
    exit();
}

$conn = getDBConnection();

// Check if user exists and get details
$stmt = $conn->prepare("SELECT id, username, role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    $stmt->close();
    $conn->close();
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Optional: Prevent deleting other admin accounts (uncomment if needed)
/*
if ($user['role'] === 'admin') {
    echo json_encode(['success' => false, 'message' => 'Cannot delete admin accounts']);
    $conn->close();
    exit();
}
*/

// Start transaction
$conn->begin_transaction();

try {
    // Note: Bookings will be automatically deleted due to ON DELETE CASCADE
    // But we'll log them first for reference
    $stmt = $conn->prepare("SELECT COUNT(*) as booking_count FROM bookings WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking_info = $result->fetch_assoc();
    $stmt->close();

    // Delete user (bookings will cascade)
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);

    if (!$stmt->execute()) {
        throw new Exception('Failed to delete user: ' . $stmt->error);
    }

    $stmt->close();

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'User deleted successfully',
        'deleted_bookings' => $booking_info['booking_count']
    ]);

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
