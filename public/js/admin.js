/**
 * Interpolate between two hex colors by ratio (0–1).
 */
function lerpColor(a, b, t) {
  var ah = parseInt(a.slice(1), 16);
  var bh = parseInt(b.slice(1), 16);
  var ar = (ah >> 16) & 0xff, ag = (ah >> 8) & 0xff, ab = ah & 0xff;
  var br = (bh >> 16) & 0xff, bg = (bh >> 8) & 0xff, bb = bh & 0xff;
  var r = Math.round(ar + (br - ar) * t);
  var g = Math.round(ag + (bg - ag) * t);
  var b2 = Math.round(ab + (bb - ab) * t);
  return 'rgb(' + r + ',' + g + ',' + b2 + ')';
}

/**
 * Apply gradient fills to all active map regions.
 */
function applyMapColors(el, countryData, maxVal) {
  Object.keys(countryData).forEach(function(code) {
    var count = countryData[code];
    if (!count) return;
    var ratio = maxVal > 1 ? Math.log(count) / Math.log(maxVal) : 1;
    ratio = Math.max(0, Math.min(1, ratio));
    var color = lerpColor('#dcfce7', '#16a34a', ratio);
    var path = el.querySelector('[data-code="' + code + '"]');
    if (path) path.style.fill = color;
  });
}

/**
 * Initialize the activity world map widget
 */
function initActivityMap(attempt) {
  attempt = attempt || 0;
  var el = document.getElementById('activity-world-map');
  if (!el || el.dataset.init === '1') return;
  if (typeof jsVectorMap === 'undefined') return;

  // On mobile, layout may not be complete yet when htmx:afterSettle fires
  // (e.g. sidebar HTMX swap races with widget load, squeezing the container).
  // Retry with setTimeout — more reliable than rAF on mobile browsers.
  if (el.offsetWidth === 0) {
    if (attempt < 20) {
      setTimeout(function() { initActivityMap(attempt + 1); }, 50);
    }
    return;
  }

  var countryData = JSON.parse(el.dataset.countries || '{}');
  var maxVal = parseInt(el.dataset.max, 10) || 1;

  el.dataset.init = '1';

  el._mapCountryData = countryData;
  el._mapMaxVal = maxVal;

  el._mapObject = new jsVectorMap({
    selector: '#activity-world-map',
    map: 'world',
    backgroundColor: 'transparent',
    zoomButtons: false,
    zoomOnScroll: false,
    showTooltip: true,
    regionStyle: {
      initial: {
        fill: '#e5e7eb',
        stroke: '#d1d5db',
        strokeWidth: 0.5,
      },
      hover: {
        fillOpacity: 0.8,
        cursor: 'pointer',
      },
    },
    onRegionTooltipShow: function(_event, tooltip, code) {
      var count = countryData[code] || 0;
      var name = tooltip.text();
      tooltip.text(name + ': ' + count.toLocaleString() + ' requests');
    },
    onLoaded: function() {
      // Apply gradient fills directly to SVG paths — bypasses jsvectormap's
      // series scale which mishandles large value ranges.
      applyMapColors(el, countryData, maxVal);
      // On mobile the sidebar HTMX swap may widen the container shortly after
      // init. Force a resize + recolor after a brief delay to catch this.
      setTimeout(function() {
        if (el._mapObject) {
          el._mapObject.updateSize();
          applyMapColors(el, el._mapCountryData, el._mapMaxVal);
        }
      }, 300);
    },
  });

  // Use ResizeObserver to detect container size changes and reapply colors
  // after updateSize() redraws the SVG paths.
  if (typeof ResizeObserver !== 'undefined') {
    var resizeTimer;
    el._mapResizeObserver = new ResizeObserver(function() {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function() {
        if (el._mapObject) {
          el._mapObject.updateSize();
          applyMapColors(el, el._mapCountryData, el._mapMaxVal);
        }
      }, 100);
    });
    el._mapResizeObserver.observe(el);
  }
}

// Initialize on HTMX content swaps (covers widget load and filter changes)
document.addEventListener('htmx:afterSettle', function() {
  initActivityMap();
});

// Initialize on regular page load (non-HTMX)
document.addEventListener('DOMContentLoaded', function() {
  initActivityMap();
});
