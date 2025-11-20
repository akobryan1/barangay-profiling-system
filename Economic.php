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

// -------------------------------------------------------
// Occupation → Sector mapping and base income per sector
// -------------------------------------------------------
$sectorKeywordMap = [
    "Fishing & Marine" => [
        "fisher", "fisherman", "fisherfolk", "boat", "bangka",
        "seaweed", "aquaculture", "fish cage", "fish vendor"
    ],
    "Transport" => [
        "driver", "tricycle", "trike", "jeep", "jeepney",
        "van", "habal", "motorcycle taxi", "delivery", "rider"
    ],
    "Construction" => [
        "carpenter", "mason", "welder", "laborer",
        "construction", "painter", "plumber"
    ],
    "Retail & Services" => [
        "store", "sari-sari", "grocery", "vendor", "tindera",
        "tindero", "cashier", "sales", "barber", "salon",
        "laundry", "service", "repair"
    ],
    "Education / Professionals" => [
        "teacher", "professor", "engineer", "nurse", "midwife",
        "accountant", "office", "clerk", "staff", "government",
        "gov't", "police", "army", "professional"
    ],
    "Manufacturing / Processing" => [
        "factory", "processing", "dried fish", "smoked fish",
        "tuyo", "tinapa", "production", "packer"
    ],
    "Household / Care Work" => [
        "housewife", "house husband", "household",
        "kasambahay", "yaya", "caregiver"
    ],
    "Student" => ["student"],
    "Unemployed" => [
        "none", "n/a", "unemployed", "jobless", "wala", "no work"
    ],
    "Overseas / OFW" => [
        "ofw", "overseas", "seafarer", "seaman", "seawoman"
    ],
    "Farmer" => [
        "farmer", "farming", "rice", "corn", "vegetable"
    ]
];

// Base estimated monthly income per sector (PHP)
$sectorBaseIncome = [
    "Fishing & Marine"         => 25000,
    "Transport"                => 24000,
    "Construction"             => 14000,
    "Retail & Services"        => 18000,
    "Education / Professionals"=> 25000,
    "Manufacturing / Processing"=> 17000,
    "Household / Care Work"    => 8000,
    "Student"                  => 0,
    "Unemployed"               => 0,
    "Overseas / OFW"           => 50000,
    "Farmer"                   => 20000,
    "Other / Misc"             => 15000
];

// For dropdown
$sectorNames = array_keys($sectorBaseIncome);

// ----------------------
// Helper functions
// ----------------------
function mapOccupationToSector(string $occupation) : string {
    global $sectorKeywordMap;

    $occ = strtolower($occupation);

    foreach ($sectorKeywordMap as $sector => $keywords) {
        foreach ($keywords as $kw) {
            if (strpos($occ, strtolower($kw)) !== false) {
                return $sector;
            }
        }
    }
    return "Other / Misc";
}

function estimateIncome(string $sector, string $occupation) : int {
    global $sectorBaseIncome;

    // Base from sector
    $income = $sectorBaseIncome[$sector] ?? $sectorBaseIncome["Other / Misc"];

    // (Optional) Fine-tune specific occupations here if needed later.

    return (int)$income;
}

