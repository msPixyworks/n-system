FROM php:8.3-apache

# ============================================================
# docker/php/Dockerfile（n-system / K-Core向け）
# 目的:
# - Apache + PHP 8.3
# - K-Coreに必要な拡張（pdo_mysql / intl / opcache / mbstring）
# - 画像処理（Imagick）
# - Apache modules（rewrite / headers / expires）
#
# 注意:
# - mbstring をソースビルドするために libonig-dev（oniguruma）が必要
# ============================================================

# 基本拡張 + Imagick 用ライブラリ
RUN set -eux; \
    apt-get -o Acquire::Retries=5 -o Acquire::http::Timeout=30 -o Acquire::https::Timeout=30 update; \
    apt-get -y --no-install-recommends --fix-missing install \
        libicu-dev \
        libzip-dev \
        libonig-dev \
        unzip \
        git \
        locales \
        imagemagick \
        libmagickwand-dev \
        libmagickcore-dev \
    ; \
    docker-php-ext-install intl pdo pdo_mysql opcache mbstring; \
    pecl install imagick; \
    docker-php-ext-enable imagick; \
    a2enmod rewrite headers expires; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*

# タイムゾーンとロケール
RUN ln -snf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime && echo "Asia/Tokyo" > /etc/timezone
RUN sed -i 's/# ja_JP.UTF-8 UTF-8/ja_JP.UTF-8 UTF-8/' /etc/locale.gen && locale-gen

ENV LANG=ja_JP.UTF-8
ENV LC_ALL=ja_JP.UTF-8

# Composer（必要なら）
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
 && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
 && php -r "unlink('composer-setup.php');"

WORKDIR /var/www/html
