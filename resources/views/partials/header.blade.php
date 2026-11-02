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
<style>
    #satsetNotifications .satset-notifications-menu { width: min(400px, 92vw); max-width: 92vw; border: 1px solid #edf0f5; border-radius: 16px; box-shadow: 0 18px 50px rgba(15, 23, 42, .16); overflow: hidden; background: #fff; }
    #satsetNotifications .satset-notifications-head { padding: 18px 20px 14px; border-bottom: 1px solid #edf0f5; }
    #satsetNotifications .satset-notifications-title { font-size: 16px; font-weight: 750; color: #172033; margin: 0; }
    #satsetNotifications .satset-notifications-subtitle { font-size: 12px; color: #8792a2; margin-top: 3px; }
    #satsetNotifications .satset-notifications-actions { display: flex; gap: 8px; margin-top: 14px; }
    #satsetNotifications .satset-notifications-action { background: #f4f6f9; border: 0; border-radius: 8px; color: #455468; font-size: 12px; font-weight: 700; padding: 7px 10px; }
    #satsetNotifications .satset-notifications-action:hover { background: #e9edf3; }
    #satsetNotifications .satset-notifications-action.clear { color: #d92d3c; background: #fff1f2; }
    #satsetNotifications .satset-notifications-action:disabled { opacity: .45; cursor: default; }
    #satsetNotifications .satset-notifications-list { max-height: min(420px, 55vh); overflow-y: auto; }
    #satsetNotifications .satset-notification-item { display: flex; align-items: flex-start; gap: 12px; width: 100%; text-align: left; text-decoration: none; background: #fff; border: 0; border-bottom: 1px solid #f1f3f6; padding: 14px 20px; cursor: pointer; }
    #satsetNotifications .satset-notification-item:hover { background: #f8fafc; }
    #satsetNotifications .satset-notification-item.unread { background: #fff8f8; }
    #satsetNotifications .satset-notification-icon { display: flex; flex: 0 0 36px; height: 36px; align-items: center; justify-content: center; border-radius: 11px; color: #e11d2e; background: #ffeded; font-size: 16px; }
    #satsetNotifications .satset-notification-content { flex: 1; min-width: 0; }
    #satsetNotifications .satset-notification-title { color: #243044; font-size: 13px; font-weight: 700; line-height: 1.35; }
    #satsetNotifications .satset-notification-message { color: #617086; font-size: 12px; line-height: 1.5; margin-top: 4px; overflow-wrap: anywhere; }
    #satsetNotifications .satset-notification-time { color: #98a2b1; font-size: 11px; margin-top: 5px; }
    #satsetNotifications .satset-notification-dot { width: 7px; height: 7px; border-radius: 50%; background: #e11d2e; margin-top: 6px; flex: 0 0 7px; }
    #satsetNotifications .satset-notifications-empty { text-align: center; color: #8993a1; font-size: 13px; padding: 32px 20px; }
