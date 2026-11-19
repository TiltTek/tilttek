import { Controller } from '@hotwired/stimulus';
import { answerOf, CELEBRATE, celebrationOf, elementOf, refusalOf } from './lib/boxes.js';

/**
 * The boxes of a calendar. The form of an openable box, marked `data-tilttek-advent-calendar-open`, is sent in the
 * background, so that opening a box neither reloads the page nor loses its place; without this controller it still
 * works as a plain form. The opened box takes the place of the closed one, the focus moves to its title, and the live
 * region says it was opened. A first opening the server asks to celebrate dispatches `celebrate` on the window, which
 * the kit's Celebration plays — drawing nothing where motion is reduced. The controller animates nothing itself.
 *
 *     <ol data-controller="tilttek--advent-calendar--boxes"
 *         data-tilttek--advent-calendar--boxes-opened-value="Box opened."
 *         data-tilttek--advent-calendar--boxes-failed-value="The box could not be opened; reload the page."
 *         data-tilttek--advent-calendar--boxes-celebration-value="Box opened!"
 *         data-tilttek--advent-calendar--boxes-effect-value="confetti">
 *         <li id="tilttek-advent-calendar-box-3">… <form data-tilttek-advent-calendar-open>…</form></li>
 *     </ol>
 *     <p role="status" aria-live="polite" data-tilttek--advent-calendar--boxes-target="status"></p>
 */
export default class extends Controller {
    static targets = ['status'];
    static values = {
        opened: { type: String, default: '' },
        failed: { type: String, default: '' },
        celebration: { type: String, default: '' },
        effect: { type: String, default: '' },
    };

    connect() {
        this.onSubmit = (event) => this.open(event);
        this.element.addEventListener('submit', this.onSubmit);
    }

    disconnect() {
        this.element.removeEventListener('submit', this.onSubmit);
    }

    async open(event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-tilttek-advent-calendar-open')) {
            return;
        }

        event.preventDefault();
        const button = form.querySelector('[type="submit"]');
        button?.setAttribute('disabled', '');

        let response;

        try {
            response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
        } catch {
            button?.removeAttribute('disabled');
            this.say(this.failedValue);

            return;
        }

        const json = await response.json().catch(() => null);

        if (!response.ok) {
            button?.removeAttribute('disabled');
            this.say(refusalOf(json, this.failedValue));

            return;
        }

        const answer = answerOf(json);
        const box = null === answer ? null : elementOf(answer.html, document);

        if (null === box) {
            form.submit();

            return;
        }

        form.closest('li')?.replaceWith(box);
        box.querySelector('[tabindex="-1"]')?.focus();
        this.say(this.openedValue);

        const detail = celebrationOf(answer, this.celebrationValue, this.effectValue);

        if (null !== detail) {
            window.dispatchEvent(new CustomEvent(CELEBRATE, { detail }));
        }
    }

    say(message) {
        if (!this.hasStatusTarget || '' === message) {
            return;
        }

        this.statusTarget.textContent = '';
        window.setTimeout(() => {
            this.statusTarget.textContent = message;
        }, 50);
    }
}
