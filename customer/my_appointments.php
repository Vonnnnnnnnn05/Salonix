<?php
include "../config/session.php";
include "../config/conn.php";

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'customer') {
    header("Location: ../index.php");
    exit;
}

$customerId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$message = "";
$error = "";

mysqli_query(
    $conn,
    "CREATE TABLE IF NOT EXISTS appointment_feedback (
        feedback_id int(11) NOT NULL AUTO_INCREMENT,
        appointment_id int(11) NOT NULL,
        customer_id int(11) NOT NULL,
        rating tinyint(1) NOT NULL,
        comments text DEFAULT NULL,
        created_at timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (feedback_id),
        UNIQUE KEY unique_appointment_feedback (appointment_id),
        KEY customer_id (customer_id),
        CONSTRAINT appointment_feedback_ibfk_1 FOREIGN KEY (appointment_id) REFERENCES appointments (appointment_id),
        CONSTRAINT appointment_feedback_ibfk_2 FOREIGN KEY (customer_id) REFERENCES users (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $error = "Invalid request token.";
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'cancel') {
            $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
            if ($appointmentId <= 0 || $customerId <= 0) {
                $error = "Invalid appointment selected.";
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ? AND customer_id = ? AND status IN ('pending', 'confirmed', 'rescheduled')");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ii", $appointmentId, $customerId);
                    mysqli_stmt_execute($stmt);
                    if (mysqli_stmt_affected_rows($stmt) > 0) {
                        $message = "Appointment cancelled successfully.";
                    } else {
                        $error = "Appointment cannot be cancelled in its current status.";
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error = "Failed to prepare cancellation.";
                }
            }
        }

        if ($action === 'feedback') {
            $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
            $rating = (int) ($_POST['rating'] ?? 0);
            $comments = trim($_POST['comments'] ?? '');

            if ($appointmentId <= 0 || $customerId <= 0 || $rating < 1 || $rating > 5) {
                $error = "Please choose a rating from 1 to 5.";
            } else {
                $checkStmt = mysqli_prepare($conn, "SELECT appointment_id FROM appointments WHERE appointment_id = ? AND customer_id = ? AND status = 'completed' LIMIT 1");
                if ($checkStmt) {
                    mysqli_stmt_bind_param($checkStmt, "ii", $appointmentId, $customerId);
                    mysqli_stmt_execute($checkStmt);
                    $checkResult = mysqli_stmt_get_result($checkStmt);
                    $canReview = $checkResult && mysqli_num_rows($checkResult) > 0;
                    mysqli_stmt_close($checkStmt);

                    if (!$canReview) {
                        $error = "Feedback is only available after the service is completed.";
                    } else {
                        $feedbackStmt = mysqli_prepare($conn, "INSERT INTO appointment_feedback (appointment_id, customer_id, rating, comments) VALUES (?, ?, ?, ?)");
                        if ($feedbackStmt) {
                            mysqli_stmt_bind_param($feedbackStmt, "iiis", $appointmentId, $customerId, $rating, $comments);
                            if (mysqli_stmt_execute($feedbackStmt)) {
                                $message = "Thank you for your feedback.";
                            } else {
                                $error = "Feedback was already submitted for this appointment.";
                            }
                            mysqli_stmt_close($feedbackStmt);
                        } else {
                            $error = "Failed to prepare feedback submission.";
                        }
                    }
                } else {
                    $error = "Failed to check appointment status.";
                }
            }
        }
    }
}

