import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';

/**
 * Session timeout: warns shortly before the Laravel session ends and shows when it has ended.
 *
 * Counts from the last response of the server (every request extends the session). The time
 * stamp lives in localStorage, so all tabs share it: a request in one tab extends the
 * session for all of them. Works with time stamps instead of trusting timers, which run late
 * in background tabs and stand still in standby.
 *
 * Module state: survives page changes, Layout mounts the popup on every page.
 */
const ACTIVITY_KEY = 'mesync.session.activity';
const LOGOUT_KEY = 'mesync.session.logout';
const FORM_KEY = 'mesync.session.form';

const state = ref('active');   // active | warning | expired
const remaining = ref(0);       // seconds until the end of the session
const checking = ref(false);
let started = false;
let lastActivity = Date.now();

const settings = () => usePage().props.session;

const readStorage = (key) => {
    try {
        return localStorage.getItem(key);
    } catch {
        return null;
    }
};

const writeStorage = (key, value) => {
    try {
        localStorage.setItem(key, value);
    } catch {
        // Private mode / blocked storage: works per tab only
    }
};

/**
 * The server answered: the session runs again for its full lifetime.
 */
const touch = () => {
    lastActivity = Date.now();
    writeStorage(ACTIVITY_KEY, String(lastActivity));
    // A successful answer means signed in again (or the login page, then no session at all)
    state.value = 'active';
    tick();
};

const expire = () => {
    state.value = 'expired';
    remaining.value = 0;
};

/**
 * At the end: ask the server. With "remember me" it signs back in and the session continues,
 * otherwise the answer is 401.
 */
const check = async () => {
    if (checking.value) return;
    checking.value = true;

    try {
        await axios.get(route('session.keep-alive'), { headers: { Accept: 'application/json' } });
        touch();
    } catch (error) {
        if ([401, 419].includes(error.response?.status)) {
            expire();
        } else {
            // Offline or server error: try again on the next tick
            lastActivity = Date.now() - (settings()?.lifetime ?? 0) * 60_000 + 30_000;
        }
    } finally {
        checking.value = false;
    }
};

const tick = () => {
    const session = settings();
    // Signed out (login page): nothing runs, signing in starts fresh
    if (!session) {
        state.value = 'active';
        return;
    }
    if (state.value === 'expired') return;

    // Another tab may have extended the session
    lastActivity = Math.max(lastActivity, Number(readStorage(ACTIVITY_KEY)) || 0);

    const lifetime = session.lifetime * 60;
    // Never warn for more than half of the session
    const warning = Math.min(session.warning * 60, lifetime / 2);
    remaining.value = Math.max(0, Math.round(lifetime - (Date.now() - lastActivity) / 1000));

    if (remaining.value <= 0) {
        check();
    } else if (remaining.value <= warning && !session.remembered) {
        state.value = 'warning';
    } else {
        state.value = 'active';
    }
};

/**
 * Keep the entries of the open forms before leaving the page, to offer them again after
 * signing in. Passwords and files are never stored.
 */
const saveForms = () => {
    const fields = [...document.querySelectorAll('main input, main textarea, main select')]
        .filter((el) => (el.id || el.name) && !['password', 'file', 'hidden', 'submit', 'button'].includes(el.type))
        .map((el) => ({
            key: el.id ? `#${el.id}` : `[name="${el.name}"]`,
            value: ['checkbox', 'radio'].includes(el.type) ? el.checked : el.value,
        }));

    if (fields.length === 0) return;

    try {
        sessionStorage.setItem(FORM_KEY, JSON.stringify({ url: location.pathname + location.search, at: Date.now(), fields }));
    } catch {
        // Nothing to do: the entries are lost like before
    }
};

/**
 * Sign in again: back to this page afterwards (the server remembers it as intended URL).
 */
const signIn = () => {
    saveForms();
    window.location.reload();
};

/**
 * Entries kept for this page within the last day.
 */
const savedForm = () => {
    try {
        const saved = JSON.parse(sessionStorage.getItem(FORM_KEY) ?? 'null');

        return saved && saved.url === location.pathname + location.search && Date.now() - saved.at < 86_400_000 ? saved : null;
    } catch {
        return null;
    }
};

const discardSavedForm = () => {
    try {
        sessionStorage.removeItem(FORM_KEY);
    } catch {
        // ignore
    }
};

/**
 * Fill the kept entries back in. The events make v-model take the values over.
 */
const restoreSavedForm = () => {
    const saved = savedForm();
    discardSavedForm();
    if (!saved) return 0;

    let restored = 0;
    for (const field of saved.fields) {
        const el = document.querySelector(`main ${field.key}`);
        if (!el) continue;

        if (['checkbox', 'radio'].includes(el.type)) {
            if (el.checked !== field.value) el.click();
        } else {
            el.value = field.value;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }
        restored++;
    }

    return restored;
};

const extend = () => check();

/**
 * Logging out in one tab ends the session in all of them.
 */
const announceLogout = () => writeStorage(LOGOUT_KEY, String(Date.now()));

const logout = () => {
    announceLogout();
    router.post(route('logout'));
};

const start = () => {
    if (started) return;
    started = true;

    lastActivity = Math.max(Date.now(), Number(readStorage(ACTIVITY_KEY)) || 0);
    writeStorage(ACTIVITY_KEY, String(lastActivity));

    // Every answer of the server extends the session
    router.on('success', touch);
    router.on('error', touch);   // validation errors: the server answered too
    axios.interceptors.response.use((response) => {
        touch();
        return response;
    });

    // An expired session or CSRF token: the popup instead of the error page
    router.on('httpException', (event) => {
        if ([401, 419].includes(event.detail.response.status)) {
            event.preventDefault();
            expire();
        }
    });

    window.addEventListener('storage', (event) => {
        if (event.key === ACTIVITY_KEY) tick();
        if (event.key === LOGOUT_KEY && settings()) expire();
    });

    // Back from the background or standby: the timers may have stood still
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') tick();
    });

    setInterval(tick, 1000);
    tick();
};

export function useSessionTimeout() {
    return {
        state,
        remaining,
        checking,
        enabled: computed(() => !!settings()),
        start,
        extend,
        logout,
        signIn,
        announceLogout,
        savedForm,
        restoreSavedForm,
        discardSavedForm,
    };
}
