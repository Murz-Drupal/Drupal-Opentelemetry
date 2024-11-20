const enablePlugin = require('../Lib/enablePlugin');
const OtelLogWatcher = require('../Lib/OtelLogWatcher');
const { spanKind, attributes } = require('../Lib/otelSemanticConvention');

let logStream;

module.exports = {
  '@tags': ['opentelemetry'],
  before(browser) {
    browser
      .drupalInstall({
        installProfile: 'opentelemetry_test_profile',
      })
      .thLogin('admin')
      .perform(() => {
        enablePlugin(browser, 'database_statement');
      });
  },
  beforeEach() {
    logStream = new OtelLogWatcher({ verbose: true });
  },
  after(browser) {
    browser.drupalUninstall();
  },

  'Test DatabaseStatementTrace plugin': (browser) => {
    const url = '/opentelemetry-test/database-statement-trace-test';
    browser
      .perform(() => {
        logStream.expectSpanResourceItem(
          'drupal.module.opentelemetry.default',
          [],
          (resourceItem) => {
            const expectedQueriesSubstrings = [
              '"u425153"."uid"',
              ' u587319 ',
            ].reverse();

            const spans = resourceItem.item.scopeSpans[0].spans;
            // We need to use the `for` loop here, because the `await` inside the
            // forEach loop doesn't work.
            // eslint-disable-next-line no-restricted-syntax
            for (const span of spans) {
              if (
                span.kind !== spanKind.SPAN_KIND_PRODUCER ||
                !span.name.match(/query-\d+/)
              ) {
                continue;
              }
              const queryObject = span.attributes.find(
                (attribute) => attribute.key === attributes.ATTR_DB_QUERY_TEXT,
              );
              const query = queryObject.value.stringValue;
              if (query.includes(expectedQueriesSubstrings[0])) {
                expectedQueriesSubstrings.shift();
              }
              if (expectedQueriesSubstrings.length === 0) {
                break;
              }
            }
            return expectedQueriesSubstrings.length === 0;
          },
        );
      })
      .drupalRelativeURL(url)
      .perform(() => {
        logStream.checkAllItemsFound(browser);
      });
  },

  'Test DatabaseStatementTrace ExecutionFailure: ': (browser) => {
    const url =
      '/opentelemetry-test/database-statement-trace-execution-failure-test';
    browser
      .perform(() => {
        logStream.expectSpanResourceItem(
          'drupal.module.opentelemetry.default',
          [
            {
              name: logStream.pathToSpanName(url),
              events: [
                {
                  name: 'exception',
                  attributes: [
                    {
                      value: {
                        stringValue:
                          'Drupal\\Core\\Database\\DatabaseExceptionWrapper',
                      },
                    },
                  ],
                },
              ],
              status: {
                message:
                  "SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near 'FOO BAR' at line 1: FOO BAR; Array\n(\n)\n",
                code: 2,
              },
            },
          ],
        );
      })
      .drupalRelativeURL(url)
      .perform(() => {
        logStream.checkAllItemsFound(browser);
      });
  },
};
