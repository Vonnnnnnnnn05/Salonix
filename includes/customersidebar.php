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
            KEY appointment_id (appointment_id),
            CONSTRAINT notification_reads_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT notification_reads_ibfk_2 FOREIGN KEY (appointment_id) REFERENCES appointments (appointment_id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $customerIdForNotifications = (int) $_SESSION['user_id'];
    $notificationStmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS total
         FROM appointments a
         LEFT JOIN notification_reads nr ON a.appointment_id = nr.appointment_id AND nr.user_id = ?
         WHERE a.customer_id = ? AND nr.read_id IS NULL"
    );
    if ($notificationStmt) {
        mysqli_stmt_bind_param($notificationStmt, "ii", $customerIdForNotifications, $customerIdForNotifications);
        mysqli_stmt_execute($notificationStmt);
        $notificationResult = mysqli_stmt_get_result($notificationStmt);
        $notificationRow = $notificationResult ? mysqli_fetch_assoc($notificationResult) : null;
        $notificationCount = $notificationRow ? (int) $notificationRow['total'] : 0;
        mysqli_stmt_close($notificationStmt);
    }
}

$customerMenu = [
    ['label' => 'Dashboard', 'icon' => 'fa-solid fa-gauge-high', 'href' => '../customer/dashboard.php', 'file' => 'dashboard.php'],
    ['label' => 'Book Appointment', 'icon' => 'fa-solid fa-calendar-plus', 'href' => '../customer/book_appointment.php', 'file' => 'book_appointment.php'],
    ['label' => 'My Appointments', 'icon' => 'fa-solid fa-calendar-check', 'href' => '../customer/my_appointments.php', 'file' => 'my_appointments.php'],
    ['label' => 'Service History', 'icon' => 'fa-solid fa-clock-rotate-left', 'href' => '../customer/service_history.php', 'file' => 'service_history.php'],
    ['label' => 'Notifications', 'icon' => 'fa-solid fa-bell', 'href' => '../customer/notifications.php', 'file' => 'notifications.php'],
    ['label' => 'Profile', 'icon' => 'fa-solid fa-user', 'href' => '../customer/profile.php', 'file' => 'profile.php'],
    ['label' => 'Logout', 'icon' => 'fa-solid fa-right-from-bracket', 'href' => '../customer/logout.php', 'file' => 'logout.php'],
];
?>
<button id="customerSidebarToggle" type="button" class="lg:hidden fixed top-4 left-4 z-50 inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white text-[#2D2D2D] shadow-md transition hover:bg-[#FDF4F5]" aria-label="Open customer sidebar">
    <i class="fa-solid fa-bars"></i>
</button>

<div id="customerSidebarOverlay" class="fixed inset-0 z-40 hidden bg-black/30 lg:hidden"></div>

<aside id="customerSidebar" class="fixed inset-y-0 left-0 z-40 w-72 -translate-x-full border-r border-[#E5E7EB] bg-white px-4 py-6 shadow-lg transition-transform duration-300 lg:translate-x-0 flex flex-col overflow-hidden">
    <div class="mb-8 flex items-center gap-3 rounded-2xl bg-[#F5D0D7] px-4 py-3 flex-shrink-0">
        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white text-[#B76E79] shadow-sm">
            <i class="fa-solid fa-heart text-lg"></i>
        </div>
        <div>
            <h1 class="text-base font-semibold text-[#2D2D2D]">SALONIX Client</h1>
            <p class="text-xs text-[#2D2D2D]/70">Customer Portal</p>
        </div>
    </div>

    <nav class="space-y-1 overflow-y-auto scrollbar-thin scrollbar-thumb-[#B76E79] scrollbar-track-[#F5D0D7] pr-2" aria-label="Customer Sidebar Navigation">
        <?php foreach ($customerMenu as $item): ?>
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
    const sidebar = document.getElementById('customerSidebar');
    const overlay = document.getElementById('customerSidebarOverlay');
    const toggle = document.getElementById('customerSidebarToggle');

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
