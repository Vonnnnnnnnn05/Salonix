<?php
include "../config/session.php";
include "../config/conn.php";

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$monitoring = mysqli_query(
    $conn,
    "SELECT sm.monitoring_id, sm.service_status, sm.actual_start_time, sm.actual_end_time, sm.notes, a.appointment_date, u.full_name, s.service_name
     FROM service_monitoring sm
     INNER JOIN appointments a ON sm.appointment_id = a.appointment_id
     INNER JOIN users u ON a.customer_id = u.user_id
     INNER JOIN services s ON a.service_id = s.service_id
     ORDER BY sm.monitoring_id DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Monitoring | SALONIX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-[#F9FAFB] text-[#2D2D2D]">
    <?php include "../includes/adminsidebar.php"; ?>
    <main class="min-h-screen lg:ml-72 px-4 pb-8 pt-20 sm:px-6 lg:px-8 lg:pt-8">
        <section class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <h1 class="text-2xl font-semibold">Service Monitoring</h1>
            <p class="mt-1 text-sm text-gray-500">Track ongoing and completed service delivery.</p>
        </section>

        <section class="mt-6 rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-[#FDF4F5]">
                        <tr>
                            <th class="rounded-l-xl px-4 py-3">Client</th>
                            <th class="px-4 py-3">Service</th>
                            <th class="px-4 py-3">Appointment</th>
                            <th class="px-4 py-3">Start</th>
                            <th class="px-4 py-3">End</th>
                            <th class="rounded-r-xl px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E5E7EB]">
                    <?php if ($monitoring && mysqli_num_rows($monitoring) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($monitoring)): ?>
                            <tr class="hover:bg-[#FDF4F5] transition">
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['service_name']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['appointment_date']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['actual_start_time'] ?: '-'); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['actual_end_time'] ?: '-'); ?></td>
                                <td class="px-4 py-3 capitalize"><?php echo htmlspecialchars(str_replace("_", " ", $row['service_status'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">No service monitoring records found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>

