/**
 * What the accessible dice controller computes, apart from the page: the expression of a choice, and the sentence a
 * roll of a dice tower is announced with.
 */

export const MAX_MODIFIER = 1000;

function whole(value, least, most, fallback) {
    const number = Number.parseInt(String(value), 10);

    return Number.isNaN(number) ? fallback : Math.min(most, Math.max(least, number));
}

/**
 * How many dice, kept between one and the most a choice rolls.
 */
export function count(value, most) {
    return whole(value, 1, Math.max(1, whole(most, 1, Number.MAX_SAFE_INTEGER, 1)), 1);
}

/**
 * The expression of a choice, as the server writes it: `2d6+1`, `1d{coin}`.
 */
export function expression(dice, notation, modifier, most) {
    const plus = whole(modifier, -MAX_MODIFIER, MAX_MODIFIER, 0);

    return `${count(dice, most)}${notation}${0 === plus ? '' : plus > 0 ? `+${plus}` : String(plus)}`;
}

/**
 * A sentence with its placeholders replaced: `fill('Total %total%', { '%total%': 8 })`.
 */
export function fill(sentence, parameters) {
    return Object.entries(parameters).reduce(
        (text, [key, value]) => text.split(key).join(String(value)),
        String(sentence ?? ''),
    );
}

/**
 * A roll of a dice tower in the sentence a screen reader announces, from the view the tower's controller gives: every
 * die by its label, a die that did not count said so, then the total or what the instruction gave; the roller's name
 * first when the page knows it.
 */
export function announcement(view, sentences) {
    const lines = (Array.isArray(view?.results) ? view.results : []).map((result) => {
        const dice = (Array.isArray(result.dice) ? result.dice : []).map((die) =>
            true === die.kept ? String(die.label) : fill(sentences.dropped, { '%die%': die.label }),
        );
        const outcome = Number.isInteger(result.total)
            ? fill(sentences.total, { '%total%': result.total })
            : 'string' === typeof result.text && '' !== result.text
              ? fill(sentences.gave, { '%text%': result.text })
              : String(sentences.nothing ?? '');
        const expression = String(result.expression ?? '');

        return 0 === dice.length
            ? fill(sentences.without_dice, { '%expression%': expression, '%outcome%': outcome })
            : fill(sentences.with_dice, {
                  '%expression%': expression,
                  '%dice%': dice.join(', '),
                  '%outcome%': outcome,
              });
    });
    const roll = lines.join(' ');

    return 'string' === typeof view?.name && '' !== view.name
        ? fill(sentences.by, { '%name%': view.name, '%roll%': roll })
        : roll;
}

/**
 * The view of an entry the tower pushes, each face of a custom die named by the labels of the page: the roller's name
 * as the tower's view gives it, the expression, the total or what was given, and every die.
 */
export function viewOf(entry, labels = {}, name = null) {
    return {
        name,
        results: (Array.isArray(entry?.results) ? entry.results : []).map((result) => {
            const dice = [];

            for (const set of Array.isArray(result.sets) ? result.sets : []) {
                const faces = 'string' === typeof set.die && Object.hasOwn(labels, set.die) ? labels[set.die] : {};

                for (const die of Array.isArray(set.dice) ? set.dice : []) {
                    const face =
                        'string' === typeof die.face && Object.hasOwn(faces, die.face) ? faces[die.face] : null;
                    dice.push({ label: null === face ? String(die.value) : String(face), kept: true === die.kept });
                }
            }

            const output = Array.isArray(result.output) ? result.output : [result.output ?? ''];

            return {
                expression: String(result.expression ?? ''),
                total: Number.isInteger(result.total) ? result.total : null,
                text: output.map((item) => String(item)).join(', '),
                dice,
            };
        }),
    };
}

/**
 * The sentence of the detail of a tower's event: its entry, its faces named by the labels of the page; else its view;
 * else its own text; else nothing.
 */
export function announcementOf(detail, sentences, labels = {}) {
    if (Array.isArray(detail?.entry?.results)) {
        return announcement(viewOf(detail.entry, labels, detail.view?.name ?? null), sentences);
    }

    if (Array.isArray(detail?.view?.results)) {
        return announcement(detail.view, sentences);
    }

    return 'string' === typeof detail?.text ? detail.text : '';
}
