/*
 * The news form's photograph: a thumbnail of the chosen or uploaded
 * photograph and its focal point.
 *
 * The two percentage fields are the record; the marker on the thumbnail
 * is a second way to set them, by clicking the photograph or by moving the
 * focused marker with the arrow keys (Shift for steps of 10). Choosing or
 * uploading a different photograph clears the point, because a point on
 * one photograph says nothing about another.
 */
(function () {
    'use strict';

    document.querySelectorAll('[data-news-photo]').forEach(function (root) {
        var select = root.querySelector('[data-photo-select]');
        var upload = root.querySelector('[data-photo-upload]');
        var frame = root.querySelector('[data-photo-frame]');
        var thumb = root.querySelector('[data-photo-thumb]');
        var marker = root.querySelector('[data-photo-marker]');
        var fx = root.querySelector('[data-focal-x]');
        var fy = root.querySelector('[data-focal-y]');

        if (!frame || !thumb || !marker || !fx || !fy) {
            return;
        }

        function clamp(n) {
            n = Math.round(Number(n));
            return isNaN(n) ? 50 : Math.max(0, Math.min(100, n));
        }

        function place() {
            marker.style.left = clamp(fx.value === '' ? 50 : fx.value) + '%';
            marker.style.top = clamp(fy.value === '' ? 50 : fy.value) + '%';
        }

        function set(x, y) {
            fx.value = clamp(x);
            fy.value = clamp(y);
            place();
        }

        function show(src) {
            thumb.src = src || '';
            frame.hidden = !src;
            fx.value = '';
            fy.value = '';
            place();
        }

        frame.addEventListener('click', function (event) {
            var box = thumb.getBoundingClientRect();
            if (box.width === 0 || box.height === 0) {
                return;
            }
            set((event.clientX - box.left) / box.width * 100, (event.clientY - box.top) / box.height * 100);
            marker.focus();
        });

        marker.addEventListener('keydown', function (event) {
            var step = event.shiftKey ? 10 : 1;
            var x = fx.value === '' ? 50 : Number(fx.value);
            var y = fy.value === '' ? 50 : Number(fy.value);
            var moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
            if (!moves[event.key]) {
                return;
            }
            event.preventDefault();
            set(x + moves[event.key][0], y + moves[event.key][1]);
        });

        fx.addEventListener('input', place);
        fy.addEventListener('input', place);

        if (select) {
            select.addEventListener('change', function () {
                var option = select.options[select.selectedIndex];
                if (upload) {
                    upload.value = '';
                }
                show(option ? option.getAttribute('data-thumb') : '');
            });
        }

        if (upload) {
            upload.addEventListener('change', function () {
                var file = upload.files && upload.files[0];
                show(file ? URL.createObjectURL(file) : '');
            });
        }
    });
})();
