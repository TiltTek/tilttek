/**
 * The update a Mercure hub pushes for a table, read from the data of its message: the table's identifier, the number
 * of moves, the phase, the round, the seats that may act and whether the game is over; null for anything else.
 */
export function readUpdate(data) {
    let update;

    try {
        update = JSON.parse(data);
    } catch {
        return null;
    }

    if (
        update === null ||
        typeof update !== 'object' ||
        typeof update.table !== 'string' ||
        !Number.isInteger(update.sequence) ||
        typeof update.phase !== 'string' ||
        !Number.isInteger(update.round) ||
        !Array.isArray(update.to_act) ||
        !update.to_act.every((seat) => typeof seat === 'string') ||
        typeof update.over !== 'boolean'
    ) {
        return null;
    }

    return update;
}

/**
 * Whether an update tells of a table other than the one the page shows: a later move, or a move taken back, which
 * lowers the number of moves.
 */
export function isNewer(update, sequence) {
    return update !== null && update.sequence !== sequence;
}
