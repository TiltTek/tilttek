import { test } from 'node:test';
import assert from 'node:assert/strict';
import { drawUrl, resultTexts } from '../../assets/src/lib/draws.js';

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