</style>
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
                <div class="dropdown pe-dropdown-mega" id="satsetNotifications" data-list-url="{{ route('notifications.index') }}" data-read-url="{{ route('notifications.read', ['id' => '__ID__']) }}" data-read-all-url="{{ route('notifications.read-all') }}" data-clear-url="{{ route('notifications.clear') }}">
                    <button class="btn header-btn position-relative" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notifikasi">
                        <i class="bi bi-bell"></i>
                        <span data-notification-badge class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none"></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end dropdown-mega-md header-dropdown-menu pe-noti-dropdown-menu satset-notifications-menu p-0" data-bs-auto-close="outside">
                        <div class="satset-notifications-head">
                            <h6 class="satset-notifications-title">Notifikasi <span class="badge bg-danger rounded-pill ms-1" data-notification-count>0</span></h6>
                            <div class="satset-notifications-subtitle">10 notifikasi terbaru</div>
                            <div class="satset-notifications-actions">
                                <button type="button" class="satset-notifications-action" data-notification-read-all disabled><i class="bi bi-check2-all me-1"></i>Baca semua</button>
                                <button type="button" class="satset-notifications-action clear" data-notification-clear disabled><i class="bi bi-trash3 me-1"></i>Hapus semua</button>
                            </div>
                        </div>
                        <div class="satset-notifications-list" data-notification-list>
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
    const readAll = container.querySelector('[data-notification-read-all]');
    const clearAll = container.querySelector('[data-notification-clear]');
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    let loading = false;
    let updating = false;
    let refreshPending = false;

    function safeUrl(value) {
        if (!value) return null;
        try {
            const url = new URL(value, window.location.origin);
            return url.origin === window.location.origin && ['http:', 'https:'].includes(url.protocol) ? url.href : null;
        } catch (_) { return null; }
    }

    async function refreshNotifications() {
        if (loading || updating) { refreshPending = true; return; }
        loading = true;
        try {
            const response = await fetch(container.dataset.listUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error('Notifikasi tidak tersedia');
            const data = await response.json();
            const unread = Number(data.unread_count) || 0;
            count.textContent = unread;
            badge.textContent = unread > 99 ? '99+' : unread;
            badge.classList.toggle('d-none', unread === 0);
            readAll.disabled = unread === 0;
            clearAll.disabled = data.notifications.length === 0;
            list.replaceChildren();
            if (!data.notifications.length) {
                const empty = document.createElement('div');
                empty.className = 'satset-notifications-empty';
                empty.textContent = 'Belum ada notifikasi.';
                list.appendChild(empty);
            }
            data.notifications.forEach(notification => {
                const url = safeUrl(notification.url);
                const item = document.createElement(url ? 'a' : 'button');
                item.className = 'satset-notification-item' + (notification.is_read ? '' : ' unread');
                if (url) item.href = url;
                else item.type = 'button';
                const title = document.createElement('div');
                title.className = 'satset-notification-title';
                title.textContent = notification.title || 'Notifikasi SatSet';
                const message = document.createElement('div');
                message.className = 'satset-notification-message';
                message.textContent = notification.message || '';
                const time = document.createElement('div');
                time.className = 'satset-notification-time';
                const date = new Date(notification.created_at);
                time.textContent = Number.isNaN(date.getTime()) ? '' : date.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
                const icon = document.createElement('span');
                icon.className = 'satset-notification-icon';
                icon.setAttribute('aria-hidden', 'true');
                const iconSymbol = document.createElement('i');
                iconSymbol.className = 'bi bi-bell';
                icon.appendChild(iconSymbol);
                const content = document.createElement('div');
                content.className = 'satset-notification-content';
                content.append(title, message, time);
                item.append(icon, content);
                if (!notification.is_read) {
                    const dot = document.createElement('span');
                    dot.className = 'satset-notification-dot';
                    dot.setAttribute('aria-label', 'Belum dibaca');
                    item.appendChild(dot);
                }
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
        } finally {
            loading = false;
            if (refreshPending && !updating) { refreshPending = false; refreshNotifications(); }
        }
    }
    async function updateAll(url, method) {
        if (updating) return;
        updating = true;
        readAll.disabled = true;
        clearAll.disabled = true;
        try {
            const response = await fetch(url, { method, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token || '' } });
            if (!response.ok) throw new Error('Gagal memperbarui notifikasi');
        } catch (_) {
            window.alert('Gagal memperbarui notifikasi. Silakan coba lagi.');
        } finally {
            updating = false;
            refreshNotifications();
        }
    }
    readAll.addEventListener('click', event => {
        event.stopPropagation();
        updateAll(container.dataset.readAllUrl, 'POST');
    });
    clearAll.addEventListener('click', async event => {
        event.stopPropagation();
        const confirmed = window.Swal
            ? (await Swal.fire({ title: 'Hapus semua notifikasi?', text: 'Seluruh riwayat notifikasi akun Anda akan dihapus.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, hapus semua', cancelButtonText: 'Batal', confirmButtonColor: '#e11d2e' })).isConfirmed
            : window.confirm('Hapus seluruh riwayat notifikasi akun Anda?');
        if (confirmed) updateAll(container.dataset.clearUrl, 'DELETE');
    });
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
