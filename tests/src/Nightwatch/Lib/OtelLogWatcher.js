/**
 * @file
 *
 * Provides a class to watch the OpenTelemetry collector logs.
 */

const LogWatcher = require('./LogWatcher');
const isSubsetOf = require('./isSubsetOf');

const logsWaitTime = 2000;

// @todo Remove the hardcoded path to the log file when https://www.drupal.org/project/gitlab_templates/issues/3485612 is fixed.
const logFilePath =
  process.env.OTELCOL_LOG_FILE ??
  '/var/tmp/opentelemetry-collector/opentelemetry-collector.log';
if (!logFilePath) {
  throw new Error(
    'The OTELCOL_LOG_FILE env variable is not set. Put there the full path to the OpenTelemetry Collector log file.',
  );
}

module.exports = class OtelLogWatcher {
  constructor({ timeout = 30000, verbose = false } = {}) {
    const file = logFilePath;
    this.timeout = timeout;
    this.verbose = verbose;
    this.logWatcher = new LogWatcher(file, timeout);
    this.logEntries = [];
    this.entriesToWatch = [];
    this.entriesFound = [];
    this.delta = 0;

    this.logWatcher.on('newLine', (line) => {
      if (this.verbose) {
        // eslint-disable-next-line no-console
        console.log('Line:', `${line.substring(0, 80)}...`);
      }
      let resourceItems;
      try {
        resourceItems = OtelLogWatcher.logLineToResourceItems(line);
      } catch (e) {
        throw new Error(
          `Error parsing JSON in the log line: ${e.message} ${JSON.stringify(line, null, 2)}`,
        );
      }
      // We need to use the `for` loop here, because the `await` inside the
      // forEach loop doesn't work.
      // eslint-disable-next-line no-restricted-syntax
      for (const resourceItem of resourceItems) {
        if (this.verbose) {
          // eslint-disable-next-line no-console
          console.log(
            'Resource item:',
            OtelLogWatcher.prettifyResourceItem(resourceItem),
          );
        }
        this.checkResourceItem(resourceItem);
      }
    });
  }

  checkResourceItem(resourceItem) {
    const resourceItemExpected = this.entriesToWatch[0];
    if (!resourceItemExpected) {
      return;
    }
    let result;
    if (typeof resourceItemExpected.callback === 'function') {
      result = resourceItemExpected.callback(resourceItem);
    } else {
      result = resourceItem.type === resourceItemExpected.type;
      if (result) {
        if (this.verbose) {
          result = isSubsetOf(resourceItemExpected.item, resourceItem.item, {
            throwError: true,
          });
        } else {
          result = isSubsetOf(resourceItemExpected.item, resourceItem.item);
        }
      }
    }
    if (result) {
      this.entriesFound.push(resourceItem);
      if (this.verbose) {
        // eslint-disable-next-line no-console
        console.log(
          'Match:',
          OtelLogWatcher.prettifyResourceItem(resourceItem),
          OtelLogWatcher.prettifyResourceItem(resourceItemExpected),
        );
      }
      this.entriesToWatch.shift();
    } else if (this.verbose) {
      // eslint-disable-next-line no-console
      console.log(
        'No match:',
        OtelLogWatcher.prettifyResourceItem(resourceItem),
        OtelLogWatcher.prettifyResourceItem(resourceItemExpected),
      );
    }
  }

  static logLineToResourceItems(line) {
    const entry = JSON.parse(line);
    const type = Object.keys(entry)[0];
    const resourceItems = entry[type].map((item) => {
      delete item.resource;
      return { type, item };
    });
    return resourceItems;
  }

  static prettifyResourceItem(resourceItem) {
    let result;
    let item;

    switch (resourceItem.type) {
      case 'resourceSpans': {
        item = resourceItem.item;
        const spans = item.scopeSpans[0].spans;
        const traceId = spans[0] ? spans[0].traceId : 'no';
        const spanNames = spans.map(
          (span) => span.name + (span.spanId ? ` (${span.spanId})` : ''),
        );
        result = `${resourceItem.type}: traceId ${traceId} - ${item.scopeSpans[0].scope.name}: ${spanNames.join(
          ', ',
        )}`;
        break;
      }
      case 'resourceMetrics': {
        const metricToString = (metric) => {
          if (metric.sum?.dataPoints?.[0].asInt) {
            return `${metric.sum.dataPoints[0].asInt} (ex: ${metric.sum.dataPoints[0].exemplars.map((exemplar) => exemplar.asInt).join(', ')}`;
          }

          if (metric.sum.dataPoints) {
            metric.sum.dataPoints.forEach((dataPoint) => {
              delete dataPoint.startTimeUnixNano;
              delete dataPoint.timeUnixNano;
              if (dataPoint.exemplars) {
                dataPoint.exemplars.forEach((exemplar) => {
                  delete exemplar.startTimeUnixNano;
                  delete exemplar.timeUnixNano;
                });
              }
            });
          }
          return `sum: ${JSON.stringify(metric.sum)}`;
        };
        item = resourceItem.item;
        const scopeMetrics = item.scopeMetrics;
        const metricNames = scopeMetrics.map(
          (scopeMetric) =>
            `${scopeMetric.metrics[0].name} (${scopeMetric.scope.name}) - metrics: ${scopeMetric.metrics.map((metric) => metricToString(metric)).join(', ')}`,
        );
        result = `${resourceItem.type}: ${metricNames.join(', ')}`;
        break;
      }
      default: {
        result = JSON.stringify(resourceItem).substring(0, 256);
        break;
      }
    }
    return result;
  }

  pathToSpanName(path, isRequest = false) {
    if (!this.basePath) {
      const parts = new URL(process.env.DRUPAL_TEST_BASE_URL);
      this.basePath = parts.pathname.replace(/\/$/, '');
    }
    let name = `GET ${this.basePath}${path}`;
    if (isRequest) {
      name += ' (request)';
    }
    return name;
  }

  expectResourceItem(resourceItem) {
    this.entriesToWatch.push(resourceItem);
  }

  expectSpanResourceItem(scopeName, spanNames, callback) {
    const resourceItem = {
      type: 'resourceSpans',
      item: {
        scopeSpans: [
          {
            scope: {
              name: scopeName,
            },
            spans: [],
          },
        ],
      },
    };
    // We need to use the `for` loop here, because the `await` inside the
    // forEach loop doesn't work.
    // eslint-disable-next-line no-restricted-syntax
    for (const spanName of spanNames) {
      const span = typeof spanName === 'string' ? { name: spanName } : spanName;
      resourceItem.item.scopeSpans[0].spans.push(span);
    }
    if (callback) {
      resourceItem.callback = callback;
    }

    this.expectResourceItem(resourceItem);
  }

  expectMetricResourceItem(metrics, callback) {
    const resourceItem = {
      type: 'resourceMetrics',
      item: {
        scopeMetrics: metrics,
      },
    };
    if (callback) {
      resourceItem.callback = callback;
    }
    this.expectResourceItem(resourceItem);
  }

  expectLogResourceItem(logRecords, scopeName, callback) {
    const resourceItem = {
      type: 'resourceLogs',
      item: {
        scopeLogs: [
          {
            scope: {
              name: scopeName,
            },
            logRecords,
          },
        ],
      },
    };
    if (callback) {
      resourceItem.callback = callback;
    }
    this.expectResourceItem(resourceItem);
  }

  checkAllItemsFound(browser) {
    browser.pause(logsWaitTime).perform(() => {
      this.stop();
      if (this.entriesToWatch.length > 0) {
        throw new Error(
          `Missing log entries:${JSON.stringify(this.entriesToWatch, null, 2)}`,
        );
      }
      browser.assert.ok(true, 'All expected log entries found');
    });
  }

  stop() {
    this.logWatcher.stopWatching();
    this.logWatcher = null;
  }
};
