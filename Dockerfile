FROM webdevops/php-nginx:7.4

COPY . /app
RUN chown -R application:application /app

USER application
WORKDIR /app
RUN composer install --ignore-platform-reqs
