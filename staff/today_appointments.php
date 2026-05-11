<?php
include "../config/session.php";
include "../config/conn.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../index.php");
    exit;
}

date_default_timezone_set('Asia/Taipei');
mysqli_query($conn, "SET time_zone = '+08:00'");

$staffId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$today = date('Y-m-d');
$allowedStatuses = ['pending', 'confirmed', 'ongoing', 'completed', 'late', 'cancelled', 'rescheduled'];

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function status_badge_class($status) {
    $classes = [
        'pending' => 'border-yellow-200 bg-yellow-50 text-yellow-700',
        'confirmed' => 'border-blue-200 bg-blue-50 text-blue-700',
        'ongoing' => 'border-indigo-200 bg-indigo-50 text-indigo-700',
        'late' => 'border-orange-200 bg-orange-50 text-orange-700',
        'rescheduled' => 'border-purple-200 bg-purple-50 text-purple-700',
        'cancelled' => 'border-red-200 bg-red-50 text-red-700',
        'completed' => 'border-green-200 bg-green-50 text-green-700',
    ];

    return $classes[$status] ?? 'border-gray-200 bg-gray-50 text-gray-700';
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $appointmentId = isset($_POST['appointment_id']) ? (int) $_POST['appointment_id'] : 0;
        $status = $_POST['status'] ?? 'pending';
        $remarks = trim($_POST['remarks'] ?? '');

        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'pending';
        }

        $updateStmt = mysqli_prepare($conn, "UPDATE appointments SET status = ?, remarks = ? WHERE appointment_id = ? AND staff_id = ?");
        if ($updateStmt) {
            mysqli_stmt_bind_param($updateStmt, "ssii", $status, $remarks, $appointmentId, $staffId);

            if (mysqli_stmt_execute($updateStmt) && mysqli_stmt_affected_rows($updateStmt) >= 0) {
                $success = "Appointment updated successfully.";
            } else {
                $error = "Failed to update appointment.";
            }
            mysqli_stmt_close($updateStmt);
        } else {
            $error = "Failed to prepare appointment update.";
        }
    }
}

// Handle MARK ARRIVED
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_arrived') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $appointmentId = isset($_POST['appointment_id']) ? (int) $_POST['appointment_id'] : 0;
        $arrivalTime = $_POST['arrival_time'] ?? date('H:i');

        if ($appointmentId <= 0 || !preg_match('/^\d{2}:\d{2}$/', $arrivalTime)) {
            $error = "Invalid arrival details.";
        } else {
            $appointStmt = mysqli_prepare($conn, "SELECT appointment_date, start_time FROM appointments WHERE appointment_id = ? AND staff_id = ? AND appointment_date = ?");
            if ($appointStmt) {
                mysqli_stmt_bind_param($appointStmt, "iis", $appointmentId, $staffId, $today);
                mysqli_stmt_execute($appointStmt);
                $appointResult = mysqli_stmt_get_result($appointStmt);
                $appointRow = $appointResult ? mysqli_fetch_assoc($appointResult) : null;
                mysqli_stmt_close($appointStmt);

                if ($appointRow) {
                    $startDateTime = new DateTime($appointRow['appointment_date'] . ' ' . $appointRow['start_time']);
                    $arrivalDateTime = new DateTime($appointRow['appointment_date'] . ' ' . $arrivalTime);
                    $lateMinutes = max(0, (int) floor(($arrivalDateTime->getTimestamp() - $startDateTime->getTimestamp()) / 60));
                    $newStatus = $lateMinutes > 0 ? 'late' : 'ongoing';

                    mysqli_begin_transaction($conn);
                    $transactionOk = true;

                    $statusStmt = mysqli_prepare($conn, "UPDATE appointments SET status = ? WHERE appointment_id = ? AND staff_id = ?");
                    if ($statusStmt) {
                        mysqli_stmt_bind_param($statusStmt, "sii", $newStatus, $appointmentId, $staffId);
                        $transactionOk = mysqli_stmt_execute($statusStmt);
                        mysqli_stmt_close($statusStmt);
                    } else {
                        $transactionOk = false;
                    }

                    if ($transactionOk) {
                        $deleteLateStmt = mysqli_prepare($conn, "DELETE FROM late_clients WHERE appointment_id = ?");
                        if ($deleteLateStmt) {
                            mysqli_stmt_bind_param($deleteLateStmt, "i", $appointmentId);
                            $transactionOk = mysqli_stmt_execute($deleteLateStmt);
                            mysqli_stmt_close($deleteLateStmt);
                        } else {
                            $transactionOk = false;
                        }
                    }

                    if ($transactionOk && $lateMinutes > 0) {
                        $lateStmt = mysqli_prepare($conn, "INSERT INTO late_clients (appointment_id, late_minutes, action_taken, recorded_at) VALUES (?, ?, 'waited', NOW())");
                        if ($lateStmt) {
                            mysqli_stmt_bind_param($lateStmt, "ii", $appointmentId, $lateMinutes);
                            $transactionOk = mysqli_stmt_execute($lateStmt);
                            mysqli_stmt_close($lateStmt);
                        } else {
                            $transactionOk = false;
                        }
                    }

                    if ($transactionOk) {
                        mysqli_commit($conn);
                        $success = $lateMinutes > 0
                            ? "Client marked as arrived. Late by {$lateMinutes} minutes."
                            : "Client marked as arrived on time. Service is now ongoing.";
                    } else {
                        mysqli_rollback($conn);
                        $error = "Failed to record arrival.";
                    }
                } else {
                    $error = "Today's appointment was not found for this staff account.";
                }
            } else {
                $error = "Failed to prepare appointment lookup.";
            }
        }
    }
}

