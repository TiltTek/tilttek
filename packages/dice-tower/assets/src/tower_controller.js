import { Controller } from '@hotwired/stimulus';
import { entriesUrl, entryText, entryView, isEntry, newer } from './lib/entries.js';

/**
 * A table's tower on the page: subscribes to the table's topic on the hub, adds each new entry once and in order, and
 * reads from the log what was missed — before the subscription opened, or while it was lost —, holding the live
 * entries back until then. Each new entry is dispatched as `tilttek--dice-tower--tower:drop`, with the entry, its view
 * and its sentence, for an accessible dice interface or any other listener of the page.
 *
 *     <section data-controller="tilttek--dice-tower--tower"
 *              data-tilttek--dice-tower--tower-hub-value="https://hub.example/.well-known/mercure?match=…"
 *              data-tilttek--dice-tower--tower-entries-url-value="/dice-tower/table:7/entries"
 *              data-tilttek--dice-tower--tower-last-value="41">
 *         <ol data-tilttek--dice-tower--tower-target="entries"></ol>
 *         <p data-tilttek--dice-tower--tower-target="empty" hidden></p>
 *         <p data-tilttek--dice-tower--tower-target="status" role="status"></p>
 *     </section>
 */
export default class extends Controller {
    static targets = ['entries', 'empty', 'status'];
    static values = {
        hub: String,
        entriesUrl: String,
        last: { type: Number, default: 0 },
        faces: { type: Object, default: {} },
        names: { type: Object, default: {} },
        dropped: { type: String, default: 'not counted' },
        offline: { type: String, default: '' },
        limit: { type: Number, default: 200 },
    };

    connect() {
        this.held = null;

        if ('' === this.hubValue || 'undefined' === typeof EventSource) {
            return;
        }

        this.source = new EventSource(this.hubValue, { withCredentials: true });
        this.source.addEventListener('open', () => {
            this.say('');
            this.catchUp();
        });
        this.source.addEventListener('error', () => this.say(this.offlineValue));
        this.source.addEventListener('message', (event) => this.receive(event.data));
    }

    disconnect() {
        this.source?.close();
        this.source = null;
    }

    receive(data) {
        let entry;

        try {
            entry = JSON.parse(data);
        } catch {
            return;
        }

        if (null !== this.held) {
            this.held.push(entry);

            return;
        }

        this.add([entry]);
    }

    async catchUp() {
        if ('' === this.entriesUrlValue || null !== this.held) {
            return;
        }

        this.held = [];
        let missed = [];

        try {
            const response = await fetch(entriesUrl(this.entriesUrlValue, this.lastValue), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (response.ok) {
                const body = await response.json();
                missed = Array.isArray(body.entries) ? body.entries : [];
            }
        } catch {
            missed = [];
        } finally {
            const held = this.held;
            this.held = null;
            this.add([...missed, ...held]);
        }
    }

    add(entries) {
        for (const entry of newer(entries.filter(isEntry), this.lastValue)) {
            const view = entryView(entry, this.facesValue, this.namesValue);
            this.lastValue = entry.position;
            this.entriesTarget.append(this.item(view));
            this.dispatch('drop', { detail: { entry, view, text: entryText(view, this.droppedValue) } });
        }

        while (this.entriesTarget.children.length > this.limitValue) {
            this.entriesTarget.firstElementChild.remove();
        }

        if (this.hasEmptyTarget && this.entriesTarget.children.length > 0) {
            this.emptyTarget.hidden = true;
        }
    }

    item(view) {
        const item = document.createElement('li');
        item.className = 'tilttek-dice-tower__entry';
        item.dataset.position = String(view.position);

        if (null !== view.name) {
            const roller = document.createElement('span');
            roller.className = 'tilttek-dice-tower__roller';
            roller.textContent = view.name;
            item.append(roller, ' ');
        }

        for (const result of view.results) {
            const part = document.createElement('span');
            part.className = 'tilttek-dice-tower__result';
            const code = document.createElement('code');
            code.textContent = result.expression;
            part.append(code);

            if (null !== result.total || null !== result.text) {
                const output = document.createElement('output');
                output.textContent = String(null !== result.total ? result.total : result.text);
                part.append(null !== result.total ? ' = ' : ' : ', output);
            }

            if (result.dice.length > 0) {
                const dice = document.createElement('span');
                dice.className = 'tilttek-dice-tower__dice';
                result.dice.forEach((die, index) => {
                    const face = document.createElement('span');
                    face.className = die.kept ? 'tilttek-dice-tower__die' : 'tilttek-dice-tower__die is-dropped';
                    face.textContent = die.kept ? die.label : `${die.label} (${this.droppedValue})`;
                    dice.append(...(0 === index ? [] : [', ']), face);
                });
                part.append(' ', dice);
            }

            item.append(part);
        }

        return item;
    }

    say(text) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = text;
        }
    }
}
