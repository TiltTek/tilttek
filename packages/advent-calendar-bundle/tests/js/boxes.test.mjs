import { test } from 'node:test';
import assert from 'node:assert/strict';
import { answerOf, CELEBRATE, celebrationOf, EFFECTS, elementOf, refusalOf } from '../../assets/src/lib/boxes.js';

const answer = (extra = {}) => ({ day: 3, first: true, celebrate: true, html: '<li id="box-3"></li>', ...extra });

test('an answer that holds together is kept, field by field', () => {
    assert.deepEqual(answerOf(answer({ extra: 1 })), { day: 3, first: true, celebrate: true, html: '<li id="box-3"></li>' });
    assert.deepEqual(answerOf(answer({ day: 1, first: false, celebrate: false })), { day: 1, first: false, celebrate: false, html: '<li id="box-3"></li>' });
});

test('an answer that does not hold together is null', () => {
    for (const bad of [null, 'box', 3, answer({ day: 0 }), answer({ day: 1.5 }), answer({ day: '3' }), answer({ first: 'yes' }), answer({ celebrate: 1 }), answer({ html: '' }), answer({ html: 7 })]) {
        assert.equal(answerOf(bad), null);
    }
});

test('a refusal gives its reason, or the message of the page', () => {
    assert.equal(refusalOf({ error: 'Opens tomorrow.' }, 'Failed'), 'Opens tomorrow.');
    assert.equal(refusalOf({ error: '' }, 'Failed'), 'Failed');
    assert.equal(refusalOf({ error: 4 }, 'Failed'), 'Failed');
    assert.equal(refusalOf(null, 'Failed'), 'Failed');
    assert.equal(refusalOf('error', 'Failed'), 'Failed');
});

test('only a first opening the server asks to celebrate is celebrated, with an effect the kit draws', () => {
    assert.equal(CELEBRATE, 'celebrate');
    assert.deepEqual(EFFECTS, ['confetti', 'fireworks']);
    assert.deepEqual(celebrationOf(answerOf(answer()), 'Opened!', 'fireworks'), { message: 'Opened!', effect: 'fireworks' });
    assert.deepEqual(celebrationOf(answerOf(answer()), 'Opened!', 'confetti'), { message: 'Opened!', effect: 'confetti' });
    assert.deepEqual(celebrationOf(answerOf(answer()), 'Opened!', 'sparks'), { message: 'Opened!' });
    assert.deepEqual(celebrationOf(answerOf(answer()), 'Opened!', ''), { message: 'Opened!' });
    assert.equal(celebrationOf(answerOf(answer({ first: false })), 'Opened!', 'confetti'), null);
    assert.equal(celebrationOf(answerOf(answer({ celebrate: false })), 'Opened!', 'confetti'), null);
    assert.equal(celebrationOf(null, 'Opened!', 'confetti'), null);
});

test('a fragment gives its single element, or null', () => {
    const element = { tag: 'li' };
    const fakeDocument = (count) => ({
        createElement: () => ({
            set innerHTML(html) {
                this.html = html;
            },
            content: { childElementCount: count, firstElementChild: element },
        }),
    });

    assert.equal(elementOf('  <li></li>  ', fakeDocument(1)), element);
    assert.equal(elementOf('<li></li><li></li>', fakeDocument(2)), null);
    assert.equal(elementOf('text', fakeDocument(0)), null);
});
