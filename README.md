# Bolao 2026

Guia de configuracao para subir uma nova instancia do site.

## Visao geral

Aplicacao Laravel 12 com Livewire 4, MySQL, filas (queue), agendador (scheduler), Reverb (websocket) e sincronizacao da API football-data.org.

Stack principal:

- PHP 8.4
- Laravel 12
- MySQL 8.4
- Nginx (porta 8080)
- Reverb via proxy Nginx (porta 8081)
- Vite/Tailwind para assets

## Arquitetura Docker

O `docker-compose.yml` sobe 3 servicos:

- `app`: PHP-FPM + Supervisor
- `nginx`: proxy web e websocket
- `mysql`: banco de dados

No container `app`, o Supervisor inicia automaticamente:

- `php-fpm`
- `queue:work`
- `schedule:work`
- `reverb:start`

## Requisitos

- Docker + Docker Compose plugin
- Git

Opcional para rodar fora de Docker:

- PHP 8.2+ (recomendado 8.4)
- Composer 2
- Node 20+
- MySQL 8+

## 1. Clonar o projeto

```bash
git clone <url-do-repositorio> bolao
cd bolao
```

## 2. Configurar ambiente

Copie o arquivo de ambiente:

```bash
cp .env.example .env
```

Ajuste no minimo as variaveis abaixo em `.env`:

- `APP_NAME`
- `APP_ENV`
- `APP_DEBUG`
- `APP_URL`
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`
- `FOOTBALL_DATA_TOKEN` (obrigatorio para sincronizacao real)
- `DEFAULT_ADMIN_NAME`, `DEFAULT_ADMIN_EMAIL`, `DEFAULT_ADMIN_PHONE`, `DEFAULT_ADMIN_PASSWORD`

Valores padrao locais:

- Site: `http://localhost:8080`
- Websocket/Reverb: `http://localhost:8081`

## 3. Subir containers

```bash
docker compose up -d --build
```

Na primeira subida, o script `docker/app/start.sh` executa automaticamente:

- `composer install` (se necessario)
- `php artisan key:generate --force`
- `php artisan migrate --force`

## 4. Instalar dependencias front-end e gerar assets

No host:

```bash
npm install
npm run build
```

Ou dentro do container `app` (se Node estiver disponivel no ambiente):

```bash
docker compose exec app npm install
docker compose exec app npm run build
```

## 5. Seed do administrador

Crie/atualize usuario administrador padrao:

```bash
docker compose exec app php artisan db:seed
```

O seeder usa as variaveis `DEFAULT_ADMIN_*` do `.env`.

## 6. Acessar o sistema

- Aplicacao: `http://localhost:8080`
- Login inicial: `DEFAULT_ADMIN_EMAIL` / `DEFAULT_ADMIN_PASSWORD`
- Health check Laravel: `http://localhost:8080/up`

## Operacao diaria

Limpar caches quando alterar configuracoes:

```bash
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan config:clear
docker compose exec app php artisan view:clear
```

Ver logs dos servicos:

```bash
docker compose logs -f app nginx mysql
```

Executar sincronizacao manual da Copa:

```bash
docker compose exec app php artisan worldcup:sync-group-stage --force
```

Agendamento automatico configurado em `routes/console.php`:

- Comando `worldcup:sync-group-stage`
- Frequencia: a cada minuto
- Executa em background, sem sobreposicao

## Publicacao de novo site (checklist rapido)

1. Definir novo dominio e ajustar `APP_URL`.
2. Gerar credenciais fortes de banco e admin.
3. Preencher `FOOTBALL_DATA_TOKEN` valido.
4. Subir stack com `docker compose up -d --build`.
5. Rodar `php artisan db:seed` para garantir admin inicial.
6. Gerar assets com `npm run build`.
7. Validar login, dashboard e rota `/up`.
8. Validar websocket em `:8081`.

## Solucao de problemas

Erro de permissao em `storage` ou `bootstrap/cache`:

```bash
docker compose exec app sh -lc "chmod -R ug+rwx storage bootstrap/cache"
```

Banco nao conectando:

- Verifique se `DB_HOST=mysql` no `.env`.
- Confirme se o container `mysql` esta `healthy`.
- Reaplique migracoes:

```bash
docker compose exec app php artisan migrate --force
```

Falha na sincronizacao externa:

- Validar `FOOTBALL_DATA_TOKEN`.
- Rodar comando manual com `--force`.
- Conferir tabela/log de sincronizacao no admin.

## Comandos uteis

```bash
# subir/parar ambiente
docker compose up -d
docker compose down

# entrar no container app
docker compose exec app sh

# testes
docker compose exec app php artisan test

# rebuild total
docker compose down
docker compose up -d --build
```
