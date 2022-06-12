FROM webdevops/php-nginx:7.4

RUN printf '\
set_real_ip_from 10.0.0.0/8; \
set_real_ip_from 172.16.0.0/12; \
real_ip_header X-Forwarded-For; \
' > /etc/nginx/conf.d/09-realip.conf

COPY . /app
RUN chown -R application:application /app

USER application
WORKDIR /app
RUN composer install --ignore-platform-reqs
