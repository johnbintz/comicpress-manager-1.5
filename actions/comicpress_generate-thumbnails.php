<?php

function cpm_action_generate_thumbnails() {
  global $comicpress_manager;

  $ok_to_keep_uploading = true;
  $files_created_in_operation = array();

  foreach ($_POST['comics'] as $comic) {
    $comic_file = stripslashes(pathinfo($comic, PATHINFO_BASENAME));

    $wrote_thumbnail = cpm_write_thumbnail($comicpress_manager->path . '/' . $comic_file, $comic_file, true);

    if (!is_null($wrote_thumbnail)) {
      if (is_array($wrote_thumbnail)) {
        $files_created_in_operation = array_merge($files_created_in_operation, $wrote_thumbnail);

        $comicpress_manager->messages[] = sprintf(__("<strong>Wrote thumbnail for %s.</strong>", 'comicpress-manager'), $comic_file);
      } else {
        $comicpress_manager->warnings[] = sprintf(__("<strong>Could not write thumbnail for %s.</strong> Check the permissions on the thumbnail directories.", 'comicpress-manager'), $comic_file);
      }
    }
    if (function_exists('cpm_wpmu_is_over_storage_limit')) {
      if (cpm_wpmu_is_over_storage_limit()) { $ok_to_keep_uploading = false; break; }
    }
  }

  if (!$ok_to_keep_uploading) {
    $comicpress_manager->messages = array();
    $comicpress_manager->warnings = array($comicpress_manager->wpmu_disk_space_message);

    foreach ($files_created_in_operation as $file) { @unlink($file); }
  }
}

?>