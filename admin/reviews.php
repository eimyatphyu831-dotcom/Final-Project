<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db.php';

// Fetch all reviews
$reviews = [];
$query = "SELECT r.id, r.rating, r.review_text, r.created_at,
                 u.name AS user_name, u.email AS user_email,
                 e.event_name, b.event_date
          FROM reviews r
          JOIN users u ON r.user_id = u.id
          JOIN events e ON r.event_id = e.id
          JOIN bookings b ON r.booking_id = b.id
          ORDER BY r.created_at DESC";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $reviews = $result->fetch_all(MYSQLI_ASSOC);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Reviews</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,500;1,600;1,700;1,800;1,900&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Playfair Display', serif; }
        .bg-sidebar { background-color: #ffffff; }
        .bg-sidebar-active { background-color: #C3B1E1; color: #ffffff; }
        .text-purple-brand { color: #9966cc; }
        .bg-purple-brand { background-color: #C3B1E1; }
    </style>
</head>

<body class="bg-gray-100 min-h-screen overflow-hidden">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>
        <div class="flex-1 flex flex-col lg:ml-64">
            <?php include 'admin_header.php'; ?>
            <main class="flex-1 p-6 overflow-y-auto">
                <div class="flex justify-between items-center mb-6">
                    <!-- <div>
                        <h1 class="text-2xl font-bold text-purple-700">Reviews</h1>
                        <p class="text-sm text-gray-500">All customer reviews</p>
                    </div> -->
                    <span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-xs font-semibold"><?= count($reviews) ?> reviews</span>
                </div>

                <?php if (count($reviews) === 0): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center">
                        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" /></svg>
                        <h3 class="text-lg font-semibold text-gray-400">No reviews yet</h3>
                        <p class="text-sm text-gray-400 mt-1">Reviews from customers will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($reviews as $r): ?>
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:shadow-md transition">
                                <div class="flex items-center justify-between mb-2">
                                    <div>
                                        <div class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($r['user_name']) ?></div>
                                        <div class="text-[10px] text-gray-400"><?= htmlspecialchars($r['user_email']) ?></div>
                                    </div>
                                    <span class="text-[10px] text-gray-400"><?= date('M j, Y', strtotime($r['created_at'])) ?></span>
                                </div>
                                <div class="flex gap-0.5 mb-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <svg class="w-4 h-4 <?= $i <= $r['rating'] ? 'text-yellow-400' : 'text-gray-300' ?>" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    <?php endfor; ?>
                                </div>
                                <p class="text-xs text-gray-600 mb-2"><?= htmlspecialchars($r['review_text']) ?></p>
                                <div class="text-[10px] text-gray-400 border-t border-gray-100 pt-2">
                                    Event: <span class="font-medium text-gray-600"><?= htmlspecialchars($r['event_name']) ?></span>
                                    &middot; <?= date('M j, Y', strtotime($r['event_date'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
