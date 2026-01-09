FROM composer:2 AS vendor
WORKDIR /app
# Install PHP dependencies without dev tools for a lean runtime image
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

FROM php:8.2-cli-alpine
# Install notmuch from the Alpine repository
RUN apk add --no-cache notmuch gettext

WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY . .

VOLUME /mail
VOLUME /notmuch

# Default config path so interactive shells (e.g., docker exec) see NOTMUCH_CONFIG
ENV NOTMUCH_CONFIG=/notmuch/notmuch-config

RUN chmod +x /app/docker-entrypoint.sh
ENTRYPOINT ["/app/docker-entrypoint.sh"]

EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
