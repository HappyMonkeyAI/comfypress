(function (wp) {
    var registerBlockType = wp.blocks.registerBlockType;
    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var Button = wp.components.Button;
    var TextareaControl = wp.components.TextareaControl;
    var Spinner = wp.components.Spinner;
    var createBlock = wp.blocks.createBlock;
    var dispatch = wp.data.dispatch;

    registerBlockType('comfy/image-generator', {
        title: 'Comfy Image',
        icon: 'format-image',
        category: 'media',
        attributes: {},
        edit: function (props) {
            var [prompt, setPrompt] = useState('');
            var [status, setStatus] = useState('idle');
            var [progress, setProgress] = useState(null);

            var restBase = (typeof ComfyImageSettings !== 'undefined' && ComfyImageSettings.rest_base) ? ComfyImageSettings.rest_base : '/wp-json/comfy-image/v1';

            function submitWorkflow() {
                setStatus('submitting');
                setProgress('Sending workflow...');

                var workflow = null;
                try {
                    if (ComfyImageSettings.default_workflow_template) {
                        // Try to parse stored template
                        workflow = JSON.parse(ComfyImageSettings.default_workflow_template);
                    }
                } catch (e) {
                    workflow = null;
                }

                if (!workflow) {
                    // Fallback simple workflow structure
                    workflow = {
                        title: 'generated',
                        nodes: [
                            {
                                id: 'txt2img',
                                type: 'txt2img',
                                params: {
                                    prompt: prompt,
                                    steps: 20,
                                    seed: Math.floor(Math.random() * 100000)
                                }
                            }
                        ]
                    };
                } else {
                    // Inject prompt into workflow where appropriate
                    var inject = function (obj) {
                        for (var k in obj) {
                            if (!obj.hasOwnProperty(k)) continue;
                            if (k === 'prompt' && (!obj[k] || obj[k] === '')) {
                                obj[k] = prompt;
                            } else if (typeof obj[k] === 'object') {
                                inject(obj[k]);
                            }
                        }
                    };
                    inject(workflow);
                }

                // POST to submit-workflow
                fetch(restBase + '/submit-workflow', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ workflow: workflow, prompt: prompt })
                }).then(function (res) { return res.json(); })
                .then(function (data) {
                    setStatus('submitted');
                    setProgress('Workflow submitted. Polling status...');
                    // Try to obtain a prompt_id
                    var prompt_id = data && (data.prompt_id || data.id || data.name || data._id);
                    // If no prompt_id, try to parse data
                    if (!prompt_id && data && typeof data === 'object') {
                        // Some ComfyUI responses include 'id' or 'prompt_id'
                        prompt_id = data.id || data.prompt_id;
                    }

                    if (!prompt_id) {
                        // If we can't get a prompt id, show raw response and stop
                        setStatus('error');
                        setProgress('Unable to determine prompt id from response.');
                        console.error('Submit workflow response', data);
                        return;
                    }

                    // Poll for status
                    var attempts = 0;
                    var maxAttempts = 40; // ~2 minutes if interval 3s
                    var interval = 3000;

                    var poll = function () {
                        attempts++;
                        setProgress('Checking status (attempt ' + attempts + ')...');
                        fetch(restBase + '/check-status/' + encodeURIComponent(prompt_id), {
                            method: 'GET', credentials: 'same-origin'
                        }).then(function (r) { return r.json(); })
                        .then(function (st) {
                            // Try to find a filename in the response
                            var filename = null;
                            if (!st) st = {};
                            // Common fields: outputs, files, result
                            if (st.outputs && Array.isArray(st.outputs) && st.outputs.length) {
                                filename = st.outputs[0];
                            } else if (st.files && Array.isArray(st.files) && st.files.length) {
                                filename = st.files[0];
                            } else if (st.result) {
                                filename = st.result;
                            } else if (st.length && Array.isArray(st) && st.length) {
                                filename = st[0];
                            } else if (st.data && st.data.length) {
                                filename = st.data[0];
                            }

                            if (filename) {
                                setProgress('Result available: ' + filename + '. Importing...');
                                // Call fetch-image?import=1 to import into media library
                                fetch(restBase + '/fetch-image?filename=' + encodeURIComponent(filename) + '&import=1', {
                                    method: 'GET', credentials: 'same-origin'
                                }).then(function (ri) { return ri.json(); })
                                .then(function (imp) {
                                    if (imp && imp.attachment_id) {
                                        setProgress('Imported. Inserting image...');
                                        // Insert image block with attachment id
                                        try {
                                            var imgBlock = createBlock('core/image', {id: imp.attachment_id, url: imp.url});
                                            dispatch('core/block-editor').insertBlocks(imgBlock);
                                            setStatus('done');
                                            setProgress('Image inserted');
                                        } catch (e) {
                                            // Fallback: insert HTML
                                            var html = '<img src="' + imp.url + '" alt="Generated image" />';
                                            dispatch('core/editor').insertBlock(createBlock('core/html', {content: html}));
                                            setStatus('done');
                                            setProgress('Image inserted (fallback)');
                                        }
                                    } else {
                                        setStatus('error');
                                        setProgress('Import failed: ' + JSON.stringify(imp));
                                    }
                                }).catch(function (err) {
                                    setStatus('error');
                                    setProgress('Import fetch failed');
                                    console.error(err);
                                });

                                return;
                            }

                            if (attempts < maxAttempts) {
                                setTimeout(poll, interval);
                            } else {
                                setStatus('error');
                                setProgress('Timed out polling for result');
                            }
                        }).catch(function (err) {
                            setStatus('error');
                            setProgress('Status check failed');
                            console.error(err);
                        });
                    };

                    poll();
                }).catch(function (err) {
                    setStatus('error');
                    setProgress('Submit failed');
                    console.error(err);
                });
            }

            return el('div', { className: props.className },
                el(TextareaControl, { label: 'Prompt', value: prompt, onChange: function (v) { setPrompt(v); } }),
                el('div', { style: { marginTop: '8px' } },
                    el(Button, { isPrimary: true, onClick: submitWorkflow, disabled: status === 'submitting' || status === 'submitted' }, 'Generate'),
                    status === 'submitting' || status === 'submitted' ? el('span', { style: { marginLeft: '10px' } }, el(Spinner)) : null
                ),
                progress ? el('div', { style: { marginTop: '8px', color: status === 'error' ? '#b62d2d' : '#333' } }, progress) : null
            );
        },
        save: function () { return null; }
    });
})(window.wp);