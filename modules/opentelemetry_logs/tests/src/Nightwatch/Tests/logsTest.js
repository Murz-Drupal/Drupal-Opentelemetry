const OtelLogWatcher = require('../../../../../../tests/src/Nightwatch/Lib/OtelLogWatcher');

let logStream;

module.exports = {
  '@tags': ['opentelemetry', 'opentelemetry_logs'],
  before(browser) {
    browser.drupalInstall({
      installProfile: 'opentelemetry_logs_test_profile',
    });
  },
  beforeEach() {
    logStream = new OtelLogWatcher();
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'Test logs pushing': (browser) => {
    browser
      .perform(() => {
        // Entry #1.
        logStream.expectLogResourceItem(
          [
            {
              body: {
                kvlistValue: {
                  values: [
                    {
                      key: 'bar',
                      value: {
                        stringValue: 'baz',
                      },
                    },
                    {
                      key: 'channel',
                      value: {
                        stringValue: 'opentelemetry_logs_test',
                      },
                    },
                  ],
                },
              },
            },
            {
              body: {
                kvlistValue: {
                  values: [
                    {
                      key: 'key1',
                      value: {
                        stringValue: 'value1',
                      },
                    },
                    {
                      key: 'key2',
                      value: {
                        stringValue: 'value2',
                      },
                    },
                  ],
                },
              },
            },
          ],
          'Drupal',
        );
      })
      .drupalRelativeURL('/opentelemetry-logs-test/logs')
      .perform(() => {
        logStream.checkAllItemsFound(browser);
      });
  },
};
