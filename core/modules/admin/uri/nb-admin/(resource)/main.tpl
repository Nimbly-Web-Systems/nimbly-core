<section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-lato flex justify-between">
  <div>
    <h1 class="text-2xl md:text-3xl font-semibold text-neutral-800 ">[field-name [data.resource]]</h1>
    <h3 class="text-sm md:text-base pt-1 pb-2 text-neutral-700 font-medium">[count data.records] [text records]</h3>
  </div>
  <div>
    [feature-cond features="manage-[data.resource],add_[data.resource],(any)_[data.resource]" tpl=add_button]
    [feature-cond features="manage-[data.resource],delete_[data.resource],(any)_[data.resource]" tpl=delete_button]
  </div>
</section>
<section class="bg-neutral-100 px-2 sm:px-4 md:px-6 lg:px-8 pb-10">

  <div data-te-datatable-init
    data-te-no-found-message="[text No [field-name [data.resource]]]">
    <table>
      <thead>
        <tr>
          [repeat data.fields var=field]
          [if data.fields=(empty) echo="<th>[text Name]</th>"]
          <th data-te-sort="false">&nbsp;</th>
        </tr>
      </thead>
      <tbody>
        [repeat data.records var=record]
      </tbody>
    </table>
  </div>
</section>
