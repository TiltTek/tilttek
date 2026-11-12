import { Controller } from '@hotwired/stimulus';
import { announcementOf, count, expression } from './lib/dice.js';

/**
 * The accessible dice form: shows the expression the choice makes, steps how many dice with its two buttons, and
 * announces in its live region the result the form was answered with and each roll of a dice tower on the same page —
 * the `tilttek--dice-tower--tower:drop` events. Nothing is read aloud unless the viewer ticks the box for it, and the
 * roll moves only when the viewer did not ask for reduced motion.
 *
 *     <form data-controller="tilttek--accessible-dice--dice"
 *           data-action="submit->tilttek--accessible-dice--dice#roll
 *                        tilttek--dice-tower--tower:drop@window->tilttek--accessible-dice--dice#announce">
 *         …
 *         <div role="status" aria-live="polite" data-tilttek--accessible-dice--dice-target="live"></div>
 *     </form>
 */
export default class extends Controller {
    static targets = ['die', 'count', 'modifier', 'expression', 'speak', 'live'];
    static values = {
        labels: { type: Object, default: {} },
        sentences: { type: Object, default: {} },
        delay: { type: Number, default: 100 },
    };

    connect() {
        this.update();

        if (this.hasLiveTarget && '' !== this.liveTarget.textContent.trim()) {
            this.say(this.liveTarget.textContent.trim());
        }
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    update() {
        if (!this.hasExpressionTarget || !this.hasCountTarget) {
            return;
        }

        const chosen = this.dieTargets.find((die) => die.checked);
        const modifier = this.hasModifierTarget ? this.modifierTarget.value : 0;
        this.expressionTarget.textContent = expression(
            this.countTarget.value,
            chosen?.dataset.notation ?? '',
            modifier,
            this.countTarget.max,
        );
    }

    fewer() {
        this.step(-1);
    }

    more() {
        this.step(1);
    }

    step(by) {
        this.countTarget.value = String(
            count(count(this.countTarget.value, this.countTarget.max) + by, this.countTarget.max),
        );
        this.update();
    }

    roll() {
        if (!this.reducedMotion()) {
            this.element.classList.add('is-rolling');
        }
    }

    announce(event) {
        const text = announcementOf(event.detail, this.sentencesValue, this.labelsValue);

        if ('' !== text) {
            this.say(text);
        }
    }

    say(text) {
        if (!this.hasLiveTarget) {
            return;
        }

        // Emptied first, then written: a screen reader announces the change, even of the same sentence twice.
        this.liveTarget.textContent = '';
        clearTimeout(this.timer);
        this.timer = setTimeout(() => {
            this.liveTarget.textContent = text;
        }, this.delayValue);

        if (this.hasSpeakTarget && this.speakTarget.checked && 'speechSynthesis' in window) {
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = document.documentElement.lang || '';
            window.speechSynthesis.cancel();
            window.speechSynthesis.speak(utterance);
        }
    }

    reducedMotion() {
        return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
    }
}
