<?php
// Debugging script for routing issues
echo "<h1>Server Configuration Debug</h1>";

echo "<h2>Server Variables</h2>";
echo "<pre>";
print_r([
    'PHP_SELF' => $_SERVER['PHP_SELF'],
    'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'],
    'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'],
    'REQUEST_URI' => $_SERVER['REQUEST_URI'],
    'QUERY_STRING' => $_SERVER['QUERY_STRING']
]);
echo "</pre>";

echo "<h2>File Existence Check</h2>";
$files = [
    'shifts.php' => file_exists('shifts.php'),
    '/shifts.php' => file_exists('/shifts.php'),
    $_SERVER['DOCUMENT_ROOT'] . '/shifts.php' => file_exists($_SERVER['DOCUMENT_ROOT'] . '/shifts.php')
];

echo "<pre>";
print_r($files);
echo "</pre>";

echo "<h2>Current Directory Contents</h2>";
echo "<pre>";
print_r(scandir('.'));
echo "</pre>";
?>
