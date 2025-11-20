<?php
// ----------------------
// Database Connection
// ----------------------
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "barangay_profiling";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ----------------------
// Helper: Run prepared query with dynamic filters
// ----------------------
function runPreparedQuery($conn, $sql, $types, $params) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    if ($types !== "" && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result;
}

// ----------------------
// Get distinct education levels (for dropdown)
// ----------------------
$educationLevels = [];
$eduRes = $conn->query("
    SELECT DISTINCT educational_attainment AS level
    FROM person
    WHERE educational_attainment IS NOT NULL 
      AND educational_attainment <> ''
    ORDER BY educational_attainment ASC
");
if ($eduRes && $eduRes->num_rows > 0) {
    while ($row = $eduRes->fetch_assoc()) {
        if (!empty($row["level"])) {
            $educationLevels[] = $row["level"];
        }
    }
}

// ----------------------
// AJAX Endpoint: return JSON analytics
// ----------------------
if (isset($_GET["action"]) && $_GET["action"] === "getEducationData") {
    header("Content-Type: application/json");

    // Read filters from GET
    $genderFilter    = isset($_GET["gender"]) ? trim($_GET["gender"]) : "";
    $educationFilter = isset($_GET["education"]) ? trim($_GET["education"]) : "";
    $minAgeFilter    = isset($_GET["minAge"]) && $_GET["minAge"] !== "" ? (int) $_GET["minAge"] : null;
    $maxAgeFilter    = isset($_GET["maxAge"]) && $_GET["maxAge"] !== "" ? (int) $_GET["maxAge"] : null;

    // Build WHERE clause and bind parameters
    $where  = "WHERE 1=1";
    $types  = "";
    $params = [];

    if ($genderFilter !== "" && $genderFilter !== "All") {
        $where  .= " AND gender = ?";
        $types  .= "s";
        $params[] = $genderFilter;
    }

    if ($educationFilter !== "" && $educationFilter !== "All") {
        $where  .= " AND educational_attainment = ?";
        $types  .= "s";
        $params[] = $educationFilter;
    }

    if ($minAgeFilter !== null) {
        $where  .= " AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ?";
        $types  .= "i";
        $params[] = $minAgeFilter;
    }

    if ($maxAgeFilter !== null) {
        $where  .= " AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) <= ?";
        $types  .= "i";
        $params[] = $maxAgeFilter;
    }

    // ----------------------
    // 1) Summary metrics
    // ----------------------
    $summary = [
        "totalPersons"       => 0,
        "totalWithEducation" => 0,
        "avgAge"             => null,
        "mostCommonLevel"    => null
    ];

    $sqlSummary = "
        SELECT 
            COUNT(*) AS totalPersons,
            SUM(CASE WHEN educational_attainment IS NOT NULL AND educational_attainment <> '' THEN 1 ELSE 0 END) AS totalWithEducation,
            AVG(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) AS avgAge
        FROM person
        $where
    ";

    $resSummary = runPreparedQuery($conn, $sqlSummary, $types, $params);
    if ($resSummary && $row = $resSummary->fetch_assoc()) {
        $summary["totalPersons"]       = (int) $row["totalPersons"];
        $summary["totalWithEducation"] = (int) $row["totalWithEducation"];
        $summary["avgAge"]             = $row["avgAge"] !== null ? round($row["avgAge"], 1) : null;
    }

    // ----------------------
    // 2) Educational Attainment Distribution
    // ----------------------
    $attainmentDistribution = [];
    $sqlAttain = "
        SELECT educational_attainment AS level, COUNT(*) AS total
        FROM person
        $where
          AND educational_attainment IS NOT NULL 
          AND educational_attainment <> ''
        GROUP BY educational_attainment
        ORDER BY total DESC
    ";
    $resAttain = runPreparedQuery($conn, $sqlAttain, $types, $params);
    if ($resAttain && $resAttain->num_rows > 0) {
        while ($row = $resAttain->fetch_assoc()) {
            $attainmentDistribution[] = [
                "level" => $row["level"],
                "total" => (int) $row["total"]
            ];
        }
    }
    if (!empty($attainmentDistribution)) {
        $summary["mostCommonLevel"] = $attainmentDistribution[0]["level"];
    }

    // ----------------------
    // 3) Educational Attainment by Gender
    // ----------------------
    $educationByGender = [];
    $sqlGender = "
        SELECT educational_attainment AS level,
               SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END)   AS male,
               SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) AS female,
               SUM(CASE WHEN gender NOT IN ('Male','Female') 
                         AND gender IS NOT NULL 
                         AND gender <> '' THEN 1 ELSE 0 END)       AS other
        FROM person
        $where
          AND educational_attainment IS NOT NULL 
          AND educational_attainment <> ''
        GROUP BY educational_attainment
        ORDER BY level ASC
    ";
    $resGender = runPreparedQuery($conn, $sqlGender, $types, $params);
    if ($resGender && $resGender->num_rows > 0) {
        while ($row = $resGender->fetch_assoc()) {
            $educationByGender[] = [
                "level"  => $row["level"],
                "male"   => (int) $row["male"],
                "female" => (int) $row["female"],
                "other"  => (int) $row["other"]
            ];
        }
    }

    // ----------------------
    // 4) Educational Attainment by Age Group
    // ----------------------
    $educationByAgeGroup = [];
    $sqlAgeGroup = "
        SELECT
            CASE
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 0 AND 12  THEN '0-12'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 13 AND 17 THEN '13-17'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 25 THEN '18-25'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 26 AND 40 THEN '26-40'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 41 AND 60 THEN '41-60'
                ELSE '60+'
            END AS age_group,
            COUNT(*) AS total
        FROM person
        $where
          AND date_of_birth IS NOT NULL
        GROUP BY age_group
        ORDER BY 
            CASE age_group
                WHEN '0-12' THEN 1
                WHEN '13-17' THEN 2
                WHEN '18-25' THEN 3
                WHEN '26-40' THEN 4
                WHEN '41-60' THEN 5
                ELSE 6
            END
    ";
    $resAgeGroup = runPreparedQuery($conn, $sqlAgeGroup, $types, $params);
    if ($resAgeGroup && $resAgeGroup->num_rows > 0) {
        while ($row = $resAgeGroup->fetch_assoc()) {
            $educationByAgeGroup[] = [
                "age_group" => $row["age_group"],
                "total"     => (int) $row["total"]
            ];
        }
    }

    // ----------------------
    // Return JSON
    // ----------------------
    echo json_encode([
        "summary"                => $summary,
        "attainmentDistribution" => $attainmentDistribution,
        "educationByGender"      => $educationByGender,
        "educationByAgeGroup"    => $educationByAgeGroup
    ]);
    $conn->close();
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Education Analysis</title>
    <link rel="stylesheet" href="education.css">
    <!-- Chart.js CDN (load once) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
