<?php
include "../config/session.php";
include "../config/conn.php";

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'customer') {
    header("Location: ../index.php");
    exit;
}

$customerId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$notifications = mysqli_query(
    $conn,
    "SELECT a.appointment_date, a.start_time, a.status, s.service_name
     FROM appointments a
     INNER JOIN services s ON a.service_id = s.service_id
     " . ($customerId > 0 ? "WHERE a.customer_id = {$customerId}" : "") . "
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
            <h1 class="text-2xl font-semibold">Notifications</h1>
            <p class="mt-1 text-sm text-gray-500">Appointment updates and service reminders.</p>
        </section>

        <section class="mt-6 space-y-3">
            <?php if ($notifications && mysqli_num_rows($notifications) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($notifications)): ?>
                    <article class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-md">
                        <div class="flex items-start gap-3">
                            <div class="mt-1 h-8 w-8 rounded-xl bg-[#F5D0D7] text-[#B76E79] flex items-center justify-center">
                                <i class="fa-solid fa-bell"></i>
                            </div>
                            <div>
                                <p class="font-medium"><?php echo htmlspecialchars($row['service_name']); ?> appointment is <span class="capitalize text-[#B76E79]"><?php echo htmlspecialchars(str_replace("_", " ", $row['status'])); ?></span>.</p>
                                <p class="mt-1 text-sm text-gray-500"><?php echo htmlspecialchars($row['appointment_date'] . " at " . date("g:i A", strtotime($row['start_time']))); ?></p>
                            </div>
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

