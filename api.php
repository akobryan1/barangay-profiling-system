<?php
header('Content-Type: application/json; charset=utf-8');

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = ''; // default XAMPP
$DB_NAME = 'barangay_profiling'; // adjust if needed

$mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'DB connection failed: '.$mysqli->connect_error]);
  exit;
}

function jpost($key, $default=null) { return $_POST[$key] ?? $default; }
$action = $_GET['action'] ?? $_POST['action'] ?? 'listResidents';

try {
  switch ($action) {

    // -------- Families (with member counts) --------
    case 'listFamiliesWithMembers':
      // members include the head of family
      $q = "SELECT f.family_id, f.family_name, f.household_number, f.address, f.family_head_id,
                   CONCAT(p.first_name, ' ', p.last_name) AS head_name,
                   COALESCE(cnt.members,0) AS members
            FROM family f
            LEFT JOIN person p ON p.person_id = f.family_head_id
            LEFT JOIN (
              SELECT family_id, COUNT(*) AS members
              FROM person
              GROUP BY family_id
            ) cnt ON cnt.family_id = f.family_id
            ORDER BY f.family_name ASC";
      $res = $mysqli->query($q);
      echo json_encode(['ok'=>true,'data'=>$res->fetch_all(MYSQLI_ASSOC)]);
      break;

    case 'listFamilies':
      $res = $mysqli->query("SELECT family_id, family_name FROM family ORDER BY family_name ASC");
      echo json_encode(['ok'=>true,'data'=>$res->fetch_all(MYSQLI_ASSOC)]);
      break;

    case 'createFamily':
      $stmt = $mysqli->prepare("INSERT INTO family(family_name, household_number, address, barangay_id) VALUES (?,?,?,?)");
      $stmt->bind_param("sssi", $_POST['family_name'], $_POST['household_number'], $_POST['address'], $_POST['barangay_id']);
      $stmt->execute(); 
      $id = $stmt->insert_id; 
      $stmt->close();
      echo json_encode(['ok'=>true,'id'=>$id]);
      break;

    case 'updateFamily':
      $fid = intval($_POST['family_id']);
      $stmt = $mysqli->prepare("UPDATE family SET family_name=?, household_number=?, address=? WHERE family_id=?");
      $stmt->bind_param("sssi", $_POST['family_name'], $_POST['household_number'], $_POST['address'], $fid);
      $stmt->execute(); 
      $stmt->close();
      echo json_encode(['ok'=>true]);
      break;

    case 'deleteFamily':
      $fid = intval($_POST['family_id']);
      $mysqli->query("DELETE FROM family WHERE family_id=".$fid);
      echo json_encode(['ok'=>true]);
      break;

    // -------- Residents --------
    case 'listResidents':
      $q = "SELECT p.*, f.family_name,
              TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) AS age
            FROM person p 
            JOIN family f ON f.family_id = p.family_id
            ORDER BY p.person_id DESC";
      $res = $mysqli->query($q);
      echo json_encode(['ok'=>true,'data'=>$res->fetch_all(MYSQLI_ASSOC)]);
      break;

    case 'searchResidents':
      $term = '%'.($_GET['term'] ?? $_POST['term'] ?? '').'%';
      $stmt = $mysqli->prepare("SELECT p.*, f.family_name,
              TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) AS age
            FROM person p 
            JOIN family f ON f.family_id=p.family_id
            WHERE CONCAT(p.first_name,' ',IFNULL(p.middle_name,''),' ',p.last_name) LIKE ?
               OR f.family_name LIKE ?
            ORDER BY p.person_id DESC");
      $stmt->bind_param("ss", $term, $term);
      $stmt->execute(); 
      $res = $stmt->get_result();
      echo json_encode(['ok'=>true,'data'=>$res->fetch_all(MYSQLI_ASSOC)]);
      break;

    case 'createResident':
      $belongs = $_POST['belongs'] ?? 'yes';
      if ($belongs === 'yes') {
        $family_id = intval($_POST['existing_family_id']);
      } else {
        $stmt = $mysqli->prepare("INSERT INTO family(family_name, household_number, address, barangay_id) VALUES (?,?,?,?)");
        $stmt->bind_param("sssi", $_POST['new_family_name'], $_POST['new_household_number'], $_POST['new_address'], $_POST['new_barangay_id']);
        $stmt->execute(); 
        $family_id = $stmt->insert_id; 
        $stmt->close();
        $_POST['relationship_to_head'] = 'Head';
      }

      $stmt = $mysqli->prepare("INSERT INTO person(family_id, first_name, middle_name, last_name, gender, date_of_birth, relationship_to_head, occupation, educational_attainment, contact_number, civil_status, religion)
                                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
      $stmt->bind_param("issssssissss",
        $family_id,
        $_POST['first_name'],
        $_POST['middle_name'],
        $_POST['last_name'],
        $_POST['gender'],
        $_POST['date_of_birth'],
        $_POST['relationship_to_head'],
        $_POST['occupation'],
        $_POST['educational_attainment'],
        $_POST['contact_number'],
        $_POST['civil_status'],
        $_POST['religion']
      );
      $stmt->execute(); 
      $pid = $stmt->insert_id; 
      $stmt->close();

      if (strtolower($_POST['relationship_to_head']) === 'head') {
        $mysqli->query("UPDATE family SET family_head_id=".$pid." WHERE family_id=".$family_id);
      }

      echo json_encode(['ok'=>true,'id'=>$pid]);
      break;

    case 'updateResident':
      $pid = intval($_POST['person_id']);
      $fid = intval($_POST['family_id']);
      $stmt = $mysqli->prepare("UPDATE person 
          SET family_id=?, first_name=?, middle_name=?, last_name=?, gender=?, date_of_birth=?, relationship_to_head=?, occupation=?, educational_attainment=?, contact_number=?, civil_status=?, religion=? 
          WHERE person_id=?");
      $stmt->bind_param("issssssissssi",
        $fid,
        $_POST['first_name'],
        $_POST['middle_name'],
        $_POST['last_name'],
        $_POST['gender'],
        $_POST['date_of_birth'],
        $_POST['relationship_to_head'],
        $_POST['occupation'],
        $_POST['educational_attainment'],
        $_POST['contact_number'],
        $_POST['civil_status'],
        $_POST['religion'],
        $pid
      );
      $stmt->execute(); 
      $stmt->close();

      if (strtolower($_POST['relationship_to_head']) === 'head') {
        $mysqli->query("UPDATE family SET family_head_id=".$pid." WHERE family_id=".$fid);
      }

      echo json_encode(['ok'=>true]);
      break;

    case 'deleteResident':
      $pid = intval($_POST['person_id']);
      $mysqli->query("DELETE FROM person WHERE person_id=".$pid);
      echo json_encode(['ok'=>true]);
      break;

    default:
      echo json_encode(['ok'=>false,'error'=>'Unknown action']);
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
?>
