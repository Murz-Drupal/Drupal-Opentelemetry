const fs = require('fs');
const readline = require('readline');
const events = require('events');

module.exports = class LogWatcher extends events.EventEmitter {
  constructor(filePath, timeout = null) {
    super();
    this.filePath = filePath;
    this.logLines = [];
    this.fileSize = 0;
    this.watcher = null;
    this.timeout = timeout; // Set the timeout from the constructor

    this.init();
  }

  init() {
    // Start watching the file for changes
    this.watchFile();

    // Get the current file size (to only process new lines)
    fs.stat(this.filePath, (err, stats) => {
      if (err) {
        // eslint-disable-next-line no-console
        console.error(`Error getting file stats: ${err.message}`);
        return;
      }
      this.fileSize = stats.size;
    });

    // If a timeout is specified, stop watching after the timeout
    if (this.timeout !== null) {
      this.stopWatchingTimeout = setTimeout(() => {
        this.stopWatching();
      }, this.timeout);
    }
  }

  watchFile() {
    // Create a file watcher
    this.watcher = fs.watch(this.filePath, (eventType) => {
      if (eventType === 'change') {
        this.processNewLines();
      }
    });

    // Handle error events from the watcher
    this.watcher.on('error', (err) => {
      // eslint-disable-next-line no-console
      console.error(`Watcher error: ${err.message}`);
    });
  }

  processNewLines() {
    this.stream = fs.createReadStream(this.filePath, {
      start: this.fileSize,
      encoding: 'utf8',
    });

    const rl = readline.createInterface({
      input: this.stream,
    });

    rl.on('line', (line) => {
      this.logLines.push(line);
      this.emit('newLine', line); // Emit an event in case client code wants to react to new lines
    });

    rl.on('close', () => {
      // Update the recorded file size.
      fs.stat(this.filePath, (err, stats) => {
        if (err) {
          // eslint-disable-next-line no-console
          console.error(`Error getting updated file stats: ${err.message}`);
          return;
        }
        this.fileSize = stats.size;
      });
    });
  }

  stopWatching() {
    if (this.watcher) {
      this.watcher.close();
      this.watcher = null;
      if (this.stream) {
        this.stream.close();
        this.stream = null;
      }
      if (this.stopWatchingTimeout) {
        clearTimeout(this.stopWatchingTimeout);
      }
      // eslint-disable-next-line no-console
      console.log('Stopped watching the file.');
    } else {
      // eslint-disable-next-line no-console
      console.log('No active file watcher to stop.');
    }
  }
};
