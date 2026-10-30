/*
 * The chosen file's preview, its pixel size, and a warning when it is
 * smaller than the slot chosen under "Used for" (item 1i). The warning
 * never stops the upload; the server checks the stored file the same way
 * and warns again after it.
 */
(function () {
    'use strict';

    document.querySelectorAll('[data-media-slots]').forEach(function (root) {
        var input = document.getElementById(root.getAttribute('data-file-input'));
        var preview = root.querySelector('[data-slot-preview]');
        var img = root.querySelector('[data-slot-preview-img]');
        var size = root.querySelector('[data-slot-preview-size]');
        var warning = root.querySelector('[data-slot-warning]');
        var url = null;
        var dims = null;

        if (!input || !preview || !img || !size || !warning || !window.URL || !URL.createObjectURL) {
            return;
        }

        function fill(template, values) {
            return template.replace(/\{(\w+)\}/g, function (m, key) {
                return values[key] !== undefined ? values[key] : m;
            });
        }

        function check() {
            var chosen = root.querySelector('input[type="radio"]:checked');
            var mw = chosen ? Number(chosen.getAttribute('data-min-w')) : 0;
            var mh = chosen ? Number(chosen.getAttribute('data-min-h')) : 0;

            if (!dims || !mw || !mh || (dims.w >= mw && dims.h >= mh)) {
                warning.hidden = true;
                warning.textContent = '';
                return;
            }

            warning.textContent = fill(root.getAttribute('data-warning-template'), {
                w: dims.w, h: dims.h, mw: mw, mh: mh, slot: chosen.getAttribute('data-slot-label')
            });
            warning.hidden = false;
        }

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];

            if (url) {
                URL.revokeObjectURL(url);
                url = null;
            }
            dims = null;
            preview.hidden = true;
            check();

            if (!file || !/^image\//.test(file.type)) {
                return;
            }

            url = URL.createObjectURL(file);
            img.onload = function () {
                if (img.naturalWidth && img.naturalHeight && file.type !== 'image/svg+xml') {
                    dims = { w: img.naturalWidth, h: img.naturalHeight };
                    size.textContent = fill(root.getAttribute('data-size-template'), dims);
                } else {
                    size.textContent = '';
                }
                preview.hidden = false;
                check();
            };
            img.src = url;
        });

        root.addEventListener('change', function (event) {
            if (event.target && event.target.type === 'radio') {
                check();
            }
        });
    });
})();
