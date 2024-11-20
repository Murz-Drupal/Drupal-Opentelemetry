const OtelLogWatcher = require('../../../../../../tests/src/Nightwatch/Lib/OtelLogWatcher');

let logStream;

module.exports = {
  '@tags': ['opentelemetry', 'opentelemetry_metrics'],
  before(browser) {
    browser.drupalInstall({
      installProfile: 'opentelemetry_metrics_test_profile',
    });
  },
  beforeEach() {
    logStream = new OtelLogWatcher();
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'Test metrics pushing': (browser) => {
    browser
      .perform(() => {
        // Entry #1.
        logStream.expectMetricResourceItem([
          {
            scope: { name: 'opentelemetry_metrics_test.meter1' },
            metrics: [
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
            ],
          },
          {
            scope: { name: 'opentelemetry_metrics_test.meter2' },
            metrics: [
              {
                name: 'counter2',
                description: 'counter2 description',
                unit: 'kg',
                sum: {
                  isMonotonic: true,
                },
              },
            ],
          },
        ]);

        // Entry #2.
        logStream.expectMetricResourceItem([
          {
            scope: { name: 'opentelemetry_metrics_test.meter1' },
            metrics: [
              {
                name: 'counter1',
                description: 'counter1 description',
                sum: {
                  dataPoints: [
                    {
                      asInt: '6',
                      exemplars: [{ asInt: '1' }, { asInt: '1' }],
                    },
                  ],
                },
              },
            ],
          },
          {
            scope: { name: 'opentelemetry_metrics_test.meter2' },
            metrics: [
              {
                name: 'counter2',
                description: 'counter2 description',
                unit: 'kg',
                sum: {
                  isMonotonic: true,
                },
              },
            ],
          },
        ]);

        // Entry #3.
        logStream.expectMetricResourceItem([
          {
            scope: { name: 'opentelemetry_metrics_test.meter1' },
            metrics: [
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
            ],
          },
          {
            scope: { name: 'opentelemetry_metrics_test.meter2' },
            metrics: [
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
            ],
          },
        ]);
      })
      .drupalRelativeURL('/opentelemetry-metrics-test/metrics')
      .perform(() => {
        logStream.checkAllItemsFound(browser);
      });
  },
};
