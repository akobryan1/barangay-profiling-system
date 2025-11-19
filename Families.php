<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "barangay_profiling";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* -------------------------------------------------
   FETCH HOUSEHOLDS FOR DROPDOWN
------------------------------------------------- */
$householdList = [];
$hRes = $conn->query("SELECT household_id FROM household ORDER BY household_id ASC");
if ($hRes && $hRes->num_rows > 0) {
    while ($row = $hRes->fetch_assoc()) {
        $householdList[] = $row;
    }
}

/* -------------------------------------------------
   FETCH PERSONS GROUPED BY HOUSEHOLD FOR FAMILY HEAD
   (person -> family -> household)
------------------------------------------------- */
$personsByHousehold = [];

$pSql = "
    SELECT 
        p.person_id,
        p.first_name,
        p.last_name,
        f.household_id
    FROM person p
    LEFT JOIN family f ON p.family_id = f.family_id
";
$pRes = $conn->query($pSql);
if ($pRes && $pRes->num_rows > 0) {
    while ($row = $pRes->fetch_assoc()) {
        $hid = $row["household_id"];
        if ($hid === null) {
            continue;
        }
        if (!isset($personsByHousehold[$hid])) {
            $personsByHousehold[$hid] = [];
        }
        $personsByHousehold[$hid][] = [
            "person_id" => $row["person_id"],
            "name"      => trim($row["first_name"] . " " . $row["last_name"])
        ];
    }
}

