<?php
/**
 * Create a notification for a user.
 *
 * @param mysqli $conn   Database connection
 * @param int    $userId Target user ID
 * @param string $title  Notification title
 * @param string $message Notification body
 * @param string $link   Optional URL to navigate to on click
 * @param string $role   Recipient role: 'user' or 'admin' (default 'user')
 * @return int|false The inserted notification ID or false on failure
 */
function createNotification($conn, $userId, $title, $message, $link = null, $role = 'user')
{
    // Ensure user_role column exists (migration)
    $check = $conn->query("SHOW COLUMNS FROM notifications LIKE 'user_role'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE notifications ADD COLUMN user_role ENUM('user','admin') DEFAULT 'user' AFTER user_id");
    }

    $stmt = $conn->prepare(
        "INSERT INTO notifications (user_id, user_role, title, message, link) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("issss", $userId, $role, $title, $message, $link);
    $success = $stmt->execute();
    $id = $success ? $stmt->insert_id : false;
    $stmt->close();
    return $id;
}
