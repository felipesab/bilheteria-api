# ADR 001: Banco de dados e ambiente de desenvolvimento

- **Status:** Proposto
- **Data:** 2026-11-08

## Contexto

A bilheteria-api é uma API Laravel 13 (PHP 8.5). Antes de escrever as primeiras funcionalidades, precisamos definir:

- qual banco de dados a aplicação usa;
- onde ficam cache, filas e sessões;
- como cada desenvolvedor sobe os serviços de que a aplicação depende;
- em qual banco os testes rodam, localmente e no CI.

O esqueleto do Laravel vem configurado com SQLite e com os drivers `database` para cache e filas. Isso é suficiente para começar, mas não representa o ambiente em que a aplicação vai rodar em produção.

## Decisão

### Banco de dados: PostgreSQL 17

A aplicação usa PostgreSQL (`DB_CONNECTION=pgsql`). A versão 17 está fixada no `docker-compose.yml` e no CI, para que todos usem o mesmo banco.

### Cache e filas: Redis 7

Cache (`CACHE_STORE=redis`) e filas (`QUEUE_CONNECTION=redis`) usam Redis. Isso tira essa carga do banco relacional, e o Redis atende bem a operações frequentes e de vida curta, como locks, contadores e jobs.

O cliente Redis padrão é o **predis** (`REDIS_CLIENT=predis`) em todos os ambientes: local, CI e produção. O pacote `predis/predis` é dependência do projeto, então ninguém precisa instalar a extensão `phpredis`. Se um dia o desempenho exigir, a troca para `phpredis` é só uma variável de ambiente, mas deve ser registrada em um novo ADR.

As sessões continuam no banco (`SESSION_DRIVER=database`).

### Serviços locais: Docker Compose

O `docker-compose.yml` na raiz sobe os serviços de que a aplicação depende. A aplicação PHP roda fora do Docker.

| Serviço  | Imagem            | Portas                         | Uso                               |
| -------- | ----------------- | ------------------------------ | --------------------------------- |
| postgres | `postgres:17`     | 5432                           | banco `bilheteria`, volume `pgdata` |
| redis    | `redis:7`         | 6379                           | cache e filas                     |
| mailpit  | `axllent/mailpit` | 1025 (SMTP), 8025 (interface web) | captura e-mails em desenvolvimento |

Para subir: `docker compose up -d`.

O `.env.example` já vem configurado para esses serviços: PostgreSQL com as credenciais do `docker-compose.yml`, Redis para cache e filas, e `MAIL_MAILER=smtp` na porta 1025, que é o Mailpit. Basta copiá-lo para `.env` e rodar `php artisan key:generate`. Os e-mails enviados em desenvolvimento aparecem em http://localhost:8025.

### Testes

- **Localmente:** o `phpunit.xml` usa SQLite em memória, para os testes serem rápidos e não dependerem do Docker.
- **No CI (GitHub Actions):** os testes rodam no PostgreSQL 17 e no Redis 7, que sobem como services do job. As variáveis de ambiente do job (`DB_CONNECTION=pgsql` etc.) prevalecem sobre os valores do `phpunit.xml`.
- Os testes de feature usam `RefreshDatabase`, que recria o schema a cada execução. Por isso eles **nunca** devem apontar para o banco `bilheteria` de desenvolvimento. Para rodar os testes no Postgres local, use um banco separado (`bilheteria_test`).

## Alternativas consideradas

- **MySQL/MariaDB:** também são bem suportados pelo Laravel. Não há impedimento técnico; a escolha pelo PostgreSQL está registrada aqui para evitar que bancos diferentes se misturem entre ambientes.
- **Manter SQLite também em produção:** é simples, mas limita escrita concorrente e não oferece os recursos de um banco servidor.
- **Cache e filas no banco (`database`):** não exige serviço extra, mas aumenta a carga no PostgreSQL e tende a ser mais lento para filas com muito volume.
- **Aplicação PHP também dentro do Docker (por exemplo, Laravel Sail):** daria um ambiente mais uniforme, mas o time já usa PHP local (Herd). Por isso o Docker fica só com os serviços.

## Consequências

**Positivas**

- Desenvolvimento, CI e produção usam o mesmo banco e as mesmas versões de serviço. Erros específicos do PostgreSQL aparecem no CI, e não em produção.
- Um único comando sobe tudo de que a aplicação precisa localmente.
- O Mailpit evita que e-mails de teste saiam para endereços reais.

**Negativas e pontos de atenção**

- É preciso ter o Docker instalado e rodando para desenvolver.
- Localmente os testes usam SQLite, e no CI usam PostgreSQL. Uma consulta que depende de recurso exclusivo do PostgreSQL pode passar no CI e falhar localmente, ou o contrário. Se isso começar a acontecer com frequência, vale rodar os testes locais no Postgres (`bilheteria_test`).
- As credenciais do `.env.example` (`bilheteria`/`secret`) servem apenas para o ambiente local. Produção deve usar credenciais próprias, nunca versionadas.
