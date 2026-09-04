# Visão técnica do projeto — LeadFlow Industrial

> **Status do documento:** rascunho técnico do estado atual.
>
> **Última atualização:** 26/08/2026.
>
> **Finalidade:** apresentar à equipe técnica da 3C a estrutura atual do site, do backend e dos pontos preparados para a futura integração.
>
> **Importante:** este documento não representa o contrato definitivo da integração. As seções relacionadas a webhook, CSV, autenticação, eventos e retornos serão atualizadas após o recebimento da especificação oficial da 3C.

## 1. Objetivo do documento

Este documento registra a arquitetura e o funcionamento atual do projeto LeadFlow Industrial para apoiar o alinhamento técnico com a 3C.

O conteúdo descreve:

- a estrutura do frontend público;
- as formas atuais de captação de leads;
- o backend Laravel preparado para recebimento e processamento;
- os mecanismos internos de segurança, fila e rastreabilidade;
- a fundação implementada para o `External ID`;
- os componentes que ainda dependem das definições oficiais da 3C.

O site público permanece operacional no fluxo atual. Em desenvolvimento local, as três origens de lead já estão conectadas ao endpoint Laravel. O backend ainda não foi publicado, e a entrega externa permanece desativada.

## 2. Visão geral da solução atual

| Componente | Tecnologia / serviço | Situação atual |
|---|---|---|
| Site institucional | HTML5, CSS3 e JavaScript | Publicado e operacional |
| Internacionalização | JavaScript com conteúdo em cinco idiomas | Implementada |
| Blog | Página própria integrada ao conteúdo da plataforma Soro | Implementado |
| Formulário de contato | FormSubmit em produção e API Laravel no ambiente local | Integração local validada |
| Solicitação de amostra | FormSubmit em produção e API Laravel no ambiente local | Integração local validada |
| Atendimento por WhatsApp | API Laravel local seguida da abertura da conversa; abertura direta em produção | Integração local validada |
| API de leads | Laravel 13 e PHP 8.3 | Implementada localmente |
| Persistência local | SQLite | Implementada |
| Banco planejado para produção | MySQL 8 | Preparado, ainda não publicado |
| Entrega externa | Arquitetura de drivers e fila | Preparada, porém desativada |
| Integração com a 3C | Webhook, CSV, retornos e autenticação | Aguardando especificação |

## 3. Frontend público

### 3.1 Estrutura e funcionamento

O frontend é uma aplicação estática, sem framework JavaScript e sem processamento no servidor. A página é entregue diretamente ao navegador por meio de arquivos HTML, CSS, JavaScript e imagens.

A estrutura pública principal é composta por:

- `index.html`: página institucional e comercial;
- `blog/index.html`: página de conteúdo e artigos;
- `css/styles.css`: estilos globais e responsivos;
- `css/blog.css`: estilos específicos do blog;
- `js/app.js`: idioma, navegação, modais, formulários e WhatsApp;
- `js/translations.js`: textos e metadados traduzidos;
- `js/blog.js`: comportamento, acessibilidade e paginação do blog;
- `js/recent-posts.js`: exibição da publicação mais recente na página principal;
- `assets/`: imagens públicas organizadas por finalidade;
- `build-dist.ps1`: geração do pacote destinado à publicação.

A pasta `dist` é gerada automaticamente e contém somente os arquivos públicos necessários ao site. O backend e os documentos internos não são incluídos nesse pacote.

### 3.2 Idiomas e experiência do usuário

O site oferece conteúdo em cinco idiomas:

- português (`pt`);
- inglês (`en`);
- chinês (`zh`);
- árabe (`ar`);
- espanhol (`es`).

O idioma selecionado é armazenado no navegador por meio de `localStorage`. A interface atualiza textos, metadados, campos dos formulários e elementos de navegação sem recarregar a página.

O frontend também possui:

- menu responsivo para computadores, tablets e celulares;
- controle de abertura e fechamento dos modais;
- gerenciamento de foco e navegação por teclado;
- rótulos de acessibilidade nos elementos interativos;
- máscaras brasileiras para telefone e CNPJ;
- entrada internacional adaptada para telefone e registro empresarial;
- conteúdo do blog e publicação em destaque exibidos somente em português.

