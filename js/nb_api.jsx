var nb_api = {};

// A save names the jobs it queued: they run at once, the scheduler runs any
// the page could not. Pages show them by listening for nb:job-start and nb:job-done.
nb_api.run_jobs = function nb_api_run_jobs(data) {
    if (!Array.isArray(data?.jobs)) {
        return data;
    }
    data.jobs.forEach((job) => {
        window.dispatchEvent(new CustomEvent("nb:job-start", { detail: job }));
        nb_api.post(`${nb.base_url}/api/v1/.jobs/${job.id}/run`)
            .catch(() => ({}))
            .then((result) => {
                const status = result?.job?.status ?? "queued";
                window.dispatchEvent(new CustomEvent("nb:job-done", { detail: { ...job, status } }));
            });
    });
    return data;
}

nb_api.post = async function nb_api_post(url = "", data = {}) {
    const response = await fetch(url, {
        method: "POST",
        mode: "same-origin",
        cache: "no-cache",
        credentials: "same-origin",
        headers: {
            "Content-Type": "application/json",
        },
        redirect: "follow",
        referrerPolicy: "no-referrer",
        body: JSON.stringify(data),
    });
    return response.json().then(nb_api.run_jobs);
}

nb_api.put = async function nb_api_put(url = "", data = {}) {
    const response = await fetch(url, {
        method: "PUT",
        mode: "same-origin",
        cache: "no-cache",
        credentials: "same-origin",
        headers: {
            "Content-Type": "application/json",
        },
        redirect: "follow",
        referrerPolicy: "no-referrer",
        body: JSON.stringify(data),
    });
    return response.json().then(nb_api.run_jobs);
}

nb_api.get = async function nb_api_get(url = "") {
    const response = await fetch(url, {
        method: "GET",
        mode: "same-origin",
        cache: "no-cache",
        credentials: "same-origin",
        headers: {
            "Content-Type": "application/json",
        },
        redirect: "follow",
        referrerPolicy: "no-referrer"
    });
    return response.json();
}

nb_api.delete = async function nb_api_delete(url = "") {
    const response = await fetch(url, {
        method: "DELETE",
        mode: "same-origin",
        cache: "no-cache",
        credentials: "same-origin",
        headers: {
            "Content-Type": "application/json",
        },
        redirect: "follow",
        referrerPolicy: "no-referrer"
    });
    return response.json();
}


export default nb_api;