<?php
include "../config/session.php";
include "../config/conn.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../index.php");
    exit;
}

$customerId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

mysqli_query(
    $conn,
    "CREATE TABLE IF NOT EXISTS notification_reads (
        read_id int(11) NOT NULL AUTO_INCREMENT,
        user_id int(11) NOT NULL,
        appointment_id int(11) NOT NULL,
        read_at timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (read_id),
        UNIQUE KEY unique_user_notification_read (user_id, appointment_id),
        KEY appointment_id (appointment_id),
        CONSTRAINT notification_reads_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT notification_reads_ibfk_2 FOREIGN KEY (appointment_id) REFERENCES appointments (appointment_id) ON DELETE CASCADE ON UPDATE CASCADE
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
    if (hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $action = $_POST['action'] ?? '';

        if ($action === 'mark_read') {
            $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
            $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO notification_reads (user_id, appointment_id) SELECT ?, appointment_id FROM appointments WHERE appointment_id = ? AND customer_id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "iii", $customerId, $appointmentId, $customerId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }

        if ($action === 'mark_all_read') {
            $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO notification_reads (user_id, appointment_id) SELECT ?, appointment_id FROM appointments WHERE customer_id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ii", $customerId, $customerId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    }

    header("Location: notifications.php");
    exit;
}

$notifications = mysqli_query(
    $conn,
    "SELECT a.appointment_id, a.appointment_date, a.start_time, a.status, s.service_name, nr.read_id
     FROM appointments a
     INNER JOIN services s ON a.service_id = s.service_id
     LEFT JOIN notification_reads nr ON a.appointment_id = nr.appointment_id AND nr.user_id = {$customerId}
     " . ($customerId > 0 ? "WHERE a.customer_id = {$customerId}" : "WHERE 1=0") . "
     ORDER BY a.appointment_date DESC, a.start_time DESC
     LIMIT 10"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | SALONIX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-[#F9FAFB] text-[#2D2D2D]">
    <?php include "../includes/customersidebar.php"; ?>
    <main class="min-h-screen lg:ml-72 px-4 pb-8 pt-20 sm:px-6 lg:px-8 lg:pt-8">
        <section class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Notifications</h1>
                    <p class="mt-1 text-sm text-gray-500">Appointment updates and service reminders.</p>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="rounded-xl border border-[#F5D0D7] px-4 py-2 text-sm font-semibold text-[#B76E79] hover:bg-[#FDF4F5]">Mark all as read</button>
                </form>
            </div>
        </section>

        <section class="mt-6 space-y-3">
            <?php if ($notifications && mysqli_num_rows($notifications) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($notifications)): ?>
                    <?php $isUnread = empty($row['read_id']); ?>
                    <article class="rounded-2xl border <?php echo $isUnread ? 'border-[#F5D0D7]' : 'border-[#E5E7EB]'; ?> bg-white p-5 shadow-md">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex items-start gap-3">
                            <div class="mt-1 h-8 w-8 rounded-xl bg-[#F5D0D7] text-[#B76E79] flex items-center justify-center">
                                <i class="fa-solid fa-bell"></i>
                            </div>
                            <div>
                                <p class="font-medium"><?php echo e($row['service_name']); ?> appointment is <span class="capitalize text-[#B76E79]"><?php echo e(str_replace("_", " ", $row['status'])); ?></span>.</p>
                                <p class="mt-1 text-sm text-gray-500"><?php echo e($row['appointment_date'] . " at " . date("g:i A", strtotime($row['start_time']))); ?></p>
                            </div>
                            </div>
                            <?php if ($isUnread): ?>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="appointment_id" value="<?php echo (int) $row['appointment_id']; ?>">
                                    <button type="submit" class="rounded-lg border border-[#F5D0D7] px-3 py-1.5 text-xs font-semibold text-[#B76E79] hover:bg-[#FDF4F5]">Mark as read</button>
                                </form>
                            <?php else: ?>
                                <span class="text-xs font-medium text-gray-400">Read</span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endwhile; ?>
            <?php else: ?>
                <article class="rounded-2xl border border-[#E5E7EB] bg-white p-8 text-center text-gray-500 shadow-md">No notifications available.</article>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>

