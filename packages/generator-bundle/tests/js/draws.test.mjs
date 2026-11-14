import { test } from 'node:test';
import assert from 'node:assert/strict';
import { attributionText, drawUrl, resultTexts } from '../../assets/src/lib/draws.js';

test('the address of a draw carries its count and its uniqueness', () => {
    assert.equal(drawUrl('/api/generators/name.elf', 3, true), '/api/generators/name.elf?count=3&unique=1');
    assert.equal(drawUrl('/api/generators/name.elf', 1, false), '/api/generators/name.elf?count=1');
    assert.equal(drawUrl('/api/g?x=1', 2, false), '/api/g?x=1&count=2');
    assert.equal(drawUrl('https://example.com/api/g', 2, true), 'https://example.com/api/g?count=2&unique=1');
});

test('each result is written as text', () => {
    assert.deepEqual(resultTexts(['Aelis', { name: 'The Boar', keeper: 'Odile' }, { layout: 'square' }, null, 3]), ['Aelis', 'The Boar', '{"layout":"square"}', 'null', '3']);
    assert.deepEqual(resultTexts([]), []);
});

test('the attributions are written in one line', () => {
    assert.equal(
        attributionText([
            { source: 'Old tables', licence: 'CC BY 4.0', attribution: 'Tables by A. Author' },
            { source: 'A grammar' },
            { licence: 'CC0-1.0' },
            { source: 'Notes', licence: 'MIT' },
        ]),
        'Tables by A. Author (CC BY 4.0); A grammar; CC0-1.0; Notes (MIT)',
    );
    assert.equal(attributionText([]), '');
    assert.equal(attributionText(undefined), '');
});
