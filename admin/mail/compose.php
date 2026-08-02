<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = 'inbox.php?compose=1';
if ($query !== '') {
    $target .= '&' . $query;
}
header('Location: ' . $target);
exit;
