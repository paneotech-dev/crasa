/*
 * The Facebook page timeline, put in only when the reader comes near it.
 *
 * The markup carries the frame's address in data-src and a plain link to
 * the page. Nothing from facebook.com loads until the placeholder is within
 * a screen of the viewport, so a reader who never scrolls that far never
 * contacts Facebook at all.
 */
(function () {
  var slots = document.querySelectorAll('[data-fb-timeline]');
  if (!slots.length) return;

  function load(slot) {
    if (slot.querySelector('iframe')) return;
    var width = Math.max(180, Math.min(500, Math.floor(slot.clientWidth || 500)));
    var src = slot.getAttribute('data-src').replace(/([?&])width=\d+/, '$1width=' + width);
    var frame = document.createElement('iframe');
    frame.src = src;
    frame.title = slot.getAttribute('data-title') || '';
    frame.width = String(width);
    frame.height = '560';
    frame.loading = 'lazy';
    frame.setAttribute('scrolling', 'no');
    frame.setAttribute('frameborder', '0');
    frame.setAttribute('allow', 'encrypted-media');
    frame.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
    slot.appendChild(frame);
    slot.setAttribute('data-loaded', '');
  }

  if (!('IntersectionObserver' in window)) {
    slots.forEach(load);
    return;
  }

  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        observer.unobserve(entry.target);
        load(entry.target);
      }
    });
  }, { rootMargin: '600px 0px' });

  slots.forEach(function (slot) { observer.observe(slot); });
})();
