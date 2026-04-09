import fs from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import sharp from 'sharp';
import puppeteer from 'puppeteer-core';

const parseArgs = (argv) => {
    const args = {};

    for (let index = 0; index < argv.length; index += 1) {
        const item = argv[index];
        if (!item.startsWith('--')) {
            continue;
        }

        const key = item.slice(2);
        const next = argv[index + 1];
        if (!next || next.startsWith('--')) {
            args[key] = true;
            continue;
        }

        args[key] = next;
        index += 1;
    }

    return args;
};

const fail = (message, code = 1) => {
    console.error(message);
    process.exit(code);
};

const escapeHtml = (value) => String(value == null ? '' : value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

const args = parseArgs(process.argv.slice(2));
const cwd = process.cwd();
const defaultBackground = path.resolve(cwd, 'uploads/messages/messages-20260407223233-f61c3532.png');
const defaultOutput = path.resolve(cwd, 'storage/messages/render-tmp/composicao-poc.jpg');
const defaultManifest = path.resolve(cwd, 'storage/messages/render-tmp/composicao-poc.hotspots.json');
const outputPath = path.resolve(args.output || defaultOutput);
const manifestPath = path.resolve(args.manifest || defaultManifest);
const backgroundPathArg = String(args.background || '').trim();
const scenePath = args.scene ? path.resolve(args.scene) : '';
const projectsFilePath = path.resolve(args.projects || path.resolve(cwd, 'storage/messages/projects.json'));
const projectId = String(args['project-id'] || '').trim();
const customerName = String(args.name || 'Lucas').trim() || 'Lucas';
const outputFormat = String(args.format || 'jpeg').trim().toLowerCase();
const jpegQuality = Math.max(50, Math.min(96, Number(args.quality || 86) || 86));
const scaleFactor = Math.max(1, Math.min(4, Number(args.scale || 2) || 2));
const outputScaleFactor = Math.max(1, Math.min(4, Number(args['output-scale'] || 1) || 1));
const tokensArg = String(args.tokens || '').trim();
const tokensFilePath = String(args['tokens-file'] || '').trim() !== ''
    ? path.resolve(String(args['tokens-file']).trim())
    : '';
const renderTypesArg = String(args['render-types'] || 'text').trim();
const textFilterArg = String(args['text-filter'] || 'all').trim().toLowerCase();

const resolveExecutablePath = async () => {
    const fromEnv = String(process.env.PUPPETEER_EXECUTABLE_PATH || '').trim();
    if (fromEnv !== '') {
        return fromEnv;
    }

    const candidates = [
        '/usr/local/bin/modatropical-chromium',
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
        '/snap/bin/chromium'
    ];

    for (const candidate of candidates) {
        try {
            await fs.access(candidate);
            return candidate;
        } catch (error) {
            // continua procurando
        }
    }

    fail('Nao foi encontrado Chromium/Chrome. Defina PUPPETEER_EXECUTABLE_PATH ou instale o navegador.');
};

const fontCandidates = {
    regular: [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf'
    ],
    bold: [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf'
    ],
    italic: [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Oblique.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Italic.ttf'
    ],
    boldItalic: [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-BoldOblique.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-BoldItalic.ttf'
    ],
    emoji: [
        '/usr/share/fonts/truetype/noto/NotoColorEmoji.ttf'
    ]
};

const resolveFontPath = async (kind) => {
    const candidates = fontCandidates[kind] || [];
    for (const candidate of candidates) {
        try {
            await fs.access(candidate);
            return candidate;
        } catch (error) {
            // continua procurando
        }
    }

    fail(`Nao encontrei a fonte local para o estilo "${kind}".`);
};

const resolveTokens = (value, tokenValues) => {
    const source = String(value == null ? '' : value);
    return source.replace(/\{\{\s*([^}]+?)\s*\}\}/g, (fullMatch, tokenName) => {
        const normalized = String(tokenName || '').trim();
        if (Object.prototype.hasOwnProperty.call(tokenValues, fullMatch)) {
            return String(tokenValues[fullMatch]);
        }
        if (Object.prototype.hasOwnProperty.call(tokenValues, normalized)) {
            return String(tokenValues[normalized]);
        }
        return fullMatch;
    });
};

