/**
 * The address of a draw: the API's address of a generator with the count and the uniqueness asked for.
 */
export function drawUrl(url, count, unique) {
    const address = new URL(url, 'http://localhost');
    address.searchParams.set('count', String(count));

    if (unique) {
        address.searchParams.set('unique', '1');
    }

    return url.startsWith('http') ? address.href : address.pathname + address.search;
}

/**
 * The text of each result: a text as it is, a structured result by its `name` field when it has one, otherwise in
 * JSON.
 */
export function resultTexts(results) {
    return results.map((result) => {
        if (typeof result === 'string') {
            return result;
        }

        if (result !== null && typeof result === 'object' && typeof result.name === 'string') {
            return result.name;
        }

        return JSON.stringify(result);
    });
}

/**
 * The attributions of a draw in one line: each its attribution, or its source, then its licence in parentheses,
 * separated by semicolons.
 */
export function attributionText(attributions) {
    return (attributions ?? [])
        .map((attribution) => {
            const credit = attribution.attribution ?? attribution.source ?? '';
            const licence = attribution.licence ?? '';

            if (credit === '') {
                return licence;
            }

            return licence === '' ? credit : `${credit} (${licence})`;
        })
        .join('; ');
}
