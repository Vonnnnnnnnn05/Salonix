<?php
include "../config/session.php";
include "../config/conn.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../index.php");
    exit;
}

$staffId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

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
        CONSTRAINT appointment_feedback_ibfk_1 FOREIGN KEY (appointment_id) REFERENCES appointments (appointment_id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT appointment_feedback_ibfk_2 FOREIGN KEY (customer_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$feedback = false;
$stmt = mysqli_prepare(
    $conn,
    "SELECT af.rating, af.comments, af.created_at, a.appointment_date, a.start_time,
            customer.full_name AS customer_name, s.service_name
     FROM appointment_feedback af
     INNER JOIN appointments a ON af.appointment_id = a.appointment_id
     INNER JOIN users customer ON af.customer_id = customer.user_id
     INNER JOIN services s ON a.service_id = s.service_id
     WHERE a.staff_id = ?
     ORDER BY af.created_at DESC"
);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $staffId);
    mysqli_stmt_execute($stmt);
    $feedback = mysqli_stmt_get_result($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback | SALONIX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-[#F9FAFB] text-[#2D2D2D]">
    <?php include "../includes/staffsidebar.php"; ?>
    <main class="min-h-screen lg:ml-72 px-4 pb-8 pt-20 sm:px-6 lg:px-8 lg:pt-8">
        <section class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <h1 class="text-2xl font-semibold">Customer Feedback</h1>
            <p class="mt-1 text-sm text-gray-500">Ratings and comments from your completed services.</p>
        </section>

        <section class="mt-6 rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-[#FDF4F5]">
                        <tr>
                            <th class="rounded-l-xl px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Service</th>
                            <th class="px-4 py-3">Appointment</th>
                            <th class="px-4 py-3">Rating</th>
                            <th class="rounded-r-xl px-4 py-3">Comments</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E5E7EB]">
                    <?php if ($feedback && mysqli_num_rows($feedback) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($feedback)): ?>
                            <tr class="hover:bg-[#FDF4F5] transition">
                                <td class="px-4 py-3"><?php echo e($row['customer_name']); ?></td>
                                <td class="px-4 py-3"><?php echo e($row['service_name']); ?></td>
                                <td class="px-4 py-3"><?php echo e($row['appointment_date'] . " " . date("g:i A", strtotime($row['start_time']))); ?></td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full border border-yellow-200 bg-yellow-50 px-2.5 py-1 text-xs font-semibold text-yellow-700">
                                        <?php echo (int) $row['rating']; ?>/5
                                    </span>
                                </td>
                                <td class="max-w-sm px-4 py-3 text-gray-600"><?php echo e($row['comments'] ?: '-'); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No feedback submitted yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
