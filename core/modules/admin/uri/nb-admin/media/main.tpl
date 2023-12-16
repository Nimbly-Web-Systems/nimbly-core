<div x-data="media_library">
  <section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-primary flex justify-between flex-wrap md:flex-nowrap">
    <div>
      <h1 class="text-2xl md:text-3xl font-semibold text-neutral-800 ">[#text Media Library#]</h1>
      <h3 class="text-sm md:text-base pt-1 pb-2 text-neutral-700 font-medium">
        <span x-text="`${first} - ${last} of ${files.length}`"></span>
        [#text files#]
      </h3>
    </div>
    [#media-pagination#]
    <div>
      [#feature-cond features="manage-content,delete_.files,(any)_.files" tpl=btn_delete_all#]
    </div>
  </section>

  <section class="bg-neutral-100 px-2 sm:px-4 md:px-6 lg:px-8 pb-10">

    <div class="flex flex-wrap flex-col-reverse sm:flex-row sm:flex-nowrap  gap-4 md:gap-6 lg:gap-8 ">
      <div class="grow bg-neutral-100">
        [#media-grid#]
      </div>
      <div class="flex-none w-[300px] p-2 mx-auto sm:p-4 bg-neutral-200 shadow">
        [#media-side-panel#]
      </div>
    </div>

  </section>
  <section class="flex items-center justify-center bg-neutral-100 pb-4">
    <template x-if="last-first > 10">
      [#media-pagination#]
    </template>
  </section>
</div>