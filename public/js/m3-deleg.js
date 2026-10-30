/*
 * Delegate registrations: the "Select all" box ticks or clears every row
 * box on the screen, and shows mixed when only some are ticked.
 */
(function () {
    var all = document.querySelector('[data-reg-select-all]');
    if (!all) {
        return;
    }
    var rows = function () {
        return Array.prototype.slice.call(document.querySelectorAll('[data-reg-select]'));
    };
    all.addEventListener('change', function () {
        rows().forEach(function (box) { box.checked = all.checked; });
    });
    document.addEventListener('change', function (e) {
        if (!e.target.hasAttribute || !e.target.hasAttribute('data-reg-select')) {
            return;
        }
        var boxes = rows();
        var ticked = boxes.filter(function (box) { return box.checked; }).length;
        all.checked = ticked === boxes.length;
        all.indeterminate = ticked > 0 && ticked < boxes.length;
    });
})();
