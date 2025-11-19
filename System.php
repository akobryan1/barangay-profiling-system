<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Overview</title>
    <link rel="stylesheet" href="System.css">
</head>

<body>
    <div class="container">
        <!-- Sidebar / Dashboard -->
        <nav class="sidebar">
            <h2 class="logo">Dashboard</h2>
            <ul>
                <li><a href="System.php" class="active">Home</a></li>
                <li><a href="Persons.php">Persons</a></li>
                <li><a href="Families.php">Families</a></li>
                <li><a href="Households.php">Households</a></li>
                <li><a href="Education.php">Education Analysis</a></li>
                <li><a href="Economic.php">Economic Analysis</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <div class="glass-panel">
                <h1 class="heading">Barangay Household Profiling & Socio-Economic Information System</h1>
                <p class="description">
                    This system is a centralized platform designed to record, organize, and analyze household, family, 
                    and individual demographic information within a single barangay.  
                    Built on a fully normalized <b>3rd Normal Form (3NF)</b> database structure, the system ensures clean, accurate, 
                    and non-redundant data while enabling powerful socio-economic analysis.
                </p>

                <h2 class="section-title">System Capabilities</h2>
                <ul class="feature-list">
                    <li>Register and manage households</li>
                    <li>Document families per household</li>
                    <li>Create detailed personal profiles</li>
                    <li>Generate insights using occupation & education</li>
                    <li>Compute real-time age from birthdates</li>
                    <li>Produce economic, education, and age-based reports</li>
                </ul>

                <h2 class="section-title">Database Structure (3NF)</h2>
                <div class="card-container">
                    <div class="info-card">
                        <h3>household</h3>
                        <p>Contains data about each household entity.</p>
                    </div>

                    <div class="info-card">
                        <h3>family</h3>
                        <p>Represents families living in a household.</p>
                    </div>

                    <div class="info-card">
                        <h3>person</h3>
                        <p>Stores demographic and socio-economic details.</p>
                    </div>
                </div>

                <h2 class="section-title">Analytics</h2>
                <ul class="feature-list">
                    <li>Employment distribution & workforce categories</li>
                    <li>Educational attainment & literacy levels</li>
                    <li>Age structure: youth, adults, senior citizens</li>
                    <li>Dependency ratios</li>
                    <li>Barangay socio-economic indicators</li>
                </ul>

                <p class="footer-note">
                    Designed for Barangay Officials, LGUs, Researchers, and Community Planners.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
