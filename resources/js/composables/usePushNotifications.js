import { onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Mendaftarkan service worker dan mengelola izin Web Push
 * (berjalan di Chrome/Edge/Firefox desktop maupun Android; iOS >= 16.4
 * setelah aplikasi ditambahkan ke Home Screen).
 */
export function usePushNotifications() {
    const page = usePage();
    const supported = ref(false);
    const permission = ref('default');
    const subscribed = ref(false);
    const busy = ref(false);
    const error = ref(null);

    const publicKey = () => page.props.push?.vapid_public_key ?? '';

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = window.atob(base64);

        return Uint8Array.from([...raw].map((char) => char.charCodeAt(0)));
    }

    async function registration() {
        return navigator.serviceWorker.register('/sw.js', { scope: '/' });
    }

    async function refresh() {
        if (!supported.value) {
            return;
        }

        permission.value = Notification.permission;

        const reg = await navigator.serviceWorker.ready;
        subscribed.value = Boolean(await reg.pushManager.getSubscription());
    }

    async function subscribe() {
        error.value = null;

        if (!supported.value) {
            error.value = 'Browser ini belum mendukung push notification.';

            return;
        }

        if (!publicKey()) {
            error.value = 'VAPID key belum dikonfigurasi di server.';

            return;
        }

        busy.value = true;

        try {
            permission.value = await Notification.requestPermission();

            if (permission.value !== 'granted') {
                error.value = 'Izin notifikasi ditolak.';

                return;
            }

            const reg = await registration();
            await navigator.serviceWorker.ready;

            const subscription =
                (await reg.pushManager.getSubscription()) ??
                (await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(publicKey()),
                }));

            const payload = subscription.toJSON();

            await fetch('/push-subscriptions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({
                    endpoint: payload.endpoint,
                    keys: payload.keys,
                    content_encoding: 'aes128gcm',
                }),
            });

            subscribed.value = true;
        } catch (exception) {
            error.value = exception?.message ?? 'Gagal mengaktifkan notifikasi.';
        } finally {
            busy.value = false;
        }
    }

    async function unsubscribe() {
        busy.value = true;

        try {
            const reg = await navigator.serviceWorker.ready;
            const subscription = await reg.pushManager.getSubscription();

            if (subscription) {
                await fetch('/push-subscriptions', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ endpoint: subscription.endpoint }),
                });

                await subscription.unsubscribe();
            }

            subscribed.value = false;
        } finally {
            busy.value = false;
        }
    }

    onMounted(async () => {
        supported.value =
            'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

        if (!supported.value) {
            return;
        }

        // Registrasi service worker bisa ditolak (konteks non-secure, browser
        // dalam mode terbatas). Kegagalan di sini tidak boleh merusak halaman.
        try {
            await registration();
            await refresh();
        } catch (exception) {
            supported.value = false;
            error.value = 'Service worker tidak dapat didaftarkan pada browser ini.';
        }
    });

    return { supported, permission, subscribed, busy, error, subscribe, unsubscribe };
}
