/**
 * @file
 *
 * Provides the `isSubset` function to check if an array or object is a subset.
 */

const fs = require('fs');

// @todo Remove the hardcoded path to the log file when https://www.drupal.org/project/gitlab_templates/issues/3485612 is fixed.
const logFilePath =
  process.env.OTELCOL_LOG_FILE ?? '/var/tmp/opentelemetry-collector.log';
if (!logFilePath) {
  throw new Error(
    'The OTELCOL_LOG_FILE env variable is not set. Put there the full path to the OpenTelemetry Collector log file.',
  );
}

let logFileLastPosition = 0;

// We need to wait while the telemetry data is transmitted and written to
// the log file.
// const waitForLogTime = 500;
const waitForLogTime = 2000;

/**
 * Returns only the new log lines appeared in the log file after the last call.
 */

module.exports = function readNewLogs(browser) {
  browser.pause(waitForLogTime);
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
};
