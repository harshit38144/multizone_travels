<?php
function Imageupload($dir, $inputname, $allext, $pass_width, $pass_height, $pass_size, $newname)
{
	if (file_exists($_FILES["$inputname"]["tmp_name"])) {
		$file_extension = strtolower(pathinfo($_FILES["$inputname"]["name"], PATHINFO_EXTENSION));
		$error = "";
		if (in_array($file_extension, $allext)) {
			list($width, $height, $type, $attr) = getimagesize($_FILES["$inputname"]["tmp_name"]);
			$image_weight = $_FILES["$inputname"]["size"];
			if ($width <= "$pass_width" && $height <= "$pass_height" && $image_weight <= "$pass_size") {
				$tmp = $_FILES["$inputname"]["tmp_name"];
				$extension[1] = "jpg";
				$name = $newname . "." . $extension[1];
				if (move_uploaded_file($tmp, "$dir" . $name)) {
					return true;
				}
			} else {
				$error .= "Please upload photo size of $pass_width X $pass_height !!!";
			}
		} else {
			$error .= "Please upload an image !!!";
		}
	} else {
		$error .= "Please Select an image !!!";
	}
	return $error;
}

?>