const toInt = (value, fallback = 0) => {
    const numeric = Number(value);
    return Number.isFinite(numeric) ? Math.round(numeric) : fallback;
};

const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

const parseRenderTypes = (value) => {
    const supported = new Set(['text', 'button']);
    const parsed = String(value || '')
        .split(',')
        .map((item) => item.trim().toLowerCase())
        .filter((item) => supported.has(item));

    return new Set(parsed.length > 0 ? parsed : ['text']);
};

const parseTextFilter = (value) => {
    const normalized = String(value || '').trim().toLowerCase();
    return ['all', 'tokenized', 'title'].includes(normalized) ? normalized : 'all';
};

const textContainsTokens = (value) => /\{\{\s*[^}]+?\s*\}\}/.test(String(value || ''));

const shouldRenderTextLayer = (layer, textFilter) => {
    if (!layer || textFilter === 'all') {
        return true;
    }

    if (textFilter === 'title') {
        return String(layer.role || '').toLowerCase() === 'title';
    }

    if (textFilter === 'tokenized') {
        return textContainsTokens(String(layer.textRaw || ''));
    }

    return true;
};

const defaultScene = {
    schemaVersion: 1,
    canvas: {
        width: 800,
        height: 1100,
        backgroundImage: defaultBackground
    },
    actions: {
        primaryHrefRaw: 'https://modatropical.store/promocoes.php',
        imageHrefRaw: 'https://modatropical.store/promocoes.php'
    },
    layers: [
        {
            id: 'title_1',
            type: 'text',
            role: 'title',
            visible: true,
            textRaw: 'Faaala, {{primeiro_nome}}! Aquele MEGA PROMOCAO que voce queria, acabou de chegar!',
            x: 48,
            y: 374,
            width: 704,
            height: 0,
            fontSize: 40,
            fontWeight: 800,
            fontStyle: 'italic',
            lineHeight: 1.35,
            textAlign: 'left',
            color: '#fff7f0',
            uppercase: false,
            shadow: 'strong'
        },
        {
            id: 'body_1',
            type: 'text',
            role: 'body',
            visible: true,
            textRaw: 'Separamos ofertas exclusivas com descontos que voce nao vai acreditar! 🤯\nMas olha so: e por tempo limitadissimo e os estoques estao voando. 🏃‍♂️💨\n\nNao deixe para depois, clique no botao abaixo e garanta o seu agora mesmo 👇',
            x: 96,
            y: 550,
            width: 648,
            height: 0,
            fontSize: 23,
            fontWeight: 700,
            fontStyle: 'italic',
            lineHeight: 1.75,
            textAlign: 'left',
            color: '#2c1917',
            uppercase: false,
            shadow: 'soft'
        },
        {
            id: 'button_1',
            type: 'button',
            role: 'button',
            visible: true,
            textRaw: 'Ver promocoes',
            hrefRaw: 'https://modatropical.store/promocoes.php',
            x: 280,
            y: 814,
            width: 216,
            height: 82
        },
        {
            id: 'hotspot_1',
            type: 'hotspot',
            role: 'image_hotspot',
            visible: true,
            textRaw: '',
            hrefRaw: 'https://modatropical.store/promocoes.php',
            x: 280,
            y: 814,
            width: 216,
            height: 99
        }
    ]
};

const parseSceneInput = (value) => {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
        return value;
    }

    const raw = String(value == null ? '' : value).trim();
    if (raw === '') {
        return null;
    }

    let parsed;
    try {
        parsed = JSON.parse(raw);
    } catch (error) {
        return null;
    }

    let depth = 0;
    while (typeof parsed === 'string' && depth < 3) {
        try {
            parsed = JSON.parse(parsed);
        } catch (error) {
            return null;
        }
        depth += 1;
    }

    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
        return null;
    }

    return parsed;
};

