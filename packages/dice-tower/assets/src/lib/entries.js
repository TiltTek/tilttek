/**
 * What the tower's controller does with the entries, apart from the page: their order, their words, and the address
 * of those missed.
 */

/**
 * The address of the entries after a position.
 */
export function entriesUrl(url, after) {
    return `${url}${url.includes('?') ? '&' : '?'}after=${encodeURIComponent(String(after))}`;
}

/**
 * Whether a value has the shape of an entry the tower pushes.
 */
export function isEntry(entry) {
    return (
        null !== entry &&
        'object' === typeof entry &&
        Number.isInteger(entry.position) &&
        entry.position > 0 &&
        Array.isArray(entry.results)
    );
}

/**
 * The entries after a position, each once, in the order of the tower.
 */
export function newer(entries, last) {
    const seen = new Set();

    return entries
        .filter((entry) => isEntry(entry) && entry.position > last)
        .filter((entry) => {
            if (seen.has(entry.position)) {
                return false;
            }

            seen.add(entry.position);

            return true;
        })
        .sort((a, b) => a.position - b.position);
}

function text(output) {
    const words = (Array.isArray(output) ? output : [output]).map((item) => String(item)).join(', ');

    return '' === words ? null : words;
}

/**
 * What the overlay shows of an entry, as its template does: the roller's name when the page knows it, and for each
 * instruction its expression, its total or what it gave, and every die by the label of its face or its value; an
 * instruction that gave nothing has no text.
 */
export function entryView(entry, faces = {}, names = {}) {
    const roller = 'string' === typeof entry.roller ? entry.roller : null;

    return {
        position: entry.position,
        roller,
        name: null !== roller && Object.hasOwn(names, roller) ? String(names[roller]) : null,
        results: entry.results.map((result) => {
            const total = Number.isInteger(result.total) ? result.total : null;
            const dice = [];

            for (const set of Array.isArray(result.sets) ? result.sets : []) {
                const labels = 'string' === typeof set.die && Object.hasOwn(faces, set.die) ? faces[set.die] : {};

                for (const die of Array.isArray(set.dice) ? set.dice : []) {
                    const face =
                        'string' === typeof die.face && Object.hasOwn(labels, die.face) ? labels[die.face] : null;
                    dice.push({ label: null === face ? String(die.value) : String(face), kept: true === die.kept });
                }
            }

            return {
                expression: String(result.expression ?? ''),
                total,
                text: null !== total || Number.isInteger(result.output) ? null : text(result.output ?? ''),
                dice,
            };
        }),
    };
}

/**
 * An entry in one sentence: "Aelis: 2d6+1 = 8 (4, 3)", a die that did not count followed by its word.
 */
export function entryText(view, dropped) {
    const results = view.results.map((result) => {
        const outcome = null !== result.total ? ` = ${result.total}` : null !== result.text ? ` : ${result.text}` : '';
        const dice = result.dice.map((die) => (die.kept ? die.label : `${die.label} (${dropped})`)).join(', ');

        return `${result.expression}${outcome}${'' === dice ? '' : ` (${dice})`}`;
    });

    return `${null === view.name ? '' : `${view.name}: `}${results.join('; ')}`;
}
