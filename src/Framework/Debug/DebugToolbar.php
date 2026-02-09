<?php

namespace Echo\Framework\Debug;

/**
 * Debug Toolbar - Visual debugging interface with HTMX request tracking
 * Uses server-side storage for full profiler data to avoid header size limits
 */
class DebugToolbar
{
    /**
     * Get debug data as headers for HTMX requests
     * Stores full data server-side, returns only request ID in header
     */
    public static function getDebugHeaders(): array
    {
        if (!config('app.debug') || !config('debug.toolbar_enabled')) {
            return [];
        }

        $profiler = Profiler::getInstance();
        if (!$profiler->isEnabled()) {
            return [];
        }

        // Generate unique request ID (use dash, underscore not allowed in route pattern)
        $requestId = 'prof-' . str_replace('.', '-', uniqid('', true));

        // Get full profiler data
        $data = $profiler->getSummary();
        $request = $data['request'] ?? [];
        $queries = $data['queries'] ?? [];

        // Build complete profile data
        $profile = [
            'id' => $requestId,
            'timestamp' => date('H:i:s'),
            'url' => $_SERVER['REQUEST_URI'] ?? '/',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'time_ms' => round($request['total_time_ms'] ?? 0, 2),
            'memory' => $request['memory_usage_formatted'] ?? '0 B',
            'memory_bytes' => $request['memory_usage'] ?? 0,
            'peak_memory' => $request['peak_memory_formatted'] ?? '0 B',
            'query_count' => $queries['summary']['count'] ?? 0,
            'query_time_ms' => round($queries['summary']['total_time_ms'] ?? 0, 2),
            'slow_count' => $queries['summary']['slow_count'] ?? 0,
            'queries' => $queries['list'] ?? [],
            'sections' => $request['sections'] ?? [],
            'timeline' => $data['timeline'] ?? [],
        ];

        // Store full profile data server-side
        $storage = new ProfilerStorage();
        $storage->store($requestId, $profile);

        // Return minimal headers - just the request ID
        return [
            'X-Echo-Debug-Id' => $requestId,
            'X-Echo-Debug-Time' => round($request['total_time_ms'] ?? 0, 2),
            'X-Echo-Debug-Memory' => $request['memory_usage_formatted'] ?? '0 B',
            'X-Echo-Debug-Queries' => $queries['summary']['count'] ?? 0,
        ];
    }

    /**
     * Render the debug toolbar HTML
     */
    public static function render(): string
    {
        if (!config('app.debug') || !config('debug.toolbar_enabled')) {
            return '';
        }

        $profiler = Profiler::getInstance();
        if (!$profiler->isEnabled()) {
            return '';
        }

        // Generate request ID and store profile (use dash, underscore not allowed in route pattern)
        $requestId = 'prof-' . str_replace('.', '-', uniqid('', true));
        $data = $profiler->getSummary();
        $request = $data['request'] ?? [];
        $queries = $data['queries'] ?? [];

        $profile = [
            'id' => $requestId,
            'timestamp' => date('H:i:s'),
            'url' => $_SERVER['REQUEST_URI'] ?? '/',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'time_ms' => round($request['total_time_ms'] ?? 0, 2),
            'memory' => $request['memory_usage_formatted'] ?? '0 B',
            'memory_bytes' => $request['memory_usage'] ?? 0,
            'peak_memory' => $request['peak_memory_formatted'] ?? '0 B',
            'query_count' => $queries['summary']['count'] ?? 0,
            'query_time_ms' => round($queries['summary']['total_time_ms'] ?? 0, 2),
            'slow_count' => $queries['summary']['slow_count'] ?? 0,
            'queries' => $queries['list'] ?? [],
            'sections' => $request['sections'] ?? [],
            'timeline' => $data['timeline'] ?? [],
            'isInitial' => true,
        ];

        // Store the initial page load profile
        $storage = new ProfilerStorage();
        $storage->store($requestId, $profile);

        return self::renderHtml($profile);
    }

