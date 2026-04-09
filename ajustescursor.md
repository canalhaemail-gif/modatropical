# Ajustes feitos via Cursor — editor de mensagens V2 / projetos salvos

Documento gerado para registrar alterações feitas na conversa (abrir projeto salvo no admin, canvas vazio, console `canvas.setWidth is not a function`, etc.).

**Escopo:** principalmente módulo de mensagens no admin (`admin/mensagens.php`, includes de cena/camadas, JS do editor V2, renderer de cena, CSS do canvas Fabric). Sem mudanças em carrinho, checkout, README (exceto este arquivo, pedido explícito do usuário).

---

## 1. `includes/customer_messages.php`

**Função `customer_message_editor_layers()`**

- **Problema:** Projetos salvos em `storage/messages/projects.json` guardam camadas extras em `editor_layers_json`, mas a função só lia `email_editor_layers` e `editor_layers`. Ao reconstruir a cena no PHP, essas camadas eram ignoradas.
- **Mudança:** Se, após o fluxo normal, `rawLayers` continuar vazio, tentar decodificar `editor_layers_json` (string JSON) e usar como lista de camadas.

---

## 2. `includes/customer_message_scene.php`

**Função `customer_message_scene_from_legacy()`**

- **Problema:** Com `show_title` / `show_body` desligados, o fluxo ainda gerava camadas “base” (muitas vezes invisíveis) e não incorporava corretamente a arte feita só nas camadas do editor visual salvas em `editor_layers_json` (após o item 1).
- **Mudança:** Detectar tipos presentes nas camadas extras (`title`, `body`, `button`, `hotspot`). Só adicionar `base_title`, `base_body`, `base_button` e `base_hotspot` se **não** existir camada extra do mesmo tipo — evita duplicar e prioriza a arte salva no editor visual.

---

## 3. `assets/js/message-scene-renderer.js`

**Função `parseSceneJson()`**

- **Problema:** Se o JSON estivesse “duplo” (resultado de `JSON.parse` ainda ser uma string JSON), `normalizeScene` recebia string e virava cena vazia (`layers: []`).
- **Mudança:** Após o primeiro `JSON.parse`, em loop (até 3 níveis), se o resultado for string, tentar `JSON.parse` de novo. Rejeitar resultado que não seja objeto plain (ex.: array no topo).

**Desenho do fundo no render (`drawContainImage`, ex-`drawCoverImage`) — 2026-04-08**

- **Mudança:** `Math.min` (contain) + centralizar, alinhado ao editor V2, para export/preview nao cortarem a imagem hero como o cover fazia.

---

## 4. `assets/js/admin-message-editor-v2.js`

Várias alterações cumulativas:

### 4.1 `crossOrigin` na imagem de fundo

- **Problema:** `fabric.Image.fromURL` usava `crossOrigin: 'anonymous'` para qualquer URL `http(s)`, o que em alguns casos na mesma origem pode atrapalhar o carregamento.
- **Mudança:** Função `isSameOriginAssetUrl()`; `crossOrigin: 'anonymous'` só quando a URL é http(s) **e** de outra origem.

### 4.2 Ordem de carga em `loadSceneIntoCanvas`

- **Problema:** `await setCanvasBackground(...)` rodava **antes** de `canvas.add(...)` nas camadas. Se o carregamento da imagem travasse ou demorasse demais, o texto/elementos nunca eram desenhados — canvas só com o bege do CSS.
- **Mudança:** Limpar canvas, definir fundo bege, adicionar todas as camadas, `requestRenderAll`, `syncHiddenScene`, e **depois** `await setCanvasBackground` com `Promise.race` contra timeout (~12s). `try/catch` por camada ao adicionar objetos.

### 4.3 Botões V2 / legado alinhados ao estado real

- **Problema:** O texto de status refletia `editor_engine`, mas os estilos dos botões não.
- **Mudança:** Em `setModeLabel()`, alternar classes `button--primary` / `button--ghost` nos botões `[data-fabric-action="apply"]` e `[data-fabric-action="legacy"]` conforme `fabric_v2` ou legado.

### 4.4 Fabric v7 — `canvas.setWidth` / `setHeight` inexistentes

