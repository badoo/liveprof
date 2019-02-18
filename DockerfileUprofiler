FROM php:5.4
MAINTAINER Timur Shagiakhmetov <timur.shagiakhmetov@corp.badoo.com>

COPY . /app
WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends git unzip \
&& curl --silent --show-error https://getcomposer.org/installer | php \
&& ./composer.phar -q update \
&& echo 'date.timezone = Europe/London' >> /usr/local/etc/php/php.ini

# uprofiler installation
RUN git clone https://github.com/FriendsOfPHP/uprofiler.git /tmp/uprofiler
WORKDIR /tmp/uprofiler/extension
RUN phpize && ./configure && make && make install \
&& echo "extension=uprofiler.so" >> /usr/local/etc/php/conf.d/20-uprofiler.ini \
&& echo "uprofiler.output_dir='/usr/src/myapp/uprofiler'" >> /usr/local/etc/php/conf.d/20-uprofiler.ini

CMD ["php", "/app/bin/example.php"]
