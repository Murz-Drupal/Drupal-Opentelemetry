const isSubset = require('../../../../../../tests/src/Nightwatch/Lib/isSubsetOf');
const readNewLogs = require('../../../../../../tests/src/Nightwatch/Lib/readNewLogs');

function getScopeMetric(name, metrics) {
  return {
    scope: {
      name,
    },
    metrics,
  };
}

function getResourceMetrics(scopeMetrics) {
  return {
    resourceMetrics: [
      {
        scopeMetrics,
      },
    ],
  };
}

module.exports = {
  '@tags': ['opentelemetry', 'opentelemetry_metrics'],
  before(browser) {
    browser.drupalInstall({
      installProfile: 'opentelemetry_metrics_test_profile',
    });
  },
  beforeEach(browser) {
    // Calling this to seek to the end of the file.
    // @todo Add locking to make work with parallel tests.
    readNewLogs(browser);
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'Test metrics pushing': (browser) => {
    // A list of spans, expected in log lines.
    const expectedResources = [];
    browser
      .drupalRelativeURL('/opentelemetry-metrics-test/metrics')
      .perform(() => {
        // Entry #1.
        expectedResources.push(
          getResourceMetrics([
            getScopeMetric('opentelemetry_metrics_test.meter1', [
              {
                name: 'counter1',
                description: 'counter1 description',
                sum: {
                  dataPoints: [
                    {
                      asInt: '1',
                      exemplars: [{ asInt: '1' }],
                    },
                  ],
                },
              },
            ]),
            getScopeMetric('opentelemetry_metrics_test.meter2', [
              {
                name: 'counter2',
                description: 'counter2 description',
                unit: 'kg',
                sum: {
                  isMonotonic: true,
                },
              },
            ]),
          ]),
        );
        // Entry #2.
        expectedResources.push(
          getResourceMetrics([
            getScopeMetric('opentelemetry_metrics_test.meter1', [
              {
                name: 'counter1',
                description: 'counter1 description',
                sum: {
                  dataPoints: [
                    {
                      asInt: '7',
                      exemplars: [
                        { asInt: '1' },
                        { asInt: '1' },
                        { asInt: '1' },
                      ],
                    },
                  ],
                },
              },
            ]),
            getScopeMetric('opentelemetry_metrics_test.meter2', [
              {
                name: 'counter2',
                description: 'counter2 description',
                unit: 'kg',
                sum: {
                  dataPoints: [
                    {
                      asInt: '42',
                      exemplars: [
                        {
                          asInt: '42',
                        },
                      ],
                    },
                  ],
                  isMonotonic: true,
                },
              },
            ]),
          ]),
        );
      })
      .perform(() => {
        const logs = readNewLogs(browser);
        // We need to use the `for` loop here, because the `await` inside the
        // forEach loop doesn't work.
        // eslint-disable-next-line no-restricted-syntax
        for (const entry of logs) {
          if (entry.resourceMetrics === undefined) {
            continue;
          }
          browser.assert.ok(
            isSubset(expectedResources.shift(), entry, { throwError: true }),
          );
        }
      });
  },
};
