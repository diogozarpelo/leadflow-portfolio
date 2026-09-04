# Operação da entrega de leads

Este documento descreve como configurar, monitorar e operar o processamento de leads da API da LeadFlow Industrial.

## Situação atual

O backend está preparado para:

- receber e validar leads do site;
- armazenar leads inicialmente como `pending`;
- processar entregas por fila;
- registrar cada tentativa de entrega;
- impedir jobs duplicados para o mesmo lead;
- classificar falhas como temporárias ou definitivas;
- recuperar processamentos interrompidos;
- limitar a quantidade máxima de tentativas;
- registrar eventos operacionais sem dados pessoais;
- consultar contagens agregadas por status;
- reenviar leads pendentes ou com falha por comandos administrativos.

A entrega externa permanece desativada por segurança enquanto o driver definitivo da 3C não estiver implementado e configurado.

## Configuração segura padrão

```dotenv
LEAD_DELIVERY_ENABLED=false
LEAD_DELIVERY_DRIVER=null
LEAD_DELIVERY_TIMEOUT=10
LEAD_DELIVERY_MAX_ATTEMPTS=3
LEAD_DELIVERY_LOG_LEVEL=info
LEAD_DELIVERY_LOG_DAYS=14
```

Com essa configuração:

- os leads continuam sendo recebidos e armazenados;
- nenhum job de entrega externa é disparado;
- nenhum dado é enviado para terceiros;
- os leads permanecem disponíveis para processamento futuro.

Após qualquer alteração no arquivo `.env`, atualize o cache:

```bash
php artisan config:clear
php artisan config:cache
```

## Banco de dados

Antes de iniciar a operação em um novo ambiente, execute:

```bash
php artisan migrate --force
php artisan migrate:status
```

As principais tabelas utilizadas são:

- `leads`;
- `lead_delivery_attempts`;
- `jobs`;
- `failed_jobs`;
- `cache`;
- `cache_locks`.

## Verificação do estado operacional

Execute:

```bash
php artisan leads:status
```

Esse comando apresenta:

- configuração atual da entrega;
- conexão e nome da fila;
- quantidade de leads em cada status;
- quantidade de tentativas em cada status.

Nenhum nome, e-mail, telefone, empresa ou mensagem é exibido.

## Estados dos leads

| Status | Significado |
|---|---|
| `pending` | Lead armazenado e aguardando processamento |
| `processing` | Entrega em processamento |
| `retrying` | Falha temporária aguardando nova tentativa |
| `sent` | Entrega concluída |
| `failed` | Falha definitiva ou limite de tentativas atingido |

## Estados das tentativas

| Status | Significado |
|---|---|
| `processing` | Tentativa em andamento |
| `succeeded` | Tentativa concluída com sucesso |
| `failed` | Tentativa encerrada com falha |

## Processamento da fila

A aplicação utiliza a conexão de fila `database`.

Para iniciar um worker:

```bash
php artisan queue:work database --queue=default --sleep=3 --max-time=3600
```

Em produção, o worker deverá ser mantido por um gerenciador de processos disponibilizado pelo servidor.

Depois de uma nova publicação do backend, reinicie os workers:

```bash
php artisan queue:restart
```

## Despacho de leads pendentes

Primeiro faça uma simulação:

```bash
php artisan leads:dispatch-pending --dry-run --limit=100
```

Para realizar o despacho:

```bash
php artisan leads:dispatch-pending --limit=100
```

O despacho somente será permitido quando:

- `LEAD_DELIVERY_ENABLED=true`;
- existir um driver externo válido;
- o driver configurado não for `null`.

## Nova tentativa de leads com falha

Primeiro faça uma simulação:

```bash
php artisan leads:retry-failed --dry-run --limit=100
```

Para realizar as novas tentativas:

```bash
php artisan leads:retry-failed --limit=100
```

## Monitoramento da fila

Consultar jobs com falha:

```bash
php artisan queue:failed
```

Monitorar o volume da fila:

```bash
php artisan queue:monitor database:default --max=100
```

Reprocessar um job específico:

```bash
php artisan queue:retry ID_DO_JOB
```

Reiniciar os workers:

```bash
php artisan queue:restart
```

## Logs operacionais

Os eventos de entrega são registrados em:

```text
storage/logs/lead-delivery-AAAA-MM-DD.log
```

A retenção padrão é de 14 dias.

Os logs registram:

