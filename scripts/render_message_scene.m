import fs from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { pathToFileURL } from 'node:url';

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

const args = parseArgs(process.argv.slice(2));
const scenePath = args.input || args.scene;
const outputPath = args.output;
const tokenPath = args.tokens || '';
const templatePath = args.template || path.resolve(process.cwd(), 'templates', 'message-renderer.html');

if (!scenePath) {
    fail('Uso: node scripts/render_message_scene.mjs --input scene.json --output saida.png');
}

if (!outputPath) {
    fail('Parametro obrigatorio ausente: --output');
}

let puppeteer;
try {
    ({ default: puppeteer } = await import('puppeteer'));
} catch (error) {
    fail('Puppeteer nao esta instalado neste ambiente. Instale a dependencia antes de usar o export.');
}

const loadJsonFile = async (filePath) => {
    const raw = await fs.readFile(filePath, 'utf8');
    return JSON.parse(raw);
};

const scene = await loadJsonFile(path.resolve(scenePath));
const tokenValues = tokenPath ? await loadJsonFile(path.resolve(tokenPath)) : null;
const templateUrl = pathToFileURL(path.resolve(templatePath)).href;

const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox']
});

try {
    const page = await browser.newPage();
    await page.goto(templateUrl, { waitUntil: 'networkidle0' });
    await page.evaluate(async (payload) => {
        await window.renderMessageSceneFromPayload(payload);
    }, {
        scene,
        tokenValues
    });

    await page.waitForFunction(() => window.__MESSAGE_RENDER_READY__ === true);

    const dataUrl = await page.evaluate(() => window.getMessageSceneDataUrl());
    const base64 = dataUrl.replace(/^data:image\/png;base64,/, '');
    const buffer = Buffer.from(base64, 'base64');

    await fs.mkdir(path.dirname(path.resolve(outputPath)), { recursive: true });
    await fs.writeFile(path.resolve(outputPath), buffer);

    console.log(path.resolve(outputPath));
} finally {
    await browser.close();
}
