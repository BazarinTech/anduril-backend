/*
-------------------------------------------------------------------------
* Template Name    : Sliced Pro - Tailwind CSS Admin & Dashboard Template   *
* Author           : SRBThemes                                              *
* Version          : 1.0.0                                                  *
* Created          : October 2024                                           *
* File Description : apexchart init Js File                                 *
*------------------------------------------------------------------------
*
* Both charts shipped with the template's demo data -- an eleven-point series
* dated 2003 and a flat 44/55 donut. They now read window.dashboardData, which
* admin/dashboard.php emits from the database just above this file's <script>
* tag. The fallbacks below are zeroes rather than the old demo numbers: if the
* data ever fails to arrive, an empty chart says so, where plausible-looking
* invented figures would not.
*/

(function () {
    var data = window.dashboardData || {};

    var labels      = data.labels      || [];
    var signups     = data.signups     || [];
    var active      = data.active      || [];
    var deposits    = Number(data.deposits) || 0;
    var withdrawals = Number(data.withdrawals) || 0;
    var currency    = data.currency || 'Kes';

    var money = function (value) {
        return currency + ' ' + Number(value).toLocaleString(undefined, {
            minimumFractionDigits: 2, maximumFractionDigits: 2,
        });
    };

    /* -- Sign-ups and activity, by month ------------------------------- */

    var activityEl = document.querySelector('#customerActivitiesChart');

    if (activityEl) {
        new ApexCharts(activityEl, {
            series: [
                { name: 'New Sign-ups', type: 'column', data: signups },
                { name: 'Active Users', type: 'area',   data: active },
            ],
            chart: {
                height: 350,
                type: 'line',
                stacked: false,
                toolbar: { show: false },
                fontFamily: 'inherit',
            },
            stroke: { width: [0, 2], curve: 'smooth' },
            plotOptions: { bar: { columnWidth: '50%', borderRadius: 3 } },
            colors: ['#0ea5e9', '#6366f1'],
            fill: {
                type: ['solid', 'gradient'],
                opacity: [1, 0.1],
                gradient: {
                    inverseColors: false,
                    type: 'vertical',
                    opacityFrom: 0.85,
                    opacityTo: 0,
                    stops: [0, 100],
                },
            },
            labels: labels,
            markers: { size: 0 },
            xaxis: {
                type: 'datetime',
                labels: { format: 'MMM yy' },
            },
            yaxis: {
                // Whole people. The default axis invents 0.5 of a user when
                // the counts are small, which they are early on.
                labels: {
                    formatter: function (value) { return Math.round(value); },
                },
                min: 0,
                forceNiceScale: true,
            },
            tooltip: {
                shared: true,
                intersect: false,
                x: { format: 'MMMM yyyy' },
            },
            noData: { text: 'No sign-ups or activity in the last 12 months' },
        }).render();
    }

    /* -- Settled deposits against settled withdrawals ------------------ */

    var salesEl = document.querySelector('#salesChart');

    if (salesEl) {
        new ApexCharts(salesEl, {
            series: [deposits, withdrawals],
            chart: { height: 190, type: 'donut', fontFamily: 'inherit' },
            labels: ['Deposits', 'Withdrawals'],
            // Green for money in, red for money out -- the same reading the
            // status badges use everywhere else in the panel.
            colors: ['#22c55e', '#ef4444'],
            plotOptions: {
                pie: {
                    startAngle: -90,
                    endAngle: 270,
                    // No centre label. A full "Kes 10,000.00" does not fit
                    // inside a 190px donut's hole -- it spills over the ring
                    // and out of the card. The figure is already on the cards
                    // above, and hovering a segment gives the exact amount.
                    donut: { labels: { show: false } },
                },
            },
            dataLabels: { enabled: false },
            stroke: { width: 0 },
            legend: { show: false },
            tooltip: {
                y: { formatter: function (value) { return money(value); } },
            },
            noData: { text: 'No settled transactions yet' },
        }).render();
    }
})();
