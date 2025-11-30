FROM mcr.microsoft.com/devcontainers/php:1-8.2-bookworm@sha256:e2857e32205aabdfcedfd142877bd546f0b8583e3aa9d3a8d6c68f90a42132b2

# Install MariaDB client
RUN apt-get update && export DEBIAN_FRONTEND=noninteractive \
    && apt-get install -y mariadb-client \ 
    && apt-get clean -y && rm -rf /var/lib/apt/lists/*

# Install php-mysql driver
# RUN docker-php-ext-install mysqli pdo pdo_mysql


# RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
# # Enable .htaccess files & mod_rewrite
# RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride all/' /etc/apache2/apache2.conf
# RUN a2enmod rewrite

# #Most of this comes directly from the WordPress docker image
# #We don't inherit from it becase it has a VOLUME that causes issues
# RUN set -eux; \
# 	apt-get update; \
# 	apt-get install -y --no-install-recommends ghostscript git less ssh-client mariadb-client; \
# 	rm -rf /var/lib/apt/lists/*

# # install Node.js
# RUN curl -sL https://deb.nodesource.com/setup_12.x | bash - ; \
# 	apt-get install -y nodejs; \
# 	rm -rf /var/lib/apt/lists/*;


# RUN apt-get update && export DEBIAN_FRONTEND=noninteractive \
#     && apt-get -y install --no-install-recommends <your-package-list-here>



# install the PHP extensions, including XDebug
RUN set -ex; \
	apt-get update && \
	export DEBIAN_FRONTEND=noninteractive; \
	apt-get install -y --no-install-recommends \
		libfreetype6-dev \
		libjpeg-dev \
		libmagickwand-dev \
		libpng-dev \
		libzip-dev
RUN \
	docker-php-ext-configure gd --with-freetype --with-jpeg; \
	docker-php-ext-install -j "$(nproc)" \
		bcmath \
		exif \
		gd \
		mysqli \
		opcache \
		zip \
		pdo \
		pdo_mysql
RUN \
	pecl install imagick-3.8.0; \
	docker-php-ext-enable imagick xdebug;\
	rm -rf /var/lib/apt/lists/*

# #XDebug settings
RUN echo "[XDebug]\n\
[XDebug]\n\
xdebug.start_with_request = yes\n\
zend_extension = xdebug\n" > $PHP_INI_DIR/conf.d/xdebug.ini

#WP recommended PHP settings
RUN { \
	echo 'opcache.memory_consumption=256'; \
	echo 'opcache.interned_strings_buffer=8'; \
	echo 'opcache.max_accelerated_files=4000'; \
	echo 'opcache.revalidate_freq=2'; \
	echo 'opcache.fast_shutdown=1'; \
} > $PHP_INI_DIR/conf.d/opcache-recommended.ini

#install WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# #install Composer
# WORKDIR /tmp
# RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
# 		&& php composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer

#add vscode user
RUN usermod -aG www-data vscode

RUN mkdir -p /var/www/.wp-cli/cache/

RUN chown -R www-data:www-data /var/www/.wp-cli
RUN chmod g+w -R /var/www/.wp-cli
RUN chmod g+s /var/www/.wp-cli

RUN sed 's/memory_limit = .*M/memory_limit = 256M/' /usr/local/etc/php/php.ini-development \
	| sed 's/upload_max_filesize = .*M/upload_max_filesize = 8M/' \
	| sed 's/post_max_size = .*M/post_max_size = 9M/' \
> /usr/local/etc/php/php.ini

RUN find /var/www/html/ -type d -exec chmod g+s {} \;
WORKDIR /var/www/html
USER www-data

#Download WP
RUN wp core download

#add dirs for plugin and theme dev
RUN mkdir -p /var/www/html/wp-content/plugins/photolog; \
	mkdir -p /var/www/html/wp-content/themes/theme-dev;

USER root

RUN sed -i 's/:80/:8088/' /etc/apache2/sites-enabled/000-default.conf
RUN sed -i 's/Listen 80$//'  /etc/apache2/ports.conf 
RUN sed -i 's/Listen 8080$/Listen 8088/'  /etc/apache2/ports.conf 
# RUN chown -R www-data:www-data /var/www/html/
# RUN chmod g+w -R /var/www/html/
# RUN find /var/www/html/ -type d -exec chmod g+s {} \;