<div class="container">
    <!-- Sidebar -->
    <nav class="sidebar">
        <h2 class="logo">Dashboard</h2>
        <ul>
            <li><a href="System.php">Home</a></li>
            <li><a href="Persons.php">Persons</a></li>
            <li><a href="Families.php">Families</a></li>
            <li><a href="Households.php">Households</a></li>
            <li><a href="Education.php" class="active">Education Analysis</a></li>
            <li><a href="Economic.php">Economic Analysis</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content">

        <!-- Summary Panel -->
        <div class="glass-panel summary-panel">
            <h1 class="heading">Education Analytics</h1>
            <p class="description">
                Explore educational attainment patterns, gender distribution, and age-based education structure for the barangay.
            </p>

            <div class="summary-grid">
                <div class="summary-card">
                    <span class="summary-label">Total Persons</span>
                    <span class="summary-value" id="sumTotalPersons">0</span>
                </div>
                <div class="summary-card">
                    <span class="summary-label">With Education Data</span>
                    <span class="summary-value" id="sumWithEducation">0</span>
                </div>
                <div class="summary-card">
                    <span class="summary-label">Average Age</span>
                    <span class="summary-value" id="sumAvgAge">–</span>
                </div>
                <div class="summary-card">
                    <span class="summary-label">Most Common Level</span>
                    <span class="summary-value" id="sumTopLevel">–</span>
                </div>
            </div>
        </div>

        <!-- Filters Panel -->
        <div class="glass-panel filters-panel">
            <h2 class="section-title">Filters</h2>

            <!-- No real form submit; just a styled grid with controls -->
            <div class="filters-grid">

                <div class="filter-group">
                    <label class="filter-label">Gender</label>
                    <select id="filterGender" class="filter-input">
                        <option value="All">All</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Educational Level</label>
                    <select id="filterEducation" class="filter-input">
                        <option value="All">All</option>
                        <?php foreach ($educationLevels as $level): ?>
                            <option value="<?= htmlspecialchars($level) ?>">
                                <?= htmlspecialchars($level) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Min Age</label>
                    <input id="filterMinAge" type="number" class="filter-input" placeholder="e.g. 18">
                </div>

                <div class="filter-group">
                    <label class="filter-label">Max Age</label>
                    <input id="filterMaxAge" type="number" class="filter-input" placeholder="e.g. 60">
                </div>

                <div class="filter-actions">
                    <button type="button" id="applyFiltersBtn" class="btn btn-primary">Apply Filters</button>
                    <button type="button" id="resetFiltersBtn" class="btn btn-secondary">Reset</button>
                </div>

            </div>
        </div>

        <!-- Charts Grid -->
        <div class="analytics-grid">

            <!-- Educational Attainment Distribution -->
            <div class="glass-panel chart-panel">
                <h2 class="section-title">Educational Attainment Distribution</h2>
                <canvas id="attainmentChart"></canvas>
            </div>

            <!-- Education by Gender -->
            <div class="glass-panel chart-panel">
                <h2 class="section-title">Educational Attainment by Gender</h2>
                <canvas id="genderChart"></canvas>
            </div>

            <!-- Education by Age Group -->
            <div class="glass-panel chart-panel">
                <h2 class="section-title">Education by Age Group</h2>
                <canvas id="ageGroupChart"></canvas>
            </div>

        </div>
    </div>