### 3.3 Formas atuais de captação de leads

O frontend possui três origens de contato:

| Origem | Desenvolvimento local | Produção atual |
|---|---|---|
| Formulário de contato | Envia o lead para `POST /api/leads` com o tipo `contact` | FormSubmit e e-mail da LeadFlow Industrial |
| Solicitação de amostra | Envia o lead para `POST /api/leads` com o tipo `sample_request` | FormSubmit e e-mail da LeadFlow Industrial |
| Modal do WhatsApp | Registra o lead como `whatsapp` e, após a confirmação da API, abre a conversa preenchida | Abre diretamente o WhatsApp corporativo |

No ambiente local, os três fluxos enviam dados em JSON para a API Laravel. Os leads são validados e armazenados com status inicial `pending`.

Os formulários de contato e amostra exibem estado de processamento, confirmação de sucesso e mensagem de erro. O fluxo do WhatsApp aguarda o registro do lead antes de abrir a nova aba, fechar o modal e limpar os campos.

Em produção, o comportamento anterior permanece ativo enquanto o backend ainda não foi publicado e homologado. Nenhum dado captado pelo site público é transmitido atualmente à 3C.

### 3.4 Blog, SEO e publicação

A página principal possui metadados de SEO, endereço canônico, dados para compartilhamento em redes sociais e estrutura JSON-LD.

O blog utiliza um widget externo da plataforma Soro. O frontend:

- carrega os artigos de forma assíncrona;
- apresenta até nove artigos por página;
- controla paginação e estados de carregamento;
- exibe uma mensagem apropriada quando o conteúdo externo está indisponível;
- mostra somente a publicação mais recente na página principal;
- mantém o conteúdo do blog restrito à experiência em português.

A publicação do frontend é preparada pelo script `build-dist.ps1`. O processo recria a pasta `dist` e copia apenas:

- `index.html`;
- ícones personalizados do navegador ainda pendentes;
- `robots.txt`;
- `sitemap.xml`;
- `assets`;
- `css`;
- `js`;
- `blog`.

Arquivos de desenvolvimento, backend, testes e documentos internos permanecem fora do pacote público.

## 4. Backend da aplicação

### 4.1 Tecnologias e finalidade

O backend é uma aplicação separada do frontend, desenvolvida com:

- PHP 8.3 ou superior;
- Laravel 13;
- Composer 2;
- SQLite no desenvolvimento local e nos testes;
- MySQL 8 planejado para produção;
- fila Laravel utilizando o banco de dados.

A finalidade do backend é receber, validar, armazenar e futuramente encaminhar os leads captados pelo site.

O núcleo atual já possui:

- identificação e verificação de saúde do serviço;
- endpoint HTTP para recebimento de leads;
- validação específica para cada origem;
- persistência no banco de dados;
- controle dos estados do lead;
- processamento assíncrono por fila;
- histórico das tentativas de entrega;
- recuperação de processamentos interrompidos;
- tratamento de falhas temporárias e definitivas;
- prevenção de jobs duplicados;
- comandos administrativos e acompanhamento operacional.

A aplicação está implementada e testada localmente. As três origens de lead estão conectadas ao backend no ambiente de desenvolvimento, mas a API ainda não foi publicada nem habilitada no site de produção.

### 4.2 Rotas e endpoints

| Método | Rota | Finalidade | Situação |
|---|---|---|---|
| `GET` | `/` | Identificação básica da API | Implementado |
| `GET` | `/api/health` | Verificação personalizada da disponibilidade | Implementado |
| `GET` | `/up` | Verificação interna de saúde do Laravel | Implementado |
| `POST` | `/api/leads` | Recebimento, validação e armazenamento de leads | Implementado |

O endpoint `POST /api/leads` recebe requisições em JSON e responde com HTTP `201 Created` quando o lead é armazenado corretamente.

A resposta de sucesso contém somente:

- o identificador interno do lead;
- o status inicial `pending`;
- uma mensagem de confirmação.

Dados pessoais completos não são devolvidos na resposta.

