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
    nb_upload.upload(file, e.currentTarget);
};

nb_upload.upload = function (file, elem) {
    var data = new FormData();
    data.append('file', file);
    fetch(nb_upload.api_url, {
        method: "POST",
        body: data
    }).then(res => res.json()).then(res => {
        if (res.success) {
            nb.notify(nb.text.file_added);
        } else {
            nb.notify(res.message);
        }
        if (elem.dataset.nbUpload) {
            const event = new CustomEvent('nb_upload_ready', { scope: elem.dataset.nbUpload, detail: res});
            document.dispatchEvent(event);
        }
    });
};

export default nb_upload;
