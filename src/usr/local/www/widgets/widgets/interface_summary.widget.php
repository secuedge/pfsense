<?php
/*
 * interface_summary.widget.php
 * Dashboard widget for real-time firewall interface summary pie chart
 */
require_once("guiconfig.inc");
require_once("status_logs_common.inc");

// Output the widget HTML
?>
<link rel="stylesheet" href="/vendor/font-awesome/css/all.min.css">
<style>
.interface-summary-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(25,118,210,0.07);
    padding: 1.2rem 1.5rem 1rem 1.5rem;
    margin-bottom: 1.5rem;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}
.interface-summary-header {
    font-size: 1.15rem;
    font-weight: 700;
    color: #1976D2;
    margin-bottom: 0.7rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.interface-summary-header i {
    font-size: 1.2rem;
}
#interface-summary-pie {
    width: 100%;
    min-height: 220px;
    margin-bottom: 0.5rem;
}
.interface-summary-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0.5rem;
}
.interface-summary-table th, .interface-summary-table td {
    padding: 0.35rem 0.5rem;
    font-size: 1rem;
    color: #333;
    text-align: left;
}
.interface-summary-table th {
    color: #1976D2;
    font-weight: 600;
    background: #f5f8fa;
}
</style>
<div class="interface-summary-card">
    <div class="interface-summary-header">
        <i class="fa fa-chart-pie"></i> Interfaces Summary
    </div>
    <div id="interface-summary-pie"></div>
    <table class="interface-summary-table" id="interface-summary-table">
        <thead>
            <tr><th>Interface</th><th>Data points</th></tr>
        </thead>
        <tbody>
            <tr><td colspan="2">Loading...</td></tr>
        </tbody>
    </table>
</div>
<script src="/vendor/d3/d3.min.js"></script>
<script src="/vendor/d3pie/d3pie.min.js"></script>
<script>
function fetchInterfaceSummary() {
    fetch("/widgets/widgets/interface_summary_data.php")
        .then(response => response.json())
        .then(data => {
            renderPieChart(data.chartData);
            renderTable(data.tableData);
        });
}
function renderPieChart(chartData) {
    document.getElementById("interface-summary-pie").innerHTML = "";
    if (!chartData.length) return;
    new d3pie("interface-summary-pie", {
        header: { title: { text: "", fontSize: 18 } },
        size: { canvasHeight: 220, canvasWidth: 340, pieOuterRadius: "80%" },
        data: { content: chartData },
        labels: {
            outer: { pieDistance: 20 },
            inner: { hideWhenLessThanPercentage: 3 },
            mainLabel: { fontSize: 11 },
            percentage: { color: "#fff", decimalPlaces: 0 },
            value: { color: "#adadad", fontSize: 11 },
            lines: { enabled: true },
            truncation: { enabled: true }
        },
        effects: { pullOutSegmentOnClick: { effect: "linear", speed: 400, size: 8 } },
        misc: { gradient: { enabled: true, percentage: 100 } }
    });
}
function renderTable(tableData) {
    const tbody = document.querySelector("#interface-summary-table tbody");
    tbody.innerHTML = "";
    if (!tableData.length) {
        tbody.innerHTML = '<tr><td colspan="2">No data</td></tr>';
        return;
    }
    tableData.forEach(row => {
        const tr = document.createElement("tr");
        tr.innerHTML = `<td>${row.label}</td><td>${row.value}</td>`;
        tbody.appendChild(tr);
    });
}
fetchInterfaceSummary();
setInterval(fetchInterfaceSummary, 10000); // Refresh every 10s
</script> 