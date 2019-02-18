FROM php:5.4
MAINTAINER Timur Shagiakhmetov <timur.shagiakhmetov@corp.badoo.com>

COPY . /app
WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends git unzip \
&& curl --silent --show-error https://getcomposer.org/installer | php \
&& ./composer.phar -q update \
&& echo 'date.timezone = Europe/London' >> /usr/local/etc/php/php.ini \
&& pecl install xhprof-0.9.4 && docker-php-ext-enable xhprof # xhprof profiler installation

CMD ["php", "/app/bin/example_save_to_file.php"]
