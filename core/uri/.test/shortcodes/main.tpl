<!-- [#set#] + [#get#] scalar -->
[#set tsc-greet="hello"#]
<span id="get-scalar">[#get tsc-greet#]</span>

<!-- [#data#] resource + [#get#] from loaded record -->
[#data test-records test-001#]
<span id="get-title">[#get data.test-records.test-001.title#]</span>
<span id="get-score">[#get data.test-records.test-001.score#]</span>

<!-- [#get#] with default fallback -->
<span id="get-default">[#get tsc-missing default="fallback"#]</span>

<!-- Named date formats use the requested locale; exact formats stay deterministic -->
<span id="date-long-en">[#date 2026-06-10 fmt=long lang=en#]</span>
<span id="date-long-nl">[#date 2026-06-10 fmt=long lang=nl#]</span>
<span id="date-exact">[#date 2026-06-10 fmt=Y/m/d#]</span>
<span id="fmt-date-long-nl">[#fmt val=2026-06-10 type=date fmt=long lang=nl#]</span>
<span id="date-invalid">[#date invalid-date fmt=long lang=en#]</span>
