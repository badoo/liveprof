FROM php:7.3
MAINTAINER Timur Shagiakhmetov <timur.shagiakhmetov@corp.badoo.com>

COPY . /app
WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends git unzip \
&& curl --silent --show-error https://getcomposer.org/installer | php \
&& ./composer.phar -q update

# tideways profiler installation
RUN git clone https://github.com/tideways/php-profiler-extension.git /tmp/xhptof
WORKDIR /tmp/xhptof
RUN phpize && ./configure && make && make install \
&& echo "extension=tideways_xhprof.so" >> /usr/local/etc/php/conf.d/20-xhprof.ini \
&& echo "xhprof.output_dir='/usr/src/myapp/xhprof'" >> /usr/local/etc/php/conf.d/20-xhprof.ini

CMD ["php", "/app/bin/example.php"]
