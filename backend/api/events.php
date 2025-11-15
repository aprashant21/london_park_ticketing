<?php
require_once '../config/database.php';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get all active events with prices
    $sql = "SELECT e.*,
            p1.adult_price as with_table_adult_price,
            p1.child_price as with_table_child_price,
            p2.adult_price as without_table_adult_price,
            p2.child_price as without_table_child_price,
            (e.total_capacity - COALESCE(SUM(b.total_tickets), 0)) as available_tickets
            FROM events e
            LEFT JOIN prices p1 ON e.id = p1.event_id AND p1.seat_type = 'with_table'
            LEFT JOIN prices p2 ON e.id = p2.event_id AND p2.seat_type = 'without_table'
            LEFT JOIN bookings b ON e.id = b.event_id AND b.booking_status = 'confirmed'
            WHERE e.status = 'active' AND e.event_date >= CURDATE()
            GROUP BY e.id
            ORDER BY e.event_date, e.event_time";

    $result = $conn->query($sql);
    $events = [];

    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }

    echo json_encode([
        'success' => true,
        'events' => $events
    ]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get single event details (for booking page)
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['event_id'])) {
        echo json_encode(['success' => false, 'message' => 'Event ID is required']);
        exit();
    }

    $event_id = intval($data['event_id']);

    $stmt = $conn->prepare("SELECT e.*,
            p1.adult_price as with_table_adult_price,
            p1.child_price as with_table_child_price,
            p2.adult_price as without_table_adult_price,
            p2.child_price as without_table_child_price,
            (e.total_capacity - COALESCE(SUM(b.total_tickets), 0)) as available_tickets
            FROM events e
            LEFT JOIN prices p1 ON e.id = p1.event_id AND p1.seat_type = 'with_table'
            LEFT JOIN prices p2 ON e.id = p2.event_id AND p2.seat_type = 'without_table'
            LEFT JOIN bookings b ON e.id = b.event_id AND b.booking_status = 'confirmed'
            WHERE e.id = ?
            GROUP BY e.id");

    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
    } else {
        $event = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'event' => $event
        ]);
    }

    $stmt->close();
}

$conn->close();
?>
