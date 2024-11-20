/**
 * @file
 *
 * Provides the `isSubset` function to check if an array or object is a subset.
 */

const isSubsetOf = require('../Lib/isSubsetOf');

module.exports = {
  '@tags': ['opentelemetry'],

  'Test the function isSubsetOf()': (browser) => {
    const testCases = [
      {
        description: 'Simple equality with primitives',
        subset: 3,
        superset: 3,
        expectedResult: true,
      },
      {
        description: 'Primitive inequality',
        subset: 3,
        superset: 4,
        expectedResult: false,
      },
      {
        description: 'Array of primitives as subset',
        subset: [1, 2],
        superset: [1, 2, 3],
        expectedResult: true,
      },
      {
        description: 'Array of primitives not a subset',
        subset: [1, 4],
        superset: [1, 2, 3],
        expectedResult: false,
      },
      {
        description: 'Nested arrays',
        subset: [[1], [2]],
        superset: [[1], [2], [3]],
        expectedResult: true,
      },
      {
        description: 'Object with primitive properties',
        subset: { a: 1, b: 2 },
        superset: { a: 1, b: 2, c: 3 },
        expectedResult: true,
      },
      {
        description: 'Object with nested objects',
        subset: { a: { b: 2 } },
        superset: { a: { b: 2, c: 3 } },
        expectedResult: true,
      },
      {
        description: 'Object with array properties',
        subset: { a: [1, 2] },
        superset: { a: [1, 2, 3] },
        expectedResult: true,
      },
      {
        description: 'Array of objects as subset',
        subset: [{ x: 5 }],
        superset: [{ x: 5 }, { y: 6 }],
        expectedResult: true,
      },
      {
        description: 'Array of objects, not a subset',
        subset: [{ x: 5 }],
        superset: [{ x: 6 }, { y: 5 }],
        expectedResult: false,
      },
      {
        description: 'Complex nested structure',
        subset: { a: [{ b: 2 }] },
        superset: { a: [{ b: 2 }, { c: 3 }] },
        expectedResult: true,
      },
      {
        description: 'Empty array in subset and non-empty in superset',
        subset: [],
        superset: [1, 2, 3],
        expectedResult: true,
      },
      {
        description: 'Empty subset and non-empty superset',
        subset: {},
        superset: { a: 1, b: 2 },
        expectedResult: true,
      },
      {
        description: 'Top-level array of objects as subset',
        subset: [{ value: 'foo' }, { value: 'bar' }],
        superset: [{ value: 'bar' }, { value: 'baz' }, { value: 'foo' }],
        expectedResult: true,
      },
      {
        description: 'Array of objects with missing values',
        subset: [{ value: 'foo' }, { value: 'qux' }],
        superset: [{ value: 'bar' }, { value: 'baz' }, { value: 'foo' }],
        expectedResult: false,
      },
    ];

    testCases.forEach(({ description, subset, superset, expectedResult }) => {
      const result = isSubsetOf(subset, superset);
      browser.assert.equal(result, expectedResult, description);
    });
  },
};
