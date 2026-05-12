<?php
include "../config/session.php";
include "../config/conn.php";
include "../includes/delete_helpers.php";

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$message = "";
$error = "";
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editStaff = null;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $error = "Invalid request token.";
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
            $fullName = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $contact = trim($_POST['contact_number'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($fullName === '' || $username === '' || strlen($password) < 8) {
                $error = "Name, username, and password (8+ chars) are required.";
            } else {
                $check = mysqli_prepare($conn, "SELECT user_id FROM users WHERE username = ? LIMIT 1");
                if ($check) {
                    mysqli_stmt_bind_param($check, "s", $username);
                    mysqli_stmt_execute($check);
                    $exists = mysqli_stmt_get_result($check);
                    $already = $exists && mysqli_num_rows($exists) > 0;
                    mysqli_stmt_close($check);
                    if ($already) {
                        $error = "Username already exists.";
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, username, password, role, contact_number, email) VALUES (?, ?, ?, 'staff', ?, ?)");
                        if ($stmt) {
                            mysqli_stmt_bind_param($stmt, "sssss", $fullName, $username, $hash, $contact, $email);
                            if (mysqli_stmt_execute($stmt)) {
                                $message = "Staff member created successfully.";
                            } else {
                                $error = "Failed to create staff member.";
                            }
                            mysqli_stmt_close($stmt);
                        } else {
                            $error = "Failed to prepare staff creation.";
                        }
                    }
                } else {
                    $error = "Failed to validate username.";
                }
            }
        }

        if ($action === 'update') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $contact = trim($_POST['contact_number'] ?? '');
            $newPassword = $_POST['new_password'] ?? '';

            if ($userId <= 0 || $fullName === '') {
                $error = "Name is required.";
            } else {
                if ($newPassword !== '') {
                    if (strlen($newPassword) < 8) {
                        $error = "New password must be at least 8 characters.";
                    } else {
                        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                        $stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ?, contact_number = ?, password = ? WHERE user_id = ? AND role = 'staff'");
                        if ($stmt) {
                            mysqli_stmt_bind_param($stmt, "ssssi", $fullName, $email, $contact, $hash, $userId);
                            if (mysqli_stmt_execute($stmt)) {
                                $message = "Staff member updated successfully.";
                                $editId = 0;
                            } else {
                                $error = "Failed to update staff member.";
                            }
                            mysqli_stmt_close($stmt);
                        } else {
                            $error = "Failed to prepare staff update.";
                        }
                    }
                } else {
                    $stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ?, contact_number = ? WHERE user_id = ? AND role = 'staff'");
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, "sssi", $fullName, $email, $contact, $userId);
                        if (mysqli_stmt_execute($stmt)) {
                            $message = "Staff member updated successfully.";
                            $editId = 0;
                        } else {
                            $error = "Failed to update staff member.";
                        }
                        mysqli_stmt_close($stmt);
                    } else {
                        $error = "Failed to prepare staff update.";
                    }
                }
            }
        }

        if ($action === 'delete') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            if ($userId <= 0) {
                $error = "Invalid staff selected.";
            } else {
                try {
                    mysqli_begin_transaction($conn);
                    salonix_delete_user_with_appointments($conn, $userId, 'staff');
                    mysqli_commit($conn);
                    $message = "Staff member and linked appointments deleted successfully.";
                } catch (mysqli_sql_exception $exception) {
                    mysqli_rollback($conn);
                    $error = "Failed to delete staff member. Please try again.";
                }
            }
        }
    }
}

if ($editId > 0) {
    $stmt = mysqli_prepare($conn, "SELECT user_id, full_name, username, email, contact_number FROM users WHERE user_id = ? AND role = 'staff' LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $editId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $editStaff = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
    }
}

