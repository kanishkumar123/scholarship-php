<?php
// --- 1. Start Session & Config ---
session_start();
include("../config.php");

// --- 2. Security Check ---
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// --- 3. Set Page Variables ---
$currentPage = 'dashboard';
$pageTitle = "Welcome, " . htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') . "!";
$pageSubtitle = "Analytics & Application Overview";

// --- 4. Include Header ---
include('header.php'); 

// --- 5. Page Data Fetching for Filters ---
$scholarships_query = mysqli_query($conn, "SELECT id, name FROM scholarships ORDER BY name");
$communities_query = mysqli_query($conn, "SELECT DISTINCT community FROM applications WHERE community IS NOT NULL AND community != '' ORDER BY community");
$institutions_query = mysqli_query($conn, "SELECT id, name FROM colleges ORDER BY name");
?>

<style>
    /* (Keep your existing CSS exactly as it is - no changes needed here) */
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }
    .stat-card {
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 20px 25px;
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.07);
        border-color: var(--primary-color);
    }
    .stat-card .stat-value {
        font-size: 2.25rem;
        font-weight: 700;
        color: var(--text-color);
        margin-bottom: 5px;
        min-height: 40px;
    }
    .stat-card .stat-label {
        font-size: 0.95rem;
        color: var(--text-muted);
        font-weight: 500;
    }
    .stat-card i.fas {
        position: absolute;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 3rem;
        color: var(--primary-color);
        opacity: 0.15;
        transition: all 0.3s ease;
    }
    .stat-card:hover i.fas {
        opacity: 0.25;
        transform: translateY(-50%) scale(1.1);
    }

    /* Specific Card Colors */
    #stat_total_scholars:hover { border-color: #007bff; }
    #stat_total_scholars i { color: #007bff; }
    
    #stat_submitted:hover { border-color: #28a745; }
    #stat_submitted i { color: #28a745; }

    #stat_not_applied:hover { border-color: #dc3545; }
    #stat_not_applied i { color: #dc3545; }

    /* Chart & Form Styles */
    .filter-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        padding: 20px;
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        margin-bottom: 25px;
    }
    .filter-group { display: flex; flex-direction: column; }
    .filter-group label { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 8px; }
    .chart-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .chart-box { background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; }
    .chart-box h3 { margin: 0 0 15px 0; font-size: 1.1rem; font-weight: 600; color: var(--text-color); }
    .chart-placeholder { width: 100%; min-height: 350px; }
    .chart-box.full-width { grid-column: 1 / -1; }
    .no-applications, .loading-data { display: flex; align-items: center; justify-content: center; height: 300px; font-size: 1.1rem; color: var(--text-muted); font-weight: 500; }
    .loading-data i.fas { margin-right: 10px; color: var(--primary-color); }
    @media (max-width: 900px) { .chart-grid { grid-template-columns: 1fr; } .chart-box.full-width { grid-column: 1; } }
</style>

<div class="stat-grid">
    <div class="stat-card" id="stat_total_scholars" onclick="window.location.href='scholarship_students_list.php?view=all'">
        <div class="stat-value">...</div>
        <div class="stat-label">Total Eligible Scholars</div>
        <i class="fas fa-users"></i>
    </div>

    <div class="stat-card" id="stat_submitted" onclick="window.location.href='view_applications.php'">
        <div class="stat-value">...</div>
        <div class="stat-label">Applications Submitted</div>
        <i class="fas fa-check-circle"></i>
    </div>

    <div class="stat-card" id="stat_not_applied" onclick="window.location.href='scholarship_students_list.php?view=pending'">
        <div class="stat-value">...</div>
        <div class="stat-label">Not Yet Applied</div>
        <i class="fas fa-exclamation-circle"></i>
    </div>
</div>

