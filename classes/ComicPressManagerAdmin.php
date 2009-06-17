<?php

require_once('ComicPressFileOperations.php');
require_once('ComicPressView.php');

class ComicPressManagerAdmin {
  var $_uploaded_files = array();
  
  var $return_values = array(
    "GD_RENAME_FILE" => 'gd rename file',
    "CONVERT_CMYK" => 'convert cmyk',
    "INVALID_IMAGE_TYPE" => 'invalid image type',
    "INVALID_FILENAME" => 'invalid filename',
    "NOT_HANDLED" => 'not handled',
    "NOT_UPLOADED" => 'not uploaded',
    "OBFUSCATED_RENAMED" => "obfuscated renamed",
    "FILE_CREATED" => "file created",
    "FILE_UPLOADED" => "file uploaded",
    "POST_CREATED" => "post created",
    "DUPLICATE_POST" => "duplicate post"
  );
  
  function ComicPressManagerAdmin() {
    global $comicpress_manager, $pagenow;
    
    $this->_f = new ComicPressFileOperations();
    
    add_action("add_category_form_pre", array($this, "comicpress_categories_warning"));
    add_action("pre_post_update", array($this, "handle_pre_post_update"));
    add_action("save_post", array($this, "handle_edit_post"));
    add_action("edit_form_advanced", array($this, "show_comic_caller"));
    add_action("delete_post", array($this, "handle_delete_post"));
    add_action("create_category", array($this, "rebuild_storyline_structure"));
    add_action("delete_category", array($this, "rebuild_storyline_structure"));
    add_action("edit_category", array($this, "rebuild_storyline_structure"));
    add_filter("manage_posts_columns", array($this, "manage_posts_columns"));
    add_action("manage_posts_custom_column", array($this, "manage_posts_custom_column"));
    add_action("admin_menu", array($this, "setup_admin_menu"));

    foreach ($_FILES as $field_name => $info) {
      if (is_uploaded_file($_FILES[$field_name]['tmp_name'])) {
        if ($_FILES[$field_name]['error'] != 0) {
          switch ($_FILES[$key]['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
              $comicpress_manager->warnings[] = sprintf(__("<strong>The file you uploaded was too large.</strong>  The max allowed filesize for uploads to your server is %s.", 'comicpress-manager'), ini_get('upload_max_filesize'));
              break;
            case UPLOAD_ERR_NO_FILE:
              break;
            default:
              $comicpress_manager->warnings[] = sprintf(__("<strong>There was an error in uploading.</strong>  The <a href='http://php.net/manual/en/features.file-upload.errors.php'>PHP upload error code</a> was %s.", 'comicpress-manager'), $_FILES[$key]['error']);
              break;
          }
        } else {
          $this->_uploaded_files[$field_name] = $info;
        }
      }
    }
  }

  function _show_view($view) {
    require_once(dirname(__FILE__) . '/views/' . $view . ".php");
    $view = new $view();
		if (method_exists($view, "init")) { $view->init(); }
    $view->render();
  }

  function show_comic_caller() {
    $this->_show_view('ComicPressEditPostShowComic');
  }

  /**
   * Show a warning at the top of Manage -> Categories if not enough categories
   * are defined.
   */
  function comicpress_categories_warning() {
    if (count(get_all_category_ids()) < 2) {
      echo '<div style="margin: 10px; padding: 5px; background-color: #440008; color: white; border: solid #a00 1px">';
      echo __("Remember, you need at least two categories defined in order to use ComicPress.", 'comicpress-manager');
      echo '</div>';
    }  
  }

  function _verify_post_before_hook($post_id) {
    global $comicpress_manager;
    
    $ok = false;
    if (!$comicpress_manager->is_cpm_managing_posts) {
      if ($comicpress_manager->get_cpm_option("cpm-edit-post-integrate") == 1) {
        $post = get_post($post_id);
        if (!empty($post)) {
          if (!in_array($post->post_type, array("attachment", "revision", "page"))) {
            $ok = $post;
          }
        }
      }
    }
    return $ok;  
  }
  
  function _is_post_in_comic_category($post_id) {
    global $comicpress_manager;
    
    $ok = false;
    extract($comicpress_manager->get_all_comic_categories());
    $post_categories = wp_get_post_categories($post_id);
    foreach ($category_tree as $node) {
      $parts = explode("/", $node);
      if (in_array(end($parts), $post_categories)) {
        $ok = true; break;
      }
    }
    
    return $ok;
  }

  /**
   * Handle updating a post.  If Edit Post Integration is enabled, and the post
   * date is changing, rename the file as necessary.
   */
  function handle_pre_post_update($post_id) {
    global $comicpress_manager;

    if (($post = $this->_verify_post_before_hook($post_id)) !== false) {
      if ($this->_is_post_in_comic_category($post_id)) {
        $original_timestamp = false;
        foreach (array("post_date", "post_date_gmt") as $param) {
          $result = strtotime(date("Y-m-d", strtotime($post->{$param})));
          if ($result !== false) {
            $original_timestamp = $result; break;
          }
        }

        $new_timestamp = strtotime(implode("-", array($_POST['aa'], $_POST['mm'], $_POST['jj'])));

        if (!empty($original_timestamp) && !empty($new_timestamp)) {
          $original_date = date(CPM_DATE_FORMAT, $original_timestamp);
          $new_date = date(CPM_DATE_FORMAT, $new_timestamp);
          
          if ($original_date !== $new_date) {

            if (empty($comicpress_manager->comic_files)) {
              $comicpress_manager->read_information_and_check_config();
            }

            foreach ($comicpress_manager->comic_files as $file) {
              $filename = pathinfo($file, PATHINFO_BASENAME);
              if (($result = $comicpress_manager->breakdown_comic_filename($filename)) !== false) {
                if ($result['date'] == $original_date) {
                  foreach ($this->find_thumbnails_by_filename($file) as $thumb_file) {
                    $this->_f->rename($thumb_file, str_replace("/${original_date}", "/${new_date}", $thumb_file));
                  }

                  $this->_f->rename($file, str_replace("/${original_date}", "/${new_date}", $file));
                }
              }
            }

            $comicpress_manager->comic_files = null;
          }
        }
      }
    }
  }

  /**
   * Find all the thumbnails for a particular image root.
   */
  function find_thumbnails_by_filename($filename) {
    global $comicpress_manager;

    $thumbnails_found = array();
    foreach ($comicpress_manager->folders as $folder_info) {
      list($name, $property, $is_fatal, $type) = $folder_info;
      
      if ($type !== "") {        
        if ($comicpress_manager->separate_thumbs_folder_defined[$type]) {
          $thumb_filename = str_replace('/' . $comicpress_manager->properties["comic_folder"] . '/', '/' . $comicpress_manager->properties[$property] . '/', $filename);

          if ($this->_f->file_exists($thumb_filename)) {
            $thumbnails_found[$type] = substr(realpath($thumb_filename), CPM_STRLEN_REALPATH_DOCUMENT_ROOT);
          }
        }
      }
    }

    return $thumbnails_found;
  }

  /**
   * Handle editing a post.
   */
  function handle_edit_post($post_id) {
    global $comicpress_manager;

    if (($post = $this->_verify_post_before_hook($post_id)) !== false) {
      $ok = $this->_is_post_in_comic_category($post_id);
      
      extract($comicpress_manager->get_all_comic_categories());
      
      if (isset($this->_uploaded_files['comicpress-replace-image']) && !$ok) {
        $post_categories = wp_get_post_categories($post_id);
        $post_categories[] = end(explode("/", reset($category_tree)));
        wp_set_post_categories($post_id, $post_categories);
        $ok = true;
      }

      if ($ok) {
        $new_date = date(CPM_DATE_FORMAT, strtotime(implode("-", array($_POST['aa'], $_POST['mm'], $_POST['jj']))));

        foreach (array('hovertext' => 'comicpress-img-title',
                       'transcript' => 'comicpress-transcript') as $meta_name => $post_name) {
          if (isset($_POST[$post_name])) {
            update_post_meta($post_id, $meta_name, $_POST[$post_name]);
          }
        }

        if (isset($this->_uploaded_files['comicpress-replace-image'])) {
          $_POST['override-date'] = $new_date;
          $this->handle_file_uploads(array('comicpress-replace-image'));
        }
      }
    }
  }

  function handle_uploaded_file($temp_path, $target_root, $source_filename, $target_filename) {
    global $comicpress_manager;
    
    $returns = array();

    extract($this->do_gd_file_check_on_upload($temp_path, $target_filename));

    if ($result !== false) {
      extract($result, EXTR_PREFIX_ALL, "filename");

      if ($file_ok) {
        if (($obfuscated_filename = $this->obfuscate_filename($target_filename)) !== $target_filename) {
          $returns[] = array($this->return_values['OBFUSCATED_RENAMED'], $target_filename, $obfuscate_filename, $result['converted_title']);
          
          $target_filename = $obfuscated_filename;
        }

        $this->_f->rename($temp_path, $target_root . '/' . $target_filename);
        
        if ($this->_f->file_exists($target_root . '/' . $target_filename)) {        
          $returns[] = array($this->return_values['FILE_CREATED'], $target_root . '/' . $target_filename);
          $returns[] = array($this->return_values['FILE_UPLOADED'], $target_filename);
          
          if ($gd_did_rename) {
            $returns[] = array($this->return_values['GD_RENAME_FILE'], $source_filename);
          }

          if ($is_cmyk) {
            $returns[] = array($this->return_values['CONVERT_CMYK'], $source_filename);
          }
        } else {
          $returns[] = array($this->return_values['NOT_UPLOADED'], $source_filename);
        }
      } else {
        $returns[] = array($this->return_values['INVALID_IMAGE_TYPE'], $source_filename);
      }
    } else {
      $returns[] = array($this->return_values['NOT_HANDLED'], $source_filename);
    }
    
    return $returns;
  }

  function _try_upload_replace($target_filename, $target_root) {
    global $comicpress_manager;

    if (!empty($_POST['overwrite-existing-file-choice'])) {

      $original_filename = $target_filename;
      $target_filename = $_POST['overwrite-existing-file-choice'];
      if (get_magic_quotes_gpc()) {
        $target_filename = stripslashes($target_filename);
      }

      if (pathinfo($original_filename, PATHINFO_EXTENSION) != pathinfo($target_filename, PATHINFO_EXTENSION)) {
        if ($this->_f->unlink($target_root . '/' . $target_filename)) {
          foreach ($comicpress_manager->get_thumbnails_to_generate() as $type) {
            $path = CPM_DOCUMENT_ROOT . '/' . $comicpress_manager->properties[$type . "_comic_folder"];
            if (($subdir = $comicpress_manager->get_subcomic_directory()) !== false) {
              $path .= '/' . $subdir;
            }
            $this->_f->unlink($path . '/' . $target_filename);
          }
        }

        $target_filename = preg_replace('#\.[^\.]+$#', '', $target_filename) . '.' . pathinfo($original_filename, PATHINFO_EXTENSION);
      }

      return $target_filename;
    }
    
    return false;
  }

  function _generate_post_for_uploaded_file($target_filename, $override_converted_title = null) {
    global $comicpress_manager;
    
    if (($result = $comicpress_manager->breakdown_comic_filename($target_filename)) !== false) {
      extract($result, EXTR_PREFIX_ALL, "filename");
      if (!empty($override_converted_title)) {
        $filename_converted_title = $override_converted_title;
      }
      if (($post_hash = $comicpress_manager->generate_post_hash($filename_date, $filename_converted_title)) !== false) {
        extract($post_hash);
        $ok_to_create_post = true;
        if (isset($_POST['duplicate_check'])) {
          $ok_to_create_post = (($post_id = post_exists($post_title, $post_content, $post_date)) == 0);
        }

        if ($ok_to_create_post) {
          if (!is_null($post_id = wp_insert_post($post_hash))) {
            foreach (array('hovertext', 'transcript') as $field) {
              if (!empty($_POST["${field}-to-use"])) { update_post_meta($post_id, $field, $_POST["${field}-to-use"]); }
            }
            
            return array($this->return_values['POST_CREATED'], get_post($post_id, ARRAY_A));
          }
        } else {
          return array($this->return_values['DUPLICATE_POST'], get_post($post_id, ARRAY_A), $target_filename);
        }
      }
    }
    return array($this->return_values['INVALID_FILENAME'], $target_filename);
  }

  /**
   * Handle uploading a set of files.
   * @param array $files A list of valid $_FILES keys to process.
   */
  function handle_file_uploads($files) {
    global $comicpress_manager;

    $posts_created = array();
    $duplicate_posts = array();
    $files_uploaded = array();
    $thumbnails_written = array();
    $invalid_filenames = array();
    $thumbnails_not_written = array();
    $files_not_uploaded = array();
    $invalid_image_types = array();
    $gd_rename_file = array();
    $did_convert_cmyk_jpeg = array();

    $target_root = CPM_DOCUMENT_ROOT . '/' . $comicpress_manager->properties[$_POST['upload-destination'] . "_folder"];
    if (($subdir = $comicpress_manager->get_subcomic_directory()) !== false) {
      $target_root .= '/' . $subdir;
    }
    
    $write_thumbnails = isset($_POST['thumbnails']) && ($_POST['upload-destination'] == "comic");
    $new_post = isset($_POST['new_post']) && ($_POST['upload-destination'] == "comic");

    $ok_to_keep_uploading = true;
    $files_created_in_operation = array();

    $filename_original_titles = array();

    foreach ($files as $key) {
      if (isset($this->_uploaded_files[$key])) {
        $temp_paths_and_targets = array();
        if (strpos($_FILES[$key]['name'], ".zip") !== false) {
        
          //harmonious zip_open zip_entry_name zip_read zip_entry_read zip_entry_open zip_entry_filesize zip_entry_close zip_close
          if (extension_loaded("zip")) {
            if (is_resource($zip = zip_open($_FILES[$key]['tmp_name']))) {
              while ($zip_entry = zip_read($zip)) {
                if (zip_entry_open($zip, $zip_entry, "r")) {
                  $comic_file = zip_entry_name($zip_entry);
                  $temp_path = $target_root . '/' . md5($comic_file . rand());
                  $this->_f->file_write_contents($temp_path,
                                    zip_entry_read($zip_entry,
                                                   zip_entry_filesize($zip_entry)));

                  $target_filename = pathinfo(zip_entry_name($zip_entry), PATHINFO_BASENAME);
                  
                  $temp_paths_and_targets[] = compact('comic_file', 'temp_path', 'target_filename');
                   zip_entry_close($zip_entry);
                }
              }
              zip_close($zip);
            }
          } else {
            $comicpress_manager->warnings[] = sprintf(__("The Zip extension is not installed. %s was not processed.", 'comicpress-manager'), $_FILES[$key]['name']);
          }
          //harmonious_end
        } else {
          $target_filename = $_FILES[$key]['name'];
          if (get_magic_quotes_gpc()) {
            $target_filename = stripslashes($target_filename);
          }

          if (($upload_replace_result = $this->_try_upload_replace($target_filename, $target_root)) !== false) {
            $new_post = false;
            
            $comicpress_manager->messages[] = sprintf(__('Uploaded file <strong>%1$s</strong> renamed to <strong>%2$s</strong>.', 'comicpress-manager'), $target_filename, $upload_replace_result);
      
            $target_filename = $upload_replace_result;
            $result = $comicpress_manager->breakdown_comic_filename($target_filename);
          } else {
            if (count($files) == 1) {
              if (!empty($_POST['override-date'])) {
                $date = strtotime($_POST['override-date']);
                if (($date !== false) && ($date !== -1)) {
                  $new_date = date(CPM_DATE_FORMAT, $date);

                  $old_filename = $target_filename;
                  if (($target_result = $comicpress_manager->breakdown_comic_filename($target_filename, true)) !== false) {
                    $target_filename = $new_date . $target_result['title'] . '.' . pathinfo($target_filename, PATHINFO_EXTENSION);
                  } else {
                    $target_filename = $new_date . '-' . $target_filename;
                  }

                  if ($old_filename !== $target_filename) {
                    $comicpress_manager->messages[] = sprintf(__('Uploaded file %1$s renamed to %2$s.', 'comicpress-manager'), $_FILES[$key]['name'], $target_filename);
                  }

                  $result = $comicpress_manager->breakdown_comic_filename($target_filename);
                } else {
                  if (preg_match('/\S/', $_POST['override-date']) > 0) {
                    $comicpress_manager->warnings[] = sprintf(__("Provided override date %s is not parseable by strtotime().", 'comicpress-manager'), $_POST['override-date']);
                  }
                }
              }
            }
            $result = $comicpress_manager->breakdown_comic_filename($target_filename, true);
            if ($result !== false) { // bad file, can we get a date attached?
              if (isset($_POST['upload-date-format']) && !empty($_POST['upload-date-format'])) {
                $target_filename = date(CPM_DATE_FORMAT, strtotime($result['date'])) .
                                   $result['title'] . '.' . pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION);
              }
            }
          }

          $comic_file = $_FILES[$key]['name'];
          $temp_path = $_FILES[$key]['tmp_name'];
          $temp_paths_and_targets[] = compact('comic_file', 'temp_path', 'target_filename');
        }
        
        foreach ($temp_paths_and_targets as $info) {
          extract($info);
          
          if (file_exists($temp_path)) {
            $results = $this->handle_uploaded_file($temp_path, $target_root, $comic_file, $target_filename);
            
            foreach ($results as $result) {
              switch ($result[0]) {
                case $this->return_values['OBFUSCATED_RENAMED']:
                  $comicpress_manager->messages[] = sprintf(__('Uploaded file %1$s renamed to %2$s.', 'comicpress-manager'), $result[1], $result[2]);
                  $filename_original_titles[$result[2]] = $result[3];
                  break;
                case $this->return_values['FILE_CREATED']:
                  $files_created_in_operation[] = $result[1];
                  break;
                case $this->return_values['FILE_UPLOADED']:
                  $files_uploaded[] = $result[1];
                  break;
                case $this->return_values['GD_RENAME_FILE']:
                  $gd_rename_file[] = $result[1];
                  break;
                case $this->return_values['CONVERT_CMYK']:
                  $did_convert_cmyk_jpeg[] = $result[1];
                  break;
                case $this->return_values['INVALID_IMAGE_TYPE']:
                  $invalid_image_types[] = $result[1];
                  break;
                case $this->return_values['NOT_HANDLED']:
                  $invalid_filenames[] = $result[1];
                  break;
                case $this->return_values['NOT_UPLOADED']:
                  $files_not_uploaded[] = $result[1];                        
                  break;
              }
            }

            if (($result = $comicpress_manager->breakdown_comic_filename($target_filename, true)) !== false) {
              extract($result, EXTR_PREFIX_ALL, 'filename');
              $target_path = $target_root . '/' . $target_filename;

              if (!empty($_POST['upload-date-format'])) {
                $target_filename = date(CPM_DATE_FORMAT, strtotime($result['date'])) .
                                    $result['title'] . '.' . pathinfo($target_filename, PATHINFO_EXTENSION);
              }
              
              if (   ($comicpress_manager->scale_method_cache == CPM_SCALE_IMAGEMAGICK)
                  && ($comicpress_manager->get_cpm_option('cpm-strip-icc-profiles') == "1")
                  && !empty($output_file)) {
                $temp_output_file = $output_file . '.' . md5(rand());

                $command = array("convert",
                                 "\"${output_file}\"",
                                 "-strip",
                                 "\"${temp_output_file}\"");

                $strip_profiles = escapeshellcmd(implode(" ", $command));

                exec($strip_profiles);

                if (file_exists($temp_output_file)) {
                  $this->_f->unlink($output_file);
                  $this->_f->rename($temp_output_file, $output_file);
                }
              }
            } else {
              $invalid_filenames[] = $comic_file;
            }
          } else {
            $invalid_filenames[] = $comic_file;
          }
          @unlink($temp_path);
        }
      }
    }
    
    if (function_exists('get_site_option')) {
      if (cpm_wpmu_is_over_storage_limit()) { $ok_to_keep_uploading = false; break; }
    }

    if ($ok_to_keep_uploading) {
      foreach ($files_uploaded as $target_filename) {
        $target_path = $target_root . '/' . $target_filename;
        $this->_f->chmod($target_path, CPM_FILE_UPLOAD_CHMOD);
        if ($write_thumbnails) {
          $wrote_thumbnail = $this->write_thumbnail($target_path, $target_filename, true);
        }
        
        if (!is_null($wrote_thumbnail)) {
          if (is_array($wrote_thumbnail)) {
            $thumbnails_written[] = $target_filename;
            $files_created_in_operation = array_merge($files_created_in_operation, $wrote_thumbnail);
          } else {
            $thumbnails_not_written[] = $target_filename;
          }
        }
      }
      if (function_exists('get_site_option')) {
        if (cpm_wpmu_is_over_storage_limit()) { $ok_to_keep_uploading = false; }
      }
    }

    if ($ok_to_keep_uploading) {
      if ($new_post) {
        foreach ($files_uploaded as $target_filename) {
          $override = (isset($filename_original_titles[$target_filename]) ? $filename_original_titles[$target_filename] : null);
          $result = $this->_generate_post_for_uploaded_file($target_filename, $override);
          
          switch ($result[0]) {
            case $this->return_values['POST_CREATED']:
              $posts_created[] = $result[1];
              break;
            case $this->return_values['DUPLICATE_POST']:
              $duplicate_posts[] = array($result[1], $result[2]);
              break;
            case $this->return_values['INVALID_FILENAME']:
              $invalid_filenames[] = $result[1];
              break;
          }
        }
      }
      $this->display_operation_messages(compact('invalid_filenames', 'files_uploaded', 'files_not_uploaded',
                                             'thumbnails_written', 'thumbnails_not_written', 'posts_created',
                                             'duplicate_posts', 'invalid_image_types', 'gd_rename_file',
                                             'did_convert_cmyk_jpeg'));
    } else {
      $comicpress_manager->messages = array();
      $comicpress_manager->warnings = array($comicpress_manager->wpmu_disk_space_message);

      foreach ($files_created_in_operation as $file) { @unlink($file); }
    }

    return array($posts_created, $duplicate_posts);
  }

