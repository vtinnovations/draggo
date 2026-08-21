/*
 * Draggo
 *
 * Package: vtinnovations/draggo
 * Copyright: V&T Innovations Team
 * Licence: LGPL-3.0-or-later
 * Website: https://v-t.one
 */

/**
 * Draggo frontend behaviours (loaded on every page). Currently: hamburger
 * navigation toggle. Delegated so it works for any number of nav elements.
 */
(function () {
  'use strict';
  // Close an open drawer/hamburger nav.
  function closeNav(onav) {
    onav.classList.remove('is-open');
    var tg = onav.querySelector('.draggo-nav-toggle');
    if (tg) tg.setAttribute('aria-expanded', 'false');
  }
  // Inject a close (×) button into the drawer panel once it first opens —
  // the toggle itself is covered by the panel, so users need another way out.
  function ensureClose(nav) {
    var list = nav.querySelector('.draggo-nav-list');
    if (list && !list.querySelector('.draggo-nav-close')) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'draggo-nav-close';
      b.setAttribute('aria-label', 'Menü schließen');
      b.innerHTML = '&times;';
      list.insertBefore(b, list.firstChild);
    }
    // Dim backdrop behind the panel (styled via .draggo-nav-backdrop; colour
    // from navOverlayColor). Lives inside the nav so scoped CSS can reach it.
    if (!nav.querySelector('.draggo-nav-backdrop')) {
      var bd = document.createElement('div');
      bd.className = 'draggo-nav-backdrop';
      nav.appendChild(bd);
    }
  }
  document.addEventListener('click', function (e) {
    // Explicit drawer close button or backdrop.
    var cb = e.target.closest('.draggo-nav-close, .draggo-nav-backdrop');
    if (cb) { var cnav = cb.closest('.draggo-nav'); if (cnav) closeNav(cnav); return; }
    // Hamburger nav
    var t = e.target.closest('.draggo-nav-toggle');
    if (t) {
      var nav = t.closest('.draggo-nav');
      if (nav) {
        var o = nav.classList.toggle('is-open');
        t.setAttribute('aria-expanded', o ? 'true' : 'false');
        if (o) ensureClose(nav);
      }
      return;
    }
    // Clicking a real link inside an open drawer/hamburger nav closes it.
    var navLink = e.target.closest('.draggo-nav.is-open a[href]');
    if (navLink) {
      var onav = navLink.closest('.draggo-nav');
      if (onav) closeNav(onav);
      // do not return — let the link navigate
    }
    // Click anywhere outside an open drawer closes it (the visible page area
    // beside the drawer acts as a backdrop).
    document.querySelectorAll('.draggo-nav.is-open').forEach(function (onav) {
      if (!onav.contains(e.target)) closeNav(onav);
    });
    // Accordion
    var head = e.target.closest('.draggo-acc-head');
    if (head) {
      var item = head.closest('.draggo-acc-item');
      var acc = head.closest('.draggo-acc');
      if (item && acc) {
        var open = item.classList.toggle('is-open');
        head.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open && acc.dataset.multi !== '1') {
          acc.querySelectorAll('.draggo-acc-item.is-open').forEach(function (o) {
            if (o !== item) { o.classList.remove('is-open'); var h = o.querySelector('.draggo-acc-head'); if (h) h.setAttribute('aria-expanded', 'false'); }
          });
        }
      }
      return;
    }
    // Tabs
    var btn = e.target.closest('.draggo-tabw-btn');
    if (btn) {
      var w = btn.closest('.draggo-tabw');
      if (w) {
        var i = btn.dataset.i;
        w.querySelectorAll('.draggo-tabw-btn').forEach(function (b) { b.classList.toggle('is-active', b === btn); });
        w.querySelectorAll('.draggo-tabw-panel').forEach(function (p) { p.classList.toggle('is-active', p.dataset.i === i); });
      }
    }
  });

  // Escape closes any open drawer.
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' || e.keyCode === 27) {
      document.querySelectorAll('.draggo-nav.is-open').forEach(function (onav) { closeNav(onav); });
    }
  });

  // ── Image carousel ──────────────────────────────────────────────
  function initCarousels(root) {
    (root || document).querySelectorAll('.draggo-car:not([data-init])').forEach(function (car) {
      car.setAttribute('data-init', '1');
      var track = car.querySelector('.draggo-car-track');
      if (!track) return;
      var slides = Array.prototype.slice.call(track.children);
      var fade = car.dataset.fade === '1';
      var per = fade ? 1 : Math.max(1, parseInt(car.dataset.per, 10) || 1);
      if (!fade) slides.forEach(function (s) { s.style.flex = '0 0 ' + (100 / per) + '%'; s.style.maxWidth = (100 / per) + '%'; });
      var idx = 0, maxI = Math.max(0, slides.length - per);
      var dotsWrap = car.querySelector('.draggo-car-dots'), dotBtns = [];
      var paint = function () { dotBtns.forEach(function (b, i) { b.classList.toggle('is-on', i === idx); }); };
      var go = function (i) {
        idx = Math.max(0, Math.min(maxI, i));
        if (fade) { slides.forEach(function (s, si) { s.classList.toggle('is-shown', si === idx); }); }
        else { track.style.transform = 'translateX(-' + (idx * (100 / per)) + '%)'; }
        paint();
      };
      var prev = car.querySelector('.draggo-car-prev'), next = car.querySelector('.draggo-car-next');
      if (prev) prev.addEventListener('click', function () { go(idx - 1); });
      if (next) next.addEventListener('click', function () { go(idx + 1); });
      if (dotsWrap) {
        for (var i = 0; i <= maxI; i++) {
          (function (i) { var b = document.createElement('button'); b.type = 'button'; b.addEventListener('click', function () { go(i); }); dotsWrap.appendChild(b); dotBtns.push(b); })(i);
        }
      }
      go(0);
      var auto = parseInt(car.dataset.auto, 10) || 0;
      var timer = null;
      var play = function () { if (auto > 0 && !timer) timer = setInterval(function () { go(idx >= maxI ? 0 : idx + 1); }, auto); };
      var stop = function () { if (timer) { clearInterval(timer); timer = null; } };
      if (auto > 0) {
        play();
        if (car.dataset.pause === '1') { car.addEventListener('mouseenter', stop); car.addEventListener('mouseleave', play); }
      }
    });
  }
  window.draggoInitCarousels = initCarousels;

  // ── Tier 2: counter + progress (animate once, on scroll into view) ──
  function whenVisible(el, cb) {
    if (!('IntersectionObserver' in window)) { cb(); return; }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) { if (e.isIntersecting) { cb(); io.disconnect(); } });
    }, { threshold: 0.35 });
    io.observe(el);
  }

  function initCounters(root) {
    (root || document).querySelectorAll('.draggo-counter:not([data-init])').forEach(function (c) {
      c.setAttribute('data-init', '1');
      var num = c.querySelector('.draggo-counter-num');
      if (!num) return;
      var start = parseInt(c.dataset.start, 10) || 0;
      var end = parseInt(c.dataset.end, 10) || 0;
      var dur = parseInt(c.dataset.dur, 10) || 2000;
      whenVisible(c, function () {
        var t0 = null;
        var step = function (ts) {
          if (t0 === null) t0 = ts;
          var p = Math.min(1, (ts - t0) / dur);
          num.textContent = Math.round(start + (end - start) * p).toLocaleString('de-DE');
          if (p < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
      });
    });
  }

  function initProgress(root) {
    (root || document).querySelectorAll('.draggo-progress-bar:not([data-init])').forEach(function (bar) {
      bar.setAttribute('data-init', '1');
      var v = Math.max(0, Math.min(100, parseInt(bar.dataset.value, 10) || 0));
      whenVisible(bar, function () { bar.style.width = v + '%'; });
    });
  }

  // ── Alert dismiss ──
  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.draggo-alert-close') : null;
    if (btn) { var box = btn.closest('.draggo-alert'); if (box) box.remove(); }
  });

  // ── Tier 5 ──
  function initCountdowns(root) {
    (root || document).querySelectorAll('.draggo-countdown:not([data-init])').forEach(function (cd) {
      cd.setAttribute('data-init', '1');
      var target = (parseInt(cd.dataset.target, 10) || 0) * 1000;
      var expired = cd.dataset.expired || '';
      var nums = {};
      cd.querySelectorAll('.draggo-cd-num').forEach(function (n) { nums[n.dataset.u] = n; });
      var pad = function (v) { return (v < 10 ? '0' : '') + v; };
      var tick = function () {
        var diff = target - Date.now();
        if (diff <= 0) { cd.innerHTML = '<span class="draggo-cd-expired">' + expired + '</span>'; return false; }
        var s = Math.floor(diff / 1000);
        if (nums.d) nums.d.textContent = Math.floor(s / 86400);
        if (nums.h) nums.h.textContent = pad(Math.floor((s % 86400) / 3600));
        if (nums.m) nums.m.textContent = pad(Math.floor((s % 3600) / 60));
        if (nums.s) nums.s.textContent = pad(s % 60);
        return true;
      };
      if (tick()) { var iv = setInterval(function () { if (!tick()) clearInterval(iv); }, 1000); }
    });
  }

  // Open-Now badge: re-evaluate open/closed in the element's configured IANA
  // timezone (via Intl) so the badge stays correct as time passes — mirrors the
  // server-side OpenHours logic exactly. The server already painted the correct
  // initial state, so this is pure keep-fresh (no flash, works without JS).
  function initOpenNow(root) {
    var DOW = { Mon: 0, Tue: 1, Wed: 2, Thu: 3, Fri: 4, Sat: 5, Sun: 6 };
    var toMin = function (hm) { var m = /^(\d{1,2}):(\d{2})$/.exec(hm || ''); return m ? (parseInt(m[1], 10) * 60 + parseInt(m[2], 10)) : null; };
    (root || document).querySelectorAll('.draggo-opennow[data-hours]:not([data-init])').forEach(function (el) {
      el.setAttribute('data-init', '1');
      var hours, days;
      try { hours = JSON.parse(el.dataset.hours); days = JSON.parse(el.dataset.days || '[]'); } catch (e) { return; }
      if (!Array.isArray(hours)) return;
      var tz = el.dataset.tz || 'UTC';
      var openLbl = el.dataset.open || 'Open', closedLbl = el.dataset.closed || 'Closed';
      var showNext = el.dataset.next === '1';
      var textEl = el.querySelector('.draggo-on-text');
      var nowTz = function () {
        var p = {};
        try {
          new Intl.DateTimeFormat('en-US', { timeZone: tz, weekday: 'short', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
            .formatToParts(new Date()).forEach(function (x) { p[x.type] = x.value; });
        } catch (e) { return null; }
        return { dow: DOW[p.weekday] != null ? DOW[p.weekday] : 0, min: parseInt(p.hour, 10) * 60 + parseInt(p.minute, 10) };
      };
      var evalNow = function () {
        var t = nowTz(); if (!t) return null;
        var today = hours[t.dow] || [], i;
        for (i = 0; i < today.length; i++) { var o = toMin(today[i][0]), c = toMin(today[i][1]); if (o != null && c != null && o <= t.min && t.min < c) return { open: true, closesAt: today[i][1] }; }
        for (i = 0; i < today.length; i++) { var o2 = toMin(today[i][0]); if (o2 != null && o2 > t.min) return { open: false, opensAt: today[i][0], opensDow: t.dow, opensToday: true }; }
        for (var k = 1; k <= 7; k++) { var d = (t.dow + k) % 7, arr = hours[d] || []; if (arr.length) return { open: false, opensAt: arr[0][0], opensDow: d, opensToday: false }; }
        return { open: false };
      };
      var render = function () {
        var r = evalNow(); if (!r) return;
        el.classList.toggle('is-open', r.open);
        el.classList.toggle('is-closed', !r.open);
        var txt;
        if (r.open) { txt = openLbl + (showNext && r.closesAt ? ' · schließt ' + r.closesAt : ''); }
        else if (!showNext || !r.opensAt) { txt = closedLbl; }
        else { txt = closedLbl + ' · öffnet ' + (r.opensToday ? '' : (days[r.opensDow] || '') + ' ') + r.opensAt; }
        if (textEl) textEl.textContent = txt;
      };
      render();
      setInterval(render, 60000);
      document.addEventListener('visibilitychange', function () { if (!document.hidden) render(); });
    });
  }

  // Dark/Light toggle: flip a manual theme override on <html> + persist it.
  // The colour tokens' dark values (emitted server-side) do the recolouring;
  // an inline head script already applied the stored choice before paint.
  function initThemeToggle(root) {
    (root || document).querySelectorAll('[data-draggo-theme-toggle]:not([data-init])').forEach(function (btn) {
      btn.setAttribute('data-init', '1');
      btn.addEventListener('click', function () {
        var de = document.documentElement;
        var cur = de.getAttribute('data-draggo-theme');
        var eff = cur || ((window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light');
        var next = eff === 'dark' ? 'light' : 'dark';
        de.setAttribute('data-draggo-theme', next);
        try { localStorage.setItem('draggoTheme', next); } catch (e) {}
      });
    });
  }

  function initMaps(root) {
    (root || document).querySelectorAll('.draggo-map-load:not([data-init])').forEach(function (btn) {
      btn.setAttribute('data-init', '1');
      btn.addEventListener('click', function () {
        var box = btn.closest('.draggo-map-consent');
        if (!box) return;
        var f = document.createElement('iframe');
        f.className = 'draggo-map-frame'; f.src = box.dataset.src; f.loading = 'lazy';
        f.setAttribute('allowfullscreen', ''); f.setAttribute('referrerpolicy', 'no-referrer-when-downgrade');
        box.parentNode.replaceChild(f, box);
      });
    });
  }

  function initShareCopy(root) {
    (root || document).querySelectorAll('.draggo-share-copy:not([data-init])').forEach(function (b) {
      b.setAttribute('data-init', '1');
      b.addEventListener('click', function () {
        var url = b.dataset.url || location.href;
        if (navigator.clipboard) { navigator.clipboard.writeText(url); }
        b.classList.add('is-copied');
        setTimeout(function () { b.classList.remove('is-copied'); }, 1200);
      });
    });
  }

  function initToc(root) {
    (root || document).querySelectorAll('.draggo-toc:not([data-init])').forEach(function (toc) {
      toc.setAttribute('data-init', '1');
      var list = toc.querySelector('.draggo-toc-list');
      if (!list) return;
      var sel = (toc.dataset.levels || '2,3').split(',').map(function (x) { return 'h' + x.trim(); }).join(',');
      var scope = toc.closest('main, article, .mod_article') || document.body;
      var n = 0, frag = '';
      scope.querySelectorAll(sel).forEach(function (h) {
        if (toc.contains(h)) return;
        if (!h.id) {
          h.id = 'toc-' + (++n) + '-' + (h.textContent || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 40);
        }
        frag += '<li class="draggo-toc-i draggo-toc-' + h.tagName.toLowerCase() + '"><a href="#' + h.id + '">' + (h.textContent || '').replace(/</g, '&lt;') + '</a></li>';
      });
      list.innerHTML = frag || '<li class="draggo-toc-empty">Keine Überschriften gefunden.</li>';
    });
  }

  function initAnim(root) {
    (root || document).querySelectorAll('.draggo-anim:not([data-init])').forEach(function (el) {
      el.setAttribute('data-init', '1');
      var span = el.querySelector('.draggo-anim-words');
      if (!span) return;
      var words; try { words = JSON.parse(el.dataset.words || '[]'); } catch (e) { words = []; }
      if (words.length < 2) return;
      var effect = el.dataset.effect || 'rotate';
      var i = 0;
      if (effect === 'type') {
        var ci = 0, deleting = false;
        var typeTick = function () {
          var w = words[i];
          if (!deleting) {
            ci++; span.textContent = w.slice(0, ci);
            if (ci >= w.length) { deleting = true; setTimeout(typeTick, 1400); return; }
          } else {
            ci--; span.textContent = w.slice(0, ci);
            if (ci <= 0) { deleting = false; i = (i + 1) % words.length; }
          }
          setTimeout(typeTick, deleting ? 50 : 110);
        };
        setTimeout(typeTick, 600);
      } else {
        setInterval(function () { i = (i + 1) % words.length; span.textContent = words[i]; }, 2200);
      }
    });
  }

  function initCodeCopy(root) {
    (root || document).querySelectorAll('.draggo-code-copy:not([data-init])').forEach(function (b) {
      b.setAttribute('data-init', '1');
      b.addEventListener('click', function () {
        var box = b.closest('.draggo-code');
        var code = box ? box.querySelector('code') : null;
        if (code && navigator.clipboard) { navigator.clipboard.writeText(code.textContent || ''); }
        var t = b.textContent; b.textContent = 'Kopiert ✓'; b.classList.add('is-copied');
        setTimeout(function () { b.textContent = t; b.classList.remove('is-copied'); }, 1400);
      });
    });
  }

  function initVideoPlaylists(root) {
    (root || document).querySelectorAll('.draggo-vplaylist:not([data-init])').forEach(function (vp) {
      vp.setAttribute('data-init', '1');
      var stage = vp.querySelector('.draggo-vp-stage');
      if (!stage) return;
      var btns = Array.prototype.slice.call(vp.querySelectorAll('.draggo-vp-item'));
      var load = function (i) {
        var b = btns[i];
        if (!b) return;
        btns.forEach(function (x) { x.classList.toggle('is-active', x === b); });
        stage.innerHTML = '';
        if (b.dataset.embed) {
          var f = document.createElement('iframe');
          f.className = 'draggo-vp-frame'; f.src = b.dataset.embed;
          f.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture');
          f.setAttribute('allowfullscreen', '');
          stage.appendChild(f);
        } else if (b.dataset.native) {
          var v = document.createElement('video');
          v.className = 'draggo-vp-frame'; v.src = b.dataset.native; v.controls = true; v.autoplay = true;
          stage.appendChild(v);
        }
      };
      btns.forEach(function (b, i) { b.addEventListener('click', function () { load(i); }); });
      var play = stage.querySelector('.draggo-vp-play');
      if (play) play.addEventListener('click', function () { load(parseInt(play.dataset.i, 10) || 0); });
    });
  }

  function initGalleries(root) {
    (root || document).querySelectorAll('.draggo-gallery[data-gallery-lightbox]:not([data-init])').forEach(function (g) {
      g.setAttribute('data-init', '1');
      var links = Array.prototype.slice.call(g.querySelectorAll('a[data-lightbox]'));
      if (!links.length) return;
      var open = function (idx) {
        var i = idx;
        var ov = document.createElement('div'); ov.className = 'draggo-lb';
        var img = document.createElement('img');
        var close = document.createElement('button'); close.type = 'button'; close.className = 'draggo-lb-close'; close.textContent = '✕'; close.setAttribute('aria-label', 'Schließen');
        var prev = document.createElement('button'); prev.type = 'button'; prev.className = 'draggo-lb-prev'; prev.textContent = '‹'; prev.setAttribute('aria-label', 'Zurück');
        var next = document.createElement('button'); next.type = 'button'; next.className = 'draggo-lb-next'; next.textContent = '›'; next.setAttribute('aria-label', 'Weiter');
        var show = function () { img.src = links[i].getAttribute('href'); };
        var go = function (d) { i = (i + d + links.length) % links.length; show(); };
        show();
        if (links.length < 2) { prev.style.display = 'none'; next.style.display = 'none'; }
        ov.appendChild(img); ov.appendChild(close); ov.appendChild(prev); ov.appendChild(next);
        var done = function () { ov.remove(); document.removeEventListener('keydown', key); };
        var key = function (e) { if (e.key === 'Escape') done(); else if (e.key === 'ArrowLeft') go(-1); else if (e.key === 'ArrowRight') go(1); };
        close.addEventListener('click', done);
        prev.addEventListener('click', function (e) { e.stopPropagation(); go(-1); });
        next.addEventListener('click', function (e) { e.stopPropagation(); go(1); });
        ov.addEventListener('click', function (e) { if (e.target === ov) done(); });
        document.addEventListener('keydown', key);
        document.body.appendChild(ov);
      };
      links.forEach(function (a, idx) { a.addEventListener('click', function (e) { e.preventDefault(); open(idx); }); });
    });
  }

  // Background media: inject container layers (shipped via window.__draggoBg)
  // then start any slideshow cycling (rows/cols already have the slide DOM).
  function initBgMedia(root) {
    root = root || document;
    var cfg = window.__draggoBg || {};
    Object.keys(cfg).forEach(function (id) {
      var host = document.querySelector('.draggo-art-' + id);
      if (!host || host.querySelector(':scope > .draggo-bg-media')) return;
      host.insertAdjacentHTML('afterbegin', cfg[id]);
      if (!host.style.position) host.style.position = 'relative';
      host.style.isolation = 'isolate';
    });
    (root.querySelectorAll ? root : document).querySelectorAll('.draggo-bg-slider').forEach(function (sl) {
      if (sl.__bgInit) return; sl.__bgInit = 1;
      var iv = parseInt(sl.dataset.interval, 10) || 5000;
      var track = sl.querySelector('.draggo-bg-track');
      if (track) {
        // Slide: translate the flex track.
        var n = track.children.length;
        if (n < 2) return;
        var ti = 0;
        setInterval(function () { ti = (ti + 1) % n; track.style.transform = 'translateX(-' + (ti * 100) + '%)'; }, iv);
        return;
      }
      // Fade: cross-fade stacked slides.
      var slides = sl.querySelectorAll('.draggo-bg-slide');
      if (slides.length < 2) return;
      var i = 0;
      setInterval(function () {
        slides[i].classList.remove('is-on');
        i = (i + 1) % slides.length;
        slides[i].classList.add('is-on');
      }, iv);
    });
  }

  function initTier2(root) {
    initCounters(root); initProgress(root);
    initCountdowns(root); initOpenNow(root); initThemeToggle(root); initMaps(root); initShareCopy(root); initToc(root);
    initAnim(root); initCodeCopy(root); initVideoPlaylists(root); initGalleries(root);
    initBgMedia(root);
  }
  window.draggoInitTier2 = initTier2;

  if (document.readyState !== 'loading') { initCarousels(); initTier2(); }
  else { document.addEventListener('DOMContentLoaded', function () { initCarousels(); initTier2(); }); }
})();
