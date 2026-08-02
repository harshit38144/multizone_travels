<?php
if (isset($_GET['restore_user'])) {
    $user_id = mysqli_real_escape_string($conn, $_GET['restore_user']);

    // Restore user
    $query = "UPDATE users SET is_deleted = 0, updated_at = NOW() WHERE id = '$user_id' AND is_deleted = 1";
    if (mysqli_query($conn, $query)) {
        $_SESSION['message'] = "User restored successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error restoring user: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
    }

    header("Location: trash_user.php");
    exit();
}

// Permanent delete user
if (isset($_GET['permanent_delete_user'])) {
    $user_id = mysqli_real_escape_string($conn, $_GET['permanent_delete_user']);

    // Get user image path
    $query = "SELECT profile_image FROM users WHERE id = '$user_id'";
    $result = mysqli_query($conn, $query);
    $user = mysqli_fetch_assoc($result);

    // Delete user record
    $delete_query = "DELETE FROM users WHERE id = '$user_id' AND is_deleted = 1";
    if (mysqli_query($conn, $delete_query)) {
        // Delete image file if exists
        if (!empty($user['profile_image']) && file_exists($user['profile_image'])) {
            unlink($user['profile_image']);
        }

        $_SESSION['message'] = "User permanently deleted!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error deleting user: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
    }

    header("Location: trash_user.php");
    exit();
}
?>