/* -------------------------------------------------
   DELETE FAMILY
------------------------------------------------- */
if (isset($_GET["delete_id"])) {
    $delete_id = (int) $_GET["delete_id"];

    $stmt = $conn->prepare("DELETE FROM family WHERE family_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();

    header("Location: Families.php");
    exit;
}

/* -------------------------------------------------
   CREATE / UPDATE FAMILY
   form_type = create | update
------------------------------------------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $formType     = $_POST["form_type"] ?? "";
    $family_id    = isset($_POST["family_id"]) ? (int) $_POST["family_id"] : null;
    $household_id = !empty($_POST["household_id"]) ? (int) $_POST["household_id"] : null;
    $family_name  = trim($_POST["family_name"] ?? "");
    $address      = trim($_POST["address"] ?? "");
    $family_head  = !empty($_POST["family_head"]) ? (int) $_POST["family_head"] : null;

    if ($formType === "create") {
        if ($family_head === null) {
            $stmt = $conn->prepare("
                INSERT INTO family (household_id, family_name, address, family_head)
                VALUES (?,?,?,NULL)
            ");
            $stmt->bind_param("iss", $household_id, $family_name, $address);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO family (household_id, family_name, address, family_head)
                VALUES (?,?,?,?)
            ");
            $stmt->bind_param("issi", $household_id, $family_name, $address, $family_head);
        }
        $stmt->execute();
        $stmt->close();

    } elseif ($formType === "update" && $family_id !== null) {
        if ($family_head === null) {
            $stmt = $conn->prepare("
                UPDATE family
                SET household_id = ?, family_name = ?, address = ?, family_head = NULL
                WHERE family_id = ?
            ");
            $stmt->bind_param("issi", $household_id, $family_name, $address, $family_id);
        } else {
            $stmt = $conn->prepare("
                UPDATE family
                SET household_id = ?, family_name = ?, address = ?, family_head = ?
                WHERE family_id = ?
            ");
            $stmt->bind_param("issii", $household_id, $family_name, $address, $family_head, $family_id);
        }
        $stmt->execute();
        $stmt->close();
    }

    header("Location: Families.php");
    exit;
}

/* -------------------------------------------------
   FETCH FAMILIES (WITH SEARCH)
------------------------------------------------- */
$search   = "";
$families = [];

if (isset($_GET["search"])) {
    $search     = trim($_GET["search"]);
    $safeSearch = $conn->real_escape_string($search);

    $sql = "
        SELECT
            f.family_id,
            f.household_id,
            f.family_name,
            f.address,
            f.family_head,
            CONCAT(p.first_name, ' ', p.last_name) AS head_name
        FROM family f
        LEFT JOIN person p ON f.family_head = p.person_id
        WHERE
            f.family_name LIKE '%$safeSearch%' OR
            f.address     LIKE '%$safeSearch%' OR
            CONCAT(p.first_name, ' ', p.last_name) LIKE '%$safeSearch%'
        ORDER BY f.family_id DESC
    ";
} else {
    $sql = "
        SELECT
            f.family_id,
            f.household_id,
            f.family_name,
            f.address,
            f.family_head,
            CONCAT(p.first_name, ' ', p.last_name) AS head_name
        FROM family f
        LEFT JOIN person p ON f.family_head = p.person_id
        ORDER BY f.family_id DESC
    ";
}

$fRes = $conn->query($sql);
if ($fRes && $fRes->num_rows > 0) {
    while ($row = $fRes->fetch_assoc()) {
        $families[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Families</title>
    <link rel="stylesheet" href="families.css">
</head>

<body>
<div class="container">
    <!-- Sidebar -->
    <nav class="sidebar">
        <h2 class="logo">Dashboard</h2>
        <ul>
            <li><a href="System.php">Home</a></li>
            <li><a href="Persons.php">Persons</a></li>
            <li><a href="Families.php" class="active">Families</a></li>
            <li><a href="Households.php">Households</a></li>
            <li><a href="Education.php">Education Analysis</a></li>
            <li><a href="Economic.php">Economic Analysis</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content">

        <!-- REGISTER FAMILY PANEL (COLLAPSIBLE) -->
        <div class="glass-panel family-form-panel" id="familyFormPanel">
            <h1 class="heading">Add New Family</h1>
            <p class="description">
                Use the form below to register a new family into the barangay database.
            </p>

            <form method="post" class="family-form">
                <input type="hidden" name="form_type" value="create">

                <div class="family-form-grid">

                    <!-- Household -->
                    <div class="form-group">
                        <label class="form-label">Household</label>
                        <div class="family-select-wrapper">
                            <select name="household_id" id="create_household_id" class="form-input" required>
                                <option value="">-- Select Household --</option>
                                <?php foreach ($householdList as $h): ?>
                                    <option value="<?= $h['household_id']; ?>">
                                        Household <?= htmlspecialchars($h['household_id']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <a href="Households.php" class="family-add-btn">
                                + New Household
                            </a>
                        </div>
                    </div>

                    <!-- Family name -->
                    <div class="form-group">
                        <label class="form-label">Family Name</label>
                        <input type="text" name="family_name" class="form-input" required>
                    </div>

                    <!-- Address -->
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-input">
                    </div>

                    <!-- Family head -->
                    <div class="form-group">
                        <label class="form-label">Family Head (optional)</label>
                        <select name="family_head" id="create_family_head" class="form-input">
                            <option value="">— No family head set —</option>
                            <!-- options are populated via JS based on household selection -->
                        </select>
                    </div>

                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add Family</button>
                </div>
            </form>
        </div>

        <!-- TABLE PANEL -->
        <div class="glass-panel family-table-panel">
            <h2 class="section-title">Registered Families</h2>

            <form method="GET" class="search-bar">
                <input
                    type="text"
                    name="search"
                    class="search-input"
                    placeholder="Search families..."
                    value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="search-btn">Search</button>
            </form>

            <button id="toggleFormBtn" class="register-btn">
                + Register Family
            </button>

            <div class="table-wrapper">
                <table class="family-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Household</th>
                        <th>Family Name</th>
                        <th>Address</th>
                        <th>Family Head</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (count($families) === 0): ?>
                        <tr>
                            <td colspan="6" class="no-data">No families found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($families as $fam): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($fam["family_id"]); ?></td>
                                <td><?php echo htmlspecialchars($fam["household_id"]); ?></td>
                                <td><?php echo htmlspecialchars($fam["family_name"]); ?></td>
                                <td><?php echo htmlspecialchars($fam["address"]); ?></td>
                                <td>
                                    <?php
                                    echo $fam["head_name"]
                                        ? htmlspecialchars($fam["head_name"])
                                        : "—";
                                    ?>
                                </td>
                                <td class="action-cell">
                                    <a href="#"
                                       class="action-link edit"
                                       onclick='openEditModal(<?= json_encode($fam); ?>); return false;'>
                                        Edit
                                    </a>

                                    <a class="action-link delete"
                                       href="Families.php?delete_id=<?= $fam["family_id"]; ?>"
                                       onclick="return confirm('Delete this family?');">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div> <!-- /main-content -->
</div> <!-- /container -->

<!-- EDIT FAMILY MODAL -->
<div id="editModal" class="modal-overlay">
    <div class="modal-content glass-panel">
        <h2 class="modal-title">Edit Family</h2>

        <form method="POST" class="edit-form">
            <input type="hidden" name="form_type" value="update">
            <input type="hidden" name="family_id" id="edit_family_id">

            <div class="family-form-grid">

                <div class="form-group">
                    <label class="form-label">Household</label>
                    <select name="household_id" id="edit_household_id" class="form-input" required>
                        <option value="">-- Select Household --</option>
                        <?php foreach ($householdList as $h): ?>
                            <option value="<?= $h['household_id']; ?>">
                                Household <?= htmlspecialchars($h['household_id']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Family Name</label>
                    <input type="text" name="family_name" id="edit_family_name" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" id="edit_address" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">Family Head (optional)</label>
                    <select name="family_head" id="edit_family_head" class="form-input">
                        <option value="">— No family head set —</option>
                        <!-- options populated in JS -->
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

<?php $conn->close(); ?>

<script>
// persons grouped by household (for family head dropdowns)
const personsByHousehold = <?php echo json_encode($personsByHousehold); ?>;

// Collapsible register panel
document.getElementById("toggleFormBtn").addEventListener("click", () => {
    const panel = document.getElementById("familyFormPanel");
    panel.classList.toggle("open");
});

// Utility: populate family head options based on household
function populateHeadOptions(householdId, selectEl, selectedId = null) {
    if (!selectEl) return;

    // Clear current options
    selectEl.innerHTML = "";
    const defaultOpt = document.createElement("option");
    defaultOpt.value = "";
    defaultOpt.textContent = "— No family head set —";
    selectEl.appendChild(defaultOpt);

    if (!householdId || !personsByHousehold[householdId]) {
        return;
    }

    personsByHousehold[householdId].forEach(p => {
        const opt = document.createElement("option");
        opt.value = p.person_id;
        opt.textContent = p.name;
        if (selectedId && String(selectedId) === String(p.person_id)) {
            opt.selected = true;
        }
        selectEl.appendChild(opt);
    });
}

// Create form: update head dropdown when household changes
const createHouseholdSelect = document.getElementById("create_household_id");
const createHeadSelect      = document.getElementById("create_family_head");
if (createHouseholdSelect && createHeadSelect) {
    createHouseholdSelect.addEventListener("change", () => {
        populateHeadOptions(createHouseholdSelect.value, createHeadSelect, null);
    });
}

// Open edit modal
function openEditModal(family) {
    document.getElementById("edit_family_id").value   = family.family_id;
    document.getElementById("edit_family_name").value = family.family_name || "";
    document.getElementById("edit_address").value     = family.address || "";

    const editHouseholdSelect = document.getElementById("edit_household_id");
    const editHeadSelect      = document.getElementById("edit_family_head");

    if (editHouseholdSelect) {
        editHouseholdSelect.value = family.household_id;
    }

    // populate head options based on family.household_id
    populateHeadOptions(family.household_id, editHeadSelect, family.family_head);

    document.getElementById("editModal").classList.add("open");
}

// Close modal
document.getElementById("modalCloseBtn").addEventListener("click", () => {
    document.getElementById("editModal").classList.remove("open");
});

// Close modal by clicking outside
document.getElementById("editModal").addEventListener("click", (e) => {
    if (e.target === e.currentTarget) {
        e.currentTarget.classList.remove("open");
    }
});
</script>
</body>
</html>
