<!-- Begin Header -->
@php
    $headerUser = auth()->user();
    $headerName = $headerUser->name ?? 'Guest';
    $headerInitials = collect(explode(' ', trim($headerName)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
        ->implode('') ?: 'G';
@endphp
<header class="app-header" id="appHeader">
    <div class="container-fluid w-100">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <div class="d-inline-flex align-items-center gap-5">
                    <button type="button" class="vertical-toggle btn btn-light-light text-muted icon-btn fs-5 rounded-pill" id="toggleSidebar">
                        <i class="bi bi-arrow-bar-left header-icon"></i>
                    </button>
                    <button type="button" class="horizontal-toggle btn btn-light-light text-muted icon-btn fs-5 rounded-pill d-none" id="toggleHorizontal">
                        <i class="ri-menu-2-line header-icon"></i>
                    </button>
                    <div class="header-dropdown d-flex align-items-center">
                        
                    </div>
                </div>
            </div>
            <div class="flex-shrink-0 d-flex align-items-center gap-1">
                
                <div class="dark-mode-btn" id="toggleMode">
                    <button class="btn header-btn active" id="lightModeBtn">
                        <i class="bi bi-brightness-high"></i>
                    </button>
                    <button class="btn header-btn" id="darkModeBtn">
                        <i class="bi bi-moon-stars"></i>
                    </button>
                </div>
                <div class="dropdown pe-dropdown-mega" id="satsetNotifications" data-list-url="{{ route('notifications.index') }}" data-read-url="{{ route('notifications.read', ['id' => '__ID__']) }}">
                    <button class="btn header-btn position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifikasi">
                        <i class="bi bi-bell"></i>
                        <span data-notification-badge class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none"></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end dropdown-mega-md header-dropdown-menu pe-noti-dropdown-menu p-0" style="width: min(400px, 92vw); max-width: 92vw;">
                        <div class="p-3 border-bottom">
                            <h6 class="d-flex align-items-center mb-0">Notifikasi <span class="badge bg-danger rounded-pill ms-2" data-notification-count>0</span></h6>
                        </div>
                        <div data-notification-list style="max-height: 420px; overflow-y: auto;">
                            <div class="p-3 text-muted small">Memuat notifikasi...</div>
                        </div>
                    </div>
                </div>
                
                <div class="dropdown pe-dropdown-mega d-none d-md-block">
                    <button class="header-profile-btn btn gap-1 text-start" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="header-btn btn position-relative">
                            <span class="d-flex align-items-center justify-content-center rounded-circle bg-primary text-white fw-bold" style="width: 36px; height: 36px; font-size: 13px;">
                                {{ $headerInitials }}
                            </span>
                            <span class="position-absolute translate-middle badge border border-light rounded-circle bg-success"><span class="visually-hidden">unread messages</span></span>
                        </span>
                        <div class="d-none d-lg-block pe-2">
                            <span class="d-block mb-0 fs-13 fw-semibold">{{ $headerName }}</span>
                        </div>
                    </button>
                    <div class="dropdown-menu dropdown-mega-sm header-dropdown-menu p-3">
                        <div class="border-bottom pb-2 mb-2 d-flex align-items-center gap-2">
                            <span class="avatar-md rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center">
                                {{ $headerInitials }}
                            </span>
                            <div>
                                <a href="javascript:void(0)">
                                    <h6 class="mb-0 lh-base">{{ $headerName }}</h6>
                                </a>
                                <p class="mb-0 fs-13 text-muted">{{ $headerUser->email ?? 'Guest' }}</p>
                            </div>
                        </div>
                        <ul class="list-unstyled mb-1 border-bottom pb-1">
                            <li><a class="dropdown-item" href="javascript:void(0)"><i class="bi bi-person me-1"></i> View Profile</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0)"><i class="bi bi-gear me-1"></i> Settings</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0)"><i class="bi bi-award me-1"></i> Subscription</a></li>
                        </ul>
                        <ul class="list-unstyled mb-1 border-bottom pb-1">
                            <li><a class="dropdown-item" href="javascript:void(0)"><i class="bi bi-clock me-1"></i> ChangLog</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0)"><i class="bi bi-people me-1"></i> Team</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0)"><i class="bi bi-headset me-1"></i> Support</a></li>
                        </ul>
                        <ul class="list-unstyled mb-0">
                            <li>
                                <form method="POST" action="{{ route('logout') }}" class="m-0">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="bi bi-box-arrow-right me-1"></i> Sign Out
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
<!-- END Header -->

