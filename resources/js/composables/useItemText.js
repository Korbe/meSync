import { nextTick } from 'vue';

/*
 * Item descriptions of invoices and offers: textareas grow with their content,
 * "- " at the start of a line is a bullet point (• in the PDF).
 */

const bulletPattern = /^\s*[-•*]\s/;

// Grow textareas with their content (incl. wrapped lines), so no scrollbar appears
const autosize = (el) => {
    el.style.height = 'auto';
    el.style.height = `${el.scrollHeight + 2}px`;
};

export const vAutosize = {
    mounted: (el) => nextTick(() => autosize(el)),
    updated: (el) => autosize(el),
};

const setCursor = (textarea, position) => nextTick(() => {
    textarea.focus();
    textarea.setSelectionRange(position, position);
});

// Enter inside a bullet line starts the next bullet; Enter on an empty bullet ends the list
export const continueBullet = (event, item) => {
    if (event.shiftKey || event.isComposing) return;

    const textarea = event.target;
    const text = item.description;
    const cursor = textarea.selectionStart;
    const lineStart = text.lastIndexOf('\n', cursor - 1) + 1;
    const line = text.slice(lineStart, cursor);

    if (!bulletPattern.test(line)) return;
    event.preventDefault();

    if (line.replace(bulletPattern, '').trim() === '') {
        item.description = text.slice(0, lineStart) + text.slice(cursor);
        setCursor(textarea, lineStart);
        return;
    }

    item.description = text.slice(0, cursor) + '\n- ' + text.slice(cursor);
    setCursor(textarea, cursor + 3);
};

export const addBullet = (event, item) => {
    const textarea = event.currentTarget.closest('div').parentElement.querySelector('textarea');
    const text = item.description.replace(/\s+$/, '');

    item.description = text ? `${text}\n- ` : '- ';
    setCursor(textarea, item.description.length);
};