const loadProjectScene = async () => {
    const raw = await fs.readFile(projectsFilePath, 'utf8');
    const parsed = JSON.parse(raw);
    if (!Array.isArray(parsed)) {
        fail('O arquivo de projetos precisa conter uma lista JSON.');
    }

    const project = parsed.find((item) => String(item && item.id || '').trim() === projectId);
    if (!project) {
        fail(`Projeto nao encontrado em ${projectsFilePath}: ${projectId}`);
    }

    const payload = project && typeof project.payload === 'object' && project.payload
        ? project.payload
        : {};
    const scene = parseSceneInput(payload.fabric_scene_json)
        || parseSceneInput(payload.scene_json)
        || parseSceneInput(payload.scene)
        || null;

    if (!scene) {
        fail(`O projeto ${projectId} nao contem fabric_scene_json/scene_json valido.`);
    }

    if ((!scene.canvas || !scene.canvas.backgroundImage) && payload.hero_image_path) {
        scene.canvas = scene.canvas && typeof scene.canvas === 'object'
            ? scene.canvas
            : {};
        scene.canvas.backgroundImage = String(payload.hero_image_path);
    }

    return {
        source: {
            kind: 'project',
            projectsFilePath,
            projectId,
            projectName: String(project.name || payload.project_name || projectId)
        },
        payload,
        scene
    };
};

const loadScene = async () => {
    if (projectId !== '') {
        return loadProjectScene();
    }

    if (scenePath !== '') {
        const raw = await fs.readFile(scenePath, 'utf8');
        const scene = parseSceneInput(raw);
        if (!scene) {
            fail('O arquivo de cena precisa conter um objeto JSON valido.');
        }
        return {
            source: {
                kind: 'scene-file',
                scenePath
            },
            payload: {},
            scene
        };
    }

    return {
        source: {
            kind: 'default-scene'
        },
        payload: {},
        scene: defaultScene
    };
};

const resolveBackgroundPath = (scene, payload = {}) => {
    if (backgroundPathArg !== '') {
        return path.resolve(backgroundPathArg);
    }

    const sceneBackground = String(scene && scene.canvas && scene.canvas.backgroundImage || '').trim();
    if (sceneBackground !== '') {
        return path.isAbsolute(sceneBackground)
            ? sceneBackground
            : path.resolve(cwd, sceneBackground);
    }

    const payloadBackground = String(payload.hero_image_path || payload.backgroundImage || '').trim();
    if (payloadBackground !== '') {
        return path.isAbsolute(payloadBackground)
            ? payloadBackground
            : path.resolve(cwd, payloadBackground);
    }

    return defaultBackground;
};

const parseTokens = async () => {
    const tokens = {
        primeiro_nome: customerName,
        '{{primeiro_nome}}': customerName
    };

    if (tokensFilePath !== '') {
        const raw = await fs.readFile(tokensFilePath, 'utf8');
        const parsed = JSON.parse(raw);
        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
            for (const [key, value] of Object.entries(parsed)) {
                const normalizedKey = String(key || '').trim();
                if (normalizedKey === '') {
                    continue;
                }
                tokens[normalizedKey] = String(value ?? '');
                if (!normalizedKey.startsWith('{{')) {
                    tokens[`{{${normalizedKey}}}`] = String(value ?? '');
                }
            }
        }
    }

    if (tokensArg === '') {
        return tokens;
    }

    const entries = tokensArg.split(',').map((item) => item.trim()).filter(Boolean);
    for (const entry of entries) {
        const separator = entry.indexOf('=');
        if (separator === -1) {
            continue;
        }
        const key = entry.slice(0, separator).trim();
        const value = entry.slice(separator + 1).trim();
        if (key === '') {
            continue;
        }
        tokens[key] = value;
        tokens[`{{${key}}}`] = value;
    }

    return tokens;
};

const resolveShadowCss = (shadow) => {
    if (shadow === 'strong') {
        return '0 6px 22px rgba(0, 0, 0, 0.30)';
    }
    if (shadow === 'soft') {
        return '0 2px 10px rgba(0, 0, 0, 0.16)';
    }
    return 'none';
};

const fontKindForLayer = (layer) => {
    const bold = Number(layer.fontWeight || 400) >= 700;
    const italic = String(layer.fontStyle || '').toLowerCase() === 'italic';
    if (bold && italic) {
        return 'boldItalic';
    }
    if (bold) {
        return 'bold';
    }
    if (italic) {
        return 'italic';
    }
    return 'regular';
};

