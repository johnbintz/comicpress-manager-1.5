<?php

function cpm_action_create_missing_posts() {
  global $comicpress_manager, $comicpress_manager_admin;

  $all_post_dates = array();
  foreach ($comicpress_manager->query_posts() as $comic_post) {
    $all_post_dates[] = date(CPM_DATE_FORMAT, strtotime($comic_post->post_date));
  }
  $all_post_dates = array_unique($all_post_dates);
  $duplicate_posts_within_creation = array();

  $posts_created = array();
  $thumbnails_written = array();
  $thumbnails_not_written = array();
  $invalid_filenames = array();
  $duplicate_posts = array();
  $new_thumbnails_not_needed = array();

  $execution_time = ini_get("max_execution_time");
  $max_posts_imported = (int)($execution_time / 2);

  $imported_post_count = 0;
  $safe_exit = false;

  if (strtotime($_POST['time']) === false) {
    $comicpress_manager->warnings[] = sprintf(__('<strong>There was an error in the post time (%1$s)</strong>.  The time is not parseable by strtotime().', 'comicpress-manager'), $_POST['time']);
  } else {
    foreach ($comicpress_manager->comic_files as $comic_file) {
      $comic_file = pathinfo($comic_file, PATHINFO_BASENAME);
      if (($result = $comicpress_manager->breakdown_comic_filename($comic_file)) !== false) {
        extract($result, EXTR_PREFIX_ALL, 'filename');

        $ok_to_create_post = !in_array($result['date'], $all_post_dates);
        $show_duplicate_post_message = false;
        $post_id = null;

        if (isset($duplicate_posts_within_creation[$result['date']])) {
          $ok_to_create_post = false;
          $show_duplicate_post_message = true;
          $post_id = $duplicate_posts_within_creation[$result['date']];
        }

        if ($ok_to_create_post) {
          if (isset($_POST['duplicate_check'])) {
            $ok_to_create_post = (($post_id = post_exists($post_title, $post_content, $post_date)) == 0);
          }
        } else {
          if (!isset($_POST['duplicate_check'])) {
            $ok_to_create_post = true;
          }
        }

        if ($ok_to_create_post) {
          if (($post_hash = $comicpress_manager->generate_post_hash($filename_date, $filename_converted_title)) !== false) {
            if (!is_null($post_id = wp_insert_post($post_hash))) {
              $imported_post_count++;
              $posts_created[] = get_post($post_id, ARRAY_A);
              $date = date(CPM_DATE_FORMAT, strtotime($filename_date));
              $all_post_dates[] = $date;
              $duplicate_posts_within_creation[$date] = $post_id;

              foreach (array('hovertext', 'transcript') as $field) {
                if (!empty($_POST["${field}-to-use"])) { update_post_meta($post_id, $field, $_POST["${field}-to-use"]); }
              }

              if (isset($_POST['thumbnails'])) {
                $wrote_thumbnail = $comicpress_manager_admin->write_thumbnail($comicpress_manager->path . '/' . $comic_file, $comic_file);
                if (!is_null($wrote_thumbnail)) {
                  if ($wrote_thumbnail) {
                    $thumbnails_written[] = $comic_file;
                  } else {
                    $thumbnails_not_written[] = $comic_file;
                  }
                } else {
                  $new_thumbnails_not_needed[] = $comic_file;
                }
              }
            }
          } else {
            $invalid_filenames[] = $comic_file;
          }
        } else {
          if ($show_duplicate_post_message) {
            $duplicate_posts[] = array(get_post($post_id, ARRAY_A), $comic_file);
          }
        }
      }
      if ($imported_post_count >= $max_posts_imported) {
        $safe_exit = true; break;
      }
    }
  }

  $comicpress_manager->import_safe_exit = $safe_exit;

  if ($safe_exit) {
    $comicpress_manager->messages[] = __("<strong>Import safely exited before you ran out of execution time.</strong> Scroll down to continue creating missing posts.", 'comicpress-manager');
  }

  if (count($posts_created) > 0) {
    $comicpress_manager_admin->display_operation_messages(compact('invalid_filenames', 'thumbnails_written',
                                           'thumbnails_not_written', 'posts_created',
                                           'duplicate_posts', 'new_thumbnails_not_needed'));
  } else {
    $comicpress_manager->messages[] = __("<strong>No new posts needed to be created.</strong>", 'comicpress-manager');
  }
}

?>
