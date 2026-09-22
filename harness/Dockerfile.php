FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli \
    && a2enmod rewrite

# Erros visíveis no navegador, igual ao comportamento atual de config/database.php,
# só que isolado no container local (nunca aponta para o banco de produção).
RUN { \
        echo 'display_errors = On'; \
        echo 'display_startup_errors = On'; \
        echo 'error_reporting = E_ALL'; \
    } > /usr/local/etc/php/conf.d/harness-errors.ini