### 4.3 Tipos de lead e validação

O campo `type` identifica a origem funcional do lead:

| Tipo | Origem planejada | Regras específicas |
|---|---|---|
| `contact` | Formulário principal | Exige setor e mensagem |
| `sample_request` | Solicitação de amostra | Exige registro empresarial, localização e mensagem |
| `whatsapp` | Modal de atendimento | Exige registro empresarial e quantidade |

Todos os tipos exigem:

- nome;
- e-mail válido;
- empresa;
- telefone;
- idioma;
- página de origem.

Idiomas aceitos: `pt`, `en`, `es`, `zh` e `ar`.

Páginas de origem aceitas: `home` e `blog`.

Os campos possuem limites de tamanho definidos. Dados inválidos retornam HTTP `422` e não são armazenados.

### 4.4 Banco de dados e persistência

O domínio de leads utiliza atualmente duas tabelas principais:

| Tabela | Finalidade |
|---|---|
| `leads` | Armazena os dados recebidos, a origem, o idioma e o estado atual do lead |
| `lead_delivery_attempts` | Registra cada tentativa de entrega externa e o respectivo resultado |

A tabela `leads` armazena:

- tipo e status;
- nome, e-mail, empresa e registro empresarial;
- telefone;
- setor, localização, quantidade e mensagem;
- idioma e página de origem;
- `External ID`, quando associado internamente;
- datas de criação e atualização.

A tabela `lead_delivery_attempts` registra:

- lead relacionado;
- número da tentativa;
- driver utilizado;
- status da tentativa;
- status HTTP, quando disponível;
- identificador externo retornado;
- código e mensagem de erro;
- horários de início e conclusão.

Cada tentativa pertence a um único lead. Quando um lead é excluído, suas tentativas relacionadas também são removidas.

### 4.5 Estados e transições do lead

| Status | Significado |
|---|---|
| `pending` | Lead armazenado e aguardando processamento |
| `processing` | Entrega externa em processamento |
| `retrying` | Falha temporária aguardando nova tentativa |
| `sent` | Entrega concluída |
| `failed` | Falha definitiva ou limite de tentativas atingido |

As mudanças de estado são controladas internamente.

Transições permitidas:

- `pending` para `processing`;
- `processing` para `sent`;
- `processing` para `retrying`;
- `processing` para `failed`;
- `retrying` para `processing`;
- `retrying` para `failed`;
- `failed` para `retrying`.

O estado `sent` é final. Uma transição não autorizada gera uma exceção e não altera o registro.

O cliente que envia o formulário não pode definir diretamente o status do lead.

### 4.6 Fundação do External ID

O backend possui uma fundação interna para armazenar e consultar o identificador externo que será futuramente fornecido pela 3C.

Implementação atual:

- coluna `external_id` na tabela `leads`;
- tipo string com limite de 191 caracteres;
- campo opcional, permitindo valor nulo;
- índice dedicado para consulta e correlação;
- atribuição controlada pelo método interno `assignExternalId()`;
- consulta pelo método interno `resolveByExternalId()`;
- campo mantido fora do preenchimento em massa;
- campo não aceito como entrada pública dos formulários.

A consulta exige exatamente um registro correspondente:

- um identificador válido retorna o lead associado;
- um identificador inexistente gera uma falha explícita;
- um identificador duplicado também gera uma falha explícita;
- o sistema não escolhe silenciosamente um lead quando existe inconsistência.

A coluna ainda não possui restrição de unicidade no banco. Essa decisão depende da confirmação da 3C sobre formato, tamanho, substituição e reutilização do identificador.

Esta fundação não ativa webhook, callback, CSV ou comunicação externa.

### 4.7 Processamento e entrega externa

Após a validação, o `LeadIntakeService` normaliza os dados e cria o lead com status inicial `pending`.

O trabalho de entrega externa somente é despachado quando:

- `LEAD_DELIVERY_ENABLED` está habilitado;
- existe um driver concreto configurado;
- o driver selecionado não é `null`.

Quando a entrega está ativa, o processamento utiliza:

