<?php
session_start();
header('Content-Type: application/json');


if (!isset($_SESSION['user_id'])) {

    http_response_code(401);

    echo json_encode([
        'error' => 'Unauthorized'
    ]);

    exit();
}


include '../config/db.php';


$user_id = $_SESSION['user_id'];

$action = $_GET['action'] ?? $_POST['action'] ?? 'fetch';



switch ($action) {


    // =========================
    // Fetch Notifications
    // =========================
    case 'fetch':

        $limit = intval($_GET['limit'] ?? 10);
        $offset = intval($_GET['offset'] ?? 0);



        $stmt = $conn->prepare("
            SELECT id,
                   title,
                   message,
                   link,
                   is_read,
                   created_at
            FROM notifications
            WHERE user_id = ? AND user_role = 'user'
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");


        $stmt->bind_param(
            "iii",
            $user_id,
            $limit,
            $offset
        );


        $stmt->execute();


        $notifications = $stmt
            ->get_result()
            ->fetch_all(MYSQLI_ASSOC);


        $stmt->close();



        // Count notifications

        $countStmt = $conn->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) AS unread
            FROM notifications
            WHERE user_id = ? AND user_role = 'user'
        ");


        $countStmt->bind_param(
            "i",
            $user_id
        );


        $countStmt->execute();


        $counts = $countStmt
            ->get_result()
            ->fetch_assoc();


        $countStmt->close();



        echo json_encode([
            'notifications' => $notifications,
            'total' => (int) $counts['total'],
            'unread' => (int) ($counts['unread'] ?? 0)
        ]);

        break;



    // =========================
    // Get Unread Count
    // =========================
    case 'unread_count':


        $stmt = $conn->prepare("
            SELECT COUNT(*) AS unread
            FROM notifications
            WHERE user_id = ?
            AND user_role = 'user'
            AND is_read = 0
        ");


        $stmt->bind_param(
            "i",
            $user_id
        );


        $stmt->execute();


        $result = $stmt
            ->get_result()
            ->fetch_assoc();


        $stmt->close();



        echo json_encode([
            'unread' => (int) $result['unread']
        ]);


        break;



    // =========================
    // Mark One Read
    // =========================
    case 'mark_read':

        $notif_id = intval($_POST['id'] ?? 0);


        if ($notif_id > 0) {


            $stmt = $conn->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE id = ?
                AND user_id = ?
                AND user_role = 'user'
            ");


            $stmt->bind_param(
                "ii",
                $notif_id,
                $user_id
            );


            $stmt->execute();


            $stmt->close();

        }


        echo json_encode([
            'success' => true
        ]);


        break;



    // =========================
    // Mark All Read
    // =========================
    case 'mark_all_read':


        $stmt = $conn->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE user_id = ?
            AND user_role = 'user'
            AND is_read = 0
        ");



        $stmt->bind_param(
            "i",
            $user_id
        );


        $stmt->execute();


        $stmt->close();



        echo json_encode([
            'success' => true
        ]);

        break;



    // =========================
    // Delete All
    // =========================
    case 'delete_all':


        $stmt = $conn->prepare("
            DELETE FROM notifications
            WHERE user_id = ?
            AND user_role = 'user'
        ");



        $stmt->bind_param(
            "i",
            $user_id
        );



        $stmt->execute();


        $stmt->close();



        echo json_encode([
            'success' => true
        ]);


        break;



    // =========================
    // Delete One
    // =========================
    case 'delete':


        $notif_id = intval($_POST['id'] ?? 0);



        if ($notif_id > 0) {


            $stmt = $conn->prepare("
                DELETE FROM notifications
                WHERE id = ?
                AND user_id = ?
                AND user_role = 'user'
            ");



            $stmt->bind_param(
                "ii",
                $notif_id,
                $user_id
            );



            $stmt->execute();


            $stmt->close();

        }



        echo json_encode([
            'success' => true
        ]);

        break;



    default:

        http_response_code(400);


        echo json_encode([
            'error' => 'Invalid action'
        ]);

}


$conn->close();

?>