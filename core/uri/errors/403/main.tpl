<section class="container max-w-5xl mx-auto pb-8">
    <h1 class="text-xl text-neutral-700 font-medium mb-4" data-nb-edit="[#cfield title#]"
            data-nb-edit-options='{"buttons":""}'>
            [#get-html title default=[#text Type here#]#]
        </h1>
    <div class="prose" data-nb-edit="[#cfield main_text#]" data-nb-edit-options='{
                "buttons":"h3,bold,italic,orderedlist,unorderedlist,quote,anchor", 
                "media": true, 
                "media_sizes":"md-90,lg-80,xl-70"}'>
        [#get-html main_text default=[#text Type here#]#]
    </div>
</section>
