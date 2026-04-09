
# oiiiiiiiiiiiiiiii
# Moda Tropical - README Tecnico Completo

> Ultima atualizacao: `2026-04-09`
>
> Raiz do projeto: `/var/www/modatropical`
>
> Ambiente principal documentado aqui: VPS em producao

---

## 1. Objetivo deste README

Este arquivo existe para ser a documentacao tecnica mais completa possivel do projeto **Moda Tropical** no estado atual.

O foco aqui e:

- explicar o que o projeto faz hoje
- mapear a estrutura real do repositorio
- registrar a stack e a forma como a aplicacao sobe
- documentar os modulos publicos e administrativos
- detalhar o modulo de mensagens, que hoje e a parte mais sensivel do sistema
- registrar o que ja foi corrigido nos ultimos dias
- deixar claro o que foi validado na pratica
- explicar quais sao os gargalos atuais
- registrar para onde estamos indo agora: `Fila + SMTP persistente + 2 workers + cache/render otimizado`

Este README nao pretende esconder a complexidade do projeto. Pelo contrario: ele serve para reduzir dependencia de memoria, conversa solta e contexto perdido.

---

## 2. Resumo executivo

### 2.1 O que e o projeto

O Moda Tropical e uma loja virtual propria, sem framework PHP grande por baixo, com:

- vitrine publica
- area do cliente
- carrinho e checkout
- integracoes de pagamento
- painel administrativo
- modulo de mensagens promocionais com notificacao interna e email

### 2.2 Stack principal

- PHP procedural
- MariaDB/MySQL
- Nginx
- PHP-FPM
- JavaScript vanilla no frontend
- Fabric.js no editor de mensagens V2
- Node.js + Puppeteer + Sharp no pipeline de render de artes de email

### 2.3 Estado geral hoje

O sistema principal da loja esta operacional.

O modulo de mensagens passou por uma sequencia grande de correcoes entre `2026-04-08` e `2026-04-09` e hoje esta em um estado **muito melhor** do que estava:

- o projeto salvo volta a abrir sem deformar a cena
- a arte final do email agora renderiza titulo + corpo + hotspot corretamente
- o Gmail deixou de perder o link principal em campanhas com hotspot unico
- o editor V2 esta usavel para mover, redimensionar e editar texto
- existe log vivo no admin para diagnosticar os eventos do editor em tempo real

### 2.4 Maior gargalo atual

O envio de email ainda e:

- sincronizado no request web
- sequencial
- com render por cliente
- com conexao SMTP aberta e fechada em cada envio

Na pratica, para `200` clientes, o comportamento atual tende a ficar em algo como `35 a 50 minutos`, dependendo do peso do render e da latencia do SMTP.

### 2.5 Proxima fase aprovada

O proximo passo tecnico escolhido e:

- `Fila + SMTP persistente + 2 workers + cache/render otimizado`

Importante:

- isso **nao** remove a personalizacao por cliente
- `{{primeiro_nome}}` continua chegando como `Lucas`, `Marina`, etc.
- o que muda e a arquitetura de processamento, nao a logica de personalizacao

---

## 3. O que o sistema entrega

### 3.1 Loja publica

O frontend publico entrega:

- pagina inicial com destaque e promocoes
- navegacao por categoria
- navegacao por marca
- pagina de produto
- busca
- vitrine de promocoes
- carrinho
- checkout

### 3.2 Area do cliente

O cliente pode:

- criar conta
- entrar / sair
- verificar email
- redefinir senha
- editar dados
- editar enderecos
- acompanhar pedidos
- ver cupons
- ver notificacoes
- salvar favoritos / itens

### 3.3 Painel admin

O painel administrativo cobre:

- dashboard
- produtos
- categorias
- marcas
- sabores
- tamanhos
- pedidos
- clientes
- cupons
- configuracoes da loja
- mensagens promocionais

### 3.4 Integracoes

O projeto contem integracoes para:

- SMTP
- PagBank
- Asaas
- login social Google
- login social Facebook
- login social Apple
- configuracao TikTok

---

## 4. Filosofia e estilo do codigo

### 4.1 Arquitetura de aplicacao

Hoje o sistema e majoritariamente:

- PHP procedural
- sem Laravel
- sem Symfony
- sem CodeIgniter
- sem Composer como base do projeto

Isso significa que:

- a aplicacao e muito direta
- a curva de leitura dos arquivos e curta
- mas a responsabilidade arquitetural recai muito sobre organizacao manual de includes, convencoes e disciplina

### 4.2 Boot unico

O arquivo central de bootstrap e:

- `includes/bootstrap.php`

Ele:

- inicia sessao
- define timezone da aplicacao como `America/Sao_Paulo`
- resolve `APP_URL`
- define `BASE_PATH`
- carrega `config/*.php`
- carrega os includes principais
- garante diretorios de upload/storage essenciais

### 4.3 Configuracao

A configuracao do sistema ainda esta baseada em arquivos PHP com constantes:

- `config/database.php`
- `config/mail.php`
- `config/asaas.php`
- `config/pagbank.php`
- `config/google.php`
- `config/facebook.php`
- `config/apple.php`
- `config/tiktok.php`

Isso funciona, mas e um debito tecnico importante:

- os segredos ainda estao acoplados ao filesystem da aplicacao
- o ideal futuro e migrar para variaveis de ambiente ou cofre de segredos

### 4.4 Sem test suite automatica hoje

Hoje o projeto **nao** tem:

- PHPUnit configurado
- suite de integracao automatica
- teste end-to-end versionado

O processo atual de validacao e baseado em:

- `php -l`
- `node --check`
- testes manuais no navegador
- testes reais de envio
- leitura do log do editor/mensagem