    /**
     * Render the toolbar HTML with embedded styles and scripts
     */
    private static function renderHtml(array $profile): string
    {
        $totalTime = $profile['time_ms'];
        $memory = $profile['memory'];
        $queryCount = $profile['query_count'];
        $queryTime = $profile['query_time_ms'];
        $slowThreshold = config('debug.slow_query_threshold') ?? 100;

        // JSON encode the initial profile for JavaScript
        $initialProfile = json_encode($profile);

        return <<<HTML
<!-- Echo Debug Toolbar -->
<style>
.echo-debug-toolbar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #1a1a2e;
    color: #eee;
    font-size: 12px;
    z-index: 999999;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.3);
}
.echo-debug-bar {
    display: flex;
    align-items: center;
    padding: 8px 16px;
    gap: 16px;
    border-bottom: 1px solid #333;
}
.echo-debug-bar-item {
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
    transition: background 0.2s;
}
.echo-debug-bar-item:hover {
    background: rgba(255,255,255,0.1);
}
.echo-debug-bar-item.active {
    background: #16213e;
}
.echo-debug-icon {
    font-size: 14px;
}
.echo-debug-label {
    color: #888;
}
.echo-debug-value {
    color: #4ecca3;
    font-weight: 600;
}
.echo-debug-badge {
    background: #4ecca3;
    color: #1a1a2e;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 600;
}
.echo-debug-badge-warning {
    background: #f39c12;
}
.echo-debug-badge-info {
    background: #3498db;
}
.echo-debug-panel {
    display: none;
    max-height: 350px;
    overflow-y: auto;
    background: #16213e;
    padding: 16px;
}
.echo-debug-panel.active {
    display: block;
}
.echo-debug-panel h4 {
    margin: 0 0 12px 0;
    color: #4ecca3;
    font-size: 14px;
}
.echo-debug-table {
    width: 100%;
    border-collapse: collapse;
}
.echo-debug-table th,
.echo-debug-table td {
    text-align: left;
    padding: 8px;
    border-bottom: 1px solid #333;
}
.echo-debug-table th {
    color: #888;
    font-weight: 500;
}
.echo-debug-table tr:hover {
    background: rgba(255,255,255,0.05);
}
.echo-debug-sql {
    font-family: monospace;
    font-size: 11px;
    color: #fff;
    word-break: break-all;
    max-width: 600px;
}
.echo-debug-params {
    color: #888;
    font-size: 10px;
    margin-top: 4px;
}
.echo-debug-time {
    color: #4ecca3;
    white-space: nowrap;
}
.echo-debug-time.slow {
    color: #f39c12;
}
.echo-debug-timeline-bar {
    height: 20px;
    background: #333;
    border-radius: 3px;
    position: relative;
    margin: 4px 0;
}
.echo-debug-timeline-segment {
    position: absolute;
    height: 100%;
    border-radius: 3px;
    background: #4ecca3;
    opacity: 0.8;
}
.echo-debug-toggle {
    margin-left: auto;
    cursor: pointer;
    padding: 4px 12px;
    background: #333;
    border-radius: 4px;
    transition: background 0.2s;
}
.echo-debug-toggle:hover {
    background: #444;
}
.echo-debug-close {
    cursor: pointer;
    padding: 4px 8px;
}
.echo-debug-request-list {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid #333;
}
.echo-debug-request-item {
    padding: 4px 8px;
    background: #333;
    border-radius: 4px;
    cursor: pointer;
    font-size: 11px;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: background 0.2s;
}
.echo-debug-request-item:hover {
    background: #444;
}
.echo-debug-request-item.active {
    background: #4ecca3;
    color: #1a1a2e;
}
.echo-debug-request-item.initial {
    border: 1px solid #4ecca3;
}
.echo-debug-request-item.loading {
    opacity: 0.6;
}
.echo-debug-request-method {
    font-weight: 600;
    font-size: 10px;
}
.echo-debug-request-url {
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.echo-debug-htmx-indicator {
    display: none;
    color: #3498db;
    animation: echo-debug-pulse 1s infinite;
}
.echo-debug-htmx-indicator.active {
    display: inline;
}
@keyframes echo-debug-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
.echo-debug-clear-btn {
    padding: 4px 8px;
    background: #e74c3c;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 11px;
    margin-left: 8px;
}
.echo-debug-clear-btn:hover {
    background: #c0392b;
}
.echo-debug-loading {
    color: #888;
    font-style: italic;
}
.echo-debug-backtrace {
    font-size: 10px;
    color: #666;
    margin-top: 4px;
}
.echo-debug-backtrace-item {
    margin-left: 10px;
}
</style>

<div class="echo-debug-toolbar" id="echo-debug-toolbar">
    <div class="echo-debug-bar">
        <div class="echo-debug-bar-item" data-panel="history">
            <span class="echo-debug-icon">&#128220;</span>
            <span class="echo-debug-label">Requests</span>
            <span class="echo-debug-value" id="echo-debug-request-count">1</span>
            <span class="echo-debug-htmx-indicator" id="echo-debug-htmx-indicator">&#9679;</span>
        </div>
        <div class="echo-debug-bar-item" data-panel="request">
            <span class="echo-debug-icon">&#9201;</span>
            <span class="echo-debug-label">Time</span>
            <span class="echo-debug-value" id="echo-debug-time">{$totalTime}ms</span>
        </div>
        <div class="echo-debug-bar-item" data-panel="memory">
            <span class="echo-debug-icon">&#128190;</span>
            <span class="echo-debug-label">Memory</span>
            <span class="echo-debug-value" id="echo-debug-memory">{$memory}</span>
        </div>
        <div class="echo-debug-bar-item" data-panel="queries">
            <span class="echo-debug-icon">&#128451;</span>
            <span class="echo-debug-label">Queries</span>
            <span class="echo-debug-value" id="echo-debug-query-count">{$queryCount}</span>
            <span class="echo-debug-value" id="echo-debug-query-time">({$queryTime}ms)</span>
            <span class="echo-debug-badge echo-debug-badge-warning" id="echo-debug-slow-badge" style="display: none;"></span>
        </div>
        <div class="echo-debug-bar-item" data-panel="timeline">
            <span class="echo-debug-icon">&#9881;</span>
            <span class="echo-debug-label">Timeline</span>
        </div>
        <div class="echo-debug-toggle" id="echo-debug-toggle">&#9660;</div>
        <div class="echo-debug-close" id="echo-debug-close">&#10005;</div>
    </div>

    <div class="echo-debug-panel" id="panel-history">
        <h4>Request History <button class="echo-debug-clear-btn" id="echo-debug-clear-history">Clear</button></h4>
        <div class="echo-debug-request-list" id="echo-debug-request-list"></div>
        <div id="echo-debug-selected-request-info">
            <p style="color: #888;">Select a request above to view details</p>
        </div>
    </div>

    <div class="echo-debug-panel" id="panel-request">
        <h4>Request Details</h4>
        <table class="echo-debug-table">
            <tr><th>Metric</th><th>Value</th></tr>
            <tr><td>URL</td><td id="echo-debug-detail-url"></td></tr>
            <tr><td>Method</td><td id="echo-debug-detail-method"></td></tr>
            <tr><td>Total Time</td><td class="echo-debug-time" id="echo-debug-detail-time"></td></tr>
            <tr><td>Memory Usage</td><td id="echo-debug-detail-memory"></td></tr>
            <tr><td>Peak Memory</td><td id="echo-debug-detail-peak"></td></tr>
        </table>
    </div>

    <div class="echo-debug-panel" id="panel-memory">
        <h4>Memory Usage</h4>
        <table class="echo-debug-table">
            <tr><th>Metric</th><th>Value</th></tr>
            <tr><td>Current</td><td id="echo-debug-mem-current"></td></tr>
            <tr><td>Peak</td><td id="echo-debug-mem-peak"></td></tr>
        </table>
    </div>

    <div class="echo-debug-panel" id="panel-queries">
        <h4>Database Queries (<span id="echo-debug-queries-title-count">{$queryCount}</span>)</h4>
        <table class="echo-debug-table">
            <thead><tr><th>SQL</th><th>Time</th></tr></thead>
            <tbody id="echo-debug-query-list"></tbody>
        </table>
    </div>

    <div class="echo-debug-panel" id="panel-timeline">
        <h4>Timeline</h4>
        <table class="echo-debug-table">
            <thead><tr><th>Section</th><th>Time</th><th>Calls</th></tr></thead>
            <tbody id="echo-debug-section-list"></tbody>
        </table>
        <h4 style="margin-top: 16px;">Visual Timeline</h4>
        <div id="echo-debug-timeline-visual"></div>
    </div>
</div>

<script>
(function() {
    const toolbar = document.getElementById('echo-debug-toolbar');
    const toggle = document.getElementById('echo-debug-toggle');
    const close = document.getElementById('echo-debug-close');
    const items = toolbar.querySelectorAll('.echo-debug-bar-item');
    const panels = toolbar.querySelectorAll('.echo-debug-panel');
    let expanded = false;
    const slowThreshold = {$slowThreshold};
    const maxRequests = 50;

    // Request history storage
    let requests = [];
    let selectedRequest = null;
    let profileCache = {};

    // Initialize with the page load request
    const initialProfile = {$initialProfile};
    profileCache[initialProfile.id] = initialProfile;
    addRequest({
        id: initialProfile.id,
        timestamp: initialProfile.timestamp,
        url: initialProfile.url,
        method: initialProfile.method,
        time_ms: initialProfile.time_ms,
        memory: initialProfile.memory,
        query_count: initialProfile.query_count,
        query_time_ms: initialProfile.query_time_ms,
        slow_count: initialProfile.slow_count,
        isInitial: true,
        loaded: true
    });
    selectRequest(initialProfile.id);

    // Panel toggle logic
    items.forEach(item => {
        item.addEventListener('click', function() {
            const panelId = 'panel-' + this.dataset.panel;
            const panel = document.getElementById(panelId);
            const wasActive = this.classList.contains('active');
            items.forEach(i => i.classList.remove('active'));
            panels.forEach(p => p.classList.remove('active'));
            if (!wasActive) {
                this.classList.add('active');
                panel.classList.add('active');
                expanded = true;
                toggle.innerHTML = '&#9650;';
            } else {
                expanded = false;
                toggle.innerHTML = '&#9660;';
            }
        });
    });

    toggle.addEventListener('click', function() {
        if (expanded) {
            items.forEach(i => i.classList.remove('active'));
            panels.forEach(p => p.classList.remove('active'));
            expanded = false;
            this.innerHTML = '&#9660;';
        } else {
            items[0].click();
        }
    });

    close.addEventListener('click', function() {
        toolbar.style.display = 'none';
    });

    // Clear history button
    document.getElementById('echo-debug-clear-history').addEventListener('click', function() {
        const initial = requests.find(r => r.isInitial);
        requests = initial ? [initial] : [];
        profileCache = {};
        if (initial && profileCache[initial.id]) {
            profileCache[initial.id] = initialProfile;
        }
        selectedRequest = requests[0] || null;
        renderRequestList();
        if (selectedRequest) {
            updateToolbarStats(selectedRequest);
            loadAndDisplayProfile(selectedRequest.id);
        }
    });

    // Listen for HTMX requests
    document.body.addEventListener('htmx:beforeRequest', function(evt) {
        document.getElementById('echo-debug-htmx-indicator').classList.add('active');
    });

    document.body.addEventListener('htmx:afterRequest', function(evt) {
        document.getElementById('echo-debug-htmx-indicator').classList.remove('active');

        const xhr = evt.detail.xhr;
        if (!xhr) return;

        // Check for debug header with request ID
        const debugId = xhr.getResponseHeader('X-Echo-Debug-Id');
        if (debugId) {
            const requestData = {
                id: debugId,
                timestamp: new Date().toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' }),
                url: evt.detail.pathInfo?.requestPath || evt.detail.elt?.getAttribute('hx-get') || evt.detail.elt?.getAttribute('hx-post') || '?',
                method: evt.detail.verb?.toUpperCase() || 'GET',
                time_ms: parseFloat(xhr.getResponseHeader('X-Echo-Debug-Time')) || 0,
                memory: xhr.getResponseHeader('X-Echo-Debug-Memory') || '0 B',
                query_count: parseInt(xhr.getResponseHeader('X-Echo-Debug-Queries')) || 0,
                query_time_ms: 0,
                slow_count: 0,
                loaded: false
            };
            addRequest(requestData);
            selectRequest(debugId);
        }
    });

    function addRequest(data) {
        // Check if already exists
        if (requests.find(r => r.id === data.id)) return;

        requests.unshift(data);
        if (requests.length > maxRequests) {
            const removed = requests.pop();
            delete profileCache[removed.id];
        }
        renderRequestList();
        document.getElementById('echo-debug-request-count').textContent = requests.length;
    }

    function selectRequest(id) {
        const req = requests.find(r => r.id === id);
        if (!req) return;

        selectedRequest = req;
        renderRequestList();
        updateToolbarStats(req);
        loadAndDisplayProfile(id);
    }

    async function loadAndDisplayProfile(id) {
        // Check cache first
        if (profileCache[id]) {
            updatePanels(profileCache[id]);
            return;
        }

        // Show loading state
        document.getElementById('echo-debug-query-list').innerHTML = '<tr><td colspan="2" class="echo-debug-loading">Loading...</td></tr>';
        document.getElementById('echo-debug-section-list').innerHTML = '<tr><td colspan="3" class="echo-debug-loading">Loading...</td></tr>';

        try {
            const response = await fetch('/_debug/profiler/' + id);
            if (!response.ok) throw new Error('Profile not found');

            const profile = await response.json();
            profileCache[id] = profile;

            // Update request with full data
            const req = requests.find(r => r.id === id);
            if (req) {
                req.query_time_ms = profile.query_time_ms || 0;
                req.slow_count = profile.slow_count || 0;
                req.loaded = true;
                renderRequestList();
            }

            // Display if still selected
            if (selectedRequest && selectedRequest.id === id) {
                updatePanels(profile);
                updateToolbarStats(req || profile);
            }
        } catch (e) {
            console.error('Echo Debug: Failed to load profile', e);
            document.getElementById('echo-debug-query-list').innerHTML = '<tr><td colspan="2" style="color: #e74c3c;">Failed to load profile data</td></tr>';
            document.getElementById('echo-debug-section-list').innerHTML = '<tr><td colspan="3" style="color: #e74c3c;">Failed to load profile data</td></tr>';
        }
    }

    function renderRequestList() {
        const list = document.getElementById('echo-debug-request-list');
        list.innerHTML = requests.map(r => {
            const isActive = selectedRequest && selectedRequest.id === r.id;
            const isInitial = r.isInitial;
            const classes = ['echo-debug-request-item'];
            if (isActive) classes.push('active');
            if (isInitial) classes.push('initial');
            if (!r.loaded) classes.push('loading');

            const url = r.url.length > 30 ? '...' + r.url.slice(-27) : r.url;
            return '<div class="' + classes.join(' ') + '" data-id="' + r.id + '">' +
                '<span class="echo-debug-request-method">' + r.method + '</span>' +
                '<span class="echo-debug-request-url" title="' + escapeHtml(r.url) + '">' + escapeHtml(url) + '</span>' +
                '<span class="echo-debug-time">' + r.time_ms + 'ms</span>' +
                (r.slow_count > 0 ? '<span class="echo-debug-badge echo-debug-badge-warning">' + r.slow_count + '</span>' : '') +
                '</div>';
        }).join('');

        // Add click handlers
        list.querySelectorAll('.echo-debug-request-item').forEach(item => {
            item.addEventListener('click', function() {
                selectRequest(this.dataset.id);
            });
        });
    }

    function updateToolbarStats(r) {
        document.getElementById('echo-debug-time').textContent = r.time_ms + 'ms';
        document.getElementById('echo-debug-memory').textContent = r.memory;
        document.getElementById('echo-debug-query-count').textContent = r.query_count;
        document.getElementById('echo-debug-query-time').textContent = '(' + (r.query_time_ms || 0) + 'ms)';

        const slowBadge = document.getElementById('echo-debug-slow-badge');
        if (r.slow_count > 0) {
            slowBadge.textContent = r.slow_count + ' slow';
            slowBadge.style.display = 'inline';
        } else {
            slowBadge.style.display = 'none';
        }
    }

    function updatePanels(r) {
        // Request details
        document.getElementById('echo-debug-detail-url').textContent = r.url || '';
        document.getElementById('echo-debug-detail-method').textContent = r.method || '';
        document.getElementById('echo-debug-detail-time').textContent = (r.time_ms || 0) + ' ms';
        document.getElementById('echo-debug-detail-memory').textContent = r.memory || '0 B';
        document.getElementById('echo-debug-detail-peak').textContent = r.peak_memory || '0 B';

        // Memory
        document.getElementById('echo-debug-mem-current').textContent = r.memory || '0 B';
        document.getElementById('echo-debug-mem-peak').textContent = r.peak_memory || '0 B';

        // Queries
        document.getElementById('echo-debug-queries-title-count').textContent = r.query_count || 0;
        const queryList = document.getElementById('echo-debug-query-list');
        const queries = r.queries || [];
        if (queries.length > 0) {
            queryList.innerHTML = queries.map(q => {
                const isSlow = q.duration > slowThreshold;
                const params = q.params && Object.keys(q.params).length > 0
                    ? '<div class="echo-debug-params">' + escapeHtml(JSON.stringify(q.params)) + '</div>'
                    : '';
                const backtrace = q.backtrace && q.backtrace.length > 0
                    ? '<div class="echo-debug-backtrace">' + q.backtrace.map(b =>
                        '<div class="echo-debug-backtrace-item">' + escapeHtml((b.file || '?') + ':' + (b.line || '?')) + '</div>'
                      ).join('') + '</div>'
                    : '';
                return '<tr>' +
                    '<td><div class="echo-debug-sql">' + escapeHtml(q.sql) + '</div>' + params + backtrace + '</td>' +
                    '<td class="echo-debug-time ' + (isSlow ? 'slow' : '') + '">' + (q.duration || 0).toFixed(2) + ' ms</td>' +
                    '</tr>';
            }).join('');
        } else {
            queryList.innerHTML = '<tr><td colspan="2">No queries executed</td></tr>';
        }

        // Sections/Timeline
        const sectionList = document.getElementById('echo-debug-section-list');
        const sections = r.sections || {};
        const sectionNames = Object.keys(sections);
        if (sectionNames.length > 0) {
            sectionList.innerHTML = sectionNames.map(name => {
                const s = sections[name];
                return '<tr>' +
                    '<td>' + escapeHtml(name) + '</td>' +
                    '<td class="echo-debug-time">' + (s.total_time_ms || 0).toFixed(2) + ' ms</td>' +
                    '<td>' + (s.calls || 0) + '</td>' +
                    '</tr>';
            }).join('');
        } else {
            sectionList.innerHTML = '<tr><td colspan="3">No sections tracked</td></tr>';
        }

        // Visual timeline
        const timelineVisual = document.getElementById('echo-debug-timeline-visual');
        const timeline = r.timeline || [];
        if (timeline.length > 0 && r.time_ms > 0) {
            let html = '<div class="echo-debug-timeline-bar">';
            timeline.forEach(t => {
                const left = ((t.start_offset_ms || 0) / r.time_ms) * 100;
                const width = Math.max(((t.duration_ms || 0) / r.time_ms) * 100, 1);
                html += '<div class="echo-debug-timeline-segment" ' +
                    'style="left: ' + left + '%; width: ' + width + '%;" ' +
                    'title="' + escapeHtml(t.name || '') + ': ' + (t.duration_ms || 0) + 'ms"></div>';
            });
            html += '</div>';
            timelineVisual.innerHTML = html;
        } else {
            timelineVisual.innerHTML = '<div class="echo-debug-timeline-bar"></div>';
        }

        // Selected request info
        document.getElementById('echo-debug-selected-request-info').innerHTML =
            '<p><strong>' + (r.method || '') + '</strong> ' + escapeHtml(r.url || '') + ' at ' + (r.timestamp || '') + '</p>' +
            '<p>Time: ' + (r.time_ms || 0) + 'ms | Memory: ' + (r.memory || '0 B') + ' | Queries: ' + (r.query_count || 0) + '</p>';
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();
</script>
<!-- End Echo Debug Toolbar -->
HTML;
    }
}
