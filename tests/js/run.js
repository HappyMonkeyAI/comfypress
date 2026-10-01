const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const registered = {};
const wp = {
    blocks: {
        registerBlockType: (name, settings) => { registered[name] = settings; },
        createBlock: () => ({}),
    },
    element: {
        createElement: () => ({}),
        useState: () => [null, () => {}],
    },
    components: { Button: 'Button', TextareaControl: 'TextareaControl', TextControl: 'TextControl', Spinner: 'Spinner' },
    data: { dispatch: () => ({ insertBlocks: () => {} }) },
};
const context = { window: { wp }, console, Promise, setTimeout, fetch: () => Promise.reject(new Error('unexpected fetch')) };
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../comfy-image/assets/block.js'), 'utf8'), context);

const helpers = context.window.ComfyImageHelpers;
assert.ok(helpers, 'block script exposes namespaced response helpers');

const history = {
    'prompt-123': {
        outputs: {
            '9': { images: [{ filename: 'result.png', subfolder: '2026/09', type: 'output' }] },
        },
    },
};
assert.equal(JSON.stringify(helpers.extractOutputImage(history, 'prompt-123')), JSON.stringify({
    filename: 'result.png', subfolder: '2026/09', type: 'output',
}));
assert.equal(helpers.extractOutputImage({ 'prompt-123': { outputs: {} } }, 'prompt-123'), null);
assert.equal(
    helpers.buildFetchImageUrl('/wp-json/comfy-image/v1', 'filename=out.png&import=1'),
    '/wp-json/comfy-image/v1/fetch-image?filename=out.png&import=1',
    'pretty REST URLs use a question mark for fetch-image parameters',
);
assert.equal(
    helpers.buildFetchImageUrl('/index.php?rest_route=/comfy-image/v1', 'filename=out.png&import=1'),
    '/index.php?rest_route=/comfy-image/v1/fetch-image&filename=out.png&import=1',
    'query-based REST URLs append fetch-image parameters without replacing rest_route',
);

function makeEditorRuntime(fetchImpl, settings = {}) {
    const registeredBlocks = {};
    let blockAttributes = {};
    const state = [];
    const refs = [];
    const inserted = [];
    const timers = [];
    let stateCursor = 0;
    let refCursor = 0;
    const wpEditor = {
        blocks: {
            registerBlockType: (name, settings) => { registeredBlocks[name] = settings; },
            createBlock: (name, attributes) => ({ name, attributes }),
        },
        element: {
            createElement: (type, props, ...children) => ({ type, props: props || {}, children }),
            useState: (initial) => {
                const index = stateCursor++;
                if (!(index in state)) state[index] = initial;
                return [state[index], (value) => { state[index] = value; }];
            },
            useRef: (initial) => {
                const index = refCursor++;
                if (!refs[index]) refs[index] = { current: initial };
                return refs[index];
            },
        },
        components: { Button: 'Button', TextareaControl: 'TextareaControl', TextControl: 'TextControl', Spinner: 'Spinner' },
        data: { dispatch: () => ({ insertBlocks: (block) => inserted.push(block) }) },
    };
    const runtime = {
        window: { wp: wpEditor },
        ComfyImageSettings: Object.assign({
            rest_base: '/wp-json/comfy-image/v1',
            nonce: 'wp-test-nonce',
            auto_save_to_media: true,
            default_workflow_template: JSON.stringify({ '4': { inputs: { text: '{{prompt}}' } } }),
        }, settings),
        fetch: fetchImpl,
        setTimeout: (callback, delay) => {
            const timer = { callback, delay, cleared: false };
            timers.push(timer);
            return timer;
        },
        clearTimeout: (timer) => { if (timer) timer.cleared = true; },
        console,
    };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../comfy-image/assets/block.js'), 'utf8'), runtime);
    return {
        runtime,
        state,
        inserted,
        timers,
        get blockSettings() { return registeredBlocks['comfy/image-generator']; },
        get blockAttributes() { return Object.assign({}, blockAttributes); },
        render: (attributes) => {
            if (attributes) blockAttributes = Object.assign({}, attributes);
            stateCursor = 0;
            refCursor = 0;
            return registeredBlocks['comfy/image-generator'].edit({
                className: 'test-block',
                attributes: blockAttributes,
                setAttributes: (nextAttributes) => { blockAttributes = Object.assign({}, blockAttributes, nextAttributes); },
            });
        },
    };
}

