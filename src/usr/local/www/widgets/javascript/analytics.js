// Analytics widget JavaScript
(function() {
    'use strict';

    // Initialize the widget
    function initAnalyticsWidget() {
        // Get the widget container
        const widgetContainer = document.querySelector('.analytics-widget');
        if (!widgetContainer) return;

        // Create chart canvas
        const chartCanvas = document.createElement('canvas');
        chartCanvas.id = 'analyticsChart';
        widgetContainer.appendChild(chartCanvas);

        // Initialize Chart.js
        const ctx = chartCanvas.getContext('2d');
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Network Traffic',
                    data: [],
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Update metrics every 5 seconds
        setInterval(() => updateMetrics(chart), 5000);
    }

    // Update metrics
    function updateMetrics(chart) {
        // Simulate data for now - replace with actual API call
        const newData = Math.random() * 1000;
        const timestamp = new Date().toLocaleTimeString();

        // Add new data point
        chart.data.labels.push(timestamp);
        chart.data.datasets[0].data.push(newData);

        // Keep only last 30 data points
        if (chart.data.labels.length > 30) {
            chart.data.labels.shift();
            chart.data.datasets[0].data.shift();
        }

        // Update chart
        chart.update();

        // Update statistical cards
        updateStats(newData);
    }

    // Update statistical cards
    function updateStats(newData) {
        const statsContainer = document.querySelector('.analytics-stats');
        if (!statsContainer) return;

        // Update total traffic
        const totalTraffic = statsContainer.querySelector('.total-traffic');
        if (totalTraffic) {
            totalTraffic.textContent = formatBytes(newData);
        }

        // Update average traffic
        const avgTraffic = statsContainer.querySelector('.avg-traffic');
        if (avgTraffic) {
            const currentAvg = parseFloat(avgTraffic.dataset.value || 0);
            const newAvg = (currentAvg + newData) / 2;
            avgTraffic.dataset.value = newAvg;
            avgTraffic.textContent = formatBytes(newAvg);
        }

        // Update peak traffic
        const peakTraffic = statsContainer.querySelector('.peak-traffic');
        if (peakTraffic) {
            const currentPeak = parseFloat(peakTraffic.dataset.value || 0);
            if (newData > currentPeak) {
                peakTraffic.dataset.value = newData;
                peakTraffic.textContent = formatBytes(newData);
            }
        }
    }

    // Format bytes to human readable format
    function formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Initialize widget when DOM is loaded
    document.addEventListener('DOMContentLoaded', initAnalyticsWidget);
})(); 