<form id="filterForm" class="filter-form" onsubmit="return false;">
    <div class="filter-group">
        <label for="scholarship_id">Scholarship:</label>
        <select id="scholarship_id" name="scholarship_id">
            <option value="">All Scholarships</option>
            <?php mysqli_data_seek($scholarships_query, 0); while ($row = mysqli_fetch_assoc($scholarships_query)) : ?>
                <option value="<?= htmlspecialchars($row['id']) ?>"><?= htmlspecialchars($row['name']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="filter-group">
        <label for="community">Community:</label>
        <select id="community" name="community">
            <option value="">All Communities</option>
            <?php mysqli_data_seek($communities_query, 0); while ($row = mysqli_fetch_assoc($communities_query)) : ?>
                <option value="<?= htmlspecialchars($row['community']) ?>"><?= htmlspecialchars($row['community']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="filter-group">
        <label for="institution_id">College:</label>
        <select id="institution_id" name="institution_id">
            <option value="">All Colleges</option>
            <?php mysqli_data_seek($institutions_query, 0); while ($row = mysqli_fetch_assoc($institutions_query)) : ?>
                <option value="<?= htmlspecialchars($row['id']) ?>"><?= htmlspecialchars($row['name']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>
</form>

<div class="chart-grid">
    <div class="chart-box">
        <h3>Applications by Gender</h3>
        <div id="gender_chart" class="chart-placeholder"></div>
    </div>
    <div class="chart-box">
        <h3>Applications by Community</h3>
        <div id="community_chart" class="chart-placeholder"></div>
    </div>
    <div class="chart-box full-width">
        <h3>Applications by College</h3>
        <div id="institution_chart" class="chart-placeholder"></div>
    </div>
</div>

<?php include('footer.php'); ?>

<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script>
    google.charts.load('current', { 'packages': ['corechart'] });
    google.charts.setOnLoadCallback(initializeDashboard);

    function initializeDashboard() {
        const filterForm = document.getElementById('filterForm');
        if (filterForm) {
            filterForm.addEventListener('change', fetchAndDrawCharts);
        }
        fetchAndDrawCharts();
    }

    function fetchAndDrawCharts() {
        const filterForm = document.getElementById('filterForm');
        if (!filterForm) return; 

        const formData = new FormData(filterForm);
        const queryString = new URLSearchParams(formData).toString();

        document.querySelectorAll('.chart-placeholder').forEach(el => {
            el.innerHTML = '<p class="loading-data"><i class="fas fa-spinner fa-spin"></i> Loading Data...</p>';
        });
        document.querySelectorAll('.stat-value').forEach(el => {
            el.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        });

        fetch(`fetch_dashboard_data.php?${queryString}`)
            .then(response => response.json())
            .then(data => {
                // Update Stats
                document.getElementById('stat_total_scholars').querySelector('.stat-value').textContent = data.stats.total_scholars || 0;
                document.getElementById('stat_submitted').querySelector('.stat-value').textContent = data.stats.submitted || 0;
                document.getElementById('stat_not_applied').querySelector('.stat-value').textContent = data.stats.not_applied || 0;

                // Draw Charts
                drawGenderChart(data.gender || {});
                drawCommunityChart(data.community || {});
                drawInstitutionChart(data.institution || {});
            })
            .catch(error => console.error('Error:', error));
    }

    function getChartOptions() {
        const isDarkMode = document.body.classList.contains('dark-theme');
        const textColor = isDarkMode ? '#f0f8ff' : '#333';
        const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.15)' : '#ccc';
        return {
            backgroundColor: 'transparent',
            legend: { textStyle: { color: textColor, fontSize: 12 }, position: 'bottom' },
            titleTextStyle: { color: textColor, fontSize: 16, bold: false },
            hAxis: { textStyle: { color: textColor }, gridlines: { color: 'transparent' } },
            vAxis: { textStyle: { color: textColor }, gridlines: { color: gridColor }, baselineColor: gridColor },
            chartArea: { left: '15%', top: '10%', width: '80%', height: '75%' },
            colors: ['#00c6ff', '#0072ff', '#80d0c7', '#13547a', '#f5a623', '#f8e71c', '#7ed321']
        };
    }

    function drawPieChart(elementId, chartData, chartTitle) {
        const chartElement = document.getElementById(elementId);
        if (!chartElement) return;
        const data = new google.visualization.DataTable();
        data.addColumn('string', chartTitle);
        data.addColumn('number', 'Count');
        const rows = Object.entries(chartData);
        if (rows.length === 0) {
            chartElement.innerHTML = `<p class="no-applications">No data available.</p>`;
            return;
        }
        data.addRows(rows);
        const options = { ...getChartOptions(), is3D: true, pieSliceTextStyle: { color: 'black' } };
        const chart = new google.visualization.PieChart(chartElement);
        chart.draw(data, options);
    }

    function drawGenderChart(d) { drawPieChart('gender_chart', d, 'Gender'); }
    function drawCommunityChart(d) { drawPieChart('community_chart', d, 'Community'); }

    function drawInstitutionChart(institutionData) {
        const chartElement = document.getElementById('institution_chart');
        if (!chartElement) return;
        const data = new google.visualization.DataTable();
        data.addColumn('string', 'College');
        data.addColumn('number', 'Applications');
        const rows = institutionData.map(item => [item.name, item.count]);
        if (rows.length === 0) {
            chartElement.innerHTML = `<p class="no-applications">No data available.</p>`;
            return;
        }
        data.addRows(rows);
        const baseOptions = getChartOptions();
        const options = {
            ...baseOptions,
            legend: { position: 'none' },
            bar: { groupWidth: '80%' }
        };
        const chart = new google.visualization.BarChart(chartElement);
        chart.draw(data, options);
    }

    window.addEventListener('resize', () => {
        clearTimeout(window.resizedFinished);
        window.resizedFinished = setTimeout(fetchAndDrawCharts, 250);
    });
</script>