  /**
   * Check an uploaded file with GD.
   */
  function do_gd_file_check_on_upload($check_file_path, $target_filename) {
    global $comicpress_manager;

    $file_ok = true;
    $is_cmyk = false;

    $result = $comicpress_manager->breakdown_comic_filename($target_filename, true);

    if (extension_loaded("gd") && ($comicpress_manager->get_cpm_option('cpm-perform-gd-check') == 1)) {
      $file_ok = (($image_info = $comicpress_manager->gd_processor->get_image_size($check_file_path)) !== false);

      if ($file_ok) {
        if (($image_info[2] == IMAGETYPE_JPEG) && ($image_info['channels'] == 4)) {
          $is_cmyk = true;
          $file_ok = false;
          $temp_check_file_path = $check_file_path . md5(rand());
          $method = $comicpress_manager->scale_method;
          if (!empty($method)) {
            if ($method->convert_to_rgb($check_file_path, 
                                    $temp_check_file_path, 
                                    $comicpress_manager->get_cpm_option("cpm-thumbnail-quality"))) {
              $this->_f->rename($temp_check_file_path, $check_file_path);
              $file_ok = true;
            }
          }
        }

        if ($file_ok) {
          $current_extension = strtolower(pathinfo($target_filename, PATHINFO_EXTENSION));
          if ($current_extension != "") {
            $remove_extension = false;
            switch($image_info[2]) {
              case IMAGETYPE_GIF:
                $remove_extension = !in_array($current_extension, array("gif"));
                break;
              case IMAGETYPE_JPEG:
                $remove_extension = !in_array($current_extension, array("jpg", "jpeg"));
                break;
              case IMAGETYPE_PNG:
                $remove_extension = !in_array($current_extension, array("png"));
                break;
            }
            
            if ($remove_extension) {
              $target_filename = preg_replace('#\.[^\.]+$#', '', $target_filename);
            }
          }
          
          if (pathinfo($target_filename, PATHINFO_EXTENSION) == "") {
            $new_extension = "";
            switch($image_info[2]) {
              case IMAGETYPE_GIF:  $new_extension = "gif"; break;
              case IMAGETYPE_JPEG: $new_extension = "jpg"; break;
              case IMAGETYPE_PNG:  $new_extension = "png"; break;
            }

            if ($new_extension != "") { $target_filename .= '.' . $new_extension; }
            $result = $comicpress_manager->breakdown_comic_filename($target_filename, true);
            $gd_did_rename = true;
          }
        }
      }
    }

    return compact('file_ok', 'gd_did_rename', 'result', 'target_filename', 'is_cmyk');
  }