- fila persistida no banco de dados;
- job dedicado para cada lead;
- bloqueio contra jobs duplicados;
- registro individual das tentativas;
- limite configurável de tentativas;
- tratamento separado para falhas temporárias e definitivas;
- recuperação de processamentos interrompidos.

A configuração segura padrão é:

| Configuração | Valor padrão |
|---|---|
| Entrega habilitada | `false` |
| Driver | `null` |
| Tempo limite | 10 segundos |
| Máximo de tentativas | 3 |

Com essa configuração, os leads podem ser recebidos e armazenados, mas nenhum dado é enviado a serviços externos.

### 4.7.1 Payload e codificação CSV

A preparação interna dos dados para entrega utiliza o seguinte fluxo:

```text
Lead -> LeadDeliveryPayload -> LeadDeliveryDriver
```

O `LeadDeliveryPayload` cria uma fronteira entre o modelo persistido e o transporte externo. Ele contém somente os dados necessários para a futura entrega e pode ser convertido em uma matriz associativa por `toArray()`.

O contrato `LeadDeliveryDriver` recebe esse payload em vez do modelo `Lead` completo. Dessa forma, alterações futuras no transporte não precisam expor relacionamentos, métodos ou controles internos do banco de dados.

O `LeadDeliveryCsvEncoder` transforma o payload em uma representação CSV preliminar:

- cria a linha de cabeçalho e a linha de dados;
- utiliza um stream temporário em memória;
- preserva conteúdo em UTF-8;
- trata vírgulas, aspas e quebras de linha;
- representa campos anuláveis como células vazias;
- utiliza vírgula como delimitador padrão;
- aceita outro delimitador de um único caractere;
- rejeita delimitadores inválidos.

Esse componente foi validado com testes automatizados, incluindo campos com quebra de linha e delimitadores inválidos.

### 4.7.2 Driver local de simulação

O `LocalCsvLeadDeliveryDriver` permite exercitar localmente o contrato de entrega sem transmitir dados para terceiros.

O fluxo de simulação é:

```text
Lead
-> LeadDeliveryPayload
-> LocalCsvLeadDeliveryDriver
-> LeadDeliveryCsvEncoder
-> arquivo CSV local privado
```

O driver:

- pode ser selecionado pelo valor `local_csv`;
- utiliza o disco `local` por padrão;
- grava arquivos na pasta `lead-delivery/outbox`;
- cria arquivos no formato `lead-{internal_id}.csv`;
- devolve o identificador simulado `local-csv-{internal_id}`;
- trata falhas de armazenamento como temporárias;
- pode ser resolvido como singleton pelo contêiner do Laravel.

Os arquivos são armazenados em área privada e não devem ser adicionados ao Git, tratados como logs ou compartilhados sem autorização.

O driver local permanece desativado por padrão. A configuração segura continua utilizando:

```dotenv
LEAD_DELIVERY_ENABLED=false
LEAD_DELIVERY_DRIVER=null
```

A simulação local não representa o contrato definitivo da 3C. Cabeçalhos oficiais, ordem dos campos, delimitador, codificação, BOM, método HTTP, `Content-Type`, autenticação e forma de transporte ainda dependem da especificação e da homologação externa.

### 4.7.3 Validação integrada da entrega local

Em 02/09/2026, foi criado um teste de integração para validar o funcionamento conjunto da estrutura local de entrega.

O teste percorre o seguinte fluxo:

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

A execução utiliza `Storage::fake()` para simular o disco local. Nenhum arquivo permanente é criado e nenhuma chamada de rede é realizada.

A validação integrada confirma:

- resolução do driver `local_csv` pelo contêiner;
- transformação do lead em payload de entrega;
- geração do cabeçalho e dos dados do CSV;
- preservação dos 13 campos esperados;
- armazenamento no diretório configurado;
- criação e conclusão da tentativa de entrega;
- geração do identificador `local-csv-{internal_id}`;
- transição do lead de `pending` para `sent`;
- ausência de transmissão para serviços externos.

O teste integrado foi aprovado com 12 asserções. O conjunto completo de entrega passou a possuir 36 testes e 194 asserções aprovadas.

### 4.7.4 Execução da entrega pelo job

