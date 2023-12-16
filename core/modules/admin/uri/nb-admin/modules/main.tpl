[#get-modules#]
<section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-primary flex justify-between">
    <div>
      <h1 class="text-2xl md:text-3xl font-semibold text-neutral-800 ">[#text Modules#]</h1>
      <h3 class="text-sm md:text-base pt-1 pb-2 text-neutral-700 font-medium">[#count data_modules#] [#text modules#]</h3>
    </div>
  </section>
  <section class="bg-neutral-100 px-2 sm:px-4 md:px-6 lg:px-8 pb-10" x-data="modules_table">
  
    <div data-te-datatable-init class="rounded-2xl shadow-md bg-neutral-50 p-4"
      data-te-no-found-message="[#text No [#field-name [#data.resource#]#]#]"
      data-te-class-color="bg-neutral-50"
       >
      <table >
        <thead>
          <tr>
            <th>[#text Module name#]</th>
            <th>[#text Layer#]</th>
            <th data-te-sort="false">[#text Actions#]</th>
          </tr>
        </thead>
        <tbody>
          [#repeat data_modules var=record#]
        </tbody>
      </table>
    </div>
  </section>
  