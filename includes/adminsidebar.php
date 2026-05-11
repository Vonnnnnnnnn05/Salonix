<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$notificationCount = 0;

if (isset($conn, $_SESSION['user_id'])) {
    mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS notification_reads (
            read_id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            appointment_id int(11) NOT NULL,
            read_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (read_id),
            UNIQUE KEY unique_user_notification_read (user_id, appointment_id),
            KEY appointment_id (appointment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $adminIdForNotifications = (int) $_SESSION['user_id'];
    $notificationStmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS total
         FROM appointments a
         LEFT JOIN notification_reads nr ON a.appointment_id = nr.appointment_id AND nr.user_id = ?
         WHERE nr.read_id IS NULL"
    );
    if ($notificationStmt) {
        mysqli_stmt_bind_param($notificationStmt, "i", $adminIdForNotifications);
        mysqli_stmt_execute($notificationStmt);
        $notificationResult = mysqli_stmt_get_result($notificationStmt);
        $notificationRow = $notificationResult ? mysqli_fetch_assoc($notificationResult) : null;
        $notificationCount = $notificationRow ? (int) $notificationRow['total'] : 0;
        mysqli_stmt_close($notificationStmt);
    }
}

$adminMenu = [
    ['label' => 'Dashboard', 'icon' => 'fa-solid fa-gauge-high', 'href' => '../admin/dashboard.php', 'file' => 'dashboard.php'],
    ['label' => 'Notifications', 'icon' => 'fa-solid fa-bell', 'href' => '../admin/notifications.php', 'file' => 'notifications.php'],
    ['label' => 'Manage Appointments', 'icon' => 'fa-solid fa-calendar-check', 'href' => '../admin/manage_appointments.php', 'file' => 'manage_appointments.php'],
    ['label' => 'Manage Services', 'icon' => 'fa-solid fa-scissors', 'href' => '../admin/manage_services.php', 'file' => 'manage_services.php'],
    ['label' => 'Service Monitoring', 'icon' => 'fa-solid fa-chart-line', 'href' => '../admin/service_monitoring.php', 'file' => 'service_monitoring.php'],
    ['label' => 'Feedback', 'icon' => 'fa-solid fa-star', 'href' => '../admin/feedback.php', 'file' => 'feedback.php'],
    ['label' => 'Customers', 'icon' => 'fa-solid fa-users', 'href' => '../admin/customers.php', 'file' => 'customers.php'],
    ['label' => 'Staff Management', 'icon' => 'fa-solid fa-user-gear', 'href' => '../admin/staff_management.php', 'file' => 'staff_management.php'],
    ['label' => 'Reports', 'icon' => 'fa-solid fa-file-lines', 'href' => '../admin/reports.php', 'file' => 'reports.php'],
    ['label' => 'Settings', 'icon' => 'fa-solid fa-sliders', 'href' => '../admin/settings.php', 'file' => 'settings.php'],
    ['label' => 'Logout', 'icon' => 'fa-solid fa-right-from-bracket', 'href' => '../admin/logout.php', 'file' => 'logout.php'],
];
?>
<button id="adminSidebarToggle" type="button" class="lg:hidden fixed top-4 left-4 z-50 inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white text-[#2D2D2D] shadow-md transition hover:bg-[#FDF4F5]" aria-label="Open admin sidebar">
    <i class="fa-solid fa-bars"></i>
</button>

<div id="adminSidebarOverlay" class="fixed inset-0 z-40 hidden bg-black/30 lg:hidden"></div>

<aside id="adminSidebar" class="fixed inset-y-0 left-0 z-40 w-72 -translate-x-full border-r border-[#E5E7EB] bg-white px-4 py-6 shadow-lg transition-transform duration-300 lg:translate-x-0 flex flex-col overflow-hidden">
    <div class="mb-8 flex items-center gap-3 rounded-2xl bg-[#F5D0D7] px-4 py-3 flex-shrink-0">
        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white text-[#B76E79] shadow-sm">
            <i class="fa-solid fa-spa text-lg"></i>
        </div>
        <div>
            <h1 class="text-base font-semibold text-[#2D2D2D]">SALONIX Admin</h1>
            <p class="text-xs text-[#2D2D2D]/70">Management Panel</p>
        </div>
    </div>

    <nav class="space-y-1 overflow-y-auto scrollbar-thin scrollbar-thumb-[#B76E79] scrollbar-track-[#F5D0D7] pr-2" aria-label="Admin Sidebar Navigation">
        <?php foreach ($adminMenu as $item): ?>
            <?php $active = $currentPage === $item['file']; ?>
            <a
                href="<?php echo htmlspecialchars($item['href']); ?>"
                class="<?php echo $active ? 'bg-[#B76E79] text-white shadow-md' : 'text-[#2D2D2D] hover:bg-[#FDF4F5]'; ?> group relative flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition duration-200"
                <?php if ($item['file'] === 'notifications.php'): ?>
                    data-notification-link="true"
                    data-notification-count="<?php echo (int) $notificationCount; ?>"
                <?php endif; ?>
            >
                <i class="<?php echo htmlspecialchars($item['icon']); ?> w-5 text-center"></i>
                <span><?php echo htmlspecialchars($item['label']); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>

<script>
(() => {
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('adminSidebarOverlay');
    const toggle = document.getElementById('adminSidebarToggle');

    if (!sidebar || !overlay || !toggle) return;

    const closeSidebar = () => {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    };

    const openSidebar = () => {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
    };

    toggle.addEventListener('click', openSidebar);
    overlay.addEventListener('click', closeSidebar);
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024) closeSidebar();
    });

    const notificationLink = document.querySelector('[data-notification-link="true"]');
    if (notificationLink) {
        const count = Number(notificationLink.dataset.notificationCount || 0);
        if (count > 0) {
            const badge = document.createElement('span');
            badge.className = 'absolute right-3 top-2 inline-flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 py-0.5 text-[11px] font-bold leading-none text-white shadow-sm';
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.setAttribute('aria-label', `${count} notifications`);
            notificationLink.appendChild(badge);
        }
    }
})();
</script>
