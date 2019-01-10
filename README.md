Live Profiler
================

![logo](images/liveprof_logo.png "logo")

Live Profiler is a system-wide performance monitoring system in use at Badoo that is built on top of [XHProf](http://pecl.php.net/package/xhprof) or its forks ([Uprofiler](https://github.com/FriendsOfPHP/uprofiler) or [Tideways](https://github.com/tideways/php-profiler-extension)).
Live Profiler continually gathers function-level profiler data from production tier by running a sample of page requests under XHProf.

[Live profiler UI](https://github.com/badoo/liveprof-ui) then aggregates the profile data corresponding to individual requests by various dimensions such a time, page type, and can help answer a variety of questions such as:
What is the function-level profile for a specific page?
How expensive is function "foo" across all pages, or on a specific page?
What functions regressed most in the last day/week/month?
What is the historical trend for execution time of a page/function? and so on.

[![Build Status](https://travis-ci.org/badoo/liveprof.svg?branch=master)](https://travis-ci.org/badoo/liveprof)
[![codecov](https://codecov.io/gh/badoo/liveprof/branch/master/graph/badge.svg)](https://codecov.io/gh/badoo/liveprof)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/badoo/liveprof/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/badoo/liveprof/?branch=master)
[![GitHub license](https://img.shields.io/github/license/badoo/liveprof.svg)](https://github.com/badoo/liveprof/blob/master/LICENSE)

System Requirements
===================

* PHP version 5.4 or later / hhvm version 3.25.0 or later
* One of [XHProf](http://pecl.php.net/package/xhprof),
     [Uprofiler](https://github.com/FriendsOfPHP/uprofiler) or
     [Tideways](https://github.com/tideways/php-profiler-extension) to actually profile the data.
  You can use other profiler which returns data in the follow format:
  ```php
  $data = [
      [
          'parent_method==>child_method' => [
              'param' => 'value' 
          ]
      ]  
  ];
  ```  
* Database extension to save profiling results

Installation
============

* You can install Live Profiler via [Composer](https://getcomposer.org/):

```bash
php composer.phar require badoo/liveprof
```

* Prepare a database server. You can use any driver described [here](https://www.doctrine-project.org/projects/doctrine-dbal/en/2.8/reference/configuration.html#configuration) or implement the custom one
* Run a script to configure database. This script creates "details" table 

```bash
LIVE_PROFILER_CONNECTION_URL=mysql://db_user:db_password@db_mysql:3306/Profiler?charset=utf8 php vendor/badoo/liveprof/bin/install.php
```

* Init a profiler before working code in the project entry point (usually public/index.php).

You should add a profiler call before your code to start profiling with default parameters:
```php
<?php
include 'vendor/autoload.php';

\Badoo\LiveProfiler\LiveProfiler::getInstance()->start();
// Code is here
```

There is a full list of methods you can use to change options:
```php
<?php

// Start profiling
\Badoo\LiveProfiler\LiveProfiler::getInstance()
    ->setConnectionString('mysql://db_user:db_password@db_mysql:3306/Profiler?charset=utf8') // optional, you can also set the connection url in the environment variable LIVE_PROFILER_CONNECTION_URL
    ->setApp('Site1') // optional, current app name to use one profiler in several apps, "Default" by default
    ->setLabel('users') // optional, the request name, by default the url path or script name in cli
    ->setDivider(700) // optional, profiling starts for 1 of 700 requests with the same app and label, 1000 by default
    ->setTotalDivider(7000) // optional, profiling starts for 1 of 7000 requests with forces label "All", 10000 by default
    ->setLogger($Logger) // optional, a custom logger implemented \Psr\Log\LoggerInterface
    ->setConnection($Connection) // optional, a custom instance of \Doctrine\DBAL\Connection if you can't use the connection url
    ->setDataPacker($DatePacker) // optional, a class implemented \Badoo\LiveProfiler\DataPackerInterface to convert array into string
    ->setStartCallback($profiler_start_callback) // optional, set it if you use custom profiler
    ->setEndCallback($profiler_profiler_callback) // optional, set it if you use custom profiler
    ->start();
```

If you want to change the Label during running (for instance, after you got some information in the router or controller) you can call:
```php
<?php

$number = random_int(0, 100);
$current_label = \Badoo\LiveProfiler\LiveProfiler::getInstance()->getLabel();
\Badoo\LiveProfiler\LiveProfiler::getInstance()->setLabel($current_label . $number);
```

if you don't want to save profiling result you can reset it anytime:
```php
<?php

\Badoo\LiveProfiler\LiveProfiler::getInstance()->reset();
```

After script ends it will call `\Badoo\LiveProfiler\LiveProfiler::getInstance()->end();` on shutdown, but you can call it explicitly after working code.

Environment Variables
=====================

`LIVE_PROFILER_CONNECTION_URL`: [url](https://www.doctrine-project.org/projects/doctrine-dbal/en/2.8/reference/configuration.html#configuration) for the database connection

Work flow
=========

Live profiler allows to run profiling with custom frequency (for instance 1 of 1000 requests) grouped by app name ('Default' by default) and custom label (by default it's the url path or script name).

It's important to calculate **the request divider** properly to have enough data for aggregation. You should divide daily request count to have approximately 1 profile per minute.
For instance, if you have 1M requests a day for the page /users the divider should be 1000000/(60*24) = 694, so divider = 700 is enough.

Also you have to calculate **a total divider** for all request profiling. It's important to control the whole system health. It can be calculated the same way as a divider calculation for particularly request,
but in this case you should use count of all daily requests. For instance, if you have 10M requests a day - total_divider=10000000/(60*24) = 6940, so total_divider = 7000 is enough.

The profiler automatically detects which profiler extension you have (xhprof, uprofiler or tidyways). You have to set profiler callbacks if you use other profiler.

You can run the test script in the docker container with **xhprof** extension and look at the example of the server configuration in Dockerfile:
```bash 
docker build -t badoo/liveprof .
docker run badoo/liveprof
```

or you can build a docker container with **tideways** extension:
```bash 
docker build -f DockerfileTidyWays -t badoo/liveprof .
```

or **uprofiler** extension:
```bash 
docker build -f DockerfileUprofiler -t badoo/liveprof .
```

or latest hhvm with included  **xhprof** extension:
```bash
docker build -f DockerfileHHVM -t badoo/liveprof .
```

If your server has php version 7.0 or later it's better to use [Tideways](https://github.com/tideways/php-profiler-extension) as profiler.

Steps to install tideways extension:
```bash
git clone https://github.com/tideways/php-profiler-extension.git
cd php-profiler-extension
phpize
./configure
make
make install
echo "extension=tideways_xhprof.so" >> /usr/local/etc/php/conf.d/20-tideways_xhprof.ini
echo "xhprof.output_dir='/tmp/xhprof'" >> /usr/local/etc/php/conf.d/20-tideways_xhprof.ini
```

Steps to install uprofiler:
```bash
git clone https://github.com/FriendsOfPHP/uprofiler.git
cd uprofiler/extension/
phpize
./configure
make
make install
echo "extension=uprofiler.so" >> /usr/local/etc/php/conf.d/20-uprofiler.ini
echo "uprofiler.output_dir='/tmp/uprofiler'" >> /usr/local/etc/php/conf.d/20-uprofiler.ini
```

Tests
=====

Install Live Profiler with dev requirements:
```bash 
php composer.phar require --dev badoo/liveprof
```

In the project directory, run:
```bash
vendor/bin/phpunit
```

License
=======

This project is licensed under the MIT open source license.
