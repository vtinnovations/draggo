/*
 * Draggo
 *
 * Package: vtinnovations/draggo
 * Copyright: V&T Innovations Team
 * Licence: LGPL-3.0-or-later
 * Website: https://v-t.one
 */

/* Draggo scrollytelling engine. One shared runtime for every scroll element.
 * Each element opts in via [data-draggo-scroll="<effect>"]; the engine wires the
 * effect with a single IntersectionObserver (visibility gate) + one rAF loop
 * (progress driving). Respects prefers-reduced-motion (everything renders in its
 * final/revealed state, no motion). Safe to load once per page; re-scans on DOM
 * changes via a MutationObserver so AJAX-inserted content works too.
 *
 * Progress convention: for a target, p = 0 when its top hits the viewport bottom,
 * p = 1 when its bottom hits the viewport top (full pass-through), clamped 0..1.
 * Effects read p (or a sticky-relative p) to drive transforms — no layout reads
 * in the write phase, GPU transforms only.
 */
(function () {
  'use strict';
  if (window.__draggoScroll) return;
  window.__draggoScroll = true;

  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var active = [];          // elements currently in/near viewport
  var ticking = false;

  function clamp(v, a, b) { return v < a ? a : (v > b ? b : v); }

  // Progress of an element through the viewport (0 before, 1 after).
  function passProgress(rect, vh) {
    return clamp((vh - rect.top) / (vh + rect.height), 0, 1);
  }
  // Progress while an element is "pinned" (its tall track scrolls past).
  function pinProgress(rect, vh) {
    var total = rect.height - vh;
    if (total <= 0) return rect.top <= 0 ? 1 : 0;
    return clamp(-rect.top / total, 0, 1);
  }

  // ── Per-effect drivers ────────────────────────────────────────────
  var drivers = {
    // Sticky cards that scale + dim as the next card overlaps them.
    stack: function (el, p, vh) {
      var cards = el.__cards || (el.__cards = [].slice.call(el.querySelectorAll('.draggo-ss-card')));
      var n = cards.length;
      cards.forEach(function (card, i) {
        var r = card.getBoundingClientRect();
        // How far the NEXT card has covered this one (0..1).
        var next = cards[i + 1];
        var cover = 0;
        if (next) {
          var nr = next.getBoundingClientRect();
          var top = parseFloat(getComputedStyle(card).top) || 0;
          cover = clamp((top - nr.top) / Math.max(1, nr.height), 0, 1);
        }
        var minScale = parseFloat(el.dataset.scale || '0.9');
        card.style.transform = 'scale(' + (1 - (1 - minScale) * cover) + ')';
        card.style.filter = cover > 0 ? 'brightness(' + (1 - 0.35 * cover) + ')' : '';
        card.style.zIndex = String(i + 1);
      });
    },
    // Pin the section; translate the inner track sideways by scroll progress.
    hpin: function (el, p, vh) {
      var stage = el.__stage || (el.__stage = el.querySelector('.draggo-hp-stage'));
      var track = el.__track || (el.__track = el.querySelector('.draggo-hp-track'));
      if (!track || !stage) return;
      var pp = pinProgress(el.getBoundingClientRect(), vh);
      // Translate by how much the (overflowing) track exceeds the visible stage
      // width — NOT the track's own width (which equals its scrollWidth → 0).
      var dist = track.scrollWidth - stage.clientWidth;
      if (dist < 0) dist = 0;
      track.style.transform = 'translate3d(' + (-dist * pp) + 'px,0,0)';
      // Reveal panels as they enter from the right.
      var panels = el.__panels || (el.__panels = [].slice.call(track.querySelectorAll('.draggo-hp-panel')));
      var seg = 1 / Math.max(1, panels.length);
      panels.forEach(function (panel, i) {
        var local = clamp((pp - i * seg) / seg, 0, 1);
        panel.style.opacity = String(0.25 + 0.75 * local);
      });
    },
    // Pin text column; swap the active visual by scroll progress (split story).
    split: function (el, p, vh) {
      var steps = el.__steps || (el.__steps = [].slice.call(el.querySelectorAll('.draggo-sp-step')));
      var vis = el.__vis || (el.__vis = [].slice.call(el.querySelectorAll('.draggo-sp-visual')));
      if (!steps.length) return;
      var pp = pinProgress(el.getBoundingClientRect(), vh);
      var idx = clamp(Math.floor(pp * steps.length), 0, steps.length - 1);
      steps.forEach(function (s, i) { s.classList.toggle('is-active', i === idx); });
      vis.forEach(function (v, i) { v.classList.toggle('is-active', i === idx); });
    },
    // Word-by-word colour fill as the element passes the viewport middle.
    text: function (el, p, vh) {
      var words = el.__words || (el.__words = [].slice.call(el.querySelectorAll('.draggo-th-w')));
      if (!words.length) return;
      var r = el.getBoundingClientRect();
      var local = clamp((vh * 0.8 - r.top) / (r.height * 0.6), 0, 1);
      var lit = Math.round(local * words.length);
      words.forEach(function (w, i) { w.classList.toggle('is-on', i < lit); });
    },
    // Multi-layer parallax: each [data-speed] layer moves at its own rate.
    parallax: function (el, p, vh) {
      var layers = el.__layers || (el.__layers = [].slice.call(el.querySelectorAll('[data-speed]')));
      var r = el.getBoundingClientRect();
      var center = (r.top + r.height / 2 - vh / 2) / vh; // -1..1
      layers.forEach(function (l) {
        var sp = parseFloat(l.dataset.speed || '0');
        l.style.transform = 'translate3d(0,' + (center * sp * -100) + 'px,0)';
      });
    },
    // Scale a framed media up to full as it scrolls into view (Apple-style).
    zoom: function (el, p, vh) {
      var frame = el.__frame || (el.__frame = el.querySelector('.draggo-zm-frame'));
      if (!frame) return;
      var from = parseFloat(el.dataset.from || '0.6');
      var s = from + (1 - from) * p;
      frame.style.transform = 'scale(' + clamp(s, from, 1) + ')';
      frame.style.borderRadius = (24 * (1 - p)) + 'px';
    },
    // Vertical timeline: fill the line + activate steps by scroll progress.
    timeline: function (el, p, vh) {
      var fill = el.__fill || (el.__fill = el.querySelector('.draggo-tl-fill'));
      var steps = el.__tsteps || (el.__tsteps = [].slice.call(el.querySelectorAll('.draggo-tl-step')));
      var r = el.getBoundingClientRect();
      var local = clamp((vh * 0.6 - r.top) / (r.height - vh * 0.4), 0, 1);
      if (fill) fill.style.height = (local * 100) + '%';
      var lit = Math.round(local * steps.length);
      steps.forEach(function (s, i) { s.classList.toggle('is-active', i < lit); });
    },
    // SVG path that draws itself as the element passes the viewport.
    svgdraw: function (el, p, vh) {
      var path = el.__sdpath || (el.__sdpath = el.querySelector('.draggo-sd-path'));
      if (!path) return;
      if (!el.__sdlen) { el.__sdlen = path.getTotalLength() || 1; path.style.strokeDasharray = el.__sdlen; }
      var r = el.getBoundingClientRect();
      var local = clamp((vh * 0.85 - r.top) / (r.height * 0.6), 0, 1);
      path.style.strokeDashoffset = String(el.__sdlen * (1 - local));
    },
    // Reading-progress bar: fills with the WHOLE PAGE scroll progress.
    progress: function (el, p, vh) {
      var bar = el.__pbar || (el.__pbar = el.querySelector('.draggo-pr-fill'));
      if (!bar) return;
      var max = document.documentElement.scrollHeight - vh;
      bar.style.width = (max > 0 ? clamp(window.scrollY / max, 0, 1) * 100 : 0) + '%';
    },
    // Curtain: full-bleed sticky panels; the covered one scales + dims.
    curtain: function (el, p, vh) {
      var panels = el.__ctp || (el.__ctp = [].slice.call(el.querySelectorAll('.draggo-ct-panel')));
      panels.forEach(function (panel, i) {
        var next = panels[i + 1];
        var cover = 0;
        if (next) {
          var top = parseFloat(getComputedStyle(panel).top) || 0;
          cover = clamp((top - next.getBoundingClientRect().top) / Math.max(1, next.getBoundingClientRect().height), 0, 1);
        }
        panel.style.transform = 'scale(' + (1 - 0.06 * cover) + ')';
        panel.style.filter = cover > 0 ? 'brightness(' + (1 - 0.4 * cover) + ')' : '';
      });
    },
    // Text-mask: parallax the image seen THROUGH the clipped text.
    textmask: function (el, p, vh) {
      var t = el.__tmt || (el.__tmt = el.querySelector('.draggo-tm-text'));
      if (!t) return;
      var r = el.getBoundingClientRect();
      var center = (r.top + r.height / 2 - vh / 2) / vh;
      t.style.backgroundPositionY = (50 + center * 35) + '%';
    },
  };

  // Reveal effect uses the observer only (one-shot), no rAF.
  function reveal(el) { el.classList.add('is-in'); }

  // Effects revealed once on enter (no per-frame driver).
  var ONE_SHOT = { reveal: 1, revealgrid: 1, tilt: 1 };

  // 3D tilt toward the pointer for each card in a tilt grid.
  function initTilt(el) {
    [].slice.call(el.querySelectorAll('.draggo-tilt-card')).forEach(function (card) {
      if (card.__tilt) return;
      card.__tilt = 1;
      card.addEventListener('pointermove', function (e) {
        var b = card.getBoundingClientRect();
        var rx = ((e.clientY - b.top) / b.height - 0.5) * -10;
        var ry = ((e.clientX - b.left) / b.width - 0.5) * 10;
        card.style.transform = 'perspective(800px) rotateX(' + rx + 'deg) rotateY(' + ry + 'deg)';
      });
      card.addEventListener('pointerleave', function () { card.style.transform = ''; });
    });
  }

  function frame() {
    ticking = false;
    var vh = window.innerHeight || document.documentElement.clientHeight;
    for (var i = 0; i < active.length; i++) {
      var el = active[i];
      var fx = el.dataset.draggoScroll;
      var drv = drivers[fx];
      if (!drv) continue;
      drv(el, passProgress(el.getBoundingClientRect(), vh), vh);
    }
  }
  function onScroll() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(frame);
  }

  var io = ('IntersectionObserver' in window) ? new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      var el = e.target;
      if (ONE_SHOT[el.dataset.draggoScroll]) {
        if (e.isIntersecting) {
          reveal(el);
          if (el.dataset.draggoScroll === 'tilt') { initTilt(el); }
          io.unobserve(el);
        }
        return;
      }
      var idx = active.indexOf(el);
      if (e.isIntersecting && idx < 0) active.push(el);
      else if (!e.isIntersecting && idx >= 0) active.splice(idx, 1);
    });
    onScroll();
  }, { rootMargin: '120px 0px' }) : null;

  function init(root) {
    var els = (root || document).querySelectorAll('[data-draggo-scroll]');
    [].forEach.call(els, function (el) {
      if (el.__draggoInit) return;
      el.__draggoInit = true;
      var fx = el.dataset.draggoScroll;
      if (reduce) {
        // Final/revealed state, no motion.
        el.classList.add('is-in');
        [].forEach.call(el.querySelectorAll('.draggo-th-w'), function (w) { w.classList.add('is-on'); });
        [].forEach.call(el.querySelectorAll('.draggo-sp-step,.draggo-sp-visual,.draggo-tl-step'), function (s) { s.classList.add('is-active'); });
        if (fx === 'tilt') { initTilt(el); }
        return;
      }
      // A reading-progress bar reflects whole-page scroll, so it is always
      // active regardless of its own visibility.
      if (fx === 'progress') { active.push(el); }
      if (io) io.observe(el);
      else if (ONE_SHOT[fx]) { reveal(el); if (fx === 'tilt') initTilt(el); }
      else if (fx !== 'progress') { active.push(el); }
    });
    if (!reduce) onScroll();
  }

  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll, { passive: true });
  if (document.readyState !== 'loading') init(); else document.addEventListener('DOMContentLoaded', function () { init(); });

  // Re-scan on dynamic inserts (AJAX/editor).
  if ('MutationObserver' in window) {
    new MutationObserver(function (muts) {
      for (var i = 0; i < muts.length; i++) {
        if (muts[i].addedNodes && muts[i].addedNodes.length) { init(); break; }
      }
    }).observe(document.body, { childList: true, subtree: true });
  }
})();
