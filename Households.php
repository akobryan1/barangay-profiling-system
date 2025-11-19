<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "barangay_profiling";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* ============================
   FETCH PERSON LIST (household heads)
=============================== */
$personList = [];
$pResult = $conn->query("SELECT person_id, first_name, last_name FROM person ORDER BY first_name ASC");
if ($pResult && $pResult->num_rows > 0) {
    while ($p = $pResult->fetch_assoc()) {
        $personList[] = $p;
    }
}

/* ============================
   DELETE HOUSEHOLD
=============================== */
if (isset($_GET["delete_id"])) {
    $delete_id = (int) $_GET["delete_id"];

    $stmt = $conn->prepare("DELETE FROM household WHERE household_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();

    header("Location: Households.php");
    exit;
}

/* ============================
   CREATE / UPDATE HOUSEHOLD
=============================== */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $household_id   = isset($_POST["household_id"]) ? (int) $_POST["household_id"] : null;
    $household_name = trim($_POST["household_name"]);
    $household_head = !empty($_POST["household_head"]) ? (int) $_POST["household_head"] : null;

    if (!empty($_POST["is_edit"])) {
        // UPDATE
        $stmt = $conn->prepare("
            UPDATE household
            SET household_name = ?, household_head = ?
            WHERE household_id = ?
        ");
        $stmt->bind_param("sii", $household_name, $household_head, $household_id);
        $stmt->execute();
        $stmt->close();

    } else {
        // CREATE
        $stmt = $conn->prepare("
            INSERT INTO household (household_name, household_head)
            VALUES (?, ?)
        ");
        $stmt->bind_param("si", $household_name, $household_head);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: Households.php");
    exit;
}

/* ============================
   FETCH HOUSEHOLDS
=============================== */
$search = "";
$households = [];

if (isset($_GET["search"])) {
    $search = trim($_GET["search"]);

    $sql = "
        SELECT h.*, p.first_name, p.last_name
        FROM household h
        LEFT JOIN person p ON h.household_head = p.person_id
        WHERE h.household_name LIKE '%$search%'
        ORDER BY h.household_id DESC
    ";

} else {
    $sql = "
        SELECT h.*, p.first_name, p.last_name
        FROM household h
        LEFT JOIN person p ON h.household_head = p.person_id
        ORDER BY h.household_id DESC
    ";
}

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $households[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Households</title>
    <link rel="stylesheet" href="households.css">
</head>

<body>
<div class="container">

    <!-- SIDEBAR -->
    <nav class="sidebar">
        <h2 class="logo">Dashboard</h2>
        <ul>
            <li><a href="System.php">Home</a></li>
            <li><a href="Persons.php">Persons</a></li>
            <li><a href="Families.php">Families</a></li>
            <li><a href="Households.php" class="active">Households</a></li>
            <li><a href="Education.php">Education Analysis</a></li>
            <li><a href="Economic.php">Economic Analysis</a></li>
        </ul>
    </nav>

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- REGISTER HOUSEHOLD PANEL -->
        <div class="glass-panel household-form-panel" id="householdFormPanel">
            <h1 class="heading">Add New Household</h1>

            <form method="post" class="household-form">
                <div class="household-form-grid">

                    <div class="form-group">
                        <label class="form-label">Household Name</label>
                        <input type="text" name="household_name" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Household Head</label>
                        <select name="household_head" class="form-input">
                            <option value="">-- Select Head --</option>
                            <?php foreach ($personList as $p): ?>
                                <option value="<?= $p["person_id"]; ?>">
                                    <?= htmlspecialchars($p["first_name"] . " " . $p["last_name"]); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add Household</button>
                </div>

            </form>
        </div>

        <!-- TABLE PANEL -->
        <div class="glass-panel household-table-panel">

            <h2 class="section-title">Registered Households</h2>

            <div class="search-bar">
                <input
                type="text"
                name="search"
                class="search-input"
                placeholder="Search households..."
                >
                <button class="search-btn">Search</button>
            </div>


            <button id="toggleFormBtn" class="register-btn">+ Register Household</button>

            <div class="table-wrapper">
                <table class="household-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Household Name</th>
                        <th>Head</th>
                        <th>Actions</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php if (count($households) === 0): ?>
                        <tr><td colspan="4" class="no-data">No households found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($households as $h): ?>
                            <tr>
                                <td><?= $h["household_id"]; ?></td>
                                <td><?= htmlspecialchars($h["household_name"]); ?></td>
                                <td>
                                    <?= htmlspecialchars($h["first_name"] . " " . $h["last_name"]); ?>
                                </td>

                                <td class="action-cell">
                                    <a href="#" class="action-link edit"
                                       onclick='openEditModal(<?= json_encode($h); ?>); return false;'>Edit</a>

                                    <a href="Households.php?delete_id=<?= $h["household_id"]; ?>"
                                       onclick="return confirm('Delete this household?');"
                                       class="action-link delete">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>

                </table>
            </div>

        </div>
    </div>
</div>


<!-- EDIT MODAL -->
<div id="editModal" class="modal-overlay">
    <div class="modal-content glass-panel">

        <h2 class="modal-title">Edit Household</h2>

        <form method="POST" class="edit-form">

            <input type="hidden" name="household_id" id="edit_household_id">
            <input type="hidden" name="is_edit" value="1">

            <div class="household-form-grid">

                <div class="form-group">
                    <label class="form-label">Household Name</label>
                    <input type="text" name="household_name" id="edit_household_name" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">Household Head</label>
                    <select name="household_head" id="edit_household_head" class="form-input">
                        <option value="">-- Select Head --</option>
                        <?php foreach ($personList as $p): ?>
                            <option value="<?= $p["person_id"]; ?>">
                                <?= htmlspecialchars($p["first_name"] . " " . $p["last_name"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <button type="button" class="btn btn-secondary" id="modalCloseBtn">Cancel</button>
            </div>

        </form>
    </div>
</div>

<script>
/* Toggle registration form */
document.getElementById("toggleFormBtn").addEventListener("click", () => {
    document.getElementById("householdFormPanel").classList.toggle("open");
});

/* Open edit modal */
function openEditModal(data) {
    document.getElementById("edit_household_id").value = data.household_id;
    document.getElementById("edit_household_name").value = data.household_name;
    document.getElementById("edit_household_head").value = data.household_head;

    document.getElementById("editModal").classList.add("open");
}

/* Close modal */
document.getElementById("modalCloseBtn").addEventListener("click", () => {
    document.getElementById("editModal").classList.remove("open");
});
document.getElementById("editModal").addEventListener("click", (e) => {
    if (e.target === e.currentTarget)
        e.currentTarget.classList.remove("open");
});
</script>

</body>
</html>

<?php $conn->close(); ?>