const buildTextLayerHtml = async (layer, text, width, height) => {
    const fontPath = await resolveFontPath(fontKindForLayer(layer));
    const emojiPath = await resolveFontPath('emoji');
    const lineHeight = Number(layer.lineHeight || 1.4);
    const fontSize = Math.max(12, Number(layer.fontSize || 18));
    const fontWeight = Number(layer.fontWeight || 400) >= 700 ? 800 : 400;
    const fontStyle = String(layer.fontStyle || '').toLowerCase() === 'italic' ? 'italic' : 'normal';
    const textAlign = ['left', 'center', 'right'].includes(String(layer.textAlign || 'left'))
        ? String(layer.textAlign)
        : 'left';
    const color = String(layer.color || '#ffffff');
    const textShadow = resolveShadowCss(String(layer.shadow || 'off'));
    const content = layer.uppercase ? text.toUpperCase() : text;
    const scaledWidth = Math.max(40, Math.round(width * scaleFactor));
    const scaledHeight = Math.max(0, Math.round(height * scaleFactor));
    const topInset = Math.max(0, Math.round(fontSize * scaleFactor * 0.14));
    const bottomInset = Math.max(0, Math.round(fontSize * scaleFactor * 0.04));

    return `<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @font-face {
            font-family: "MTLayerFont";
            src: url("file://${fontPath}") format("truetype");
            font-display: block;
        }
        @font-face {
            font-family: "MTEmojiFont";
            src: url("file://${emojiPath}") format("truetype");
            font-display: block;
        }
        html, body {
            margin: 0;
            padding: 0;
            background: transparent;
        }
        body {
            width: ${scaledWidth}px;
            padding: 0;
            background: transparent;
        }
        #frame {
            box-sizing: border-box;
            width: ${scaledWidth}px;
            min-height: ${scaledHeight}px;
            margin: 0;
            padding: ${topInset}px 0 ${bottomInset}px 0;
            background: transparent;
            overflow: visible;
        }
        #layer {
            box-sizing: border-box;
            display: block;
            width: 100%;
            margin: 0;
            padding: 0;
            color: ${color};
            font-family: "MTLayerFont", "MTEmojiFont", sans-serif;
            font-size: ${fontSize * scaleFactor}px;
            font-weight: ${fontWeight};
            font-style: ${fontStyle};
            line-height: ${lineHeight};
            text-align: ${textAlign};
            white-space: pre-wrap;
            word-break: break-word;
            text-shadow: ${textShadow};
            -webkit-font-smoothing: antialiased;
            text-rendering: geometricPrecision;
        }
    </style>
</head>
<body>
    <div id="frame"><div id="layer">${escapeHtml(content).replace(/\n/g, '<br>')}</div></div>
</body>
</html>`;
};

const buildButtonLayerHtml = async (layer, text, width, height, padding) => {
    const fontPath = await resolveFontPath('bold');
    const emojiPath = await resolveFontPath('emoji');
    const scaledWidth = Math.max(120, Math.round(width * scaleFactor));
    const scaledHeight = Math.max(44, Math.round(height * scaleFactor));
    const scaledPadding = Math.max(10, Math.round(padding * scaleFactor));
    const fontSize = clamp(Math.round(scaledHeight * 0.36), 18, 42);

    return `<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @font-face {
            font-family: "MTButtonFont";
            src: url("file://${fontPath}") format("truetype");
            font-display: block;
        }
        @font-face {
            font-family: "MTEmojiFont";
            src: url("file://${emojiPath}") format("truetype");
            font-display: block;
        }
        html, body {
            margin: 0;
            padding: 0;
            background: transparent;
        }
        body {
            width: ${scaledWidth + (scaledPadding * 2)}px;
            height: ${scaledHeight + (scaledPadding * 2)}px;
            background: transparent;
        }
        #button {
            box-sizing: border-box;
            display: flex;
            align-items: center;
            justify-content: center;
            width: ${scaledWidth}px;
            height: ${scaledHeight}px;
            margin: ${scaledPadding}px;
            padding: 0 ${Math.round(scaledHeight * 0.4)}px;
            border-radius: 999px;
            border: 2px solid rgba(255, 228, 187, 0.35);
            background: linear-gradient(180deg, #d98a5f 0%, #bd6f4c 55%, #9f5739 100%);
            box-shadow: 0 18px 28px rgba(86, 45, 27, 0.18), inset 0 1px 0 rgba(255, 255, 255, 0.25);
            color: #fff6ed;
            font-family: "MTButtonFont", "MTEmojiFont", sans-serif;
            font-size: ${fontSize}px;
            font-weight: 800;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            -webkit-font-smoothing: antialiased;
        }
        #button span {
            transform: translateY(1px);
        }
    </style>
</head>
<body>
    <div id="button"><span>${escapeHtml(text)}</span></div>
</body>
</html>`;
};