---

## 5. Ambiente e runtime reais da VPS

### 5.1 Sistema operacional

Validado no servidor:

- hostname: `vmi3172503`
- OS: `Ubuntu 24.04.4 LTS`
- kernel: `Linux 6.8.0-100-generic`

### 5.2 Servicos principais

Servicos conferidos como ativos:

- `nginx`
- `php8.3-fpm`
- `mysql`

### 5.3 Versoes principais

Verificado no servidor:

- PHP CLI: `8.3.6`
- Node.js: `v18.19.1`
- npm: `9.2.0`

### 5.4 Modulos PHP relevantes

Confirmados no ambiente:

- `curl`
- `gd`
- `PDO`
- `pdo_mysql`

Observacao importante:

- `mbstring` **nao** esta presente no ambiente atual

Por isso o codigo ja contem varios fallbacks para operacoes de string.

### 5.5 PHP CLI x PHP-FPM

Os limites do CLI e do FPM nao sao iguais.

CLI:

- `max_execution_time = 0`
- `memory_limit = -1`
- `post_max_size = 8M`
- `upload_max_filesize = 2M`

FPM:

- `max_execution_time = 30`
- `memory_limit = 128M`
- `post_max_size = 8M`
- `upload_max_filesize = 5M`

Impacto pratico:

- o navegador usa FPM, entao upload e execucao web obedecem o FPM
- testes via terminal podem parecer mais permissivos do que o ambiente web

### 5.6 Nginx

O vhost ativo esta em:

- `/etc/nginx/sites-available/modatropical`

Pontos importantes verificados:

- `server_name modatropical.store`
- `root /var/www/modatropical`
- `index index.php index.html`
- `client_max_body_size 8M`
- `try_files $uri $uri/ /index.php?$query_string`
- PHP via socket `unix:/var/run/php/php8.3-fpm.sock`
- HTTPS com Let's Encrypt

Tambem existe um proxy interno:

- `location /code/ -> http://127.0.0.1:8080/`

### 5.7 Browser local para render

O projeto possui browser local instalado em:

- `.local-browser/chrome/linux-147.0.7727.55/chrome-linux64/chrome`

E o atalho usado pelo renderer resolve para:

- `/usr/local/bin/modatropical-chromium`

Isso e importante porque o pipeline de render do email depende de Chromium/Puppeteer.

---

## 6. Estrutura real do repositorio

### 6.1 Pastas principais

As pastas principais observadas na raiz sao:

- `admin/`
- `assets/`
- `config/`
- `database/`
- `includes/`
- `node_modules/`
- `oauth/`
- `scripts/`
- `storage/`
- `templates/`
- `tmp_codex_uploads/`
- `uploads/`
- `.local-browser/`

### 6.2 Paginas publicas na raiz

Arquivos PHP publicos observados:

- `index.php`
- `busca.php`
- `categoria.php`
- `marca.php`
- `produto.php`
- `promocoes.php`
- `carrinho.php`
- `finalizar-pedido.php`
- `rastreio.php`
- `cadastro.php`
- `entrar.php`
- `sair.php`
- `completar-cadastro.php`
- `editar-contato.php`
- `editar-enderecos.php`
- `esqueci-senha.php`
- `redefinir-senha.php`
- `verificar-email.php`
- `minha-conta.php`
- `meus-pedidos.php`
- `meus-cupons.php`
- `favoritos.php`
- `itens-salvos.php`
- `notificacoes.php`
- `notificacoes-widget.php`
- `imagem-critica.php`
- `asaas-webhook.php`
- `pagbank-webhook.php`
- `pagbank-homologacao.php`
- `politica-de-privacidade.php`
- `termos-de-servico.php`
- `exclusao-de-dados.php`

### 6.3 Artefatos de backup no repositorio

Existem arquivos de backup gerados durante trabalho recente, por exemplo:

- `carrinho.php.codexbak-20260405154630`
- `assets/js/app.js.codexbak-20260405154630`
- `assets/css/public.css.codexbak-20260405154630`

Esses arquivos **nao** sao o fluxo principal da aplicacao e nao devem ser tratados como fonte canonica.

---

## 7. Mapa dos modulos

### 7.1 `admin/`

Arquivos confirmados:

- `index.php`
- `login.php`
- `logout.php`
- `produtos.php`
- `produto_form.php`
- `categorias.php`
- `categoria_form.php`
- `marcas.php`
- `marca_form.php`
- `sabores.php`
- `sabor_form.php`
- `tamanhos.php`
- `tamanho_form.php`
- `pedidos.php`
- `clientes.php`
- `cliente_form.php`
- `cupons.php`
- `cupom_form.php`
- `configuracoes.php`
- `mensagens.php`

Papeis:

- dashboard e indicadores
- CRUD de catalogo
- operacao de pedidos
- manutencao de clientes
- manutencao de cupons
- configuracao da loja
- disparo de mensagens

### 7.2 `includes/`

Arquivos relevantes:

- `bootstrap.php`
- `functions.php`
- `auth.php`
- `mailer.php`
- `storefront.php`
- `orders.php`
- `notifications.php`
- `coupons.php`
- `customer_messages.php`
- `customer_message_scene.php`
- `customer_addresses.php`
- `customer_verification.php`
- `customer_favorites.php`
- `asaas.php`
- `pagbank.php`
- `google_auth.php`
- `social_auth.php`
- `social_auth_cluster.php`

Papeis resumidos:

