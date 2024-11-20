/**
 * @file
 *
 * Provides the `enablePlugin` function.
 */

/**
 * Enable a Drupal Opentelemetry plugin.
 *
 * @param {object} browser
 *   Nightwatch browser object.
 * @param {string} pluginName
 *   The name of the plugin to enable.
 */
module.exports = function enablePlugin(browser, pluginName) {
  browser
    .drupalRelativeURL('/admin/config/development/opentelemetry')
    .click(`input[name="span_plugins_enabled[${pluginName}]"]`)
    .click('input[type="submit"]');
};
