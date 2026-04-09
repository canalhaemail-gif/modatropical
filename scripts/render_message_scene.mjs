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
    ({ default: puppeteer } = await import('puppeteer-core'));
} catch (error) {
    fail('puppeteer-core nao esta instalado neste ambiente. Instale as dependencias de export antes de usar o renderer.');
}

const resolveExecutablePath = async () => {
    const fromEnv = (process.env.PUPPETEER_EXECUTABLE_PATH || '').trim();
    if (fromEnv !== '') {
        return fromEnv;
    }

    const candidates = [
        '/usr/local/bin/modatropical-chromium',
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
        '/snap/bin/chromium',
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files\\Chromium\\Application\\chrome.exe'
    ];

    for (const candidate of candidates) {
        try {
            await fs.access(candidate);
            return candidate;
        } catch (error) {
            // continua procurando
        }
    }

    fail('Nao foi encontrado um navegador Chromium/Chrome. Defina PUPPETEER_EXECUTABLE_PATH ou instale o Chromium no servidor.');
};

const loadJsonFile = async (filePath) => {
    const raw = await fs.readFile(filePath, 'utf8');
    return JSON.parse(raw);
};

const scene = await loadJsonFile(path.resolve(scenePath));
const tokenValues = tokenPath ? await loadJsonFile(path.resolve(tokenPath)) : null;
const templateUrl = pathToFileURL(path.resolve(templatePath)).href;
const executablePath = await resolveExecutablePath();
const browserRuntimeDir = path.resolve(
    path.dirname(path.resolve(outputPath)),
    `.chromium-runtime-${process.pid}-${Date.now()}`
);
await fs.mkdir(browserRuntimeDir, { recursive: true });
let browser;

try {
    browser = await puppeteer.launch({
        headless: true,
        executablePath,
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

    const page = await browser.newPage();
    await page.goto(templateUrl, { waitUntil: 'networkidle0' });
    await page.evaluate(async (payload) => {
        await window.renderMessageSceneFromPayload(payload);
    }, {
        scene,
        tokenValues
    });

    await page.waitForFunction(() => window.__MESSAGE_RENDER_READY__ === true);

    await fs.mkdir(path.dirname(path.resolve(outputPath)), { recursive: true });
    const target = await page.$('#messageSceneExport');
    if (!target) {
        fail('Nao foi possivel localizar o elemento final de export na pagina renderer.');
    }

    await target.screenshot({
        path: path.resolve(outputPath),
        type: 'png',
        omitBackground: true
    });

    console.log(path.resolve(outputPath));
} finally {
    if (browser) {
        await browser.close();
    }
    await fs.rm(browserRuntimeDir, { recursive: true, force: true });
}
