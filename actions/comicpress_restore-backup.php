<?php

function cpm_action_restore_backup() {
  global $comicpress_manager;

  $config_dirname = dirname($comicpress_manager->config_filepath);
  if (is_numeric($_POST['backup-file-time'])) {
    if (file_exists($config_dirname . '/comicpress-config.php.' . $_POST['backup-file-time'])) {
      if ($comicpress_manager->can_write_config) {
        if (@copy($config_dirname . '/comicpress-config.php.' . $_POST['backup-file-time'],
                  $config_dirname . '/comicpress-config.php') !== false) {

          $comicpress_manager->read_information_and_check_config();

          $comicpress_manager->messages[] = sprintf(__("<strong>Restored %s</strong>.  Check to make sure your site is functioning correctly.", 'comicpress-manager'), 'comicpress-config.php.' . $_POST['backup-file-time']);
        } else {
          $comicpress_manager->warnings[] = sprintf(__("<strong>Could not restore %s</strong>.  Check the permissions of your theme folder and try again.", 'comicpress-manager'), 'comicpress-config.php.' . $_POST['backup-file-time']);
        }
      }
    }
  }
}

?>