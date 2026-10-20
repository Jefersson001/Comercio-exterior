const sidebar = document.querySelector('[data-sidebar]');
const sidebarOverlay = document.querySelector('[data-sidebar-overlay]');
const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
const dashboardContent = document.querySelector('[data-dashboard-content]');

if (sidebar && sidebarOverlay && sidebarToggle && dashboardContent) {
    const sidebarPreference = document.documentElement.dataset.sidebarState;
    const desktop = window.matchMedia('(min-width: 1024px)');
    let desktopOpen = sidebarPreference ? sidebarPreference === 'open' : true;
    let isSidebarOpen = desktop.matches && desktopOpen;

    const updateSidebar = () => {
        document.documentElement.dataset.sidebarState = isSidebarOpen ? 'open' : 'closed';
        sidebar.classList.toggle('-translate-x-full', ! isSidebarOpen);
        sidebar.classList.toggle('lg:translate-x-0', isSidebarOpen);
        dashboardContent.classList.toggle('lg:pl-72', isSidebarOpen);
        sidebarOverlay.classList.toggle('hidden', ! isSidebarOpen);
        sidebarToggle.setAttribute('aria-expanded', String(isSidebarOpen));
    };

    const rememberSidebar = () => {
        try {
            if (desktop.matches) {
                desktopOpen = isSidebarOpen;
                localStorage.setItem('dashboard-sidebar', isSidebarOpen ? 'open' : 'closed');
            }
        } catch {
            // Keep the toggle usable when browser storage is unavailable.
        }
        updateSidebar();
    };

    sidebarToggle.addEventListener('click', () => {
        isSidebarOpen = ! isSidebarOpen;
        rememberSidebar();
    });

    sidebarOverlay.addEventListener('click', () => {
        isSidebarOpen = false;
        rememberSidebar();
        sidebarToggle.focus();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isSidebarOpen) {
            isSidebarOpen = false;
            rememberSidebar();
            sidebarToggle.focus();
        }
    });

    desktop.addEventListener('change', () => {
        isSidebarOpen = desktop.matches && desktopOpen;
        updateSidebar();
    });

    updateSidebar();
}

