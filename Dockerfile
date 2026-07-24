# 1. Usar la imagen oficial de PHP
FROM php:8.4-cli

# 2. Instalar dependencias y extensiones de PostgreSQL/GD para imágenes
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_pgsql gd \
    && rm -rf /var/lib/apt/lists/*

# 3. Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Configurar el directorio de trabajo
WORKDIR /app

# 5. Copiar los archivos del proyecto al contenedor
COPY . .

# 6. Instalar dependencias de Laravel optimizadas para producción
RUN composer install --no-dev --optimize-autoloader

# 7. Ejecutar el servidor usando el puerto dinámico de Render
CMD php artisan serve --host=0.0.0.0 --port=$PORT
