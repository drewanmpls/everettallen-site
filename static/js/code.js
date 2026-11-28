// Free-code links (/events/?code=...): remember the code for the Buy flow and tidy the address. Loaded in the head, not deferred, so it runs first.
try{var u=new URL(location.href),c=u.searchParams.get("code");if(c){localStorage.setItem("eaCode",c.trim());u.searchParams.delete("code");history.replaceState(null,"",u.pathname+u.search+u.hash)}}catch(e){}