- início de tentativa;
- entrega concluída;
- tentativa com falha;
- processamento interrompido;
- limite de tentativas atingido;
- falha definitiva do job.

Os logs não devem conter:

- nome;
- e-mail;
- telefone;
- empresa;
- CNPJ;
- mensagem;
- payload completo do lead.

## Interrupção emergencial

Para impedir novos despachos, configure:

```dotenv
LEAD_DELIVERY_ENABLED=false
```

Depois atualize o cache:

```bash
php artisan config:cache
```

Essa configuração impede novos jobs, mas não interrompe automaticamente jobs que já estejam na fila ou em execução.

Para uma interrupção imediata:

1. interrompa ou pause o worker;
2. desabilite a entrega no `.env`;
3. atualize o cache de configuração;
4. execute `php artisan leads:status`;
5. analise os jobs e os logs antes de retomar.

## Comandos destrutivos

Os comandos abaixo podem apagar registros da fila e não devem ser executados sem análise:

```bash
php artisan queue:clear
php artisan queue:flush
```

`queue:clear` remove jobs aguardando processamento.

`queue:flush` remove registros de jobs com falha.

## Checklist de publicação

1. Configurar o `.env` de produção.
2. Manter `APP_DEBUG=false`.
3. Confirmar a conexão MySQL.
4. Executar `php artisan migrate --force`.
5. Executar `php artisan config:cache`.
6. Executar `php artisan route:cache`.
7. Executar `php artisan leads:status`.
8. Iniciar ou reiniciar o worker.
9. Validar `php artisan queue:failed`.
10. Validar as permissões de `storage` e `bootstrap/cache`.
11. Confirmar a criação dos logs operacionais.
12. Manter a entrega desabilitada até a configuração do driver externo.

## Preparação dos dados para entrega

Em 27/08/2026, a fronteira entre o domínio de leads e os futuros drivers externos foi reforçada.

O `LeadDeliveryService` continua responsável pelo processamento, pelos status, pelas tentativas e pelo tratamento de falhas. Antes de chamar o driver configurado, o serviço transforma o modelo `Lead` em um `LeadDeliveryPayload`.

O payload:

- contém somente os dados necessários para a futura entrega;
- preserva o identificador interno do lead;
- mantém campos opcionais como valores anuláveis;
- não expõe métodos, relacionamentos ou controles operacionais do modelo;
- pode ser convertido em uma matriz associativa por `toArray()`;
- permanece independente de CSV, JSON, webhook e autenticação.

O contrato `LeadDeliveryDriver` passou a receber `LeadDeliveryPayload` em vez do modelo `Lead` completo.

Essa alteração prepara uma fronteira segura para o futuro driver da 3C. O mapeamento definitivo de campos, os nomes externos, o formato CSV, a direção do webhook e os mecanismos de autenticação continuam dependentes da especificação oficial.

Nenhuma comunicação externa foi ativada por essa alteração.

## Codificação preliminar em CSV

Em 27/08/2026, foi criada a classe `LeadDeliveryCsvEncoder` para transformar o `LeadDeliveryPayload` em uma representação CSV interna e preliminar.

O codificador:

- gera uma linha de cabeçalho a partir das chaves fornecidas por `toArray()`;
- gera a linha de dados correspondente ao payload;
- utiliza vírgula como delimitador padrão;
- permite configurar outro delimitador de um único caractere;
- preserva conteúdo em UTF-8;
- protege corretamente campos que contêm vírgulas, aspas ou quebras de linha;
- representa campos anuláveis como células vazias;
- utiliza um stream temporário em memória;
- não cria arquivos persistentes;
- rejeita delimitadores inválidos com mais de um caractere.

Em 28/08/2026, os testes do codificador foram ampliados para cobrir campos com quebra de linha e a rejeição de delimitadores inválidos.

Essa implementação ainda não representa o contrato definitivo da 3C. Permanecem pendentes de confirmação:

- nomes e ordem oficial dos cabeçalhos;
- presença ou ausência da linha de cabeçalho;
- delimitador esperado;
- codificação e necessidade de BOM;
- padrão de quebra de linha;
- forma de transporte do CSV;
- método HTTP e `Content-Type`;
- autenticação e tratamento das respostas.

Nenhum arquivo foi transmitido e nenhuma comunicação externa foi ativada.

## Driver local de simulação em CSV

Em 01/09/2026, foi criado o `LocalCsvLeadDeliveryDriver` para simular uma entrega completa sem transmitir informações para serviços externos.