- **Problema:** No Fabric.js v7 (`assets/vendor/fabric.min.js`), a API do `Canvas` não expõe mais `setWidth` / `setHeight`. O console mostrava: `Uncaught TypeError: canvas.setWidth is not a function`.
- **Mudança:** Usar `canvas.setDimensions({ width, height })` quando existir; fallback para `setWidth`/`setHeight` apenas se ainda existirem.

### 4.5 Cena inicial, fallback legado e metadados (2026-04-08)

- **Problema:** Canvas continuava vazio para alguns usuários; JSON da cena só em `input hidden` + `window.messageEditorConfig` podia ser sensível a contexto HTML; `syncHiddenScene` dependia de `object.data`, que o Fabric pode não preservar como esperado; texto em camadas podia não ser lido (`object.text` vs `_text`).
- **Mudança:**
  - Prioridade ao resolver JSON da cena: argumento explícito → conteúdo de `<script type="application/json" id="message-editor-initial-scene">` → `messageEditorConfig` → inputs hidden.
  - Fallback de camadas: `parse` vazio ou `canvas.add` falhando → reconstruir a partir de `<script type="application/json" id="message-editor-initial-layers">` e, se vazio, do `#editor_layers_json`.
  - `WeakMap` (`layerMetaByObject`) guarda o mesmo meta que antes ia só em `object.data`, com `object.set('data', meta)` quando existir.
  - `fabricObjectText()` para leitura de texto; `objectToSceneLayer`, `normalizeScaledObject`, `syncButtonGroupText` e `text:changed` usam meta do WeakMap ou `data`.
  - `renderAll()` + `requestRenderAll()` após montar camadas.
  - `hasRotatingPoint` / `setControlsVisibility` apenas se existirem.
  - `fontWeight` numérico no `Textbox` (800 / 400).
  - **`setCanvasBackground`:** escala da imagem hero com `Math.min` (contain) e posição centralizada, para o card 800x1100 mostrar a arte inteira (faixas bege se a proporção da imagem for diferente).

---

## 5. `admin/mensagens.php`

- Classes dos botões “Usar V2 no envio” e “Voltar ao legado” conforme `$draft['editor_engine'] === 'fabric_v2'`.
- **`customer_message_editor_layers`:** passar `editor_layers_json` explicitamente além de `email_editor_layers`.
- **Bootstrap da cena no HTML (2026-04-08):**
  - Variáveis `$messageEditorBootScene` / `$messageEditorBootLayers` (decode + re-encode seguro).
  - Dois blocos: `<script type="application/json" id="message-editor-initial-scene">` e `id="message-editor-initial-layers">` com `JSON_HEX_TAG | JSON_HEX_APOS` para não quebrar o parser HTML se o texto do cliente contiver `</script>` etc.
  - `window.messageEditorConfig` também usa `JSON_HEX_TAG | JSON_HEX_APOS` no `json_encode`.

---

## 6. `assets/css/admin.css`

- **2026-04-08:** Regras para `.message-fabric-editor__canvas-shell .canvas-container` (`inline-block`, `max-width: 100%`) para o wrapper que o Fabric cria ao redor do `<canvas>`.
- **2026-04-08:** Estilos do painel **Debug do editor de mensagens** (`.message-editor-debug*`) no final da pagina de mensagens.
- **2026-04-08 (ajuste pos-debug):** Removidos `width`/`height: auto` genericos nos `canvas` dentro do shell do Fabric — passamos a estilizar sobretudo `.canvas-container` (sombra, bege, `border-radius`); nos canvas filhos apenas `display: block`, para o par lower/upper nao desalinhar (sintoma: `getObjects().length` > 0 mas tela “vazia”).
- **2026-04-08 (card cortado):** `.canvas-container` com `overflow: visible`, `width: fit-content`, sem `max-width` forcado; shell com `overflow-x: auto`; `.panel-card--messages:has([data-message-fabric-root])` com `overflow: visible` para o card nao cortar o canvas. Imagem de fundo no editor: **contain** + centralizar (`Math.min` + offset), em vez de **cover** (`Math.max`), para mostrar a arte inteira no 800x1100.

---

## 6b. Debug no final de `admin/mensagens.php` (2026-04-08)

- Bloco `<details>` no fim do conteudo (antes dos scripts do Fabric) com:
  - **Servidor:** JSON (pretty) com `project` na URL, projeto encontrado, tamanhos de `fabric_scene_json` / `scene_json` / `editor_layers_json`, contagens de camadas do boot, `editor_engine`, hero, etc.
  - **Cliente:** `<pre id="message-editor-debug-client">` preenchido por `messageEditorDebugPush` / `messageEditorDebugRender`.
