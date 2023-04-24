[[_TOC_]]

#### Introduction

WebProfiler module extract, collect, store and display profiling information for Drupal.

For every request, WebProfiler create a profile file that contains all the collected information. This
information are then rendered on a toolbar on every HTML response, and on a dedicated dashboard in the
backoffice.

A lot of Drupal subsystems are replaced by WebProfiler to collect profiling information, and this
can lead to some performance issues. For this reason, WebProfiler must not be used in production.

#### Installation

WebProfiler can be downloaded and installed like any other Drupal module.

#### Collect time metrics

To enable the collection of time metrics you need to add this line to the `settings.php` file:

```php
$settings['tracer_plugin'] = 'stopwatch_tracer';
```

Anyway a better solution to trace Drupal internals is to use the `tracer` plugin to send
data to an external trace database like [Grafana Tempo](https://grafana.com/oss/tempo/). You can
find more information on [this](https://www.youtube.com/watch?v=6UKIbbbflAs) YouTube video.

#### Configuration

After enabling the module, only some widgets are displayed, you can enable all the others in the
WebProfiler settings page (`/admin/config/development/devel/webprofiler`).
