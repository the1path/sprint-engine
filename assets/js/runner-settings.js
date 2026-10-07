/* Runner preview; keep colour math in parity with RunnerBranding. */
(function () {
    'use strict';
    function luminance(hex) {
        const rgb = hex.slice(1).match(/../g).map(c => {
            const value = parseInt(c, 16) / 255;
            return value <= 0.04045 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2];
    }
    function foreground(hex) {
        const value = luminance(hex);
        return (value + 0.05) / 0.05 > 1.05 / (value + 0.05) ? '#000000' : '#ffffff';
    }
    function mix(a, b, weight) {
        return '#' + [1, 3, 5].map(i => Math.round(parseInt(a.slice(i, i + 2), 16) * weight + parseInt(b.slice(i, i + 2), 16) * (1 - weight)).toString(16).padStart(2, '0')).join('');
    }
    function variables(config, radii) {
        const result = {};
        ['primary', 'background', 'surface', 'text', 'muted'].forEach(key => { result['--se-' + key] = config[key]; });
        result['--se-on-primary'] = /^#[0-9a-f]{6}$/i.test(config.primary_text || '') ? config.primary_text : foreground(config.primary);
        result['--se-radius'] = radii[config.corner_style];
        result['--se-control-radius'] = config.corner_style === 'soft' ? '0.5rem' : radii[config.corner_style];
        const baseline = config.primary === '#205b48' && config.surface === '#ffffff';
        const derived = {
            hover: ['#164333', '#000000', 0.75], accent: ['#276a55', config.surface, 1],
            link: ['#165b4b', config.surface, 1], label: ['#386453', config.surface, 1],
            'secondary-text': ['#245440', config.surface, 1], secondary: ['#eef4f0', config.surface, 0.08],
            'secondary-hover': ['#dceae1', config.surface, 0.16], border: ['#d5e0db', config.surface, 0.2],
            'control-border': ['#c7d8ce', config.surface, 0.25], track: ['#dce6e0', config.surface, 0.18],
            task: ['#f0f6f3', config.surface, 0.06], quote: ['#83a593', config.surface, 0.55],
            'notice-border': ['#aabcb2', config.surface, 0.35]
        };
        Object.entries(derived).forEach(([key, parts]) => { result['--se-' + key] = baseline ? parts[0] : mix(config.primary, parts[1], parts[2]); });
        if (!baseline && foreground(config.primary) === '#000000') result['--se-hover'] = mix(config.primary, '#ffffff', 0.85);
        if (!baseline) {
            result['--se-secondary-text'] = foreground(result['--se-secondary']);
            result['--se-label'] = mix(config.primary, foreground(config.surface), 0.25);
            result['--se-link'] = result['--se-label'];
        }
        return result;
    }
    if (typeof module !== 'undefined' && module.exports) { module.exports = {luminance, foreground, variables}; return; }
    const preview = document.getElementById('se-branding-preview');
    if (!preview || typeof sprintEngineBranding === 'undefined') return;
    const config = Object.assign({}, sprintEngineBranding.settings);
    function update() {
        Object.entries(variables(config, sprintEngineBranding.radii)).forEach(([key, value]) => preview.style.setProperty(key, value));
        const contrast = (a, b) => (Math.max(luminance(a), luminance(b)) + 0.05) / (Math.min(luminance(a), luminance(b)) + 0.05);
        document.getElementById('se-contrast-warning').hidden = contrast(config.primary_text || foreground(config.primary), config.primary) >= 4.5 && ['text', 'muted'].every(key => ['surface', 'background'].every(bg => contrast(config[key], config[bg]) >= 4.5));
    }
    document.querySelectorAll('.se-color').forEach(input => {
        const change = value => {
            if (input.dataset.color === 'primary_text' && value === '') { config.primary_text = ''; update(); return; }
            if (/^#[0-9a-f]{6}$/i.test(value)) { config[input.dataset.color] = value.toLowerCase(); update(); }
        };
        input.addEventListener('input', () => change(input.value));
        jQuery(input).wpColorPicker({change: (event, ui) => change(ui.color.toString()), clear: () => change(input.dataset.color === 'primary_text' ? '' : sprintEngineBranding.settings[input.dataset.color])});
    });
    document.getElementById('se-corner-style').addEventListener('change', event => { config.corner_style = event.target.value; update(); });
    function logo(attachment) {
        document.getElementById('se-logo-id').value = attachment ? attachment.id : 0;
        ['se-logo-thumbnail', 'se-preview-logo'].forEach(id => {
            const container = document.getElementById(id);
            container.replaceChildren();
            if (attachment) {
                const image = document.createElement('img');
                image.src = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                image.alt = attachment.alt || sprintEngineBranding.alt;
                image.className = 'se-runner__logo';
                container.appendChild(image);
            }
        });
    }
    let frame;
    document.getElementById('se-choose-logo').addEventListener('click', () => {
        if (!frame) {
            frame = wp.media({title: sprintEngineBranding.choose, button: {text: sprintEngineBranding.use}, library: {type: 'image'}, multiple: false});
            frame.on('select', () => logo(frame.state().get('selection').first().toJSON()));
        }
        frame.open();
    });
    document.getElementById('se-remove-logo').addEventListener('click', () => logo(null));
    update();
}());
