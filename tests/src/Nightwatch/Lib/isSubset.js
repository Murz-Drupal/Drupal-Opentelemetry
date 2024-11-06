/**
 * @file
 *
 * Provides the `isSubset` function to check if an array or object is a subset.
 */

/**
 * Checks if a nested array or object is a subset of another array or object.
 *
 * @param {Array|Object} subset
 *   The subset to check.
 * @param {Array|Object} superset
 *  The superset to check against.
 * @param {Object} options
 *  An object containing options.
 *
 * @return {bool}
 * True if `subset` is a subset of `superset`, false otherwise.
 */

module.exports = function isSubset(subset, superset, options = {}) {
  // Helper function to check if a value is an object
  function isObject(obj) {
    return obj !== null && typeof obj === 'object' && !Array.isArray(obj);
  }

  // Helper function to check if an element is a subset of another
  function isEqual(value1, value2) {
    if (Array.isArray(value1) && Array.isArray(value2)) {
      // Check every element in value1 is present in value2
      return value1.every((item1) =>
        value2.some((item2) => isEqual(item1, item2)),
      );
    }
    if (isObject(value1) && isObject(value2)) {
      // If both are objects, recursively check their properties
      return Object.keys(value1).every(
        (key) => key in value2 && isEqual(value1[key], value2[key]),
      );
    }
    // Otherwise, use strict equality
    return value1 === value2;
  }

  // Function to handle errors based on `options`
  function handleError() {
    if (options && options.throwError) {
      throw new Error(
        `Expected\n${JSON.stringify(subset, null, 2)}\nto be a subset of\n${JSON.stringify(superset, null, 2)}`,
      );
    }
    return false;
  }

  // Directly handle the case where both inputs are arrays
  if (Array.isArray(subset) && Array.isArray(superset)) {
    if (!isEqual(subset, superset)) {
      return handleError();
    }
    return true;
  }

  // Ensure both inputs are objects if not arrays
  if (!isObject(subset) || !isObject(superset)) {
    return handleError();
  }

  // Iterate over the subset keys
  // eslint-disable-next-line no-restricted-syntax
  for (const key in subset) {
    // Check if the superset contains the key and the corresponding value is equal
    if (!(key in superset) || !isEqual(subset[key], superset[key])) {
      return handleError();
    }
  }

  return true;
};
