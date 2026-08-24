import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import AppLayout from './Layouts/AppLayout.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Dashboard Keuangan';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),

    resolve: async (name) => {
        const page = await resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        );

        // Halaman auth memakai layout polos; sisanya memakai shell aplikasi.
        page.default.layout ??= name.startsWith('Auth/') ? undefined : AppLayout;

        return page;
    },

    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },

    progress: {
        color: '#059669',
        showSpinner: false,
    },
});
