<?php

function cpm_action_do_first_run() {
  global $comicpress_manager, $blog_id;

  $dir_list = array(
    CPM_DOCUMENT_ROOT,
    CPM_DOCUMENT_ROOT . '/comics',
    CPM_DOCUMENT_ROOT . '/comics-rss',
    CPM_DOCUMENT_ROOT . '/comics-archive'
  );

  if ($is_wpmu = function_exists('get_site_option')) { $dir_list = cpm_wpmu_first_run_dir_list(); }

  $any_made = false;
  $all_made = true;

  foreach ($dir_list as $dir_to_make) {
    if (!file_exists($dir_to_make)) {
      $any_made = true;
      if (@mkdir($dir_to_make)) {
        if (!$is_wpmu) {
          $comicpress_manager->messages[] = sprintf(__("<strong>Directory created:</strong> %s", 'comicpress-manager'), $dir_to_make);
        }
      } else {
        $all_made = false;
        if (!$is_wpmu) {
          $comicpress_manager->warnings[] = sprintf(__("<strong>Unable to create directory:</strong> %s", 'comicpress-manager'), $dir_to_make);
        }
      }
    }
  }

  if (!$any_made) {
    $comicpress_manager->messages[] = __("<strong>All the directories were already found, nothing to do!</strong>", "comicpress-manager");
  }
  if ($is_wpmu) {
    if ($all_made) {
      $comicpress_manager->messages[] = sprintf(__("<strong>All directories created!</strong>", 'comicpress-manager'), $dir_to_make);
      cpm_wpmu_complete_first_run();
    } else {
      $comicpress_manager->warnings[] = sprintf(__("<strong>Unable to create directories!</strong> Contact your administrator.", 'comicpress-manager'), $dir_to_make);
    }
    $comicpress_manager->set_cpm_option("cpm-did-first-run", 1);
  }

  $comicpress_manager->did_first_run = true;

  $comicpress_manager->read_information_and_check_config();
}

?>