const fs = require('fs');
const isSubset = require('../Lib/isSubset');

// @todo Remove the hardcoded path to the log file when https://www.drupal.org/project/gitlab_templates/issues/3485612 is fixed.
const logFilePath =
  process.env.OTELCOL_LOG_FILE ?? '/var/tmp/opentelemetry-collector.log';
if (!logFilePath) {
  throw new Error(
    'The OTELCOL_LOG_FILE env variable is not set. Put there the full path to the OpenTelemetry Collector log file.',
  );
}

const spanKindTypes = {
  KIND_INTERNAL: 0,
  KIND_CLIENT: 1,
  KIND_SERVER: 2,
  KIND_PRODUCER: 3,
  KIND_CONSUMER: 4,
};

let logFileLastPosition = 0;

function readNewLogs() {
  const logFile = fs.openSync(logFilePath, 'r');
  const logFileSize = fs.fstatSync(logFile).size;
  const bytesToRead = logFileSize - logFileLastPosition;
  const buffer = Buffer.alloc(bytesToRead);
  const bytesRead = fs.readSync(
    logFile,
    buffer,
    0,
    bytesToRead,
    logFileLastPosition,
  );
  const lines = buffer.toString('utf8', 0, bytesRead).split('\n');
  fs.closeSync(logFile);
  logFileLastPosition = logFileSize;
  return lines
    .map((line) => {
      try {
        return JSON.parse(line);
      } catch (e) {
        return line;
      }
    })
    .filter((line) => line !== '');
}

function assertSpans(
  { spans, path, additionalSpans = [], defaultSpansProperties = [] },
  browser,
) {
  const method = 'GET';

  function getLine(name, propertiesToAdd) {
    return {
      name,
      attributes: [
        { key: 'http.request.method', value: { stringValue: 'GET' } },
        { key: 'url.path', value: { stringValue: path } },
      ],
      kind: spanKindTypes.KIND_SERVER,
      status: {},
      ...propertiesToAdd,
    };
  }

  let expected = [
    getLine(`${method} ${path} (request)`, {
      kind: spanKindTypes.KIND_CLIENT,
      ...defaultSpansProperties[0],
    }),
    getLine(`${method} ${path}`, {
      ...defaultSpansProperties[1],
    }),
  ];
  if (additionalSpans) {
    expected = expected.concat(
      additionalSpans.map((span) => {
        return span;
      }),
    );
  }
  browser.assert.ok(
    isSubset(expected, spans, { throwError: true }),
    'Spans content is equals to expected values',
  );
}

module.exports = {
  '@tags': ['opentelemetry'],
  before(browser) {
    browser.drupalInstall({
      installProfile: 'opentelemetry_testing',
    });
  },
  beforeEach() {
    // Calling this to seek to the end of the file.
    // @todo Add locking to make work with parallel tests.
    readNewLogs();
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'Check submitted spans': (browser) => {
    // We need to wait while the telemetry data is transmitted and written to
    // the log file.
    const waitForLogTime = 500;

    // A list of spans, expected in log lines.
    const expectedLogsSpans = [];

    browser
      .drupalRelativeURL('/')
      .perform(() => {
        expectedLogsSpans.push({
          path: '/',
        });
      })
      .drupalRelativeURL('/admin/config/development/opentelemetry')
      .perform(() => {
        expectedLogsSpans.push({
          path: '/admin/config/development/opentelemetry',
          defaultSpansProperties: [
            {},
            {
              events: [{ name: 'exception' }],
              status: {
                message:
                  "The 'administer site configuration' permission is required.",
                code: 2,
              },
            },
          ],
        });
      })
      .thLogin('admin', 'admin')
      .perform(() => {
        expectedLogsSpans.push({
          path: '/test-helpers-functional/login/admin',
        });
      })
      .drupalRelativeURL('/admin/config/development/opentelemetry')
      .perform(() => {
        expectedLogsSpans.push({
          path: '/admin/config/development/opentelemetry',
          additionalSpans: [
            { name: 'OpenTelemetry settings form' },
            { name: 'parent buildForm' },
          ],
        });
      })
      .pause(waitForLogTime)
      .perform(() => {
        // Collect all added logs and check spans.
        const logs = readNewLogs();
        // eslint-disable-next-line no-restricted-syntax
        for (const log of logs) {
          assertSpans(
            {
              spans: log.resourceSpans[0].scopeSpans[0].spans,
              ...expectedLogsSpans.shift(),
            },
            browser,
          );
        }
      });
  },
};
