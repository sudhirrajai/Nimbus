// resources/js/app.js
import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { formatDate, formatDateTime, formatTime, getPanelTimezone, setPanelTimezone } from './Utils/date';

const appName = import.meta.env.VITE_APP_NAME || 'Nimbus';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        if (props.initialPage?.props?.timezone) {
            setPanelTimezone(props.initialPage.props.timezone);
        }

        const vueApp = createApp({ render: () => h(App, props) });
        vueApp.config.globalProperties.$formatDate = formatDate;
        vueApp.config.globalProperties.$formatDateTime = formatDateTime;
        vueApp.config.globalProperties.$formatTime = formatTime;
        vueApp.config.globalProperties.$panelTimezone = getPanelTimezone;

        return vueApp
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});