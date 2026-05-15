<section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-primary">
  <div>
    <h1 class="text-2xl md:text-3xl font-semibold text-neutral-800 ">[#text Shortcodes#]</h1>
    <h3 class="text-sm md:text-base pt-1 pb-2 text-neutral-700 font-medium">
      [#text Available Nimbly template shortcodes.#]
    </h3>
  </div>
</section>

[#get-libraries#]
<section class="bg-neutral-100 px-2 sm:px-4 md:px-6 lg:px-8 pb-10" x-data="nb_table">
  <div class="rounded-2xl shadow-md bg-neutral-50 p-4">
    <div class="form-control mb-4 max-w-xs">
      <label for="shortcodes_search" class="label">
        <span class="label-text">[#text Search#]</span>
      </label>
      <input id="shortcodes_search" type="search" class="input input-bordered input-sm bg-neutral-50"
        x-model="search" @input="filter_rows" placeholder="[#text Search#]">
    </div>
    <div class="overflow-x-auto">
    <table class="table table-zebra">
      <thead>
        <tr>
          <th><button type="button" class="font-semibold" @click="sort_by(0)">Name</button></th>
          <th><button type="button" class="font-semibold" @click="sort_by(1)">Layer</button></th>
          <th><button type="button" class="font-semibold" @click="sort_by(2)">Module</button></th>
          <th>Example(s)</th>
        </tr>
      </thead>
      <tbody x-ref="body">
        [#repeat data.libraries#]
      </tbody>
    </table>
    </div>
  </div>
</section>
