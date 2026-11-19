/**
 * The countdown of a game on the page, kept by the browser between two readings of the server: the time left is
 * read from the state's `data-remaining`, written as text in its `<time>` element each second, and said at chosen
 * marks — every ten minutes, then each minute of the last five.
 */
export function clockText(seconds) {
    const left = Math.max(0, seconds);
    const hours = Math.floor(left / 3600);
    const minutes = Math.floor((left % 3600) / 60);
    const rest = String(left % 60).padStart(2, '0');

    return hours > 0 ? `${hours}:${String(minutes).padStart(2, '0')}:${rest}` : `${minutes}:${rest}`;
}

export function isMark(seconds) {
    if (seconds <= 0 || 0 !== seconds % 60) {
        return false;
    }

    const minutes = seconds / 60;

    return minutes <= 5 || 0 === minutes % 10;
}

export class Countdown {
    constructor(root, onMark) {
        this.root = root;
        this.onMark = onMark;
        this.timer = null;
    }

    start() {
        this.stop();
        const state = this.root.querySelector('.tilttek-escape-room__state');

        if (null === state || 'true' === state.dataset.paused || '' !== (state.dataset.outcome ?? '')) {
            return;
        }

        this.remaining = Number.parseInt(state.dataset.remaining ?? '0', 10);
        this.timer = setInterval(() => this.tick(), 1000);
    }

    tick() {
        this.remaining = Math.max(0, this.remaining - 1);
        const clock = this.root.querySelector('.tilttek-escape-room__clock');

        if (null !== clock) {
            clock.textContent = clockText(this.remaining);
            clock.setAttribute('datetime', `PT${this.remaining}S`);
        }

        if (isMark(this.remaining)) {
            this.onMark(this.remaining / 60);
        }

        if (0 === this.remaining) {
            this.stop();
        }
    }

    stop() {
        if (null !== this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    }
}
