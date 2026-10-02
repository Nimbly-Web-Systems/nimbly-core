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


nb_upload.handle_change = function (e) {
    const files = e.currentTarget.files || e.target.files || e.dataTransfer.files;
    if (!files || files.length !== 1) {
        return;
    }
    var file = files[0];
    nb_upload.upload(file, e.currentTarget).catch(() => {});
};

nb_upload.upload = function (file, elem) {
    var data = new FormData();
    data.append('file', file);
    return fetch(nb_upload.api_url, {
        method: "POST",
        body: data
    }).then(res => res.json()).then(res => {
        if (res.success) {
            nb.notify(nb.text.file_added);
        } else {
            nb.notify(res.message);
        }
        if (elem?.dataset.nbUpload) {
            const event = new CustomEvent('nb_upload_ready', { scope: elem.dataset.nbUpload, detail: res});
            document.dispatchEvent(event);
        }
        return res;
    }).catch(error => {
        nb.notify(nb.text.image_upload_failed || 'Image upload failed. Please try again.');
        throw error;
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
