<!-- [#set#] + [#get#] scalar -->
[#set tsc-greet="hello"#]
<span id="get-scalar">[#get tsc-greet#]</span>

<!-- [#data#] resource + [#get#] from loaded record -->
[#data test-records test-001#]
<span id="get-title">[#get data.test-records.test-001.title#]</span>
<span id="get-score">[#get data.test-records.test-001.score#]</span>

<!-- [#get#] with default fallback -->
<span id="get-default">[#get tsc-missing default="fallback"#]</span>
