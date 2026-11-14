import { test } from 'node:test';
import assert from 'node:assert/strict';
import { eventsOf, firstTime } from '../../assets/src/lib/once.js';

const memory = () => {
    const items = new Map();

    return { getItem: (key) => items.get(key) ?? null, setItem: (key, value) => items.set(key, value) };
};

test('a run is said once per storage', () => {
    const storage = memory();

    assert.equal(firstTime(storage, 'run:1'), true);
    assert.equal(firstTime(storage, 'run:1'), false);
    assert.equal(firstTime(storage, 'run:2'), true);
});

test('a refused storage says the run every time', () => {
    const refused = {
        getItem: () => {
            throw new Error('denied');
        },
        setItem: () => {},
    };

    assert.equal(firstTime(refused, 'run:1'), true);
    assert.equal(firstTime(refused, 'run:1'), true);
    assert.equal(firstTime(null, 'run:1'), true);
});

test('a run over says completed, then perfect when it is', () => {
    assert.deepEqual(eventsOf(false, false), []);
    assert.deepEqual(eventsOf(false, true), []);
    assert.deepEqual(eventsOf(true, false), ['completed']);
    assert.deepEqual(eventsOf(true, true), ['completed', 'perfect']);
});

test('a run over says team-won last when the team won', () => {
    assert.deepEqual(eventsOf(false, true, true), []);
    assert.deepEqual(eventsOf(true, false, true), ['completed', 'team-won']);
    assert.deepEqual(eventsOf(true, true, true), ['completed', 'perfect', 'team-won']);
    assert.deepEqual(eventsOf(true, true, false), ['completed', 'perfect']);
});