  /**
   * Obfuscate a filename.
   */
  function obfuscate_filename($filename) {
    global $comicpress_manager;
    
    if (($result = $comicpress_manager->breakdown_comic_filename($filename)) !== false) {
      $md5_key = substr(md5(rand() + strlen($filename)), 0, 8);
      $extension = pathinfo($filename, PATHINFO_EXTENSION);
      $mode = $comicpress_manager->get_cpm_option('cpm-obfuscate-filenames-on-upload');
      switch ($mode) {
        case "append":
          return $result['date'] . $result['title'] . '-' . $md5_key . '.' . $extension;
          break;
        case "replace":
          return $result['date'] . '-' . $md5_key . '.' . $extension;
          break;
      }
    }
    return $filename;
  }

  /**
   * Write a thumbnail image to the thumbnail folders.
   * @param string $input The input image filename.
   * @param string $target_filename The filename for the thumbnails.
   * @param boolean $do_rebuild If true, force rebuilding thumbnails.
   * @return mixed True if successful, false if not, null if unable to write.
   */
  function write_thumbnail($input, $target_filename, $do_rebuild = false) {
    global $comicpress_manager;

    $target_format = pathinfo($target_filename, PATHINFO_EXTENSION);
    $files_created_in_operation = array();

    $write_targets = array();
    foreach ($comicpress_manager->separate_thumbs_folder_defined as $type => $value) {
      if ($value) {
        if ($comicpress_manager->thumbs_folder_writable[$type]) {
          $converted_target_filename = preg_replace('#\.[^\.]+$#', '', $target_filename) . '.' . $target_format;

          $target = CPM_DOCUMENT_ROOT . '/' . $comicpress_manager->properties[$type . "_comic_folder"];
          if (($subdir = $comicpress_manager->get_subcomic_directory()) !== false) {
            $target .= '/' . $subdir;
          }
          $target .= '/' . $converted_target_filename;

          if (!in_array($target, $write_targets)) {
            $write_targets[$type] = $target;
          }
        }
      }
    }

    if (count($write_targets) > 0) {
      if (!$do_rebuild) {
        if ($this->_f->file_exists($input)) {
          if ($this->_f->file_exists($target)) {
            $t1 = $this->_f->filemtime($input);
            $t2 = $this->_f->filemtime($target);
            if ($t1 > $t2) {
              $do_rebuild = true;
            }
          } else {
            $do_rebuild = true;
          }
        }
      }

      if ($do_rebuild) {
        if ($comicpress_manager->scale_method != false) {
          $targets_and_constraints = array();
          foreach ($write_targets as $type => $target) {
            $targets_and_constraints[] = array(
              $target,
              array(
                'width' => 
                (isset($comicpress_manager->properties["${type}_comic_width"])) ? $comicpress_manager->properties["${type}_comic_width"] : $comicpress_manager->properties['archive_comic_width']
              )
            );
          }
          
          return $comicpress_manager->scale_method->generate_thumbnails($input, $targets_and_constraints, strtolower($target_format), $comicpress_manager->get_cpm_option("cpm-thumbnail-quality"));
        }
      }
    }

    return null;
  }

