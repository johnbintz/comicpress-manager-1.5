<?php

function cpm_action_skip_first_run() {
  global $comicpress_manager;

  $comicpress_manager->messages[] = __("<strong>No directories were created.</strong> You'll need to create directories on your own.", 'comicpress-manager');

  $comicpress_manager->read_information_and_check_config();
}

?>