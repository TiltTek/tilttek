import { test } from 'node:test';
import assert from 'node:assert/strict';
import { isNewer, readUpdate } from '../../assets/src/lib/updates.js';

const update = { table: 'table:42', sequence: 3, phase: 'race', round: 2, to_act: ['ann'], over: false };

test('an update of a table is read from the data of its message', () => {
    assert.deepEqual(readUpdate(JSON.stringify(update)), update);
    assert.deepEqual(readUpdate(JSON.stringify({ ...update, to_act: [], over: true })), { ...update, to_act: [], over: true });
});

test('anything else is no update', () => {
    for (const data of ['', 'not json', 'null', '3', '[]', JSON.stringify({ ...update, table: 4 }), JSON.stringify({ ...update, sequence: '3' }), JSON.stringify({ ...update, sequence: 1.5 }), JSON.stringify({ ...update, phase: null }), JSON.stringify({ ...update, round: '2' }), JSON.stringify({ ...update, to_act: 'ann' }), JSON.stringify({ ...update, to_act: [1] }), JSON.stringify({ ...update, over: 'no' })]) {
        assert.equal(readUpdate(data), null, data);
    }
});

test('an update is newer when its number of moves differs from the page', () => {
    assert.equal(isNewer(update, 2), true);
    assert.equal(isNewer(update, 4), true);
    assert.equal(isNewer(update, 3), false);
    assert.equal(isNewer(null, 3), false);
});
