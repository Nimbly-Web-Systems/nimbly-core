<h1 class=" text-neutral-700 font-bold text-2xl" data-nb-edit="articles.[get record.uuid].title">
    [render articles.[get record.uuid].title]
</h1>

<p class="uppercase text-neutral-500 text-sm">[date [get record.date]]</p>

<div class="my-6 relative w-full bg-gray-100" data-nb-edit-img="articles.[get record.uuid].main_img">
    image goes here
</div>

<div class="prose prose-neutral my-4">
    [render articles.[get record.uuid].intro]
    [render articles.[get record.uuid].main_text]
</div>