- `bootstrap.php`: inicializacao da app
- `functions.php`: helpers gerais, URL, assets, imagens criticas, utilitarios
- `auth.php`: autenticacao e sessao
- `mailer.php`: envio SMTP e fallback em log
- `storefront.php`: catalogo, busca, agrupamento e dados da vitrine
- `orders.php`: regras de status, rastreio, pagamento e consolidacao de pedidos
- `notifications.php`: notificacoes internas do cliente
- `coupons.php`: cupons, escopo, validade, resgate e carteira
- `customer_messages.php`: tokenizacao, composicao de email, render e envio
- `customer_message_scene.php`: normalizacao da cena, compatibilidade entre legado e V2
- `asaas.php` e `pagbank.php`: integracoes de pagamento

### 7.3 `assets/`

Subpastas:

- `assets/css/`
- `assets/js/`
- `assets/img/`
- `assets/vendor/`

Arquivos-chave:

- `assets/css/public.css`
- `assets/css/admin.css`
- `assets/js/app.js`
- `assets/js/admin.js`
- `assets/js/admin-message-editor.js`
- `assets/js/admin-message-editor-v2.js`
- `assets/js/message-scene-renderer.js`
- `assets/vendor/fabric.min.js`

### 7.4 `scripts/`

Scripts confirmados:

- `scripts/render_composicao.js`
- `scripts/render_message_scene.mjs`
- `scripts/render_message_scene.m`
- `scripts/pagbank_homologacao_checkout.php`

O que interessa hoje:

- `render_composicao.js` e o renderer principal usado no pipeline atual de composicao do email
- `render_message_scene.mjs` e o renderer browser-based auxiliar
- `render_message_scene.m` parece ser um artefato legado/auxiliar e nao o caminho principal atual

### 7.5 `templates/`

Hoje existe:

- `templates/message-renderer.html`

Esse template e carregado pelo renderer browser/Puppeteer para montar a cena em HTML antes da captura.

### 7.6 `database/`

Arquivos principais:

- `database/cardapio_digital.sql`
- `database/vps_fresh_install.sql`
- `database/update_*.sql`

O projeto usa SQL manual versionado por arquivo, nao um sistema de migrations com framework.

### 7.7 `storage/`

Subpastas confirmadas:

- `storage/apple`
- `storage/google`
- `storage/logs`
- `storage/mail`
- `storage/messages`
- `storage/messages/emoji-cache`
- `storage/messages/render-runtime`
- `storage/messages/render-tmp`

Uso:

- `storage/mail`: fallback/log de emails
- `storage/messages`: projetos, cache e runtime do modulo de mensagens
- `storage/logs`: artefatos tecnicos e logs auxiliares

Permissoes observadas:

- `storage`: `root:www-data drwxrwsr-x`
- `storage/logs`: `root:www-data drwxrwsr-x`
- `storage/mail`: `www-data:www-data drwxrwsr-x`
- `storage/messages`: `www-data:www-data drwxrwsr-x`

### 7.8 `uploads/`

Subpastas confirmadas:

- `uploads/brands`
- `uploads/messages`
- `uploads/products`
- `uploads/store`

Uso:

- imagens de marca
- imagens de produto
- imagens da loja
- arte final e insumos do modulo de mensagens

Permissoes observadas:

- `uploads`: `www-data:www-data drwxrwxr-x`
- `uploads/messages`: `www-data:www-data drwxrwxr-x`

---

## 8. Banco de dados

### 8.1 Banco principal

Configurado hoje em:

- `config/database.php`

Banco principal:

- `cardapio_digital`

Charset:

- `utf8mb4`

### 8.2 Grupos de tabelas principais

A partir do schema principal, os grupos de tabelas hoje sao:

#### Administracao e configuracao

- `admins`
- `configuracoes`

#### Clientes e identidade

- `clientes`
- `cliente_identities`
- `cliente_email_verificacoes`
- `cliente_email_alteracoes`
- `cliente_enderecos`
- `cliente_password_resets`
- `cliente_remember_tokens`

#### Catalogo

- `categorias`
- `marcas`
- `sabores`
- `produtos`
- `produto_sabores`
- `produto_imagens`

#### Cupons

- `cupons`
- `cupom_produtos`
- `cupom_marcas`
- `cliente_cupons`

#### Notificacoes

- `cliente_notificacoes`

#### Pedidos

- `pedidos`
- `pedido_itens`
- `pedido_historico`

### 8.3 O que ainda nao existe no banco

Hoje **nao** existem ainda, pelo menos versionados aqui, tabelas como:

- `message_batches`
- `message_batch_jobs`
- `message_send_log`

Essas tabelas farao parte da proxima fase da arquitetura de fila.

### 8.4 Strategia atual de mudanca de schema

O projeto faz evolucao do banco via arquivos SQL nomeados manualmente, por exemplo:

- `update_clientes.sql`
- `update_google_login.sql`
- `update_cupons_notificacoes_descontos.sql`
- `update_pagbank_pix.sql`
- `update_pedidos_rastreio.sql`

Isso funciona, mas exige disciplina:

- aplicar na ordem correta
- registrar o que entrou e quando
- evitar drift entre ambientes

---

## 9. Configuracao e segredos

### 9.1 Estado atual

Hoje o projeto ainda mantem segredos sensiveis em arquivos versionaveis/servidor dentro de `config/*.php`.

Isso inclui:

- banco
- SMTP
- gateways de pagamento
- social login

### 9.2 Regra de documentacao

Este README pode e deve citar:

- onde os segredos moram
- quais arquivos os contem
- quais riscos existem

Mas este README **nao deve copiar**:

- senhas
- tokens
- app passwords
- chaves privadas

### 9.3 Debito tecnico importante

Uma melhoria futura desejavel e:

- mover configuracao sensivel para variaveis de ambiente
- ou algum mecanismo de secret management