const renderElementToBuffer = async (browser, html, selector) => {
    const page = await browser.newPage();

    try {
        await page.setViewport({ width: 1800, height: 1800, deviceScaleFactor: 1 });
        await page.setContent(html, { waitUntil: 'load' });
        const fontsReadyHandle = await page.evaluateHandle('document.fonts.ready');
        await fontsReadyHandle.dispose();
        await page.evaluate(() => document.fonts.ready);
        await page.evaluate(() => new Promise((resolve) => {
            requestAnimationFrame(() => requestAnimationFrame(resolve));
        }));

        const handle = await page.$(selector);
        if (!handle) {
            throw new Error(`Elemento ${selector} nao encontrado para screenshot.`);
        }

        return await handle.screenshot({
            type: 'png',
            omitBackground: true
        });
    } finally {
        await page.close();
    }
};

const renderLayerBuffer = async (browser, layer, tokenValues) => {
    const resolvedText = resolveTokens(String(layer.textRaw || ''), tokenValues).trim();
    if (resolvedText === '') {
        return null;
    }

    const width = Math.max(40, toInt(layer.width, 0));
    const height = Math.max(40, toInt(layer.height, 0));
    const html = layer.type === 'button'
        ? await buildButtonLayerHtml(layer, resolvedText, width, height, 10)
        : await buildTextLayerHtml(layer, resolvedText, width, height);
    const selector = layer.type === 'button' ? '#button' : '#frame';
    const buffer = await renderElementToBuffer(browser, html, selector);
    const metadata = await sharp(buffer).metadata();
    const renderedWidth = Math.round((metadata.width || 0) / scaleFactor);
    const renderedHeight = Math.round((metadata.height || 0) / scaleFactor);
    const topInset = layer.type === 'text'
        ? Math.max(0, Math.round(Math.max(12, Number(layer.fontSize || 18)) * 0.14))
        : 0;

    return {
        buffer,
        width: renderedWidth,
        height: renderedHeight,
        requestedWidth: width,
        requestedHeight: height,
        overflowHeight: Math.max(0, renderedHeight - height),
        resolvedText,
        topInset
    };
};

