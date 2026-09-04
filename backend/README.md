# LeadFlow Industrial Backend

API responsável por receber, validar, armazenar e encaminhar os leads enviados pelo site da LeadFlow Industrial.

## Tecnologias

- PHP 8.3 ou superior
- Laravel 13
- Composer 2
- MySQL ou MariaDB em produção
- SQLite no desenvolvimento local e nos testes
- Fila Laravel utilizando banco de dados

## Situação atual

O backend está preparado para:

- identificação do serviço;
- verificação de saúde da API;
- recebimento de leads por endpoint HTTP;
- suporte a contato, solicitação de amostra e WhatsApp;
- validação específica para cada tipo de lead;
- armazenamento seguro no banco de dados;
- controle do ciclo de vida dos leads;
- processamento assíncrono por fila;
- registro individual das tentativas de entrega;
- recuperação de processamentos interrompidos;
- tratamento de falhas temporárias e definitivas;
- prevenção de jobs duplicados;
- limitação da quantidade de tentativas;
- despacho administrativo de leads pendentes;
- nova tentativa administrativa de leads com falha;
- consulta agregada da situação operacional;
- logs específicos sem dados pessoais;
- proteção por rate limiting;
- campo honeypot contra robôs;
- CORS restrito às origens autorizadas;
- testes automatizados de comportamento e persistência.

A entrega externa está desabilitada por padrão. O driver concreto da 3C será implementado após o recebimento das especificações oficiais e das credenciais do ambiente de testes.

## Primeira instalação local

Entre na pasta do backend:

    cd backend

Execute somente na primeira configuração:

    composer run setup

Esse comando instala as dependências, cria o arquivo `.env`, gera a chave da aplicação e executa as migrations.

O arquivo `.env` contém configurações locais e informações sensíveis e nunca deve ser enviado ao Git.

## Execução local

Para iniciar o servidor:

    composer run dev

A API ficará disponível, por padrão, em:

    http://127.0.0.1:8000

## Testes

Para executar todos os testes:

    composer run test

Para verificar o padrão de formatação:

    vendor/bin/pint --test

No Windows:

    .\vendor\bin\pint.bat --test

## Rotas disponíveis

| Método | Rota | Finalidade |
|---|---|---|
| GET | `/` | Identificação da API |
| GET | `/api/health` | Verificação personalizada de funcionamento |
| GET | `/up` | Verificação interna de saúde do Laravel |
| POST | `/api/leads` | Recebimento e armazenamento de leads |

## Banco de dados

O projeto utiliza bancos diferentes conforme o ambiente:

- desenvolvimento local: SQLite;
- testes automatizados: SQLite temporário em memória;
- produção: MySQL ou MariaDB.

A aplicação seleciona o banco pelas variáveis `DB_*` do arquivo `.env`.

Para consultar as migrations:

    php artisan migrate:status

Para aplicar as migrations em produção:

    php artisan migrate --force

## Entrega de leads

A configuração segura padrão mantém a entrega externa desabilitada:

    LEAD_DELIVERY_ENABLED=false
    LEAD_DELIVERY_DRIVER=null
    LEAD_DELIVERY_TIMEOUT=10
    LEAD_DELIVERY_MAX_ATTEMPTS=3

Com essa configuração, os leads são recebidos e armazenados, mas não são enviados para serviços externos.

## Estados dos leads

| Status | Finalidade |
|---|---|
| `pending` | Aguardando processamento |
| `processing` | Em processamento |
| `retrying` | Aguardando nova tentativa |
| `sent` | Entrega concluída |
| `failed` | Falha definitiva |

## Comandos operacionais

Consultar o estado agregado:

    php artisan leads:status

Simular o despacho de leads pendentes:

    php artisan leads:dispatch-pending --dry-run --limit=100

Despachar leads pendentes:

    php artisan leads:dispatch-pending --limit=100

Simular novas tentativas:

    php artisan leads:retry-failed --dry-run --limit=100

Reprocessar leads com falha:

    php artisan leads:retry-failed --limit=100

Consultar jobs com falha:

    php artisan queue:failed

Iniciar o worker localmente:

    php artisan queue:work database --queue=default

## Logs

Os eventos de entrega são registrados diariamente em:

    storage/logs/lead-delivery-AAAA-MM-DD.log

Os logs operacionais não armazenam nomes, e-mails, telefones, empresas, mensagens ou o payload completo dos leads.

## Documentação

- Contrato da API: `docs/lead-api-contract.md`
- Manual operacional: `docs/lead-delivery-operations.md`

## Produção

Antes da publicação:

- configurar o arquivo `.env`;
- gerar uma chave exclusiva;
- manter `APP_ENV=production`;
- manter `APP_DEBUG=false`;
- configurar o MySQL;
- executar as migrations;
- gerar os caches do Laravel;
- validar as permissões das pastas;
- iniciar o worker da fila;
- validar HTTPS e CORS;
- executar `php artisan leads:status`;
- verificar os logs e os jobs com falha;
- manter a entrega desabilitada até a configuração do driver externo.

As instruções detalhadas estão no manual `docs/lead-delivery-operations.md`.

## Integrações pendentes

O núcleo do backend está implementado e protegido por testes.

Ainda dependem de informações externas:

- implementação do driver definitivo da 3C;
- credenciais e ambiente de testes da integração;
- configuração do servidor de produção;
- conexão do front-end com a API publicada.

## Licença

Projeto proprietário da LeadFlow Industrial. Uso e distribuição não autorizados.