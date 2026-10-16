import { onMounted, onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * True while a list loads new data from the server (search, filters, pages).
 * `typing()` marks the time between a keystroke and the debounced request,
 * so the indicator shows up right away and not only after the delay.
 */
export function useListLoading() {
    const loading = ref(false);
    let stopStart = null;
    let stopFinish = null;

    onMounted(() => {
        stopStart = router.on('start', () => loading.value = true);
        stopFinish = router.on('finish', () => loading.value = false);
    });

    onUnmounted(() => {
        stopStart?.();
        stopFinish?.();
    });

    const typing = () => loading.value = true;

    return { loading, typing };
}
