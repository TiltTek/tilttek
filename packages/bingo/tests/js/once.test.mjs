import { test } from 'node:test';
import assert from 'node:assert/strict';
import { firstTime } from '../../assets/src/lib/once.js';

const memory = () => {
    const items = new Map();

    return { getItem: (key) => items.get(key) ?? null, setItem: (key, value) => items.set(key, value) };
};

test('a win is said once per storage', () => {
    const storage = memory();

    assert.equal(firstTime(storage, 'game:1'), true);
    assert.equal(firstTime(storage, 'game:1'), false);
    assert.equal(firstTime(storage, 'game:2'), true);
});

test('a refused storage says the win every time', () => {
    const refused = {
        getItem: () => {
            throw new Error('denied');
        },
        setItem: () => {},
    };

    assert.equal(firstTime(refused, 'game:1'), true);
    assert.equal(firstTime(refused, 'game:1'), true);
    assert.equal(firstTime(null, 'game:1'), true);
});
