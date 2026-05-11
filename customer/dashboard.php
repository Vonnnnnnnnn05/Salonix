<?php
include "../config/session.php";
include "../config/conn.php";

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'customer') {
    header("Location: ../index.php");
    exit;
}

$customerId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$filter = $customerId > 0 ? "WHERE customer_id = {$customerId}" : "";

function customer_count($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return 0;
    }
    $row = mysqli_fetch_row($result);
    return isset($row[0]) ? (int) $row[0] : 0;
}

$totalAppointments = customer_count($conn, "SELECT COUNT(*) FROM appointments {$filter}");
$upcomingAppointments = customer_count($conn, "SELECT COUNT(*) FROM appointments {$filter}" . ($filter ? " AND " : " WHERE ") . "appointment_date >= CURDATE()");
$completedAppointments = customer_count($conn, "SELECT COUNT(*) FROM appointments {$filter}" . ($filter ? " AND " : " WHERE ") . "status = 'completed'");

$latestAppointments = mysqli_query(
    $conn,
    "SELECT a.appointment_date, a.start_time, a.status, s.service_name
     FROM appointments a
     INNER JOIN services s ON a.service_id = s.service_id
     " . ($customerId > 0 ? "WHERE a.customer_id = {$customerId}" : "") . "
     ORDER BY a.appointment_date DESC, a.start_time DESC
     LIMIT 8"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard | SALONIX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-[#F9FAFB] text-[#2D2D2D]">
    <?php include "../includes/customersidebar.php"; ?>
    <main class="min-h-screen lg:ml-72 px-4 pb-8 pt-20 sm:px-6 lg:px-8 lg:pt-8">
        <section class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <h1 class="text-2xl font-semibold">Customer Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500">Your appointment and service summary.</p>
        </section>

        <section class="mt-6 grid gap-4 sm:grid-cols-3">
            <article class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-md">
                <p class="text-sm text-gray-500">Total Appointments</p>
                <p class="mt-2 text-3xl font-semibold text-[#B76E79]"><?php echo $totalAppointments; ?></p>
            </article>
            <article class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-md">
                <p class="text-sm text-gray-500">Upcoming</p>
                <p class="mt-2 text-3xl font-semibold text-[#B76E79]"><?php echo $upcomingAppointments; ?></p>
            </article>
            <article class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-md">
                <p class="text-sm text-gray-500">Completed</p>
                <p class="mt-2 text-3xl font-semibold text-[#B76E79]"><?php echo $completedAppointments; ?></p>
            </article>
        </section>

        <section class="mt-6 rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <h2 class="text-lg font-semibold">Recent Bookings</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-[#FDF4F5]">
                        <tr>
                            <th class="rounded-l-xl px-4 py-3">Service</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="rounded-r-xl px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E5E7EB]">
                    <?php if ($latestAppointments && mysqli_num_rows($latestAppointments) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($latestAppointments)): ?>
                            <tr class="hover:bg-[#FDF4F5] transition">
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['service_name']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['appointment_date'] . " " . date("g:i A", strtotime($row['start_time']))); ?></td>
                                <td class="px-4 py-3 capitalize"><?php echo htmlspecialchars(str_replace("_", " ", $row['status'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">No bookings yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>

