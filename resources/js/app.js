import './bootstrap';

import { createInertiaApp, Link } from '@inertiajs/vue3';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

// From the server at runtime (APP_NAME), not baked into the build: the assets are built locally
const appName = document.querySelector('meta[name="application-name"]')?.content || 'meSync';

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
