/**
 * @file
 *
 * Provides the OpenTelemetry semantic convention constants.
 */

module.exports = {
  spanKind: {
    SPAN_KIND_INTERNAL: 0,
    SPAN_KIND_CLIENT: 1,
    SPAN_KIND_SERVER: 2,
    SPAN_KIND_PRODUCER: 3,
    SPAN_KIND_CONSUMER: 4,
  },

  attributes: {
    ATTR_DB_QUERY_TEXT: 'db.query.text',
  },
};
