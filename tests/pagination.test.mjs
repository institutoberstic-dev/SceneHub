import test from 'node:test';
import assert from 'node:assert/strict';
import { pageWindow } from '../resources/js/components/paginationWindow.js';
test('page windows have unique boundaries and ellipses', () => {
    assert.deepEqual(pageWindow(1, 0), []);
    assert.deepEqual(pageWindow(1, 1), [1]);
    assert.deepEqual(pageWindow(1, 3), [1, 2, 3]);
    assert.deepEqual(pageWindow(10, 20), [1, 'before', 8, 9, 10, 11, 12, 'after', 20]);
    assert.deepEqual(pageWindow(99, 20), [1, 'before', 16, 17, 18, 19, 20]);
});