Em 03/09/2026, a integração local foi ampliada com um teste que executa a entrega por meio do `DeliverLeadJob`, aproximando a validação do fluxo utilizado pelo processamento em fila.

O fluxo validado é:

```text
DeliverLeadJob
-> LeadDeliveryService
-> LeadDeliveryPayload
-> LocalCsvLeadDeliveryDriver
-> LeadDeliveryCsvEncoder
-> arquivo CSV local privado
```

Ao concluir a execução:

- a tentativa é registrada com o driver `local_csv`;
- a tentativa recebe o status `succeeded`;
- o identificador simulado `local-csv-{internal_id}` é armazenado;
- o lead passa do status `pending` para `sent`;
- o arquivo CSV é criado no diretório configurado.

O teste também executa novamente o mesmo job para o mesmo lead. A segunda execução não cria uma nova tentativa, não gera outro arquivo e não altera o conteúdo já armazenado. Esse comportamento confirma a idempotência interna do processamento e protege o fluxo contra entregas duplicadas.

A validação utiliza armazenamento temporário por meio de `Storage::fake()`. Portanto, nenhum arquivo permanente é criado e nenhum dado é enviado para a 3C, para a Kommo ou para qualquer outro serviço externo.

Em 03/09/2026, os resultados dessa etapa foram:

- teste integrado dedicado ao job: 1 teste e 11 asserções aprovadas;
- testes relacionados ao job: 7 testes e 38 asserções aprovadas;
- conjunto de entrega de leads: 37 testes e 205 asserções aprovadas;
- nenhuma falha identificada.

### 4.7.5 Falhas, recuperação e limite de tentativas

Em 04/09/2026, a validação integrada da entrega local foi ampliada para cobrir falhas temporárias no armazenamento do arquivo CSV.

O teste utiliza o `DeliverLeadJob`, o `LeadDeliveryService` e o `LocalCsvLeadDeliveryDriver` reais, com um sistema de arquivos simulado e controlado.

Foram validados três cenários:

- falha temporária na gravação do CSV, mantendo o lead com status `retrying`;
- recuperação bem-sucedida em uma nova tentativa, com transição final para `sent`;
- falhas sucessivas até o limite configurado, com transição definitiva para `failed`.

A cobertura também confirma:

- registro do código `local_csv_storage_failed`;
- preservação do histórico e da numeração das tentativas;
- criação do identificador externo somente após uma entrega bem-sucedida;
- ausência de status HTTP na simulação local;
- interrupção de novos processamentos após o limite máximo;
- inexistência de tentativas adicionais depois do estado definitivo `failed`.

Resultados obtidos em 04/09/2026:

- teste integrado dedicado: 3 testes e 39 asserções aprovadas;
- testes conjuntos do job e da entrega local: 10 testes e 77 asserções aprovadas;
- conjunto de entrega de leads: 40 testes e 244 asserções aprovadas;
- nenhuma falha identificada.

Nenhum arquivo permanente foi criado e nenhuma comunicação externa foi realizada durante os testes.

### 4.8 Segurança e observabilidade

O backend possui as seguintes camadas de proteção:

- validação específica para cada tipo de lead;
- controle dos campos aceitos;
- proteção contra atribuição pública do status e do `External ID`;
- limite de cinco solicitações por minuto para cada endereço IP;
- campo honeypot contra preenchimento automatizado;
- CORS restrito aos domínios oficiais e às origens locais de desenvolvimento;
- entrega externa desativada por padrão;
- registros operacionais sem exposição de dados pessoais.

Origens atualmente autorizadas pelo CORS:

- `https://leadflow.example`;
- `https://www.leadflow.example`;
- `http://127.0.0.1:5500`;
- `http://localhost:5500`.

O acompanhamento operacional pode ser realizado por comandos administrativos que permitem:

- consultar contagens agregadas dos leads e tentativas;
- simular o despacho dos leads pendentes;
- despachar leads pendentes;
- simular novas tentativas;
- reenviar leads com falha;
- consultar jobs que falharam;
- acompanhar o volume da fila.

