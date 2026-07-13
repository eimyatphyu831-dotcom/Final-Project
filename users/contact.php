<?php
require_once '../config/db.php';
require_once '../includes/notification_helper.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $event_type = trim($_POST['event_type'] ?? '');
    $message_text = trim($_POST['message'] ?? '');

    if ($name && $email && $message_text) {
        $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, event_type, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $event_type, $message_text);
        if ($stmt->execute()) {
            $success = "Thank you, $name! Your inquiry has been submitted. We'll get back to you soon.";
            // Notify all admins
            $adminResult = $conn->query("SELECT id FROM admins");
            if ($adminResult) {
                while ($admin = $adminResult->fetch_assoc()) {
                    createNotification($conn, $admin['id'], 'New Contact Message', "$name sent a message: " . substr($message_text, 0, 50) . (strlen($message_text) > 50 ? '...' : ''), '../admin/contact_messages.php');
                }
            }
        } else {
            $error = 'Failed to submit your inquiry. Please try again.';
        }
        $stmt->close();
    } else {
        $error = 'Please fill in all required fields.';
    }
}

include '../includes/header.php';
?>

<section id="contact" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 bg-[#f6f3fa]">
    <div class="bg-white rounded-[2.5rem] shadow-xl overflow-hidden flex flex-col md:flex-row border border-slate-100">
        <!-- Dynamic Intake Fields -->
        <div class="p-8 md:p-12 flex-1">
            <h3 class="text-2xl font-serif font-bold text-brand-600 mb-2">Ready to plan details?</h3>
            <p class="text-xs text-slate-400 mb-8">Submit details below and a personal concierge planner will reach out.
            </p>

            <?php if ($success): ?>
                <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl">
                    <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl">
                    <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Full Name</label>
                        <input type="text" name="name" placeholder="John Doe" required
                            class="w-full text-sm bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 focus:outline-none focus:border-brand-200">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Email Address</label>
                        <input type="email" name="email" placeholder="john@example.com" required
                            class="w-full text-sm bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 focus:outline-none focus:border-brand-200">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Event Type</label>
                    <select name="event_type"
                        class="w-full text-sm bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-slate-400 focus:outline-none focus:border-brand-200">
                        <option value="">Select Option...</option>
                        <option value="Wedding Ceremony">Wedding Ceremony</option>
                        <option value="Corporate Seminar/Gala">Corporate Seminar/Gala</option>
                        <option value="Social/Private Party">Social/Private Party</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Special Requirements</label>
                    <textarea name="message" rows="3" placeholder="Tell us more about your ideas..." required
                        class="w-full text-sm bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 focus:outline-none focus:border-brand-200"></textarea>
                </div>
                <button type="submit"
                    class="w-full bg-brand-200 hover:bg-brand-900 text-brand-900 hover:text-white text-sm font-semibold py-3.5 rounded-xl transition duration-200 shadow-md shadow-brand-200/20">Submit
                    Inquiry</button>
            </form>
        </div>

        <!-- Context Block Sidebar -->
        <div
            class="bg-brand-50 p-8 md:p-12 md:w-80 flex flex-col justify-between text-brand-900 border-l border-slate-100">
            <div>
                <h4 class="font-serif font-bold text-xl mb-6 text-brand-600">Contact Information</h4>
                <div class="space-y-4 text-sm text-slate-600">
                    <div class="flex items-start gap-3">
                        <i data-lucide="map-pin" class="w-4 h-4 mt-0.5 shrink-0 text-brand-600"></i>
                        <span>No.67,Rose  Road, Pyawbwe Township,
                        Mandalay, Myanmar</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <i data-lucide="phone" class="w-4 h-4 shrink-0 text-brand-600"></i>
                        <span>+95 9 950 305004</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <i data-lucide="mail" class="w-4 h-4 shrink-0 text-brand-600"></i>
                        <span>eventpro@gmail.com</span>
                    </div>
                </div>
            </div>
            <div class="pt-8 border-t border-slate-200 text-xs text-slate-400">
                Office Hours: Mon - Fri (9AM - 6PM EST)
            </div>
        </div>
    </div>
</section>

<?php
include '../includes/footer.php';
?>