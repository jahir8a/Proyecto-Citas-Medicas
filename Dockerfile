FROM php:8.1-apache

# Instalar extensiones necesarias
RUN apt-get update && apt-get install -y \
	git \
	unzip \
	zip \
	libzip-dev \
	libpng-dev \
	libjpeg62-turbo-dev \
	libfreetype6-dev \
	libxrender1 \
	libfontconfig1 \
	libxext6 \
	&& docker-php-ext-configure gd --with-freetype --with-jpeg \
	&& docker-php-ext-install -j$(nproc) pdo pdo_mysql mysqli gd zip \
	&& apt-get clean && rm -rf /var/lib/apt/lists/*

# Habilitar mod_rewrite para URLs limpias
RUN a2enmod rewrite

# Instalar Composer (para dependencias PHP como Dompdf) y preparar entrypoint
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Establecer el directorio de trabajo
WORKDIR /var/www/html

# Cambiar permisos
RUN chown -R www-data:www-data /var/www/html

# Crear script de arranque que instale dependencias si hace falta y luego arranque Apache
RUN printf '%s\n' "#!/bin/bash" "set -e" "cd /var/www/html" "if [ -f composer.json ] && [ ! -d vendor ]; then composer install --no-interaction --prefer-dist --optimize-autoloader; fi" "exec apache2-foreground" > /usr/local/bin/startup.sh \
	&& chmod +x /usr/local/bin/startup.sh

# Exponer puerto
EXPOSE 80

# Comando por defecto
CMD ["/usr/local/bin/startup.sh"]
