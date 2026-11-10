(function () {
    'use strict';

    const config = window.SatsetWebPushConfig;
    if (!config || !config.publicKey) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const subscriptionVersionKey = 'satset-web-push-subscription-version';

    function browserName() {
        const ua = navigator.userAgent;
        if (/Edg\//.test(ua)) return 'Microsoft Edge';
        if (/OPR\//.test(ua)) return 'Opera';
        if (/CriOS|Chrome\//.test(ua)) return 'Google Chrome';
        if (/FxiOS|Firefox\//.test(ua)) return 'Mozilla Firefox';
        if (/Safari\//.test(ua)) return 'Safari';
        return 'browser ini';
    }

    function isIos() {
        return /iPad|iPhone|iPod/.test(navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    }

    function isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;
    }

    function base64UrlToUint8Array(value) {
        const padding = '='.repeat((4 - value.length % 4) % 4);
        const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = window.atob(base64);
        return Uint8Array.from([...raw].map(char => char.charCodeAt(0)));
    }

    async function saveSubscription(subscription) {
        const json = subscription.toJSON();
        const response = await fetch(config.subscribeUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                endpoint: json.endpoint,
                keys: json.keys,
                content_encoding: window.PushManager?.supportedContentEncodings?.[0] || 'aes128gcm',
            }),
        });

        if (!response.ok) {
            throw new Error('Subscription tidak dapat disimpan di server SatSet.');
        }
    }

    async function removeStoredSubscription(subscription) {
        if (!subscription?.endpoint || !config.unsubscribeUrl) return;

        try {
            await fetch(config.unsubscribeUrl, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ endpoint: subscription.endpoint }),
            });
        } catch (error) {
            console.warn('Subscription lama belum dapat dihapus dari SatSet:', error);
        }
    }

    async function ensureSubscription(registration) {
        let subscription = await registration.pushManager.getSubscription();
        let refreshed = false;

        if (subscription && localStorage.getItem(subscriptionVersionKey) !== config.subscriptionVersion) {
            await removeStoredSubscription(subscription);
            await subscription.unsubscribe();
            subscription = null;
            refreshed = true;
        }

        if (!subscription) {
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: base64UrlToUint8Array(config.publicKey),
            });
            refreshed = true;
        }
        await saveSubscription(subscription);
        localStorage.setItem(subscriptionVersionKey, config.subscriptionVersion);
        return { subscription, refreshed };
    }

    async function showActivationNotification(registration) {
        await registration.showNotification('Notifikasi SatSet aktif', {
            body: 'Browser ini siap menerima pembaruan tiket dan approval SatSet.',
            icon: '/assets/images/satset-icon-192.png',
            badge: '/assets/images/k_favicon_32x.png',
            tag: 'satset-push-activation',
            data: { url: '/ticket/tiket-saya' },
        });
    }

    async function waitForSwal() {
        for (let attempt = 0; attempt < 30; attempt++) {
            if (window.Swal) return window.Swal;
            await new Promise(resolve => setTimeout(resolve, 100));
        }
        return null;
    }

    async function waitUntilNoAlert(Swal) {
        for (let attempt = 0; attempt < 20 && Swal.isVisible(); attempt++) {
            await new Promise(resolve => setTimeout(resolve, 500));
        }
    }

    async function showUnsupported(message) {
        const Swal = await waitForSwal();
        if (!Swal) return;
        await waitUntilNoAlert(Swal);
        await Swal.fire({
            icon: 'warning',
            title: 'Notifikasi browser belum tersedia',
            html: message,
            confirmButtonText: 'Saya mengerti',
            confirmButtonColor: '#e31b23',
        });
    }

    async function showIosInstallGuide() {
        const Swal = await waitForSwal();
        if (!Swal) return;
        await waitUntilNoAlert(Swal);
        await Swal.fire({
            icon: 'info',
            title: 'Pasang SatSet ke Home Screen',
            html: 'Agar notifikasi tetap masuk saat Safari tidak dibuka, tekan <b>Share</b>, pilih <b>Add to Home Screen</b>, lalu buka SatSet dari ikon tersebut.',
            confirmButtonText: 'Saya mengerti',
            confirmButtonColor: '#e31b23',
        });
    }

    async function showBlocked() {
        const Swal = await waitForSwal();
        if (!Swal) return;
        await waitUntilNoAlert(Swal);

        await Swal.fire({
                icon: 'warning',
                title: 'Notifikasi SatSet diblokir',
                html: `Jika ingin menerima notifikasi, buka pengaturan situs di <b>${browserName()}</b> dan ubah <b>Notifications</b> menjadi <b>Allow/Izinkan</b>.`,
                confirmButtonText: 'Mengerti',
                confirmButtonColor: '#e31b23',
        });
    }

    async function requestPermission(registration) {
        const Swal = await waitForSwal();
        if (!Swal) return;
        await waitUntilNoAlert(Swal);

        const result = await Swal.fire({
            icon: 'info',
            title: 'Aktifkan notifikasi SatSet',
            html: 'Aktifkan notifikasi browser untuk menerima pembaruan permintaan, status, penugasan, dan approval.',
            confirmButtonText: 'Aktifkan sekarang',
            confirmButtonColor: '#e31b23',
            showCancelButton: true,
            cancelButtonText: 'Nanti saja',
            showLoaderOnConfirm: true,
            preConfirm: async () => {
                try {
                    const permission = await Notification.requestPermission();
                    if (permission === 'granted') {
                        await ensureSubscription(registration);
                        return { enabled: true };
                    }
                    return { enabled: false, permission };
                } catch (error) {
                    Swal.showValidationMessage(error.message || 'Notifikasi belum dapat diaktifkan.');
                    return false;
                }
            },
        });

        if (result.value?.enabled === true) {
            await Swal.fire({
                icon: 'success',
                title: 'Notifikasi aktif',
                text: 'Notifikasi SatSet akan tetap masuk meskipun tab tidak sedang aktif.',
                timer: 1800,
                showConfirmButton: false,
            });
        } else if (Notification.permission === 'denied') {
            await showBlocked();
        }
    }

    async function initialize(userRequested = false) {
        if (!window.isSecureContext && location.hostname !== 'localhost') {
            if (userRequested) await showUnsupported('Web Push hanya dapat berjalan melalui koneksi HTTPS.');
            return;
        }

        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
            if (userRequested) await showUnsupported(`Versi ${browserName()} ini belum mendukung Web Push. Gunakan Chrome, Edge, Firefox, atau Safari versi terbaru.`);
            return;
        }

        if (isIos() && !isStandalone()) {
            if (userRequested) await showIosInstallGuide();
            return;
        }

        if (!userRequested && Notification.permission !== 'granted') return;

        const registration = await navigator.serviceWorker.register(config.serviceWorkerUrl, { scope: '/' });
        await navigator.serviceWorker.ready;

        if (Notification.permission === 'granted') {
            const result = await ensureSubscription(registration);
            if (result.refreshed) {
                await showActivationNotification(registration);
            }
        } else if (Notification.permission === 'denied') {
            if (userRequested) await showBlocked();
        } else if (userRequested) {
            await requestPermission(registration);
        }
    }

    window.addEventListener('load', () => {
        const enableButton = document.querySelector('[data-enable-web-push]');
        if (enableButton) {
            enableButton.classList.remove('d-none');
            enableButton.addEventListener('click', event => {
                event.stopPropagation();
                initialize(true).catch(error => {
                    console.error('SatSet Web Push initialization failed:', error);
                    showUnsupported('Notifikasi belum dapat diaktifkan. Muat ulang halaman atau hubungi administrator SatSet.');
                });
            });
        }
        setTimeout(() => initialize().catch(async error => {
            console.error('SatSet Web Push initialization failed:', error);
        }), 800);
    });
})();
