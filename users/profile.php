<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

include "../config/db.php";

$user_id = $_SESSION['user_id'];
$message = $_SESSION['profile_message'] ?? '';
unset($_SESSION['profile_message']);
$error = '';

// Ensure upload directory exists
$profileUploadDir = '../uploads/profiles/';
if (!is_dir($profileUploadDir))
    mkdir($profileUploadDir, 0755, true);

$stmt = $conn->prepare("SELECT id, name, email, phone, image FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = strtolower(trim($_POST['email']));
    $phone = trim($_POST['phone']);
    $registration_reason = trim($_POST['registration_reason'] ?? '');
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($name) || empty($email)) {
        $error = "Name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->bind_param("si", $email, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "Email already in use by another account.";
        } else {
            // Handle profile image upload
            $imageName = $user['image'] ?? null;
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/profiles/';
                if (!is_dir($uploadDir))
                    mkdir($uploadDir, 0755, true);
                $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                if (in_array($ext, $allowed)) {
                    $imageName = 'profile_' . $user_id . '_' . time() . '.' . $ext;
                    move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadDir . $imageName);
                    // Delete old image if exists
                    if ($user['image'] && file_exists($uploadDir . $user['image'])) {
                        unlink($uploadDir . $user['image']);
                    }
                } else {
                    $error = "Invalid image format. Allowed: jpg, jpeg, png, webp, gif.";
                }
            }

            if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
                $pw_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $pw_stmt->bind_param("i", $user_id);
                $pw_stmt->execute();
                $pw_result = $pw_stmt->get_result()->fetch_assoc();
                $pw_stmt->close();

                if (!password_verify($current_password, $pw_result['password'])) {
                    $error = "Current password is incorrect.";
                } elseif ($new_password !== $confirm_password) {
                    $error = "New passwords do not match.";
                } elseif (strlen($new_password) < 6) {
                    $error = "New password must be at least 6 characters.";
                } else {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $update = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, image = ?, password = ? WHERE id = ?");
                    $update->bind_param("sssssi", $name, $email, $phone, $imageName, $hashed, $user_id);
                    if ($update->execute()) {
                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_email'] = $email;
                        $_SESSION['profile_message'] = "Profile updated successfully.";
                        $update->close();
                        $check->close();
                        header("Location: profile.php");
                        exit();
                    } else {
                        $error = "Update failed. Please try again.";
                    }
                    $update->close();
                }
            } else {
                $update = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, image = ? WHERE id = ?");
                $update->bind_param("ssssi", $name, $email, $phone, $imageName, $user_id);
                if ($update->execute()) {
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['profile_message'] = "Profile updated successfully.";
                    $update->close();
                    $check->close();
                    header("Location: profile.php");
                    exit();
                } else {
                    $error = "Update failed. Please try again.";
                }
                $update->close();
            }
        }
        $check->close();
    }
}

// Add image column to users table if not exists
$imgCol = $conn->query("SHOW COLUMNS FROM users LIKE 'image'");
if ($imgCol && $imgCol->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN image VARCHAR(255) DEFAULT NULL AFTER phone");
}

// Count bookings and reviews
$bookingCount = 0;
$bc = $conn->query("SELECT COUNT(*) c FROM bookings WHERE user_id = $user_id");
if ($bc)
    $bookingCount = $bc->fetch_assoc()['c'];

$reviewCount = 0;
$rc = $conn->query("SELECT COUNT(*) c FROM reviews WHERE user_id = $user_id");
if ($rc)
    $reviewCount = $rc->fetch_assoc()['c'];

$pageTitle = "My Profile";
include "../includes/header.php";

$conn->close();

$initials = '';
if (!empty($user['name'])) {
    $parts = explode(' ', $user['name']);
    foreach ($parts as $p)
        $initials .= strtoupper($p[0] ?? '');
    $initials = substr($initials, 0, 2);
}
?>