Hoje isso **nao** esta resolvido.

---

## 10. Fluxos principais da aplicacao

### 10.1 Loja e vitrine

O modulo de vitrine, via `includes/storefront.php`, cobre:

- busca de categorias ativas
- busca de marcas ativas
- busca de produtos ativos
- agrupamento por categoria e marca
- truncamento de nome curto
- busca por slug
- normalizacao de busca com alias

Paginas envolvidas:

- `index.php`
- `categoria.php`
- `marca.php`
- `produto.php`
- `busca.php`
- `promocoes.php`

### 10.2 Carrinho e checkout

Arquivos principais:

- `carrinho.php`
- `finalizar-pedido.php`

O sistema suporta:

- compra com entrega
- retirada na loja
- pagamento offline
- pagamento online
- rastreio/status do pedido

### 10.3 Pedidos

Em `includes/orders.php` existem regras importantes para:

- status do pedido
- status do pagamento
- codigo de rastreio
- forma de retirada/entrega
- troco em dinheiro
- snapshot de endereco

### 10.4 Notificacoes internas

Em `includes/notifications.php` o sistema oferece:

- busca de notificacoes do cliente
- contador de nao lidas
- marcar como lidas
- apagar individualmente
- apagar tudo
- criar notificacao para um cliente
- criar notificacao em massa

Essa parte e importante porque o modulo de mensagens usa notificacao interna alem do email.

### 10.5 Cupons

Em `includes/coupons.php` existem regras para:

- tipo de cupom
- escopo do cupom
- normalizacao de codigo
- limites de resgate
- carteira de cupons do cliente
- enriquecimento de estatisticas de uso

### 10.6 Pagamentos

#### Asaas

`includes/asaas.php` contem:

- detecao de ambiente sandbox/producao
- construcao de URL publica/webhook
- request HTTP para API Asaas
- funcoes auxiliares de checkout
- consolidacao de dados do cliente/endereco

#### PagBank

`includes/pagbank.php` contem:

- token por ambiente
- request HTTP autenticado
- URL publica/webhook/redirect
- normalizacao de dados
- montagem do payload do checkout

Tambem existe:

- `scripts/pagbank_homologacao_checkout.php`
- `pagbank-homologacao.php`
- `pagbank-webhook.php`

---

## 11. Modulo de mensagens: estado atual completo

Esta e hoje a parte mais importante do projeto do ponto de vista de engenharia recente.

### 11.1 Objetivo do modulo

O modulo de mensagens foi construido para permitir campanhas como:

- promo geral para todos os clientes
- campanha para clientes sem pedido ha 45 dias
- campanha individual
- boas-vindas
- alerta de estoque

Combinando:

- notificacao interna
- email
- layout visual com arte de fundo
- textos personalizados
- CTA visual e/ou hotspot clicavel

### 11.2 Arquivos principais do modulo

Arquivos-chave:

- `admin/mensagens.php`
- `includes/customer_messages.php`
- `includes/customer_message_scene.php`
- `assets/js/admin-message-editor-v2.js`
- `assets/js/message-scene-renderer.js`
- `scripts/render_composicao.js`
- `scripts/render_message_scene.mjs`
- `templates/message-renderer.html`

### 11.3 Armazenamento de projeto no admin

Os projetos de mensagem sao persistidos hoje em:

- `storage/messages/projects.json`

Cada projeto armazena, entre outros:

- `project_id`
- `project_name`
- `title`
- `message`
- `hero_image_path`
- `scene_json`
- `fabric_scene_json`
- `editor_layers_json`
- flags de visibilidade
- posicoes e tamanhos herdados do legado

### 11.4 Formatos de estado importantes

Hoje o editor trabalha com tres representacoes principais:

#### 1. `scene_json`

Representa a cena normalizada:

- canvas
- background
- layers
- actions

#### 2. `fabric_scene_json`

Representa a versao mantida pelo editor V2/Fabric.

#### 3. `editor_layers_json`

Representa a camada de compatibilidade com o formato legado.

### 11.5 Camada de compatibilidade legado x V2

`includes/customer_message_scene.php` existe exatamente para isso.

Ele faz:

- defaults da cena
- normalizacao de numeros e booleanos
- normalizacao de cores, sombra, alinhamento
- conversao de contexto legado para cena
- conversao de cena para editor layers
- heuristica para preferir o legado normalizado quando a cena moderna parece incoerente

Isso foi essencial nos ultimos ajustes porque existia um problema real em projetos salvos:

- algumas cenas reabriam com canvas `800x1100`
- mas o fundo real e o layout esperavam `800x702`
- o resultado era cena deslocada, achatada ou reescalada de modo ruim

### 11.6 Tokenizacao

Em `includes/customer_messages.php` existem funcoes centrais:

- `customer_message_tokens(...)`
- `customer_message_apply_tokens(...)`
- `customer_message_prepare_content(...)`

Tokens suportados hoje incluem:

- `{{nome}}`
- `{{primeiro_nome}}`
- `{{loja}}`
- `{{saudacao_sumido}}`
- `{{sumido_ou_sumida}}`
- `{{bem_vindo_ou_vinda}}`
- `{{promocoes_url}}`
- `{{loja_url}}`
- `{{produto_nome}}`
- `{{produto_url}}`

Exemplo:

- texto salvo: `Faaala, {{primeiro_nome}}! Aquele MEGA PROMOCAO que voce queria, acabou de chegar!`
- cliente final: `Faaala, Lucas! Aquele MEGA PROMOCAO que voce queria, acabou de chegar!`

### 11.7 Regra crucial sobre personalizacao visual

Existe uma diferenca importante entre:

- texto personalizado no HTML
- texto personalizado dentro da arte renderizada