  /**
   * Display messages when CPM operations are completed.
   */
  function display_operation_messages($info) {
    global $comicpress_manager;
    extract($info);

    if (count($invalid_filenames) > 0) {
      $comicpress_manager->messages[] = __("<strong>The following filenames were invalid:</strong> ", 'comicpress-manager') . implode(", ", $invalid_filenames);
    }

    if (count($invalid_image_types) > 0) {
      $comicpress_manager->warnings[] = __("<strong>According to GD, the following files were invalid image files:</strong> ", 'comicpress-manager') . implode(", ", $invalid_image_types);
    }

    if (count($files_uploaded) > 0) {
      $comicpress_manager->messages[] = __("<strong>The following files were uploaded:</strong> ", 'comicpress-manager') . implode(", ", $files_uploaded);
    }

    if (count($files_not_uploaded) > 0) {
      $comicpress_manager->messages[] = __("<strong>The following files were not uploaded, or the permissions on the uploaded file do not allow reading the file.</strong> Check the permissions of both the target directory and the upload directory and try again: ", 'comicpress-manager') . implode(", ", $files_not_uploaded);
    }

    if (count($thumbnails_written) > 0) {
      $comicpress_manager->messages[] = __("<strong>Thumbnails were written for the following files:</strong> ", 'comicpress-manager') . implode(", ", $thumbnails_written);
    }
    
    if (count($thumbnails_not_written) > 0) {
      $comicpress_manager->messages[] = __("<strong>Thumbnails were not written for the following files.</strong>  Check the permissions on the rss &amp; archive folders, and make sure the files you're processing are valid image files: ", 'comicpress-manager') . implode(", ", $thumbnails_not_written);
    }

    if (count($new_thumbnails_not_needed) > 0) {
      $comicpress_manager->messages[] = __("<strong>New thumbnails were not needed for the following files:</strong> ", 'comicpress-manager') . implode(", ", $new_thumbnails_not_needed);
    }

    if (count($gd_rename_file) > 0) {
      $comicpress_manager->messages[] = __("<strong>GD was able to recognize the filetypes of these files and change their extensions to match:</strong> ", 'comicpress-manager') . implode(", ", $gd_rename_file);
    }

    if (count($did_convert_cmyk_jpeg) > 0) {
      $comicpress_manager->messages[] = __("<strong>The following JPEG files have been converted from CMYK to RGB:</strong> ", 'comicpress-manager') . implode(", ", $did_convert_cmyk_jpeg);
    }

    if (count($posts_created) > 0) {
      $post_links = array();
      foreach ($posts_created as $comic_post) {
        $post_links[] = "<li><strong>" . $comic_post['post_title'] . "</strong> (" . $comic_post['post_date'] . ") " . generate_view_edit_post_links($comic_post) . "</li>";
      }

      $comicpress_manager->messages[] = __("<strong>New posts created.</strong>  View them from the links below:", 'comicpress-manager') . " <ul>" . implode("", $post_links) . "</ul>";
    } else {
      if (count($files_uploaded) > 0) {
        if (count($duplicate_posts) == 0) {
          $comicpress_manager->messages[] = __("<strong>No new posts created.</strong>", 'comicpress-manager');
        }
      }
    }

    if (count($duplicate_posts) > 0) {
      $post_links = array();
      foreach ($duplicate_posts as $info) {
        list($comic_post, $comic_file) = $info;
        $post_links[] = "<li><strong>" . $comic_file . " &mdash; " . $comic_post['post_title'] . "</strong> (" . $comic_post['post_date'] . ") " . generate_view_edit_post_links($comic_post) . "</li>";
      }

      $comicpress_manager->messages[] = __("<strong>The following files would have created duplicate posts.</strong> View them from the links below: ", 'comicpress-manager') . "<ul>" . implode("", $post_links) . "</ul>";
    }
  }

