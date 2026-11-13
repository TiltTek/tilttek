/**
 * Whether the key was marked in the storage already, and marks it: an event said once per browser tab. Storage may be
 * refused, by a private window or the site's settings: the event is then said every time.
 */
export function firstTime(storage, key) {
    try {
        if (storage?.getItem(key) === '1') {
            return false;
        }

        storage?.setItem(key, '1');
    } catch {
        return true;
    }

    return true;
}
