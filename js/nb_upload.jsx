var nb_upload = {
   api_url: nb.base_url + '/api/v1/.files'
};

nb_upload.init = function () {
    const uploaders = document.querySelectorAll('input[type=file][data-nb-upload]');
    uploaders.forEach(el => {
        nb_upload.init_uploader(el);
    })
};

nb_upload.init_uploader = function(el) {
    el.addEventListener('change', nb_upload.handle_change);
}


nb_upload.handle_change = async function (e) {
    const input = e.currentTarget;
    const files = Array.from(input.files || []);
    if (!files.length || (files.length > 1 && !input.multiple)) {
        return;
    }
    const scope = input.dataset.nbUpload;
    const quiet = files.length > 1;
    const progress = files.map(() => 0);
    let done = 0;
    let next = 0;
    const report = (total = files.length) => document.dispatchEvent(new CustomEvent('nb_upload_progress', {
        detail: { scope, total, done, progress: progress.reduce((sum, p) => sum + p, 0) / files.length }
    }));
    // a few at a time; each finished file is announced through nb_upload_ready
    const worker = async () => {
        while (next < files.length) {
            const i = next++;
            const res = await nb_upload.upload(files[i], input, {
                quiet,
                on_progress: fraction => { progress[i] = fraction; report(); }
            }).catch(() => ({ success: false }));
            if (quiet && !res.success) {
                nb.notify(files[i].name + ': ' + (res.message || nb.text.image_upload_failed || 'Upload failed'));
            }
            progress[i] = 1;
            done++;
            report();
        }
    };
    report();
    await Promise.all(files.slice(0, 3).map(worker));
    input.value = ''; // allow choosing the same files again
    report(0);
};

// Resolves with the API result. opts: on_progress(fraction) while sending,
// quiet to skip notices (the caller shows its own state), signal to abort.
nb_upload.upload = function (file, elem, opts = {}) {
    return new Promise((resolve, reject) => {
        const data = new FormData();
        data.append('file', file);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', nb_upload.api_url);
        if (opts.on_progress) {
            xhr.upload.addEventListener('progress', e => {
                if (e.lengthComputable) opts.on_progress(e.loaded / e.total);
            });
        }
        xhr.addEventListener('load', () => {
            let res;
            try {
                res = JSON.parse(xhr.responseText);
            } catch (error) {
                // proxies may answer with an HTML error page
                res = { success: false, message: xhr.status === 413 ? 'UPLOAD_TOO_LARGE' : 'UPLOAD_FAILED' };
            }
            if (!opts.quiet) nb.notify(res.success ? nb.text.file_added : res.message);
            if (elem?.dataset.nbUpload) {
                const event = new CustomEvent('nb_upload_ready', { scope: elem.dataset.nbUpload, detail: res});
                document.dispatchEvent(event);
            }
            resolve(res);
        });
        xhr.addEventListener('error', () => {
            if (!opts.quiet) nb.notify(nb.text.image_upload_failed || 'Image upload failed. Please try again.');
            reject(new Error('Network error'));
        });
        xhr.addEventListener('abort', () => reject(new DOMException('Upload cancelled', 'AbortError')));
        if (opts.signal?.aborted) return reject(new DOMException('Upload cancelled', 'AbortError'));
        opts.signal?.addEventListener('abort', () => xhr.abort());
        xhr.send(data);
    });
};

// Shared by the media picker and direct editor uploads. Use captured field
// options, since the focused editor can change while an upload is in flight.
nb_upload.image_html = function (file, options = {}, alt = '') {
    const img = document.createElement('img');
    img.className = 'w-full';
    img.loading = 'lazy';
    img.alt = alt;
    const base = nb.base_url + '/img/' + file.uuid;
    img.src = base + (file.width && file.height ? '/480w' : '');
    if (file.width && file.height) {
        img.width = file.width;
        img.height = file.height;
        img.style.maxWidth = file.width + 'px';
        img.style.maxHeight = file.height + 'px';
        const sources = [];
        for (const width of [120, 180, 240, 320, 480, 640, 800, 960, 1120, 1280, 1440, 1600, 1760, 1920]) {
            sources.push(base + '/' + width + 'w ' + width + 'w');
            if (file.width < width) break;
        }
        img.srcset = sources.join(', ');
        const sizes = ['100vw'];
        for (const rule of (options.media_sizes || '').split(',')) {
            const [breakpoint, width] = rule.trim().split('-');
            if (nb.tw_breakpoints?.[breakpoint] && Number(width) > 0) {
                sizes.unshift('(min-width: ' + nb.tw_breakpoints[breakpoint] + 'px) ' + Number(width) + 'vw');
            }
        }
        img.sizes = sizes.join(', ');
    }
    const template_id = (file.aspect ?? file.aspect_ratio ?? (file.width / file.height)) >= 1
        ? 'nb_media_insert_img_landscape_tpl' : 'nb_media_insert_img_portrait_tpl';
    if (document.getElementById(template_id) && nb.populate_template) {
        const escaped = document.createElement('div');
        escaped.textContent = alt;
        const template = document.createElement('template');
        template.innerHTML = nb.populate_template(template_id, {
            uuid: file.uuid, width: file.width || '', height: file.height || '',
            src: img.getAttribute('src'), srcset: img.srcset, sizes: img.sizes,
            alt: escaped.innerHTML.replace(/"/g, '&quot;'),
        }).trim();
        if (!file.width || !file.height) {
            template.content.querySelectorAll('img').forEach(image => {
                ['width', 'height', 'style'].forEach(attr => image.removeAttribute(attr));
            });
        }
        return template.innerHTML;
    }
    return img.outerHTML;
};

export default nb_upload;