$appointments = false;
$appointmentStmt = mysqli_prepare(
    $conn,
    "SELECT a.appointment_id, a.appointment_date, a.start_time, a.end_time, a.status, a.remarks, u.full_name, s.service_name
     FROM appointments a
     INNER JOIN users u ON a.customer_id = u.user_id
     INNER JOIN services s ON a.service_id = s.service_id
     WHERE a.appointment_date = ? AND a.staff_id = ?
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
        a.start_time ASC"
);
if ($appointmentStmt) {
    mysqli_stmt_bind_param($appointmentStmt, "si", $today, $staffId);
    mysqli_stmt_execute($appointmentStmt);
    $appointments = mysqli_stmt_get_result($appointmentStmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Today Appointments | SALONIX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-[#F9FAFB] text-[#2D2D2D]">
    <?php include "../includes/staffsidebar.php"; ?>
    <main class="min-h-screen lg:ml-72 px-4 pb-8 pt-20 sm:px-6 lg:px-8 lg:pt-8">
        <section class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <h1 class="text-2xl font-semibold">Today's Appointments</h1>
            <p class="mt-1 text-sm text-gray-500">Scheduled clients for today. Click edit to update status.</p>
        </section>

        <?php if (isset($success)): ?>
            <div class="mt-4 rounded-2xl bg-green-50 border border-green-200 p-4 text-green-700">
                <i class="fas fa-check-circle mr-2"></i><?php echo e($success); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="mt-4 rounded-2xl bg-red-50 border border-red-200 p-4 text-red-700">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo e($error); ?>
            </div>
        <?php endif; ?>

        <section class="mt-6 rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-[#FDF4F5]">
                        <tr>
                            <th class="rounded-l-xl px-4 py-3">Client</th>
                            <th class="px-4 py-3">Service</th>
                            <th class="px-4 py-3">Time</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="rounded-r-xl px-4 py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E5E7EB]">
                    <?php if ($appointments && mysqli_num_rows($appointments) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($appointments)): ?>
                            <tr class="hover:bg-[#FDF4F5] transition">
                                <td class="px-4 py-3"><?php echo e($row['full_name']); ?></td>
                                <td class="px-4 py-3"><?php echo e($row['service_name']); ?></td>
                                <td class="px-4 py-3"><?php echo e(date("g:i A", strtotime($row['start_time'])) . " - " . date("g:i A", strtotime($row['end_time']))); ?></td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold capitalize <?php echo e(status_badge_class($row['status'])); ?>">
                                        <?php echo e(str_replace("_", " ", $row['status'])); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-2">
                                        <button
                                            type="button"
                                            class="js-edit-appointment text-[#B76E79] hover:text-[#964a54] font-medium"
                                            data-appointment-id="<?php echo (int) $row['appointment_id']; ?>"
                                            data-status="<?php echo e($row['status']); ?>"
                                            data-remarks="<?php echo e($row['remarks'] ?? ''); ?>"
                                        >
                                            <i class="fas fa-edit mr-1"></i>Edit
                                        </button>
                                        <button
                                            type="button"
                                            class="js-arrival-appointment text-green-600 hover:text-green-700 font-medium"
                                            data-appointment-id="<?php echo (int) $row['appointment_id']; ?>"
                                        >
                                            <i class="fas fa-check mr-1"></i>Arrived
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No appointments today.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- Edit Modal -->
    <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full">
            <h2 class="text-xl font-semibold mb-4">Edit Appointment</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="appointment_id" id="appointmentId">

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Status</label>
                    <select name="status" id="appointmentStatus" required class="w-full px-3 py-2 border border-[#E5E7EB] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#B76E79]">
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                        <option value="late">Late</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="rescheduled">Rescheduled</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Remarks</label>
                    <textarea name="remarks" id="appointmentRemarks" rows="3" class="w-full px-3 py-2 border border-[#E5E7EB] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#B76E79]" placeholder="Add any notes..."></textarea>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeEditModal()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-[#B76E79] text-white rounded-lg hover:bg-[#964a54] transition">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Arrival Modal -->
    <div id="arrivalModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full">
            <h2 class="text-xl font-semibold mb-4">Mark Client Arrival</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
                <input type="hidden" name="action" value="mark_arrived">
                <input type="hidden" name="appointment_id" id="arrivalAppointmentId">

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Actual Arrival Time</label>
                    <input type="time" name="arrival_time" id="arrivalTime" required class="w-full px-3 py-2 border border-[#E5E7EB] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#B76E79]">
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeArrivalModal()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">Confirm Arrival</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        function openEditModal(id, status, remarks) {
            document.getElementById('appointmentId').value = id;
            document.getElementById('appointmentStatus').value = status || 'pending';
            document.getElementById('appointmentRemarks').value = remarks || '';
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        function openArrivalModal(id) {
            document.getElementById('arrivalAppointmentId').value = id;
            document.getElementById('arrivalTime').value = new Date().toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit' });
            document.getElementById('arrivalModal').classList.remove('hidden');
        }

        function closeArrivalModal() {
            document.getElementById('arrivalModal').classList.add('hidden');
        }

        document.addEventListener('click', function (event) {
            const editButton = event.target.closest('.js-edit-appointment');
            if (editButton) {
                openEditModal(
                    editButton.dataset.appointmentId,
                    editButton.dataset.status,
                    editButton.dataset.remarks
                );
                return;
            }

            const arrivalButton = event.target.closest('.js-arrival-appointment');
            if (arrivalButton) {
                openArrivalModal(arrivalButton.dataset.appointmentId);
            }
        });
    </script>
</body>
</html>

