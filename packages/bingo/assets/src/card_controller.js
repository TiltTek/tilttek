import { Controller } from '@hotwired/stimulus';
import { firstTime } from './lib/once.js';

/**
 * Says that a bingo card won: on connect, when the card carries the pattern it won with, it dispatches the DOM event
 * `bingo:won` on the window, its detail naming the game, the card's serial and the pattern, once per card in a browser
 * tab, so that coming back to the page does not play it again. The kit's Celebration listens with
 * `data-action="bingo:won@window->celebration#play"`; anything else may listen too.
 */
export default class extends Controller {
    static values = { game: String, serial: Number, pattern: String };

    connect() {
        if (
            this.patternValue === '' ||
            !firstTime(this.storage(), `tilttek-bingo-won:${this.gameValue}:${this.serialValue}`)
        ) {
            return;
        }

        this.dispatch('won', {
            prefix: 'bingo',
            target: window,
            detail: { game: this.gameValue, serial: this.serialValue, pattern: this.patternValue },
        });
    }

    storage() {
        try {
            return window.sessionStorage;
        } catch {
            return null;
        }
    }
}
