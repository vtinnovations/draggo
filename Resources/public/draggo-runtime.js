/*
 * Draggo
 *
 * Package: vtinnovations/draggo
 * Copyright: V&T Innovations Team
 * Licence: LGPL-3.0-or-later
 * Website: https://v-t.one
 */

/**
 * Draggo frontend runtime. Tiny, dependency-free. Activated by
 * ElementStyleListener only when a page actually uses scroll animations or
 * image lightboxes; reads its work-list from window.DRAGGO_FX.
 *
 *   window.DRAGGO_FX = { anim: ['.draggo-el-5', …], lightbox: ['.draggo-el-9', …] }
 */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') { fn(); }
    else { document.addEventListener('DOMContentLoaded', fn); }
  }

  ready(function () {
    var fx = window.DRAGGO_FX || {};
    var anim = fx.anim || [];
    var lightbox = fx.lightbox || [];
    var sticky = fx.sticky || [];

    // ── Sticky elements (position:fixed on scroll — reliable inside any
    //    parent, unlike pure CSS position:sticky) ────────────────────────
    if (sticky.length) {
      sticky.forEach(function (s) {
        Array.prototype.forEach.call(document.querySelectorAll(s.sel), function (el) {
          var offset = s.offset || 0;
          var placeholder = null, stuck = false, startTop = 0;
          var measure = function () {
            if (stuck) return;
            startTop = el.getBoundingClientRect().top + window.pageYOffset;
          };
          var stick = function () {
            var r = el.getBoundingClientRect();
            placeholder = document.createElement('div');
            placeholder.style.height = r.height + 'px';
            placeholder.className = 'draggo-sticky-ph';
            el.parentNode.insertBefore(placeholder, el);
            el.style.position = 'fixed';
            el.style.top = offset + 'px';
            el.style.left = r.left + 'px';
            el.style.width = r.width + 'px';
            el.style.zIndex = '99999';
            el.classList.add('draggo-stuck');
            stuck = true;
          };
          var unstick = function () {
            el.style.position = ''; el.style.top = ''; el.style.left = ''; el.style.width = ''; el.style.zIndex = '';
            el.classList.remove('draggo-stuck');
            if (placeholder) { placeholder.remove(); placeholder = null; }
            stuck = false;
          };
          var onScroll = function () {
            if (window.pageYOffset + offset >= startTop) { if (!stuck) stick(); }
            else if (stuck) { unstick(); measure(); }
          };
          measure();
          window.addEventListener('scroll', onScroll, { passive: true });
          window.addEventListener('resize', function () { if (stuck) unstick(); measure(); onScroll(); });
          onScroll();
        });
      });
    }

    // ── Scroll animations ───────────────────────────────────────────
    if (anim.length) {
      var nodes = [];
      anim.forEach(function (sel) {
        Array.prototype.forEach.call(document.querySelectorAll(sel), function (n) { nodes.push(n); });
      });

      if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
          entries.forEach(function (e) {
            if (e.isIntersecting) { e.target.classList.add('draggo-in'); io.unobserve(e.target); }
          });
        }, { threshold: 0.15, rootMargin: '0px 0px -10% 0px' });
        nodes.forEach(function (n) { io.observe(n); });
      } else {
        // No observer support → just reveal everything.
        nodes.forEach(function (n) { n.classList.add('draggo-in'); });
      }
    }

    // ── Image lightbox ──────────────────────────────────────────────
    if (lightbox.length) {
      var overlay = null;

      function close() { if (overlay) { overlay.classList.remove('is-open'); } }

      function ensureOverlay() {
        if (overlay) { return overlay; }
        overlay = document.createElement('div');
        overlay.className = 'draggo-lightbox';
        overlay.innerHTML = '<button type="button" class="draggo-lightbox-close" aria-label="Schließen">✕</button><img alt="">';
        document.body.appendChild(overlay);
        overlay.addEventListener('click', function (ev) { if (ev.target === overlay || ev.target.classList.contains('draggo-lightbox-close')) { close(); } });
        document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') { close(); } });
        return overlay;
      }

      function open(src, alt) {
        var o = ensureOverlay();
        var img = o.querySelector('img');
        img.src = src; img.alt = alt || '';
        o.classList.add('is-open');
      }

      lightbox.forEach(function (sel) {
        Array.prototype.forEach.call(document.querySelectorAll(sel), function (wrap) {
          var img = wrap.tagName === 'IMG' ? wrap : wrap.querySelector('img');
          if (!img) { return; }
          wrap.style.cursor = 'zoom-in';
          wrap.addEventListener('click', function (ev) {
            ev.preventDefault();
            // Prefer a full-size source if the markup links to one.
            var link = img.closest('a');
            var full = (link && /\.(jpe?g|png|gif|webp|avif)$/i.test(link.getAttribute('href') || '')) ? link.getAttribute('href') : img.currentSrc || img.src;
            open(full, img.alt);
          });
        });
      });
    }
  });
})();
