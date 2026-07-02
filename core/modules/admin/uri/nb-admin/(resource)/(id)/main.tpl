<section class="container max-w-6xl mx-auto py-8 px-[40px] bg-neutral-100">

    <div class="mb-8 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl md:text-3xl font-semibold text-neutral-800" data-nb-edit="[#cfield title#]"
            data-nb-edit-options='{"buttons":""}'>
            [#text Edit [#resource-name [#resource-id#]#]#]
        </h1>
        [#if resource-id=roles tpl=btn-role-permissions#]
    </div>

    [#edit-resource-form#]
</section>