</div>

<script>
// ----------------------
// Global chart handles
// ----------------------
let attainmentChart = null;
let genderChart = null;
let ageGroupChart = null;

// ----------------------
// Helper: neon gradient
// ----------------------
function createNeonGradient(ctx) {
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, "rgba(236, 72, 153, 0.9)");  // pink
    gradient.addColorStop(0.5, "rgba(129, 140, 248, 0.8)"); // purple
    gradient.addColorStop(1, "rgba(56, 189, 248, 0.8)");  // cyan
    return gradient;
}

// ----------------------
// Plugin: percentage labels for single-dataset bar charts
// ----------------------
const percentageLabelPlugin = {
    id: "percentageLabel",
    afterDatasetsDraw(chart, args, opts) {
        const pluginOpts = chart.options.plugins && chart.options.plugins.percentageLabel;
        if (!pluginOpts || pluginOpts.enabled === false) return;

        // Only for bar charts with a single dataset
        if (chart.config.type !== "bar" || chart.data.datasets.length !== 1) return;

        const { ctx } = chart;
        const dataset = chart.data.datasets[0];
        const meta = chart.getDatasetMeta(0);
        const data = dataset.data;
        const total = data.reduce((a, b) => a + b, 0) || 1;

        ctx.save();
        ctx.fillStyle = pluginOpts.color || "rgba(255,255,255,0.85)";
        ctx.font = (pluginOpts.font || "bold 14px Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif");
        ctx.textAlign = "center";
        ctx.textBaseline = "bottom";

        meta.data.forEach((bar, index) => {
            const value = data[index];
            if (value === 0 || value === null || value === undefined) return;
            const percent = ((value / total) * 100).toFixed(0) + "%";
            ctx.fillText(percent, bar.x, bar.y - 6);
        });

        ctx.restore();
    }
};

Chart.register(percentageLabelPlugin);

// ----------------------
// Fetch and load analytics data
// ----------------------
function loadEducationData() {
    const gender     = document.getElementById("filterGender").value;
    const minAge     = document.getElementById("filterMinAge").value;
    const maxAge     = document.getElementById("filterMaxAge").value;
    const education  = document.getElementById("filterEducation").value;

    const params = new URLSearchParams();
    params.append("action", "getEducationData");
    if (gender)    params.append("gender", gender);
    if (minAge)    params.append("minAge", minAge);
    if (maxAge)    params.append("maxAge", maxAge);
    if (education) params.append("education", education);

    fetch("Education.php?" + params.toString())
        .then(res => res.json())
        .then(data => {
            updateSummary(data.summary);
            renderAttainmentChart(data.attainmentDistribution);
            renderGenderChart(data.educationByGender);
            renderAgeGroupChart(data.educationByAgeGroup);
        })
        .catch(err => {
            console.error("Error loading education analytics:", err);
        });
}

// ----------------------
// Update summary cards
// ----------------------
function updateSummary(summary) {
    document.getElementById("sumTotalPersons").textContent =
        summary.totalPersons !== null ? summary.totalPersons : 0;

    document.getElementById("sumWithEducation").textContent =
        summary.totalWithEducation !== null ? summary.totalWithEducation : 0;

    document.getElementById("sumAvgAge").textContent =
        summary.avgAge !== null ? summary.avgAge : "–";

    document.getElementById("sumTopLevel").textContent =
        summary.mostCommonLevel !== null ? summary.mostCommonLevel : "–";
}

