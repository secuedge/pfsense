<?php
/*
 * analytics.widget.php
 *
 * Network Analytics Dashboard Widget
 */

// Analytics Widget
$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");

// Get widget configuration
$widget_config = $user_settings['widgets']['analytics-0-config'] ?? [];

// Initialize metrics
$metrics = [
    'active_users' => 0,
    'bandwidth' => 0,
    'threats' => 0,
    'connections' => 0
];

// Get real-time metrics
$metrics['active_users'] = get_active_users();
$metrics['bandwidth'] = get_bandwidth_usage();
$metrics['threats'] = get_threat_count();
$metrics['connections'] = get_active_connections();

// Handle AJAX update requests
if ($_GET['action'] == 'update') {
    header('Content-Type: application/json');
    echo json_encode([
        'active_users' => $metrics['active_users'],
        'active_users_trend' => get_trend_data('active_users'),
        'bandwidth' => $metrics['bandwidth'],
        'bandwidth_trend' => get_trend_data('bandwidth'),
        'threats' => $metrics['threats'],
        'threats_trend' => get_trend_data('threats'),
        'connections' => $metrics['connections'],
        'connections_trend' => get_trend_data('connections')
    ]);
    exit;
}

// Function to get active users count
function get_active_users() {
    $users = 0;
    // Get DHCP leases directly from the leases file
    $leases_file = "/var/dhcpd/var/db/dhcpd.leases";
    if (file_exists($leases_file)) {
        $leases_content = file_get_contents($leases_file);
        // Count active leases (those that are not expired)
        $users = substr_count($leases_content, "binding state active");
    }
    return $users;
}

// Function to get bandwidth usage
function get_bandwidth_usage() {
    $bandwidth = 0;
    // Get interface statistics using pfSense's interfaces.inc
    require_once("/etc/inc/interfaces.inc");
    $interfaces = get_configured_interface_with_descr();
    foreach ($interfaces as $ifname => $ifdescr) {
        $ifinfo = get_interface_info($ifname);
        if (is_array($ifinfo)) {
            // Convert to MB for better readability
            $bandwidth += round(($ifinfo['inbytes'] + $ifinfo['outbytes']) / (1024 * 1024), 2);
        }
    }
    return $bandwidth;
}

// Function to get threat count
function get_threat_count() {
    $threats = 0;
    // Get firewall logs using pfSense's filter.inc
    require_once("/etc/inc/filter.inc");
    $filterlogfile = "/var/log/filter.log";
    if (file_exists($filterlogfile)) {
        // Count blocked connections in the last hour
        $cmd = "/usr/local/sbin/clog " . escapeshellarg($filterlogfile) . " | grep 'block' | grep -v '127.0.0.1' | grep -v '::1' | wc -l";
        $threats = intval(trim(shell_exec($cmd)));
    }
    return $threats;
}

// Function to get active connections
function get_active_connections() {
    $connections = 0;
    // Get active connections using pfSense's pfctl
    $cmd = "/sbin/pfctl -s states | grep -v '^$' | grep -v '^[0-9]' | wc -l";
    $connections = intval(trim(shell_exec($cmd)));
    return $connections;
}

// Function to get trend data
function get_trend_data($metric) {
    static $last_values = [];
    $current_value = $GLOBALS['metrics'][$metric];
    
    if (!isset($last_values[$metric])) {
        $last_values[$metric] = $current_value;
        return [
            'value' => '0%',
            'increase' => false
        ];
    }
    
    $last_value = $last_values[$metric];
    $difference = $current_value - $last_value;
    $percentage = $last_value > 0 ? round(($difference / $last_value) * 100) : 0;
    
    $last_values[$metric] = $current_value;
    
    return [
        'value' => abs($percentage) . '%',
        'increase' => $difference > 0
    ];
}
?>
<link rel="stylesheet" href="/vendor/font-awesome/css/all.min.css">
<link rel="stylesheet" href="/css/analytics-widget-modern.css">
<div class="row">
  <div class="col-md-3">
    <div class="card kpi-card">
      <div class="card-body">
        <h6>Active Users</h6>
        <h2 id="active-users"><?=$metrics['active_users']?></h2>
        <span class="trend" id="active-users-trend"></span>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card kpi-card">
      <div class="card-body">
        <h6>Bandwidth (MB)</h6>
        <h2 id="bandwidth"><?=$metrics['bandwidth']?></h2>
        <span class="trend" id="bandwidth-trend"></span>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card kpi-card">
      <div class="card-body">
        <h6>Threats</h6>
        <h2 id="threats"><?=$metrics['threats']?></h2>
        <span class="trend" id="threats-trend"></span>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card kpi-card">
      <div class="card-body">
        <h6>Connections</h6>
        <h2 id="connections"><?=$metrics['connections']?></h2>
        <span class="trend" id="connections-trend"></span>
      </div>
    </div>
  </div>
</div>
<div class="row">
  <div class="col-md-12">
    <div class="card">
      <div class="card-body">
        <canvas id="analytics-trend-chart" height="80"></canvas>
      </div>
    </div>
  </div>
</div>
<script src="/vendor/chart.js/chart.min.js"></script>
<script>
// Placeholder for AJAX and Chart.js integration
// You should implement AJAX polling to update the KPIs and chart data
</script>

