[#get-modules#]
<section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-primary flex justify-between">
    <div>
      <h1 class="text-2xl md:text-3xl font-semibold text-neutral-800 ">[#text Modules#]</h1>
      <h3 class="text-sm md:text-base pt-1 pb-2 text-neutral-700 font-medium">[#count data_modules#] [#text modules#]</h3>
    </div>
  </section>
  <section class="bg-neutral-100 px-2 sm:px-4 md:px-6 lg:px-8 pb-10" x-data="nb_table">

    <div class="rounded-2xl shadow-md bg-neutral-50 p-4">
      <div class="form-control mb-4 max-w-xs">
        <label for="modules_search" class="label">
          <span class="label-text">[#text Search#]</span>
        </label>
        <input id="modules_search" type="search" class="input input-bordered input-sm bg-neutral-50"
          x-model="search" @input="filter_rows" placeholder="[#text Search#]">
      </div>
      <div class="overflow-x-auto">
      <table class="table table-zebra">
        <thead>
          <tr>
            <th><button type="button" class="font-semibold" @click="sort_by(0)">[#text Module name#]</button></th>
            <th><button type="button" class="font-semibold" @click="sort_by(1)">[#text Layer#]</button></th>
            <th>[#text Actions#]</th>
          </tr>
        </thead>
        <tbody x-ref="body">
          [#repeat data_modules var=record#]
        </tbody>
      </table>
      </div>
    </div>
  </section>