// ----------------------
// Charts
// ----------------------
function renderAttainmentChart(rows) {
    const labels = rows.map(r => r.level);
    const counts = rows.map(r => r.total);

    const canvas = document.getElementById("attainmentChart");
    const ctx = canvas.getContext("2d");
    const gradient = createNeonGradient(ctx);

    if (attainmentChart) {
        attainmentChart.data.labels = labels;
        attainmentChart.data.datasets[0].data = counts;
        attainmentChart.update();
        return;
    }

    attainmentChart = new Chart(ctx, {
        type: "bar",
        data: {
            labels: labels,
            datasets: [{
                label: "Number of Persons",
                data: counts,
                backgroundColor: gradient,
                borderWidth: 0,
                borderRadius: 14
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                percentageLabel: { enabled: true }
            },
            scales: {
                x: {
                    ticks: { color: "#ffffff" },
                    grid: { display: false }
                },
                y: {
                    ticks: { color: "#a8a8a8" },
                    grid: { color: "rgba(255,255,255,0.05)" }
                }
            }
        }
    });
}

function renderGenderChart(rows) {
    const labels = rows.map(r => r.level);
    const male   = rows.map(r => r.male);
    const female = rows.map(r => r.female);
    const other  = rows.map(r => r.other);

    const canvas = document.getElementById("genderChart");
    const ctx = canvas.getContext("2d");

    const gradientMale   = createNeonGradient(ctx);
    const gradientFemale = createNeonGradient(ctx);
    const gradientOther  = createNeonGradient(ctx);

    if (genderChart) {
        genderChart.data.labels = labels;
        genderChart.data.datasets[0].data = male;
        genderChart.data.datasets[1].data = female;
        genderChart.data.datasets[2].data = other;
        genderChart.update();
        return;
    }

    genderChart = new Chart(ctx, {
        type: "bar",
        data: {
            labels: labels,
            datasets: [
                { label: "Male",   data: male,   backgroundColor: gradientMale,   borderRadius: 12 },
                { label: "Female", data: female, backgroundColor: gradientFemale, borderRadius: 12 },
                { label: "Other",  data: other,  backgroundColor: gradientOther,  borderRadius: 12 }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { labels: { color: "#ffffff" } }
                // no percentageLabel here to avoid clutter on stacked chart
            },
            scales: {
                x: {
                    stacked: true,
                    ticks: { color: "#ffffff" },
                    grid: { display: false }
                },
                y: {
                    stacked: true,
                    ticks: { color: "#a8a8a8" },
                    grid: { color: "rgba(255,255,255,0.05)" }
                }
            }
        }
    });
}

function renderAgeGroupChart(rows) {
    const labels = rows.map(r => r.age_group);
    const counts = rows.map(r => r.total);

    const canvas = document.getElementById("ageGroupChart");
    const ctx = canvas.getContext("2d");
    const gradient = createNeonGradient(ctx);

    if (ageGroupChart) {
        ageGroupChart.data.labels = labels;
        ageGroupChart.data.datasets[0].data = counts;
        ageGroupChart.update();
        return;
    }

    ageGroupChart = new Chart(ctx, {
        type: "bar",
        data: {
            labels: labels,
            datasets: [{
                label: "Number of Persons",
                data: counts,
                backgroundColor: gradient,
                borderRadius: 14,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                percentageLabel: { enabled: true }
            },
            scales: {
                x: {
                    ticks: { color: "#ffffff" },
                    grid: { display: false }
                },
                y: {
                    ticks: { color: "#a8a8a8" },
                    grid: { color: "rgba(255,255,255,0.05)" }
                }
            }
        }
    });
}

// ----------------------
// Event listeners
// ----------------------
document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("applyFiltersBtn").addEventListener("click", loadEducationData);

    document.getElementById("resetFiltersBtn").addEventListener("click", () => {
        document.getElementById("filterGender").value    = "All";
        document.getElementById("filterMinAge").value    = "";
        document.getElementById("filterMaxAge").value    = "";
        document.getElementById("filterEducation").value = "All";
        loadEducationData();
    });

    // Initial load
    loadEducationData();
});
</script>

</body>
</html>
<?php
$conn->close();
?>
