(function (global) {
    'use strict';

    function escHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fmtCurrency(value, currency, locale) {
        var amount = Number.isFinite(Number(value)) ? Number(value) : 0;
        return new Intl.NumberFormat(locale || 'en-US', {
            style: 'currency',
            currency: currency || 'USD',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(amount);
    }

    function timeAgo(dateValue) {
        var ts = new Date(dateValue).getTime();
        if (!Number.isFinite(ts)) return '';

        var diff = Math.max(0, Math.floor((Date.now() - ts) / 1000));
        if (diff < 60) return diff + 's ago';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    function debounce(fn, wait) {
        var timeout;
        var delay = Number(wait) || 0;
        return function () {
            var ctx = this;
            var args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function () {
                fn.apply(ctx, args);
            }, delay);
        };
    }

    global.DCHelpers = Object.assign({}, global.DCHelpers || {}, {
        escHtml: escHtml,
        fmtCurrency: fmtCurrency,
        timeAgo: timeAgo,
        debounce: debounce,
    });
})(window);