O driver:

- implementa o contrato `LeadDeliveryDriver`;
- recebe somente o `LeadDeliveryPayload`;
- utiliza o `LeadDeliveryCsvEncoder`;
- grava um arquivo CSV no disco local configurado;
- utiliza por padrão a pasta `lead-delivery/outbox`;
- cria nomes determinísticos no formato `lead-{internal_id}.csv`;
- devolve um identificador simulado no formato `local-csv-{internal_id}`;
- classifica falhas de armazenamento como temporárias e passíveis de nova tentativa;
- pode ser selecionado pelo valor `local_csv`;
- é resolvido como singleton pelo contêiner do Laravel.

No disco local padrão do Laravel, um arquivo poderá ser armazenado em um caminho semelhante a:

```text
storage/app/private/lead-delivery/outbox/lead-42.csv
```

As opções disponíveis são:

```dotenv
LEAD_DELIVERY_LOCAL_CSV_DISK=local
LEAD_DELIVERY_LOCAL_CSV_DIRECTORY=lead-delivery/outbox
```

O driver `local_csv` é destinado somente ao desenvolvimento e à homologação local controlada.

Os arquivos gerados contêm dados do lead e, portanto:

- não devem ser adicionados ao Git;
- não devem ser enviados ou compartilhados sem autorização;
- não devem ser tratados como logs operacionais;
- devem permanecer em armazenamento privado;
- devem ser removidos quando não forem mais necessários para a homologação.

Os testes automatizados confirmam:

- criação do CSV no caminho esperado;
- conteúdo básico do arquivo;
- nome do driver;
- identificador local simulado;
- ausência de status HTTP;
- tratamento de falha de armazenamento;
- classificação da falha como temporária;
- resolução do driver como singleton.

Esse driver não representa o contrato oficial da 3C e não deve ser habilitado em produção. A integração externa continuará desativada até o recebimento da especificação oficial, credenciais e ambiente de testes.

## Teste integrado do fluxo local em CSV

Em 02/09/2026, foi criado o `LocalCsvLeadDeliveryIntegrationTest` para validar o funcionamento conjunto dos componentes internos da entrega local.

Diferentemente dos testes unitários isolados, esse teste percorre o fluxo integrado:

```text
Lead pending
-> LeadDeliveryService
-> LeadDeliveryPayload
-> LocalCsvLeadDeliveryDriver
-> LeadDeliveryCsvEncoder
-> arquivo CSV
-> tentativa succeeded
-> Lead sent
```

O teste utiliza um disco temporário e controlado por `Storage::fake()`. Dessa forma, nenhum arquivo permanente é criado e nenhuma informação é transmitida para serviços externos.

A validação confirma:

- criação inicial do lead com status `pending`;
- resolução do driver `local_csv` pelo contêiner do Laravel;
- transformação do modelo em `LeadDeliveryPayload`;
- geração do conteúdo pelo `LeadDeliveryCsvEncoder`;
- armazenamento do CSV no caminho configurado;
- presença e ordem dos 13 campos esperados;
- preservação dos dados obrigatórios e opcionais;
- criação de uma única tentativa de entrega;
- registro do driver como `local_csv`;
- conclusão da tentativa com status `succeeded`;
- geração do identificador `local-csv-{internal_id}`;
- ausência de status HTTP na simulação local;
- preenchimento dos horários de início e conclusão;
- transição final do lead para o status `sent`.

Resultados obtidos em 02/09/2026:

- teste integrado dedicado: 1 teste e 12 asserções aprovadas;
- conjunto de entrega de leads: 36 testes e 194 asserções aprovadas;
- suíte completa do backend: 82 testes e 372 asserções aprovadas;
- Laravel Pint: 68 arquivos aprovados;
- nenhuma falha identificada.

Essa validação comprova que a estrutura interna consegue processar um lead até a conclusão de uma entrega local simulada. O teste não representa integração real com a 3C e não realiza chamadas de rede.

## Teste integrado da entrega pelo job

Em 03/09/2026, foi criado o `LocalCsvLeadDeliveryJobIntegrationTest` para validar a execução completa da entrega local por meio do `DeliverLeadJob`.

O teste percorre o fluxo:

```text
DeliverLeadJob
-> LeadDeliveryService
-> LeadDeliveryPayload
-> LocalCsvLeadDeliveryDriver
-> LeadDeliveryCsvEncoder
-> arquivo CSV local
-> tentativa succeeded
-> Lead sent
```

