import { Controller } from '@hotwired/stimulus';
import { isNewer, readUpdate } from './lib/updates.js';

/**
 * Reads the view of the reader's seat from the view endpoint, then again at each update the Mercure hub pushes on the
 * table's topic, and dispatches it as the `view` event, `game-table:view` with the endpoint's answer as its detail; the
 * application renders the view itself. The number of moves, the phase, the round and the seats that may act are shown
 * in their targets as text. The update carries identifiers only; the view is read from the server, which checks who
 * reads it.
 *
 *     <section data-controller="tilttek--game-engine-bundle--game-table"
 *              data-tilttek--game-engine-bundle--game-table-view-url-value="/game-tables/table:42"
 *              data-tilttek--game-engine-bundle--game-table-hub-url-value="https://hub.example/.well-known/mercure?topic=…"
 *              data-tilttek--game-engine-bundle--game-table-sequence-value="12">
 *         <div data-tilttek--game-engine-bundle--game-table-target="view"></div>
 *         <p data-tilttek--game-engine-bundle--game-table-target="status" role="status"></p>
 *     </section>
 */
export default class extends Controller {
    static targets = ['view', 'status', 'moves', 'phase', 'round', 'toAct'];
    static values = {
        viewUrl: String,
        hubUrl: { type: String, default: '' },
        sequence: { type: Number, default: -1 },
        failedMessage: { type: String, default: 'The table cannot be read just now.' },
    };

    connect() {
        this.read();

        if (this.hubUrlValue !== '' && typeof EventSource !== 'undefined') {
            this.source = new EventSource(this.hubUrlValue, { withCredentials: true });
            this.source.onmessage = (message) => {
                if (isNewer(readUpdate(message.data), this.sequenceValue)) {
                    this.read();
                }
            };
        }
    }

    disconnect() {
        this.source?.close();
        this.source = undefined;
    }

    async read() {
        this.busy(true);

        try {
            const response = await fetch(this.viewUrlValue, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const body = await response.json();

            if (!response.ok) {
                this.say(body.detail ?? this.failedMessageValue);

                return;
            }

            this.sequenceValue = body.sequence;
            this.show('moves', String(body.sequence));
            this.show('phase', body.phase);
            this.show('round', String(body.round));
            this.show('toAct', body.to_act.join(', '));
            this.say('');
            this.dispatch('view', { detail: body });
        } catch {
            this.say(this.failedMessageValue);
        } finally {
            this.busy(false);
        }
    }

    show(target, text) {
        const name = `has${target.charAt(0).toUpperCase()}${target.slice(1)}Target`;

        if (this[name]) {
            this[`${target}Target`].textContent = text;
        }
    }

    busy(busy) {
        if (this.hasViewTarget) {
            this.viewTarget.setAttribute('aria-busy', busy ? 'true' : 'false');
        }
    }

    say(text) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = text;
        }
    }
}
