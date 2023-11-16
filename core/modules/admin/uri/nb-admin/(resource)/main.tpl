<section class="bg-neutral-50 p-4 sm:p-6 md:p-8 lg:p-10 font-lato">
  <h1 class="text-2xl md:text-3xl font-semibold text-neutral-800 ">[field-name [data.resource]]</h1>
  <h3 class="text-sm md:text-base py-2 text-neutral-700">[text List, edit, add and delete [data.resource]]</h3>
</section>
<section class="bg-neutral-100 px-4 sm:px-6 md:px-8 lg:px-10 py-4">

[feature-cond features="manage-[data.resource],add_[data.resource],(any)_[data.resource]" tpl=add_button]
[feature-cond features="manage-[data.resource],delete_[data.resource],(any)_[data.resource]" tpl=delete_button]
<table class="nb-table" data-resource="[data.resource]">
      <thead>
        <tr>
          [repeat data.fields var=field]
          [if data.fields=(empty) echo="<th>Name</th>"]
          <th>&nbsp;</th>
        </tr>
      </thead>
      <tbody>
      [repeat data.records var=record limit=250]
      </tbody>
</table>
[if repeat.limit=yes echo="<p>etc.</p>"]
[if data.records=(empty) echo="<p>No [data.resource] items yet.</p>"]

[if _order=(not-empty) tpl=order]
</section>
