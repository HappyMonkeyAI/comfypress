# ComfyPress example workflows

These are ComfyUI API-format text-to-image workflows used by the Settings > Comfy Image example selector. Each has the required `{{prompt}}` marker in its positive text input. The supplied editor-format source workflows at the repository root are left unchanged.

- `flux2-klein-4b.api.json`: 512×512, 4 steps; requires `flux-2-klein-base-4b.safetensors`, `qwen_3_4b.safetensors`, and `flux2-vae.safetensors`. This variant was previously submitted directly to ComfyUI and completed successfully; that prior smoke test predates the settings picker.
- `z-image-turbo.api.json`: 512×512, 8 steps; requires `z_image_turbo_bf16.safetensors`, `qwen_3_4b.safetensors`, and `ae.safetensors`.
- `kandinsky5-lite.api.json`: 512×512, 8 steps; requires `kandinsky5lite_t2i.safetensors`, `qwen_2.5_vl_7b_fp8_scaled.safetensors`, `clip_l.safetensors`, and `ae.safetensors`.

The Z-Image and Kandinsky examples are API-format adaptations of the supplied ComfyUI editor workflows. Their original editor workflows previously completed direct ComfyUI smoke tests after conversion in the ComfyUI frontend; these packaged API adaptations have not yet been submitted to a live ComfyUI server. Model/node availability and ComfyUI version compatibility may vary. Image settings are modest defaults, not quality recommendations.
