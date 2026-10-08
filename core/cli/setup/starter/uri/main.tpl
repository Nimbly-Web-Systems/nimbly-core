<section class="mx-auto max-w-5xl px-6 pb-16 pt-16 sm:pt-24">
    <p class="mb-8 inline-flex items-center gap-2 rounded-full border border-base-300 bg-base-200 px-3 py-1 text-xs font-medium text-neutral-600">
        <span class="h-2 w-2 rounded-full bg-success"></span>
        Your site is running
    </p>
    <h1 class="max-w-3xl text-4xl font-semibold tracking-tight text-neutral-900 sm:text-6xl"
        data-nb-edit="[#cfield title#]" data-nb-edit-options='{"plain":true}'>
        [#get-html [#cfield title#] plain default="A new site, ready to become yours"#]
    </h1>
    <div class="mt-6 max-w-2xl text-lg leading-8 text-neutral-600" data-nb-edit="[#cfield intro#]">
        [#get-html [#cfield intro#] default="<p>This is your home page. It is a small file in your project, and the title and this text are already editable: log in and click them.</p>"#]
    </div>
    <div class="mt-10 flex flex-wrap items-center gap-3">
        <a href="[#base-url#]/login" class="btn btn-primary">Log in</a>
        <a href="[#base-url#]/nb-admin/" class="btn btn-ghost">Open the admin</a>
    </div>
</section>

<section class="mx-auto max-w-5xl px-6 pb-16">
    <div class="grid gap-6 md:grid-cols-3">
        <div class="rounded-box border border-base-300 bg-base-100 p-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-primary">1. Pages</p>
            <h2 class="mt-2 text-lg font-semibold text-neutral-900">A page is a folder</h2>
            <p class="mt-2 text-sm leading-6 text-neutral-600">
                This page is <code class="rounded bg-base-200 px-1 py-0.5 text-xs">ext/uri/main.tpl</code>.
                Add a folder <code class="rounded bg-base-200 px-1 py-0.5 text-xs">ext/uri/about</code> with the same two files
                and you have <code class="rounded bg-base-200 px-1 py-0.5 text-xs">/about</code>.
            </p>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-primary">2. Content</p>
            <h2 class="mt-2 text-lg font-semibold text-neutral-900">Text you can click</h2>
            <p class="mt-2 text-sm leading-6 text-neutral-600">
                One attribute, <code class="rounded bg-base-200 px-1 py-0.5 text-xs">data-nb-edit</code>, makes a text editable
                on the page itself. The title and the intro above use it; look at how in this file.
            </p>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-primary">3. Data</p>
            <h2 class="mt-2 text-lg font-semibold text-neutral-900">Records without a database</h2>
            <p class="mt-2 text-sm leading-6 text-neutral-600">
                A resource is a folder under <code class="rounded bg-base-200 px-1 py-0.5 text-xs">ext/data</code>
                with one <code class="rounded bg-base-200 px-1 py-0.5 text-xs">.meta</code> file, in JSON, that lists its fields.
                That file is all it takes: the admin screens and the API follow.
            </p>
        </div>
    </div>
</section>

<section class="mx-auto max-w-5xl px-6 pb-16">
    <div class="grid gap-10 rounded-box border border-base-300 bg-base-100 p-6 sm:p-8 md:grid-cols-2">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-primary">Try it</p>
            <h2 class="mt-2 text-lg font-semibold text-neutral-900">A resource and a form</h2>
            <p class="mt-2 text-sm leading-6 text-neutral-600">
                Your project has one example resource,
                <code class="rounded bg-base-200 px-1 py-0.5 text-xs">ext/data/messages/.meta</code>, and this form,
                <code class="rounded bg-base-200 px-1 py-0.5 text-xs">ext/uri/message.json</code>. Each is a few lines of JSON.
                What you save here appears in the admin under Messages.
            </p>
            <p class="mt-3 text-sm leading-6 text-neutral-600">
                A form only takes what the person may write, so this one is closed to visitors. To open it, make a route
                <code class="rounded bg-base-200 px-1 py-0.5 text-xs">ext/uri/api/v1/messages/index.tpl</code> that holds
                <code class="rounded bg-base-200 px-1 py-0.5 text-xs">&#91;#api-allow post messages#&#93;</code>,
                and show the form to everyone in
                <code class="rounded bg-base-200 px-1 py-0.5 text-xs">ext/uri/main.tpl</code>.
            </p>
        </div>
        <div>
            [#set starter-visitor=[#logged-in#]#]
            [#if starter-visitor=logged-in tpl=message-form#]
            [#if starter-visitor=(empty) tpl=message-login#]
        </div>
    </div>
</section>

<section class="mx-auto max-w-5xl px-6 pb-24">
    <div class="rounded-box bg-neutral p-6 text-neutral-content sm:p-8">
        <h2 class="text-lg font-semibold">Where to go next</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 opacity-80">
            The reference is in your project and searchable from the command line. Rebuild the stylesheet after you change a template.
        </p>
        <pre class="mt-5 overflow-x-auto rounded-field bg-black/30 p-4 text-sm leading-7"><code>./nimbly docs:list
./nimbly docs:search "resource"
./nimbly build</code></pre>
        <p class="mt-5 text-sm opacity-80">
            Everything on this page is yours to change or remove. Start with
            <code class="rounded bg-white/10 px-1 py-0.5 text-xs">ext/uri/main.tpl</code>.
        </p>
    </div>
</section>
