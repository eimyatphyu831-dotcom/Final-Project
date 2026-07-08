<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

include '../config/db.php';

$admin_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'fetch';

switch ($action) {
    case 'fetch':
        $limit = intval($_GET['limit'] ?? 10);
        $offset = intval($_GET['offset'] ?? 0);

        $stmt = $conn->prepare(
            "SELECT id, title, message, link, is_read, created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bind_param("iii", $admin_id, $limit, $offset);
        $stmt->execute();
        $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $countStmt = $conn->prepare(
            "SELECT COUNT(*) as total, SUM(is_read = 0) as unread
             FROM notifications WHERE user_id = ?"
        );
        $countStmt->bind_param("i", $admin_id);
        $countStmt->execute();
        $counts = $countStmt->get_result()->fetch_assoc();
        $countStmt->close();

        echo json_encode([
            'notifications' => $notifications,
            'total' => intval($counts['total']),
            'unread' => intval($counts['unread'])
        ]);
        break;

    case 'unread_count':
        $stmt = $conn->prepare(
            "SELECT SUM(is_read = 0) as unread FROM notifications WHERE user_id = ?"
        );
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        echo json_encode(['unread' => intval($result['unread'])]);
        break;

    case 'mark_read':
        $notif_id = intval($_POST['id'] ?? 0);
        if ($notif_id > 0) {
            $stmt = $conn->prepare(
                "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?"
            );
            $stmt->bind_param("ii", $notif_id, $admin_id);
            $stmt->execute();
            $stmt->close();
        }
        echo json_encode(['success' => true]);
        break;

    case 'delete_all':
        $stmt = $conn->prepare(
            "DELETE FROM notifications WHERE user_id = ?"
        );
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        break;

    case 'mark_all_read':
        $stmt = $conn->prepare(
            "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0"
        );
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

$conn->close();