  /**
   * Find a comic file by date.
   */
  function find_comic_by_date($timestamp) {
    global $comicpress_manager;

    if (!is_numeric($timestamp)) { return false; }
    $files = $this->_f->glob($comicpress_manager->get_comic_folder_path() . '/' . date(CPM_DATE_FORMAT, $timestamp) . '*');
    
    if (empty($files)) { return false; }
    
    foreach ($files as $file) {
      if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $comicpress_manager->allowed_extensions)) {
        return $file;
      }
    }
    return false;
  }


  /**
   * Handle deleting a post. If Edit Post Integration is enabled, delete any associated
   * files from the comics folders.
   */
  function handle_delete_post($post_id) {
    global $comicpress_manager;

    if (!$comicpress_manager->is_cpm_managing_posts) {
      if ($comicpress_manager->get_cpm_option("cpm-edit-post-integrate") == 1) {
        $post = get_post($post_id);
        if (!empty($post)) {
          if (!in_array($post->post_type, array("attachment", "revision", "page"))) {

            $ok = $this->_is_post_in_comic_category($post_id);

            if ($ok) {
              if (($parsed_date = strtotime($post->post_date)) !== false) {
                $original_date = date(CPM_DATE_FORMAT, $parsed_date);

                if (empty($comicpress_manager->comic_files)) {
                  $comicpress_manager->read_information_and_check_config();
                }

                if (is_array($comicpress_manager->comic_files)) {
                  foreach ($comicpress_manager->comic_files as $file) {
                    $filename = pathinfo($file, PATHINFO_BASENAME);
                    if (($result = $comicpress_manager->breakdown_comic_filename($filename)) !== false) {
                      if ($result['date'] == $original_date) {
                        foreach ($this->find_thumbnails_by_filename($file) as $thumb_file) {
                          $thumb_file = CPM_DOCUMENT_ROOT . $thumb_file;
                          $this->_f->unlink($thumb_file);
                        }

                        $this->_f->unlink($file);
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
  }
  
  /**
   * If a category is added, deleted, or edited, and it's not done through
   * the Storyline Structure page, normalize the Storyline Structure
   * so that it includes/removes the affected categories.
   */
  function rebuild_storyline_structure($term_id) {
    global $comicpress_manager;

    if (empty($comicpress_manager->is_cpm_modifying_categories)) {
      $comicpress_manager->read_information_and_check_config();
      $comicpress_manager->normalize_storyline_structure();
    }
  }

  /**
   * Add the Comic column to Edit Posts.
   */
  function manage_posts_columns($posts_columns) {
    wp_enqueue_script('prototype');

    $posts_columns['comic'] = "Comic";
    return $posts_columns;
  }

  /**
   * Populate the Comic coulmn in Edit Posts.
   */
  function manage_posts_custom_column($column_name) {
    global $comicpress_manager, $post, $comicpress_manager_admin;

    if ($column_name == "comic") {
      $post_date = date(CPM_DATE_FORMAT, strtotime($post->post_date));

      if ($is_first = empty($this->broken_down_comic_files)) {
        $this->broken_down_comic_files = array();
        if (empty($comicpress_manager->comic_files)) {
          $comicpress_manager->read_information_and_check_config();
        }

        if (is_array($comicpress_manager->comic_files) && !empty($comicpress_manager->comic_files)) {
          foreach ($comicpress_manager->comic_files as $file) {
            $filename = pathinfo($file, PATHINFO_BASENAME);
            if (($result = $comicpress_manager->breakdown_comic_filename($filename)) !== false) {
              if (!isset($this->broken_down_comic_files[$result['date']])) {
                $this->broken_down_comic_files[$result['date']] = array();
              }
              $this->broken_down_comic_files[$result['date']][] = $file;
            }
          }
          if (!empty($this->broken_down_comic_files)) {
            ?>
              <script type="text/javascript">
                function set_up_opener(id) {
                  Event.observe('comic-icon-' + id, 'mouseover', function() {
                    Element.clonePosition('comic-hover-' + id, 'comic-icon-' + id, { setWidth: false, setHeight: false, offsetLeft: -215, offsetTop: -((Element.getDimensions('comic-hover-' + id)['height'] - Element.getDimensions('comic-icon-' + id)['height']) / 2) });
                    $('comic-hover-' + id).show();
                  });

                  Event.observe('comic-icon-' + id, 'mouseout', function() {
                    $('comic-hover-' + id).hide();
                  });
                }
              </script>
            <?php
          }
        }
      }

      if (!empty($this->broken_down_comic_files)) {
        $ok = false;

        $categories = wp_get_post_categories($post->ID);
        if ($comicpress_manager->get_subcomic_directory() !== false) {
          $ok = in_array(get_option('comicpress-manager-manage-subcomic'), $categories);
        } else {
          extract($comicpress_manager->get_all_comic_categories());

          foreach ($category_tree as $node) {
            if (in_array(end(explode("/", $node)), $categories)) {
              $ok = true; break;
            }
          }
        }

        if ($ok) {
          if (isset($this->broken_down_comic_files[$post_date])) {
            $index = 0;
            foreach ($this->broken_down_comic_files[$post_date] as $file) {
              $image_index = $post->ID . '-' . $index;

              $thumbnails_found = $this->find_thumbnails_by_filename($file);

              $icon_file_to_use = $file;
              if (is_array($thumbnails_found)) {
                foreach ($thumbnails_found as $type => $value) {
                  if (!empty($value)) { $icon_file_to_use = $value; }
                }
              }

              $hovertext = get_post_meta($post->ID, "hovertext", true);
              ?>
                <img style="border: solid black 1px; margin-bottom: 2px" id="comic-icon-<?php echo $image_index ?>" src="<?php echo $comicpress_manager->build_comic_uri($icon_file_to_use) ?>" width="100" /><br />
                <div id="comic-hover-<?php echo $image_index ?>" style="display: none; position: absolute; width: 200px; padding: 5px; border: solid black 1px; background-color: #DDDDDD; z-index: 1">
                  <img width="200"  id="preview-comic-<?php echo $image_index ?>" src="<?php echo $comicpress_manager->build_comic_uri($file) ?>" />

                  <p><strong>File:</strong> <?php echo pathinfo($file, PATHINFO_BASENAME) ?></p>

                  <?php if (count($thumbnails_found) > 0) { ?>
                    <p><strong>Thumbs:</strong> <?php echo implode(", ", array_keys($thumbnails_found)) ?></p>
                  <?php } ?>

                  <?php if (!empty($hovertext)) { ?>
                    <p><strong>Hover:</strong> <?php echo $hovertext ?></p>
                  <?php } ?>
                </div>
                <script type="text/javascript">set_up_opener('<?php echo $image_index ?>')</script>
              <?php
              $index++;
            }
          } else {
            echo __("No comic files found!", 'comicpress-manager');
          }
        }
      }
    }
  }

  /**
   * Add the ComicPress News Dashboard widget.
   */
  function add_dashboard_widget($widgets) {
    global $wp_registered_widgets;
    if (!isset($wp_registered_widgets['dashboard_cpm'])) {
      return $widgets;
    }
    array_splice($widgets, sizeof($widgets)-1, 0, 'dashboard_cpm');
    return $widgets;
  }

  /**
   * Write out the ComicPress News Dashboard widget.
   */
  function dashboard_widget($sidebar_args) {
    if (is_array($sidebar_args)) {
      extract($sidebar_args, EXTR_SKIP);
    }
    echo $before_widget . $before_title . $widget_name . $after_title;
    wp_widget_rss_output('http://feeds.feedburner.com/comicpress?format=xml', array('items' => 2, 'show_summary' => true));
    echo $after_widget;
  }

  /**
   * Add the QuomicPress Dashboard widget.
   */
  function add_quomicpress_widget($widgets) {
    global $wp_registered_widgets;
    if (!isset($wp_registered_widgets['dashboard_quomicpress'])) {
      return $widgets;
    }
    array_splice($widgets, sizeof($widgets)-1, 0, 'dashboard_quomicpress');
    return $widgets;
  }


  /**
   * Write out the QuomicPress Dashboard widget.
   */
  function quomicpress_widget($sidebar_args) {
    if (is_array($sidebar_args)) {
      extract($sidebar_args, EXTR_SKIP);
    }
    echo $before_widget . $before_title . $widget_name . $after_title;
    $this->_show_view("ComicPressQuomicPressWidget");
    
    echo $after_widget;
  }

  /**
   * Write all of the styles and scripts.
   */
  function write_global_styles_scripts() {
    global $comicpress_manager, $blog_id;

    $plugin_url_root = get_option('siteurl') . '/' . $this->get_plugin_path();

    $ajax_request_url = isset($_SERVER['URL']) ? $_SERVER['URL'] : $_SERVER['SCRIPT_URL'];
    ?>

    <script type="text/javascript">
      var messages = {
        'add_file_upload_file': "<?php _e("File:", 'comicpress-manager') ?>",
        'add_file_upload_remove': "<?php _e("remove", 'comicpress-manager') ?>",
        'count_missing_posts_none_missing': "<?php _e("You're not missing any posts!", 'comicpress-manager') ?>",
        'failure_in_counting_posts': "<?php _e("There was a failure in counting. You may have too many comics/posts to analyze before your server times out.", 'comicpress-manager') ?>",
        'count_missing_posts_counting': "<?php _e("counting", 'comicpress-manager') ?>"
      };

      var ajax_request_uri = "<?php echo $plugin_url_root ?>/comicpress_manager_count_missing_entries.php?blog_id=<?php echo $blog_id ?>";
    </script>
    <?php $this->include_javascript("comicpress_script.js") ?>
    <link rel="stylesheet" href="<?php echo $plugin_url_root . '/comicpress_styles.css' ?>" type="text/css" />
    <?php if ($comicpress_manager->need_calendars) { ?>
      <link rel="stylesheet" href="<?php echo $plugin_url_root ?>/jscalendar-1.0/calendar-blue.css" type="text/css" />
      <script type="text/javascript" src="<?php echo $plugin_url_root ?>/jscalendar-1.0/calendar.js"></script>
      <script type="text/javascript" src="<?php echo $plugin_url_root ?>/jscalendar-1.0/lang/calendar-en.js"></script>
      <script type="text/javascript" src="<?php echo $plugin_url_root ?>/jscalendar-1.0/calendar-setup.js"></script>
    <?php } ?>
    <!--[if IE]>
      <script type="text/javascript">Event.observe(window, 'load', function() { prepare_comicpress_manager() })</script>
    <![endif]-->

    <!--[if lte IE 6]>
    <style type="text/css">div#cpm-container div#cpm-left-column { margin-top: 0 }</style>
    <![endif]-->
  <?php }

  /**
   * Get the path to the plugin folder.
   */
  function get_plugin_path() {
    return PLUGINDIR . '/' . plugin_basename(realpath(dirname(__FILE__) . '/../'));
  }

  /**
   * Include a JavaScript file, preferring the minified version of the file if available.
   */
  function include_javascript($name) {
    $js_path = $this->_f->realpath(dirname(__FILE__) . '/../js');
    $plugin_url_root = get_option('siteurl') . '/' . $this->get_plugin_path();

    $regular_file = $name;
    $minified_file = 'minified-' . $name;

    $file_to_use = $regular_file;
    if ($this->_f->file_exists($js_path . '/' . $minified_file)) {
      if ($this->_f->filemtime($js_path . '/' . $minified_file) >= $this->_f->filemtime($js_path . '/' . $regular_file)) {
        $file_to_use = $minified_file;
      }
    }
    
    ?><script type="text/javascript" src="<?php echo $plugin_url_root ?>/js/<?php echo $file_to_use ?>"></script><?php
  }

  function get_backup_files() {
    global $comicpress_manager;
    
    $available_backup_files = array();
    $found_backup_files = $this->_f->glob(dirname($comicpress_manager->config_filepath) . '/comicpress-config.php.*');
    if ($found_backup_files === false) { $found_backup_files = array(); }
    foreach ($found_backup_files as $file) {
      if (preg_match('#\.([0-9]+)$#', $file, $matches) > 0) {
        list($all, $time) = $matches;
        $available_backup_files[] = $time;
      }
    }

    arsort($available_backup_files);
    return $available_backup_files;
  }

  function handle_warnings() {
    global $comicpress_manager;
    
    // display informative messages to the use
    // TODO: remove separate arrays and tag messages based on an enum value
    foreach (array(
      array(
        $comicpress_manager->messages,
        __("The operation you just performed returned the following:", 'comicpress-manager'),
        'messages'),
      array(
        $comicpress_manager->warnings,
        __("The following warnings were generated:", 'comicpress-manager'),
        'warnings'),
      array(
        $comicpress_manager->errors,
        __("The following problems were found in your configuration:", 'comicpress-manager'),
        'errors')
    ) as $info) {
      list($messages, $header, $style) = $info;
      if (count($messages) > 0) {
        if (count($messages) == 1) {
          $output = $messages[0];
        } else {
          ob_start(); ?>

          <ul>
            <?php foreach ($messages as $message) { ?>
              <li><?php echo $message ?></li>
            <?php } ?>
          </ul>

          <?php $output = ob_get_clean();
        }

        if ((strpos(PHP_OS, "WIN") !== false) && ($style == "warnings")) {
          $output .= __("<p><strong>If your error is permissions-related, you may have to set some Windows-specific permissions on your filesystem.</strong> Consult your Webhost for more information.</p>", 'comicpress-manager');
        }

        ?>
        <div id="cpm-<?php echo $style ?>"><?php echo $output ?></div>
      <?php }
    }

    // errors are fatal.
    if (count($comicpress_manager->errors) > 0) {
      $current_theme_info = get_theme(get_current_theme());
      ?>
      <p><?php _e("You must fix the problems above before you can proceed with managing your ComicPress installation.", 'comicpress-manager') ?></p>

      <?php if ($comicpress_manager->show_config_editor) { ?>
        <p><strong><?php _e("Details:", 'comicpress-manager') ?></strong></p>
        <ul>
          <li><strong><?php _e("Current ComicPress theme folder:", 'comicpress-manager') ?></strong> <?php echo $current_theme_info['Template Dir'] ?></li>
          <li><strong><?php _e("Available categories:", 'comicpress-manager') ?></strong>
            <table id="categories-table">
              <tr>
                <th><?php _e("Category Name", 'comicpress-manager') ?></th>
                <th><?php _e("ID #", 'comicpress-manager') ?></th>
              </tr>
              <?php foreach (get_all_category_ids() as $category_id) {
                $category = get_category($category_id);
                ?>
                <tr>
                  <td><?php echo $category->name ?></td>
                  <td align="center"><?php echo $category->term_id ?></td>
                </tr>
              <?php } ?>
            </table>
          </li>
        </ul>
      <?php }

      $update_automatically = true;

      if (function_exists('get_site_option')) {
        $comicpress_manager->show_config_editor = true;
      } else {
        if ($comicpress_manager->config_method == "comicpress-config.php") {
          if (!$comicpress_manager->can_write_config) {
            $update_automatically = false;
          }
        } else {
          if (count($available_backup_files) > 0) {
            if (!$comicpress_manager->can_write_config) {
              $update_automatically = false;
            }
          } else {
            $update_automatically = false;
          }
        }
        
        if (!$update_automatically) { ?>
          <p>
            <?php printf(__("<strong>You won't be able to update your comicpress-config.php or functions.php file directly through the ComicPress Manager interface.</strong> Check to make sure the permissions on %s and comicpress-config.php are set so that the Webserver can write to them.  Once you submit, you'll be given a block of code to paste into the comicpress-config.php file.", 'comicpress-manager'), $current_theme_info['Template Dir']) ?>
          </p>
        <?php } else {
          $available_backup_files = $this->get_backup_files();
          
          if (count($available_backup_files) > 0) { ?>
            <p>
              <?php _e("<strong>Some backup comicpress-config.php files were found in your theme directory.</strong>  You can choose to restore one of these backup files, or you can go ahead and create a new configuration below.", 'comicpress-manager') ?>
            </p>

            <form action="" method="post">
              <input type="hidden" name="action" value="restore-backup" />
              <strong><?php _e("Restore from backup dated:", 'comicpress-manager') ?></strong>
                <select name="backup-file-time">
                  <?php foreach($available_backup_files as $time) { ?>
                    <option value="<?php echo $time ?>">
                      <?php echo date("r", $time) ?>
                    </option>
                  <?php } ?>
                </select>
              <input type="submit" class="button" value="<?php _e("Restore", 'comicpress-manager') ?>" />
            </form>
            <hr />
          <?php }
        }
      }

      if ($comicpress_manager->show_config_editor) {
        echo $this->edit_config();
      } ?>

      <?php if (!function_exists('get_site_option')) { ?>
        <hr />

        <strong><?php _e('Debug info', 'comicpress-manager') ?></strong> (<em><?php _e("this data is sanitized to protect your server's configuration", 'comicpress-manager') ?></em>)

        <?php echo $this->show_debug_info(false);
      }

      return false;
    }
    return true;
  }
  
  /**
   * Show site debug info.
   */
  function show_debug_info($display_none = true) {
    global $comicpress_manager;

    ob_start(); ?>
    <span id="debug-info" class="code-block" <?php echo $display_none ? "style=\"display: none\"" : "" ?>><?php
      $output_config = get_object_vars($comicpress_manager);
      $output_config['comic_files'] = count($comicpress_manager->comic_files) . " comic files";
      $output_config['config_filepath'] = substr(realpath($comicpress_manager->config_filepath), CPM_STRLEN_REALPATH_DOCUMENT_ROOT);
      $output_config['path'] = substr(realpath($comicpress_manager->path), CPM_STRLEN_REALPATH_DOCUMENT_ROOT);
      $output_config['zip_enabled'] = extension_loaded("zip");

      clearstatcache();
      $output_config['folder_perms'] = array();

      $subdir = "";
      if (($subdir = $comicpress_manager->get_subcomic_directory()) !== false) {
        $subdir = '/' . $subdir;
      }

      foreach (array(
        'comic' => CPM_DOCUMENT_ROOT . '/' . $comicpress_manager->properties['comic_folder'] . $subdir,
        'rss' => CPM_DOCUMENT_ROOT . '/' . $comicpress_manager->properties['rss_comic_folder'] . $subdir,
        'archive' => CPM_DOCUMENT_ROOT . '/' . $comicpress_manager->properties['archive_comic_folder'] . $subdir,
        'config' => $comicpress_manager->config_filepath
      ) as $key => $path) {
        if (($s = @stat($path)) !== false) {
          $output_config['folder_perms'][$key] = decoct($s[2]);
        } else {
          $output_config['folder_perms'][$key] = "folder does not exist";
        }
      }

      $new_output_config = array();
      foreach ($output_config as $key => $value) {
        if (is_string($value)) {
          $value = htmlentities($value);
        }
        $new_output_config[$key] = $value;
      }

      var_dump($new_output_config);
    ?></span>
    <?php

    return ob_get_clean();
  }

  /**
   * Show the config editor.
   */
  function edit_config() {
    global $comicpress_manager;

    include('cp_configuration_options.php');

    $max_depth = 3;
    $max_depth_message = false;
    $max_directories = 256;

    $folders_to_ignore = implode("|", array('wp-content', 'wp-includes', 'wp-admin'));

    $folder_stack = glob(CPM_DOCUMENT_ROOT . '/*');
    if ($folder_stack === false) { $folder_stack = array(); }
    $found_folders = array();

    while (count($folder_stack) > 0) {
      $file = array_shift($folder_stack);
      if (is_dir($file)) {
        $root_file = substr($file, strlen(CPM_DOCUMENT_ROOT) + 1);
        if (preg_match("#(${folders_to_ignore})$#", $root_file) == 0) {
          if (count(explode("/", $root_file)) <= $max_depth) {
            $found_folders[] = $root_file;
            $folder_stack = array_merge($folder_stack, glob($file . "/*"));
          } else {
            if (!$max_depth_message) {
              $comicpress_manager->messages[] = sprintf(__("I went %s levels deep in my search for comic directories. Are you sure you have your site set up correctly?", 'comicpress-manager'), $max_depth);
              $max_depth_message = true;
            }
          }
        }
      }
      if (count($found_folders) == $max_directories) {
        $comicpress_manager->messages[] = sprintf(__("I found over %s directories from your site root. Are you sure you have your site set up correctly?", 'comicpress-manager'), $max_directories);
        break;
      }
    }

    sort($found_folders);

    ob_start(); ?>

    <form action="" method="post" id="config-editor">
      <input type="hidden" name="action" value="update-config" />

      <table class="form-table">
        <?php foreach ($comicpress_configuration_options as $field_info) {
          $no_wpmu = false;
          extract($field_info);

          $ok = (function_exists('get_site_option')) ? ($no_wpmu !== true) : true;
          if ($ok) {
            $description = " <em>(" . $description . ")</em>";

            $config_id = (isset($field_info['variable_name'])) ? $field_info['variable_name'] : $field_info['id'];

            switch($type) {
              case "category": ?>
                <tr>
                  <th scope="row"><?php echo $name ?>:</th>
                  <td><select name="<?php echo $config_id ?>" title="<?php _e('All possible WordPress categories', 'comicpress-manager') ?>">
                                 <?php foreach (get_all_category_ids() as $cat_id) {
                                   $category = get_category($cat_id); ?>
                                   <option value="<?php echo $category->cat_ID ?>"
                                           <?php echo ($comicpress_manager->properties[$config_id] == $cat_id) ? " selected" : "" ?>><?php echo $category->cat_name; ?></option>
                                 <?php } ?>
                               </select><?php echo $description ?></td>
                </tr>
                <?php break;
              case "folder":
                $directory_found_in_list = in_array($comicpress_manager->properties[$config_id], $found_folders);
                ?>
                <tr>
                  <th scope="row"><?php echo $name ?>:</th>
                  <td class="config-field">
                    <input type="radio" name="folder-<?php echo $config_id ?>" id="folder-s-<?php echo $config_id ?>" value="select" <?php echo $directory_found_in_list ? "checked" : "" ?>/> <label for="folder-s-<?php echo $config_id ?>">Select directory from a list</label><br />
                    <div id="folder-select-<?php echo $config_id ?>">
                      <select title="<?php _e("List of possible folders at the root of your site", 'comicpress-manager') ?>" name="select-<?php echo $config_id ?>" id="<?php echo $config_id ?>">
                      <?php 
                        foreach ($found_folders as $file) { ?>
                          <option <?php echo ($file == $comicpress_manager->properties[$config_id]) ? " selected" : "" ?> value="<?php echo $file ?>"><?php echo $file ?></option>
                        <?php } ?>
                      </select><?php echo $description ?>
                    </div>
                    
                    <input type="radio" name="folder-<?php echo $config_id ?>" id="folder-e-<?php echo $config_id ?>" value="enter" <?php echo !$directory_found_in_list ? "checked" : "" ?>/> <label for="folder-e-<?php echo $config_id ?>">Enter in my directory name</label><br />
                    <div id="folder-enter-<?php echo $config_id ?>">
                      <input type="text" name="enter-<?php echo $config_id ?>" value="<?php echo $comicpress_manager->properties[$config_id] ?>" />
                    </div>
                    <script type="text/javascript">
                      var folder_handler_<?php echo $config_id ?> = function() {
                        if ($("folder-e-<?php echo $config_id ?>").checked) {
                          $("folder-select-<?php echo $config_id ?>").hide();
                          $("folder-enter-<?php echo $config_id ?>").show();
                        } else {
                          $("folder-select-<?php echo $config_id ?>").show();
                          $("folder-enter-<?php echo $config_id ?>").hide();
                        }
                      };

                      ["s", "e"].each(function(w) {
                        Event.observe("folder-" + w + "-<?php echo $config_id ?>", 'change', folder_handler_<?php echo $config_id ?>);
                      });

                      folder_handler_<?php echo $config_id ?>();
                    </script>
                  </td>
                </tr>
                <?php break;
              case "integer": ?>
                <tr>
                  <th scope="row"><?php echo $name ?>:</th>
                  <td><input type="text" name="<?php echo $config_id ?>" size="20" value="<?php echo $comicpress_manager->properties[$config_id] ?>" /><?php echo $description ?></td>
                </tr>
                <?php break;
            }
          }
        } ?>
        <?php if (!function_exists('get_site_option')) { ?>
          <?php
            $all_comic_folders_found = true;
            foreach (array(''. 'rss_', 'archive_') as $folder_name) {
              if (!file_exists(CPM_DOCUMENT_ROOT . '/' . $comicpress_manager->properties["${folder_name}comic_folder"])) { $all_comic_folders_found = false; break; }
            }

            if (!$all_comic_folders_found) { ?>
              <tr>
                <td colspan="2">
                  <p><?php _e("<strong>Create your comics, archive, or RSS folders first</strong>, then reload this page and use the dropdowns to select the target folder. If ComicPress Manager can't automatically find your folders, you can enter the folder names into the dropdowns.", 'comicpress-manager') ?></p>
                </td>
              </tr>
            <?php }
          ?>
          <?php if (!$comicpress_manager->is_wp_options) { ?>
            <tr>
              <th scope="row"><label for="just-show-config"><?php _e("Don't try to write my config out; just display it", 'comicpress-manager') ?></label></th>
              <td>
                <input type="checkbox" name="just-show-config" id="just-show-config" value="yes" />
                <label for="just-show-config"><em>(if you're having problems writing to your config from ComicPress Manager, check this box)</em></label>
              </td>
            </tr>
          <?php } ?>
        <?php } ?>
        <tr>
          <td colspan="2" align="center">
            <input class="button update-config" type="submit" value="<?php _e("Update Config", 'comicpress-manager') ?>" />
          </td>
        </tr>
      </table>
    </form>

    <?php return ob_get_clean();
  }

  /**
   * Add pages to the admin interface and load necessary JavaScript libraries.
   * Also read in the configuration and handle any POST actions.
   */
  function setup_admin_menu() {
    global $plugin_page, $access_level, $pagenow, $comicpress_manager, $wp_version;

    load_plugin_textdomain('comicpress-manager', $this->get_plugin_path());

    $comicpress_manager->read_information_and_check_config();

    $do_enqueue_prototype = false;

    if (($pagenow == "post.php") && ($_REQUEST['action'] == "edit")) {
      $do_enqueue_prototype = true;
    }

    $filename = plugin_basename(__FILE__);

    if (strpos($plugin_page, $filename) !== false) {
      $editor_load_pages = array($filename, $filename . '-import');

      if (in_array($plugin_page, $editor_load_pages)) {
        wp_enqueue_script('editor');
        if (!function_exists('wp_tiny_mce')) {
          wp_enqueue_script('wp_tiny_mce');
        }
      }

      $do_enqueue_prototype = true;

      $this->handle_actions();
    }

    if (in_array($pagenow, array("edit.php", "post-new.php"))) {
      $do_enqueue_prototype = true;
    }

    if ($do_enqueue_prototype) {
      wp_enqueue_script('prototype');
      wp_enqueue_script('scriptaculous-effects');
      wp_enqueue_script('scriptaculous-builder');
    }

    if (!isset($access_level)) { $access_level = 10; }

    $plugin_title = __("ComicPress Manager", 'comicpress-manager');

    add_menu_page($plugin_title, __("ComicPress", 'comicpress-manager'), $access_level, $filename, array($this, "_index_caller"), get_option('siteurl') . '/' . $this->get_plugin_path() . '/comicpress-icon.png');
    
    add_submenu_page($filename, $plugin_title, __("Upload", 'comicpress-manager'), $access_level, $filename, array($this, '_index_caller'));

    if (!function_exists('get_site_option')) {
      add_submenu_page($filename, $plugin_title, __("Import", 'comicpress-manager'), $access_level, $filename . '-import', array($this, '_import_caller'));
    }

    add_submenu_page($filename, $plugin_title, __("Bulk Edit", 'comicpress-manager'), $access_level, $filename . '-status', array($this, '_bulk_edit_caller'));

    add_submenu_page($filename, $plugin_title, __("Storyline Structure", 'comicpress-manager'), $access_level, $filename . '-storyline', array($this, '_storyline_caller'));

    add_submenu_page($filename, $plugin_title, __("Change Dates", 'comicpress-manager'), $access_level, $filename . '-dates', array($this, '_dates_caller'));
    add_submenu_page($filename, $plugin_title, __("ComicPress Config", 'comicpress-manager'), $access_level, $filename . '-config', array($this, '_comicpress_config_caller'));
    add_submenu_page($filename, $plugin_title, __("Manager Config", 'comicpress-manager'), $access_level, $filename . '-cpm-config', array($this, '_manager_config_caller'));

    if ($pagenow == "index.php") {
      if ($comicpress_manager->get_cpm_option('cpm-enable-dashboard-rss-feed') == 1) {
        wp_register_sidebar_widget( 'dashboard_cpm', __("ComicPress News", "comicpress-manager"), array($this, 'dashboard_widget'),
          array( 'all_link' => "http://mindfaucet.com/comicpress/", 'feed_link' => "http://feeds.feedburner.com/comicpress?format=xml", 'width' => 'half', 'class' => 'widget_rss' )
        );

        add_filter('wp_dashboard_widgets', array($this, 'add_dashboard_widget'));
      }

      if (($option = generate_comic_categories_options('category')) !== false) {
        if ($comicpress_manager->get_cpm_option('cpm-enable-quomicpress') == 1) {
          if (count($comicpress_manager->errors) == 0) {
            wp_register_sidebar_widget( 'dashboard_quomicpress', __("QuomicPress (Quick ComicPress)", "comicpress-manager"), array($this, 'quomicpress_widget'),
              array( 'width' => 'half' )
            );

            add_filter('wp_dashboard_widgets', array($this, 'add_quomicpress_widget'));
          }
        }
      }
    }
  }
  
  function _index_caller() {
    $this->_show_view('ComicPressUpload');
  }
  
  /**
   * Handle all ComicPress actions.
   */
  function handle_actions() {
    global $comicpress_manager;

    $valid_actions = array('multiple-upload-file', 'create-missing-posts', 
                           'update-config', 'restore-backup', 'change-dates',
                           'write-comic-post', 'update-cpm-config', 'do-first-run', 'skip-first-run',
                           'build-storyline-schema', 'batch-processing', 'manage-subcomic');

    //
    // take actions based upon $_POST['action']
    //
    if (isset($_POST['action'])) {
      if (in_array($_POST['action'], $valid_actions)) {
        require_once('actions/comicpress_' . $_POST['action'] . '.php');
        call_user_func("cpm_action_" . str_replace("-", "_", $_POST['action']));
      }
    }
  }
}

?>