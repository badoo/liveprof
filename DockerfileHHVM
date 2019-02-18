FROM hhvm/hhvm:latest
MAINTAINER Timur Shagiakhmetov <timur.shagiakhmetov@corp.badoo.com>

COPY . /app
WORKDIR /app

RUN curl -sS https://getcomposer.org/installer | hhvm --php \
&& hhvm ./composer.phar -q update

CMD ["hhvm", "/app/bin/example.php"]
