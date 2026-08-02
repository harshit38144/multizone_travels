<?php
if (isset($_GET['url'])) {
    $url = $_GET['url'];
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: image/png');
        // Fetch the image and output it
        echo file_get_contents($url);
    } else {
        http_response_code(400);
    }
}
?>
