<?php
/**
 * Create a notification for a user.
 *
 * @param mysqli $conn   Database connection
 * @param int    $userId Target user ID
 * @param string $title  Notification title
 * @param string $message Notification body
 * @param string $link   Optional URL to navigate to on click
 * @return int|false The inserted notification ID or false on failure
 */
function createNotification($conn, $userId, $title, $message, $link = null)
{
    $stmt = $conn->prepare(
        "INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("isss", $userId, $title, $message, $link);
    $success = $stmt->execute();
    $id = $success ? $stmt->insert_id : false;
    $stmt->close();
    return $id;
}