Os logs de entrega não devem registrar nome, e-mail, telefone, empresa, CNPJ, mensagem ou o payload completo do lead.

### 4.9 Testes automatizados e qualidade

A última validação completa do backend, executada em 04/09/2026, apresentou:

- 86 testes aprovados;
- 422 asserções aprovadas;
- nenhuma falha;
- todos os 70 arquivos do backend aprovados pelo Laravel Pint.

A cobertura inclui:

- recebimento dos três tipos de lead;
- validação e rejeição de dados inválidos;
- rate limiting;
- CORS;
- honeypot;
- persistência e relacionamentos;
- transições dos estados;
- processamento e recuperação da entrega;
- falhas temporárias e definitivas;
- limite de tentativas;
- prevenção de jobs duplicados;
- comandos administrativos;
- logs sem dados pessoais;
- atribuição e consulta do `External ID`;
- rejeição de identificador inexistente ou duplicado;
- transformação segura de `Lead` em `LeadDeliveryPayload`;
- representação canônica do payload por `toArray()`;
- codificação preliminar em CSV;
- tratamento de vírgulas, aspas, campos anuláveis e quebras de linha;
- uso de delimitador configurável;
- rejeição de delimitadores inválidos;
- armazenamento local e privado do CSV;
- integração entre job, serviço, payload, driver e codificador;
- conclusão da tentativa e transição do lead para `sent`;
- idempotência diante da execução repetida do mesmo job.
- falha temporária no armazenamento local do CSV;
- transição do lead para `retrying` após falha temporária;
- recuperação bem-sucedida em uma nova tentativa;
- preservação do histórico e da numeração das tentativas;
- interrupção do processamento ao atingir o limite máximo;
- ausência de novas tentativas após o estado definitivo `failed`.

Os testes utilizam um banco SQLite temporário em memória e não alteram os dados do ambiente local ou de produção.

## 5. Integração com a 3C

### 5.1 Definições obtidas no kickoff

Durante a reunião de 24/08/2026, foram registrados os seguintes alinhamentos:

- a integração utilizará webhook;
- os dados básicos já capturados pelo site devem atender à primeira etapa;
- a 3C solicitou as informações dos leads em estrutura de tabela CSV;
- a 3C disponibilizará um `External ID` para rastreamento e espelhamento;
- os resultados também poderão ser acompanhados na plataforma da 3C;
- existe um ambiente de testes;
- leads qualificados poderão receber follow-up pelo WhatsApp;
- a operação inicial será direcionada a números nacionais, com foco informado no DDD 11;
- a 3C será responsável pela manutenção do agente e de suas automações;
- a LeadFlow Industrial continuará responsável pelo site e pelo frontend.

Essas definições representam o alinhamento funcional da reunião. Elas ainda não substituem a especificação técnica detalhada necessária para a implementação.

### 5.2 Fluxo funcional esperado

O fluxo apresentado no kickoff prevê as seguintes etapas:

1. o lead informa seus dados em uma das origens do site;
2. os dados são recebidos e preparados para integração;
3. a lista é disponibilizada pelo mecanismo de webhook;
4. a plataforma da 3C inicia a operação de discagem;
5. o agente realiza a conversa e o tratamento de objeções;
6. o contato é classificado como qualificado ou desqualificado;
7. os resultados são associados ao `External ID`;
8. as informações da chamada retornam para acompanhamento;
9. o lead qualificado pode receber uma mensagem de WhatsApp;
10. os status podem ser acompanhados na plataforma e futuramente no sistema interno.

Esse fluxo é funcional e conceitual. A direção exata das requisições, o formato dos retornos e os responsáveis por cada chamada HTTP ainda precisam ser formalizados pela equipe de desenvolvimento da 3C.

### 5.3 Informações aguardadas da 3C

Antes da implementação definitiva, será necessário confirmar:

