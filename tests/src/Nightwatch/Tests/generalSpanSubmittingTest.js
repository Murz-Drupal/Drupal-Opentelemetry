const OtelLogWatcher = require('../Lib/OtelLogWatcher');

let logStream;

module.exports = {
  '@tags': ['opentelemetry'],
  before(browser) {
    browser.drupalInstall({
      installProfile: 'opentelemetry_test_profile',
    });
  },
  beforeEach() {
    logStream = new OtelLogWatcher();
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'Check submitted spans': (browser) => {
    browser
      .perform(() => {
        logStream.expectSpanResourceItem(
          'drupal.module.opentelemetry.default',
          [logStream.pathToSpanName('/', true), logStream.pathToSpanName('/')],
        );
      })
      .drupalRelativeURL('/')

      .perform(() => {
        logStream.expectSpanResourceItem(
          'drupal.module.opentelemetry.default',
          [
            logStream.pathToSpanName(
              '/admin/config/development/opentelemetry',
              true,
            ),
            {
              name: logStream.pathToSpanName(
                '/admin/config/development/opentelemetry',
              ),
              events: [{ name: 'exception' }],
              status: {
                message:
                  "The 'administer site configuration' permission is required.",
                code: 2,
              },
            },
          ],
        );
      })
      .drupalRelativeURL('/admin/config/development/opentelemetry')

      .perform(() => {
        logStream.expectSpanResourceItem(
          'drupal.module.opentelemetry.default',
          [
            logStream.pathToSpanName(
              '/test-helpers-functional/login/admin',
              true,
            ),
            logStream.pathToSpanName('/test-helpers-functional/login/admin'),
          ],
        );
      })
      .thLogin('admin', 'admin')

      .perform(() => {
        logStream.expectSpanResourceItem(
          'drupal.module.opentelemetry.default',
          [
            'parent buildForm',
            'OpenTelemetry settings form',
            logStream.pathToSpanName(
              '/admin/config/development/opentelemetry',
              true,
            ),
            logStream.pathToSpanName('/admin/config/development/opentelemetry'),
          ],
        );
      })
      .drupalRelativeURL('/admin/config/development/opentelemetry')

      .perform(() => {
        logStream.checkAllItemsFound(browser);
      });
  },
};