A validação utiliza `Storage::fake()` e configura o driver `local_csv` somente no ambiente de testes. Nenhum arquivo permanente é criado e nenhuma comunicação externa é realizada.

O teste confirma:

- resolução do serviço e do driver pelo contêiner do Laravel;
- execução da entrega por meio do `DeliverLeadJob`;
- criação do arquivo CSV no diretório configurado;
- registro da tentativa com o driver `local_csv`;
- conclusão da tentativa com status `succeeded`;
- geração do identificador simulado `local-csv-{internal_id}`;
- transição do lead de `pending` para `sent`;
- manutenção de apenas uma tentativa após uma segunda execução;
- manutenção de apenas um arquivo CSV;
- preservação do conteúdo original do arquivo;
- ausência de processamento duplicado para o mesmo lead.

Resultados obtidos em 03/09/2026:

- teste integrado dedicado ao job: 1 teste e 11 asserções aprovadas;
- testes relacionados ao job: 7 testes e 38 asserções aprovadas;
- conjunto de entrega de leads: 37 testes e 205 asserções aprovadas;
- suíte completa do backend: 83 testes e 383 asserções aprovadas;
- Laravel Pint: 69 arquivos aprovados;
- nenhuma falha identificada.

Essa validação comprova que o job, o serviço de entrega, o driver local e a geração do CSV funcionam em conjunto, preservando a idempotência do processamento. A integração externa continua desativada e nenhum dado é transmitido para a 3C ou para a Kommo.

## Testes integrados de falha, recuperação e limite

Em 04/09/2026, foi criado o `LocalCsvLeadDeliveryFailureIntegrationTest` para validar o comportamento conjunto do job, serviço e driver local quando o armazenamento do CSV apresenta falhas temporárias.

A validação utiliza o `LocalCsvLeadDeliveryDriver` real com um sistema de arquivos simulado e controlado. Nenhum arquivo permanente é criado e nenhuma comunicação externa é realizada.

Foram cobertos três cenários.

### Falha temporária

Quando o armazenamento do CSV falha:

- o driver gera uma exceção classificada como temporária;
- o código `local_csv_storage_failed` é registrado;
- a tentativa recebe o status `failed`;
- o lead passa para o status `retrying`;
- o número da tentativa permanece correto;
- nenhum identificador externo ou status HTTP é criado;
- a conclusão da tentativa é registrada.

### Recuperação após falha

Quando a primeira gravação falha e a segunda é concluída:

- a primeira tentativa permanece registrada como `failed`;
- uma segunda tentativa é criada;
- a segunda tentativa recebe o status `succeeded`;
- o lead passa de `retrying` para `sent`;
- o identificador `local-csv-{internal_id}` é registrado somente na tentativa bem-sucedida;
- o histórico das duas tentativas permanece preservado.

### Limite máximo de tentativas

Quando todas as gravações falham até o limite configurado:

- são criadas somente as tentativas permitidas;
- todas as tentativas permanecem registradas como `failed`;
- o lead recebe o status definitivo `failed`;
- uma nova execução não inicia outro processamento;
- nenhum identificador externo é registrado;
- o sistema não ultrapassa o limite configurado.

Resultados obtidos em 04/09/2026:

- teste integrado dedicado: 3 testes e 39 asserções aprovadas;
- testes conjuntos do job e da entrega local: 10 testes e 77 asserções aprovadas;
- conjunto de entrega de leads: 40 testes e 244 asserções aprovadas;
- suíte completa do backend: 86 testes e 422 asserções aprovadas;
- Laravel Pint: 70 arquivos aprovados;
- nenhuma falha identificada.

Essa cobertura comprova que o processamento local consegue identificar falhas temporárias, preservar o histórico, recuperar o lead em uma nova tentativa e interromper corretamente o fluxo ao atingir o limite configurado.

A integração externa continua desativada e nenhum dado é transmitido para a 3C, para a Kommo ou para qualquer outro serviço.

## Integração externa

O contrato de entrega, o processamento, as tentativas, a fila, a recuperação e a observabilidade estão implementados.

A simulação local em CSV também está disponível para desenvolvimento e homologação controlada, sem transmissão de dados para terceiros.

A etapa dependente da integração externa será criar o driver concreto da 3C após o recebimento das especificações oficiais, credenciais e ambiente de testes.
