<h1 class="text-lg font-bold text-neutral-900">[#text Installation#]</h1>
<h2 class="text-sm uppercase text-neutral-600">[#text Step#] [#step#]: [#text step[#step#]_long#]</h2>


<div class="w-full bg-neutral-200 dark:bg-neutral-600 mt-4 mb-8">
    <div class="bg-cnormal p-0.5 text-center text-xs leading-none text-neutral-50"
        style="width: [#if step=1 echo=33.33%#][#if step=2 echo=66.66%#][#if step=3 echo=100%#]">
        [#if step=1 echo=33.33%#][#if step=2 echo=66.66%#][#if step=3 echo=100%#]
    </div>
</div>

<form name="step[#step#]" action="[#url#].php" method="post" accept-charset="utf-8">
    [#form-errors#]
    [#form-key step[#step#]#]
    [#step[#step#]#]
</form>