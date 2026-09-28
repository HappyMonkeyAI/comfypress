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
    components: { Button: 'Button', TextareaControl: 'TextareaControl', Spinner: 'Spinner' },
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

function makeEditorRuntime(fetchImpl, settings = {}) {
    const registeredBlocks = {};
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
        components: { Button: 'Button', TextareaControl: 'TextareaControl', Spinner: 'Spinner' },
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
        render: () => {
            stateCursor = 0;
            refCursor = 0;
            return registeredBlocks['comfy/image-generator'].edit({ className: 'test-block' });
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
    assert.equal(runtime.state[1], 'done', 'editor reports successful completion');
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
    assert.equal(cancelRuntime.state[1], 'cancelled', 'cancel control stops local result polling');
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

    if (!registered['comfy/image-generator']) {
        throw new Error('Gutenberg block was not registered');
    }
    console.log('PASS: 18 JavaScript contract checks');
})().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
