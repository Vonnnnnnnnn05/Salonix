<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$staffMenu = [
    ['label' => 'Dashboard', 'icon' => 'fa-solid fa-gauge-high', 'href' => '../staff/dashboard.php', 'file' => 'dashboard.php'],
    ['label' => 'Today Appointments', 'icon' => 'fa-solid fa-calendar-day', 'href' => '../staff/today_appointments.php', 'file' => 'today_appointments.php'],
    ['label' => 'Ongoing Services', 'icon' => 'fa-solid fa-scissors', 'href' => '../staff/ongoing_services.php', 'file' => 'ongoing_services.php'],
    ['label' => 'Service Status', 'icon' => 'fa-solid fa-wave-square', 'href' => '../staff/service_status.php', 'file' => 'service_status.php'],
    ['label' => 'Client Monitoring', 'icon' => 'fa-solid fa-user-clock', 'href' => '../staff/client_monitoring.php', 'file' => 'client_monitoring.php'],
    ['label' => 'Profile', 'icon' => 'fa-solid fa-user', 'href' => '../staff/profile.php', 'file' => 'profile.php'],
    ['label' => 'Logout', 'icon' => 'fa-solid fa-right-from-bracket', 'href' => '../staff/logout.php', 'file' => 'logout.php'],
];
?>
<button id="staffSidebarToggle" type="button" class="lg:hidden fixed top-4 left-4 z-50 inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white text-[#2D2D2D] shadow-md transition hover:bg-[#FDF4F5]" aria-label="Open staff sidebar">
    <i class="fa-solid fa-bars"></i>
</button>

<div id="staffSidebarOverlay" class="fixed inset-0 z-40 hidden bg-black/30 lg:hidden"></div>

<aside id="staffSidebar" class="fixed inset-y-0 left-0 z-40 w-72 -translate-x-full border-r border-[#E5E7EB] bg-white px-4 py-6 shadow-lg transition-transform duration-300 lg:translate-x-0">
    <div class="mb-8 flex items-center gap-3 rounded-2xl bg-[#F5D0D7] px-4 py-3">
        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white text-[#B76E79] shadow-sm">
            <i class="fa-solid fa-user-tie text-lg"></i>
        </div>
        <div>
            <h1 class="text-base font-semibold text-[#2D2D2D]">SALONIX Staff</h1>
            <p class="text-xs text-[#2D2D2D]/70">Service Workspace</p>
        </div>
    </div>

    <nav class="space-y-1" aria-label="Staff Sidebar Navigation">
        <?php foreach ($staffMenu as $item): ?>
            <?php $active = $currentPage === $item['file']; ?>
            <a href="<?php echo htmlspecialchars($item['href']); ?>" class="<?php echo $active ? 'bg-[#B76E79] text-white shadow-md' : 'text-[#2D2D2D] hover:bg-[#FDF4F5]'; ?> group flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition duration-200">
                <i class="<?php echo htmlspecialchars($item['icon']); ?> w-5 text-center"></i>
                <span><?php echo htmlspecialchars($item['label']); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>

<script>
(() => {
    const sidebar = document.getElementById('staffSidebar');
    const overlay = document.getElementById('staffSidebarOverlay');
    const toggle = document.getElementById('staffSidebarToggle');

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
})();
</script>
