<section class="px-6 py-16 sm:py-24">
    <article class="prose prose-lg mx-auto max-w-3xl">
        <h1 data-nb-edit="pages.[#get page.uuid#].title.[#get language#]"
            data-nb-edit-options='{"plain":true}'>[#get-html pages.[#get page.uuid#].title plain#]</h1>
        <div data-nb-edit="pages.[#get page.uuid#].body.[#get language#]"
            data-nb-edit-options='{"buttons":"format,bold,italic,link,ul,ol","media":true}'>
            [#get-html pages.[#get page.uuid#].body#]
        </div>
    </article>
</section>