// ----------------------
// AJAX Endpoint: return JSON analytics
// ----------------------
if (isset($_GET["action"]) && $_GET["action"] === "getEconomicData") {
    header("Content-Type: application/json");

    $genderFilter = isset($_GET["gender"]) ? trim($_GET["gender"]) : "";
    $sectorFilter = isset($_GET["sector"]) ? trim($_GET["sector"]) : "";
    $minIncome    = isset($_GET["minIncome"]) && $_GET["minIncome"] !== "" ? (int) $_GET["minIncome"] : null;
    $maxIncome    = isset($_GET["maxIncome"]) && $_GET["maxIncome"] !== "" ? (int) $_GET["maxIncome"] : null;

    // Fetch persons with occupation (and optional gender filter)
    $sql = "
        SELECT person_id, gender, occupation
        FROM person
        WHERE occupation IS NOT NULL
          AND occupation <> ''
    ";

    $types = "";
    $params = [];

    if ($genderFilter !== "" && $genderFilter !== "All") {
        $sql .= " AND gender = ?";
        $types .= "s";
        $params[] = $genderFilter;
    }

    $stmt = $conn->prepare($sql);
    if ($types !== "" && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Aggregation containers
    $totalPersons      = 0;
    $totalIncome       = 0;
    $unemployedCount   = 0;
    $sectorCounts      = [];
    $sectorIncomeTotal = [];

    // Income brackets
    $brackets = [
        ["label" => "No income",        "min" => 0,     "max" => 0],
        ["label" => "1 – 9,999",        "min" => 1,     "max" => 9999],
        ["label" => "10,000 – 14,999",  "min" => 10000, "max" => 14999],
        ["label" => "15,000 – 19,999",  "min" => 15000, "max" => 19999],
        ["label" => "20,000 – 24,999",  "min" => 20000, "max" => 24999],
        ["label" => "25,000 – 29,999",  "min" => 25000, "max" => 29999],
        ["label" => "30,000+",          "min" => 30000, "max" => PHP_INT_MAX],
    ];
    $bracketCounts = [];
    foreach ($brackets as $b) {
        $bracketCounts[$b["label"]] = 0;
    }

    // Process each person
    while ($row = $result->fetch_assoc()) {
        $occupation = trim($row["occupation"]);
        if ($occupation === "") continue;

        $sector = mapOccupationToSector($occupation);
        $income = estimateIncome($sector, $occupation);

        // Apply sector filter after mapping
        if ($sectorFilter !== "" && $sectorFilter !== "All" && $sector !== $sectorFilter) {
            continue;
        }

        // Apply income filters
        if ($minIncome !== null && $income < $minIncome) continue;
        if ($maxIncome !== null && $income > $maxIncome) continue;

        $totalPersons++;
        $totalIncome += $income;

        if ($sector === "Unemployed" || $income == 0) {
            $unemployedCount++;
        }

        if (!isset($sectorCounts[$sector])) {
            $sectorCounts[$sector] = 0;
            $sectorIncomeTotal[$sector] = 0;
        }
        $sectorCounts[$sector]++;
        $sectorIncomeTotal[$sector] += $income;

        // Bracket assignment
        foreach ($brackets as $b) {
            if ($income >= $b["min"] && $income <= $b["max"]) {
                $bracketCounts[$b["label"]]++;
                break;
            }
        }
    }
    $stmt->close();

    // Summary metrics
    $avgIncome = $totalPersons > 0 ? round($totalIncome / $totalPersons) : null;
    $unempPct  = $totalPersons > 0 ? round(($unemployedCount / $totalPersons) * 100) : 0;

    $topSector = null;
    if (!empty($sectorCounts)) {
        arsort($sectorCounts);
        $topSector = array_key_first($sectorCounts);
    }

    $summary = [
        "totalPersons"      => $totalPersons,
        "avgIncome"         => $avgIncome,
        "unemployedCount"   => $unemployedCount,
        "unemployedPercent" => $unempPct,
        "topSector"         => $topSector
    ];

    // Format bracket data
    $incomeBrackets = [];
    foreach ($brackets as $b) {
        $label = $b["label"];
        $incomeBrackets[] = [
            "label" => $label,
            "count" => $bracketCounts[$label] ?? 0
        ];
    }

    // Sector distribution
    $sectorDistribution = [];
    foreach ($sectorCounts as $sector => $count) {
        $sectorDistribution[] = [
            "sector" => $sector,
            "count"  => $count
        ];
    }

    // Sector average income
    $sectorAverageIncome = [];
    foreach ($sectorCounts as $sector => $count) {
        $avg = $sectorIncomeTotal[$sector] / max($count, 1);
        $sectorAverageIncome[] = [
            "sector" => $sector,
            "avg"    => round($avg)
        ];
    }

    echo json_encode([
        "summary"             => $summary,
        "incomeBrackets"      => $incomeBrackets,
        "sectorDistribution"  => $sectorDistribution,
        "sectorAverageIncome" => $sectorAverageIncome
    ]);

    $conn->close();
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Economic Analysis</title>
    <link rel="stylesheet" href="economic.css">
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
            <li><a href="Education.php">Education Analysis</a></li>
            <li><a href="Economic.php" class="active">Economic Analysis</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content">

        <!-- Summary Panel -->
        <div class="glass-panel summary-panel">
            <h1 class="heading">Economic Analytics</h1>
            <p class="description">
                Explore occupation patterns, estimated monthly income, and sector-based economic structure of the barangay.
            </p>

            <div class="summary-grid">
                <div class="summary-card">
                    <span class="summary-label">With Occupation Data</span>
                    <span class="summary-value" id="sumTotalPersons">0</span>
                </div>
                <div class="summary-card">
                    <span class="summary-label">Avg Estimated Income (₱/month)</span>
                    <span class="summary-value" id="sumAvgIncome">–</span>
                </div>
                <div class="summary-card">
                    <span class="summary-label">Unemployed / No Income</span>
                    <span class="summary-value" id="sumUnemployed">0</span>
                    <span class="summary-sub" id="sumUnemployedPct">0%</span>
                </div>
                <div class="summary-card">
                    <span class="summary-label">Largest Sector</span>
                    <span class="summary-value" id="sumTopSector">–</span>
                </div>
            </div>
        </div>

        <!-- Filters Panel -->
        <div class="glass-panel filters-panel">
            <h2 class="section-title">Filters</h2>
            <form class="filters-grid" onsubmit="return false;">

                <div class="filter-group">
                    <label class="filter-label">Gender</label>
                    <select id="filterGender" class="filter-input">
                        <option value="All">All</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Sector</label>
                    <select id="filterSector" class="filter-input">
                        <option value="All">All</option>
                        <?php foreach ($sectorNames as $sector): ?>
                            <option value="<?= htmlspecialchars($sector) ?>">
                                <?= htmlspecialchars($sector) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Min Income (₱/month)</label>
                    <input id="filterMinIncome" type="number" class="filter-input" placeholder="e.g. 10000">
                </div>

                <div class="filter-group">
                    <label class="filter-label">Max Income (₱/month)</label>
                    <input id="filterMaxIncome" type="number" class="filter-input" placeholder="e.g. 30000">
                </div>

                <div class="filter-actions">
                    <button type="button" id="applyFiltersBtn" class="btn btn-primary">Apply Filters</button>
                    <button type="button" id="resetFiltersBtn" class="btn btn-secondary">Reset</button>
                </div>
            </form>
        </div>

        <!-- Charts Grid -->
        <div class="analytics-grid">

            <!-- Income Bracket Distribution -->
            <div class="glass-panel chart-panel">
                <h2 class="section-title">Estimated Monthly Income Distribution</h2>
                <canvas id="incomeBracketChart"></canvas>
            </div>

            <!-- Sector Distribution -->
            <div class="glass-panel chart-panel">
                <h2 class="section-title">Population by Economic Sector</h2>
                <canvas id="sectorDistributionChart"></canvas>
            </div>

            <!-- Sector Average Income -->
            <div class="glass-panel chart-panel">
                <h2 class="section-title">Average Income per Sector (₱/month)</h2>
                <canvas id="sectorIncomeChart"></canvas>
            </div>

        </div>
    </div>
</div>

<script>
// Global chart references
let incomeBracketChart = null;
let sectorDistributionChart = null;
let sectorIncomeChart = null;

// Neon gradient for bars
function createNeonGradient(ctx) {
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, "rgba(255, 0, 153, 0.9)");   // neon pink
    gradient.addColorStop(0.5, "rgba(121, 53, 245, 0.7)");// purple
    gradient.addColorStop(1, "rgba(0, 184, 255, 0.6)");   // cyan
    return gradient;
}

// Plugin: percentage labels at top of bars (for distribution charts)
const percentageLabelPlugin = {
    id: "percentageLabelPlugin",
    afterDatasetsDraw(chart, args, pluginOptions) {
        const { ctx } = chart;
        const dataset = chart.data.datasets[0];
        if (!dataset) return;

        const meta = chart.getDatasetMeta(0);
        const data = dataset.data;
        const total = data.reduce((a, b) => a + b, 0);
        if (!total) return;

        meta.data.forEach((bar, index) => {
            const value = data[index];
            if (!value) return;

            const percent = ((value / total) * 100).toFixed(0) + "%";

            ctx.save();
            ctx.fillStyle = "rgba(255,255,255,0.9)";
            ctx.font = "600 13px Inter, system-ui, -apple-system, BlinkMacSystemFont";
            ctx.textAlign = "center";
            ctx.textBaseline = "bottom";
            ctx.fillText(percent, bar.x, bar.y - 6);
            ctx.restore();
        });
    }
};

// -----------------------
// Fetch + update helpers
// -----------------------
function loadEconomicData() {
    const gender    = document.getElementById("filterGender").value;
    const sector    = document.getElementById("filterSector").value;
    const minIncome = document.getElementById("filterMinIncome").value;
    const maxIncome = document.getElementById("filterMaxIncome").value;

    const params = new URLSearchParams();
    params.append("action", "getEconomicData");
    if (gender)    params.append("gender", gender);
    if (sector)    params.append("sector", sector);
    if (minIncome) params.append("minIncome", minIncome);
    if (maxIncome) params.append("maxIncome", maxIncome);

    fetch("Economic.php?" + params.toString())
        .then(res => res.json())
        .then(data => {
            updateSummary(data.summary);
            renderIncomeBracketChart(data.incomeBrackets);
            renderSectorDistributionChart(data.sectorDistribution);
            renderSectorIncomeChart(data.sectorAverageIncome);
        })
        .catch(err => console.error("Error loading economic analytics:", err));
}

function updateSummary(summary) {
    document.getElementById("sumTotalPersons").textContent =
        summary.totalPersons ?? 0;

    document.getElementById("sumAvgIncome").textContent =
        summary.avgIncome !== null ? summary.avgIncome.toLocaleString() : "–";

    document.getElementById("sumUnemployed").textContent =
        summary.unemployedCount ?? 0;

    document.getElementById("sumUnemployedPct").textContent =
        (summary.unemployedPercent ?? 0) + "%";

    document.getElementById("sumTopSector").textContent =
        summary.topSector ?? "–";
}

// Charts
function renderIncomeBracketChart(rows) {
    const labels = rows.map(r => r.label);
    const counts = rows.map(r => r.count);

    const ctx = document.getElementById("incomeBracketChart").getContext("2d");
    const gradient = createNeonGradient(ctx);

    if (incomeBracketChart) {
        incomeBracketChart.data.labels = labels;
        incomeBracketChart.data.datasets[0].data = counts;
        incomeBracketChart.update();
        return;
    }

    incomeBracketChart = new Chart(ctx, {
        type: "bar",
        data: {
            labels,
            datasets: [{
                label: "Households / Persons",
                data: counts,
                backgroundColor: gradient,
                borderRadius: 16,
                borderWidth: 0,
                categoryPercentage: 0.5,
                barPercentage: 0.85,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
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
        },
        plugins: [percentageLabelPlugin]
    });
}

function renderSectorDistributionChart(rows) {
    const labels = rows.map(r => r.sector);
    const counts = rows.map(r => r.count);

    const ctx = document.getElementById("sectorDistributionChart").getContext("2d");
    const gradient = createNeonGradient(ctx);

    if (sectorDistributionChart) {
        sectorDistributionChart.data.labels = labels;
        sectorDistributionChart.data.datasets[0].data = counts;
        sectorDistributionChart.update();
        return;
    }

    sectorDistributionChart = new Chart(ctx, {
        type: "bar",
        data: {
            labels,
            datasets: [{
                label: "Population",
                data: counts,
                backgroundColor: gradient,
                borderRadius: 16,
                borderWidth: 0,
                categoryPercentage: 0.5,
                barPercentage: 0.85
            }]
        },
        options: {
            indexAxis: "y",
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    ticks: { color: "#a8a8a8" },
                    grid: { color: "rgba(255,255,255,0.05)" }
                },
                y: {
                    ticks: { color: "#ffffff" },
                    grid: { display: false }
                }
            }
        },
        plugins: [percentageLabelPlugin]
    });
}

function renderSectorIncomeChart(rows) {
    const labels = rows.map(r => r.sector);
    const avgs   = rows.map(r => r.avg);

    const ctx = document.getElementById("sectorIncomeChart").getContext("2d");
    const gradient = createNeonGradient(ctx);

    if (sectorIncomeChart) {
        sectorIncomeChart.data.labels = labels;
        sectorIncomeChart.data.datasets[0].data = avgs;
        sectorIncomeChart.update();
        return;
    }

    sectorIncomeChart = new Chart(ctx, {
        type: "bar",
        data: {
            labels,
            datasets: [{
                label: "Avg Income",
                data: avgs,
                backgroundColor: gradient,
                borderRadius: 16,
                borderWidth: 0,
                categoryPercentage: 0.5,
                barPercentage: 0.85
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => "₱ " + ctx.parsed.y.toLocaleString()
                    }
                }
            },
            scales: {
                x: {
                    ticks: { color: "#ffffff" },
                    grid: { display: false }
                },
                y: {
                    ticks: {
                        color: "#a8a8a8",
                        callback: (value) => "₱ " + value.toLocaleString()
                    },
                    grid: { color: "rgba(255,255,255,0.05)" }
                }
            }
        }
        // No percentage labels here – income values, not shares
    });
}

// Init listeners
document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("applyFiltersBtn").addEventListener("click", loadEconomicData);

    document.getElementById("resetFiltersBtn").addEventListener("click", () => {
        document.getElementById("filterGender").value     = "All";
        document.getElementById("filterSector").value     = "All";
        document.getElementById("filterMinIncome").value  = "";
        document.getElementById("filterMaxIncome").value  = "";
        loadEconomicData();
    });

    loadEconomicData();
});
</script>

</body>
</html>
<?php
$conn->close();
?>
