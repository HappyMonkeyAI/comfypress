(function (wp) {
    var registerBlockType = wp.blocks.registerBlockType;
    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useRef = wp.element.useRef;
    var Button = wp.components.Button;
    var TextareaControl = wp.components.TextareaControl;
    var TextControl = wp.components.TextControl;
    var Spinner = wp.components.Spinner;
    var createBlock = wp.blocks.createBlock;
    var dispatch = wp.data.dispatch;

    function parseResponse(response) {
        return response.json().catch(function () {
            return {};
        }).then(function (data) {
            if (!response.ok) {
                var message = data && typeof data.error === 'string' ? data.error : 'Request failed';
                throw new Error(message.slice(0, 300));
            }
            return data;
        });
    }

    function extractOutputImage(history, promptId) {
        if (!history || typeof history !== 'object') return null;

        var entry = promptId && history[promptId];
        if (!entry && history.outputs) entry = history;
        if (!entry) {
            var entries = Object.keys(history).map(function (key) { return history[key]; });
            entry = entries.find(function (item) { return item && item.outputs; });
        }
        if (!entry || !entry.outputs || typeof entry.outputs !== 'object') return null;

        var nodeOutputs = Object.keys(entry.outputs).map(function (key) { return entry.outputs[key]; });
        for (var i = 0; i < nodeOutputs.length; i++) {
            var images = nodeOutputs[i] && nodeOutputs[i].images;
            if (Array.isArray(images) && images.length && images[0] && typeof images[0].filename === 'string') {
                return {
                    filename: images[0].filename,
                    subfolder: typeof images[0].subfolder === 'string' ? images[0].subfolder : '',
                    type: typeof images[0].type === 'string' ? images[0].type : 'output'
                };
            }
        }
        return null;
    }

    function buildFetchImageUrl(restBase, query) {
        var endpoint = restBase + '/fetch-image';
        return endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + query;
    }

    window.ComfyImageHelpers = {
        parseResponse: parseResponse,
        extractOutputImage: extractOutputImage,
        buildFetchImageUrl: buildFetchImageUrl
    };

    registerBlockType('comfy/image-generator', {
        title: 'Comfy Image Generator',
        description: 'Generate and insert an image with ComfyUI.',
        keywords: ['comfy', 'image', 'generate', 'prompt'],
        icon: 'format-image',
        category: 'media',
        attributes: {
            prompt: { type: 'string', default: '' },
            seed: { type: 'string', default: '' },
            steps: { type: 'string', default: '' },
            cfg: { type: 'string', default: '' }
        },
        edit: function (props) {
            var blockAttributes = props.attributes || {};
            var prompt = typeof blockAttributes.prompt === 'string' ? blockAttributes.prompt : '';
            var seed = typeof blockAttributes.seed === 'string' ? blockAttributes.seed : '';
            var steps = typeof blockAttributes.steps === 'string' ? blockAttributes.steps : '';
            var cfg = typeof blockAttributes.cfg === 'string' ? blockAttributes.cfg : '';
            var [status, setStatus] = useState('idle');
            var [progress, setProgress] = useState(null);
            var pollRef = useRef({ timer: null, cancelled: false, inFlight: false, runId: 0 });

            var pluginSettings = typeof ComfyImageSettings !== 'undefined' ? ComfyImageSettings : {};
            var restBase = pluginSettings.rest_base || '/wp-json/comfy-image/v1';

            // Default headers (include WP REST nonce if available)
            var defaultHeaders = {};
            if (pluginSettings.nonce) {
                defaultHeaders['X-WP-Nonce'] = pluginSettings.nonce;
            }

            function submitWorkflow() {
                if (pollRef.current.inFlight) return;
                pollRef.current.inFlight = true;
                pollRef.current.cancelled = false;
                var runId = ++pollRef.current.runId;
                var isCurrentRun = function () { return pollRef.current.runId === runId; };
                var fail = function (message) {
                    if (!isCurrentRun()) return;
                    pollRef.current.inFlight = false;
                    setStatus('error');
                    setProgress(message);
                };

                setStatus('submitting');
                setProgress('Sending workflow...');

                var workflow = null;
                try {
                    workflow = JSON.parse(pluginSettings.default_workflow_template || '');
                } catch (e) {
                    fail('Configure a valid ComfyUI API workflow in Settings > Comfy Image.');
                    return;
                }

                if (!workflow || typeof workflow !== 'object' || Array.isArray(workflow) || !prompt.trim()) {
                    fail('Enter a prompt and configure a ComfyUI API workflow before generating.');
                    return;
                }

                var requestPayload = { workflow: workflow, prompt: prompt };
                ['seed', 'steps', 'cfg'].forEach(function (name) {
                    var value = blockAttributes[name];
                    if (typeof value === 'string' && value.trim() !== '') {
                        requestPayload[name] = value.trim();
                    }
                });

                // POST to submit-workflow
                fetch(restBase + '/submit-workflow', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: Object.assign({ 'Content-Type': 'application/json' }, defaultHeaders),
                    body: JSON.stringify(requestPayload)
                }).then(parseResponse)
                .then(function (data) {
                    if (!isCurrentRun()) return;
                    setStatus('submitted');
                    setProgress('Workflow submitted. Polling status...');
                    var prompt_id = data && (data.prompt_id || data.id || data.name || data._id);
                    if (!prompt_id) {
                        fail('ComfyUI did not return a prompt ID.');
                        return;
                    }

                    // Poll for status
                    var attempts = 0;
                    var maxAttempts = 40; // ~2 minutes if interval 3s
                    var interval = 3000;
                    var saveToMediaEnabled = pluginSettings.auto_save_to_media !== false && pluginSettings.auto_save_to_media !== '';

                    var poll = function () {
                        if (!isCurrentRun() || pollRef.current.cancelled) return;
                        attempts++;
                        setProgress('Checking status (attempt ' + attempts + ')...');
                        fetch(restBase + '/check-status/' + encodeURIComponent(prompt_id), {
                            method: 'GET', credentials: 'same-origin', headers: defaultHeaders
                        }).then(parseResponse)
                        .then(function (st) {
                            if (!isCurrentRun() || pollRef.current.cancelled) return;
                            var image = extractOutputImage(st, prompt_id);

                            if (image) {
                                setProgress(saveToMediaEnabled ? 'Result available. Importing into the Media Library...' : 'Result available. Loading the ComfyUI image...');
                                var imageQuery = 'filename=' + encodeURIComponent(image.filename) +
                                    '&subfolder=' + encodeURIComponent(image.subfolder) +
                                    '&type=' + encodeURIComponent(image.type);
                                if (saveToMediaEnabled) imageQuery += '&import=1';
                                fetch(buildFetchImageUrl(restBase, imageQuery), {
                                    method: 'GET', credentials: 'same-origin', headers: defaultHeaders
                                }).then(parseResponse)
                                .then(function (imp) {
                                    if (!isCurrentRun() || pollRef.current.cancelled) return;
                                    if (imp && imp.attachment_id) {
                                        setProgress('Imported. Inserting image...');
                                        var imgBlock = createBlock('core/image', {id: imp.attachment_id, url: imp.url});
                                        dispatch('core/block-editor').insertBlocks(imgBlock);
                                        pollRef.current.inFlight = false;
                                        setStatus('done');
                                        setProgress('Image inserted');
                                    } else if (imp && imp.view_url) {
                                        var remoteImageBlock = createBlock('core/image', {url: imp.view_url});
                                        dispatch('core/block-editor').insertBlocks(remoteImageBlock);
                                        pollRef.current.inFlight = false;
                                        setStatus('done');
                                        setProgress('Image inserted from ComfyUI; it was not copied to the Media Library.');
                                    } else {
                                        fail('Image import did not return a Media Library attachment.');
                                    }
                                }).catch(function (err) {
                                    if (!isCurrentRun() || pollRef.current.cancelled) return;
                                    fail(err.message || 'Image import failed.');
                                });

                                return;
                            }

                            if (attempts < maxAttempts) {
                                pollRef.current.timer = setTimeout(poll, interval);
                            } else {
                                fail('Timed out polling for result');
                            }
                        }).catch(function (err) {
                            if (!isCurrentRun() || pollRef.current.cancelled) return;
                            fail(err.message || 'Status check failed.');
                        });
                    };

                    poll();
                }).catch(function (err) {
                    fail(err.message || 'Submit failed.');
                });
            }

            function cancelPolling() {
                pollRef.current.cancelled = true;
                pollRef.current.runId++;
                pollRef.current.inFlight = false;
                if (pollRef.current.timer) {
                    clearTimeout(pollRef.current.timer);
                    pollRef.current.timer = null;
                }
                setStatus('cancelled');
                setProgress('Status polling stopped. The ComfyUI job may continue running.');
            }

            function setNumericAttribute(name, value) {
                var attributes = {};
                attributes[name] = value;
                props.setAttributes(attributes);
            }

            return el('div', { className: props.className },
                el(TextareaControl, { label: 'Prompt', value: prompt, onChange: function (v) { props.setAttributes({ prompt: v }); } }),
                el(TextControl, {
                    label: 'Seed override (optional)',
                    help: '0–18446744073709551615. Add {{seed}} as a complete value in the workflow template.',
                    type: 'text', inputMode: 'numeric', pattern: '[0-9]*', maxLength: 20, value: seed,
                    onChange: function (value) { setNumericAttribute('seed', value); }
                }),
                el(TextControl, {
                    label: 'Steps override (optional)',
                    help: '1–4096. Add {{steps}} as a complete value in the workflow template.',
                    type: 'number', min: '1', max: '4096', step: '1', value: steps,
                    onChange: function (value) { setNumericAttribute('steps', value); }
                }),
                el(TextControl, {
                    label: 'CFG override (optional)',
                    help: '0–100 with up to two decimal places. Add {{cfg}} as a complete value in the workflow template.',
                    type: 'number', min: '0', max: '100', step: '0.01', value: cfg,
                    onChange: function (value) { setNumericAttribute('cfg', value); }
                }),
                el('div', { style: { marginTop: '8px' } },
                    el(Button, { isPrimary: true, onClick: submitWorkflow, disabled: status === 'submitting' || status === 'submitted' }, status === 'error' || status === 'cancelled' ? 'Retry' : 'Generate'),
                    status === 'submitted' ? el(Button, { isSecondary: true, onClick: cancelPolling }, 'Stop polling') : null,
                    status === 'submitting' || status === 'submitted' ? el('span', { style: { marginLeft: '10px' } }, el(Spinner)) : null
                ),
                progress ? el('div', { style: { marginTop: '8px', color: status === 'error' ? '#b62d2d' : '#333' } }, progress) : null
            );
        },
        save: function () { return null; }
    });
})(window.wp);