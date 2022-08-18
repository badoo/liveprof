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

Here is [a plugin](https://plugins.jetbrains.com/plugin/13767-live-profiler) for PhpStorm to see the method performance directly in IDE.

[liveprof.org](http://liveprof.org/) shows all features and can be used for test purposes.

[![Build Status](https://travis-ci.org/badoo/liveprof.svg?branch=master)](https://travis-ci.org/badoo/liveprof)
[![codecov](https://codecov.io/gh/badoo/liveprof/branch/master/graph/badge.svg)](https://codecov.io/gh/badoo/liveprof)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/badoo/liveprof/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/badoo/liveprof/?branch=master)
[![GitHub license](https://img.shields.io/github/license/badoo/liveprof.svg)](https://github.com/badoo/liveprof/blob/master/LICENSE)

System Requirements
===================

* PHP version 5.4 or later / hhvm version 3.25.0 or later
* One of [XHProf](http://pecl.php.net/package/xhprof),
     [Uprofiler](https://github.com/FriendsOfPHP/uprofiler) or
     [Tideways](https://github.com/tideways/php-profiler-extension) to profile and collect the data.
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
* Database extension to save profiling results to the database.

Installation
============

1. You can install Live Profiler via [Composer](https://getcomposer.org/):

```bash
php composer.phar require badoo/liveprof
```

2. Prepere a storage for results depends on mode

[save data in database] If you use DB mode you need to prepare a database server. You can use any driver described [here](https://www.doctrine-project.org/projects/doctrine-dbal/en/2.8/reference/configuration.html#configuration) or implement the custom one. You need run a script to configure database. This script creates "details" table: 

```bash
LIVE_PROFILER_CONNECTION_URL=mysql://db_user:db_password@db_mysql:3306/Profiler?charset=utf8 php vendor/badoo/liveprof/bin/install.php
```

[save data in files] It's also possible to save profiling result into files. To do it prepare a directory with write permissions.

[send data to demo site] You need to visit [liveprof.org](http://liveprof.org/) , sign in and copy API key.

3. Init a profiler before working code in the project entry point (usually public/index.php).

Usage
=====

There is an example of usage a profiler with default parameters:
```php
<?php
include 'vendor/autoload.php';

\Badoo\LiveProfiler\LiveProfiler::getInstance()->start();
// Code is here
```

There is an example how to test Live Profiler without any extension and database. You can use a build-in profiler compatible with XHProf and <a href="http://liveprof.org">liveprof.org</a> as UI:
```php
<?php
include 'vendor/autoload.php';

\Badoo\LiveProfiler\LiveProfiler::getInstance()
     ->setMode(\Badoo\LiveProfiler\LiveProfiler::MODE_API)
     ->setApiKey('70366397-97d6-41be-a83c-e9e649c824e1') // a key for guest
     ->useSimpleProfiler() // Use build-in profiler instead of XHProf or its forks
     ->setApp('Demo') // Some unique app name
     ->start();
     
// Code is here
// start a timer before each inportant method
\Badoo\LiveProfiler\SimpleProfiler::getInstance()->startTimer(__METHOD__); // any string can be used as a timer tag
// stop the timer before the end of the method
\Badoo\LiveProfiler\SimpleProfiler::getInstance()->endTimer(__METHOD__); // any string can be used as a timer tag
```

There is a full list of methods you can use to change options:
```php
<?php

// Start profiling
\Badoo\LiveProfiler\LiveProfiler::getInstance()
    ->setMode(\Badoo\LiveProfiler\LiveProfiler::MODE_DB) // optional, MODE_DB - save profiles to db, MODE_FILES - save profiles to files, MODE_API - send profiles to http://liveprof.org/ 
    ->setConnectionString('mysql://db_user:db_password@db_mysql:3306/Profiler?charset=utf8') // optional, you can also set the connection url in the environment variable LIVE_PROFILER_CONNECTION_URL
    ->setPath('/app/data/') // optional, path to save profiles, you can also set the file path in the environment variable LIVE_PROFILER_PATH
    ->setApiKey('api_key') // optional, api key to send profiles and see demo, you can get it on http://liveprof.org/ 
    ->setApp('Site1') // optional, current app name to use one profiler in several apps, "Default" by default
    ->setLabel('users') // optional, the request name, by default the url path or script name in cli
    ->setDivider(700) // optional, profiling starts for 1 of 700 requests with the same app and label, 1000 by default
    ->setTotalDivider(7000) // optional, profiling starts for 1 of 7000 requests with forces label "All", 10000 by default
    ->setLogger($Logger) // optional, a custom logger implemented \Psr\Log\LoggerInterface
    ->setConnection($Connection) // optional, a custom instance of \Doctrine\DBAL\Connection if you can't use the connection url
    ->setDataPacker($DatePacker) // optional, a class implemented \Badoo\LiveProfiler\DataPackerInterface to convert array into string
    ->setStartCallback($profiler_start_callback) // optional, set it if you use custom profiler
    ->setEndCallback($profiler_profiler_callback) // optional, set it if you use custom profiler
    ->useXhprof() // optional, force use xhprof as profiler
    ->useTidyWays() // optional, force use TidyWays as profiler
    ->useUprofiler() // optional, force use uprofiler as profiler
    ->useSimpleProfiler() // optional, force use internal profiler
    ->useXhprofSample() // optional, force use xhprof in sampling mode
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

`LIVE_PROFILER_PATH`: path to save profiles in \Badoo\LiveProfiler\LiveProfiler::MODE_FILES mode

`LIVE_PROFILER_API_URL`: api url to send profiles in \Badoo\LiveProfiler\LiveProfiler::MODE_API mode and see demo on [liveprof.org](http://liveprof.org/) 

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

or you can build a docker container with **xhprof** using sampling:
```bash 
docker build -f DockerfileSamples -t badoo/liveprof .
docker run badoo/liveprof
```

or you can build a docker container with **tideways** extension:
```bash 
docker build -f DockerfileTidyWays -t badoo/liveprof .
docker run badoo/liveprof
```

or **uprofiler** extension:
```bash 
docker build -f DockerfileUprofiler -t badoo/liveprof .
docker run badoo/liveprof
```

or latest hhvm with included  **xhprof** extension:
```bash
docker build -f DockerfileHHVM -t badoo/liveprof .
docker run badoo/liveprof
```
or if you want to use API with included  **xhprof** extension:
```bash
docker build -f DockerfileUseApi -t badoo/liveprof .
docker run badoo/liveprof
```

* If your server has php version 7.* it's better to use [Tideways](https://github.com/tideways/php-profiler-extension) as profiler.
* If your server has php version 8.* it's better to use [Xhprof](https://github.com/longxinH/xhprof) as profiler.

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
