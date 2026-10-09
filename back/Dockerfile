# Imagem do backend Laravel. Imagens oficiais multi-arch (amd64 e arm64).
#
# Alvos:
#   dev  — desenvolvimento: código montado por volume, servidor embutido do PHP na porta 8000.
#   (o alvo de produção para o Render será adicionado na etapa de deploy.)

FROM php:8.3-fpm-bookworm AS base

# Instalador de extensões PHP (resolve dependências do sistema em amd64 e arm64).
COPY --from=mlocati/php-extension-installer:2.7 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pgsql mbstring intl zip bcmath opcache pcntl

COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

FROM base AS dev

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip postgresql-client \
    && rm -rf /var/lib/apt/lists/*

EXPOSE 8000

# Servidor embutido do PHP com o roteador do Laravel (o mesmo que "artisan serve" usa).
# Chamado direto para herdar as variáveis do compose (DB_HOST=postgres); "artisan serve" não repassa todas.
# O roteador espera o diretório atual em public/.
CMD ["sh", "-c", "cd public && exec php -S 0.0.0.0:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"]