$staffMembers = mysqli_query($conn, "SELECT user_id, full_name, username, email, contact_number, created_at FROM users WHERE role = 'staff' ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management | SALONIX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-[#F9FAFB] text-[#2D2D2D]">
    <?php include "../includes/adminsidebar.php"; ?>
    <main class="min-h-screen lg:ml-72 px-4 pb-8 pt-20 sm:px-6 lg:px-8 lg:pt-8">
        <section class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <h1 class="text-2xl font-semibold">Staff Management</h1>
            <p class="mt-1 text-sm text-gray-500">Manage salon personnel details and records.</p>
        </section>

        <?php if ($message !== ''): ?>
            <div class="mt-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <section class="mt-6 rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <h2 class="text-lg font-semibold"><?php echo $editStaff ? 'Update Staff Member' : 'Create Staff Member'; ?></h2>
            <form method="POST" class="mt-4 grid gap-4 md:grid-cols-2">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="<?php echo $editStaff ? 'update' : 'create'; ?>">
                <?php if ($editStaff): ?>
                    <input type="hidden" name="user_id" value="<?php echo (int) $editStaff['user_id']; ?>">
                <?php endif; ?>
                <div>
                    <label class="mb-2 block text-sm font-medium">Full Name</label>
                    <input name="full_name" type="text" required class="w-full rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]" value="<?php echo htmlspecialchars($editStaff['full_name'] ?? ''); ?>">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium">Username</label>
                    <?php if ($editStaff): ?>
                        <input type="text" disabled class="w-full rounded-xl border border-[#E5E7EB] bg-gray-50 px-4 py-3 text-sm" value="<?php echo htmlspecialchars($editStaff['username']); ?>">
                    <?php else: ?>
                        <input name="username" type="text" required class="w-full rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]">
                    <?php endif; ?>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium">Email</label>
                    <input name="email" type="email" class="w-full rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]" value="<?php echo htmlspecialchars($editStaff['email'] ?? ''); ?>">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium">Contact Number</label>
                    <input name="contact_number" type="text" class="w-full rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]" value="<?php echo htmlspecialchars($editStaff['contact_number'] ?? ''); ?>">
                </div>
                <div class="md:col-span-2">
                    <?php if ($editStaff): ?>
                        <label class="mb-2 block text-sm font-medium">New Password (optional)</label>
                        <input name="new_password" type="password" class="w-full rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]" placeholder="Leave blank to keep current password">
                    <?php else: ?>
                        <label class="mb-2 block text-sm font-medium">Password</label>
                        <input name="password" type="password" required class="w-full rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm focus:border-[#B76E79] focus:outline-none focus:ring-2 focus:ring-[#F5D0D7]" placeholder="At least 8 characters">
                    <?php endif; ?>
                </div>
                <div class="md:col-span-2 flex gap-2">
                    <button type="submit" class="rounded-xl bg-[#B76E79] px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-[#a9606b]"><?php echo $editStaff ? 'Update Staff' : 'Create Staff'; ?></button>
                    <?php if ($editStaff): ?>
                        <a href="staff_management.php" class="rounded-xl border border-[#E5E7EB] px-4 py-2 text-sm font-medium hover:bg-[#FDF4F5]">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="mt-6 rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-[#FDF4F5]">
                        <tr>
                            <th class="rounded-l-xl px-4 py-3">Name</th>
                            <th class="px-4 py-3">Username</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Contact</th>
                            <th class="px-4 py-3">Added</th>
                            <th class="rounded-r-xl px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E5E7EB]">
                    <?php if ($staffMembers && mysqli_num_rows($staffMembers) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($staffMembers)): ?>
                            <tr class="hover:bg-[#FDF4F5] transition">
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['username']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['email'] ?: '-'); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($row['contact_number'] ?: '-'); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars(date("M d, Y", strtotime($row['created_at']))); ?></td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="staff_management.php?edit=<?php echo (int) $row['user_id']; ?>" class="rounded-lg border border-[#E5E7EB] px-3 py-1.5 text-xs font-medium hover:bg-white">Edit</a>
                                        <form method="POST" onsubmit="return confirm('Delete this staff account?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo (int) $row['user_id']; ?>">
                                            <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">No staff members found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
