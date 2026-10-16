import { reactive } from 'vue';

// Shared state of the single confirm dialog rendered by <VConfirmDialog /> in the layout
export const confirmState = reactive({
    show: false,
    title: '',
    message: '',
    confirmText: 'Bestätigen',
    cancelText: 'Abbrechen',
    variant: 'primary',   // primary | danger
    resolve: null,
});

/**
 * Styled replacement for window.confirm():
 *
 *   if (!(await confirm({ title: 'Logo entfernen?', message: '…', confirmText: 'Entfernen', variant: 'danger' }))) return;
 */
export const confirm = (options) => new Promise((resolve) => {
    // A dialog that is still open counts as cancelled
    confirmState.resolve?.(false);

    Object.assign(confirmState, {
        title: '',
        message: '',
        confirmText: 'Bestätigen',
        cancelText: 'Abbrechen',
        variant: 'primary',
        ...options,
        show: true,
        resolve,
    });
});

export const settleConfirm = (result) => {
    confirmState.resolve?.(result);
    confirmState.resolve = null;
    confirmState.show = false;
};

export const useConfirm = () => confirm;
