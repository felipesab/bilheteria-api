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

### Por que PostgreSQL e não MySQL

O Laravel suporta os dois bancos no mesmo nível, e ambos estão disponíveis em provedores gerenciados. A escolha se baseia em recursos que pesam numa bilheteria, onde o risco principal é vender o mesmo assento duas vezes ou deixar o estoque de ingressos inconsistente:

- **Regras de integridade no próprio banco.** O PostgreSQL tem índices únicos parciais (`UNIQUE ... WHERE`). Com eles, dá para garantir "um assento só pode ter um ingresso ativo por sessão" e ainda permitir ingressos cancelados para o mesmo assento. Também tem *exclusion constraints*, que impedem intervalos sobrepostos, como reservas de horário. O MySQL não tem nenhum dos dois, então essas regras ficariam só no código da aplicação, onde duas requisições simultâneas podem passar pela validação ao mesmo tempo.
- **Migrations transacionais.** No PostgreSQL, comandos de estrutura (`CREATE`, `ALTER`) rodam dentro de transação. Se uma migration falhar no meio, nada fica aplicado pela metade. No MySQL, cada comando de estrutura faz *commit* implícito, e uma falha pode deixar o schema num estado intermediário que precisa ser corrigido à mão.
- **Tipos de dados.** O PostgreSQL tem `uuid` nativo, `jsonb` com índices GIN, `timestamptz` (data e hora com fuso) e arrays. O MySQL também tem `JSON`, mas para indexar o conteúdo depende de colunas geradas ou de índices multivalorados.
- **Comportamento estrito com tipos.** O PostgreSQL rejeita dados e comparações com tipos incompatíveis. O MySQL depende do `sql_mode`. O Laravel liga o modo estrito por padrão (`'strict' => true` em `config/database.php`), o que reduz essa diferença, mas não a elimina.

Os trade-offs que aceitamos com essa escolha estão em **Consequências**.

### Cache e filas: Redis 7

Cache (`CACHE_STORE=redis`) e filas (`QUEUE_CONNECTION=redis`) usam Redis. Isso tira essa carga do banco relacional, e o Redis atende bem a operações frequentes e de vida curta, como locks, contadores e jobs.

O cliente Redis padrão é o **predis** (`REDIS_CLIENT=predis`) em todos os ambientes: local, CI e produção. O pacote `predis/predis` é dependência do projeto, então ninguém precisa instalar a extensão `phpredis`. Se um dia o desempenho exigir, a troca para `phpredis` é só uma variável de ambiente, mas deve ser registrada em um novo ADR.

As sessões continuam no banco (`SESSION_DRIVER=database`).

### Serviços locais: Docker Compose

O `docker-compose.yml` na raiz sobe os serviços de que a aplicação depende. A aplicação PHP roda fora do Docker.

| Serviço  | Imagem            | Portas                         | Uso                               |
| -------- | ----------------- | ------------------------------ | --------------------------------- |
| postgres | `postgres:17`     | 5432                           | bancos `bilheteria` e `bilheteria_test`, volume `pgdata` |
| redis    | `redis:7`         | 6379                           | cache e filas                     |
| mailpit  | `axllent/mailpit` | 1025 (SMTP), 8025 (interface web) | captura e-mails em desenvolvimento |

Para subir: `docker compose up -d`.

Na primeira vez que o volume `pgdata` é criado, o Postgres executa os scripts de `docker/postgres/init/`. O `01-criar-banco-de-teste.sql` cria o banco `bilheteria_test`, usado pelos testes. Esses scripts não rodam de novo enquanto o volume existir.

O `.env.example` já vem configurado para esses serviços: PostgreSQL com as credenciais do `docker-compose.yml`, Redis para cache e filas, e `MAIL_MAILER=smtp` na porta 1025, que é o Mailpit. Basta copiá-lo para `.env` e rodar `php artisan key:generate`. Os e-mails enviados em desenvolvimento aparecem em http://localhost:8025.

### Testes

