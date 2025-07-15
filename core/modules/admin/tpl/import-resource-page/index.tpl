<script>
    _resource_url="[#get _resource_url default=[#base-url#]/nb-admin/[#resource-name#]#]";
    [#include file=[#base-path#]core/modules/admin/tpl/import-resource-page/form_import.js#]
</script>
<section class="container max-w-6xl mx-auto py-8 px-[40px] bg-neutral-100">

    <h1 class="text-2xl md:text-3xl font-semibold text-neutral-800 mb-8" data-nb-edit="[#cfield title#]"
        data-nb-edit-options='{"buttons":""}'>
        [#text btn_txt_import_[#resource-name#]#]
    </h1>

    [#import-resource-form#]

</section>