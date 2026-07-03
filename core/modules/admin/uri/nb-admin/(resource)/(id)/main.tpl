<section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-primary">

    <nav class="mb-2 flex items-center gap-1.5 text-xs font-medium text-neutral-500" aria-label="Breadcrumb">
        [#breadcrumb-home#]
        <span aria-hidden="true">/</span>
        <a class="hover:text-cnormal hover:underline" href="[#base-url#]/nb-admin/[#resource-id#]">[#resource-name [#resource-id#] plural#]</a>
        <span aria-hidden="true">/</span>
        <span class="text-neutral-700">[#resource-title [#resource-id#] [#get uuid#]#]</span>
    </nav>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl md:text-3xl font-semibold text-neutral-800" data-nb-edit="[#cfield title#]"
            data-nb-edit-options='{"buttons":""}'>
            [#text Edit [#resource-name [#resource-id#]#]#]
        </h1>
        <a class="[#btn-class-secondary#]" href="[#base-url#]/nb-admin/[#resource-id#]">[#text Back to overview#]</a>
    </div>

    [#resource-switcher [#resource-id#] [#get uuid#]#]

    [#edit-resource-form#]
</section>
