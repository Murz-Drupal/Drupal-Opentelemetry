const isSubsetOf = require('../Lib/isSubsetOf');
const readNewLogs = require('../Lib/readNewLogs');

const spanKindTypes = {
  KIND_INTERNAL: 0,
  KIND_CLIENT: 1,
  KIND_SERVER: 2,
  KIND_PRODUCER: 3,
  KIND_CONSUMER: 4,
};

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
    isSubsetOf(expected, spans, { throwError: true }),
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
  beforeEach(browser) {
    // Calling this to seek to the end of the file.
    // @todo Add locking to make work with parallel tests.
    readNewLogs(browser);
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'Check submitted spans': (browser) => {
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
      .perform(() => {
        // Collect all added logs and check spans.
        const logs = readNewLogs(browser);
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
