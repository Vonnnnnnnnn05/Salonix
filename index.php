<?php
include "config/conn.php";

date_default_timezone_set('Asia/Taipei');
mysqli_query($conn, "SET time_zone = '+08:00'");

$today = date('Y-m-d');
$todayAppointmentCount = 0;
$todayAppointments = false;

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function appointment_dot_class($status) {
    $classes = [
        'pending' => 'bg-yellow-400',
        'confirmed' => 'bg-blue-500',
        'ongoing' => 'bg-indigo-500',
        'late' => 'bg-orange-500',
        'rescheduled' => 'bg-purple-500',
        'cancelled' => 'bg-red-500',
        'completed' => 'bg-green-500',
    ];

    return $classes[$status] ?? 'bg-gray-400';
}

$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM appointments WHERE appointment_date = ?");
if ($countStmt) {
    mysqli_stmt_bind_param($countStmt, "s", $today);
    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    $countRow = $countResult ? mysqli_fetch_assoc($countResult) : null;
    $todayAppointmentCount = $countRow ? (int) $countRow['total'] : 0;
    mysqli_stmt_close($countStmt);
}

$appointmentStmt = mysqli_prepare(
    $conn,
    "SELECT a.start_time, a.status, u.full_name, s.service_name
     FROM appointments a
     INNER JOIN users u ON a.customer_id = u.user_id
     INNER JOIN services s ON a.service_id = s.service_id
     WHERE a.appointment_date = ?
     ORDER BY
        CASE a.status
            WHEN 'pending' THEN 1
            WHEN 'confirmed' THEN 2
            WHEN 'ongoing' THEN 3
            WHEN 'late' THEN 4
            WHEN 'rescheduled' THEN 5
            WHEN 'cancelled' THEN 6
            WHEN 'completed' THEN 7
            ELSE 8
        END,
        a.start_time ASC
     LIMIT 3"
);
if ($appointmentStmt) {
    mysqli_stmt_bind_param($appointmentStmt, "s", $today);
    mysqli_stmt_execute($appointmentStmt);
    $todayAppointments = mysqli_stmt_get_result($appointmentStmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SALONIX | Salon Appointment and Service Monitoring System</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        *{
            font-family: 'Inter', sans-serif;
        }

        body{
            background: #FFFFFF;
            color: #2D2D2D;
        }

        .hover-card{
            transition: all 0.3s ease;
        }

        .hover-card:hover{
            transform: translateY(-5px);
        }
    </style>
</head>
<body class="antialiased">

    <!-- HERO SECTION -->
    <section class="w-full min-h-screen flex items-center">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 py-20">

            <div class="grid lg:grid-cols-2 gap-16 items-center">

                <!-- LEFT CONTENT -->
                <div>

                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-[#F5D0D7] bg-white mb-6">
                        <i class="fa-solid fa-scissors text-[#B76E79] text-sm"></i>
                        <span class="text-sm font-medium text-[#2D2D2D]">
                            Professional Salon Management System
                        </span>
                    </div>

                    <h1 class="text-5xl lg:text-6xl font-bold leading-tight text-[#2D2D2D] mb-6">
                        SALONIX
                    </h1>

                    <h2 class="text-2xl lg:text-3xl font-semibold text-[#B76E79] mb-6">
                        Smart Salon Appointment & Service Monitoring
                    </h2>

                    <p class="text-gray-600 text-lg leading-relaxed mb-10 max-w-xl">
                        Streamline salon scheduling, monitor appointments in real-time, reduce service delays, and improve customer satisfaction with an organized and modern management system.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-4">

                        <a href="login.php"
                           class="px-8 py-4 rounded-xl bg-[#B76E79] hover:bg-[#a85f69] text-white font-semibold shadow-md transition duration-300 text-center">
                            <i class="fa-solid fa-right-to-bracket mr-2"></i>
                            Login
                        </a>

                        <a href="register.php"
                           class="px-8 py-4 rounded-xl border border-[#F5D0D7] hover:border-[#B76E79] hover:bg-[#FDF4F5] text-[#2D2D2D] font-semibold transition duration-300 text-center">
                            <i class="fa-solid fa-user-plus mr-2"></i>
                            Register
                        </a>

                    </div>

                </div>

                <!-- RIGHT CONTENT -->
                <div class="relative">

                    <div class="bg-white border border-[#F5D0D7] rounded-3xl p-6 shadow-xl">

                        <!-- HEADER -->
                        <div class="flex items-center justify-between mb-8">

                            <div>
                                <h3 class="text-xl font-bold text-[#2D2D2D]">
                                    Today's Appointments
                                </h3>
                                <p class="text-sm text-gray-500">
                                    Real-time monitoring dashboard
                                </p>
                            </div>

                            <div class="w-12 h-12 rounded-2xl bg-[#FDF4F5] flex items-center justify-center">
                                <i class="fa-solid fa-calendar-check text-[#B76E79] text-xl"></i>
                            </div>

                        </div>

                        <!-- APPOINTMENT LIST -->

                        <div class="space-y-4">
                            <?php if ($todayAppointments && mysqli_num_rows($todayAppointments) > 0): ?>
                                <?php while ($appointment = mysqli_fetch_assoc($todayAppointments)): ?>
                                    <div class="flex items-center justify-between gap-4 p-4 rounded-2xl border border-gray-100 hover:bg-[#FDF4F5] transition">

                                        <div class="flex min-w-0 items-center gap-4">

                                            <div class="h-3 w-3 shrink-0 rounded-full <?php echo e(appointment_dot_class($appointment['status'])); ?>"></div>

                                            <div class="min-w-0">
                                                <h4 class="truncate font-semibold text-[#2D2D2D]">
                                                    <?php echo e($appointment['service_name']); ?>
                                                </h4>
                                                <p class="truncate text-sm text-gray-500">
                                                    <?php echo e($appointment['full_name']); ?>
                                                </p>
                                            </div>

                                        </div>

                                        <div class="shrink-0 text-right">
                                            <span class="block text-sm font-medium text-[#B76E79]">
                                                <?php echo e(date("g:i A", strtotime($appointment['start_time']))); ?>
                                            </span>
                                            <span class="mt-1 inline-flex rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[11px] font-medium capitalize text-gray-600">
                                                <?php echo e(str_replace('_', ' ', $appointment['status'])); ?>
                                            </span>
                                        </div>

                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="rounded-2xl border border-dashed border-gray-200 p-6 text-center">
                                    <p class="font-semibold text-[#2D2D2D]">No appointments today</p>
                                    <p class="mt-1 text-sm text-gray-500">New bookings will appear here automatically.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- FOOT -->
                        <div class="mt-8 pt-6 border-t border-gray-100 flex items-center justify-between">

                            <div class="flex items-center gap-2 text-sm text-gray-500">
                                <i class="fa-regular fa-clock"></i>
                                <span><?php echo (int) $todayAppointmentCount; ?> Appointment<?php echo $todayAppointmentCount === 1 ? '' : 's'; ?> Today</span>
                            </div>

                            <a href="login.php" class="text-[#B76E79] font-semibold text-sm hover:underline">
                                View Schedule
                            </a>

                        </div>

                    </div>

                </div>

            </div>

        </div>
    </section>

    <!-- HOW IT WORKS -->
    <section class="py-24 bg-white">

        <div class="max-w-7xl mx-auto px-6 lg:px-8">

            <div class="text-center mb-16">

                <h2 class="text-4xl font-bold text-[#2D2D2D] mb-4">
                    How SALONIX Works
                </h2>

                <p class="text-gray-500 text-lg max-w-2xl mx-auto">
                    Simplify salon operations through intelligent scheduling and real-time monitoring.
                </p>

            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">

                <!-- CARD -->
                <div class="bg-white border border-[#F5D0D7] rounded-3xl p-8 shadow-sm hover-card">

                    <div class="w-16 h-16 rounded-2xl bg-[#FDF4F5] flex items-center justify-center mb-6">
                        <i class="fa-solid fa-calendar-plus text-2xl text-[#B76E79]"></i>
                    </div>

                    <h3 class="text-xl font-bold mb-3">
                        Book Appointment
                    </h3>

                    <p class="text-gray-500 leading-relaxed">
                        Customers can easily reserve services using an organized appointment schedule.
                    </p>

                </div>

                <!-- CARD -->
                <div class="bg-white border border-[#F5D0D7] rounded-3xl p-8 shadow-sm hover-card">

                    <div class="w-16 h-16 rounded-2xl bg-[#FDF4F5] flex items-center justify-center mb-6">
                        <i class="fa-solid fa-user-clock text-2xl text-[#B76E79]"></i>
                    </div>

                    <h3 class="text-xl font-bold mb-3">
                        Monitor Late Clients
                    </h3>

                    <p class="text-gray-500 leading-relaxed">
                        Detect delayed customers and avoid disruptions in salon operations.
                    </p>

                </div>

                <!-- CARD -->
                <div class="bg-white border border-[#F5D0D7] rounded-3xl p-8 shadow-sm hover-card">

                    <div class="w-16 h-16 rounded-2xl bg-[#FDF4F5] flex items-center justify-center mb-6">
                        <i class="fa-solid fa-stopwatch text-2xl text-[#B76E79]"></i>
                    </div>

                    <h3 class="text-xl font-bold mb-3">
                        Track Services
                    </h3>

                    <p class="text-gray-500 leading-relaxed">
                        Monitor ongoing services and improve time management for staff and customers.
                    </p>

                </div>

                <!-- CARD -->
                <div class="bg-white border border-[#F5D0D7] rounded-3xl p-8 shadow-sm hover-card">

                    <div class="w-16 h-16 rounded-2xl bg-[#FDF4F5] flex items-center justify-center mb-6">
                        <i class="fa-solid fa-chart-line text-2xl text-[#B76E79]"></i>
                    </div>

                    <h3 class="text-xl font-bold mb-3">
                        Improve Workflow
                    </h3>

                    <p class="text-gray-500 leading-relaxed">
                        Reduce scheduling conflicts and enhance salon efficiency with organized monitoring.
                    </p>

                </div>

            </div>

        </div>

    </section>

    <!-- WHY CHOOSE -->
    <section class="py-24 bg-white">

        <div class="max-w-7xl mx-auto px-6 lg:px-8">

            <div class="text-center mb-16">

                <h2 class="text-4xl font-bold text-[#2D2D2D] mb-4">
                    Why Choose SALONIX
                </h2>

                <p class="text-gray-500 text-lg">
                    Designed to modernize and organize salon operations professionally.
                </p>

            </div>

            <div class="grid lg:grid-cols-3 gap-6">

                <div class="bg-white border border-[#F5D0D7] rounded-3xl p-8 shadow-sm">
                    <i class="fa-solid fa-calendar-check text-3xl text-[#B76E79] mb-6"></i>
                    <h3 class="text-xl font-bold mb-4">Smart Scheduling</h3>
                    <p class="text-gray-500">
                        Prevent overlapping appointments and organize customer bookings effectively.
                    </p>
                </div>

                <div class="bg-white border border-[#F5D0D7] rounded-3xl p-8 shadow-sm">
                    <i class="fa-solid fa-clock text-3xl text-[#B76E79] mb-6"></i>
                    <h3 class="text-xl font-bold mb-4">Time Monitoring</h3>
                    <p class="text-gray-500">
                        Improve salon productivity with proper service duration monitoring.
                    </p>
                </div>

                <div class="bg-white border border-[#F5D0D7] rounded-3xl p-8 shadow-sm">
                    <i class="fa-solid fa-users text-3xl text-[#B76E79] mb-6"></i>
                    <h3 class="text-xl font-bold mb-4">Customer Management</h3>
                    <p class="text-gray-500">
                        Organize customer records and improve overall salon experience.
                    </p>
                </div>

            </div>

        </div>

    </section>

    <!-- CTA -->
    <section class="py-24">

        <div class="max-w-5xl mx-auto px-6 lg:px-8">

            <div class="bg-white border border-[#F5D0D7] rounded-[40px] p-12 text-center shadow-sm">

                <div class="w-20 h-20 mx-auto rounded-3xl bg-[#FDF4F5] flex items-center justify-center mb-8">
                    <i class="fa-solid fa-spa text-4xl text-[#B76E79]"></i>
                </div>

                <h2 class="text-4xl font-bold text-[#2D2D2D] mb-6">
                    Start Managing Your Salon Efficiently
                </h2>

                <p class="text-gray-500 text-lg max-w-2xl mx-auto mb-10">
                    Experience a smarter and more organized way of handling appointments, salon services, and customer management.
                </p>

                <div class="flex flex-col sm:flex-row justify-center gap-4">

                    <a href="login.php"
                       class="px-8 py-4 rounded-xl bg-[#B76E79] hover:bg-[#a85f69] text-white font-semibold shadow-md transition duration-300">
                        <i class="fa-solid fa-right-to-bracket mr-2"></i>
                        Login
                    </a>

                    <a href="register.php"
                       class="px-8 py-4 rounded-xl border border-[#F5D0D7] hover:bg-[#FDF4F5] text-[#2D2D2D] font-semibold transition duration-300">
                        <i class="fa-solid fa-user-plus mr-2"></i>
                        Register
                    </a>

                </div>

            </div>

        </div>

    </section>

</body>
</html>
