import { test } from 'node:test';
import assert from 'node:assert/strict';
import { entriesUrl, entryText, entryView, isEntry, newer } from '../../assets/src/lib/entries.js';

const entry = (position, extra = {}) => ({ position, table: 'table:7', roll: 'abc', roller: null, results: [], ...extra });

test('the address of the missed entries carries the last position', () => {
    assert.equal(entriesUrl('/dice-tower/table:7/entries', 12), '/dice-tower/table:7/entries?after=12');
    assert.equal(entriesUrl('/entries?locale=fr', 0), '/entries?locale=fr&after=0');
});

test('only an entry with a position and results is an entry', () => {
    assert.equal(isEntry(entry(1)), true);
    assert.equal(isEntry(entry(0)), false);
    assert.equal(isEntry(entry(1.5)), false);
    assert.equal(isEntry({ position: 3 }), false);
    assert.equal(isEntry(null), false);
    assert.equal(isEntry('entry'), false);
});

test('the entries after the last one, each once, in order', () => {
    const entries = [entry(9), entry(4), entry(7), entry(9), entry(2), { position: 8 }, null];

    assert.deepEqual(
        newer(entries, 4).map((item) => item.position),
        [7, 9],
    );
    assert.deepEqual(
        newer(entries, 0).map((item) => item.position),
        [2, 4, 7, 9],
    );
    assert.deepEqual(newer([], 0), []);
});

test('an entry in words, as the template writes it', () => {
    const view = entryView(
        entry(5, {
            roller: 'member:1',
            results: [
                {
                    expression: '2d6kh1',
                    total: 4,
                    output: 4,
                    sets: [{ notation: '2d6kh1', die: null, dice: [{ value: 4, face: null, kept: true }, { value: 2, face: null, kept: false }] }],
                },
                {
                    expression: '2d{coin}',
                    total: null,
                    output: ['heads', 3],
                    sets: [{ notation: '2d{coin}', die: 'coin', dice: [{ value: 1, face: 'heads', kept: true }, { value: 0, face: 'joker', kept: true }] }],
                },
                { expression: '1d{gem}', total: null, output: 'red', sets: [{ die: 'gem', dice: [{ value: 2, face: 'red', kept: 'yes' }] }] },
                { expression: '6', total: null, output: 6 },
                { total: 'x', output: null, sets: 'none' },
            ],
        }),
        { coin: { heads: 'Heads', tails: 'Tails' } },
        { 'member:1': 'Aelis' },
    );

    assert.deepEqual(view, {
        position: 5,
        roller: 'member:1',
        name: 'Aelis',
        results: [
            { expression: '2d6kh1', total: 4, text: null, dice: [{ label: '4', kept: true }, { label: '2', kept: false }] },
            { expression: '2d{coin}', total: null, text: 'heads, 3', dice: [{ label: 'Heads', kept: true }, { label: '0', kept: true }] },
            { expression: '1d{gem}', total: null, text: 'red', dice: [{ label: '2', kept: false }] },
            { expression: '6', total: null, text: null, dice: [] },
            { expression: '', total: null, text: null, dice: [] },
        ],
    });
    assert.equal(entryText(view, 'not counted'), 'Aelis: 2d6kh1 = 4 (4, 2 (not counted)); 2d{coin} : heads, 3 (Heads, 0); 1d{gem} : red (2 (not counted)); 6; ');
});

test('a roller the page does not name, or nobody', () => {
    assert.equal(entryView(entry(1, { roller: 'member:2' }), {}, { 'member:1': 'Aelis' }).name, null);
    assert.equal(entryView(entry(1, { roller: 3 }), {}, { 3: 'Brom' }).roller, null);
    assert.equal(entryView(entry(1), {}, { toString: 'x' }).name, null);
    assert.equal(entryText(entryView(entry(1, { results: [{ expression: '1d4', total: 3, output: 3, sets: [] }] })), 'x'), '1d4 = 3');
});