function findElement(tree, type) {
    if (!tree || typeof tree !== 'object') return null;
    if (tree.type === type) return tree;
    for (const child of tree.children || []) {
        const found = findElement(child, type);
        if (found) return found;
    }
    return null;
}

function findElements(tree, type, found = []) {
    if (!tree || typeof tree !== 'object') return found;
    if (tree.type === type) found.push(tree);
    for (const child of tree.children || []) findElements(child, type, found);
    return found;
}

(async () => {
    const promptRuntime = makeEditorRuntime(async () => ({ ok: true, json: async () => ({}) }));
    assert.equal(promptRuntime.blockSettings.attributes.prompt.type, 'string', 'generator prompt is declared as a saved string attribute');
    let promptTree = promptRuntime.render({ prompt: 'Saved prompt' });
    assert.equal(findElement(promptTree, 'TextareaControl').props.value, 'Saved prompt', 'the editor restores the prompt from saved block attributes');
    findElement(promptTree, 'TextareaControl').props.onChange('Edited prompt');
    assert.equal(promptRuntime.blockAttributes.prompt, 'Edited prompt', 'prompt edits update the saved block attribute');
    promptTree = promptRuntime.render();
    assert.equal(findElement(promptTree, 'TextareaControl').props.value, 'Edited prompt', 'the editor reflects prompt attribute updates');

    const overrideRuntimeCalls = [];
    const overrideRuntime = makeEditorRuntime(async (url, options) => {
        overrideRuntimeCalls.push({ url, options });
        return Promise.reject(new Error('stop after submit'));
    }, {
        default_workflow_template: JSON.stringify({
            noise: { inputs: { noise_seed: '{{seed}}' } },
            sampler: { inputs: { steps: '{{steps}}' } },
            guider: { inputs: { cfg: '{{cfg}}' } },
            text: { inputs: { value: '{{prompt}}' } },
        }),
    });
    let overrideTree = overrideRuntime.render({ prompt: 'Stored prompt', seed: '0', steps: '4', cfg: '5.25' });
    assert.equal(overrideRuntime.blockSettings.attributes.seed.type, 'string', 'seed override is saved as a string to preserve full unsigned 64-bit values');
    assert.equal(overrideRuntime.blockSettings.attributes.steps.type, 'string', 'steps override is saved as an optional string');
    assert.equal(overrideRuntime.blockSettings.attributes.cfg.type, 'string', 'CFG override is saved as an optional string');
    let overrideControls = findElements(overrideTree, 'TextControl');
    assert.equal(overrideControls.length, 3, 'the editor renders seed, steps, and CFG controls');
    assert.equal(overrideControls[0].props.type, 'text', 'the seed field preserves exact 64-bit decimal input');
    assert.deepEqual(overrideControls.map((control) => control.props.value), ['0', '4', '5.25'], 'saved override values are restored in the editor');
    overrideControls[0].props.onChange('42');
    assert.equal(overrideRuntime.blockAttributes.seed, '42', 'seed edits update the saved block attribute');
    overrideTree = overrideRuntime.render();
    findElement(overrideTree, 'TextareaControl').props.onChange('A saved prompt');
    overrideTree = overrideRuntime.render();
    findElement(overrideTree, 'Button').props.onClick();
    const overridePayload = JSON.parse(overrideRuntimeCalls[0].options.body);
    assert.equal(overridePayload.seed, '42', 'submit sends the saved seed override');
    assert.equal(overridePayload.steps, '4', 'submit sends the saved steps override');
    assert.equal(overridePayload.cfg, '5.25', 'submit sends the saved CFG override');
    assert.equal(overridePayload.prompt, 'A saved prompt', 'overrides do not replace the separate prompt field');

    const optionalRuntimeCalls = [];
    const optionalRuntime = makeEditorRuntime(async (url, options) => {
        optionalRuntimeCalls.push({ url, options });
        return Promise.reject(new Error('stop after submit'));
    }, {
        default_workflow_template: JSON.stringify({
            noise: { inputs: { noise_seed: '{{seed}}' } },
            text: { inputs: { value: '{{prompt}}' } },
        }),
    });
    const optionalTree = optionalRuntime.render({ prompt: 'A seeded image', seed: '0', steps: '', cfg: '' });
    findElement(optionalTree, 'Button').props.onClick();
    const optionalPayload = JSON.parse(optionalRuntimeCalls[0].options.body);
    assert.equal(optionalPayload.seed, '0', 'an explicit zero seed is sent rather than treated as unset');
    assert.equal(Object.prototype.hasOwnProperty.call(optionalPayload, 'steps'), false, 'an empty steps field is omitted');
    assert.equal(Object.prototype.hasOwnProperty.call(optionalPayload, 'cfg'), false, 'an empty CFG field is omitted');

    const ok = await helpers.parseResponse({ ok: true, json: async () => ({ prompt_id: 'prompt-123' }) });
    assert.equal(ok.prompt_id, 'prompt-123');

    await assert.rejects(
        helpers.parseResponse({ ok: false, json: async () => ({ error: 'ComfyUI rejected the workflow' }) }),
        /ComfyUI rejected the workflow/,
    );

    const calls = [];
    const runtime = makeEditorRuntime(async (url, options) => {
        calls.push({ url, options });
        let payload;
        if (calls.length === 1) {
            payload = { prompt_id: 'prompt-1' };
        } else if (calls.length === 2) {
            payload = { 'prompt-1': { outputs: { '9': { images: [{ filename: 'out.png', subfolder: 'batch/a', type: 'output' }] } } } };
        } else {
            payload = { attachment_id: 42, url: '/uploads/out.png' };
        }
        return { ok: true, json: async () => payload };
    });
    let tree = runtime.render();
    findElement(tree, 'TextareaControl').props.onChange('A blue cabin');
    tree = runtime.render();
    findElement(tree, 'Button').props.onClick();
    for (let i = 0; i < 12; i++) await new Promise((resolve) => setImmediate(resolve));
    assert.equal(calls.length, 3, 'editor performs submit, history poll, and image import');
    assert.equal(JSON.parse(calls[0].options.body).prompt, 'A blue cabin', 'editor sends the prompt separately from the workflow template');
    assert.equal(calls[0].options.headers['X-WP-Nonce'], 'wp-test-nonce', 'editor sends the WordPress REST nonce');
    assert.match(calls[2].url, /subfolder=batch%2Fa/, 'editor preserves ComfyUI output subfolders');
    assert.equal(runtime.inserted[0].name, 'core/image', 'editor inserts a native image block');
    assert.equal(runtime.inserted[0].attributes.id, 42, 'editor inserts the returned media attachment');
    assert.equal(runtime.state[0], 'done', 'editor reports successful completion');
    assert.ok(registered['comfy/image-generator'].keywords.includes('comfy'), 'block is discoverable by the /comfy slash inserter query');

    const duplicateCalls = [];
    const duplicateRuntime = makeEditorRuntime(async (url, options) => {
        duplicateCalls.push({ url, options });
        return { ok: false, json: async () => ({ error: 'pending' }) };
    });
    tree = duplicateRuntime.render();
    findElement(tree, 'TextareaControl').props.onChange('One request only');
    tree = duplicateRuntime.render();
    const generateClick = findElement(tree, 'Button').props.onClick;
    generateClick();
    generateClick();
    assert.equal(duplicateCalls.length, 1, 'rapid repeated clicks cannot submit duplicate workflows');

    const cancelCalls = [];
    const cancelRuntime = makeEditorRuntime(async (url, options) => {
        cancelCalls.push({ url, options });
        const payload = cancelCalls.length === 1 ? { prompt_id: 'prompt-pending' } : {};
        return { ok: true, json: async () => payload };
    });
    tree = cancelRuntime.render();
    findElement(tree, 'TextareaControl').props.onChange('A forest');
    tree = cancelRuntime.render();
    findElement(tree, 'Button').props.onClick();
    for (let i = 0; i < 12; i++) await new Promise((resolve) => setImmediate(resolve));
    tree = cancelRuntime.render();
    const buttons = findElements(tree, 'Button');
    assert.equal(buttons.length, 2, 'active polling exposes a cancel control');
    buttons[1].props.onClick();
    assert.equal(cancelRuntime.state[0], 'cancelled', 'cancel control stops local result polling');
    assert.equal(cancelRuntime.timers[0].cleared, true, 'cancel control clears the scheduled poll timer');

    const remoteCalls = [];
    const remoteRuntime = makeEditorRuntime(async (url, options) => {
        remoteCalls.push({ url, options });
        let payload;
        if (remoteCalls.length === 1) payload = { prompt_id: 'prompt-remote' };
        else if (remoteCalls.length === 2) payload = { 'prompt-remote': { outputs: { '9': { images: [{ filename: 'remote.png', subfolder: '', type: 'output' }] } } } };
        else payload = { view_url: 'http://comfy.test/view?filename=remote.png&type=output' };
        return { ok: true, json: async () => payload };
    }, { auto_save_to_media: false });
    tree = remoteRuntime.render();
    findElement(tree, 'TextareaControl').props.onChange('A remote image');
    tree = remoteRuntime.render();
    findElement(tree, 'Button').props.onClick();
    for (let i = 0; i < 12; i++) await new Promise((resolve) => setImmediate(resolve));
    assert.doesNotMatch(remoteCalls[2].url, /import=1/, 'disabled auto-save requests only the remote view URL');
    assert.equal(remoteRuntime.inserted[0].attributes.url, 'http://comfy.test/view?filename=remote.png&type=output', 'remote-view setting inserts the ComfyUI image URL');

    const localizedFalseCalls = [];
    const localizedFalseRuntime = makeEditorRuntime(async (url, options) => {
        localizedFalseCalls.push({ url, options });
        let payload;
        if (localizedFalseCalls.length === 1) payload = { prompt_id: 'prompt-localized-false' };
        else if (localizedFalseCalls.length === 2) payload = { 'prompt-localized-false': { outputs: { '9': { images: [{ filename: 'remote.png', subfolder: '', type: 'output' }] } } } };
        else payload = { view_url: 'http://comfy.test/view?filename=remote.png&type=output' };
        return { ok: true, json: async () => payload };
    }, { auto_save_to_media: '' });
    tree = localizedFalseRuntime.render();
    findElement(tree, 'TextareaControl').props.onChange('A localized disabled setting');
    tree = localizedFalseRuntime.render();
    findElement(tree, 'Button').props.onClick();
    for (let i = 0; i < 12; i++) await new Promise((resolve) => setImmediate(resolve));
    assert.doesNotMatch(localizedFalseCalls[2].url, /import=1/, 'WordPress-localized false setting does not request Media Library import');
    assert.equal(localizedFalseRuntime.inserted[0].attributes.url, 'http://comfy.test/view?filename=remote.png&type=output', 'WordPress-localized false setting inserts the remote view URL');

    if (!registered['comfy/image-generator']) {
        throw new Error('Gutenberg block was not registered');
    }
    console.log('PASS: 38 JavaScript contract checks');
})().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