Os testes rodam no PostgreSQL em todos os ambientes, para que uma consulta que funciona nos testes funcione também em produção.

- **Localmente:** o `phpunit.xml` define `DB_CONNECTION=pgsql` e `DB_DATABASE=bilheteria_test`, um banco separado no mesmo PostgreSQL do Docker Compose. Os demais valores de conexão vêm do `.env`.
- **No CI (GitHub Actions):** os testes rodam no PostgreSQL 17 e no Redis 7, que sobem como services do job, também com o banco `bilheteria_test`. As variáveis de ambiente do job prevalecem sobre os valores do `phpunit.xml`.
- Os testes de feature usam `RefreshDatabase`, que recria o schema a cada execução. Por isso eles **nunca** devem apontar para o banco `bilheteria` de desenvolvimento.

## Alternativas consideradas

- **MySQL/MariaDB:** são bem suportados pelo Laravel e mais comuns em hospedagens simples. O MySQL também lida melhor com muitas conexões simultâneas sem ferramenta extra (veja Consequências). Não foi escolhido porque não tem índices únicos parciais nem *exclusion constraints*, e porque suas migrations não são transacionais. Os motivos estão detalhados em "Por que PostgreSQL e não MySQL".
- **Manter SQLite também em produção:** é simples, mas limita escrita concorrente e não oferece os recursos de um banco servidor.
- **Cache e filas no banco (`database`):** não exige serviço extra, mas aumenta a carga no PostgreSQL e tende a ser mais lento para filas com muito volume.
- **Aplicação PHP também dentro do Docker (por exemplo, Laravel Sail):** daria um ambiente mais uniforme, mas o time já usa PHP local (Herd). Por isso o Docker fica só com os serviços.

## Consequências

**Positivas**

- Desenvolvimento, testes, CI e produção usam o mesmo banco e as mesmas versões de serviço. Erros específicos do PostgreSQL aparecem já nos testes locais, e não em produção.
- Regras críticas de estoque, como "um ingresso ativo por assento", podem ser garantidas pelo banco, que continua consistente mesmo com requisições simultâneas.
- Um único comando sobe tudo de que a aplicação precisa localmente.
- O Mailpit evita que e-mails de teste saiam para endereços reais.

**Negativas e pontos de atenção**

- É preciso ter o Docker instalado e rodando para desenvolver e para rodar os testes.
- **Custo por conexão.** O PostgreSQL cria um processo por conexão, o que consome mais memória que o modelo do MySQL, que usa uma thread por conexão. Em picos de venda, como a abertura de um show concorrido, muitos workers abertos ao mesmo tempo podem esgotar o limite de conexões. Se isso ocorrer, a solução usual é um *pooler* como o PgBouncer, que é mais um componente para operar.
- **Manutenção do MVCC.** Cada `UPDATE` no PostgreSQL gera uma nova versão da linha, e a antiga só é limpa pelo `autovacuum`. Tabelas atualizadas com muita frequência, como status de assentos e reservas temporárias, precisam ter o `autovacuum` acompanhado em produção, para não crescerem nem ficarem lentas.
- **Comparações de texto diferenciam maiúsculas de minúsculas.** No PostgreSQL, `=` e `LIKE` diferenciam "Show" de "show". No MySQL, a collation padrão não diferencia. Buscas por nome devem usar `ILIKE` ou `whereLike()` do Laravel, e não `like` direto.
- **Menos portabilidade.** Ao usar recursos exclusivos do PostgreSQL (índices parciais, `jsonb`, *exclusion constraints*), trocar de banco no futuro deixa de ser só uma variável de ambiente.
- Quem já tinha o volume `pgdata` antes do script de inicialização não ganha o `bilheteria_test` automaticamente. Nesse caso, crie-o uma vez com `docker compose exec postgres createdb -U bilheteria bilheteria_test`.
- As credenciais do `.env.example` (`bilheteria`/`secret`) servem apenas para o ambiente local. Produção deve usar credenciais próprias, nunca versionadas.
