<section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-primary">
    <h1 class="text-2xl md:text-3xl font-semibold text-neutral-800">[#text Dashboard#]</h1>
    <h3 class="text-sm md:text-base pt-1 pb-2 text-neutral-700">[#text dashboard-subtitle#]</h3>
</section>
<section class="bg-neutral-100 px-2 sm:px-4 md:px-6 lg:px-8 pb-10">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 w-full">
        [#panel-users#]
        [#get-user-resources#]
        [#if data.user-resources=(not-empty) tpl=panel-data#]
        [#panel-updates#]
        [#panel-media#]    
        [#panel-status#]     
    </div>
</section>
