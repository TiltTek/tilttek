import { test } from 'node:test';
import assert from 'node:assert/strict';
import { MAX_MODIFIER, announcement, announcementOf, count, expression, fill, viewOf } from '../../assets/src/lib/dice.js';

const sentences = {
    with_dice: '%expression%: %dice%. %outcome%.',
    without_dice: '%expression%: %outcome%.',
    total: 'Total %total%',
    gave: 'Result: %text%',
    nothing: 'No result',
    dropped: '%die% not counted',
    by: '%name% rolled. %roll%',
};

test('how many dice, between one and the most', () => {
    assert.equal(count('3', 10), 3);
    assert.equal(count('0', 10), 1);
    assert.equal(count('-4', 10), 1);
    assert.equal(count('11', 10), 10);
    assert.equal(count('two', 10), 1);
    assert.equal(count('7', ''), 1);
    assert.equal(count('7', '0'), 1);
    assert.equal(count(2.9, 10), 2);
});

test('the expression of a choice, as the server writes it', () => {
    assert.equal(expression('2', 'd6', '1', 100), '2d6+1');
    assert.equal(expression('3', 'd8', '-2', 100), '3d8-2');
    assert.equal(expression('1', 'd{coin}', '0', 100), '1d{coin}');
    assert.equal(expression('', 'd20', '', 100), '1d20');
    assert.equal(expression('500', 'd4', '5000', 100), `100d4+${MAX_MODIFIER}`);
    assert.equal(expression('1', 'd4', '-5000', 100), `1d4-${MAX_MODIFIER}`);
    assert.equal(MAX_MODIFIER, 1000);
});

test('a sentence with its placeholders replaced', () => {
    assert.equal(fill('Total %total%', { '%total%': 8 }), 'Total 8');
    assert.equal(fill('%a% and %a%, %b%', { '%a%': 'x' }), 'x and x, %b%');
    assert.equal(fill(undefined, { '%a%': 'x' }), '');
});

test('a roll of a tower in one sentence', () => {
    const view = {
        name: 'Aelis',
        results: [
            { expression: '2d6kh1', total: 4, text: null, dice: [{ label: '4', kept: true }, { label: '2', kept: false }] },
            { expression: '1d{coin}', total: null, text: 'heads', dice: [{ label: 'Heads', kept: true }] },
            { expression: '7', total: 7, text: null, dice: [] },
            { expression: '[]', total: null, text: '', dice: 'none' },
            { total: 'x', text: null },
        ],
    };

    assert.equal(
        announcement(view, sentences),
        'Aelis rolled. 2d6kh1: 4, 2 not counted. Total 4. 1d{coin}: Heads. Result: heads. 7: Total 7. []: No result. : No result.',
    );
    assert.equal(announcement({ ...view, name: '' }, sentences).startsWith('2d6kh1'), true);
    assert.equal(announcement({ name: null, results: [] }, sentences), '');
    assert.equal(announcement(null, sentences), '');
    assert.equal(announcement({ results: [{ expression: '1', total: null, text: null, dice: [] }] }, {}), '');
});

test('an entry the tower pushes, its faces named by the labels of the page', () => {
    const entry = {
        position: 3,
        results: [
            {
                expression: '2d{coin}',
                total: 1,
                output: 1,
                sets: [{ die: 'coin', dice: [{ value: 1, face: 'heads', kept: true }, { value: 0, face: 'joker', kept: 'yes' }] }],
            },
            { expression: '1L[a,b]', total: null, output: ['a', 2], sets: 'none' },
            { output: null, sets: [{ die: 7, dice: [{ value: 5, face: 'heads' }] }, { die: 'coin', dice: 'none' }] },
        ],
    };
    const labels = { coin: { heads: 'Pile' } };

    assert.deepEqual(viewOf(entry, labels, 'Brom'), {
        name: 'Brom',
        results: [
            { expression: '2d{coin}', total: 1, text: '1', dice: [{ label: 'Pile', kept: true }, { label: '0', kept: false }] },
            { expression: '1L[a,b]', total: null, text: 'a, 2', dice: [] },
            { expression: '', total: null, text: '', dice: [{ label: '5', kept: false }] },
        ],
    });
    assert.deepEqual(viewOf(null), { name: null, results: [] });
    assert.equal(
        announcementOf({ entry, view: { name: 'Brom' } }, sentences, labels),
        'Brom rolled. 2d{coin}: Pile, 0 not counted. Total 1. 1L[a,b]: Result: a, 2. : 5 not counted. No result.',
    );
});

test('the detail of a tower event: its entry, else its view, else its text', () => {
    const view = { name: null, results: [{ expression: '1d4', total: 3, text: null, dice: [{ label: '3', kept: true }] }] };

    assert.equal(announcementOf({ entry: { results: [] }, view }, sentences), '');
    assert.equal(announcementOf({ view }, sentences), '1d4: 3. Total 3.');
    assert.equal(announcementOf({ view: {}, text: 'Aelis: 1d4 = 3' }, sentences), 'Aelis: 1d4 = 3');
    assert.equal(announcementOf({ text: 4 }, sentences), '');
    assert.equal(announcementOf(undefined, sentences), '');
    assert.equal(announcementOf({ entry: { results: [{ expression: '1', total: 1, output: 1 }] } }, sentences), '1: Total 1.');
});
