import { Controller } from '@hotwired/stimulus';
import { Countdown } from './countdown.js';

/**
 * The host's console. Its forms, marked `data-action="tilttek--escape-room--console#send"`, are sent in the
 * background: what was done, or why it was refused, is said in the live region, and the console is read again in
 * place. Without this controller they still work as plain forms. The countdown runs between two readings, and the
 * console is read again when the hub tells it the game moved — a hint the players asked —, or every few seconds
 * without a hub; it is replaced only when the game or its screen changed.
 *
 *     <main data-controller="tilttek--escape-room--console"
 *           data-tilttek--escape-room--console-hub-value="https://hub.example/.well-known/mercure?match=…">
 *         <div data-tilttek--escape-room--console-target="panel">…</div>
 *         <p role="status" aria-live="polite" data-tilttek--escape-room--console-target="status"></p>
 *     </main>
 */
export default class extends Controller {
    static targets = ['panel', 'status'];
    static values = {
        hub: String,
        poll: { type: Number, default: 5 },
        doneText: { type: String, default: '' },
    };

    connect() {
        this.countdown = new Countdown(this.panelTarget, () => {});
        this.countdown.start();

        if ('' !== this.hubValue && 'undefined' !== typeof EventSource) {
            this.source = new EventSource(this.hubValue, { withCredentials: true });
            this.source.addEventListener('message', () => this.reload());

            return;
        }

        this.poller = setInterval(() => this.reload(), Math.max(1, this.pollValue) * 1000);
    }

    disconnect() {
        this.countdown?.stop();
        this.source?.close();
        this.source = null;
        clearInterval(this.poller);
    }

    async send(event) {
        event.preventDefault();
        const form = event.target.closest('form');
        let response;

        try {
            response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
        } catch {
            form.submit();

            return;
        }

        if (422 === response.status) {
            const body = await response.json();
            this.say(body.error ?? '');

            return;
        }

        if (!response.ok) {
            form.submit();

            return;
        }

        this.say(this.doneTextValue);
        await this.reload();
    }

    async reload() {
        let response;

        try {
            response = await fetch(window.location.pathname, {
                headers: { Accept: 'text/html' },
                credentials: 'same-origin',
            });
        } catch {
            return;
        }

        if (!response.ok) {
            return;
        }

        const panel = new DOMParser()
            .parseFromString(await response.text(), 'text/html')
            .querySelector('[data-tilttek--escape-room--console-target="panel"]');
        const focused = document.activeElement?.closest('form')?.action;
        const version = (root) => root?.querySelector('.tilttek-escape-room__state')?.dataset.version;

        if (null !== panel && version(panel) !== version(this.panelTarget)) {
            this.panelTarget.replaceChildren(
                ...Array.from(panel.childNodes, (node) => document.importNode(node, true)),
            );
            this.countdown.start();
            this.panelTarget.querySelector(`form[action="${focused}"] button`)?.focus();
        }
    }

    say(text) {
        if (this.hasStatusTarget && '' !== text) {
            this.statusTarget.textContent = text;
        }
    }
}
