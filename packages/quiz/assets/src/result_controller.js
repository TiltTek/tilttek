import { Controller } from '@hotwired/stimulus';
import { eventsOf, firstTime } from './lib/once.js';

/**
 * Says that a player's run through a quiz is over: on connect, it dispatches the DOM event `quiz:completed` on the
 * window, then `quiz:perfect` for a perfect run and `quiz:team-won` when the player's team won, their detail naming the
 * quiz, the run, whether it is perfect, the team and whether it is a practice play, once per run in a browser tab, so
 * that coming back to the page does not play it again. The kit's Celebration listens
 * with `data-action="quiz:completed@window->celebration#play"`; anything else may listen too.
 */
export default class extends Controller {
    static values = {
        quiz: String,
        run: String,
        completed: Boolean,
        perfect: Boolean,
        team: String,
        teamWon: Boolean,
        practice: Boolean,
    };

    connect() {
        const events = eventsOf(this.completedValue, this.perfectValue, this.teamWonValue);

        if (events.length === 0 || !firstTime(this.storage(), `tilttek-quiz-completed:${this.runValue}`)) {
            return;
        }

        for (const name of events) {
            this.dispatch(name, {
                prefix: 'quiz',
                target: window,
                detail: {
                    quiz: this.quizValue,
                    run: this.runValue,
                    perfect: this.perfectValue,
                    team: this.teamValue,
                    practice: this.practiceValue,
                },
            });
        }
    }

    storage() {
        try {
            return window.sessionStorage;
        } catch {
            return null;
        }
    }
}
