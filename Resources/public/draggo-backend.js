/*
 * Draggo
 *
 * Package: vtinnovations/draggo
 * Copyright: V&T Innovations Team
 * Licence: LGPL-3.0-or-later
 * Website: https://v-t.one
 */

/*
 * Draggo backend grid visualiser.
 *
 * Contao renders an article's content elements as a flat <ul> of <li> rows;
 * our grid wrappers (row_start / col / row_stop) are just rows in that list.
 * This script regroups the rows of each Draggo grid into side-by-side column
 * boxes — similar to premium grid modules — so the grid is recognisable in the
 * backend. It only moves DOM nodes for display; the underlying records and
 * their edit/delete operations are untouched.
 *
 * Best-effort: live column resizing and drag-sort across the regrouped layout
 * are NOT handled here (edit a row's "Struktur" field to change widths). Each
 * row's operation menu (edit/delete/hide) keeps working.
 */
(function () {
  'use strict';

  function markerOf(li) {
    return li.querySelector('.draggo-be-wrapper');
  }

  function apply() {
    var starts = document.querySelectorAll('.draggo-be-wrapper[data-draggo-type="row_start"]');

    Array.prototype.forEach.call(starts, function (marker) {
      var startLi = marker.closest('li');
      if (!startLi || startLi.getAttribute('data-draggo-grouped')) return;

      var widths = (marker.getAttribute('data-draggo-cols') || '')
        .split(',').map(function (n) { return parseInt(n, 10); })
        .filter(function (n) { return n >= 1 && n <= 12; });

      // Collect column groups between row_start and row_stop.
      var cols = [[]];
      var node = startLi.nextElementSibling;
      var stopLi = null;
      while (node) {
        var m = markerOf(node);
        var t = m ? m.getAttribute('data-draggo-type') : null;
        if (t === 'row_stop') { stopLi = node; break; }
        if (t === 'col') { cols.push([]); node.setAttribute('data-draggo-hide', '1'); node = node.nextElementSibling; continue; }
        cols[cols.length - 1].push(node);
        node = node.nextElementSibling;
      }
      if (stopLi) stopLi.setAttribute('data-draggo-hide', '1');

      // Build the side-by-side layout and move the content rows into columns.
      var wrapLi = document.createElement('li');
      wrapLi.className = 'draggo-be-rowwrap';

      var row = document.createElement('div');
      row.className = 'draggo-be-row';

      cols.forEach(function (colNodes, i) {
        var pct = (widths[i] || Math.floor(12 / cols.length) || 12) / 12 * 100;
        var col = document.createElement('div');
        col.className = 'draggo-be-col';
        col.style.flex = '0 0 ' + pct + '%';
        col.style.maxWidth = pct + '%';

        var head = document.createElement('div');
        head.className = 'draggo-be-col-head';
        head.textContent = 'Spalte · ' + Math.round(pct) + '%';
        col.appendChild(head);

        colNodes.forEach(function (li) { col.appendChild(li); });
        row.appendChild(col);
      });

      wrapLi.appendChild(row);
      startLi.parentNode.insertBefore(wrapLi, startLi.nextElementSibling);
      startLi.setAttribute('data-draggo-grouped', '1');
    });
  }

  function boot() {
    try { apply(); } catch (e) { if (window.console) console.warn('Draggo backend grid:', e); }
  }

  document.addEventListener('DOMContentLoaded', boot);
  // Contao 5 backend uses Turbo — re-run after navigations/renders.
  document.addEventListener('turbo:load', boot);
  document.addEventListener('turbo:render', boot);
})();
