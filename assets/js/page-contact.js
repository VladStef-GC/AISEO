/**
 * Contact & Feedback page — star rating widget.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var widget = document.querySelector('.aisc-contact-rating');
        if (!widget) {
            return;
        }

        var stars = Array.prototype.slice.call(widget.querySelectorAll('.aisc-contact-star'));
        var input = widget.querySelector('input[name="aisc_rating"]');

        function paint(value) {
            stars.forEach(function (star) {
                var filled = parseInt(star.getAttribute('data-value'), 10) <= value;
                star.classList.toggle('dashicons-star-filled', filled);
                star.classList.toggle('dashicons-star-empty', !filled);
            });
        }

        stars.forEach(function (star) {
            star.addEventListener('click', function () {
                var value = parseInt(star.getAttribute('data-value'), 10);
                if (input) {
                    input.value = value;
                }
                widget.setAttribute('data-rating', value);
                paint(value);
            });

            star.addEventListener('mouseenter', function () {
                paint(parseInt(star.getAttribute('data-value'), 10));
            });
        });

        widget.querySelector('.aisc-contact-stars').addEventListener('mouseleave', function () {
            paint(parseInt(widget.getAttribute('data-rating'), 10) || 0);
        });
    });
})();
