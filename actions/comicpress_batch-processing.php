<?php

//harmonious @zip @hash

function cpm_action_batch_processing() {
  global $comicpress_manager, $comicpress_manager_admin;

  $files_to_delete = array();
  $posts_to_delete = array();
  $thumbnails_to_regenerate = array();
  $files_to_redate = array();
  $posts_to_redate = array();
  $posts_to_generate = array();
  $posts_that_exist = array();

  $posts_to_recategorize = array();

  extract($comicpress_manager->normalize_storyline_structure());
  $comic_categories = array();
  foreach ($category_tree as $node) { $comic_categories[] = end(explode("/", $node)); }

  $comicpress_manager->is_cpm_managing_posts = true;

  foreach ($_POST as $field => $value) {
    if (($_POST['bulk-action'] != "-1") && ($_POST['bulk-action'] != "individual")) {
      $bulk_posts_updated = array();

      if (preg_match("#^(file|post),([^\,]*),(.*)$#", $field, $matches) > 0) {
        list ($all, $type, $date, $id) = $matches;

        if (isset($_POST["batch-${date}"])) {
          switch ($_POST['bulk-action']) {
            case "delete":
              switch ($type) {
                case "file":
                  if (($result = cpm_match_id_to_file($id)) !== false) {
                    $files_to_delete[] = $result;
                  }
                break;
                case "post": $posts_to_delete[] = $id; break;
              }
              break;
            case "regen-thumbs":
              if ($type == "file") {
                if (($result = cpm_match_id_to_file($id)) !== false) {
                  $thumbnails_to_regenerate[] = $result;
                }
              }
              break;
            case "edit":
              if ($type == "post") {
                foreach (array('hovertext' => 'bulk-hovertext',
                               'transcript' => 'bulk-transcript') as $meta_name => $post_name) {
                  if (isset($_POST[$post_name])) {
                    update_post_meta($id, $meta_name, $_POST[$post_name]);
                  }
                }

                $post_categories = wp_get_post_categories($id);
                $did_change = false;

                if (isset($_POST['bulk-storyline-in-comic-category'])) {
                  foreach ($comic_categories as $category_id) {
                    if (in_array($category_id, $_POST['bulk-storyline-in-comic-category'])) {
                      if (!in_array($category_id, $post_categories)) {
                        $did_change = true;
                        $post_categories[] = $category_id;
                      }
                    } else {
                      if (($index = array_search($category_id, $post_categories)) !== false) {
                        $did_change = true;
                        array_splice($post_categories, $index, 1);
                      }
                    }
                  }
                }

                if ($did_change) {
                  wp_set_post_categories($id, $post_categories);
                }

                $bulk_posts_updates[] = $id;
              }
              break;
            case "import":
              switch ($type) {
                case "file":
                  if (($result = cpm_match_id_to_file($id)) !== false) {
                    $posts_to_generate[] = $result;
                  }
                  break;
                case "post":
                  $posts_that_exist[] = $date;
                  break;
              }
              break;
          }
        }
      }
    } else {
      if (preg_match('#^([0-9]+)-in-comic-category#', $field, $matches) > 0) {
        if (get_post($matches[1])) {
          $posts_to_recategorize[$matches[1]] = $value;
        }
      }

      if (preg_match("#^delete-file-(.*)$#", $field, $matches) > 0) {
        if (($result = cpm_match_id_to_file($matches[1])) !== false) {
          $files_to_delete[] = $result;
        }
      }

      if (preg_match("#^delete-post-(.*)$#", $field, $matches) > 0) {
        if (get_post($matches[1])) {
          $posts_to_delete[] = $matches[1];
        }
      }

      if (preg_match('#^regen-(.*)$#', $field, $matches) > 0) {
        if (($result = cpm_match_id_to_file($matches[1])) !== false) {
          $thumbnails_to_regenerate[] = $result;
        }
      }

      if (preg_match("#^do-redate-file-(.*)$#", $field, $matches) > 0) {
        if (($result = cpm_match_id_to_file($matches[1])) !== false) {
          $files_to_redate[$result] = $value;
        }
      }

      if (preg_match("#^generate-post-(.*)$#", $field, $matches) > 0) {
        if (($result = cpm_match_id_to_file($matches[1])) !== false) {
          $posts_to_generate[] = $result;
        }
      }

      if (preg_match("#^delete-post-(.*)$#", $field, $matches) > 0) {
        if (get_post($matches[1])) {
          $posts_to_redate[$matches[1]] = $value;
        }
      }
    }
  }

  $did_generate_thumbs = array();

  $ok_to_keep_uploading = true;
  $files_created_in_operation = array();

  if (count($thumbnails_to_regenerate) > 0) {
    $thumbnails_written = array();
    $thumbnails_not_written = array();
    foreach ($thumbnails_to_regenerate as $file) {
      $comic_file = pathinfo($file, PATHINFO_BASENAME);
      $wrote_thumbnail = $comicpress_manager_admin->write_thumbnail($file, $comic_file, true);

      if (!is_null($wrote_thumbnail)) {
        if (is_array($wrote_thumbnail)) {
          $files_created_in_operation = array_merge($files_created_in_operation, $wrote_thumbnail);

          $thumbnails_written[] = $comic_file;
        } else {
          $thumbnails_not_written[] = $comic_file;
        }
      }
      if (function_exists('cpm_wpmu_is_over_storage_limit')) {
        if (cpm_wpmu_is_over_storage_limit()) { $ok_to_keep_uploading = false; break; }
      }
    }

    if (count($thumbnails_written) > 0) {
      $comicpress_manager->messages[] = sprintf(__("<strong>The following thumbnails were written:</strong> %s", 'comicpress-manager'), implode(", ", $thumbnails_written));
    }

    if (count($thumbnails_not_written) > 0) {
      $comicpress_manager->warnings[] = sprintf(__("<strong>The following thumbnails were not written:</strong> %s", 'comicpress-manager'), implode(", ", $thumbnails_not_written));
    }
  }

  if (count($bulk_posts_updates) > 0) {
    $comicpress_manager->messages[] = sprintf(__("<strong>The following posts were updated:</strong> %s", 'comicpress-manager'), implode(", ", $bulk_posts_updates));
  }

  if (count($files_to_delete) > 0) {
    $comic_files_deleted = array();
    foreach ($files_to_delete as $file) {
      $comic_file = pathinfo($file, PATHINFO_BASENAME);
      $delete_targets = array($file);
      foreach ($comicpress_manager->thumbs_folder_writable as $type => $value) {
        $path = CPM_DOCUMENT_ROOT . '/' . $comicpress_manager->properties[$type . "_comic_folder"];
        if (($subdir = $comicpress_manager->get_subcomic_directory()) !== false) {
          $path .= '/' . $subdir;
        }
        $path .= '/' . $comic_file;
        $delete_targets[] = $path;;
      }
      foreach ($delete_targets as $target) {
        if (file_exists($target)) {
          @unlink($target);
        }
      }
      $comic_files_deleted[] = $comic_file;
    }

    $comicpress_manager->messages[] = sprintf(__("<strong>The following comic files and their associated thumbnails were deleted:</strong> %s", 'comicpress-manager'), implode(", ", $comic_files_deleted));
  }

  if (count($posts_to_delete) > 0) {
    foreach ($posts_to_delete as $post) {
      wp_delete_post($post);
    }
    $comicpress_manager->messages[] = sprintf(__("<strong>The following posts were deleted:</strong> %s", 'comicpress-manager'), implode(", ", $posts_to_delete));
  }

  $master_category = end(explode("/", reset($category_tree)));

  foreach ($posts_to_generate as $file) {
    $ok = false;
    $comic_file = pathinfo($file, PATHINFO_BASENAME);
    if (($result = $comicpress_manager->breakdown_comic_filename($comic_file)) !== false) {
      if (!in_array(date("Y-m-d", strtotime($result['date'])), $posts_that_exist)) {
        if (($post_hash = $comicpress_manager->generate_post_hash($result['date'], $result['converted_title'])) !== false) {

          $post_hash['post_category'] = array($master_category);
          $ok = !is_null($post_id = wp_insert_post($post_hash));
        }
      }
    }

    if ($ok) {
      $comicpress_manager->messages[] = sprintf(__('<strong>Created post %1$s for %2$s.</strong>', 'comicpress-manager'), $post_id, $comic_file);
    } else {
      $comicpress_manager->warnings[] = sprintf(__("<strong>Could not create post for %s.</strong>", 'comicpress-manager'), $comic_file);
    }
  }

  foreach ($posts_to_recategorize as $id => $requested_comic_categories) {
    if (!in_array($id, $posts_to_delete)) {
      $post_categories = wp_get_post_categories($id);
      $did_change = false;

      foreach ($comic_categories as $category_id) {
        if (in_array($category_id, $requested_comic_categories)) {
          if (!in_array($category_id, $post_categories)) {
            $did_change = true;
            $post_categories[] = $category_id;
          }
        } else {
          if (($index = array_search($category_id, $post_categories)) !== false) {
            $did_change = true;
            array_splice($post_categories, $index, 1);
          }
        }
      }

      if ($did_change) {
        wp_set_post_categories($id, $post_categories);
        $comicpress_manager->messages[] = sprintf(__("<strong>Storyline for post %s updated.</strong>", 'comicpress-manager'), $id);
      }
    }
  }

  if (!$ok_to_keep_uploading) {
    $comicpress_manager->warnings = array($comicpress_manager->wpmu_disk_space_message);

    foreach ($files_created_in_operation as $file) { @unlink($file); }
  }

  $comicpress_manager->comic_files = $comicpress_manager->read_comics_folder();
}

function cpm_match_id_to_file($id) {
  global $comicpress_manager;

  foreach ($comicpress_manager->comic_files as $file) {
    $filename = str_replace(".", "_", pathinfo($file, PATHINFO_BASENAME));
    if ($filename == $id) { return $file; }
  }
  return false;
}

?>