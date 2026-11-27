// Contact page: show the friendly error when send.php redirects back with ?e=1.
(function(){var p=document.getElementById('cerr');if(p&&/[?&]e=1(&|$)/.test(location.search))p.hidden=false;})();
