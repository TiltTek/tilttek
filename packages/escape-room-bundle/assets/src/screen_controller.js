import { Controller } from '@hotwired/stimulus';
import { Countdown } from './countdown.js';

/**
 * The hint screen of a game. When the Mercure hub tells it the game moved — or every few seconds without a hub —, it
 * reads the state again and replaces the page's copy only when its version changed, so a celebration plays once. Each
 * hint or message shown is said in the live region as a whole sentence, the time left at chosen marks, a pause in
 * words. It dispatches `tilttek-escape-room:moved` on its element at each new state, and `tilttek-escape-room:finished`
 * when the game ends, with `detail.outcome` (`escaped`, `time-up` or `abandoned`).
 *
 *     <main data-controller="tilttek--escape-room--screen"
 *           data-tilttek--escape-room--screen-hub-value="https://hub.example/.well-known/mercure?match=…"
 *           data-tilttek--escape-room--screen-state-url-value="/escape-room/screen/g-1/state"
 *           data-tilttek--escape-room--screen-poll-value="5">
 *         <div data-tilttek--escape-room--screen-target="state">…</div>
 *         <p role="status" aria-live="polite" data-tilttek--escape-room--screen-target="status"></p>
 *     </main>
 */
export default class extends Controller {
    static targets = ['state', 'status'];
    static values = {
        hub: String,
        stateUrl: String,
        poll: { type: Number, default: 5 },
        pausedText: { type: String, default: '' },
        leftText: { type: String, default: '{minutes}' },
    };

    connect() {
        this.countdown = new Countdown(this.stateTarget, (minutes) =>
            this.say(this.leftTextValue.replace('{minutes}', String(minutes))),
        );
        this.countdown.start();

        if ('' !== this.hubValue && 'undefined' !== typeof EventSource) {
            this.source = new EventSource(this.hubValue, { withCredentials: true });
            this.source.addEventListener('message', () => this.refresh());
            this.source.addEventListener('open', () => this.refresh());

            return;
        }

        this.poller = setInterval(() => this.refresh(), Math.max(1, this.pollValue) * 1000);
    }

    disconnect() {
        this.countdown?.stop();
        this.source?.close();
        this.source = null;
        clearInterval(this.poller);
    }

    async refresh() {
        let response;

        try {
            response = await fetch(this.stateUrlValue, {
                headers: { Accept: 'text/html' },
                credentials: 'same-origin',
            });
        } catch {
            return;
        }

        if (!response.ok) {
            return;
        }

        const next = new DOMParser()
            .parseFromString(await response.text(), 'text/html')
            .querySelector('.tilttek-escape-room__state');
        const current = this.stateTarget.querySelector('.tilttek-escape-room__state');

        if (null === next || next.dataset.version === current?.dataset.version) {
            return;
        }

        const ended = '' === (current?.dataset.outcome ?? '') && '' !== (next.dataset.outcome ?? '');
        this.stateTarget.replaceChildren(document.importNode(next, true));
        this.countdown.start();
        this.announce(next);
        this.dispatch('moved', { prefix: 'tilttek-escape-room' });

        if (ended) {
            clearInterval(this.poller);
            this.dispatch('finished', { prefix: 'tilttek-escape-room', detail: { outcome: next.dataset.outcome } });
        }
    }

    announce(state) {
        const words = [
            state.querySelector('.tilttek-escape-room__outcome'),
            state.querySelector('.tilttek-escape-room__paused'),
            state.querySelector('.tilttek-escape-room__hint'),
            state.querySelector('.tilttek-escape-room__message'),
        ]
            .filter((element) => null !== element)
            .map((element) => element.textContent.replace(/\s+/g, ' ').trim());

        if (words.length > 0) {
            this.say(words.join(' '));
        }
    }

    say(text) {
        if (this.hasStatusTarget && '' !== text) {
            this.statusTarget.textContent = text;
        }
    }
}
