<?php

if (isset($_GET['delete_image_master'])) {

	$id = $_GET['delete_image_master'];

	$q = mysqli_query($conn, "SELECT image FROM image_master WHERE id='$id'");
	$row = mysqli_fetch_assoc($q);

	if (!empty($row['image'])) {
		$path = "uploads/images/" . $row['image'];
		if (file_exists($path)) {
			unlink($path);
		}
	}

	mysqli_query($conn, "UPDATE image_master SET is_deleted=1 WHERE id='$id'");

	$_SESSION['msg'] = "Image Deleted Successfully";

	header("location:image_master.php");
}
/* =========================
   SOFT DELETE LEAD
========================= */
if (isset($_GET['delete_lead'])) {

	$id = intval($_GET['delete_lead']);

	$sql = "UPDATE leads SET is_deleted = 1 WHERE id = $id";

	if (mysqli_query($conn, $sql)) {
		$_SESSION['msg'] = "Lead deleted successfully!";
	} else {
		$_SESSION['msg'] = "Failed to delete lead!";
	}

	header("Location: view_leads.php");
	exit;
}
/* =========================
   BULK SOFT DELETE
========================= */
if (isset($_POST['delete_selected'])) {

	if (!empty($_POST['lead_ids'])) {

		$ids = implode(',', array_map('intval', $_POST['lead_ids']));

		$sql = "UPDATE leads SET is_deleted = 1 WHERE id IN ($ids)";
		mysqli_query($conn, $sql);

		$_SESSION['msg'] = "Selected leads deleted!";
	} else {
		$_SESSION['msg'] = "No lead selected!";
	}

	header("Location: view_leads.php");
	exit;
}
if (isset($_GET['permanent_delete'])) {
	$id = $_GET['permanent_delete'];
	mysqli_query($conn, "DELETE FROM leads WHERE id='$id'");
	$_SESSION['msg'] = "Lead permanently deleted";
	header("Location: trash_leads.php");
}



/* =========================
   BULK SOFT DELETE LUCKYDRAW ENTRIES
========================= */
if (isset($_POST['delete_selected_entries'])) {

	if (!empty($_POST['entry_ids'])) {

		$ids = implode(',', array_map('intval', $_POST['entry_ids']));

		$sql = "UPDATE luckydraw_entries SET is_deleted = 1 WHERE id IN ($ids)";
		mysqli_query($conn, $sql);

		$_SESSION['msg'] = "Selected entries deleted!";
	} else {
		$_SESSION['msg'] = "No entry selected!";
	}

	header("Location: view_luckydraw_entry.php");
	exit;
}

if (isset($_GET['permanent_delete_luckydraw'])) {
	$id = $_GET['permanent_delete_luckydraw'];
	mysqli_query($conn, "DELETE FROM luckydraw_entries WHERE id='$id'");
	$_SESSION['msg'] = "Luckydraw entry permanently deleted";
	header("Location: trash_luckydraw_entry.php");
}


if (isset($_GET['delete_counter'])) {

	$id = $_GET['delete_counter'];

	mysqli_query($conn, "DELETE FROM counters WHERE id='$id'");

	header("Location: manage_counters.php");
	exit;
}

if (isset($_GET['delete_gallery'])) {

	$id = $_GET['delete_gallery'];

	mysqli_query($conn, "DELETE FROM home_gallery WHERE id='$id'");

	header("Location: manage_home_gallery.php");
	exit;
}

if (isset($_GET['delete_home_service'])) {

	$id = intval($_GET['delete_home_service']);

	mysqli_query($conn, "
DELETE FROM home_services WHERE id='$id'
");

	$_SESSION['msg'] = "Service deleted";
	header("Location: manage_home_services.php");
	exit;
}

if (isset($_GET['delete_gallery'])) {

	$id = intval($_GET['delete_gallery']);

	$res = mysqli_query($conn, "SELECT image FROM gallery WHERE id='$id'");
	$row = mysqli_fetch_assoc($res);

	@unlink("uploads/gallery/" . $row['image']);

	mysqli_query($conn, "DELETE FROM gallery WHERE id='$id'");

	$_SESSION['msg'] = "Deleted";
	header("Location: manage_gallery.php");
	exit;
}

if (isset($_GET['toggle_gallery_status'])) {

	$id = intval($_GET['toggle_gallery_status']);

	mysqli_query($conn, "
UPDATE gallery
SET status=IF(status=1,0,1)
WHERE id='$id'
");

	header("Location: manage_gallery.php");
	exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['updateGalleryOrder'])) {

	foreach ($data['updateGalleryOrder'] as $row) {

		mysqli_query($conn, "
UPDATE gallery
SET sort_order='{$row['position']}'
WHERE id='{$row['id']}'
");

	}

	exit;
}

if (isset($_GET['delete_project'])) {

	$id = $_GET['delete_project'];

	mysqli_query($conn, "DELETE FROM projects WHERE id='$id'");

	header("location:manage_projects.php");
}

if (isset($_GET['toggle_project'])) {

	$id = $_GET['toggle_project'];

	mysqli_query($conn, "UPDATE projects 
        SET status = IF(status=1,0,1) 
        WHERE id='$id'");

	header("location:manage_projects.php");
}

$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['updateProjectOrder'])) {

	foreach ($data['updateProjectOrder'] as $row) {

		mysqli_query($conn, "UPDATE projects 
            SET sort_order='{$row['position']}' 
            WHERE id='{$row['id']}'");
	}

	exit;
}

/* =========================
   DELETE SERVICE DETAIL
========================= */

if (isset($_GET['delete_service_detail'])) {

	$id = intval($_GET['delete_service_detail']);

	$res = mysqli_fetch_assoc(
		mysqli_query($conn, "SELECT image FROM service_details WHERE id='$id'")
	);

	if (!empty($res['image'])) {
		@unlink("uploads/services/" . $res['image']);
	}

	mysqli_query($conn, "DELETE FROM service_details WHERE id='$id'");

	$_SESSION['msg'] = "Service deleted";

	header("Location: manage_service_details.php");
	exit;
}

/* =========================
   TOGGLE SERVICE STATUS
========================= */

if (isset($_GET['toggle_service_detail'])) {

	$id = intval($_GET['toggle_service_detail']);

	mysqli_query($conn, "
        UPDATE service_details
        SET status = IF(status=1,0,1)
        WHERE id='$id'
    ");

	header("Location: manage_service_details.php");
	exit;
}

/* DELETE GALLERY IMAGE */

if (isset($_GET['delete_main_gallery'])) {

	$id = intval($_GET['delete_main_gallery']);

	// Get image name
	$res = mysqli_query($conn, "SELECT image FROM gallery WHERE id='$id'");
	$row = mysqli_fetch_assoc($res);

	if ($row) {

		$image = $row['image'];
		$path = "uploads/gallery/" . $image;

		// Delete image file
		if (file_exists($path)) {
			unlink($path);
		}

		// Delete from database
		mysqli_query($conn, "DELETE FROM gallery WHERE id='$id'");

		$_SESSION['msg'] = "Gallery image deleted successfully";

	} else {

		$_SESSION['msg'] = "Image not found";

	}

	header("Location: manage_gallery.php");
	exit;
}