| Tema | Definição necessária |
|---|---|
| Webhook | Quem fornece cada URL e qual sistema inicia cada requisição |
| Método HTTP | Método, cabeçalhos e tipo de conteúdo esperado |
| CSV | Forma de transmissão, delimitador, codificação, cabeçalhos e exemplo aprovado |
| Campos | Nomes, tipos, obrigatoriedade, limites e tratamento de valores nulos |
| Autenticação | Token, assinatura, segredo compartilhado, certificado ou liberação de IP |
| Resposta | Exemplos completos de sucesso, erro temporário e erro definitivo |
| `External ID` | Formato, tamanho, unicidade, substituição e momento de devolução |
| Retornos | Eventos, status, qualificação, dados da chamada e transcrição |
| Callback | URL, autenticação, repetição, idempotência e confirmação de recebimento |
| Limites | Volume, rate limiting, timeout e disponibilidade esperada |
| Retentativas | Quantidade, intervalo e critérios para repetir uma operação |
| Segurança | Requisitos de armazenamento, privacidade e retenção de dados |
| Números internacionais | Países atendidos, formato e regras operacionais |
| CRM | Destino dos status enquanto a solução comercial é avaliada |

Nenhum nome de campo, código de resposta, regra de autenticação ou evento externo deve ser considerado definitivo antes do recebimento da documentação oficial.

## 6. Infraestrutura e publicação

### 6.1 Hospedagem atual

O frontend está publicado em hospedagem compartilhada e utiliza HTTPS no domínio oficial.

A hospedagem informou disponibilidade de:

- PHP 8.3;
- extensão `pdo_mysql`;
- MySQL 8.0.32;
- banco no host `localhost`;
- porta MySQL `3306`;
- Apache com `mod_rewrite`;
- criação de banco e usuário pelo painel.

Limitações informadas:

- ausência de Composer no servidor;
- ausência de terminal e SSH;
- impossibilidade de alterar livremente o diretório público da aplicação;
- ausência de um gerenciador permanente para o worker da fila.

Essas limitações não afetam o site estático atual, mas precisam ser consideradas para a publicação do backend Laravel.

A contratação de uma VPS não é obrigatória apenas pela existência de um webhook. Entretanto, uma infraestrutura compatível poderá ser necessária para manter fila, worker, callbacks, monitoramento e processamento contínuo.

A decisão definitiva será tomada depois da confirmação do fluxo, do volume, da disponibilidade exigida e das responsabilidades técnicas da integração.

## 7. Responsabilidades

| Área | Responsável atual | Observação |
|---|---|---|
| Site institucional | Diogo Antonio Zarpelão | Manutenção do frontend e dos arquivos públicos |
| Backend LeadFlow Industrial | Diogo Antonio Zarpelão | Desenvolvimento, testes, documentação e futura conexão |
| Formulários do site | Diogo Antonio Zarpelão | Manutenção do fluxo atual e futura migração para a API |
| Agente de voz | 3C | Desenvolvimento, operação e manutenção |
| Automações da plataforma | 3C | Responsabilidade pelos componentes internos |
| Credenciais internas da plataforma | 3C | Não fazem parte do código da LeadFlow Industrial |
| Webhook da LeadFlow Industrial | A definir tecnicamente | Implementação e proteção dependerão do contrato |
| CRM | Decisão da organização demonstrativa | Proposta comercial será avaliada pela equipe responsável |
| Infraestrutura do backend | Decisão conjunta | Será definida conforme os requisitos finais |
| Suporte da plataforma | 3C | Atendimento pelos canais informados no kickoff |

Credenciais, tokens, chaves e senhas não devem ser registrados nesta documentação nem armazenados no repositório.

## 8. Estado de implementação

| Item | Implementado | Publicado / ativo |
|---|---:|---:|
| Frontend institucional | Sim | Sim |
| Blog e publicação em destaque | Sim | Sim |
| FormSubmit para contato e amostra | Sim | Sim |
| Atendimento direto por WhatsApp | Sim | Sim |
| Endpoint `POST /api/leads` | Sim | Não |
| Integração dos três formulários com a API | Sim | Não |
| Persistência dos leads | Sim | Não |
| Validação por tipo de lead | Sim | Não |
| Fila e tentativas de entrega | Sim | Não |
| Recuperação e tratamento de falhas | Sim | Não |
| Comandos e logs operacionais | Sim | Não |
| Preparação isolada do payload de entrega | Sim | Não |
| Geração de conteúdo CSV | Sim | Não |
| Driver local de simulação em CSV | Sim | Não |
| Armazenamento do `External ID` | Sim | Não |
| Consulta e correlação pelo `External ID` | Sim | Não |
| Driver específico da 3C | Não | Não |
| Transmissão externa do CSV | Não | Não |
| Callback para retornos da 3C | Não | Não |
| Integração com a Kommo | Não | Não |
| Infraestrutura definitiva do backend | Não | Não |