<div class="min-h-screen bg-brand-50/40 py-8">
    <div class="max-w-lg mx-auto px-4">

        <?php if ($message): ?>
            <div
                class="p-2 bg-green-50 border border-green-200 rounded-lg text-xs text-green-700 flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700 flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="mb-3 flex flex-col items-center justify-center">
            <h1 class="text-2xl font-bold text-brand-600">My Profile</h1>
            <p class="text-sm text-brand-900/70">Manage your personal information</p>
        </div>

        <!-- View Mode -->
        <div id="profileView">
            <div class="bg-white rounded-xl shadow-sm border border-brand-200/50 overflow-hidden">
                <div class="bg-brand-200 px-4 py-4 text-center">
                    <?php if (!empty($user['image'])): ?>
                        <img src="../uploads/profiles/<?= htmlspecialchars($user['image']) ?>?t=<?= time() ?>"
                            class="w-14 h-14 mx-auto rounded-full object-cover border-2 border-white/80 shadow-sm mb-1">
                    <?php else: ?>
                        <div
                            class="w-14 h-14 mx-auto rounded-full bg-white/80 flex items-center justify-center text-brand-900 text-lg font-bold shadow-sm mb-1.5">
                            <?= htmlspecialchars($initials ?: '?') ?>
                        </div>
                    <?php endif; ?>
                    
                </div>
                <div class="p-4 space-y-3">
                    <div class="flex items-center gap-3 p-3 bg-brand-50 rounded-lg">
                        <div class="w-8 h-8 rounded-full bg-brand-200 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-[10px] text-gray-400 uppercase">Full Name</div>
                            <div class="text-sm font-semibold text-brand-900 truncate">
                                <?= htmlspecialchars($user['name'] ?? '—') ?>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 bg-brand-50 rounded-lg">
                        <div class="w-8 h-8 rounded-full bg-brand-200 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-[10px] text-gray-400 uppercase">Email</div>
                            <div class="text-sm font-semibold text-brand-900 truncate">
                                <?= htmlspecialchars($user['email'] ?? '—') ?>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 bg-brand-50 rounded-lg">
                        <div class="w-8 h-8 rounded-full bg-brand-200 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-[10px] text-gray-400 uppercase">Phone</div>
                            <div class="text-sm font-semibold text-brand-900 truncate">
                                <?= htmlspecialchars($user['phone'] ?? '—') ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 px-4 pb-2">
                    <div class="bg-brand-50 rounded-lg p-3 text-center border border-brand-100">
                        <div class="text-lg font-bold text-brand-600"><?= $bookingCount ?></div>
                        <div class="text-[10px] text-gray-500 uppercase">Bookings</div>
                    </div>
                    <div class="bg-brand-50 rounded-lg p-3 text-center border border-brand-100">
                        <div class="text-lg font-bold text-brand-600"><?= $reviewCount ?></div>
                        <div class="text-[10px] text-gray-500 uppercase">Reviews</div>
                    </div>
                </div>

                <!-- VIEW MODE BUTTONS -->
                <div class="px-4 pb-6 flex gap-2">
                    <!-- Cancel Button (Leaves the page) -->
                    <button type="button" onclick="window.history.back()"
                        class="flex-1 py-2.5 bg-gray-100 hover:bg-gray-200 text-brand-900 font-semibold rounded-lg text-sm transition">
                        Cancel
                    </button>

                    <!-- Edit Profile Button -->
                    <button type="button" onclick="enableEdit()"
                        class="flex-1 py-2.5 bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-lg text-sm transition shadow-sm">
                        <svg class="w-3.5 h-3.5 inline mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edit Profile
                    </button>
                </div>
            </div>
        </div>

        <!-- Edit Mode -->
        <form method="POST" id="profileEditForm" class="hidden" enctype="multipart/form-data">
            <div class="bg-white rounded-xl shadow-sm border border-brand-200/50 overflow-hidden">
                <div class="bg-brand-200 px-4 py-4 text-center">
                    <div class="relative inline-block mb-1.5">
                        <?php if (!empty($user['image'])): ?>
                            <img id="editProfilePreview"
                                src="../uploads/profiles/<?= htmlspecialchars($user['image']) ?>?t=<?= time() ?>"
                                class="w-14 h-14 rounded-full object-cover border-2 border-white/80 shadow-sm">
                        <?php else: ?>
                            <div id="editProfilePreview"
                                class="w-14 h-14 rounded-full bg-white/80 flex items-center justify-center text-brand-900 text-lg font-bold shadow-sm">
                                <?= htmlspecialchars($initials ?: '?') ?>
                            </div>
                        <?php endif; ?>
                        <label for="profileImageInput"
                            class="absolute -bottom-1 -right-1 w-6 h-6 bg-brand-600 hover:bg-brand-700 text-white rounded-full flex items-center justify-center cursor-pointer shadow-sm border-2 border-white transition">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </label>
                        <input type="file" name="profile_image" id="profileImageInput" accept="image/*" class="hidden"
                            onchange="previewProfileImage(this)">
                    </div>
                    <h1 class="text-base font-bold text-brand-900">Edit Profile</h1>
                    <p class="text-[10px] text-brand-700/70">Update your information below</p>
                </div>
                <div class="p-4 space-y-3">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Full Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required
                            class="w-full px-3 py-2 rounded-lg border border-brand-200 bg-brand-50 focus:bg-white focus:border-brand-600 focus:ring-1 focus:ring-brand-200 outline-none transition text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required
                            class="w-full px-3 py-2 rounded-lg border border-brand-200 bg-brand-50 focus:bg-white focus:border-brand-600 focus:ring-1 focus:ring-brand-200 outline-none transition text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Phone</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                            class="w-full px-3 py-2 rounded-lg border border-brand-200 bg-brand-50 focus:bg-white focus:border-brand-600 focus:ring-1 focus:ring-brand-200 outline-none transition text-sm">
                    </div>

                    <hr class="border-brand-100">

                    <div>
                        <div class="flex items-center gap-1.5 mb-2">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <span class="text-xs font-semibold text-brand-900">Change Password</span>
                            <span class="text-[9px] text-gray-400">(optional)</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <input type="password" name="current_password" placeholder="Current"
                                class="w-full px-3 py-2 rounded-lg border border-brand-200 bg-brand-50 focus:bg-white focus:border-brand-600 focus:ring-1 focus:ring-brand-200 outline-none transition text-xs">
                            <input type="password" name="new_password" placeholder="New"
                                class="w-full px-3 py-2 rounded-lg border border-brand-200 bg-brand-50 focus:bg-white focus:border-brand-600 focus:ring-1 focus:ring-brand-200 outline-none transition text-xs">
                            <input type="password" name="confirm_password" placeholder="Confirm"
                                class="w-full px-3 py-2 rounded-lg border border-brand-200 bg-brand-50 focus:bg-white focus:border-brand-600 focus:ring-1 focus:ring-brand-200 outline-none transition text-xs">
                        </div>
                    </div>
                </div>
                <!-- EDIT MODE BUTTONS -->
                <div class="px-4 pb-4 flex gap-2">
                    <button type="button" onclick="cancelEdit()"
                        class="flex-1 py-2.5 bg-gray-100 hover:bg-gray-200 text-brand-900 font-semibold rounded-lg text-xs transition">
                        Cancel
                    </button>
                    <button type="submit"
                        class="flex-1 py-2.5 bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-lg text-xs transition shadow-sm">
                        <svg class="w-3.5 h-3.5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    function enableEdit() {
        document.getElementById('profileView').classList.add('hidden');
        document.getElementById('profileEditForm').classList.remove('hidden');
    }
    function cancelEdit() {
        document.getElementById('profileView').classList.remove('hidden');
        document.getElementById('profileEditForm').classList.add('hidden');
    }
    function previewProfileImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                const preview = document.getElementById('editProfilePreview');
                preview.src = e.target.result;
                preview.classList.remove('rounded-full', 'bg-white/80', 'flex', 'items-center', 'justify-center', 'text-brand-900', 'text-lg', 'font-bold');
                preview.classList.add('w-14', 'h-14', 'rounded-full', 'object-cover', 'border-2', 'border-white/80', 'shadow-sm');
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>

<?php include "../includes/footer.php"; ?>