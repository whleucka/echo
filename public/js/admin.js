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
 * Initialize the activity world map widget
 */
function initActivityMap() {
  var el = document.getElementById('activity-world-map');
  if (!el || el.dataset.init === '1') return;
  if (typeof jsVectorMap === 'undefined') return;

  var countryData = JSON.parse(el.dataset.countries || '{}');
  var maxVal = parseInt(el.dataset.max, 10) || 1;

  el.dataset.init = '1';

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
    onRegionTooltipShow: function(event, tooltip, code) {
      var count = countryData[code] || 0;
      var name = tooltip.text();
      tooltip.text(name + ': ' + count.toLocaleString() + ' requests');
    },
    onLoaded: function() {
      // Apply gradient fills directly to SVG paths — bypasses jsvectormap's
      // series scale which mishandles large value ranges.
      Object.keys(countryData).forEach(function(code) {
        var count = countryData[code];
        if (!count) return;
        var ratio = maxVal > 1 ? (count - 1) / (maxVal - 1) : 1;
        ratio = Math.max(0, Math.min(1, ratio));
        var color = lerpColor('#dcfce7', '#16a34a', ratio);
        var path = el.querySelector('[data-code="' + code + '"]');
        if (path) path.style.fill = color;
      });
    },
  });
}

// Initialize on HTMX content swaps (covers widget load and filter changes)
document.addEventListener('htmx:afterSettle', function() {
  initActivityMap();
});

// Initialize on regular page load (non-HTMX)
document.addEventListener('DOMContentLoaded', function() {
  initActivityMap();
});

// Resize the map when the window size changes
window.addEventListener('resize', function() {
  var el = document.getElementById('activity-world-map');
  if (el && el._mapObject) {
    el._mapObject.updateSize();
  }
});
