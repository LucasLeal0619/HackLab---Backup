# HackLab

Sistema interno de gestão do Hackathon do Senac.

- `front` — interface em JavaScript com Vue 3 e Vite.
- `back` — API em PHP com Laravel 12 e PostgreSQL.
- `docker-compose.yml` — ambiente de desenvolvimento e testes (Laravel + PostgreSQL).

A documentação de arquitetura e regras está em [`CLAUDE.md`](CLAUDE.md) e [`hacklab_docs/`](hacklab_docs/README.md).

`node_modules`, `vendor` e os arquivos `.env` não vão no Git. Quem clonar o repositório precisa instalar na própria máquina.

## O que instalar antes

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Windows/macOS) ou Docker Engine + Compose (Linux)
- [Node.js](https://nodejs.org/) 20 ou mais recente, com npm (para o front)

PHP, Composer e PostgreSQL **não** precisam estar instalados na máquina: rodam nos containers.

## Back (Docker)

A partir da raiz do projeto:

```bash
cp back/.env.example back/.env
cp back/.env.testing.example back/.env.testing
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan key:generate --env=testing
docker compose exec app php artisan hacklab:db-check
docker compose exec app php artisan migrate --seed
```

No PowerShell, troque `cp` por `Copy-Item`.

**Rede que bloqueia o build** (proxy/firewall que barra o `apt` do Debian dentro do Docker): suba só o banco com `docker compose up -d postgres` e rode o Laravel no PHP da máquina (PHP 8.2+ com `pdo_pgsql` e `pgsql`), usando `php artisan …` dentro de `back/`. O `.env` já aponta para `127.0.0.1:5442`.

- API: http://localhost:8000/api/v1/health
- PostgreSQL (para DBeaver/pgAdmin): `127.0.0.1:5442`, usuário `hacklab`, senha `hacklab`, schema `hacklab`

Bancos:

| Banco | Uso |
|---|---|
| `hacklab_dev` | desenvolvimento |
| `hacklab_test` | testes automatizados (apagado a cada execução) |
| Supabase | produção (só nas variáveis do Render, nunca no `.env` local) |

As tabelas são criadas só pelas migrations do Laravel. Não crie tabelas manualmente.

### Testes

```bash
docker compose exec app php artisan test
```

Os testes rodam em PostgreSQL, no banco `hacklab_test`. O backend recusa rodar testes em qualquer banco que não termine em `_test` e qualquer conexão de desenvolvimento ou teste com o Supabase.

### Comandos úteis

```bash
docker compose ps                     # status
docker compose logs -f app            # logs do Laravel
docker compose exec app php artisan … # qualquer comando artisan
docker compose down                   # para os containers (dados do PostgreSQL ficam no volume)
docker compose down -v                # para e APAGA o volume do PostgreSQL (recria hacklab_dev e hacklab_test)
```

## Front

```bash
cd front
npm install
npm run dev
```

Abre em http://localhost:5174/

Os dados da interface ainda ficam no navegador (`localStorage`). A ligação com a API será feita tela a tela.

## Produção

- API Laravel: Render Web Service (Docker)
- Frontend: Static Site (`npm run build`, URL da API em `VITE_API_URL`)
- Banco: Supabase (PostgreSQL gerenciado, Session pooler com SSL)

Docker é o ambiente de desenvolvimento e testes, não a produção. Detalhes, riscos e pendências em [`hacklab_docs/docs/infraestrutura.md`](hacklab_docs/docs/infraestrutura.md).
