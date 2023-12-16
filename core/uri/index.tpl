[#set x=[#logged-in#]#]
[#if x=logged-in redirect=nb-admin#]
[#if x=(empty) redirect=login#]