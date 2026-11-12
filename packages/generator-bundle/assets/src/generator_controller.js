import { Controller } from '@hotwired/stimulus';
import { drawUrl, resultTexts } from './lib/draws.js';

/**
 * Asks the draw API for new results of a generator and shows them in place of the old ones, each as a list item
 * written as text; a status element says when the draw fails or finds fewer results than asked.
 *
 *     <div data-controller="tilttek--generator-bundle--generator"
 *          data-tilttek--generator-bundle--generator-url-value="/api/generators/name.elf"
 *          data-tilttek--generator-bundle--generator-count-value="5">
 *         <ul data-tilttek--generator-bundle--generator-target="results"></ul>
 *         <p data-tilttek--generator-bundle--generator-target="status" role="status"></p>
 *         <button data-action="tilttek--generator-bundle--generator#draw">Again</button>
 *     </div>
 */
export default class extends Controller {
    static targets = ['results', 'status'];
    static values = {
        url: String,
        count: { type: Number, default: 1 },
        unique: { type: Boolean, default: true },
        shortMessage: { type: String, default: 'Fewer results than asked were found.' },
    };

    async draw() {
        this.element.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(drawUrl(this.urlValue, this.countValue, this.uniqueValue), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const body = await response.json();

            if (!response.ok) {
                this.say(body.detail ?? response.statusText);

                return;
            }

            this.resultsTarget.replaceChildren(
                ...resultTexts(body.results).map((text) => {
                    const item = document.createElement('li');
                    item.textContent = text;

                    return item;
                }),
            );
            this.say(body.short ? this.shortMessageValue : '');
        } catch (error) {
            this.say(error.message);
        } finally {
            this.element.removeAttribute('aria-busy');
        }
    }

    say(text) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = text;
        }
    }
}