Se `{{primeiro_nome}}` estiver **dentro da arte**, entao:

- o render final visual muda por cliente
- nao podemos reutilizar cegamente a mesma imagem para todo mundo

Se o token estiver so no HTML fora da arte, entao:

- muito mais coisa pode ser cacheada/reaproveitada

Essa regra influencia diretamente a arquitetura futura da fila e do cache.

### 11.8 Fluxo atual de preparacao de conteudo

O fluxo atual, em alto nivel, e:

1. O admin monta titulo, mensagem, imagem, layers e links.
2. O sistema resolve tokens por cliente.
3. O sistema monta o contexto de editor.
4. O sistema monta a cena final.
5. O sistema constroi o HTML do email.
6. O sistema envia notificacao interna, email ou ambos.

### 11.9 Render da arte do email

Hoje o render segue uma cadeia de fallback:

1. `customer_message_scene_render_composition(...)`
2. `customer_message_scene_render_browser(...)`
3. `customer_message_editor_render_flat_art(...)`

#### `render_composition`

Usa:

- `scripts/render_composicao.js`
- `sharp`
- `puppeteer-core`

Esse caminho:

- recebe `scene.json`
- recebe `tokens-file`
- renderiza a arte final
- gera hotspots
- exporta imagem final para `uploads/messages`

#### `render_browser`

Usa:

- `scripts/render_message_scene.mjs`
- `templates/message-renderer.html`
- `assets/js/message-scene-renderer.js`

Esse caminho monta a cena em HTML e tira screenshot.

#### `flat_art`

Usa GD como ultimo fallback.

### 11.10 Montagem do HTML do email

`customer_message_build_editor_email_html(...)` e uma das pecas centrais do envio.

Ela:

- chama o renderer
- recupera URL da imagem final
- calcula largura/altura de exibicao
- injeta hotspots clicaveis quando aplicavel
- monta fallback de link de imagem inteira quando necessario
- devolve o bloco final do email

### 11.11 Hotspots e CTA

Hoje o sistema suporta:

- botao visual
- hotspot invisivel
- link principal

Durante os testes recentes, descobrimos que depender so de overlay absoluto para hotspot no email era arriscado em clientes como Gmail.

Por isso o pipeline atual ganhou fallback:

- se houver um unico hotspot principal, a imagem inteira pode virar link tambem

Isso melhorou a confiabilidade do clique.

### 11.12 Fluxo atual de envio no admin

Hoje, em `admin/mensagens.php`, o envio acontece no proprio request HTTP.

Em alto nivel:

1. Admin escolhe destinatarios.
2. `admin_message_target_customers(...)` resolve os clientes.
3. O sistema entra em um `foreach`.
4. Para cada cliente chama `customer_message_send(...)`.
5. `customer_message_send(...)` prepara conteudo, renderiza email, envia notificacao e/ou email.
6. So no fim a pagina responde.

### 11.13 Gargalos estruturais atuais

Esse desenho tem quatro gargalos importantes:

#### Gargalo 1. Request web faz trabalho pesado demais

O clique do admin segura:

- resolucao do lote
- tokenizacao
- render
- SMTP
- consolidacao de resultados

#### Gargalo 2. Tudo e sequencial

Hoje o envio roda um cliente por vez.

#### Gargalo 3. Render por cliente

Quando a arte usa token visual, o renderer e executado para cada cliente.

#### Gargalo 4. SMTP por cliente

`includes/mailer.php` hoje:

- abre socket
- EHLO
- AUTH
- MAIL FROM
- RCPT TO
- DATA
- QUIT

para **cada** email enviado.

### 11.14 Estimativa de tempo no estado atual

Com base nos testes recentes:

- o custo real observado ficou em torno de `10 a 15 segundos` por cliente em cenarios com email

Isso leva a algo como:

- `35 a 50 minutos` para `200` clientes

Esse numero nao e um benchmark cientifico; e uma leitura pratica de uso real.

---

## 12. Editor de mensagens V2: o que foi feito recentemente

Esta secao registra o trabalho tecnico recente de forma historica.

### 12.1 Contexto

O editor V2 passou por varias iteracoes intensas entre `2026-04-08` e `2026-04-09`.

O objetivo era sair de um estado instavel para um estado usavel, com:

- preview fiel
- persistencia correta
- render final correto no email
- edicao de texto menos frustrante
- diagnostico em tempo real

### 12.2 Problema 1: cena salva reabria deformada

Sintoma observado:

- ao salvar e reabrir projeto, o fundo e os textos podiam voltar desalinhados
- havia projetos com `sceneHeight` voltando para `1100` mesmo quando o layout pratico era `702`

O que foi feito:

- reforco na normalizacao da cena
- uso mais cuidadoso do legado normalizado quando a cena moderna parecia inconsistente
- manutencao do `backgroundImage` correto no boot

Resultado:

- o projeto salvo passou a reabrir de forma coerente no canvas

### 12.3 Problema 2: email final renderizava so o titulo

Sintoma observado no log:

- a imagem de fundo vinha
- o titulo aparecia
- o corpo nao vinha
- o hotspot nao ficava confiavel

Diagnostico:

- o compositor estava sendo chamado com filtro de texto restritivo
- o log apontava `textFilter: "tokenized"`
- isso favorecia apenas camadas com token, e no caso pratico estava sobrando so o titulo

Correcao aplicada:

- o renderer passou a usar `--text-filter all`

Resultado:

- a arte final do email passou a incluir titulo + corpo + demais textos esperados

### 12.4 Problema 3: link invisivel nao era confiavel em clientes de email

Sintoma:

- hotspot existia no render
- mas o clique nao era confiavel em alguns clientes

