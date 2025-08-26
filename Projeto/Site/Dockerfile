FROM php:8.2-apache

# Copia os arquivos do projeto para a pasta padrão do Apache
COPY . /var/www/html/

# Habilita o mod_rewrite (se precisar para frameworks)
RUN a2enmod rewrite

# Exponha a porta padrão do Apache
EXPOSE 80

# O CMD já vem no php:apache para rodar o apache2-foreground

