<?php
$host = "localhost";          
$user = "root";               
$pass = "";                   
$dbname = "barangay_profiling";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// FETCH FAMILY LIST FOR DROPDOWN. BRYAN, DO NOT REMOVE AGAIN
$familyList = [];

$famResult = $conn->query("SELECT family_id, family_name FROM family ORDER BY family_name ASC");

if ($famResult && $famResult->num_rows > 0) {
    while ($fam = $famResult->fetch_assoc()) {
        $familyList[] = $fam;
    }
}



$editMode   = false;
$editPerson = [
    "person_id"             => "",
    "family_id"             => "",
    "first_name"            => "",
    "middle_name"           => "",
    "last_name"             => "",
    "gender"                => "",
    "date_of_birth"         => "",
    "relationship_to_head"  => "",
    "occupation"            => "",
    "educational_attainment"=> "",
    "contact_number"        => "",
    "civil_status"          => "",
    "religion"              => ""
];

// ---------- DELETE ----------
if (isset($_GET["delete_id"])) {
    $delete_id = (int) $_GET["delete_id"];
    $stmt = $conn->prepare("DELETE FROM person WHERE person_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();

    header("Location: Persons.php");
    exit;
}

// ---------- CREATE / UPDATE ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $person_id            = isset($_POST["person_id"]) ? (int) $_POST["person_id"] : null;
    $family_id            = !empty($_POST["family_id"]) ? (int) $_POST["family_id"] : null;
    $first_name           = trim($_POST["first_name"]);
    $middle_name          = trim($_POST["middle_name"]);
    $last_name            = trim($_POST["last_name"]);
    $gender               = trim($_POST["gender"]);
    $date_of_birth        = $_POST["date_of_birth"] ?: null;
    $relationship_to_head = trim($_POST["relationship_to_head"]);
    $occupation           = trim($_POST["occupation"]);
    $educ_attainment      = trim($_POST["educational_attainment"]);
    $contact_number       = trim($_POST["contact_number"]);
    $civil_status         = trim($_POST["civil_status"]);
    $religion             = trim($_POST["religion"]);

    if (!empty($_POST["is_edit"])) {
        // UPDATE
        $stmt = $conn->prepare("
            UPDATE person
            SET family_id = ?, first_name = ?, middle_name = ?, last_name = ?, gender = ?,
                date_of_birth = ?, relationship_to_head = ?, occupation = ?, educational_attainment = ?,
                contact_number = ?, civil_status = ?, religion = ?
            WHERE person_id = ?
        ");
        $stmt->bind_param(
            "isssssssssssi",
            $family_id,
            $first_name,
            $middle_name,
            $last_name,
            $gender,
            $date_of_birth,
            $relationship_to_head,
            $occupation,
            $educ_attainment,
            $contact_number,
            $civil_status,
            $religion,
            $person_id
        );
        $stmt->execute();
        $stmt->close();
    } else {
        // CREATE
        $stmt = $conn->prepare("
            INSERT INTO person
            (family_id, first_name, middle_name, last_name, gender, date_of_birth,
             relationship_to_head, occupation, educational_attainment, contact_number,
             civil_status, religion)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param(
            "isssssssssss",
            $family_id,
            $first_name,
            $middle_name,
            $last_name,
            $gender,
            $date_of_birth,
            $relationship_to_head,
            $occupation,
            $educ_attainment,
            $contact_number,
            $civil_status,
            $religion
        );
        $stmt->execute();
        $stmt->close();
    }

    header("Location: Persons.php");
    exit;
}

// ---------- FETCH ALL PERSONS ----------
$search = "";
$people = [];

if (isset($_GET["search"])) {
    $search = trim($_GET["search"]);
    
    $sql = "
        SELECT *
        FROM person
        WHERE 
            first_name LIKE '%$search%' OR
            middle_name LIKE '%$search%' OR
            last_name LIKE '%$search%' OR
            occupation LIKE '%$search%' OR
            educational_attainment LIKE '%$search%' OR
            relationship_to_head LIKE '%$search%' OR
            religion LIKE '%$search%' OR
            civil_status LIKE '%$search%'
        ORDER BY person_id DESC
    ";
} else {
    $sql = "SELECT * FROM person ORDER BY person_id DESC";
}

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $people[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Persons</title>
    <link rel="stylesheet" href="persons.css">
</head>

<body>
<div class="container">
    <!-- Sidebar -->
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
        <!-- FORM PANEL (CREATE / UPDATE) -->
        <!-- REGISTER PERSON PANEL (COLLAPSIBLE) -->
<div class="glass-panel person-form-panel" id="personFormPanel">
    <h1 class="heading">Add New Person</h1>
    <p class="description">
        Use the form below to add a new individual to the barangay person registry.
    </p>

    <form method="post" class="person-form">

        <div class="person-form-grid">

            <div class="form-group" style="grid-column: span 1;">
                <label class="form-label">Family</label>
                <div class="family-select-wrapper">

                    <select name="family_id" class="form-input">
                        <option value="">-- Select Family --</option>
                        <?php foreach ($familyList as $f): ?>
                            <option value="<?= $f['family_id']; ?>">
                                <?= htmlspecialchars($f['family_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <a href="Families.php" class="family-add-btn">
                        + New Family
                    </a>

                </div>
            </div>

            <div class="form-group">
                <label class="form-label">First Name</label>
                <input type="text" name="first_name" class="form-input" required>
            </div>

            <div class="form-group">
                <label class="form-label">Middle Name</label>
                <input type="text" name="middle_name" class="form-input">
            </div>

            <div class="form-group">
                <label class="form-label">Last Name</label>
                <input type="text" name="last_name" class="form-input" required>
            </div>

            <div class="form-group">
                <label class="form-label">Gender</label>
                <select name="gender" class="form-input">
                    <option value="">Select</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Date of Birth</label>
                <input type="date" name="date_of_birth" class="form-input">
            </div>

            <div class="form-group">
                <label class="form-label">Relationship to Head</label>
                <input type="text" name="relationship_to_head" class="form-input">
            </div>

            <div class="form-group">
                <label class="form-label">Occupation</label>
                <input type="text" name="occupation" class="form-input">
            </div>

            <div class="form-group">
                <label class="form-label">Educational Attainment</label>
                <input type="text" name="educational_attainment" class="form-input">
            </div>

            <div class="form-group">
                <label class="form-label">Contact Number</label>
                <input type="text" name="contact_number" class="form-input">
            </div>

            <div class="form-group">
                <label class="form-label">Civil Status</label>
                <input type="text" name="civil_status" class="form-input">
            </div>

            <div class="form-group">
                <label class="form-label">Religion</label>
                <input type="text" name="religion" class="form-input">
            </div>

        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Add Person</button>
        </div>

    </form>
</div>


        <!-- TABLE PANEL (READ + ACTIONS) -->
        <div class="glass-panel person-table-panel">
            <h2 class="section-title">Registered Persons</h2>
            <form method="GET" class="search-bar">
                <input
                    type="text"
                    name="search"
                    class="search-input"
                    placeholder="Search persons..."
                    value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="search-btn">Search</button>
            </form>

            <button id="toggleFormBtn" class="register-btn">
                + Register Person
            </button>


            <div class="table-wrapper">
                <table class="person-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Family ID</th>
                        <th>Name</th>
                        <th>Gender</th>
                        <th>Date of Birth</th>
                        <th>Relationship</th>
                        <th>Occupation</th>
                        <th>Education</th>
                        <th>Contact</th>
                        <th>Civil Status</th>
                        <th>Religion</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (count($people) === 0): ?>
                        <tr>
                            <td colspan="12" class="no-data">No persons found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($people as $p): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p["person_id"]); ?></td>
                                <td><?php echo htmlspecialchars($p["family_id"]); ?></td>
                                <td><?php echo htmlspecialchars($p["first_name"] . " " . $p["last_name"]); ?></td>
                                <td><?php echo htmlspecialchars($p["gender"]); ?></td>
                                <td><?php echo htmlspecialchars($p["date_of_birth"]); ?></td>
                                <td><?php echo htmlspecialchars($p["relationship_to_head"]); ?></td>
                                <td><?php echo htmlspecialchars($p["occupation"]); ?></td>
                                <td><?php echo htmlspecialchars($p["educational_attainment"]); ?></td>
                                <td><?php echo htmlspecialchars($p["contact_number"]); ?></td>
                                <td><?php echo htmlspecialchars($p["civil_status"]); ?></td>
                                <td><?php echo htmlspecialchars($p["religion"]); ?></td>
                                <td class="action-cell">
                                    <a href="#" class="action-link edit"
                                        onclick='openEditModal(<?= json_encode($p); ?>); return false;'>
                                        Edit
                                    </a>

                                    <a class="action-link delete"
                                        href="Persons.php?delete_id=<?= $p["person_id"]; ?>"
                                        onclick="return confirm('Delete this person?');">
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
    </div>
</div>
</body>
<!-- EDIT PERSON MODAL -->
<div id="editModal" class="modal-overlay">
    <div class="modal-content glass-panel">
        <h2 class="modal-title">Edit Person</h2>

        <form method="POST" class="edit-form">
            <input type="hidden" name="person_id" id="edit_person_id">

            <div class="person-form-grid">

                <div class="form-group">
                    <label class="form-label">First Name</label>
                    <input type="text" name="first_name" id="edit_first_name" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">Middle Name</label>
                    <input type="text" name="middle_name" id="edit_middle_name" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" id="edit_last_name" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select name="gender" id="edit_gender" class="form-input">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="date_of_birth" id="edit_date_of_birth" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">Relationship to Head</label>
                    <input type="text" name="relationship_to_head" id="edit_relationship_to_head" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">Occupation</label>
                    <input type="text" name="occupation" id="edit_occupation" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">Educational Attainment</label>
                    <input type="text" name="educational_attainment" id="edit_educational_attainment" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="contact_number" id="edit_contact_number" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">Civil Status</label>
                    <input type="text" name="civil_status" id="edit_civil_status" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">Religion</label>
                    <input type="text" name="religion" id="edit_religion" class="form-input">
                </div>
            </div>

            <div class="modal-actions">
                <button type="submit" name="update_person" class="btn btn-primary">Save Changes</button>
                <button type="button" class="btn btn-secondary" id="modalCloseBtn">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById("toggleFormBtn").addEventListener("click", () => {
    const panel = document.getElementById("personFormPanel");
    panel.classList.toggle("open");
});

function openEditModal(person) {
    // Fill modal fields
    document.getElementById("edit_person_id").value = person.person_id;
    document.getElementById("edit_first_name").value = person.first_name;
    document.getElementById("edit_middle_name").value = person.middle_name;
    document.getElementById("edit_last_name").value = person.last_name;
    document.getElementById("edit_gender").value = person.gender;
    document.getElementById("edit_date_of_birth").value = person.date_of_birth;
    document.getElementById("edit_relationship_to_head").value = person.relationship_to_head;
    document.getElementById("edit_occupation").value = person.occupation;
    document.getElementById("edit_educational_attainment").value = person.educational_attainment;
    document.getElementById("edit_contact_number").value = person.contact_number;
    document.getElementById("edit_civil_status").value = person.civil_status;
    document.getElementById("edit_religion").value = person.religion;

    // Show modal
    document.getElementById("editModal").classList.add("open");
}

// Close modal
document.getElementById("modalCloseBtn").addEventListener("click", () => {
    document.getElementById("editModal").classList.remove("open");
});

// Close by clicking outside
document.getElementById("editModal").addEventListener("click", (e) => {
    if (e.target === e.currentTarget) {
        e.currentTarget.classList.remove("open");
    }
});
</script>

</html>
<?php
$conn->close();
?>
>
