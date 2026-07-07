<section class="bg-neutral-100 p-3 sm:p-4 md:p-6 lg:p-8 font-primary">

    <nav class="mb-2 flex items-center gap-1.5 text-xs font-medium text-neutral-500" aria-label="Breadcrumb">
        [#breadcrumb-home#]
        <span aria-hidden="true">/</span>
        <a class="hover:text-cnormal hover:underline" href="[#base-url#]/nb-admin/[#resource-id#]">[#resource-name [#resource-id#] plural#]</a>
        <span aria-hidden="true">/</span>
        <span class="text-neutral-700">[#text Add#]</span>
    </nav>
    <h1 class="mb-6 text-2xl font-semibold text-neutral-800 md:mb-8 md:text-3xl" data-nb-edit="[#cfield title#]"
        data-nb-edit-options='{"buttons":""}'>
        [#text Add [#resource-name [#resource-id#]#]#]
    </h1>

    [#add-resource-form#]
</section>
