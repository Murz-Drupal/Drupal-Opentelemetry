# OpenTelemetry

Integration of the [OpenTelemetry PHP library](https://github.com/open-telemetry/opentelemetry-php)
 with Drupal. [More info about OpenTelemetry](https://opentelemetry.io/).

This allows you to see not only the total execution time of the Drupal Request,
but also detailed information about internal processes like time spent on
preparing the Request, SQL  queries, etc.

### Usage example in your custom code:

```php
$this->openTelemetry = \Drupal::service('opentelemetry');
$tracer = $this->openTelemetry->getTracer();
$span = $tracer->spanBuilder('My custom operation')->startSpan();
$span->setAttribute('foo', 'bar');
// Do some stuff.
$span->addEvent('My event', ['baz' => 'qux']);
$span->setAttribute('results', 'quux');
$span->end();
```

For a full description of the module, visit the
[project page](https://www.drupal.org/project/opentelemetry).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/opentelemetry).


## Requirements

The module requires PHP at least 8.0, and depends on OpenTelemetry-PHP library.

For advanced features it requires Drupal Core 10.1.x where the patch from the
issue https://www.drupal.org/project/drupal/issues/3313355 is already commited
or applied manually.

Also, to use [OpenTelemetry auto-instrumentation](https://github.com/open-telemetry/opentelemetry-php-instrumentation)
features it requires the `otel_instrumentation` PHP extension to be installed.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

The configuration page is available at the path
`/admin/config/development/opentelemetry`.


### Local configuration quick start

To get OpenTelemetry server locally with a convenient web interface to view your
traces you can use [DDEV project](https://ddev.readthedocs.io/) with my
[Grafana Stack addon for DDEV](https://github.com/MurzNN/ddev-grafana):

1. Enable debug mode in OpenTelemetry module settings, and confugure the `http://tempo:9411/api/v2/spans`.

2. Get the trace id from the message after enabling the setting.

3. Open Grafana interface at url like `http://your-project.ddev.site:3000/`.

4. Go to "Explore" tab in the left sidebar.

5. Choose "Tempo" source from data source picker in the top left corner.

6. Select "Query type = Search" in the form, and press the blue "Run query"
button.

7. See the list of recent traces and find your trace by the trace id.


## Maintainers

- Alexey Korepov - [Murz](https://www.drupal.org/u/murz)
