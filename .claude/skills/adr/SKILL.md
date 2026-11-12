---
name: adr
description: "Cria e mantém ADRs (Architecture Decision Records) do projeto em docs/adr/. Use quando o usuário pedir para criar, registrar, documentar, atualizar, aceitar ou substituir uma decisão de arquitetura, ou falar em \"ADR\", \"registro de decisão\" ou \"documentar a decisão sobre X\" (banco, cache, filas, autenticação, estrutura, infraestrutura, bibliotecas). Não use para README, changelog ou documentação de API."
---

# ADR: registro de decisões de arquitetura

Os ADRs deste projeto ficam em `docs/adr/`, em português (pt-BR), um arquivo por decisão. O modelo de estrutura está em [template.md](template.md), e o exemplo de referência é `docs/adr/001-banco-e-ambiente.md`. Leia os dois antes de escrever.

## Público

Um ADR é escrito para o time e para quem entrar no projeto depois, não para quem pediu. O leitor não participou da conversa. Explique o contexto sem pressupor o que foi discutido no chat.

## Criar um ADR

1. **Numeração e nome.** Liste `docs/adr/` e use o próximo número com três dígitos. O nome do arquivo é `NNN-assunto-em-kebab-case.md`, em português e sem acentos (por exemplo, `002-autenticacao-da-api.md`). O título dentro do arquivo é `# ADR NNN: <Título>`, com acentos normais.
2. **Levante os fatos no projeto.** Cada versão, variável de ambiente, serviço ou arquivo citado precisa ser conferido no repositório: `composer.json`, `.env.example`, `docker-compose.yml`, `config/`, `.github/workflows/`. Não cite um valor que você não verificou.
3. **Não invente motivações.** Se o usuário não disse por que escolheu algo, escreva apenas trade-offs técnicos verdadeiros e gerais. Ao entregar, avise que as justificativas precisam ser revisadas ou completadas pelo time.
4. **Preencha o modelo.** Siga as seções de [template.md](template.md), nesta ordem: Contexto, Decisão, Alternativas consideradas, Consequências.
   - Em **Decisão**, use um subtítulo `###` por tema. Mostre os valores concretos em `código` e use tabela quando houver vários itens com os mesmos atributos.
   - Em **Alternativas consideradas**, registre pelo menos as alternativas óbvias, cada uma com o motivo de não ter sido escolhida.
   - Em **Consequências**, separe **Positivas** e **Negativas e pontos de atenção**.
5. **Status e data.** Um ADR novo nasce com `Proposto`, porque quem aceita é o time. A data é a de hoje, no formato `AAAA-MM-DD`.
6. **Pendências.** Se o projeto ainda não estiver de acordo com a decisão (configuração divergente, valores padrão do esqueleto, ambientes diferentes entre si), liste cada divergência como pendência em "Negativas e pontos de atenção". Ao entregar, ofereça corrigir.

## Atualizar um ADR

- **Pendência resolvida:** remova o item da lista e registre o estado final na seção Decisão. Por exemplo, "o `.env.example` já vem configurado para...". Confira a correção no projeto antes de removê-la.
- **ADR `Proposto`:** pode ser editado livremente até ser aceito.
- **ADR `Aceito`:** não reescreva a decisão. Para mudá-la, crie um novo ADR. No novo, cite o antigo no Contexto. No antigo, mude apenas o status para `Substituído pelo ADR NNN`.
- Valores de status: `Proposto`, `Aceito`, `Rejeitado`, `Obsoleto`, `Substituído pelo ADR NNN`.

## Estilo

- Frases curtas e diretas, voz ativa. Sem adjetivos de propaganda ("robusto", "moderno").
- Identificadores, comandos, variáveis e nomes de arquivo sempre em `código`.
- Credenciais só podem aparecer se forem de desenvolvimento local e já estiverem versionadas, e com o aviso de que produção usa outras.
- Não crie índice, README ou outros arquivos em `docs/` sem o usuário pedir.

## Ao entregar

Comece a resposta com uma linha dizendo para quem o documento foi escrito (por exemplo, "Escrito para: o time do projeto e quem entrar nele depois."). Depois aponte o que precisa de revisão humana: o status, as justificativas que você não pôde confirmar e as pendências encontradas.
