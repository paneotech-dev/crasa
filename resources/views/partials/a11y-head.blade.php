{{--
    The stored display options, applied to <html> before first paint. One
    copy for the public layout and the signed-in shell, so both read the
    same localStorage key ('crasa-a11y') and a choice made on one surface
    holds on the other. public/js/a11y.js writes the key; this only reads
    it, and a refused or missing storage leaves the page at its defaults.
--}}
<script>try{var a=JSON.parse(localStorage.getItem('crasa-a11y')||'{}'),h=document.documentElement;if(['smaller','larger','largest'].indexOf(a.size)>-1)h.classList.add('a11y-text-'+a.size);['contrast','underline','spacing','dyslexia','pause'].forEach(function(f){if(a[f]===true)h.classList.add('a11y-'+f);});if(localStorage.getItem('crasa-translation-notice')==='closed')h.classList.add('translation-notice-closed');}catch(e){}</script>
