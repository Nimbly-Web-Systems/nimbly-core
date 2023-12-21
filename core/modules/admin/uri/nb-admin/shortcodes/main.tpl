<section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-primary">
  <div>
    <h1 class="text-2xl md:text-3xl font-semibold text-neutral-800 ">[#text Shortcodes#]</h1>
    <h3 class="text-sm md:text-base pt-1 pb-2 text-neutral-700 font-medium">
      [#text Available Nimbly template shortcodes.#]
    </h3>
  </div>
</section>

[#get-libraries#]
<section class="bg-neutral-100 px-2 sm:px-4 md:px-6 lg:px-8 pb-10">
  <div data-te-datatable-init class="rounded-2xl shadow-md bg-neutral-50 p-4" data-te-class-color="bg-neutral-50">
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Layer</th>
          <th>Module</th>
          <th>Example(s)</th>
        </tr>
      </thead>
      <tbody>
        [#repeat data.libraries#]
      </tbody>
    </table>
  </div>
</section>