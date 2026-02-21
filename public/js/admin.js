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

  new jsVectorMap({
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
    series: {
      regions: [{
        attribute: 'fill',
        scale: ['#dcfce7', '#16a34a'],
        values: countryData,
        min: 0,
        max: maxVal,
      }]
    },
    onRegionTooltipShow: function(event, tooltip, code) {
      var count = countryData[code] || 0;
      var name = tooltip.text();
      tooltip.text(name + ': ' + count.toLocaleString() + ' requests');
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