<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('satsetNotifications');
    if (!container) return;
    const list = container.querySelector('[data-notification-list]');
    const count = container.querySelector('[data-notification-count]');
    const badge = container.querySelector('[data-notification-badge]');
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    let loading = false;

    function safeUrl(value) {
        if (!value) return null;
        try {
            const url = new URL(value, window.location.origin);
            return url.origin === window.location.origin && ['http:', 'https:'].includes(url.protocol) ? url.href : null;
        } catch (_) { return null; }
    }

    async function refreshNotifications() {
        if (loading) return;
        loading = true;
        try {
            const response = await fetch(container.dataset.listUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error('Notifikasi tidak tersedia');
            const data = await response.json();
            const unread = Number(data.unread_count) || 0;
            count.textContent = unread;
            badge.textContent = unread > 99 ? '99+' : unread;
            badge.classList.toggle('d-none', unread === 0);
            list.replaceChildren();
            if (!data.notifications.length) {
                const empty = document.createElement('div');
                empty.className = 'p-3 text-muted small';
                empty.textContent = 'Belum ada notifikasi.';
                list.appendChild(empty);
            }
            data.notifications.forEach(notification => {
                const url = safeUrl(notification.url);
                const item = document.createElement(url ? 'a' : 'button');
                item.className = 'd-block w-100 text-start text-decoration-none border-0 border-bottom p-3 ' + (notification.is_read ? 'bg-white' : 'bg-light');
                if (url) item.href = url;
                else item.type = 'button';
                const title = document.createElement('div');
                title.className = 'fw-bold text-dark';
                title.textContent = notification.title || 'Notifikasi SatSet';
                const message = document.createElement('div');
                message.className = 'small text-secondary mt-1';
                message.textContent = notification.message || '';
                const time = document.createElement('div');
                time.className = 'small text-muted mt-1';
                const date = new Date(notification.created_at);
                time.textContent = Number.isNaN(date.getTime()) ? '' : date.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
                item.append(title, message, time);
                item.addEventListener('click', async event => {
                    event.preventDefault();
                    try {
                        await fetch(container.dataset.readUrl.replace('__ID__', encodeURIComponent(notification.id)), {
                            method: 'POST', credentials: 'same-origin',
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token || '' }
                        });
                    } finally {
                        if (url) window.location.assign(url);
                        else refreshNotifications();
                    }
                });
                list.appendChild(item);
            });
        } catch (_) {
            const error = document.createElement('div');
            error.className = 'p-3 text-muted small';
            error.textContent = 'Notifikasi belum dapat dimuat. Coba buka kembali.';
            list.replaceChildren(error);
        } finally { loading = false; }
    }
    container.querySelector('[data-bs-toggle="dropdown"]').addEventListener('click', refreshNotifications);
    refreshNotifications();
    window.setInterval(refreshNotifications, 60000);
});
</script>

<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 bg-transparent">
            <div class="d-flex justify-content-between align-items-center bg-body">
                <div class="d-flex align-items-center border-0 px-3">
                    <i class="bi bi-search me-2"></i>
                    <input class="d-flex w-full py-3 bg-transparent border-0 focus-ring" placeholder="Search Here.." autocomplete="off" autocorrect="off" spellcheck="false" aria-autocomplete="list" role="combobox" aria-expanded="true" type="text">
                </div>
                <button type="button" class="btn-close pe-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-body mt-4">
                <p class="font-normal mb-2">Searching For...</p>
                <span class="badge bg-light-subtle border text-body">Analytics <i class="ri-close-line"></i></span>
                <span class="badge bg-light-subtle border text-body">Project <i class="ri-close-line"></i></span>
                <span class="badge bg-light-subtle border text-body">Eccomerce <i class="ri-close-line"></i></span>
                <span class="badge bg-light-subtle border text-body">CRM <i class="ri-close-line"></i></span>
                <span class="badge bg-light-subtle border text-body">Logistics <i class="ri-close-line"></i></span>
                <span class="badge bg-light-subtle border text-body">Academy <i class="ri-close-line"></i></span>
            </div>
        </div>
    </div>
</div>
