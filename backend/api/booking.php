<?php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Validate token
$tokenData = validateToken();
if (!$tokenData) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['event_id', 'num_adults', 'num_children', 'seat_type'];
foreach ($required as $field) {
    if (!isset($data[$field])) {
        echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
        exit();
    }
}

$user_id = $tokenData['user_id'];
$event_id = intval($data['event_id']);
$num_adults = intval($data['num_adults']);
$num_children = intval($data['num_children']);
$seat_type = $data['seat_type'];
$total_tickets = $num_adults + $num_children;

$conn = getDBConnection();

// Start transaction
$conn->begin_transaction();

try {
    // Get event details with lock
    $stmt = $conn->prepare("SELECT e.*,
            COALESCE((SELECT SUM(b.total_tickets) FROM bookings b WHERE b.event_id = e.id AND b.booking_status = 'confirmed'), 0) as booked_tickets,
            p.adult_price, p.child_price
            FROM events e
            LEFT JOIN prices p ON e.id = p.event_id AND p.seat_type = ?
            WHERE e.id = ? FOR UPDATE");

    $stmt->bind_param("si", $seat_type, $event_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Event not found');
    }

    $event = $result->fetch_assoc();
    $stmt->close();

    // Validate business rules
    if ($event['requires_adult'] && $num_adults < 1) {
        throw new Exception('At least one adult ticket is required for this event');
    }

    if ($total_tickets > $event['max_tickets_per_sale']) {
        throw new Exception('Maximum ' . $event['max_tickets_per_sale'] . ' tickets allowed per booking');
    }

    $available_tickets = $event['total_capacity'] - $event['booked_tickets'];

    if ($total_tickets > $available_tickets) {
        throw new Exception('Only ' . $available_tickets . ' tickets available');
    }

    // Check if user has photo (required for events with children)
    if ($event['requires_adult'] && $num_children > 0) {
        $stmt = $conn->prepare("SELECT photo_path FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (empty($user['photo_path'])) {
            throw new Exception('Adult photo is required for bookings with children. Please update your profile.');
        }
    }

    // Calculate total price
    $total_price = ($num_adults * $event['adult_price']) + ($num_children * $event['child_price']);

    // Generate booking reference
    $booking_reference = 'LCP' . date('Ymd') . rand(1000, 9999);

    // Create booking
    $stmt = $conn->prepare("INSERT INTO bookings (user_id, event_id, booking_reference, num_adults, num_children, total_tickets, seat_type, total_price, booking_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')");
    $stmt->bind_param("iisiiisd", $user_id, $event_id, $booking_reference, $num_adults, $num_children, $total_tickets, $seat_type, $total_price);

    if (!$stmt->execute()) {
        throw new Exception('Booking failed');
    }

    $booking_id = $conn->insert_id;
    $stmt->close();

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Booking successful',
        'booking' => [
            'id' => $booking_id,
            'booking_reference' => $booking_reference,
            'event_name' => $event['event_name'],
            'event_date' => $event['event_date'],
            'event_time' => $event['event_time'],
            'num_adults' => $num_adults,
            'num_children' => $num_children,
            'total_tickets' => $total_tickets,
            'seat_type' => $seat_type,
            'total_price' => $total_price
        ]
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