<div id="analytics_widget" class="widget-card">
    <div class="widget-header">
        <div class="widget-title">
            <i class="fas fa-chart-line"></i>
            <span>Network Analytics</span>
        </div>
        <div class="widget-controls">
            <button class="widget-refresh" onclick="refreshAnalytics()">
                <i class="fas fa-sync-alt"></i>
            </button>
            <button class="widget-expand" onclick="expandWidget('analytics')">
                <i class="fas fa-expand-alt"></i>
            </button>
        </div>
    </div>

    <div class="widget-content">
        <div class="analytics-grid">
            <!-- Traffic Overview -->
            <div class="analytics-card">
                <div class="metric-header">
                    <span>Traffic Overview</span>
                    <span class="metric-period">Last 24h</span>
                </div>
                <div class="metric-value">
                    <span id="total_traffic">...</span>
                    <span class="metric-trend positive">
                        <i class="fas fa-arrow-up"></i>
                        <span id="traffic_trend">0%</span>
                    </span>
                </div>
                <canvas id="traffic_chart"></canvas>
            </div>

            <!-- Active Connections -->
            <div class="analytics-card">
                <div class="metric-header">
                    <span>Active Connections</span>
                    <span class="metric-period">Real-time</span>
                </div>
                <div class="metric-value">
                    <span id="active_connections">...</span>
                    <div class="connection-types" id="connection_breakdown"></div>
                </div>
            </div>

            <!-- Security Events -->
            <div class="analytics-card">
                <div class="metric-header">
                    <span>Security Events</span>
                    <span class="metric-period">Today</span>
                </div>
                <div class="metric-value">
                    <span id="security_events">...</span>
                    <div class="security-breakdown" id="security_breakdown"></div>
                </div>
            </div>

            <!-- Performance -->
            <div class="analytics-card">
                <div class="metric-header">
                    <span>System Performance</span>
                    <span class="metric-period">Current</span>
                </div>
                <div class="metric-value">
                    <div class="performance-grid">
                        <div class="performance-item">
                            <span>CPU</span>
                            <span id="cpu_usage">...</span>
                        </div>
                        <div class="performance-item">
                            <span>Memory</span>
                            <span id="memory_usage">...</span>
                        </div>
                        <div class="performance-item">
                            <span>Load</span>
                            <span id="system_load">...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Widget Styles -->
<style>
.widget-card {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-bottom: 1rem;
    overflow: hidden;
}

.widget-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.widget-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
}

.widget-controls {
    display: flex;
    gap: 0.5rem;
}

.widget-controls button {
    background: transparent;
    border: none;
    color: var(--text-muted);
    padding: 0.25rem;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.widget-controls button:hover {
    color: var(--text-color);
    background: var(--hover-bg);
}

.analytics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    padding: 1rem;
}

.analytics-card {
    background: var(--secondary-bg);
    border-radius: 8px;
    padding: 1rem;
    border: 1px solid var(--border-color);
}

.metric-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.metric-period {
    font-size: 0.875rem;
    color: var(--text-muted);
}

.metric-value {
    font-size: 1.5rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.metric-trend {
    font-size: 0.875rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.metric-trend.positive {
    color: var(--success-color);
    background: rgba(34, 197, 94, 0.1);
}

.metric-trend.negative {
    color: var(--danger-color);
    background: rgba(239, 68, 68, 0.1);
}

.performance-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    width: 100%;
}

.performance-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.performance-item span:first-child {
    font-size: 0.875rem;
    color: var(--text-muted);
}
</style>

<!-- Widget Scripts -->
<script>
// Initialize charts and data
document.addEventListener('DOMContentLoaded', function() {
    initializeAnalytics();
    refreshAnalytics();
    // Update every 30 seconds
    setInterval(refreshAnalytics, 30000);
});

function initializeAnalytics() {
    // Initialize traffic chart
    const ctx = document.getElementById('traffic_chart').getContext('2d');
    window.trafficChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Traffic',
                data: [],
                borderColor: 'rgb(59, 130, 246)',
                tension: 0.4,
                fill: true,
                backgroundColor: 'rgba(59, 130, 246, 0.1)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

function refreshAnalytics() {
    // Fetch new data
    fetch('/api/analytics/data')
        .then(response => response.json())
        .then(data => updateWidgetData(data))
        .catch(error => console.error('Error fetching analytics:', error));
}

function updateWidgetData(data) {
    // Update traffic data
    document.getElementById('total_traffic').textContent = formatBytes(data.totalTraffic);
    document.getElementById('traffic_trend').textContent = data.trafficTrend + '%';

    // Update connections
    document.getElementById('active_connections').textContent = data.activeConnections;

    // Update security events
    document.getElementById('security_events').textContent = data.securityEvents;

    // Update performance metrics
    document.getElementById('cpu_usage').textContent = data.cpuUsage + '%';
    document.getElementById('memory_usage').textContent = data.memoryUsage + '%';
    document.getElementById('system_load').textContent = data.systemLoad;

    // Update chart
    updateTrafficChart(data.trafficHistory);
}

function updateTrafficChart(history) {
    window.trafficChart.data.labels = history.map(h => h.time);
    window.trafficChart.data.datasets[0].data = history.map(h => h.value);
    window.trafficChart.update();
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function expandWidget(widgetId) {
    // Implement widget expansion logic
}
</script> 