Correcao:

- quando existe um unico hotspot principal, a imagem inteira ganha fallback de link

Resultado:

- Gmail e outros cenarios ficam mais robustos

### 12.5 Problema 4: painel lateral de propriedades ficou pesado e confuso

Foi testado um inspector lateral mais completo, estilo propriedades de camada.

Resultado do teste:

- funcionalmente ajudava
- visualmente ficou confuso
- o usuario nao gostou da experiencia

Decisao:

- o painel lateral foi removido
- o editor ficou focado no canvas e na interacao direta

### 12.6 Problema 5: edicao nativa do Fabric era ruim

Sintomas:

- nao escrevia onde o usuario esperava
- o cursor era ruim
- espaco dava comportamento estranho
- a experiencia parecia quebrada

Correcao:

- abandonamos a edicao de texto interna do `fabric.Textbox`
- foi criada uma `textarea` HTML sobreposta ao texto selecionado

Resultado:

- digitacao ficou com comportamento de navegador real
- copiar/colar, espaco e quebra de linha ficaram previsiveis

### 12.7 Problema 6: duplo clique duplicava/ghostava o texto

Sintoma:

- ao entrar em modo de edicao, o texto parecia duplicado por cima dele mesmo

Diagnostico:

- existiam dois caminhos de `dblclick`
- um vindo do DOM
- outro vindo do Fabric

Correcao:

- removido o caminho duplicado
- mantido o fluxo principal do Fabric
- escondido temporariamente o texto do canvas enquanto a `textarea` esta ativa

Resultado:

- o ghosting parou

### 12.8 Problema 7: debug morria logo depois do boot

Sintoma:

- o painel ficava visivel
- mas nao continuava registrando depois da inicializacao

Diagnostico:

- o init finalizava a sessao de debug cedo demais

Correcao:

- o fluxo final passou a continuar registrando eventos em vez de encerrar o trace

Resultado:

- agora o log realmente serve para acompanhar o que acontece apos a pagina abrir

### 12.9 Problema 8: espacos digitados sumiam no commit

Sintoma observado no log:

- durante a digitacao `overlayValueBytes` subia
- no commit `committedValueBytes` voltava ao valor anterior

Diagnostico:

- a normalizacao do texto estava colapsando espacos na saida da edicao

Correcao:

- a normalizacao passou a preservar espacos digitados
- a limpeza ficou focada em quebra de linha, nao em esmagar o texto

Resultado:

- espacos passaram a sobreviver ao commit

### 12.10 Problema 9: cursor das alcas de resize estava enganando

Sintoma:

- ao passar nas alcas de cima/baixo, o cursor sugeria esquerda/direita

Correcao:

- handles superior/inferior: `ns-resize`
- handles laterais: `ew-resize`
- cantos: diagonal

Resultado:

- feedback visual agora combina com a direcao de resize esperada

### 12.11 Modelo de interacao atual do editor V2

No estado atual, o editor de mensagens V2 trabalha com uma interacao mista de mouse + teclado.

Controles e acoes hoje documentados na tela e implementados no JS:

- selecionar objeto com clique
- editar texto com duplo clique
- editar texto com botao `Editar texto`
- editar texto com `Enter`
- duplicar camada com botao `Duplicar`
- remover camada com `Delete` ou `Backspace`
- mover camada com setas
- mover mais rapido com `Shift + setas`

Do ponto de vista de implementacao, as regras relevantes ficam concentradas em:

- `admin/mensagens.php`
- `assets/js/admin-message-editor-v2.js`

Importante:

- a edicao de texto em si acontece na `textarea` overlay, nao diretamente no textbox interno do Fabric
- o texto do canvas fica escondido temporariamente durante a edicao para evitar ghosting
- o editor continua sincronizando `scene_json`, `fabric_scene_json` e `editor_layers_json` enquanto o usuario trabalha

---

## 13. Debug atual do editor

### 13.1 Painel de log

Hoje existe um unico painel de log sempre visivel no admin de mensagens.

Ele fica ligado desde o carregamento da pagina.

### 13.2 O que ele registra

Eventos de navegador:

- `pagina_carregada`
- `click`
- `dblclick`
- `focusin`
- `input`
- `change`

Eventos internos do editor:

- `canvas: mouse_dblclick`
- `beginTextEditing: start`
- `beginTextEditing: armed`
- `beginTextEditing: focus_ready`
- `overlay_editor: keydown`
- `overlay_editor: input`
- `overlay_editor: blur`
- `exitTextEditing: start`
- `exitTextEditing: committed`

Eventos de sincronizacao:

- `objectToSceneLayer`
- `syncLegacyHiddenState`
- `syncHiddenScene`

### 13.3 Para que o log serve hoje

Serve para responder perguntas como:

- o duplo clique chegou?
- a `textarea` realmente abriu?
- o texto digitado entrou no overlay?
- o commit preservou bytes?
- a cena hidden foi atualizada?

### 13.4 O que o log ja nos ajudou a descobrir

Foi o log que permitiu cravar, por exemplo:

- que havia duplo caminho de `dblclick`
- que o debug estava sendo encerrado cedo demais
- que os espacos eram capturados, mas perdidos no commit
- que o renderer estava filtrando texto de forma errada

---

## 14. Validacoes e testes realizados recentemente

### 14.1 Validacao de sintaxe

Durante o trabalho recente, foram usados repetidamente:

- `php -l admin/mensagens.php`
- `php -l includes/customer_messages.php`
- `node --check assets/js/admin-message-editor-v2.js`

### 14.2 Validacao manual do editor

Foi testado manualmente:

