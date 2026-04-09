document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-message-form]');
    if (!form) {
        return;
    }

    const presetButtons = Array.from(document.querySelectorAll('[data-message-preset]'));
    const presetCards = Array.from(document.querySelectorAll('[data-message-preset-card]'));
    const titleInput = document.getElementById('title');
    const messageInput = document.getElementById('message');
    const linkInput = document.getElementById('link_url');
    const imageLinkInput = document.getElementById('image_link_url');
    const buttonLabelInput = document.getElementById('button_label');
    const showTitleInput = document.getElementById('show_title');
    const showBodyInput = document.getElementById('show_body');
    const showButtonInput = document.getElementById('show_button');
    const showHotspotInput = document.getElementById('show_image_hotspot');
    const titleSizeInput = document.getElementById('title_size');
    const bodySizeInput = document.getElementById('body_size');
    const titleLineHeightInput = document.getElementById('title_line_height');
    const bodyLineHeightInput = document.getElementById('body_line_height');
    const titleAlignInput = document.getElementById('title_align');
    const bodyAlignInput = document.getElementById('body_align');
    const titleBoldInput = document.getElementById('title_bold');
    const bodyBoldInput = document.getElementById('body_bold');
    const titleItalicInput = document.getElementById('title_italic');
    const bodyItalicInput = document.getElementById('body_italic');
    const titleUppercaseInput = document.getElementById('title_uppercase');
    const bodyUppercaseInput = document.getElementById('body_uppercase');
    const titleShadowInput = document.getElementById('title_shadow');
    const bodyShadowInput = document.getElementById('body_shadow');
    const titleColorInput = document.getElementById('title_color');
    const bodyColorInput = document.getElementById('body_color');
    const hotspotWidthInput = document.getElementById('image_hotspot_width');
    const hotspotHeightInput = document.getElementById('image_hotspot_height');
    const editorLayersInput = document.getElementById('editor_layers_json');
    const sceneInput = document.getElementById('scene_json');
    const editorEngineInput = document.getElementById('editor_engine');
    const recipientModeInput = document.querySelector('[data-recipient-mode]');
    const messageKindInput = document.getElementById('message_kind');
    const customerRow = document.querySelector('[data-customer-row]');
    const customerSelect = document.getElementById('customer_id');
    const heroInput = document.querySelector('[data-message-hero-input]');
    const heroName = document.querySelector('[data-message-hero-name]');
    const heroClearButton = document.querySelector('[data-message-clear-image]');
    const heroPreview = document.querySelector('[data-message-hero-preview]');
    const heroImagePreview = document.querySelector('[data-message-hero-image-preview]');
    const renderedPreviewFrame = document.querySelector('[data-message-rendered-frame]');
    const currentHeroImagePathInput = document.getElementById('current_hero_image_path');
    const extraLayersHost = document.querySelector('[data-message-extra-layers]');
    const hotspotSettings = document.querySelector('[data-hotspot-settings]');
    const addLayerButtons = Array.from(document.querySelectorAll('[data-add-layer]'));
    const textStylePanel = document.querySelector('[data-text-style-panel]');
    const textStylePanelTitle = document.querySelector('[data-text-style-title]');
    const styleAlignControl = document.querySelector('[data-text-style-control="align"]');
    const styleBoldControl = document.querySelector('[data-text-style-control="bold"]');
    const styleItalicControl = document.querySelector('[data-text-style-control="italic"]');
    const styleUppercaseControl = document.querySelector('[data-text-style-control="uppercase"]');
    const styleShadowControl = document.querySelector('[data-text-style-control="shadow"]');
    const liveValueTargets = Array.from(document.querySelectorAll('[data-live-value-for]')).reduce(function (accumulator, item) {
        accumulator[item.dataset.liveValueFor] = item;
        return accumulator;
    }, {});
    const titlePreview = document.querySelector('[data-message-title-preview]');
    const bodyPreview = document.querySelector('[data-message-body-preview]');
    const buttonPreview = document.querySelector('[data-message-button-preview]');
    const titleBlock = document.querySelector('[data-draggable="title"]');
    const bodyBlock = document.querySelector('[data-draggable="body"]');
    const buttonBlock = document.querySelector('[data-draggable="button"]');
    const hotspotBlock = document.querySelector('[data-draggable="hotspot"]');
    const hideButtons = Array.from(document.querySelectorAll('[data-hide-element]'));
    const hiddenLayoutInputs = Array.from(document.querySelectorAll('[data-layout-input]')).reduce(function (accumulator, input) {
        accumulator[input.dataset.layoutInput] = input;
        return accumulator;
    }, {});
    const editorConfig = window.messageEditorConfig || {};
    const presetMeta = editorConfig.presetMeta && typeof editorConfig.presetMeta === 'object' ? editorConfig.presetMeta : {};
    const initialExtraLayers = Array.isArray(editorConfig.initialExtraLayers) ? editorConfig.initialExtraLayers : [];
    const renderFrameUrl = typeof editorConfig.renderFrameUrl === 'string' ? editorConfig.renderFrameUrl : '';
    const sceneRenderer = window.MessageSceneRenderer || null;
    const layerSpecs = {
        title: { widthKey: 'title_width', minWidth: 14, maxWidth: 90, fontInput: titleSizeInput, minFont: 22, maxFont: 86, lineHeightInput: titleLineHeightInput, minLineHeight: 80, maxLineHeight: 220 },
        body: { widthKey: 'body_width', minWidth: 18, maxWidth: 90, fontInput: bodySizeInput, minFont: 12, maxFont: 34, lineHeightInput: bodyLineHeightInput, minLineHeight: 100, maxLineHeight: 260 },
        button: { widthKey: 'button_width', heightKey: 'button_height', minWidth: 12, maxWidth: 70, minHeight: 6, maxHeight: 26 },
        hotspot: { widthKey: 'image_hotspot_width', heightKey: 'image_hotspot_height', minWidth: 4, maxWidth: 90, minHeight: 4, maxHeight: 90 }
    };
    let heroObjectUrl = null;
    let activeDrag = null;
    let extraLayers = Array.isArray(initialExtraLayers) ? initialExtraLayers.slice() : [];
    let selectedEditable = null;
    let selectedLayerIdentity = '';

    const legacySceneSyncEnabled = function () {
        return !editorEngineInput || editorEngineInput.value !== 'fabric_v2';
    };

    const escapeHtml = function (value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const replacePreviewTokens = function (value) {
        if (sceneRenderer && typeof sceneRenderer.resolvePreviewText === 'function') {
            return sceneRenderer.resolvePreviewText(value);
        }

        return String(value == null ? '' : value);
    };

    const clamp = function (value, min, max) {
        return Math.min(Math.max(value, min), max);
    };

    const getLayoutValue = function (name, fallback) {
        const input = hiddenLayoutInputs[name];
        const value = input ? Number(input.value) : NaN;
        return Number.isFinite(value) ? value : fallback;
    };

    const setLayoutValue = function (name, value) {
        const input = hiddenLayoutInputs[name];
        if (input) {
            input.value = String(Math.round(value));
        }
    };

    const sceneCanvasDefaults = sceneRenderer && sceneRenderer.canvasDefaults
        ? sceneRenderer.canvasDefaults
        : { width: 800, height: 1100 };
    let sceneRenderFrame = 0;
    let sceneRenderToken = 0;
    let renderedFrameReady = false;

    const percentToPixels = function (value, axisSize) {
        return Math.round((clamp(Number(value), 0, 100) / 100) * axisSize);
    };

    const normalizeHref = function (value) {
        return String(value == null ? '' : value).trim();
    };

    const currentPreviewBackgroundImage = function () {
        if (heroObjectUrl) {
            return heroObjectUrl;
        }

        if (heroPreview && heroPreview.dataset && heroPreview.dataset.existingImageUrl) {
            return String(heroPreview.dataset.existingImageUrl).trim();
        }

        return '';
    };

    const currentSceneBackgroundImage = function () {
        if (!currentHeroImagePathInput) {
            return '';
        }

        return String(currentHeroImagePathInput.value || '').trim();
    };

    const buildTextLayerSceneData = function (options) {
        const settings = options || {};
        const style = normalizeTextStyle(settings.style || {}, settings.role);
        const canvasWidth = sceneCanvasDefaults.width;
        const canvasHeight = sceneCanvasDefaults.height;

        return {
            id: settings.id,
            type: 'text',
            role: settings.role,
            visible: settings.visible !== false,
            textRaw: String(settings.textRaw || ''),
            hrefRaw: '',
            x: percentToPixels(settings.x, canvasWidth),
            y: percentToPixels(settings.y, canvasHeight),
            width: percentToPixels(settings.width, canvasWidth),
            height: 0,
            fontFamily: 'default',
            fontSize: Math.round(clamp(Number(settings.fontSize), settings.role === 'title' ? 22 : 12, settings.role === 'title' ? 86 : 34)),
            fontWeight: style.bold ? 800 : (settings.role === 'title' ? 700 : 400),
            fontStyle: style.italic ? 'italic' : 'normal',
            lineHeight: clamp(Number(settings.lineHeight), 0.8, 2.6),
            textAlign: style.align,
            color: settings.color,
            uppercase: style.uppercase,
            shadow: style.shadow
        };
    };

    const buildButtonLayerSceneData = function (options) {
        const settings = options || {};
        return {
            id: settings.id,
            type: 'button',
            role: 'button',
            visible: settings.visible !== false,
            textRaw: String(settings.textRaw || ''),
            hrefRaw: normalizeHref(settings.hrefRaw),
            x: percentToPixels(settings.x, sceneCanvasDefaults.width),
            y: percentToPixels(settings.y, sceneCanvasDefaults.height),
            width: percentToPixels(settings.width, sceneCanvasDefaults.width),
            height: percentToPixels(settings.height, sceneCanvasDefaults.height)
        };
    };

    const buildHotspotLayerSceneData = function (options) {
        const settings = options || {};
        return {
            id: settings.id,
            type: 'hotspot',
            role: 'image_hotspot',
            visible: settings.visible === true,
            textRaw: '',
            hrefRaw: normalizeHref(settings.hrefRaw),
            x: percentToPixels(settings.x, sceneCanvasDefaults.width),
            y: percentToPixels(settings.y, sceneCanvasDefaults.height),
            width: percentToPixels(settings.width, sceneCanvasDefaults.width),
            height: percentToPixels(settings.height, sceneCanvasDefaults.height)
        };
    };

    const buildSceneFromEditorState = function (forPreview) {
        const scene = {
            schemaVersion: 1,
            canvas: {
                width: sceneCanvasDefaults.width,
                height: sceneCanvasDefaults.height,
                backgroundImage: forPreview ? currentPreviewBackgroundImage() : currentSceneBackgroundImage()
            },
            actions: {
                primaryHrefRaw: normalizeHref(linkInput ? linkInput.value : ''),
                imageHrefRaw: normalizeHref(imageLinkInput ? imageLinkInput.value : '')
            },
            layers: []
        };

        scene.layers.push(buildTextLayerSceneData({
            id: 'base_title',
            role: 'title',
            visible: !!(showTitleInput && showTitleInput.checked),
            textRaw: titleInput ? titleInput.value.trim() : '',
            x: getLayoutValue('title_x', 8),
            y: getLayoutValue('title_y', 10),
            width: getLayoutValue('title_width', 72),
            fontSize: titleSizeInput ? titleSizeInput.value : 54,
            lineHeight: (Number(titleLineHeightInput ? titleLineHeightInput.value : 104) || 104) / 100,
            color: titleColorInput ? titleColorInput.value : '#fff7f0',
            style: baseTextStyle('title')
        }));

        scene.layers.push(buildTextLayerSceneData({
            id: 'base_body',
            role: 'body',
            visible: !!(showBodyInput && showBodyInput.checked),
            textRaw: messageInput ? messageInput.value.trim() : '',
            x: getLayoutValue('body_x', 8),
            y: getLayoutValue('body_y', 30),
            width: getLayoutValue('body_width', 72),
            fontSize: bodySizeInput ? bodySizeInput.value : 18,
            lineHeight: (Number(bodyLineHeightInput ? bodyLineHeightInput.value : 175) || 175) / 100,
            color: bodyColorInput ? bodyColorInput.value : '#2c1917',
            style: baseTextStyle('body')
        }));

        scene.layers.push(buildButtonLayerSceneData({
            id: 'base_button',
            visible: !!(showButtonInput && showButtonInput.checked),
            textRaw: currentButtonLabel(),
            hrefRaw: linkInput ? linkInput.value : '',
            x: getLayoutValue('button_x', 24),
            y: getLayoutValue('button_y', 82),
            width: getLayoutValue('button_width', 26),
            height: getLayoutValue('button_height', 11)
        }));

        scene.layers.push(buildHotspotLayerSceneData({
            id: 'base_hotspot',
            visible: !!(showHotspotInput && showHotspotInput.checked),
            hrefRaw: imageLinkInput ? imageLinkInput.value : '',
            x: getLayoutValue('image_hotspot_x', 24),
            y: getLayoutValue('image_hotspot_y', 78),
            width: hotspotWidthInput ? hotspotWidthInput.value : 26,
            height: hotspotHeightInput ? hotspotHeightInput.value : 10
        }));

        extraLayers.forEach(function (layer) {
            if (layer.type === 'title' || layer.type === 'body') {
                scene.layers.push(buildTextLayerSceneData({
                    id: String(layer.id || ('layer_' + Date.now())),
                    role: layer.type,
                    visible: true,
                    textRaw: String(layer.content || ''),
                    x: Number(layer.x || 0),
                    y: Number(layer.y || 0),
                    width: Number(layer.width || (layer.type === 'title' ? 72 : 72)),
                    fontSize: Number(layer.font_size || (layer.type === 'title' ? 54 : 18)),
                    lineHeight: (Number(layer.line_height || (layer.type === 'title' ? 104 : 175)) || (layer.type === 'title' ? 104 : 175)) / 100,
                    color: layer.type === 'title'
                        ? (titleColorInput ? titleColorInput.value : '#fff7f0')
                        : (bodyColorInput ? bodyColorInput.value : '#2c1917'),
                    style: layer
                }));
                return;
            }

            if (layer.type === 'button') {
                scene.layers.push(buildButtonLayerSceneData({
                    id: String(layer.id || ('layer_' + Date.now())),
                    visible: true,
                    textRaw: String(layer.content || ''),
                    hrefRaw: layer.link_url || '',
                    x: Number(layer.x || 0),
                    y: Number(layer.y || 0),
                    width: Number(layer.width || 26),
                    height: Number(layer.height || 11)
                }));
                return;
            }

            if (layer.type === 'hotspot') {
                scene.layers.push(buildHotspotLayerSceneData({
                    id: String(layer.id || ('layer_' + Date.now())),
                    visible: true,
                    hrefRaw: layer.link_url || '',
                    x: Number(layer.x || 0),
                    y: Number(layer.y || 0),
                    width: Number(layer.width || 26),
                    height: Number(layer.height || 10)
                }));
            }
        });

        return scene;
    };

    const persistSceneJson = function () {
        if (!sceneInput || !legacySceneSyncEnabled()) {
            return;
        }

        sceneInput.value = JSON.stringify(buildSceneFromEditorState(false));
    };

    const renderSceneFramePreview = function () {
        if (!legacySceneSyncEnabled()) {
            return;
        }

        if (!renderedPreviewFrame || !renderedPreviewFrame.contentWindow || typeof renderedPreviewFrame.contentWindow.renderMessageSceneFromPayload !== 'function') {
            return;
        }

        const token = ++sceneRenderToken;
        const scene = buildSceneFromEditorState(true);
        renderedPreviewFrame.contentWindow.renderMessageSceneFromPayload({
            scene: scene,
            tokenValues: sceneRenderer && sceneRenderer.previewTokenValues
                ? sceneRenderer.previewTokenValues
                : null
        }).catch(function () {
            if (token !== sceneRenderToken) {
                return;
            }
        });
    };

    const scheduleSceneSync = function () {
        persistSceneJson();

        if (!legacySceneSyncEnabled() || !renderedPreviewFrame || !renderedFrameReady || !sceneRenderer) {
            return;
        }

        if (sceneRenderFrame) {
            window.cancelAnimationFrame(sceneRenderFrame);
        }

        sceneRenderFrame = window.requestAnimationFrame(function () {
            sceneRenderFrame = 0;
            renderSceneFramePreview();
        });
    };

    if (renderedPreviewFrame && renderFrameUrl !== '' && renderedPreviewFrame.getAttribute('src') !== renderFrameUrl) {
        renderedPreviewFrame.setAttribute('src', renderFrameUrl);
    }

    if (renderedPreviewFrame) {
        renderedPreviewFrame.addEventListener('load', function () {
            renderedFrameReady = true;
            scheduleSceneSync();
        });
    }

    const getBaseLayerDefinition = function (key) {
        const spec = layerSpecs[key] || {};

        return {
            key: key,
            x: getLayoutValue(key + '_x', 0),
            y: getLayoutValue(key + '_y', 0),
            width: spec.widthKey ? getLayoutValue(spec.widthKey, spec.minWidth || 24) : null,
            height: spec.heightKey ? getLayoutValue(spec.heightKey, spec.minHeight || 10) : null,
            fontSize: spec.fontInput ? clamp(Number(spec.fontInput.value || 0), spec.minFont || 12, spec.maxFont || 86) : null
        };
    };

    const cloneBaseLayerToExtra = function (key) {
        const base = getBaseLayerDefinition(key);

        if (key === 'title') {
            const style = baseTextStyle('title');
            return {
                id: 'layer_' + Date.now() + '_title',
                type: 'title',
                content: titleInput && titleInput.value.trim() !== '' ? titleInput.value.trim() : 'Novo titulo',
                link_url: '',
                x: clamp(base.x + 3, 0, 90),
                y: clamp(base.y + 3, 0, 94),
                width: base.width || 72,
                height: 10,
                font_size: base.fontSize || 54,
                line_height: clamp(Number(titleLineHeightInput ? titleLineHeightInput.value : 104), 80, 220),
                align: style.align,
                bold: style.bold,
                italic: style.italic,
                uppercase: style.uppercase,
                shadow: style.shadow
            };
        }

        if (key === 'body') {
            const style = baseTextStyle('body');
            return {
                id: 'layer_' + Date.now() + '_body',
                type: 'body',
                content: messageInput && messageInput.value.trim() !== '' ? messageInput.value.trim() : 'Novo texto',
                link_url: '',
                x: clamp(base.x + 3, 0, 90),
                y: clamp(base.y + 3, 0, 94),
                width: base.width || 72,
                height: 16,
                font_size: base.fontSize || 18,
                line_height: clamp(Number(bodyLineHeightInput ? bodyLineHeightInput.value : 175), 100, 260),
                align: style.align,
                bold: style.bold,
                italic: style.italic,
                uppercase: style.uppercase,
                shadow: style.shadow
            };
        }

        if (key === 'button') {
            return {
                id: 'layer_' + Date.now() + '_button',
                type: 'button',
                content: currentButtonLabel(),
                link_url: linkInput ? linkInput.value.trim() : '',
                x: clamp(base.x + 3, 0, 90),
                y: clamp(base.y + 3, 0, 94),
                width: base.width || 26,
                height: base.height || 11
            };
        }

        if (key === 'hotspot') {
            return {
                id: 'layer_' + Date.now() + '_hotspot',
                type: 'hotspot',
                content: '',
                link_url: imageLinkInput && imageLinkInput.value.trim() !== '' ? imageLinkInput.value.trim() : (linkInput ? linkInput.value.trim() : ''),
                x: clamp(base.x + 3, 0, 90),
                y: clamp(base.y + 3, 0, 94),
                width: base.width || 26,
                height: base.height || 10
            };
        }

        return null;
    };

    const persistExtraLayers = function () {
        if (editorLayersInput) {
            editorLayersInput.value = JSON.stringify(extraLayers);
        }

        scheduleSceneSync();
    };

    const getLayerIdentity = function (element) {
        if (!element) {
            return '';
        }

        if (element.dataset.draggableLayer) {
            return 'extra:' + element.dataset.draggableLayer;
        }

        if (element.dataset.draggable) {
            return 'base:' + element.dataset.draggable;
        }

        return '';
    };

    const disableInlineEditing = function (scope) {
        const root = scope || document;
        root.querySelectorAll('[data-layer-content][contenteditable="true"]').forEach(function (node) {
            node.setAttribute('contenteditable', 'false');
            node.blur();
        });
    };

    const clearSelection = function () {
        disableInlineEditing();
        document.querySelectorAll('.message-email-preview__editable.is-selected').forEach(function (node) {
            node.classList.remove('is-selected');
        });
        selectedEditable = null;
        selectedLayerIdentity = '';
        syncTextStylePanel();
    };

    const selectEditable = function (element) {
        if (!element) {
            clearSelection();
            return;
        }
        document.querySelectorAll('.message-email-preview__editable.is-selected').forEach(function (node) {
            if (node !== element) {
                node.classList.remove('is-selected');
            }
        });
        element.classList.add('is-selected');
        selectedEditable = element;
        selectedLayerIdentity = getLayerIdentity(element);
        syncTextStylePanel();
    };

    const nextLayerOffset = function (type) {
        return extraLayers.filter(function (layer) {
            return layer.type === type;
        }).length * 4;
    };

    const hiddenBooleanValue = function (input, fallback) {
        if (!input) {
            return fallback;
        }

        return String(input.value || (fallback ? '1' : '0')) === '1';
    };

    const hiddenStringValue = function (input, fallback) {
        if (!input) {
            return fallback;
        }

        const value = String(input.value || '').trim();
        return value !== '' ? value : fallback;
    };

    const baseTextStyle = function (type) {
        if (type === 'title') {
            return {
                align: hiddenStringValue(titleAlignInput, 'left'),
                bold: hiddenBooleanValue(titleBoldInput, true),
                italic: hiddenBooleanValue(titleItalicInput, false),
                uppercase: hiddenBooleanValue(titleUppercaseInput, false),
                shadow: hiddenStringValue(titleShadowInput, 'strong')
            };
        }

        return {
            align: hiddenStringValue(bodyAlignInput, 'left'),
            bold: hiddenBooleanValue(bodyBoldInput, false),
            italic: hiddenBooleanValue(bodyItalicInput, false),
            uppercase: hiddenBooleanValue(bodyUppercaseInput, false),
            shadow: hiddenStringValue(bodyShadowInput, 'soft')
        };
    };

    const writeBaseTextStyle = function (type, style) {
        if (type === 'title') {
            if (titleAlignInput) {
                titleAlignInput.value = style.align;
            }
            if (titleBoldInput) {
                titleBoldInput.value = style.bold ? '1' : '0';
            }
            if (titleItalicInput) {
                titleItalicInput.value = style.italic ? '1' : '0';
            }
            if (titleUppercaseInput) {
                titleUppercaseInput.value = style.uppercase ? '1' : '0';
            }
            if (titleShadowInput) {
                titleShadowInput.value = style.shadow;
            }
            return;
        }

        if (bodyAlignInput) {
            bodyAlignInput.value = style.align;
        }
        if (bodyBoldInput) {
            bodyBoldInput.value = style.bold ? '1' : '0';
        }
        if (bodyItalicInput) {
            bodyItalicInput.value = style.italic ? '1' : '0';
        }
        if (bodyUppercaseInput) {
            bodyUppercaseInput.value = style.uppercase ? '1' : '0';
        }
        if (bodyShadowInput) {
            bodyShadowInput.value = style.shadow;
        }
    };

    const normalizeTextStyle = function (style, type) {
        const base = baseTextStyle(type);
        return {
            align: ['left', 'center', 'right'].includes(String(style && style.align || '')) ? String(style.align) : base.align,
            bold: style && typeof style.bold !== 'undefined' ? Boolean(style.bold) : base.bold,
            italic: style && typeof style.italic !== 'undefined' ? Boolean(style.italic) : base.italic,
            uppercase: style && typeof style.uppercase !== 'undefined' ? Boolean(style.uppercase) : base.uppercase,
            shadow: ['off', 'soft', 'strong'].includes(String(style && style.shadow || '')) ? String(style.shadow) : base.shadow
        };
    };

    const textTransformValue = function (text, style) {
        if (!style || !style.uppercase) {
            return text;
        }

        return String(text || '').toLocaleUpperCase('pt-BR');
    };

    const textShadowValue = function (style) {
        if (!style || style.shadow === 'off') {
            return 'none';
        }

        if (style.shadow === 'strong') {
            return '0 6px 22px rgba(0, 0, 0, 0.30)';
        }

        return '0 2px 10px rgba(0, 0, 0, 0.16)';
    };

    const applyTextStyleToNode = function (node, type, style) {
        if (!node) {
            return;
        }

        const resolved = normalizeTextStyle(style, type);
        node.style.textAlign = resolved.align;
        node.style.fontWeight = resolved.bold ? '800' : (type === 'title' ? '700' : '400');
        node.style.fontStyle = resolved.italic ? 'italic' : 'normal';
        node.style.textTransform = resolved.uppercase ? 'uppercase' : 'none';
        node.style.textShadow = textShadowValue(resolved);
    };

    const selectedTextLayerTarget = function () {
        if (!selectedEditable) {
            return null;
        }

        if (selectedEditable.dataset.draggable === 'title') {
            return { mode: 'base', type: 'title' };
        }
        if (selectedEditable.dataset.draggable === 'body') {
            return { mode: 'base', type: 'body' };
        }

        const layerId = selectedEditable.dataset.draggableLayer || '';
        if (layerId === '') {
            return null;
        }

        const layer = extraLayers.find(function (item) {
            return item.id === layerId;
        });

        if (!layer || (layer.type !== 'title' && layer.type !== 'body')) {
            return null;
        }

        return { mode: 'extra', type: layer.type, layer: layer };
    };

    const syncTextStylePanel = function () {
        if (!textStylePanel || !styleAlignControl || !styleBoldControl || !styleItalicControl || !styleUppercaseControl || !styleShadowControl) {
            return;
        }

        const target = selectedTextLayerTarget();
        if (!target) {
            textStylePanel.hidden = true;
            return;
        }

        const style = target.mode === 'base'
            ? baseTextStyle(target.type)
            : normalizeTextStyle(target.layer, target.type);

        if (textStylePanelTitle) {
            textStylePanelTitle.textContent = target.type === 'title'
                ? 'Estilo do titulo selecionado'
                : 'Estilo do texto selecionado';
        }

        styleAlignControl.value = style.align;
        styleBoldControl.checked = style.bold;
        styleItalicControl.checked = style.italic;
        styleUppercaseControl.checked = style.uppercase;
        styleShadowControl.value = style.shadow;
        textStylePanel.hidden = false;
    };

    const updateSelectedTextStyle = function () {
        if (!styleAlignControl || !styleBoldControl || !styleItalicControl || !styleUppercaseControl || !styleShadowControl) {
            return;
        }

        const target = selectedTextLayerTarget();
        if (!target) {
            return;
        }

        const style = {
            align: styleAlignControl.value,
            bold: styleBoldControl.checked,
            italic: styleItalicControl.checked,
            uppercase: styleUppercaseControl.checked,
            shadow: styleShadowControl.value
        };

        if (target.mode === 'base') {
            writeBaseTextStyle(target.type, style);
            syncPreviewLayout();
            return;
        }

        target.layer.align = style.align;
        target.layer.bold = style.bold;
        target.layer.italic = style.italic;
        target.layer.uppercase = style.uppercase;
        target.layer.shadow = style.shadow;
        renderExtraLayers();
    };

    const syncRecipientVisibility = function () {
        const customerMode = recipientModeInput && recipientModeInput.value === 'customer';
        if (customerRow) {
            customerRow.hidden = !customerMode;
        }
        if (!customerMode && customerSelect) {
            customerSelect.value = '';
        }
    };

    const setSelectedPresetCard = function (button) {
        presetCards.forEach(function (card) {
            card.classList.remove('is-selected');
        });

        if (button) {
            const card = button.closest('[data-message-preset-card]');
            if (card) {
                card.classList.add('is-selected');
            }
        }
    };

    const currentButtonLabel = function () {
        const currentKind = messageKindInput ? (messageKindInput.value || 'manual') : 'manual';
        const currentPreset = presetMeta[currentKind] || presetMeta.manual || { cta_label: 'Abrir mensagem' };
        const customLabel = buttonLabelInput ? buttonLabelInput.value.trim() : '';

        return customLabel !== '' ? customLabel : (currentPreset.cta_label || 'Abrir mensagem');
    };

    const rawContentForEditable = function (editable) {
        if (!editable) {
            return '';
        }

        const baseType = editable.dataset.draggable || '';
        if (baseType === 'title') {
            return titleInput ? titleInput.value.trim() : '';
        }
        if (baseType === 'body') {
            return messageInput ? messageInput.value.trim() : '';
        }
        if (baseType === 'button') {
            return currentButtonLabel();
        }

        const layerId = editable.dataset.draggableLayer || '';
        if (!layerId) {
            return '';
        }

        const layer = extraLayers.find(function (item) {
            return item.id === layerId;
        });

        return layer ? String(layer.content || '') : '';
    };

    const buildLayerFromInputs = function (type) {
        const offset = nextLayerOffset(type);

        if (type === 'title') {
            const style = baseTextStyle('title');
            return {
                id: 'layer_' + Date.now() + '_title',
                type: 'title',
                content: titleInput && titleInput.value.trim() !== '' ? titleInput.value.trim() : 'Novo titulo',
                link_url: '',
                x: clamp(getLayoutValue('title_x', 8) + offset, 0, 90),
                y: clamp(getLayoutValue('title_y', 10) + offset, 0, 94),
                width: clamp(getLayoutValue('title_width', 72), 14, 90),
                height: 10,
                font_size: clamp(Number(titleSizeInput ? titleSizeInput.value : 54), 22, 86),
                line_height: clamp(Number(titleLineHeightInput ? titleLineHeightInput.value : 104), 80, 220),
                align: style.align,
                bold: style.bold,
                italic: style.italic,
                uppercase: style.uppercase,
                shadow: style.shadow
            };
        }

        if (type === 'body') {
            const style = baseTextStyle('body');
            return {
                id: 'layer_' + Date.now() + '_body',
                type: 'body',
                content: messageInput && messageInput.value.trim() !== '' ? messageInput.value.trim() : 'Novo texto',
                link_url: '',
                x: clamp(getLayoutValue('body_x', 8) + offset, 0, 90),
                y: clamp(getLayoutValue('body_y', 30) + offset, 0, 94),
                width: clamp(getLayoutValue('body_width', 72), 18, 90),
                height: 16,
                font_size: clamp(Number(bodySizeInput ? bodySizeInput.value : 18), 12, 34),
                line_height: clamp(Number(bodyLineHeightInput ? bodyLineHeightInput.value : 175), 100, 260),
                align: style.align,
                bold: style.bold,
                italic: style.italic,
                uppercase: style.uppercase,
                shadow: style.shadow
            };
        }

        if (type === 'button') {
            return {
                id: 'layer_' + Date.now() + '_button',
                type: 'button',
                content: currentButtonLabel(),
                link_url: linkInput ? linkInput.value.trim() : '',
                x: clamp(getLayoutValue('button_x', 24) + offset, 0, 90),
                y: clamp(getLayoutValue('button_y', 82) + offset, 0, 94),
                width: clamp(getLayoutValue('button_width', 26), 12, 70),
                height: clamp(getLayoutValue('button_height', 11), 6, 26)
            };
        }

        if (type === 'hotspot') {
            const layerLink = imageLinkInput && imageLinkInput.value.trim() !== ''
                ? imageLinkInput.value.trim()
                : (linkInput ? linkInput.value.trim() : '');

            if (layerLink === '') {
                return null;
            }

            return {
                id: 'layer_' + Date.now() + '_hotspot',
                type: 'hotspot',
                content: '',
                link_url: layerLink,
                x: clamp(getLayoutValue('image_hotspot_x', 24) + offset, 0, 90),
                y: clamp(getLayoutValue('image_hotspot_y', 78) + offset, 0, 94),
                width: clamp(Number(hotspotWidthInput ? hotspotWidthInput.value : 26), 4, 90),
                height: clamp(Number(hotspotHeightInput ? hotspotHeightInput.value : 10), 4, 90)
            };
        }

        return null;
    };

    const decorateLayerElement = function (wrapper, options) {
        const type = options.type;
        const isBase = options.isBase === true;
        const key = options.key || type;
        wrapper.classList.add('message-email-preview__editable');
        wrapper.dataset.layerType = type;

        let actions = wrapper.querySelector('.message-email-preview__actions');
        if (!actions) {
            actions = document.createElement('div');
            actions.className = 'message-email-preview__actions';
            wrapper.appendChild(actions);
        } else {
            actions.innerHTML = '';
        }

        const duplicate = document.createElement('button');
        duplicate.type = 'button';
        duplicate.className = 'message-email-preview__action';
        duplicate.dataset.duplicateLayer = isBase ? key : (options.layerId || '');
        duplicate.dataset.duplicateMode = isBase ? 'base' : 'extra';
        duplicate.setAttribute('aria-label', 'Duplicar elemento');
        duplicate.textContent = '+';
        duplicate.addEventListener('pointerdown', function (event) {
            event.stopPropagation();
        });
        actions.appendChild(duplicate);

        const remove = wrapper.querySelector('.message-email-preview__hide');
        if (remove) {
            remove.classList.add('message-email-preview__action');
            remove.dataset.removeMode = isBase ? 'base' : 'extra';
            if (isBase) {
                remove.dataset.hideElement = key;
            }
            remove.addEventListener('pointerdown', function (event) {
                event.stopPropagation();
            });
            actions.appendChild(remove);
        }

        ['n', 'e', 's', 'w', 'ne', 'nw', 'se', 'sw'].forEach(function (handle) {
            let handleNode = wrapper.querySelector('[data-resize-handle="' + handle + '"]');
            if (!handleNode) {
                handleNode = document.createElement('button');
                handleNode.type = 'button';
                handleNode.className = 'message-email-preview__resize message-email-preview__resize--' + handle;
                handleNode.dataset.resizeHandle = handle;
                wrapper.appendChild(handleNode);
            }

            handleNode.dataset.resizeMode = isBase ? 'base' : 'extra';
            if (isBase) {
                handleNode.dataset.resizeLayer = key;
            } else {
                handleNode.dataset.resizeLayer = options.layerId || '';
            }
            handleNode.setAttribute('aria-label', 'Redimensionar elemento');
        });
    };

    const renderExtraLayers = function () {
        if (!extraLayersHost) {
            return;
        }

        extraLayersHost.innerHTML = '';

        extraLayers.forEach(function (layer) {
            const wrapper = document.createElement('div');
            wrapper.dataset.extraLayerId = layer.id;
            wrapper.dataset.draggableLayer = layer.id;

            const hide = document.createElement('button');
            hide.type = 'button';
            hide.className = 'message-email-preview__hide';
            hide.dataset.removeExtraLayer = layer.id;
            hide.setAttribute('aria-label', 'Remover elemento');
            hide.textContent = 'x';

            if (layer.type === 'title' || layer.type === 'body') {
                wrapper.className = 'message-email-preview__overlay message-email-preview__overlay--' + (layer.type === 'title' ? 'title' : 'body');
                wrapper.style.left = clamp(Number(layer.x || 0), 0, 90) + '%';
                wrapper.style.top = clamp(Number(layer.y || 0), 0, 94) + '%';
                wrapper.style.width = clamp(Number(layer.width || (layer.type === 'title' ? 72 : 72)), layer.type === 'title' ? 14 : 18, 90) + '%';

                if (layer.type === 'title') {
                    const strong = document.createElement('strong');
                    strong.className = 'message-email-preview__title';
                    strong.style.fontSize = clamp(Number(layer.font_size || (titleSizeInput ? titleSizeInput.value : 54)), 22, 86) + 'px';
                    strong.style.lineHeight = (clamp(Number(layer.line_height || (titleLineHeightInput ? titleLineHeightInput.value : 104)), 80, 220) / 100).toFixed(2);
                    strong.style.color = titleColorInput ? titleColorInput.value : '#fff7f0';
                    strong.textContent = replacePreviewTokens(String(layer.content || 'Novo titulo'));
                    strong.dataset.layerContent = 'title';
                    strong.contentEditable = 'false';
                    strong.spellcheck = false;
                    applyTextStyleToNode(strong, 'title', layer);
                    wrapper.appendChild(strong);
                } else {
                    const copy = document.createElement('div');
                    copy.className = 'message-email-preview__copy';
                    copy.style.fontSize = clamp(Number(layer.font_size || (bodySizeInput ? bodySizeInput.value : 18)), 12, 34) + 'px';
                    copy.style.lineHeight = (clamp(Number(layer.line_height || (bodyLineHeightInput ? bodyLineHeightInput.value : 175)), 100, 260) / 100).toFixed(2);
                    copy.style.color = bodyColorInput ? bodyColorInput.value : '#2c1917';
                    copy.innerHTML = replacePreviewTokens(String(layer.content || 'Novo texto'))
                        .split(/\n+/)
                        .map(function (item) { return item.trim(); })
                        .filter(Boolean)
                        .map(escapeHtml)
                        .join('<br><br>');
                    copy.dataset.layerContent = 'body';
                    copy.contentEditable = 'false';
                    copy.spellcheck = false;
                    applyTextStyleToNode(copy, 'body', layer);
                    wrapper.appendChild(copy);
                }
            } else if (layer.type === 'button') {
                wrapper.className = 'message-email-preview__cta-layer';
                wrapper.style.left = clamp(Number(layer.x || 0), 0, 90) + '%';
                wrapper.style.top = clamp(Number(layer.y || 0), 0, 94) + '%';
                wrapper.style.width = clamp(Number(layer.width || 26), 12, 70) + '%';
                wrapper.style.minHeight = clamp(Number(layer.height || 11), 6, 26) + '%';

                const button = document.createElement('span');
                button.className = 'message-email-preview__button';
                if (!layer.link_url) {
                    button.classList.add('is-disabled');
                }
                button.textContent = replacePreviewTokens(String(layer.content || 'Abrir mensagem'));
                button.dataset.layerContent = 'button';
                button.contentEditable = 'false';
                button.spellcheck = false;
                wrapper.appendChild(button);
            } else if (layer.type === 'hotspot') {
                wrapper.className = 'message-email-preview__hotspot';
                wrapper.style.left = clamp(Number(layer.x || 0), 0, 90) + '%';
                wrapper.style.top = clamp(Number(layer.y || 0), 0, 94) + '%';
                wrapper.style.width = clamp(Number(layer.width || 26), 4, 90) + '%';
                wrapper.style.height = clamp(Number(layer.height || 10), 4, 90) + '%';
                if (!layer.link_url) {
                    wrapper.classList.add('is-disabled');
                }
            }

            wrapper.appendChild(hide);
            decorateLayerElement(wrapper, { isBase: false, type: layer.type, layerId: layer.id });
            extraLayersHost.appendChild(wrapper);

            if (selectedLayerIdentity !== '' && selectedLayerIdentity === getLayerIdentity(wrapper)) {
                wrapper.classList.add('is-selected');
                selectedEditable = wrapper;
            }
        });

        persistExtraLayers();
        syncTextStylePanel();
    };

    const syncPreviewTitle = function () {
        if (!titlePreview || !titleInput) {
            return;
        }

        const value = titleInput.value.trim();
        titlePreview.textContent = replacePreviewTokens(value !== '' ? value : 'Seu titulo aparece aqui');
        scheduleSceneSync();
    };

    const syncPreviewBody = function () {
        if (!bodyPreview || !messageInput) {
            return;
        }

        const value = messageInput.value.trim();
        const previewValue = replacePreviewTokens(value !== '' ? value : 'O texto principal do email aparece aqui, abaixo da imagem.');
        const paragraphs = previewValue
            .split(/\n+/)
            .map(function (item) { return item.trim(); })
            .filter(Boolean)
            .slice(0, 3);

        bodyPreview.innerHTML = paragraphs.map(escapeHtml).join('<br><br>');
        scheduleSceneSync();
    };

    const syncPreviewButton = function () {
        if (!buttonPreview) {
            return;
        }

        buttonPreview.textContent = replacePreviewTokens(currentButtonLabel());
        buttonPreview.classList.toggle('is-disabled', !linkInput || linkInput.value.trim() === '');
        scheduleSceneSync();
    };

    const syncPreviewLayout = function () {
        if (liveValueTargets.title_size && titleSizeInput) {
            liveValueTargets.title_size.textContent = clamp(Number(titleSizeInput.value), 22, 86) + 'px';
        }

        if (liveValueTargets.body_size && bodySizeInput) {
            liveValueTargets.body_size.textContent = clamp(Number(bodySizeInput.value), 12, 34) + 'px';
        }

        if (liveValueTargets.image_hotspot_width && hotspotWidthInput) {
            liveValueTargets.image_hotspot_width.textContent = clamp(Number(hotspotWidthInput.value), 4, 90) + '%';
        }

        if (liveValueTargets.image_hotspot_height && hotspotHeightInput) {
            liveValueTargets.image_hotspot_height.textContent = clamp(Number(hotspotHeightInput.value), 4, 90) + '%';
        }

        if (hotspotSettings) {
            hotspotSettings.hidden = !(showHotspotInput && showHotspotInput.checked);
        }

        if (titleBlock && titlePreview) {
            titleBlock.style.left = getLayoutValue('title_x', 8) + '%';
            titleBlock.style.top = getLayoutValue('title_y', 10) + '%';
            titleBlock.style.width = getLayoutValue('title_width', 72) + '%';
            titlePreview.style.fontSize = clamp(Number(titleSizeInput ? titleSizeInput.value : 54), 22, 86) + 'px';
            titlePreview.style.lineHeight = (clamp(Number(titleLineHeightInput ? titleLineHeightInput.value : 104), 80, 220) / 100).toFixed(2);
            titlePreview.style.color = titleColorInput ? titleColorInput.value : '#fff7f0';
            applyTextStyleToNode(titlePreview, 'title', baseTextStyle('title'));
            titleBlock.hidden = !(showTitleInput && showTitleInput.checked);
        }

        if (bodyBlock && bodyPreview) {
            bodyBlock.style.left = getLayoutValue('body_x', 8) + '%';
            bodyBlock.style.top = getLayoutValue('body_y', 30) + '%';
            bodyBlock.style.width = getLayoutValue('body_width', 72) + '%';
            bodyPreview.style.fontSize = clamp(Number(bodySizeInput ? bodySizeInput.value : 18), 12, 34) + 'px';
            bodyPreview.style.lineHeight = (clamp(Number(bodyLineHeightInput ? bodyLineHeightInput.value : 175), 100, 260) / 100).toFixed(2);
            bodyPreview.style.color = bodyColorInput ? bodyColorInput.value : '#2c1917';
            applyTextStyleToNode(bodyPreview, 'body', baseTextStyle('body'));
            bodyBlock.hidden = !(showBodyInput && showBodyInput.checked);
        }

        if (buttonBlock) {
            buttonBlock.style.left = getLayoutValue('button_x', 24) + '%';
            buttonBlock.style.top = getLayoutValue('button_y', 82) + '%';
            buttonBlock.style.width = getLayoutValue('button_width', 26) + '%';
            buttonBlock.style.minHeight = getLayoutValue('button_height', 11) + '%';
            buttonBlock.hidden = !(showButtonInput && showButtonInput.checked);
        }

        if (hotspotBlock) {
            hotspotBlock.style.left = getLayoutValue('image_hotspot_x', 24) + '%';
            hotspotBlock.style.top = getLayoutValue('image_hotspot_y', 78) + '%';
            hotspotBlock.style.width = clamp(Number(hotspotWidthInput ? hotspotWidthInput.value : 26), 4, 90) + '%';
            hotspotBlock.style.height = clamp(Number(hotspotHeightInput ? hotspotHeightInput.value : 10), 4, 90) + '%';
            hotspotBlock.hidden = !(showHotspotInput && showHotspotInput.checked);
            hotspotBlock.classList.toggle('is-disabled', !imageLinkInput || imageLinkInput.value.trim() === '');
        }

        renderExtraLayers();
        scheduleSceneSync();
    };

    const syncPreviewImage = function () {
        if (!heroPreview || !heroInput || !heroImagePreview) {
            return;
        }

        if (heroObjectUrl) {
            URL.revokeObjectURL(heroObjectUrl);
            heroObjectUrl = null;
        }

        const file = heroInput.files && heroInput.files[0] ? heroInput.files[0] : null;

        if (file) {
            heroObjectUrl = URL.createObjectURL(file);
            heroPreview.classList.add('has-image');
            heroPreview.classList.remove('has-gradient');
            heroPreview.dataset.existingImageUrl = '';
            heroImagePreview.src = heroObjectUrl;
            heroImagePreview.hidden = false;

            if (heroName) {
                heroName.textContent = file.name;
            }
            if (heroClearButton) {
                heroClearButton.hidden = false;
            }

            scheduleSceneSync();
            return;
        }

        const savedImageUrl = heroPreview.dataset.existingImageUrl || '';
        if (savedImageUrl !== '') {
            heroPreview.classList.add('has-image');
            heroPreview.classList.remove('has-gradient');
            heroImagePreview.src = savedImageUrl;
            heroImagePreview.hidden = false;

            if (heroName) {
                const savedPath = currentHeroImagePathInput ? currentHeroImagePathInput.value.trim() : '';
                heroName.textContent = savedPath !== '' ? savedPath.split('/').pop() : 'Imagem salva';
            }
            if (heroClearButton) {
                heroClearButton.hidden = false;
            }

            scheduleSceneSync();
            return;
        }

        heroPreview.classList.remove('has-image');
        heroPreview.classList.add('has-gradient');
        heroImagePreview.removeAttribute('src');
        heroImagePreview.hidden = true;

        if (heroName) {
            heroName.textContent = 'Nenhuma imagem selecionada';
        }
        if (heroClearButton) {
            heroClearButton.hidden = true;
        }

        scheduleSceneSync();
    };

    [titleBlock, bodyBlock, buttonBlock, hotspotBlock].filter(Boolean).forEach(function (element) {
        const key = element.dataset.draggable || '';
        if (!key) {
            return;
        }
        decorateLayerElement(element, { isBase: true, type: key, key: key });
    });

    if (titlePreview) {
        titlePreview.contentEditable = 'false';
        titlePreview.spellcheck = false;
    }

    if (bodyPreview) {
        bodyPreview.contentEditable = 'false';
        bodyPreview.spellcheck = false;
    }

    if (buttonPreview) {
        buttonPreview.contentEditable = 'false';
        buttonPreview.spellcheck = false;
    }

    hideButtons.forEach(function (button) {
        button.textContent = 'x';
    });

    presetButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (messageKindInput) {
                messageKindInput.value = button.dataset.presetKey || 'manual';
            }
            if (titleInput) {
                titleInput.value = button.dataset.presetTitle || '';
            }
            if (messageInput) {
                messageInput.value = button.dataset.presetMessage || '';
            }
            if (linkInput) {
                linkInput.value = button.dataset.presetLink || '';
            }
            if (buttonLabelInput) {
                const kind = button.dataset.presetKey || 'manual';
                const preset = presetMeta[kind] || presetMeta.manual || { cta_label: 'Abrir mensagem' };
                buttonLabelInput.value = preset.cta_label || '';
            }
            if (recipientModeInput && button.dataset.presetMode) {
                recipientModeInput.value = button.dataset.presetMode;
            }

            syncRecipientVisibility();
            setSelectedPresetCard(button);
            syncPreviewTitle();
            syncPreviewBody();
            syncPreviewButton();
            syncPreviewLayout();
            if (titleInput) {
                titleInput.focus();
                titleInput.setSelectionRange(titleInput.value.length, titleInput.value.length);
            }
        });
    });

    if (recipientModeInput) {
        recipientModeInput.addEventListener('change', syncRecipientVisibility);
    }

    if (titleInput) {
        titleInput.addEventListener('input', syncPreviewTitle);
    }

    if (messageInput) {
        messageInput.addEventListener('input', syncPreviewBody);
    }

    if (linkInput) {
        linkInput.addEventListener('input', syncPreviewButton);
    }

    if (imageLinkInput) {
        imageLinkInput.addEventListener('input', syncPreviewLayout);
    }

    if (buttonLabelInput) {
        buttonLabelInput.addEventListener('input', syncPreviewButton);
    }

    [styleAlignControl, styleBoldControl, styleItalicControl, styleUppercaseControl, styleShadowControl]
        .filter(Boolean)
        .forEach(function (control) {
            control.addEventListener('input', updateSelectedTextStyle);
            control.addEventListener('change', updateSelectedTextStyle);
        });

    if (titlePreview && titleInput) {
        titlePreview.addEventListener('input', function () {
            titleInput.value = titlePreview.textContent.replace(/\s+\n/g, '\n').trim();
        });
        titlePreview.addEventListener('blur', function () {
            titlePreview.setAttribute('contenteditable', 'false');
            syncPreviewTitle();
        });
    }

    if (bodyPreview && messageInput) {
        bodyPreview.addEventListener('input', function () {
            const text = bodyPreview.innerText.replace(/\r/g, '').trim();
            messageInput.value = text;
        });
        bodyPreview.addEventListener('blur', function () {
            bodyPreview.setAttribute('contenteditable', 'false');
            syncPreviewBody();
        });
    }

    if (buttonPreview && buttonLabelInput) {
        buttonPreview.addEventListener('input', function () {
            buttonLabelInput.value = buttonPreview.textContent.trim();
        });
        buttonPreview.addEventListener('blur', function () {
            buttonPreview.setAttribute('contenteditable', 'false');
            syncPreviewButton();
        });
    }

    [showTitleInput, showBodyInput, showButtonInput, showHotspotInput, titleSizeInput, bodySizeInput, titleColorInput, bodyColorInput, hotspotWidthInput, hotspotHeightInput]
        .filter(Boolean)
        .forEach(function (input) {
            input.addEventListener('input', syncPreviewLayout);
            input.addEventListener('change', syncPreviewLayout);
        });

    if (heroInput) {
        heroInput.addEventListener('change', syncPreviewImage);
    }

    if (heroClearButton && heroInput) {
        heroClearButton.addEventListener('click', function () {
            heroInput.value = '';
            if (currentHeroImagePathInput) {
                currentHeroImagePathInput.value = '';
            }
            if (heroPreview) {
                heroPreview.dataset.existingImageUrl = '';
            }
            syncPreviewImage();
        });
    }

    hideButtons.forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            const key = button.dataset.hideElement || '';

            if (key === 'title' && showTitleInput) {
                showTitleInput.checked = false;
            } else if (key === 'body' && showBodyInput) {
                showBodyInput.checked = false;
            } else if (key === 'button' && showButtonInput) {
                showButtonInput.checked = false;
            } else if (key === 'hotspot' && showHotspotInput) {
                showHotspotInput.checked = false;
            }

            syncPreviewLayout();
            clearSelection();
        });
    });

    addLayerButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const type = button.dataset.addLayer || '';
            const layer = buildLayerFromInputs(type);

            if (!layer) {
                return;
            }

            extraLayers.push(layer);
            selectedLayerIdentity = 'extra:' + layer.id;
            renderExtraLayers();
        });
    });

    const dragMap = {
        title: ['title_x', 'title_y'],
        body: ['body_x', 'body_y'],
        button: ['button_x', 'button_y'],
        hotspot: ['image_hotspot_x', 'image_hotspot_y'],
    };

    const beginDrag = function (event) {
        const target = event.currentTarget;
        const key = target.dataset.draggable || '';
        const layoutKeys = dragMap[key];

        if (!layoutKeys || !heroPreview || event.target.closest('[data-hide-element], [data-duplicate-layer], [data-resize-handle]') || event.target.isContentEditable) {
            return;
        }

        event.preventDefault();
        selectEditable(target);

        activeDrag = {
            mode: 'base',
            xKey: layoutKeys[0],
            yKey: layoutKeys[1],
            startX: event.clientX,
            startY: event.clientY,
            originX: getLayoutValue(layoutKeys[0], 0),
            originY: getLayoutValue(layoutKeys[1], 0),
            rect: heroPreview.getBoundingClientRect(),
        };

        document.body.classList.add('message-editor-dragging');
    };

    const beginExtraLayerDrag = function (event) {
        const target = this instanceof Element ? this : event.currentTarget;
        const layerId = target.dataset.draggableLayer || '';

        if (!layerId || !heroPreview || event.target.closest('[data-remove-extra-layer], [data-duplicate-layer], [data-resize-handle]') || event.target.isContentEditable) {
            return;
        }

        const layerIndex = extraLayers.findIndex(function (layer) {
            return layer.id === layerId;
        });

        if (layerIndex === -1) {
            return;
        }

        event.preventDefault();
        selectEditable(target);

        activeDrag = {
            mode: 'extra',
            layerIndex: layerIndex,
            startX: event.clientX,
            startY: event.clientY,
            originX: Number(extraLayers[layerIndex].x || 0),
            originY: Number(extraLayers[layerIndex].y || 0),
            rect: heroPreview.getBoundingClientRect(),
        };

        document.body.classList.add('message-editor-dragging');
    };

    const beginResize = function (event) {
        const handleTarget = event.target && event.target.closest
            ? event.target.closest('[data-resize-handle]')
            : null;
        const source = handleTarget || event.currentTarget;
        const handle = source && source.dataset ? (source.dataset.resizeHandle || '') : '';
        const mode = source && source.dataset ? (source.dataset.resizeMode || 'base') : 'base';
        const targetKey = source && source.dataset ? (source.dataset.resizeLayer || '') : '';

        if (!handle || !targetKey || !heroPreview) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        if (source && source.setPointerCapture && typeof event.pointerId === 'number') {
            try {
                source.setPointerCapture(event.pointerId);
            } catch (exception) {
                // Ignora falha de capture.
            }
        }
        selectEditable(source.closest('.message-email-preview__editable'));

        if (mode === 'base') {
            const spec = layerSpecs[targetKey] || {};
            activeDrag = {
                mode: 'base-resize',
                handle: handle,
                key: targetKey,
                startX: event.clientX,
                startY: event.clientY,
                rect: heroPreview.getBoundingClientRect(),
                originX: getLayoutValue(targetKey + '_x', 0),
                originY: getLayoutValue(targetKey + '_y', 0),
                originWidth: spec.widthKey ? getLayoutValue(spec.widthKey, spec.minWidth || 24) : null,
                originHeight: spec.heightKey ? getLayoutValue(spec.heightKey, spec.minHeight || 10) : null,
                originFont: spec.fontInput ? Number(spec.fontInput.value || 0) : null,
                originLineHeight: spec.lineHeightInput ? Number(spec.lineHeightInput.value || 0) : null,
                spec: spec
            };
        } else {
            const layerIndex = extraLayers.findIndex(function (layer) {
                return layer.id === targetKey;
            });
            if (layerIndex === -1) {
                return;
            }

            activeDrag = {
                mode: 'extra-resize',
                handle: handle,
                layerIndex: layerIndex,
                startX: event.clientX,
                startY: event.clientY,
                rect: heroPreview.getBoundingClientRect(),
                originX: Number(extraLayers[layerIndex].x || 0),
                originY: Number(extraLayers[layerIndex].y || 0),
                originWidth: Number(extraLayers[layerIndex].width || 26),
                originHeight: Number(extraLayers[layerIndex].height || 10),
                originFont: Number(extraLayers[layerIndex].font_size || (extraLayers[layerIndex].type === 'title' ? 54 : 18)),
                originLineHeight: Number(extraLayers[layerIndex].line_height || (extraLayers[layerIndex].type === 'title' ? 104 : 175))
            };
        }

        document.body.classList.add('message-editor-dragging');
    };

    const applyResizeDelta = function (payload) {
        const isTextLayer = payload.type === 'title' || payload.type === 'body';
        const minWidth = isTextLayer ? (payload.type === 'title' ? 14 : 18) : (payload.type === 'button' ? 12 : 4);
        const maxWidth = payload.type === 'button' ? 70 : 90;
        const minHeight = payload.type === 'button' ? 6 : 4;
        const maxHeight = payload.type === 'button' ? 26 : 90;
        const minFont = payload.type === 'title' ? 22 : 12;
        const maxFont = payload.type === 'title' ? 86 : 34;
        const minLineHeight = payload.type === 'title' ? 80 : 100;
        const maxLineHeight = payload.type === 'title' ? 220 : 260;
        const widthDelta = payload.deltaX;
        const heightDelta = payload.deltaY;
        const pixelDeltaY = payload.pixelDeltaY || 0;

        if (payload.handle.indexOf('e') !== -1 && payload.originWidth !== null) {
            payload.setWidth(clamp(payload.originWidth + widthDelta, minWidth, maxWidth));
        }
        if (payload.handle.indexOf('w') !== -1 && payload.originWidth !== null) {
            const nextWidth = clamp(payload.originWidth - widthDelta, minWidth, maxWidth);
            const consumed = payload.originWidth - nextWidth;
            payload.setWidth(nextWidth);
            payload.setX(clamp(payload.originX + consumed, 0, 90));
        }

        if (isTextLayer && (payload.handle === 'n' || payload.handle === 's')) {
            if (payload.handle === 's' && payload.originLineHeight !== null) {
                payload.setLineHeight(clamp(payload.originLineHeight + (pixelDeltaY * 0.9), minLineHeight, maxLineHeight));
            }
            if (payload.handle === 'n' && payload.originLineHeight !== null) {
                payload.setLineHeight(clamp(payload.originLineHeight - (pixelDeltaY * 0.9), minLineHeight, maxLineHeight));
            }
        } else if (isTextLayer) {
            if (payload.handle.indexOf('s') !== -1) {
                payload.setFont(clamp(payload.originFont + (heightDelta * 0.9), minFont, maxFont));
            }
            if (payload.handle.indexOf('n') !== -1) {
                payload.setFont(clamp(payload.originFont - (heightDelta * 0.9), minFont, maxFont));
            }
        } else {
            if (payload.handle.indexOf('s') !== -1 && payload.originHeight !== null) {
                payload.setHeight(clamp(payload.originHeight + heightDelta, minHeight, maxHeight));
            }
            if (payload.handle.indexOf('n') !== -1 && payload.originHeight !== null) {
                const nextHeight = clamp(payload.originHeight - heightDelta, minHeight, maxHeight);
                const consumed = payload.originHeight - nextHeight;
                payload.setHeight(nextHeight);
                payload.setY(clamp(payload.originY + consumed, 0, 94));
            }
        }
    };

    const handleDragMove = function (event) {
        if (!activeDrag) {
            return;
        }

        const deltaX = ((event.clientX - activeDrag.startX) / activeDrag.rect.width) * 100;
        const deltaY = ((event.clientY - activeDrag.startY) / activeDrag.rect.height) * 100;

        if (activeDrag.mode === 'extra') {
            extraLayers[activeDrag.layerIndex].x = clamp(activeDrag.originX + deltaX, 0, 90);
            extraLayers[activeDrag.layerIndex].y = clamp(activeDrag.originY + deltaY, 0, 94);
            renderExtraLayers();
            return;
        }

        if (activeDrag.mode === 'base-resize') {
            applyResizeDelta({
                type: activeDrag.key,
                handle: activeDrag.handle,
                deltaX: deltaX,
                deltaY: deltaY,
                pixelDeltaY: event.clientY - activeDrag.startY,
                originX: activeDrag.originX,
                originY: activeDrag.originY,
                originWidth: activeDrag.originWidth,
                originHeight: activeDrag.originHeight,
                originFont: activeDrag.originFont,
                originLineHeight: activeDrag.originLineHeight,
                setX: function (value) { setLayoutValue(activeDrag.key + '_x', value); },
                setY: function (value) { setLayoutValue(activeDrag.key + '_y', value); },
                setWidth: function (value) { if (activeDrag.spec.widthKey) { setLayoutValue(activeDrag.spec.widthKey, value); } },
                setHeight: function (value) { if (activeDrag.spec.heightKey) { setLayoutValue(activeDrag.spec.heightKey, value); } },
                setFont: function (value) { if (activeDrag.spec.fontInput) { activeDrag.spec.fontInput.value = String(Math.round(value)); } },
                setLineHeight: function (value) { if (activeDrag.spec.lineHeightInput) { activeDrag.spec.lineHeightInput.value = String(Math.round(value)); } }
            });
            syncPreviewLayout();
            return;
        }

        if (activeDrag.mode === 'extra-resize') {
            const layer = extraLayers[activeDrag.layerIndex];
            if (!layer) {
                return;
            }
            applyResizeDelta({
                type: layer.type,
                handle: activeDrag.handle,
                deltaX: deltaX,
                deltaY: deltaY,
                pixelDeltaY: event.clientY - activeDrag.startY,
                originX: activeDrag.originX,
                originY: activeDrag.originY,
                originWidth: activeDrag.originWidth,
                originHeight: activeDrag.originHeight,
                originFont: activeDrag.originFont,
                originLineHeight: activeDrag.originLineHeight,
                setX: function (value) { layer.x = value; },
                setY: function (value) { layer.y = value; },
                setWidth: function (value) { layer.width = value; },
                setHeight: function (value) { layer.height = value; },
                setFont: function (value) { layer.font_size = Math.round(value); },
                setLineHeight: function (value) { layer.line_height = Math.round(value); }
            });
            renderExtraLayers();
            return;
        }

        setLayoutValue(activeDrag.xKey, clamp(activeDrag.originX + deltaX, 0, 90));
        setLayoutValue(activeDrag.yKey, clamp(activeDrag.originY + deltaY, 0, 94));
        syncPreviewLayout();
    };

    const endDrag = function () {
        activeDrag = null;
        document.body.classList.remove('message-editor-dragging');
    };

    [titleBlock, bodyBlock, buttonBlock, hotspotBlock].filter(Boolean).forEach(function (element) {
        element.addEventListener('pointerdown', beginDrag);
    });

    if (extraLayersHost) {
        extraLayersHost.addEventListener('click', function (event) {
            const layerElement = event.target.closest('.message-email-preview__editable');
            if (layerElement) {
                selectEditable(layerElement);
                return;
            }

            if (!event.target.closest('.message-email-preview__actions') && !event.target.closest('[data-resize-handle]')) {
                clearSelection();
            }
        });

        extraLayersHost.addEventListener('click', function (event) {
            const duplicateButton = event.target.closest('[data-duplicate-layer]');
            if (duplicateButton) {
                event.preventDefault();
                event.stopPropagation();
                const mode = duplicateButton.dataset.duplicateMode || 'extra';
                const id = duplicateButton.dataset.duplicateLayer || '';

                if (mode === 'base') {
                    const layer = cloneBaseLayerToExtra(id);
                    if (layer) {
                        extraLayers.push(layer);
                        renderExtraLayers();
                    }
                    return;
                }

                const source = extraLayers.find(function (layer) {
                    return layer.id === id;
                });
                if (source) {
                    const clone = JSON.parse(JSON.stringify(source));
                    clone.id = 'layer_' + Date.now() + '_' + source.type;
                    clone.x = clamp(Number(source.x || 0) + 3, 0, 90);
                    clone.y = clamp(Number(source.y || 0) + 3, 0, 94);
                    extraLayers.push(clone);
                    selectedLayerIdentity = 'extra:' + clone.id;
                    renderExtraLayers();
                }
                return;
            }

            const removeButton = event.target.closest('[data-remove-extra-layer]');
            if (!removeButton) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            const layerId = removeButton.dataset.removeExtraLayer || '';
            extraLayers = extraLayers.filter(function (layer) {
                return layer.id !== layerId;
            });
            renderExtraLayers();
            clearSelection();
        });

        extraLayersHost.addEventListener('pointerdown', function (event) {
            const resizeHandle = event.target.closest('[data-resize-handle]');
            if (resizeHandle) {
                beginResize(event);
                return;
            }
            const layerElement = event.target.closest('[data-draggable-layer]');
            if (!layerElement) {
                return;
            }

            beginExtraLayerDrag.call(layerElement, event);
        });

        extraLayersHost.addEventListener('input', function (event) {
            const layerElement = event.target.closest('[data-draggable-layer]');
            if (!layerElement) {
                return;
            }

            const layerId = layerElement.dataset.draggableLayer || '';
            const layer = extraLayers.find(function (item) {
                return item.id === layerId;
            });
            if (!layer) {
                return;
            }

            layer.content = event.target.innerText.replace(/\r/g, '').trim();
            persistExtraLayers();
        });

        extraLayersHost.addEventListener('blur', function (event) {
            const layerElement = event.target.closest('[data-draggable-layer]');
            if (!layerElement) {
                return;
            }
            if (event.target.matches('[data-layer-content]')) {
                event.target.setAttribute('contenteditable', 'false');
            }
            renderExtraLayers();
        }, true);
    }

    [titleBlock, bodyBlock, buttonBlock, hotspotBlock].filter(Boolean).forEach(function (element) {
        element.addEventListener('click', function (event) {
            if (!event.target.closest('[data-hide-element], [data-duplicate-layer], [data-resize-handle]')) {
                selectEditable(element);
            }
        });
    });

    if (heroPreview) {
        heroPreview.addEventListener('dblclick', function (event) {
            const contentNode = event.target.closest('[data-layer-content]');
            const editable = event.target.closest('.message-email-preview__editable');
            if (!contentNode || !editable) {
                return;
            }

            selectEditable(editable);
            disableInlineEditing();
            const rawContent = rawContentForEditable(editable);
            if (contentNode.dataset.layerContent === 'body') {
                contentNode.innerText = rawContent !== '' ? rawContent : 'Novo texto';
            } else if (contentNode.dataset.layerContent === 'button') {
                contentNode.textContent = rawContent !== '' ? rawContent : 'Abrir mensagem';
            } else {
                contentNode.textContent = rawContent !== '' ? rawContent : 'Novo titulo';
            }
            contentNode.setAttribute('contenteditable', 'true');
            contentNode.focus();

            const selection = window.getSelection();
            if (selection) {
                const range = document.createRange();
                range.selectNodeContents(contentNode);
                range.collapse(false);
                selection.removeAllRanges();
                selection.addRange(range);
            }
        });

        heroPreview.addEventListener('click', function (event) {
            if (!event.target.closest('.message-email-preview__editable')) {
                clearSelection();
            }
        });
    }

    const baseDuplicateButtons = Array.from(document.querySelectorAll('[data-duplicate-mode="base"]'));
    baseDuplicateButtons.forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            const key = button.dataset.duplicateLayer || '';
            const layer = cloneBaseLayerToExtra(key);
            if (!layer) {
                return;
            }
            extraLayers.push(layer);
            selectedLayerIdentity = 'extra:' + layer.id;
            renderExtraLayers();
        });
    });

    const baseResizeHandles = Array.from(document.querySelectorAll('[data-resize-mode="base"]'));
    baseResizeHandles.forEach(function (handle) {
        handle.addEventListener('pointerdown', beginResize);
    });

    window.addEventListener('pointermove', handleDragMove);
    window.addEventListener('pointerup', endDrag);
    window.addEventListener('pointercancel', endDrag);

    syncRecipientVisibility();
    syncPreviewTitle();
    syncPreviewBody();
    syncPreviewButton();
    syncPreviewLayout();
    syncPreviewImage();
    renderExtraLayers();
});
