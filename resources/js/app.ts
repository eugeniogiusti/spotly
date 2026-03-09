import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { i18nVue, loadLanguageAsync } from 'laravel-vue-i18n';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';
import { initializeTheme } from '@/composables/useAppearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const locale = (props.initialPage.props as { locale?: string }).locale ?? 'en';
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18nVue, {
                lang: locale,
                resolve: (lang: string) =>
                    import(`../../lang/php_${lang}.json`),
            })
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// Switch i18n language on every Inertia navigation when locale changes
router.on('navigate', (event) => {
    const locale = (event.detail.page.props as { locale?: string }).locale;
    if (locale) {
        loadLanguageAsync(locale);
    }
});

// This will set light / dark mode on page load...
initializeTheme();