- abrir projeto salvo
- reabrir projeto no admin
- mover layers
- redimensionar layers
- dar duplo clique no texto
- usar `Enter` para editar
- digitar espaco
- sair da edicao e confirmar commit

### 14.3 Validacao manual do envio

Foi testado manualmente:

- salvar projeto
- enviar mensagem
- conferir o HTML/email resultante
- conferir screenshot do email recebido
- conferir se o nome do cliente foi resolvido
- conferir se o corpo do texto apareceu
- conferir se o botao/link final ficou clicavel

### 14.4 Resultado pratico das validacoes

Estado atual validado:

- imagem base correta
- titulo correto
- corpo correto
- nome do cliente resolvido
- CTA visual correto
- link clicavel com fallback mais seguro

---

## 15. Debitos tecnicos e riscos conhecidos

### 15.1 Configuracao sensivel ainda em PHP

Debito:

- segredos em `config/*.php`

Risco:

- acoplamento forte ao servidor
- maior chance de vazamento por manutencao descuidada

### 15.2 Sem suite automatica de testes

Debito:

- muita confianca em teste manual

Risco:

- regressao silenciosa em partes menos usadas

### 15.3 Envio de mensagens ainda sincrono

Debito:

- o request do admin faz tudo sozinho

Risco:

- demora
- timeout
- UX ruim
- dificuldade de retomar lote
- pouca observabilidade operacional

### 15.4 SMTP sem reuso de conexao

Debito:

- handshake repetido para cada email

Risco:

- throughput ruim

### 15.5 Render por cliente ainda caro

Debito:

- quando ha token visual, cada cliente aciona render proprio

Risco:

- custo alto por destinatario

### 15.6 Migrations manuais

Debito:

- SQL versionado por arquivo, sem engine de migration

Risco:

- drift de schema entre ambientes

### 15.7 `mbstring` ausente

Debito:

- parte do codigo precisa conviver com fallback

Risco:

- edge cases de string multibyte exigem cuidado

---

## 16. Onde estamos indo agora

O proximo passo ja decidido para a arquitetura de mensagens e:

- `Fila + SMTP persistente + 2 workers + cache/render otimizado`

### 16.1 Objetivos da proxima fase

Queremos:

- tirar o envio do request web
- criar processamento em background
- reduzir custo por cliente
- manter personalizacao por cliente
- mostrar progresso real no admin
- evitar duplicidade de envio
- aumentar confiabilidade operacional

### 16.2 O que esta fora de duvida

A nova arquitetura **nao** pode quebrar:

- `{{primeiro_nome}}`
- personalizacao do assunto
- personalizacao do corpo
- personalizacao visual quando o token esta na arte

Exemplo que precisa continuar verdadeiro:

- salvo no admin: `Faaala, {{primeiro_nome}}!`
- recebido pelo cliente: `Faaala, Lucas!`

### 16.3 Como a arquitetura deve mudar

Hoje:

- clique do admin = processamento completo

Futuro:

- clique do admin = criacao do lote
- worker = processamento real do envio

### 16.4 Componentes planejados

Ainda nao implementados, mas ja aprovados conceitualmente:

- tabela de lotes de mensagem
- tabela de jobs por destinatario
- log de envio por job
- worker CLI em PHP
- servico de worker gerenciado por `systemd`
- endpoint/polling de progresso no admin

### 16.5 Modelo de dados planejado

Nome sugerido de tabelas futuras:

- `message_batches`
- `message_batch_jobs`
- `message_send_log`

Campos importantes esperados em `message_batches`:

- id
- status
- total_recipients
- pending_count
- processing_count
- sent_count
- failed_count
- created_by
- created_at
- started_at
- finished_at
- rate_limit_per_minute
- idempotency_key

Campos importantes esperados em `message_batch_jobs`:

- id
- batch_id
- customer_id
- email
- status
- attempts
- max_attempts
- available_at
- reserved_at
- worker_id
- last_error
- dedupe_key
- render_cache_key
- sent_at

### 16.6 Worker em PHP

A recomendacao escolhida e:

- worker CLI em PHP
- gerenciado por `systemd`
- 2 instancias iniciais

Motivo:

- stack principal ja esta em PHP
- operacao em VPS fica simples
- evita trazer Redis/RabbitMQ cedo demais

### 16.7 SMTP persistente

O mailer atual precisara evoluir para algo como:

- abrir conexao SMTP
- autenticar uma vez
- enviar varios emails na mesma sessao
- reconectar de forma controlada apos X envios ou Y tempo

Regras esperadas:

- cada worker com sua propria conexao
- sem compartilhar socket entre processos
- reconnect seguro em erro

### 16.8 Cache/render otimizado

O ganho de performance mais forte vira quando separarmos:

#### Parte fixa da arte

Tudo que nao muda:

- fundo
- decoracao
- elementos visuais fixos
- texto estatico

#### Parte variavel da arte

Tudo que muda por cliente:

- `{{primeiro_nome}}`
- outros tokens visuais

Estrategia segura:

- cachear base renderizada por hash da cena
- so renderizar novamente quando a parte visual variavel realmente mudar

### 16.9 Regra de ouro do cache

Nunca fazer isto:

- renderizar uma imagem com `Lucas`
- mandar a mesma imagem para `Marina`

Cache so sera seguro quando:

- nao houver token visual
- ou quando a chave do cache incluir os tokens visuais que alteram o resultado

### 16.10 Progresso no admin

O painel admin deve deixar de bloquear e passar a:

- criar o lote
- mostrar status
- consultar progresso por polling

Exemplos de status futuros:

- pendente
- processando
- enviado
- falhou
- cancelado
- pausado

### 16.11 Idempotencia e dedupe

A arquitetura futura precisa garantir:

