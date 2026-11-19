/** The DOM event the kit's Celebration plays on, dispatched on the window. */
export const CELEBRATE = 'celebrate';

/** The effects the kit's Celebration draws. */
export const EFFECTS = ['confetti', 'fireworks'];

/**
 * What the server answered to an opening, kept only when it holds together: the day, whether this request opened the
 * box first, whether to celebrate, and the HTML of the opened box. Anything else is null, and the page falls back to
 * sending the form.
 */
export function answerOf(json) {
    if (null === json || 'object' !== typeof json) {
        return null;
    }

    const { day, first, celebrate, html } = json;

    if (
        !Number.isInteger(day) ||
        day < 1 ||
        'boolean' !== typeof first ||
        'boolean' !== typeof celebrate ||
        'string' !== typeof html ||
        '' === html
    ) {
        return null;
    }

    return { day, first, celebrate, html };
}

/** The reason a refused opening gives, or the message of the page when it gives none. */
export function refusalOf(json, fallback) {
    return null !== json && 'object' === typeof json && 'string' === typeof json.error && '' !== json.error
        ? json.error
        : fallback;
}

/**
 * The detail of the `celebrate` event for a box opened first: the message, and the effect when it is one the kit
 * draws; null when the answer does not ask for a celebration.
 */
export function celebrationOf(answer, message, effect) {
    if (null === answer || !answer.first || !answer.celebrate) {
        return null;
    }

    return EFFECTS.includes(effect) ? { message, effect } : { message };
}

/** The single element an HTML fragment holds, or null when it holds none or several. */
export function elementOf(html, document) {
    const template = document.createElement('template');
    template.innerHTML = html.trim();

    return 1 === template.content.childElementCount ? template.content.firstElementChild : null;
}
