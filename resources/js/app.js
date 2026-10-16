import './bootstrap';

import { createInertiaApp, Link } from '@inertiajs/vue3';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    withApp(app) {
        app.use(ZiggyVue).component('Link', Link);
    },
    progress: {
        color: '#1fadae',
        delay: 150,
    },
});