- um clique nao gera dois lotes iguais por acidente
- um mesmo destinatario nao entra duas vezes no mesmo batch
- se o browser repetir POST, o lote nao duplica
- se worker cair, o job pode ser retomado com seguranca

### 16.12 Concorrrencia

Configuracao inicial desejada:

- `2 workers`

Motivo:

- melhora throughput
- mantem controle
- reduz chance de burst agressivo no SMTP

### 16.13 Estimativa de ganho

Cenario atual:

- `35 a 50 minutos` para `200` clientes, em estimativa pratica

Cenario intermediario:

- fila + worker + SMTP persistente = grande melhoria operacional e reducao relevante de tempo

Cenario alvo realista:

- fila + SMTP persistente + 2 workers + render/cache otimizado

Faixa desejada:

- algo como `3 a 10 minutos` para `200` clientes em cenarios bons

Em campanhas muito favoraveis, com pouca variacao visual:

- o throughput agregado pode se aproximar de `1 a 3 segundos` por cliente

### 16.14 Ordem recomendada de implementacao

Esta e a ordem recomendada para atacar o problema com menor risco:

1. Criar lote + jobs no banco.
2. Fazer o admin so enfileirar e responder rapido.
3. Criar worker CLI em PHP.
4. Adicionar idempotencia e dedupe.
5. Criar progresso/polling no admin.
6. Evoluir `mailer.php` para SMTP persistente.
7. Rodar com 2 workers.
8. Medir tempo gasto em render x SMTP x tokenizacao.
9. Implementar cache/base render otimizado.
10. So depois pensar em renderer persistente extra, se ainda necessario.

---

## 17. O que ja esta pronto versus o que ainda e plano

### 17.1 Ja pronto

- loja operacional
- painel admin operacional
- modulo de mensagens funcional
- editor V2 em Fabric operando
- composicao de arte final com titulo e corpo corretos
- fallback de hotspot para email
- log vivo do editor
- edicao via `textarea` overlay
- ghosting corrigido
- preservacao de espacos corrigida
- cursores de resize coerentes

### 17.2 Ainda nao implementado

- fila de envio real
- tabelas de batch/job
- worker CLI
- progresso de batch no admin
- SMTP persistente
- 2 workers em producao
- cache inteligente de render por hash de cena/tokens visuais

---

## 18. Arquivos mais sensiveis hoje

Se alguem for tocar no projeto, estes sao os arquivos que merecem mais cuidado:

### 18.1 Core

- `includes/bootstrap.php`
- `includes/functions.php`
- `config/*.php`

### 18.2 Loja e checkout

- `includes/storefront.php`
- `includes/orders.php`
- `carrinho.php`
- `finalizar-pedido.php`

### 18.3 Pagamentos

- `includes/asaas.php`
- `includes/pagbank.php`
- `asaas-webhook.php`
- `pagbank-webhook.php`

### 18.4 Mensagens

- `admin/mensagens.php`
- `includes/customer_messages.php`
- `includes/customer_message_scene.php`
- `assets/js/admin-message-editor-v2.js`
- `assets/js/message-scene-renderer.js`
- `scripts/render_composicao.js`
- `scripts/render_message_scene.mjs`
- `templates/message-renderer.html`
- `includes/mailer.php`

---

## 19. Comandos uteis de manutencao

### 19.1 Validacao de sintaxe PHP

```bash
php -l admin/mensagens.php
php -l includes/customer_messages.php
php -l includes/mailer.php
```

### 19.2 Validacao de sintaxe JS

```bash
node --check assets/js/admin-message-editor-v2.js
node --check assets/js/message-scene-renderer.js
```

### 19.3 Conferencia de versoes

```bash
php -v
node -v
npm -v
```

### 19.4 Conferencia de servicos

```bash
systemctl is-active nginx php8.3-fpm mysql
```

### 19.5 Estrutura de storage/uploads

```bash
find storage -maxdepth 2 -type d | sort
find uploads -maxdepth 2 -type d | sort
```

### 19.6 Conferencia do vhost

```bash
sed -n '1,220p' /etc/nginx/sites-available/modatropical
```

---

## 20. Checklist de seguranca para mexer neste projeto

Antes de alterar qualquer parte sensivel:

- nao vazar credenciais de `config/*.php`
- nao presumir que o renderer suporta reutilizacao de arte quando ha token visual
- nao mexer em `mailer.php` sem pensar em fallback/log
- nao misturar o formato legado e o V2 sem passar por normalizacao
- nao remover o debug do editor sem ter observabilidade equivalente
- nao tratar os backups `.codexbak` como fonte canonica

---

## 21. Retrato final do projeto em `2026-04-09`

Se alguem precisar entender rapidamente "onde estamos", a resposta honesta e esta:

- a loja esta no ar e funcional
- o painel administrativo esta funcional
- o modulo de mensagens, que era a area mais fragil, avancou muito e hoje esta util de verdade
- o maior problema restante nao e mais "o editor esta quebrado", e sim "o envio ainda esta sincrono e caro"
- a proxima fase correta nao e mais ajuste visual pequeno: e arquitetura de envio em background

Em outras palavras:

- a parte de **qualidade do editor e do render** saiu do estado de crise
- a parte de **escala e throughput de envio** virou o proximo alvo tecnico

Esse README deve continuar sendo atualizado conforme:

- a fila entrar
- o mailer ganhar persistencia
- os workers forem criados
- o cache/render otimizado sair do papel

Enquanto isso nao acontece, a leitura correta do sistema e:

- funcional
- sensivel
- melhor documentado
- e finalmente com um rumo tecnico claro para escalar o envio sem sacrificar personalizacao
