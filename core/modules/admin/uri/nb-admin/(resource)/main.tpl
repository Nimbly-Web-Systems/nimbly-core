<section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-primary flex justify-between">
  <div>
    <h1 class="text-2xl md:text-3xl font-semibold text-neutral-800 ">[#field-name [#data.resource#]#]</h1>
    <h3 class="text-sm md:text-base pt-1 pb-2 text-neutral-700 font-medium">[#count data.records#] [#text records#]</h3>
  </div>
  <div>
    [#feature-cond features="manage-[#data.resource#],add_[#data.resource#],(any)_[#data.resource#]" tpl=btn_add#]
    [#feature-cond features="manage-[#data.resource#],delete_[#data.resource#],(any)_[#data.resource#]" tpl=btn_delete_all#]
  </div>
</section>
<section class="bg-neutral-100 px-2 sm:px-4 md:px-6 lg:px-8 pb-10" x-data="resource_table('[#data.resource#]')">

  <div data-te-datatable-init class="rounded-2xl shadow-md bg-neutral-50 p-4"
    data-te-no-found-message="[#text No [#field-name [#data.resource#]#]#]"
    data-te-class-color="bg-neutral-50"
     >
    <table >
      <thead>
        <tr>
          [#repeat data.fields var=field#]
          [#if data.fields=(empty) echo="<th>[#text Name#]</th>"#]
          <th data-te-sort="false">[#text Actions#]</th>
        </tr>
      </thead>
      <tbody>
        [#repeat data.records var=record#]
      </tbody>
    </table>
  </div>
</section>
