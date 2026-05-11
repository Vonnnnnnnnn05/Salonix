<?php
include "../config/session.php";
include "../config/conn.php";

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'staff') {
    header("Location: ../index.php");
    exit;
}

$staffId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$staffFilter = $staffId > 0 ? "WHERE a.staff_id = {$staffId}" : "";

function staff_count($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return 0;
    }
    $row = mysqli_fetch_row($result);
    return isset($row[0]) ? (int) $row[0] : 0;
}

$today = date("Y-m-d");
$todayCount = staff_count($conn, "SELECT COUNT(*) FROM appointments a {$staffFilter}" . ($staffFilter ? " AND " : " WHERE ") . "a.appointment_date = '{$today}'");
$ongoingCount = staff_count($conn, "SELECT COUNT(*) FROM appointments a {$staffFilter}" . ($staffFilter ? " AND " : " WHERE ") . "a.status = 'ongoing'");
$completedCount = staff_count($conn, "SELECT COUNT(*) FROM appointments a {$staffFilter}" . ($staffFilter ? " AND " : " WHERE ") . "a.status = 'completed'");

$latest = mysqli_query(
    $conn,
    "SELECT a.appointment_date, a.start_time, a.status, u.full_name, s.service_name
     FROM appointments a
     INNER JOIN users u ON a.customer_id = u.user_id
     INNER JOIN services s ON a.service_id = s.service_id
     " . ($staffFilter ? "WHERE a.staff_id = {$staffId}" : "") . "
     ORDER BY a.appointment_date DESC, a.start_time DESC
     LIMIT 8"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard | SALONIX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-[#F9FAFB] text-[#2D2D2D]">
    <?php include "../includes/staffsidebar.php"; ?>
    <main class="min-h-screen lg:ml-72 px-4 pb-8 pt-20 sm:px-6 lg:px-8 lg:pt-8">
        <section class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <h1 class="text-2xl font-semibold">Staff Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500">Daily operational view for assigned services.</p>
        </section>

        <section class="mt-6 grid gap-4 sm:grid-cols-3">
            <article class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-md">
                <p class="text-sm text-gray-500">Today's Appointments</p>
                <p class="mt-2 text-3xl font-semibold text-[#B76E79]"><?php echo $todayCount; ?></p>
            </article>
            <article class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-md">
                <p class="text-sm text-gray-500">Ongoing</p>
                <p class="mt-2 text-3xl font-semibold text-[#B76E79]"><?php echo $ongoingCount; ?></p>
            </article>
            <article class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-md">
                <p class="text-sm text-gray-500">Completed</p>
                <p class="mt-2 text-3xl font-semibold text-[#B76E79]"><?php echo $completedCount; ?></p>
            </article>
        </section>

        <section class="mt-6 rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <h2 class="text-lg font-semibold">Recent Appointments</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-[#FDF4F5]">
                        <tr>
                            <th class="rounded-l-xl px-4 py-3">Client</th>
                            <th class="px-4 py-3">Service</th>
                            <th class="px-4 py-3">Schedule</th>
                            <th class="rounded-r-xl px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E5E7EB]">
                    <?php if ($latest && mysqli_num_rows($latest) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($latest)): ?>
                            <tr class="hover:bg-[#FDF4F5] transition">
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['service_name']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['appointment_date'] . " " . date("g:i A", strtotime($row['start_time']))); ?></td>
                                <td class="px-4 py-3 capitalize"><?php echo htmlspecialchars(str_replace("_", " ", $row['status'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">No appointments found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>