- Script inline registra:
  - **Clique em Abrir** (captura): grava `sessionStorage` e linha de log antes da navegacao.
  - **DOMContentLoaded:** le a URL `?project=...` e o valor armazenado do clique anterior.
- `admin-message-editor-v2.js` chama `window.messageEditorDebugPush` quando existir: aborts, criacao do canvas, passos de `loadSceneIntoCanvas` (tamanho do JSON, camadas parseadas, fallbacks, contagem de objetos no canvas, fim apos fundo), e `init concluido` apos o `Promise` de `loadSceneIntoCanvas('')`.
- Apos `loadSceneIntoCanvas`: `canvas.calcOffset()` quando existir e redesenho em duplo `requestAnimationFrame` + `renderAll`/`requestRenderAll` para garantir pintura apos layout/CSS.
- Os mesmos eventos vao para `console.info` com prefixo `[mensagens-editor-debug]` / `[mensagens-debug]`.

---

## 7. Arquivos não alterados de forma relevante (referência)

- Carrinho, checkout, loja pública, SMTP, PagBank, Asaas, outras páginas admin.
- `README.md` (não atualizado salvo pedido explícito).

---

## 8. Erros no console sem ligação direta com o projeto

- **`Unexpected token 'export'` em `chrome-extension://.../webpage_content_reporter.js`:** extensão do navegador.
- **`content.js` / `log-init` / `get phone number error` / arrays `classList` com `_ak9y`:** em geral **extensão** (ex.: WhatsApp Web, Cursor, tradutor), não código da loja.

Para ver só erros do site: **janela anônima** sem extensões ou filtrar por “mensagens.php” / “admin-message-editor-v2” no console.

O erro **`canvas.setWidth is not a function`** vinha do editor V2 + Fabric v7 e foi tratado no item 4.4.

---

## 9. Como validar depois dos ajustes

1. Hard refresh na página de mensagens (Ctrl+F5) para carregar JS/CSS com novo `?v=` do `asset_url`.
2. Admin → Mensagens → Projetos salvos → **Abrir**.
3. Console: não deve aparecer `canvas.setWidth is not a function` (origem do nosso JS).
4. Canvas deve mostrar camadas e, quando possível, imagem de fundo.
5. Se o console ainda lotar de `content.js`, testar em aba anônima para confirmar que é extensão.

---

## 10. Atualização extra — envio V2 por composição

- O fluxo de envio do V2 deixou de depender primeiro do screenshot HTML completo.
- Novo renderer: `scripts/render_composicao.js`
  - usa **Puppeteer** só para rasterizar blocos `text` em PNG transparente no envio;
  - aguarda `document.fonts.ready`;
  - usa **sharp** para compor tudo sobre a imagem base e exportar **JPG** otimizado.
- Backend integrado em `includes/customer_messages.php`:
  - novo caminho preferencial `customer_message_scene_render_composition(...)`;
  - fallback antigo mantido: browser renderer legado e, por último, `customer_message_editor_render_flat_art(...)`.
- No modo atual de envio, o compositor foi ajustado para **carimbar apenas textos tokenizados** (ex.: `{{primeiro_nome}}`) sobre a arte-base, evitando trazer junto textos estáticos do editor.
- O HTML final do email V2 agora:
  - recebe `<img>` com `width` e `height` HTML explícitos;
  - usa a arte JPG renderizada;
  - preserva o **hotspot transparente** do botão como overlay separado, enquanto o botão visual pode vir pronto da imagem base.
- Teste real feito com projeto salvo:
  - arquivo final gerado em `uploads/messages/rendered-email-20260407233746-e00d8002.jpg`;
  - dimensões `1280x1123`;
  - tamanho ~`125 KB`;
  - hotspot retornado para `https://modatropical.store/promocoes.php`.
- Observação importante:
  - ainda existem projetos salvos apontando para fundos antigos removidos (`hero_image_path` órfão). Nesses casos, o envio V2 precisa usar um fundo existente ou cair no fallback.

---

*Última atualização: 2026-04-08 — renderer composicional do V2 integrado ao envio, JPG otimizado, `img` com dimensões explícitas e hotspot transparente preservado.*
