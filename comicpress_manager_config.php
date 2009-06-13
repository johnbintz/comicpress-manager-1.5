<?php

// Select the level of access you want editors on your site to have.

$access_level = 10; // Administrator only
//$access_level = 5;  // Editors only
//$access_level = 2;  // Authors & Editors

define("CPM_SCALE_NONE", 0);
define("CPM_SCALE_IMAGEMAGICK", 1);
define("CPM_SCALE_GD", 2);

$result = get_option('comicpress-manager-cpm-date-format');
if (!empty($result)) {
  define("CPM_DATE_FORMAT", $result);
} else {
  define("CPM_DATE_FORMAT", "Y-m-d");
}

// for Windows users, your permissions will automatically be set to 0777 (writable).
// there is no easy way in PHP to modify permissions on an NTFS filesystem (and no
// permissions to speak of on FAT32!)

if (strpos(PHP_OS, "WIN") !== false) {
  // for Windows users
  define("CPM_FILE_UPLOAD_CHMOD", 0777);
} else {
  $result = get_option('comicpress-manager-cpm-upload-permissions');
  $chmod_to_use = 0664; // writable by owner and any group members (common)
  if (!empty($result)) {
    $requested_chmod_to_use = 0;
    $strlen_result = strlen($result);
    $is_ok = true;
    for ($i = 0; $i < $strlen_result; ++$i) {
      $char = $result{$strlen_result - 1 - $i};
      if (preg_match('#[0-7]#', $char) > 0) {
        $requested_chmod_to_use = $requested_chmod_to_use + ($char * pow(8, $i));
      } else {
        $is_ok = false;
      }
    }
    if ($is_ok) {
      $chmod_to_use = $requested_chmod_to_use;
    }
  }
  define("CPM_FILE_UPLOAD_CHMOD", $chmod_to_use);
}

// CPM_DOCUMENT_ROOT override

$result = get_option("comicpress-manager-cpm-document-root");
if (!empty($result)) {
  define('CPM_DOCUMENT_ROOT', untrailingslashit($result));
}

?>