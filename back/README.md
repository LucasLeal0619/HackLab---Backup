# HackLab — back

API em PHP com Laravel 12. A estrutura do framework está pronta. A regra de negócio do HackLab ainda não foi implementada.

O passo a passo completo do projeto está no [README da raiz](../README.md).

## Instalar nesta máquina

É preciso ter PHP 8.2 ou 8.3 (com `pdo_sqlite`) e Composer.

Windows (PowerShell):

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File -Force database\database.sqlite
php artisan migrate
php artisan serve
```

Linux ou macOS:

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve
```

A API sobe em http://localhost:8000/. O banco local é o arquivo `database/database.sqlite`.
