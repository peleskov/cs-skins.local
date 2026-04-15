(function() {
    var params = new URLSearchParams(window.location.search);
    var utmSource = params.get('utm_source');
    if (params.get('utm_medium') === 'cpa' && utmSource === 'partners') {
        var pid = params.get('utm_content');
        if (pid) {
            var days = 30;
            var expires = new Date(Date.now() + days * 864e5).toUTCString();
            document.cookie = 'lr_partner_id=' + pid + ';expires=' + expires + ';path=/';
            var lid = params.get('utm_campaign') || '';
            document.cookie = 'lr_link_id=' + lid + ';expires=' + expires + ';path=/';
        }
    } else if (utmSource) {
        document.cookie = 'lr_partner_id=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
        document.cookie = 'lr_link_id=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
    }
})();
