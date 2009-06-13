<?php

//harmonious @zip @hash

function cpm_action_change_dates() {
  global $comicpress_manager;

  $comic_posts_to_date_shift = array();
  $comic_files_to_date_shift = array();
  $comic_post_target_date_counts = array();

  $wp_date_string_length  = strlen(date("Y-m-d"));
  $cpm_date_string_length = strlen(date(CPM_DATE_FORMAT));

  $comicpress_manager->is_cpm_managing_posts = true;

  // find all comic files that will be shifted
  foreach ($comicpress_manager->comic_files as $comic_file) {
    $comic_filename = pathinfo($comic_file, PATHINFO_BASENAME);
    $filename_info = $comicpress_manager->breakdown_comic_filename($comic_filename);
    $key = md5($comic_file);

    if (isset($_POST['dates'][$key])) {
      if ($_POST['dates'][$key] != $filename_info['date']) {
        $timestamp = strtotime($_POST['dates'][$key]);
        if (($timestamp !== false) && ($timestamp !== -1)) {
          $target_date = date(CPM_DATE_FORMAT, $timestamp);

          $new_comic_filename = $target_date . substr($comic_filename, $cpm_date_string_length);

          $comic_posts_to_date_shift[strtotime($filename_info['date'])] = $timestamp;
          if (!isset($comic_post_target_date_counts[$timestamp])) {
            $comic_post_target_date_counts[$timestamp] = 0;
          }
          $comic_post_target_date_counts[$timestamp]++;

          if (!isset($comic_files_to_date_shift[$timestamp])) {
            $comic_files_to_date_shift[$timestamp] = array($comic_filename, $new_comic_filename);
          }
        }
      }
    }
  }

  $comic_posts_to_change = array();

  $all_posts = $comicpress_manager->query_posts();

  // get the target dates for all files to move
  if (count($comic_posts_to_date_shift) > 0) {
    foreach ($all_posts as $comic_post) {
      $post_date_day = substr($comic_post->post_date, 0, $wp_date_string_length);
      $post_date_day_timestamp = strtotime($post_date_day);
      if (isset($comic_posts_to_date_shift[$post_date_day_timestamp])) {
        if ($comic_post_target_date_counts[$comic_posts_to_date_shift[$post_date_day_timestamp]] == 1) {
          $new_post_date = date("Y-m-d", $comic_posts_to_date_shift[$post_date_day_timestamp]) . substr($comic_post->post_date, $wp_date_string_length);
          $comic_posts_to_change[$comic_post->ID] = array($comic_post, $new_post_date);
        }
      }
    }
  }

  $final_post_day_counts = array();

  // intersect all existing and potential new posts, counting how many
  // posts occur on each day
  foreach ($all_posts as $comic_post) {
    if (isset($comic_posts_to_change[$comic_post->ID])) {
      $date_to_use = $comic_posts_to_change[$comic_post->ID][1];
    } else {
      $date_to_use = $comic_post->post_date;
    }

    $day_to_use = strtotime(substr($date_to_use, 0, $wp_date_string_length));
    if (!isset($final_post_day_counts[$day_to_use])) {
      $final_post_day_counts[$day_to_use] = 0;
    }
    $final_post_day_counts[$day_to_use]++;
  }

  $posts_moved = array();

  // move what can be moved
  foreach ($comic_posts_to_change as $id => $info) {
    list($comic_post, $new_post_date) = $info;
    $new_post_day = strtotime(substr($new_post_date, 0, $wp_date_string_length));
    if ($final_post_day_counts[$new_post_day] == 1) {
      $old_post_date = $comic_post->post_date;
      $comic_post->post_date = $new_post_date;
      $comic_post->post_date_gmt = get_gmt_from_date($new_post_date);
      wp_update_post($comic_post);
      $comicpress_manager->messages[] = sprintf(__('<strong>Post %1$s moved to %2$s.</strong>', 'comicpress-manager'), $id, date("Y-m-d", $new_post_day));
      $posts_moved[$new_post_day] = array($comic_post, $old_post_date);
    } else {
      $comicpress_manager->warnings[] = sprintf(__('<strong>Moving post %1$s to %2$s would cause two comic posts to exist on the same day.</strong>  This is not allowed in the automated process.', 'comicpress-manager'), $id, date("Y-m-d", $new_post_day));
    }
  }

  // try to move all the files, and roll back any changes to files and posts that fail
  foreach ($comic_post_target_date_counts as $target_date => $count) {
    if (!isset($final_post_day_counts[$target_date]) || ($final_post_day_counts[$target_date] == 1)) {
      if ($count > 1) {
        $comicpress_manager->warnings[] = sprintf(__("<strong>You are moving two comics to the same date: %s.</strong>  This is not allowed in the automated process.", 'comicpress-manager'), $target_date);
      } else {
        list($comic_filename, $new_comic_filename) = $comic_files_to_date_shift[$target_date];

        $roll_back_change = false;

        $calculate_do_move = array();

        foreach (array(
          array(__('comic folder', 'comicpress-manager'), 'comic_folder', ""),
          array(__('RSS feed folder', 'comicpress-manager'), 'rss_comic_folder', "rss"),
          array(__('archive folder', 'comicpress-manager'), 'archive_comic_folder', "archive")) as $folder_info) {
            list ($name, $property, $type) = $folder_info;

            $do_move = true;
            if ($type != "") {
              if ($comicpress_manager->separate_thumbs_folder_defined[$type]) {
                if ($comicpress_manager->thumbs_folder_writable[$type]) {
                  $do_move = ($comicpress_manager->get_cpm_option("${type}-generate-thumbnails") == 1);
                }
              }
              $calculate_do_move[$type] = $do_move;
            }

            if ($do_move) {
              $path = CPM_DOCUMENT_ROOT . '/' . $comicpress_manager->properties[$property];
              if (!file_exists($path)) {
                $comicpress_manager->errors[] = sprintf(__('The %1$s <strong>%2$s</strong> does not exist.', 'comicpress-manager'), $name, $comicpress_manager->properties[$property]);

                $roll_back_change = true;
              } else {
                if (file_exists($path . '/' . $comic_filename)) {
                  if (@rename($path . '/' . $comic_filename, $path . '/' . $new_comic_filename)) {
                    $comicpress_manager->messages[] = sprintf(__('<strong>Rename %1$s file %2$s to %3$s.</strong>', 'comicpress-manager'), $name, $comic_filename, $new_comic_filename);
                  } else {
                    $comicpress_manager->warnings[] = sprintf(__('<strong>The renaming of %1$s to %2$s failed.</strong>  Check the permissions on %3$s', 'comicpress-manager'), $comic_filename, $new_comic_filename, $path);

                    $roll_back_change = true;
                  }
                }
              }
            }
        }

        if ($roll_back_change) {
          foreach (array(
            array(__('comic folder', 'comicpress-manager'), 'comic_folder',""),
            array(__('RSS feed folder', 'comicpress-manager'), 'rss_comic_folder',"rss"),
            array(__('archive folder', 'comicpress-manager'), 'archive_comic_folder',"archive")) as $folder_info) {
              list ($name, $property) = $folder_info;

              $do_move = isset($calculate_do_move[$type]) ? $calculate_do_move[$type] : true;

              if ($do_move) {
                $path = CPM_DOCUMENT_ROOT . '/' . $comicpress_manager->properties[$property];
                if (file_exists($path . '/' . $new_comic_filename)) {
                  @rename($path . '/' . $new_comic_filename, $path . '/' . $comic_filename);
                  $comicpress_manager->messages[] = sprintf(__("<strong>Rolling back %s.</strong>", 'comicpress-manager'), $new_comic_filename);
                }
              }
          }

          if (isset($posts_moved[$target_date])) {
            list($comic_post, $old_post_date) = $posts_moved[$target_date];
            $comic_post->post_date = $old_post_date;
            $comic_post->post_date_gmt = get_gmt_from_date($old_post_date);
            wp_update_post($comic_post);
            $comicpress_manager->messages[] = sprintf(__('<strong>Rename error, rolling back post %1$s to %2$s.</strong>', 'comicpress-manager'), $comic_post->ID, $old_post_date);
          }
        }
      }
    }
  }

  $comicpress_manager->comic_files = $comicpress_manager->read_comics_folder();
}

?>