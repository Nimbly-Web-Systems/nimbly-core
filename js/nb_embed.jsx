const nb_embed = {
    init() {
        document.addEventListener('nb:ready', () => {
            document.querySelectorAll('iframe[data-nb-embed][srcdoc]').forEach(frame => {
                const start = () => frame.contentWindow?.postMessage('nb:embed-ready', '*');
                frame.addEventListener('load', start);
                start();
            });
        }, { once: true });
    },
    url(value) {
        try {
            const url = new URL(value);
            if (!/^https:\/\//i.test(value) || /[\x00-\x20\\]/.test(value) || url.username || url.password) return false;
            return url.protocol === 'https:';
        } catch { return false; }
    },
    dimension(value, fallback) {
        return /^[1-9][0-9]{0,4}$/.test(String(value)) ? Math.min(10000, Number(value)) : fallback;
    },
    parse(code) {
        // An inert template does not fetch frames, images, styles or scripts.
        const template = document.createElement('template');
        template.innerHTML = code;
        const frames = template.content.querySelectorAll('iframe');
        if (frames.length !== 1) throw new Error('Paste one iframe with optional external HTTPS scripts.');
        const frame = frames[0];
        if (frame.hasAttribute('srcdoc') || !this.url(frame.getAttribute('src') || '')) throw new Error('Use an absolute HTTPS iframe URL; srcdoc is not accepted.');
        const scripts = [];
        for (const script of template.content.querySelectorAll('script')) {
            const src = script.getAttribute('src') || '';
            if (!this.url(src) || script.textContent.trim()) throw new Error('Only external HTTPS scripts are accepted.');
            if (!scripts.includes(src)) scripts.push(src);
        }
        return {
            src: frame.getAttribute('src'), title: frame.getAttribute('title') || 'Embedded media',
            width: this.dimension(frame.getAttribute('width'), 640),
            height: this.dimension(frame.getAttribute('height'), 360),
            scripts, sandbox: true, proportional: false
        };
    },
    info(frame) {
        let metadata = {};
        try { metadata = JSON.parse(frame.getAttribute('data-nb-embed') || '{}'); } catch { /* legacy frame */ }
        return {
            src: frame.getAttribute('src'), title: frame.getAttribute('title') || 'Embedded media',
            width: this.dimension(frame.getAttribute('width'), 640), height: this.dimension(frame.getAttribute('height'), 360),
            scripts: Array.isArray(metadata.scripts) ? metadata.scripts.filter(url => this.url(url)) : [],
            sandbox: typeof metadata.sandbox === 'boolean' ? metadata.sandbox : frame.hasAttribute('sandbox'),
            proportional: metadata.proportional === true
        };
    },
    frame(info, preview = false) {
        if (!this.url(info.src)) throw new Error('Use an absolute HTTPS iframe URL.');
        const same_host = new URL(info.src).hostname === location.hostname;
        if (info.sandbox && same_host) throw new Error('Sandboxed embeds must use an external source.');
        const frame = document.createElement('iframe');
        frame.setAttribute('data-nb-embed', JSON.stringify(info));
        frame.src = info.src;
        frame.title = info.title || 'Embedded media';
        frame.width = this.dimension(info.width, 640);
        frame.height = this.dimension(info.height, 360);
        frame.style.cssText = `display:block;border:0;max-width:100%;width:100%;height:${frame.height}px;`;
        if (info.proportional) {
            frame.style.height = 'auto';
            frame.style.aspectRatio = `${frame.width}/${frame.height}`;
        }
        frame.allow = 'autoplay; fullscreen; picture-in-picture';
        if (preview || info.sandbox) frame.setAttribute('sandbox', 'allow-scripts allow-presentation' + (same_host ? '' : ' allow-same-origin'));
        return frame;
    },
    open(code) {
        const modal = nb.media_alpine;
        if (!modal) return;
        modal.generic_editor = nb.edit.active_editor;
        const selection = window.getSelection();
        if (selection.rangeCount && modal.generic_editor?.contains(selection.getRangeAt(0).commonAncestorContainer)) nb.edit.store_caret_pos();
        modal.generic_range = selection.rangeCount ? selection.getRangeAt(0).cloneRange() : null;
        modal.mode = 'embed';
        modal.embed_info.active = 'generic';
        modal.generic_code = code;
        modal.parse_generic();
        nb.modal.open('nb-modal-insert-media');
    },
    paste(event, editor) {
        if (editor._nb_editor_options?.media !== true) return false;
        const code = event.clipboardData.getData('text/html') || event.clipboardData.getData('text/plain');
        if (!/<iframe\b/i.test(code)) return false;
        event.preventDefault();
        try {
            const info = this.parse(code);
            if (info.scripts.length) this.open(code);
            else nb.edit.insert_html('<figure>' + this.frame(info, true).outerHTML + '</figure>', editor);
        } catch { this.open(code); }
        return true;
    },
    serialize(editor) {
        const clone = editor.cloneNode(true);
        clone.querySelectorAll('[data-nb-upload-placeholder]').forEach(node => node.remove());
        clone.querySelectorAll('iframe[data-nb-embed]').forEach(frame => {
            frame.replaceWith(this.frame(this.info(frame)));
        });
        return clone.innerHTML.trim();
    }
};

export default nb_embed;
