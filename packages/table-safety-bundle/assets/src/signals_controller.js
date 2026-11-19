import { Controller } from '@hotwired/stimulus';

/**
 * A table's signals on the page. The forms of the signal buttons on the page, marked `data-tilttek-table-safety-signal`,
 * are sent in the background, so that raising a signal neither reloads the page nor loses its place; without this
 * controller they still work as plain forms. Each
 * new signal — raised here, pushed by the hub, or read from the feed after the connection came back — is added once
 * to the list and announced in the live region, by its kind and never by who raised it. A signal marked as seen is
 * updated in place.
 *
 *     <section data-controller="tilttek--table-safety--signals"
 *              data-tilttek--table-safety--signals-hub-value="https://hub.example/.well-known/mercure?match=…"
 *              data-tilttek--table-safety--signals-feed-url-value="/table-safety/table:7/signals"
 *              data-tilttek--table-safety--signals-last-value="2026-11-12T20:00:00+00:00"
 *              data-tilttek--table-safety--signals-kinds-value='{"x-card": "X-card"}'>
 *         <ol data-tilttek--table-safety--signals-target="list"></ol>
 *         <p data-tilttek--table-safety--signals-target="empty" hidden></p>
 *         <p role="status" aria-live="polite" data-tilttek--table-safety--signals-target="status"></p>
 *     </section>
 */
export default class extends Controller {
    static targets = ['list', 'empty', 'status'];
    static values = {
        hub: String,
        feedUrl: String,
        last: String,
        kinds: { type: Object, default: {} },
        seen: { type: String, default: 'seen' },
        sent: { type: String, default: '' },
        offline: { type: String, default: '' },
        limit: { type: Number, default: 100 },
    };

    connect() {
        this.known = new Set(Array.from(this.listTarget.querySelectorAll('li[data-id]'), (item) => item.dataset.id));
        this.forms = Array.from(document.querySelectorAll('form[data-tilttek-table-safety-signal]'));
        this.onSubmit = (event) => this.raise(event);
        for (const form of this.forms) {
            form.addEventListener('submit', this.onSubmit);
        }

        if ('' === this.hubValue || 'undefined' === typeof EventSource) {
            return;
        }

        this.source = new EventSource(this.hubValue, { withCredentials: true });
        this.source.addEventListener('open', () => this.catchUp());
        this.source.addEventListener('error', () => this.say(this.offlineValue));
        this.source.addEventListener('message', (event) => this.receive(event.data));
    }

    disconnect() {
        for (const form of this.forms ?? []) {
            form.removeEventListener('submit', this.onSubmit);
        }
        this.source?.close();
        this.source = null;
    }

    async raise(event) {
        event.preventDefault();
        const form = event.currentTarget;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                form.submit();

                return;
            }

            const body = await response.json();
            this.add(body.signal);
            this.say(this.sentValue);
        } catch {
            form.submit();
        }
    }

    receive(data) {
        try {
            this.add(JSON.parse(data));
        } catch {
            // A message that is not a signal is left out.
        }
    }

    async catchUp() {
        if ('' === this.feedUrlValue) {
            return;
        }

        const url = new URL(this.feedUrlValue, window.location.href);

        if ('' !== this.lastValue) {
            url.searchParams.set('after', this.lastValue);
        }

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

            if (response.ok) {
                const body = await response.json();
                for (const signal of Array.isArray(body.signals) ? body.signals : []) {
                    this.add(signal);
                }
            }
        } catch {
            this.say(this.offlineValue);
        }
    }

    add(signal) {
        if (
            null === signal ||
            'object' !== typeof signal ||
            'string' !== typeof signal.id ||
            'string' !== typeof signal.kind
        ) {
            return;
        }

        const label = this.kindsValue[signal.kind] ?? signal.kind;

        if (this.known.has(signal.id)) {
            const item = this.listTarget.querySelector(`li[data-id="${CSS.escape(signal.id)}"]`);

            if (item && signal.acknowledged_at && !item.classList.contains('is-seen')) {
                item.classList.add('is-seen');
                item.querySelector('form')?.remove();
                item.append(' ', this.seenValue);
            }

            return;
        }

        this.known.add(signal.id);
        this.listTarget.append(this.item(signal, label));
        this.lastValue = signal.raised_at ?? this.lastValue;
        this.say(label);

        while (this.listTarget.children.length > this.limitValue) {
            this.listTarget.firstElementChild.remove();
        }

        if (this.hasEmptyTarget) {
            this.emptyTarget.hidden = true;
        }
    }

    item(signal, label) {
        const item = document.createElement('li');
        item.dataset.id = signal.id;
        const time = document.createElement('time');
        time.dateTime = signal.raised_at ?? '';
        time.textContent = signal.raised_at
            ? new Date(signal.raised_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            : '';
        const kind = document.createElement('strong');
        kind.textContent = label;
        item.append(time, ' ', kind);

        if (signal.acknowledged_at) {
            item.classList.add('is-seen');
            item.append(' ', this.seenValue);
        }

        return item;
    }

    say(text) {
        if (!this.hasStatusTarget || '' === text) {
            return;
        }

        // Emptied first, so that the same words are announced again.
        this.statusTarget.textContent = '';
        window.setTimeout(() => {
            this.statusTarget.textContent = text;
        }, 50);
    }
}