Os recursos marcados como implementados e não publicados existem no código local e no repositório, mas ainda não participam do fluxo público do site.

Em desenvolvimento local, os formulários de contato, solicitação de amostra e WhatsApp já enviam os leads para a API Laravel. Os três fluxos foram validados com persistência no banco SQLite e status inicial `pending`.

Em produção, os formulários de contato e amostra continuam utilizando o FormSubmit. O atendimento por WhatsApp continua abrindo diretamente a conversa corporativa. Esse comportamento será mantido até a publicação e a homologação do backend.

A entrega externa permanece desativada e o driver padrão continua configurado como `null`. O `LocalCsvLeadDeliveryDriver` está implementado somente para simulação controlada em desenvolvimento, mas não está ativo por padrão. Nenhum dado é transmitido atualmente à 3C ou à Kommo. Essa restrição evita assumir contratos, formatos e regras ainda não confirmados pelas equipes responsáveis.

## 9. Próximos passos

A evolução planejada deve seguir esta ordem:

1. receber a documentação técnica e os exemplos oficiais da 3C;
2. confirmar a direção do webhook e os responsáveis por cada requisição;
3. validar campos, formato CSV, autenticação e respostas;
4. definir as regras do `External ID` e dos eventos de retorno;
5. ajustar a documentação deste projeto com as decisões confirmadas;
6. implementar o driver específico da 3C;
7. implementar o callback protegido, caso seja necessário;
8. configurar o ambiente de testes;
9. executar testes de sucesso, falha, repetição e indisponibilidade;
10. definir a infraestrutura definitiva do backend;
11. publicar o backend com a entrega externa desativada;
12. conectar gradualmente os formulários do frontend;
13. homologar o fluxo completo;
14. ativar a integração somente após o aceite técnico.

Cada etapa deve ser testada e documentada antes da ativação da etapa seguinte.

## 10. Documentos relacionados

| Documento | Finalidade |
|---|---|
| `backend/README.md` | Visão operacional e instruções gerais do backend |
| `backend/docs/lead-api-contract.md` | Contrato atual do endpoint de recebimento de leads |
| `backend/docs/lead-delivery-operations.md` | Configuração, fila, comandos, logs e operação da entrega |
| Fluxograma apresentado em 24/08/2026 | Representação funcional do agente e do tratamento dos leads |
| Resumo e transcrição do kickoff | Registro das decisões, responsabilidades e pendências |

Os documentos do backend descrevem o comportamento interno atual. O fluxograma e o resumo da reunião representam os alinhamentos funcionais realizados com a 3C.

Quando a especificação oficial for recebida, este documento e o contrato da API deverão ser revisados em conjunto.

## 11. Conclusão técnica

O site público da LeadFlow Industrial está operacional e continua utilizando os canais atuais de contato.

O backend possui uma base funcional para receber, validar, armazenar e processar leads com segurança. A arquitetura de fila, tentativas, recuperação, logs e rastreabilidade já está implementada e protegida por testes.

A fundação do `External ID` também está concluída, permitindo futura associação entre o lead interno e os resultados retornados pela 3C.

As etapas restantes dependem principalmente da documentação oficial da integração, dos exemplos de comunicação, das regras de segurança e do ambiente de testes.

Até que essas informações sejam recebidas e homologadas:

- a entrega externa permanecerá desativada;
- o driver continuará configurado como `null`;
- a conexão dos formulários com o backend permanecerá restrita ao ambiente local;
- nenhum webhook ou callback será publicado;
- nenhum dado será transmitido à 3C.

Este documento deverá evoluir junto com o projeto e receber uma revisão definitiva antes de ser utilizado como documentação final da integração.