const composeScene = async (scene, payload, outputFilePath, hotspotsFilePath, tokenValues, sourceInfo, renderTypes, textFilter) => {
    const backgroundPath = resolveBackgroundPath(scene, payload);
    const backgroundSource = sharp(backgroundPath);
    const backgroundMetadata = await backgroundSource.metadata();
    if (!backgroundMetadata.width || !backgroundMetadata.height) {
        fail('Nao foi possivel ler as dimensoes da imagem base.');
    }

    const canvasWidth = Math.max(1, toInt(scene.canvas && scene.canvas.width, backgroundMetadata.width));
    const canvasHeight = Math.max(1, toInt(scene.canvas && scene.canvas.height, backgroundMetadata.height));
    const ratioX = 1;
    const ratioY = 1;
    const browserRuntimeDir = path.resolve(
        path.dirname(outputFilePath),
        `.chromium-runtime-${process.pid}-${Date.now()}`
    );
    await fs.mkdir(browserRuntimeDir, { recursive: true });
    let browser = null;

    try {
        browser = await puppeteer.launch({
            headless: true,
            executablePath: await resolveExecutablePath(),
            userDataDir: browserRuntimeDir,
            env: {
                ...process.env,
                HOME: browserRuntimeDir,
                XDG_CONFIG_HOME: browserRuntimeDir,
                XDG_CACHE_HOME: browserRuntimeDir
            },
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-crashpad',
                '--disable-breakpad',
                '--no-first-run',
                '--no-default-browser-check'
            ]
        });

        const composites = [];
        const hotspots = [];
        const renderedLayerIds = [];
        const layerMetrics = [];
        let flowShiftY = 0;
        const hasExplicitHotspots = (Array.isArray(scene.layers) ? scene.layers : []).some((item) => {
            if (!item || typeof item !== 'object') {
                return false;
            }
            return item.visible !== false
                && String(item.type || '') === 'hotspot'
                && String(item.hrefRaw || '').trim() !== '';
        });

        for (const rawLayer of Array.isArray(scene.layers) ? scene.layers : []) {
            const layer = rawLayer && typeof rawLayer === 'object' ? rawLayer : null;
            if (!layer || layer.visible === false) {
                continue;
            }

            if (layer.type === 'hotspot') {
                const baseTop = Math.round(toInt(layer.y, 0) * ratioY);
                const adjustedTop = baseTop + Math.round(flowShiftY * ratioY);
                hotspots.push({
                    id: String(layer.id || ''),
                    hrefRaw: resolveTokens(String(layer.hrefRaw || ''), tokenValues),
                    left: Math.round(toInt(layer.x, 0) * ratioX),
                    top: adjustedTop,
                    width: Math.round(toInt(layer.width, 0) * ratioX),
                    height: Math.round(toInt(layer.height, 0) * ratioY)
                });
                layerMetrics.push({
                    id: String(layer.id || ''),
                    type: String(layer.type || ''),
                    role: String(layer.role || ''),
                    requestedWidth: Math.max(0, toInt(layer.width, 0)),
                    requestedHeight: Math.max(0, toInt(layer.height, 0)),
                    baseLeft: Math.round(toInt(layer.x, 0) * ratioX),
                    baseTop,
                    appliedTop: adjustedTop,
                    flowShiftY: Math.round(flowShiftY * 100) / 100,
                    overflowHeight: 0
                });
                continue;
            }

            const layerType = String(layer.type || '').toLowerCase();
            if (!['text', 'button'].includes(layerType)) {
                continue;
            }

            if (
                renderTypes.has(layerType)
                && (layerType !== 'text' || shouldRenderTextLayer(layer, textFilter))
            ) {
                const rendered = await renderLayerBuffer(browser, layer, tokenValues);
                if (rendered) {
                    const baseTop = Math.round(toInt(layer.y, 0) * ratioY);
                    const adjustedTop = baseTop + Math.round(flowShiftY * ratioY);
                    const input = await sharp(rendered.buffer)
                        .resize({
                            width: Math.max(1, Math.round(rendered.width * ratioX)),
                            height: Math.max(1, Math.round(rendered.height * ratioY)),
                            fit: 'fill'
                        })
                        .png()
                        .toBuffer();

                    composites.push({
                        input,
                        left: Math.round(toInt(layer.x, 0) * ratioX),
                        top: adjustedTop
                    });
                    renderedLayerIds.push(String(layer.id || ''));
                    layerMetrics.push({
                        id: String(layer.id || ''),
                        type: String(layer.type || ''),
                        role: String(layer.role || ''),
                        requestedWidth: rendered.requestedWidth,
                        requestedHeight: rendered.requestedHeight,
                        renderedWidth: rendered.width,
                        renderedHeight: rendered.height,
                        baseLeft: Math.round(toInt(layer.x, 0) * ratioX),
                        baseTop,
                        appliedTop: adjustedTop,
                        flowShiftY: Math.round(flowShiftY * 100) / 100,
                        overflowHeight: rendered.overflowHeight,
                        topInset: rendered.topInset,
                        textLength: rendered.resolvedText.length
                    });
                    if (layerType === 'text' && rendered.overflowHeight > 0) {
                        flowShiftY += rendered.overflowHeight;
                    }
                }
            }

            if (
                layerType === 'button'
                && !hasExplicitHotspots
                && String(layer.hrefRaw || '').trim() !== ''
            ) {
                hotspots.push({
                    id: String(layer.id || ''),
                    hrefRaw: resolveTokens(String(layer.hrefRaw || ''), tokenValues),
                    left: Math.round(toInt(layer.x, 0) * ratioX),
                    top: Math.round(toInt(layer.y, 0) * ratioY) + Math.round(flowShiftY * ratioY),
                    width: Math.round(toInt(layer.width, 0) * ratioX),
                    height: Math.round(toInt(layer.height, 0) * ratioY)
                });
            }
        }

        await fs.mkdir(path.dirname(outputFilePath), { recursive: true });
        await fs.mkdir(path.dirname(hotspotsFilePath), { recursive: true });

        let outputWidth = canvasWidth;
        let outputHeight = canvasHeight;
        let normalizedHotspots = hotspots.map((hotspot) => ({ ...hotspot }));
        let pipeline = backgroundSource
            .resize({
                width: canvasWidth,
                height: canvasHeight,
                fit: 'fill'
            })
            .composite(composites)
            .flatten({ background: '#f3e5d8' });

        if (outputScaleFactor > 1) {
            outputWidth = Math.max(1, Math.round(canvasWidth * outputScaleFactor));
            outputHeight = Math.max(1, Math.round(canvasHeight * outputScaleFactor));
            pipeline = pipeline.resize({
                width: outputWidth,
                height: outputHeight,
                fit: 'fill'
            });
            normalizedHotspots = normalizedHotspots.map((hotspot) => ({
                ...hotspot,
                left: Math.round((Number(hotspot.left) || 0) * outputScaleFactor),
                top: Math.round((Number(hotspot.top) || 0) * outputScaleFactor),
                width: Math.round((Number(hotspot.width) || 0) * outputScaleFactor),
                height: Math.round((Number(hotspot.height) || 0) * outputScaleFactor)
            }));
        }

        if (outputFormat === 'png') {
            pipeline = pipeline.png({ compressionLevel: 9 });
        } else {
            pipeline = pipeline.jpeg({
                quality: jpegQuality,
                progressive: true,
                mozjpeg: true
            });
        }

        await pipeline.toFile(outputFilePath);
        await fs.writeFile(hotspotsFilePath, JSON.stringify({
            source: sourceInfo,
            background: backgroundPath,
            output: outputFilePath,
            tokenValues,
            renderTypes: Array.from(renderTypes),
            textFilter,
            renderedLayerIds,
            hotspots: normalizedHotspots
        }, null, 2));

        return {
            source: sourceInfo,
            outputPath: outputFilePath,
            hotspotsPath: hotspotsFilePath,
            hotspots: normalizedHotspots,
            renderTypes: Array.from(renderTypes),
            textFilter,
            renderedLayerIds,
            layerMetrics,
            outputSize: {
                width: outputWidth,
                height: outputHeight
            },
            backgroundSize: {
                width: canvasWidth,
                height: canvasHeight
            }
        };
    } finally {
        if (browser) {
            await browser.close();
        }
        await fs.rm(browserRuntimeDir, { recursive: true, force: true });
    }
};

const main = async () => {
    const loaded = await loadScene();
    const resolvedBackground = resolveBackgroundPath(loaded.scene, loaded.payload);
    try {
        await fs.access(resolvedBackground);
    } catch (error) {
        fail(`Imagem base nao encontrada: ${resolvedBackground}`);
    }

    const tokenValues = await parseTokens();
    const renderTypes = parseRenderTypes(renderTypesArg);
    const textFilter = parseTextFilter(textFilterArg);
    const result = await composeScene(
        loaded.scene,
        loaded.payload,
        outputPath,
        manifestPath,
        tokenValues,
        loaded.source,
        renderTypes,
        textFilter
    );
    console.log(JSON.stringify(result, null, 2));
};

main().catch((error) => {
    fail(error instanceof Error ? error.stack || error.message : String(error));
});
