<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

include "../config/db.php";
require_once '../includes/notification_helper.php';

// Ensure reviews table exists
$conn->query("CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    rating TINYINT NOT NULL,
    review_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE ON UPDATE CASCADE
)");

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $booking_id = (int) $_POST['booking_id'];
    $event_id = (int) $_POST['event_id'];
    $rating = (int) $_POST['rating'];
    $review_text = trim($_POST['review_text'] ?? '');

    // Verify booking belongs to user and is confirmed
    $check = $conn->prepare("SELECT id FROM bookings WHERE id = ? AND user_id = ? AND status = 'Confirmed'");
    $check->bind_param("ii", $booking_id, $user_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows === 0) {
        $error = "Invalid booking or booking is not confirmed.";
    } elseif ($rating < 1 || $rating > 5) {
        $error = "Please select a rating between 1 and 5.";
    } elseif (empty($review_text)) {
        $error = "Please write a review.";
    } else {
        // Check if already reviewed
        $dup = $conn->prepare("SELECT id FROM reviews WHERE booking_id = ? AND user_id = ?");
        $dup->bind_param("ii", $booking_id, $user_id);
        $dup->execute();
        $dup->store_result();
        if ($dup->num_rows > 0) {
            $error = "You have already reviewed this booking.";
        } else {
            $stmt = $conn->prepare("INSERT INTO reviews (booking_id, user_id, event_id, rating, review_text) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiis", $booking_id, $user_id, $event_id, $rating, $review_text);
            if ($stmt->execute()) {
                $message = "Your review has been submitted. Thank you!";
                // Notify admins
                $adminResult = $conn->query("SELECT id FROM admins");
                if ($adminResult) {
                    $eventName = '';
                    $en = $conn->query("SELECT event_name FROM events WHERE id = $event_id");
                    if ($en && $row = $en->fetch_assoc()) $eventName = $row['event_name'];
                    while ($admin = $adminResult->fetch_assoc()) {
                        createNotification($conn, $admin['id'], 'New Review', "{$_SESSION['user_name']} reviewed {$eventName} with {$rating} stars.", '../admin/reviews.php', 'admin');
                    }
                }
            } else {
                $error = "Failed to submit review. Please try again.";
            }
            $stmt->close();
        }
        $dup->close();
    }
    $check->close();
}

// Fetch bookings available for review (confirmed & not yet reviewed)
$available = [];
$stmt = $conn->prepare("SELECT b.id, b.event_id, e.event_name, b.event_date, v.name AS venue_name
    FROM bookings b
    JOIN events e ON b.event_id = e.id
    JOIN venues v ON b.venue_id = v.id
    WHERE b.user_id = ? AND b.status = 'Confirmed'
    AND NOT EXISTS (SELECT 1 FROM reviews r WHERE r.booking_id = b.id AND r.user_id = ?)
    ORDER BY b.event_date DESC");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$available = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch user's existing reviews
$myReviews = [];
$stmt = $conn->prepare("SELECT r.id, r.rating, r.review_text, r.created_at, e.event_name
    FROM reviews r
    JOIN events e ON r.event_id = e.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$myReviews = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = "My Reviews";
include "../includes/header.php";
// $conn intentionally left open for header/footer use; closed by PHP at end of request
?>

<div class="min-h-screen bg-brand-50/40 py-6">
    <div class="max-w-2xl mx-auto px-4">

        <div class="text-center mb-6">
            <h1 class="text-xl font-bold text-brand-900">My Reviews</h1>
            <p class="text-xs text-gray-400">Share your experience with upcoming events</p>
        </div>

        <?php if ($message): ?>
            <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-xs text-green-700 flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700 flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Review Form -->
        <?php if (count($available) > 0): ?>
            <div class="bg-white rounded-xl shadow-sm border border-brand-200/50 overflow-hidden mb-6">
                <div class="bg-brand-200 px-4 py-3">
                    <h2 class="text-brand-900 font-semibold text-sm">Write a Review</h2>
                </div>
                <form method="POST" class="p-4 space-y-3">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Select Booking</label>
                        <select name="booking_id" id="bookingSelect" onchange="updateEventId(this)" required
                            class="w-full px-3 py-2 rounded-lg border border-brand-200 bg-brand-50 focus:bg-white focus:border-brand-600 focus:ring-1 focus:ring-brand-200 outline-none transition text-sm">
                            <option value="">— Choose a booking —</option>
                            <?php foreach ($available as $b): ?>
                                <option value="<?= $b['id'] ?>" data-event="<?= $b['event_id'] ?>">
                                    <?= htmlspecialchars($b['event_name']) ?> —
                                    <?= htmlspecialchars($b['venue_name']) ?> —
                                    <?= date('M j, Y', strtotime($b['event_date'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="event_id" id="eventIdInput" value="">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Rating</label>
                        <div class="flex gap-1" id="starRating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <button type="button" data-value="<?= $i ?>"
                                    class="star-btn w-9 h-9 rounded-full flex items-center justify-center text-gray-300 hover:text-yellow-400 transition text-lg"
                                    onclick="setRating(<?= $i ?>)">
                                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                </button>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating" id="ratingInput" value="">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Your Review</label>
                        <textarea name="review_text" rows="3" required placeholder="Share your experience..."
                            class="w-full px-3 py-2 rounded-lg border border-brand-200 bg-brand-50 focus:bg-white focus:border-brand-600 focus:ring-1 focus:ring-brand-200 outline-none transition text-sm resize-none"></textarea>
                    </div>
                    <button type="submit" name="submit_review"
                        class="w-full py-2.5 bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-lg text-sm transition shadow-sm">
                        Submit Review
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Existing Reviews -->
        <div class="bg-white rounded-xl shadow-sm border border-brand-200/50 overflow-hidden">
            <div class="bg-brand-200 px-4 py-3">
                <h2 class="text-brand-900 font-semibold text-sm">Your Reviews</h2>
            </div>
            <div class="p-4">
                <?php if (count($myReviews) === 0): ?>
                    <p class="text-sm text-gray-400 text-center py-6">No reviews yet. Review your confirmed bookings above!</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($myReviews as $rev): ?>
                            <div class="p-3 bg-brand-50 rounded-lg border border-brand-100">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm font-semibold text-brand-900"><?= htmlspecialchars($rev['event_name']) ?></span>
                                    <span class="text-xs text-gray-400"><?= date('M j, Y', strtotime($rev['created_at'])) ?></span>
                                </div>
                                <div class="flex gap-0.5 mb-1.5">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <svg class="w-4 h-4 <?= $i <= $rev['rating'] ? 'text-yellow-400' : 'text-gray-300' ?>" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    <?php endfor; ?>
                                </div>
                                <p class="text-xs text-gray-600"><?= htmlspecialchars($rev['review_text']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
function setRating(val) {
    document.getElementById('ratingInput').value = val;
    document.querySelectorAll('.star-btn').forEach((btn, i) => {
        const star = btn.querySelector('svg');
        if (i < val) {
            star.classList.remove('text-gray-300');
            star.classList.add('text-yellow-400');
        } else {
            star.classList.remove('text-yellow-400');
            star.classList.add('text-gray-300');
        }
    });
}

function updateEventId(select) {
    const opt = select.options[select.selectedIndex];
    document.getElementById('eventIdInput').value = opt ? opt.dataset.event || '' : '';
}
</script>

<?php
$conn->close();
include "../includes/footer.php"; ?>
