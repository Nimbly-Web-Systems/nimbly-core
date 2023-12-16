<h1 class=" text-neutral-700 font-bold text-2xl" 
    data-nb-edit="articles.[#get record.uuid#].title" 
    data-nb-edit-options='{"buttons":""}'>
    [#get-html articles.[get record.uuid#].title]
</h1>

<p class="uppercase text-neutral-500 text-sm">[#date [#get record.date#]#]</p>

<div class="my-6 relative w-full bg-gray-100" data-nb-edit-img="articles.[#get record.uuid#].main_img">
    [#module images#]
    [#get-img-html [get record.main_img#] sizes="md-50"]
</div>

<div class="prose prose-neutral my-4" data-nb-edit="articles.[#get record.uuid#].intro">
    [#get-html articles.[get record.uuid#].intro]
</div>

<div class="prose prose-neutral my-4" 
    data-nb-edit="articles.[#get record.uuid#].main_text"
    data-nb-edit-options='{
        "buttons":"h2,h3,bold,italic,anchor,quote,orderedlist,unorderedlist", 
        "media":"true",
        "media_sizes":"md-50"
    }'>
    [#get-html articles.[get record.uuid#].main_text]
</div>