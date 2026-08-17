<section class="bg-neutral-100 p-3 sm:p-4 md:p-6 lg:p-8 font-primary">

    <nav class="mb-2 flex items-center gap-1.5 text-xs font-medium text-neutral-500" aria-label="Breadcrumb">
        [#breadcrumb-home#]
        <span aria-hidden="true">/</span>
        <a class="hover:text-cnormal hover:underline" href="[#base-url#]/nb-admin/[#resource-id#]">[#resource-name [#resource-id#] plural#]</a>
        <span aria-hidden="true">/</span>
        <span class="text-neutral-700">[#text Add#]</span>
    </nav>
    <h1 class="mb-6 text-2xl font-semibold text-neutral-800 md:mb-8 md:text-3xl">
        [#text Add [#resource-name [#resource-id#]#]#]
    </h1>

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        <div class="order-2 min-w-0 flex-1 lg:order-1">
            [#add-resource-form#]
        </div>
        <div class="order-1 empty:hidden lg:order-2 lg:sticky lg:top-4 lg:w-80 lg:shrink-0">
            [#resource-record-actions#]
        </div>
    </div>
</section>
