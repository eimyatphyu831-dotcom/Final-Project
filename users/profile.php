<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

include "../config/db.php";

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

$stmt = $conn->prepare("SELECT id, name, email, phone, registration_reason FROM users WHERE id = ?");
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
                    $update = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, registration_reason = ?, password = ? WHERE id = ?");
                    $update->bind_param("sssssi", $name, $email, $phone, $registration_reason, $hashed, $user_id);
                    if ($update->execute()) {
                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_email'] = $email;
                        $message = "Profile updated successfully.";
                        $user['name'] = $name;
                        $user['email'] = $email;
                        $user['phone'] = $phone;
                        $user['registration_reason'] = $registration_reason;
                    } else {
                        $error = "Update failed. Please try again.";
                    }
                    $update->close();
                }
            } else {
                $update = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, registration_reason = ? WHERE id = ?");
                $update->bind_param("ssssi", $name, $email, $phone, $registration_reason, $user_id);
                if ($update->execute()) {
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $message = "Profile updated successfully.";
                    $user['name'] = $name;
                    $user['email'] = $email;
                    $user['phone'] = $phone;
                    $user['registration_reason'] = $registration_reason;
                } else {
                    $error = "Update failed. Please try again.";
                }
                $update->close();
            }
        }
        $check->close();
    }
}

$conn->close();

$pageTitle = "My Profile";
include "../includes/header.php";
?>

<div class="min-h-screen bg-slate-50">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-brand-900">My Profile</h1>
            <p class="text-sm text-gray-500 mt-1">Manage your personal information and password</p>
        </div>

        <?php if ($message): ?>
            <div
                class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl text-sm text-green-700 flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-brand-900 mb-1.5">Full Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-brand-600 focus:ring-2 focus:ring-brand-200 outline-none transition text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-brand-900 mb-1.5">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-brand-600 focus:ring-2 focus:ring-brand-200 outline-none transition text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-brand-900 mb-1.5">Phone</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-brand-600 focus:ring-2 focus:ring-brand-200 outline-none transition text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-brand-900 mb-1.5">Why did you register?</label>
                    <textarea name="registration_reason" rows="3"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-brand-600 focus:ring-2 focus:ring-brand-200 outline-none transition text-sm resize-none"><?= htmlspecialchars($user['registration_reason'] ?? '') ?></textarea>
                </div>
            </div>

            <hr class="border-slate-100">

            <div>
                <h3 class="text-sm font-semibold text-brand-900 mb-1">Change Password</h3>
                <p class="text-xs text-gray-400 mb-4">Leave blank to keep current password</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-brand-900 mb-1.5">Current Password</label>
                        <input type="password" name="current_password"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-brand-600 focus:ring-2 focus:ring-brand-200 outline-none transition text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-brand-900 mb-1.5">New Password</label>
                        <input type="password" name="new_password"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-brand-600 focus:ring-2 focus:ring-brand-200 outline-none transition text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-brand-900 mb-1.5">Confirm New Password</label>
                        <input type="password" name="confirm_password"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-brand-600 focus:ring-2 focus:ring-brand-200 outline-none transition text-sm">
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="index.php"
                    class="px-5 py-2.5 rounded-full text-sm text-brand-900 hover:bg-slate-100 transition font-semibold">
                    Cancel
                </a>
                <button type="submit"
                    class="bg-brand-200 hover:bg-brand-900 text-brand-900 hover:text-white px-5 py-2.5 rounded-full text-sm transition duration-200 font-semibold">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php include "../includes/footer.php"; ?>