$appointments = mysqli_query(
    $conn,
    "SELECT a.appointment_id, a.appointment_date, a.start_time, a.end_time, a.status, u.full_name AS staff_name, s.service_name,
            af.feedback_id, af.rating, af.comments
     FROM appointments a
     INNER JOIN users u ON a.staff_id = u.user_id
     INNER JOIN services s ON a.service_id = s.service_id
     LEFT JOIN appointment_feedback af ON a.appointment_id = af.appointment_id
     " . ($customerId > 0 ? "WHERE a.customer_id = {$customerId}" : "WHERE 1=0") . "
     ORDER BY a.appointment_date DESC, a.start_time DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments | SALONIX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-[#F9FAFB] text-[#2D2D2D]">
    <?php include "../includes/customersidebar.php"; ?>
    <main class="min-h-screen lg:ml-72 px-4 pb-8 pt-20 sm:px-6 lg:px-8 lg:pt-8">
        <section class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <h1 class="text-2xl font-semibold">My Appointments</h1>
            <p class="mt-1 text-sm text-gray-500">View all your booked appointment schedules.</p>
        </section>

        <?php if ($message !== ''): ?>
            <div class="mt-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"><?php echo e($message); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?php echo e($error); ?></div>
        <?php endif; ?>

        <section class="mt-6 rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-[#FDF4F5]">
                        <tr>
                            <th class="rounded-l-xl px-4 py-3">Service</th>
                            <th class="px-4 py-3">Staff</th>
                            <th class="px-4 py-3">Schedule</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="rounded-r-xl px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E5E7EB]">
                    <?php if ($appointments && mysqli_num_rows($appointments) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($appointments)): ?>
                            <tr class="hover:bg-[#FDF4F5] transition">
                                <td class="px-4 py-3"><?php echo e($row['service_name']); ?></td>
                                <td class="px-4 py-3"><?php echo e($row['staff_name']); ?></td>
                                <td class="px-4 py-3"><?php echo e($row['appointment_date'] . " " . date("g:i A", strtotime($row['start_time'])) . " - " . date("g:i A", strtotime($row['end_time']))); ?></td>
                                <td class="px-4 py-3 capitalize"><?php echo e(str_replace("_", " ", $row['status'])); ?></td>
                                <td class="px-4 py-3">
                                    <?php if (in_array($row['status'], ['pending', 'confirmed', 'rescheduled'], true)): ?>
                                        <form method="POST" onsubmit="return confirm('Cancel this appointment?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="appointment_id" value="<?php echo (int) $row['appointment_id']; ?>">
                                            <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100">Cancel</button>
                                        </form>
                                    <?php elseif ($row['status'] === 'completed' && empty($row['feedback_id'])): ?>
                                        <button
                                            type="button"
                                            class="js-feedback-button rounded-lg border border-[#F5D0D7] bg-[#FDF4F5] px-3 py-1.5 text-xs font-medium text-[#B76E79] hover:border-[#B76E79]"
                                            data-appointment-id="<?php echo (int) $row['appointment_id']; ?>"
                                            data-service="<?php echo e($row['service_name']); ?>"
                                        >
                                            Give feedback
                                        </button>
                                    <?php elseif ($row['status'] === 'completed'): ?>
                                        <span class="inline-flex rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700">
                                            Rated <?php echo (int) $row['rating']; ?>/5
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">No action</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No appointments found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <div id="feedbackModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <h2 class="text-xl font-semibold">Service Feedback</h2>
            <p id="feedbackServiceName" class="mt-1 text-sm text-gray-500"></p>
            <form method="POST" class="mt-5">
                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="feedback">
                <input type="hidden" name="appointment_id" id="feedbackAppointmentId">

                <label for="feedbackRating" class="mb-2 block text-sm font-medium">Rating</label>
                <select id="feedbackRating" name="rating" required class="w-full rounded-xl border border-[#E5E7EB] px-3 py-2.5 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]">
                    <option value="">Select rating</option>
                    <option value="5">5 - Excellent</option>
                    <option value="4">4 - Good</option>
                    <option value="3">3 - Okay</option>
                    <option value="2">2 - Poor</option>
                    <option value="1">1 - Very poor</option>
                </select>

                <label for="feedbackComments" class="mb-2 mt-4 block text-sm font-medium">Comments</label>
                <textarea id="feedbackComments" name="comments" rows="4" class="w-full rounded-xl border border-[#E5E7EB] px-3 py-2.5 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]" placeholder="Tell us about your service experience..."></textarea>

                <div class="mt-5 flex gap-3">
                    <button type="button" id="closeFeedbackModal" class="flex-1 rounded-xl bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-300">Cancel</button>
                    <button type="submit" class="flex-1 rounded-xl bg-[#B76E79] px-4 py-2 text-sm font-semibold text-white hover:bg-[#a9606b]">Submit</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const feedbackModal = document.getElementById('feedbackModal');
        const feedbackAppointmentId = document.getElementById('feedbackAppointmentId');
        const feedbackServiceName = document.getElementById('feedbackServiceName');
        const feedbackComments = document.getElementById('feedbackComments');
        const feedbackRating = document.getElementById('feedbackRating');
        const closeFeedbackModal = document.getElementById('closeFeedbackModal');

        document.addEventListener('click', (event) => {
            const button = event.target.closest('.js-feedback-button');
            if (!button) return;

            feedbackAppointmentId.value = button.dataset.appointmentId;
            feedbackServiceName.textContent = button.dataset.service || '';
            feedbackRating.value = '';
            feedbackComments.value = '';
            feedbackModal.classList.remove('hidden');
            feedbackModal.classList.add('flex');
        });

        closeFeedbackModal.addEventListener('click', () => {
            feedbackModal.classList.add('hidden');
            feedbackModal.classList.remove('flex');
        });
    </script>
</body>
</html>
