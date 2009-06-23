<?php

require_once('ComicPressGDProcessing.php');
require_once('ComicPressImageMagickProcessing.php');
require_once('ComicPressFileOperations.php');

define("CPM_OPTION_PREFIX", "comicpress-manager");

class ComicPressManager {
  var $properties = array(
    // Leave these alone! These values should be read from your comicpress-config.php file.
    // If your values from comicpress-config.php are not being read, then something is wrong in your config.
    'comic_folder'         => 'comics',
    'comiccat'             => '1',
    'blogcat'              => '2',
    'rss_comic_folder'     => 'comics',
    'archive_comic_folder' => 'comics',
    'archive_comic_width'  => '380',
    'rss_comic_width'      => '380',
    'blog_postcount'       => '10'
  );

  var $warnings, $messages, $errors, $detailed_warnings, $show_config_editor;
  var $config_method, $config_filepath, $path, $plugin_path;
  var $comic_files;
  var $scale_method, $identify_method_cache, $can_write_config;
  var $need_calendars = false;
  var $is_wp_options = false;

  var $import_safe_exit = null;
  var $did_first_run;

  var $is_cpm_managing_posts, $is_cpm_modifying_categories;
  var $wpmu_disk_space_message;

  var $separate_thumbs_folder_defined = array('rss' => null, 'archive' => null);
  var $thumbs_folder_writable = array('rss' => null, 'archive' => null);
  var $allowed_extensions = array("gif", "jpg", "jpeg", "png");

  var $category_info = array('comiccat' => null, 'blogcat' => null);

  var $_f;

  var $folders = array(
    array('comic folder', 'comic_folder', true, ""),
    array('RSS feed folder', 'rss_comic_folder', false, 'rss'),
    array('archive folder', 'archive_comic_folder', false, 'archive'));

  var $error_types = array(
    'NOT_A_FOLDER' => 'not a folder',
    'NOT_WRITABLE' => 'not writable',
    'NOT_STATABLE' => 'not statable',
    'INVALID_CATEGORY' => 'invalid category',
    'CATEGORY_DOES_NOT_EXIST' => 'category does not exist'
  );

  function ComicPressManager() {    
    $this->_f = new ComicPressFileOperations();
    $this->scale_method = false;
    $this->gd_processor = false;
    
    if (extension_loaded("gd")) {
      $this->scale_method = new ComicPressGDProcessing();
      $this->gd_processor = new ComicPressGDProcessing();
    }
    
    $result = @shell_exec("which convert") . @shell_exec("which identify");
    if (!empty($result)) {
      $this->scale_method = new ComicPressImageMagickProcessing();
    }
    
    if (function_exists('cpm_wpmu_config_setup')) { cpm_wpmu_config_setup($this); }

    if (!defined('CPM_DOCUMENT_ROOT')) {
      define('CPM_DOCUMENT_ROOT', $this->calculate_document_root());
    }
    
    if (!defined("CPM_STRLEN_REALPATH_DOCUMENT_ROOT")) {
      define("CPM_STRLEN_REALPATH_DOCUMENT_ROOT", strlen(realpath(CPM_DOCUMENT_ROOT)));
    }
  }

  /**
   * Get the option name for a ComicPress Manager option.
   * CPM options are prefixed with "comicpress-manager-".
   * @param string $option_name The CPM key name.
   * @return string The full WP options key name.
   */
  function get_cpm_option_key($option_name) {
    return CPM_OPTION_PREFIX . '-' . $option_name;
  }
  
  /**
   * Retrieve a ComicPress Manager option.
   * @param string $option_name The CPM key name.
   * @return string The value of the option.
   */
  function get_cpm_option($option_name) { return get_option($this->get_cpm_option_key($option_name)); }
  
  /**
   * Set a ComicPress Manager option.
   * @param string $option_name The CPM key name.
   * @param string $value The value to set.
   */
  function set_cpm_option($option_name, $value) { update_option($this->get_cpm_option_key($option_name), $value); }

  /**
   * Calculate the document root where comics are stored.
   * @param array $override_server_info If set, override $_SERVER with these values.
   * @return string|boolean The document root, or false if there was an error.
   */
  function calculate_document_root($override_server_info = null) {
    global $cpm_attempted_document_roots, $wpmu_version;
    $cpm_attempted_document_roots = array();
    
    $server_info = !is_null($override_server_info) ? $override_server_info : $_SERVER;

    // we need something to work with
    $any_possible_data = false;
    foreach (array('SCRIPT_FILENAME', 'DOCUMENT_ROOT') as $field) {
      if (isset($server_info[$field])) {
        $any_possible_data = true; break;
      }
    }
    if (!$any_possible_data) { return false; }

    $document_root = null;

    // first try getting path info straight from server info
    $translated_script_filename = str_replace('\\', '/', $server_info['SCRIPT_FILENAME']);

    foreach (array('SCRIPT_NAME', 'SCRIPT_URL') as $var_to_try) {
      if (isset($server_info[$var_to_try])) {
        $root_to_try = substr($translated_script_filename, 0, -strlen($server_info[$var_to_try]));
        $cpm_attempted_document_roots[] = $root_to_try;

        if ($this->_f->file_exists($root_to_try . '/index.php')) {
          $document_root = $root_to_try;
          break;
        }
      }
    }

    // then use the URL if necessary
    if (is_null($document_root) && isset($server_info['DOCUMENT_ROOT'])) {
      $parsed_url = @parse_url(get_option('home'));
      if ($parsed_url === false) { return false; }

      $document_root = untrailingslashit($server_info['DOCUMENT_ROOT']) . $parsed_url['path'];
    }

    // still nothing found?
    if (is_null($document_root)) { return false; }

    // WPMU
    if (!empty($wpmu_version) && function_exists('cpm_wpmu_modify_path')) {
      $document_root = cpm_wpmu_modify_path($document_root);
    }

    return untrailingslashit($document_root);
  }

  /**
   * Transform a date()-compatible string into a human-parseable string.
   * Useful for generating examples of date() usage.
   */
  function transform_date_string($string, $replacements) {
    if (!is_array($replacements)) { return false; }
    if (!is_string($string)) { return false; }

    $transformed_string = $string;
    foreach (array("Y", "m", "d") as $required_key) {
      if (!isset($replacements[$required_key])) { return false; }
      $transformed_string = preg_replace('#(?<![\\\])' . $required_key . '#', $replacements[$required_key], $transformed_string);
    }

    $transformed_string = str_replace('\\', '', $transformed_string);
    return $transformed_string;
  }

  /**
   * Generate an example date string.
   * @param string $example_date The example date format.
   * @return string The formatted string.
   */
  function generate_example_date($example_date) {
    return $this->transform_date_string($example_date, array('Y' => "YYYY", 'm' => "MM", 'd' => "DD"));
  }

  /**
   * Build the URI to a comic file.
   */
  function build_comic_uri($filename, $base_dir = null) {
    if (!is_null($base_dir)) {
      if (strlen($filename) < strlen($base_dir)) { return false; }
    }
    if (($realpath_result = realpath($filename)) !== false) {
      $filename = $realpath_result;
    }
    if (!is_null($base_dir)) {
      $filename = substr($filename, strlen($base_dir));
    }
    $parts = explode('/', str_replace('\\', '/', $filename));
    if (count($parts) < 2) { return false; }

    $parsed_url = parse_url(get_option('home'));
    $path = $parsed_url['path'];
    if (function_exists('get_site_option')) { $path = cpm_wpmu_fix_admin_uri($path); }

    $count = 2;

    if (($dirname = $this->get_subcomic_directory()) !== false) {
      $count = 3;
      if ($parts[count($parts) - 2] != $dirname) { return false; } 
    }

    return $path . '/' . implode('/', array_slice($parts, -$count, $count));
  }

  function get_subcomic_directory() {
    $result = get_option('comicpress-manager-manage-subcomic');
    if (!empty($result)) {
      if ($result != $this->properties['comiccat']) {
        if (($category = get_category($result)) !== false) {
          return $category->slug;
        }
      }
    }
    return false;
  }

  /**
   * Breakdown the name of a comic file into a date and proper title.
   */
  function breakdown_comic_filename($filename, $allow_override = false, $override_value = null) {
    $pattern = CPM_DATE_FORMAT;
    if ($allow_override) {
      if (isset($_POST['upload-date-format']) && !empty($_POST['upload-date-format'])) { $pattern = $_POST['upload-date-format']; }
      if (!is_null($override_value)) { $pattern = $override_value; }
    }
    
    $pattern = $this->transform_date_string($pattern, array("Y" => '[0-9]{4,4}',
                                                            "m" => '[0-9]{2,2}',
                                                            "d" => '[0-9]{2,2}'));

    if (preg_match("/^(${pattern})(.*)\.[^\.]+$/", $filename, $matches) > 0) {
      list($all, $date, $title) = $matches;

      if (strtotime($date) === false) { return false; }
      $converted_title = ucwords(trim(preg_replace('/[\-\_]/', ' ', $title)));

      return compact('date', 'title', 'converted_title');
    } else {
      return false;
    }
  }

  function build_query_posts_string() {
    $query_posts_string = "posts_per_page=999999&post_status=draft,pending,future,inherit,publish&cat=";

    $comic_categories = array();
    
    $this->get_all_comic_categories();
    if (is_array($this->category_tree)) {
      foreach ($this->category_tree as $node) {
        $comic_categories[] = end(explode("/", $node));
      }
    }

    $query_posts_string .= implode(",", $comic_categories);  
    return $query_posts_string;
  }

  /**
   * Retrieve posts from the WordPress database.
   */
  function query_posts() {
    $result = query_posts($this->build_query_posts_string());
    if (empty($result)) { $result = array(); }
    return $result;
  }

  /**
   * Get a tree of the categories that are children of the comic category.
   */
  function get_all_comic_categories() {
    $max_id = 0;
    $category_tree = array();

    foreach (get_all_category_ids() as $category_id) {
      $category = get_category($category_id);
      $ok = true;
      if ($category->parent == 0) { $ok = ($category_id == $this->properties['comiccat']); }
      if ($ok) {
        $category_tree[] = $category->parent . '/' . $category_id;
        $max_id = max($max_id, $category_id);
      }
    }

    // flatten parents and children
    do {
      $all_ok = true; $any_changes = false;
      for ($i = 0; $i < count($category_tree); ++$i) {
        $current_parts = explode("/", $category_tree[$i]);
        if (reset($current_parts) != 0) {

          $all_ok = false; $any_changes = false;
          for ($j = 0; $j < count($category_tree); ++$j) {
            $j_parts = explode("/", $category_tree[$j]);

            if (end($j_parts) == reset($current_parts)) {
              $category_tree[$i] = implode("/", array_merge($j_parts, array_slice($current_parts, 1)));
              $any_changes = true;
              break;
            }
          }
          $all_ok = !$any_changes;
        }
      }
    } while (!$all_ok);

    $this->category_tree = $category_tree;
    $this->max_id = $max_id;

    // DEPRECATED: should be getting direct from object
    return array('category_tree' => $this->category_tree,
                 'max_id' => $this->max_id);
  }
  
  /**
   * Generate a hash for passing to wp_insert_post()
   * @param string $filename_date The post date.
   * @param string $filename_converted_title The title of the comic.
   * @return array The post information or false if the date is invalid.
   */
  function generate_post_hash($filename_date = null, $filename_converted_title = null, $override_post = null) {
    $post_data = (!is_null($override_post)) ? $override_post : $_POST;

    if (is_null($filename_date)) { return false; }
    if (is_null($filename_converted_title)) { return false; }

    if (isset($post_data['time']) && !empty($post_data['time'])) {
      if (strtolower($post_data['time']) == "now") {
        $filename_date .= " " . strftime("%H:%M:%S");
      } else {
        $filename_date .= " " . $post_data['time'];
      }
    }
    if (($timestamp = strtotime($filename_date)) !== false) {
      if ($filename_converted_title == "") {
        $filename_converted_title = strftime("%m/%d/%Y", $timestamp);
      }

      $this->normalize_storyline_structure();

      $selected_categories = array();
      if (isset($post_data['in-comic-category'])) {
        if (is_array($post_data['in-comic-category'])) {
          foreach ($this->category_tree as $node) {
            $category_id = end(explode("/", $node));
            if (in_array($category_id, $post_data['in-comic-category'])) {
              $selected_categories[$category_id] = get_cat_name($category_id);
            }
          }
        }
      }

      $all_category_ids = get_all_category_ids();
      if (isset($post_data['additional-categories'])) {
        if (is_array($post_data['additional-categories'])) {
          foreach ($post_data['additional-categories'] as $category_id) {
            if (in_array($category_id, $all_category_ids)) {
              $selected_categories[$category_id] = get_cat_name($category_id);
            }
          }
        }
      }
      
      if (empty($selected_categories)) { return false; }

      $post_category = array_keys($selected_categories);

      $category_name = implode(", ", array_values($selected_categories));

      $override_title = $post_data['override-title-to-use'];
      $tags = $post_data['tags'];
      if (get_magic_quotes_gpc()) {
        $override_title = stripslashes($override_title);
        $tags = stripslashes($tags);
      }
      $post_title    = !empty($override_title) ? $override_title : $filename_converted_title;

      $post_content = "";
      if (isset($post_data['content']) && !empty($post_data['content'])) {
        $post_content = str_replace(
                          array('{date}', '{title}', '{category}'),
                          array(
                            date('F j, Y', $timestamp),
                            $post_title,
                            $category_name
                          ),
                          $post_data['content']
                        );
      }

      $post_date     = date('Y-m-d H:i:s', $timestamp);
      $post_date_gmt = get_gmt_from_date($post_date);
      
      $publish_mode = ($timestamp > time()) ? "future" : "publish";
      $post_status   = isset($post_data['publish']) ? $publish_mode : "draft";
      $tags_input    = $tags;

      return compact('post_content', 'post_title', 'post_date', 'post_date_gmt', 'post_category', 'post_status', 'tags_input');
    }

    return false;
  }

  /**
   * Normalize a storyline structure, merging it with category changes as necessary.
   * @return array A compact()ed array with the $max_id found and the $category_tree.
   */
  function normalize_storyline_structure() {
    $this->get_all_comic_categories();

    do {
      $did_normalize = false;

      // sort it by this order as best as possible
      if ($result = get_option("comicpress-storyline-category-order")) {
        $sorted_tree = explode(",", $result);

        $new_sorted_tree = array();
        foreach ($sorted_tree as $node) {
          if (in_array($node, $this->category_tree)) {
            $new_sorted_tree[] = $node;
          } else {
            $did_normalize = true;
          }
        }
        $sorted_tree = $new_sorted_tree;

        foreach ($this->category_tree as $node) {
          if (!in_array($node, $sorted_tree)) {
            // try to find the nearest sibling
            $parts = explode("/", $node);

            while (count($parts) > 0) {
              array_pop($parts);
              $node_snippit = implode("/", $parts);
              $last_sibling = null;
              for ($i = 0; $i < count($sorted_tree); ++$i) {
                if (strpos($sorted_tree[$i], $node_snippit) === 0) {
                  $last_sibling = $i;
                }
              }
              if (!is_null($last_sibling)) {
                $did_normalize = true;
                array_splice($sorted_tree, $last_sibling + 1, 0, $node);
                break;
              }
            }
          }
        }

        $this->category_tree = $sorted_tree;
      } else {
        sort($this->category_tree);
      }
      if ($did_normalize || empty($result)) {
        update_option("comicpress-storyline-category-order", implode(",", $this->category_tree));
      }
    } while ($did_normalize);

    return array('category_tree' => $this->category_tree,
                 'max_id' => $this->max_id);
  }
  
  function convert_short_size_string_to_bytes($string) {
    $max_bytes = trim($string);

    $last = strtolower(substr($max_bytes, -1, 1));
    switch($last) {
      case 'g': $max_bytes *= 1024;
      case 'm': $max_bytes *= 1024;
      case 'k': $max_bytes *= 1024;
    }

    return $max_bytes;
  }
    
  /**
   * Find all the valid comics in the comics folder.
   * If CPM_SKIP_CHECKS is enabled, comic file validity is not checked, improving speed.
   * @param array $provided_files If given, use the provided list of files rather than glob()bing the comic folder path.
   * @return array The list of valid comic files in the comic folder.
   */
  function read_comics_folder($provided_files = null) {
    $glob_results = (is_array($provided_files) ? $provided_files : glob($this->get_comic_folder_path() . "/*"));

    if ($glob_results === false) {
      //$comicpress_manager->messages[] = "FYI: glob({$comicpress_manager->path}/*) returned false. This can happen on some PHP installations if you have no files in your comic directory. This message will disappear once you upload a comic to your site.";
      return array(); 
    }

    $filtered_glob_results = array();
    foreach ($glob_results as $result) {
      if (in_array(strtolower(pathinfo($result, PATHINFO_EXTENSION)), $this->allowed_extensions)) {
        $filtered_glob_results[] = $result;
      }
    }

    if ($this->get_cpm_option("cpm-skip-checks") == 1) {
      return $filtered_glob_results;
    } else {
      $files = array();
      foreach ($filtered_glob_results as $file) {
        if ($this->breakdown_comic_filename(pathinfo($file, PATHINFO_BASENAME)) !== false) {
          $files[] = $file;
        }
      }
      return $files;
    }
  }

  /**
   * Get the absolute filepath to the comic folder.
   */
  function get_comic_folder_path() {
    $output = CPM_DOCUMENT_ROOT . '/' . $this->properties['comic_folder'];

    if (($subdir = $this->get_subcomic_directory()) !== false) {
      $output .= '/' . $subdir;
    }

    $this->path = $output;

    return $output;
  }

  /**
   * Get the list of thumbnails to generate.
   */
  function get_thumbnails_to_generate() {
    $thumbnails_to_generate = array();

    if ($this->scale_method !== false) {
      foreach ($this->thumbs_folder_writable as $type => $value) {
        if ($value) {
          if ($this->separate_thumbs_folder_defined[$type] !== false) {
            if ($this->get_cpm_option("cpm-${type}-generate-thumbnails") == 1) {
              $thumbnails_to_generate[] = $type;
            }
          }
        }
      }
    }

    return $thumbnails_to_generate;
  }
  

  /**
   * Read the ComicPress config.
   */
  function read_comicpress_config($override_config = null) {
    global $wpmu_version;
		
		$method = null;
    
    if (is_array($override_config)) {
      $method = __("Unit Testing", 'comicpress-manager');
      $this->properties = array_merge($this->properties, $override_config);
    } else {
			if (!empty($wpmu_version) && function_exists('cpm_wpmu_load_options')) {
        cpm_wpmu_load_options();
        $method = __("WordPress Options", 'comicpress-manager');
      } else {
        $current_theme_info = get_theme(get_current_theme());

        if (isset($current_theme_info['Template Dir'])) {
          foreach (array("comicpress-config.php", "functions.php") as $possible_file) {
            $filepath = WP_CONTENT_DIR . $current_theme_info['Template Dir'] . '/' . $possible_file;
            
            if ($this->_f->file_exists($filepath)) {
              $this->config_filepath = $filepath;
              
              $file = $this->_f->file_get_contents($filepath);
              $variable_values = array();

              foreach (array_keys($this->properties) as $variable) {
                if (preg_match("#\\$${variable}\ *\=\ *([^\;]*)\;#", $file, $matches) > 0) {
                  $variable_values[$variable] = preg_replace('#"#', '', $matches[1]);
                }
              }

              $this->properties = array_merge($this->properties, $variable_values);
            
              $method = basename($filepath);
              
              $this->can_write_config = false;
              
              $perm_check_filename = $filepath . '-' . md5(rand());
              if (@touch($perm_check_filename) === true) {
                $move_check_filename = $perm_check_filename . '-' . md5(rand());
                if (@rename($perm_check_filename, $move_check_filename)) {
                  @unlink($move_check_filename);
                  $this->can_write_config = true;
                } else {
                  @unlink($perm_check_filename);
                }
              }
              
              break;
            }
          }
        }
      }
    }

    $this->config_method = $method;
  }
  
  function _check_separate_thumbnail_folders() {
    foreach (array_keys($this->separate_thumbs_folder_defined) as $type) {
      $this->separate_thumbs_folder_defined[$type] = ($this->properties['comic_folder'] != $this->properties[$type . '_comic_folder']);
    }
  }

  function _test_image_folder_writable($path, $thumb_type) {
    if (!is_dir($path)) {
      return $this->error_types['NOT_A_FOLDER'];
    }
    do {
      $tmp_filename = "test-" . md5(rand());
    } while ($this->_f->file_exists($path . '/' . $tmp_filename));
  
    $ok_to_warn = true;
    if ($thumb_type != "") {
      $ok_to_warn = ($this->get_cpm_option("cpm-${thumb_type}-generate-thumbnails") == 1);
    }

    $return_value = "";

    if (!@touch($path . '/' . $tmp_filename)) {
      if ($ok_to_warn) {
        $return_value = $this->error_types['NOT_WRITABLE'];
      }
    } else {
      if (@stat($path . '/' . $tmp_filename) === false) {
        if ($ok_to_warn) {
          $return_value = $this->error_types['NOT_STATABLE'];
        }
      }
    }

    if (($return_value !== "") || !$ok_to_warn) {
      if ($thumb_type != "") {
        $this->thumbs_folder_writable[$thumb_type] = false;
      }
    }
    
    if (is_null($this->thumbs_folder_writable[$thumb_type])) {
      if ($this->_f->file_exists($path . '/' . $tmp_filename)) {
        @unlink($path . '/' . $tmp_filename);
      }
      if ($thumb_type != "") {
        $this->thumbs_folder_writable[$thumb_type] = true;
      }
    }
    
    return $return_value;
  }

  function _check_category($type) {
    if (!is_numeric($this->properties[$type])) {
      // the property is non-numeric
      return $this->error_types['INVALID_CATEGORY'];
    } else {
      // one comic category is specified
      $result = get_category($this->properties[$type]);
      if (empty($result)) {
        return $this->error_types['CATEGORY_DOES_NOT_EXIST'];
      } else {
        $this->category_info[$type] = get_object_vars($result);
      }
    }
    return "";
  }

  /**
   * Read information about the current installation.
   */
  function read_information_and_check_config() {
    global $cpm_attempted_document_roots, $blog_id;

    $this->read_comicpress_config();
    $this->get_comic_folder_path();
    $this->plugin_path = PLUGINDIR . '/' . plugin_basename(__FILE__);
    
    $this->_check_separate_thumbnail_folders();

    $this->errors = array();
    $this->warnings = array();
    $this->detailed_warnings = array();
    $this->messages = array();
    $this->show_config_editor = true;

    if ($this->get_cpm_option("cpm-skip-checks") == 1) {
      // if the user knows what they're doing, disabling all of the checks improves performance
      foreach ($this->folders as $folder_info) {
        list ($name, $property, $is_fatal, $thumb_type) = $folder_info;
        if ($thumb_type != "") {
          $this->thumbs_folder_writable[$thumb_type] = true;
        }
      }
      
      $this->category_info['comiccat'] = get_object_vars(get_category($this->properties['comiccat']));
      $this->blog_category_info = get_object_vars(get_category($this->properties['blogcat']));
      $this->comic_files = $this->read_comics_folder();
    } else {
      foreach ($this->folders as $folder_info) {
        list ($name, $property, $is_fatal, $thumb_type) = $folder_info;
        if ($thumb_type != "") {
          $this->thumbs_folder_writable[$thumb_type] = null;
        }
      }
      
      // quick check to see if the theme is ComicPress.
      // this needs to be made more robust.
      if (preg_match('/ComicPress/', get_current_theme()) == 0) {
        $this->detailed_warnings[] = __("The current theme isn't the ComicPress theme.  If you've renamed the theme, ignore this warning.", 'comicpress-manager');
      }

      $any_cpm_document_root_failures = false;

      if (!function_exists('get_site_option')) {
        // is the site root configured properly?
        if (!$this->_f->file_exists(CPM_DOCUMENT_ROOT)) {
          $this->errors[] = sprintf(__('The comics site root <strong>%s</strong> does not exist. Check your <a href="options-general.php">WordPress address and address settings</a>.', 'comicpress-manager'), CPM_DOCUMENT_ROOT);
          $any_cpm_document_root_failures = true;
        }

        if (!$this->_f->file_exists(CPM_DOCUMENT_ROOT . '/index.php')) {
          $this->errors[] = sprintf(__('The comics site root <strong>%s</strong> does not contain a WordPress index.php file. Check your <a href="options-general.php">WordPress address and address settings</a>.', 'comicpress-manager'), CPM_DOCUMENT_ROOT);
          $any_cpm_document_root_failures = true;
        }
      }

      if ($any_cpm_document_root_failures) {
        $this->errors[] = print_r($cpm_attempted_document_roots, true);
      }

      // folders that are the same as the comics folder won't be written to
      $all_the_same = array();
      foreach ($this->separate_thumbs_folder_defined as $type => $value) {
        if (!$value) { $all_the_same[] = $type; }
      }

      if (count($all_the_same) > 0) {
        $this->detailed_warnings[] = sprintf(__("The <strong>%s</strong> folders and the comics folder are the same.  You won't be able to generate thumbnails until you change these folders.", 'comicpress-manager'), implode(", ", $all_the_same));
      }

      if ($this->get_cpm_option('cpm-did-first-run') == 1) {
        // check the existence and writability of all image folders
        foreach ($this->folders as $folder_info) {
          list ($name, $property, $is_fatal, $thumb_type) = $folder_info;
          if (($thumb_type == "") || ($this->separate_thumbs_folder_defined[$thumb_type] == true)) {
            $path = CPM_DOCUMENT_ROOT . '/' . $this->properties[$property];
            
            $result = $this->_test_image_folder_writable($path, $thumb_type);
            
            switch ($result) {
              case $this->error_types['NOT_A_FOLDER']:
                $this->errors[] = sprintf(__('The %1$s <strong>%2$s</strong> does not exist.  Did you create it within the <strong>%3$s</strong> folder?' , 'comicpress-manager'), $name, $this->properties[$property], CPM_DOCUMENT_ROOT);                
                break;
              case $this->error_types['NOT_WRITABLE']:
                $message = sprintf(__('The %1$s <strong>%2$s</strong> is not writable by the Webserver.', 'comicpress-manager'), $name, $this->properties[$property]);
                
                if ($is_fatal) {
                  $this->errors[] = $message;
                } else {
                  $this->warnings[] = $message;
                }
                break;
              case $this->error_types['NOT_STATABLE']:
                $this->errors[] = __('<strong>Files written to the %s directory by the Webserver cannot be read again!</strong>  Are you using IIS7 with FastCGI?', $this->properties[$property]);
          
                break;
            }
          }
        }
      }

      // to generate thumbnails, a supported image processor is needed
      if ($this->scale_method == false) {
        $this->detailed_warnings[] = __("No image resize methods are installed (GD or ImageMagick).  You are unable to generate thumbnails automatically.", 'comicpress-manager');
      }

      // are there enough categories created?
      if (count(get_all_category_ids()) < 2) {
        $this->errors[] = __("You need to define at least two categories, a blog category and a comics category, to use ComicPress.  Visit <a href=\"categories.php\">Manage -> Categories</a> and create at least two categories, then return here to continue your configuration.", 'comicpress-manager');
        $this->show_config_editor = false;
      } else {
        foreach ($this->category_info as $type => $value) {
          $result = $this->_check_category($type);
          
          switch ($result) {
            case $this->error_types['INVALID_CATEGORY']:
              $this->errors[] = sprintf(__("%s needs to be defined as a number, not an alphanumeric string.", 'comicpress-manager'), $type);
              break;
            case $this->error_types['CATEGORY_DOES_NOT_EXIST'];
              $this->errors[] = sprintf(__("The requested category ID for %s, <strong>%s</strong>, doesn't exist!", 'comicpress-manager'), $type, $this->properties[$type]);
              break;
          }
        }
        
        if ($this->properties['blogcat'] == $this->properties['comiccat']) {
          $this->warnings[] = __("Your comic and blog categories are the same.  This will cause browsing problems for visitors to your site.", 'comicpress-manager');
        }
      }

      // a quick note if you have no comics uploaded.
      // could be a sign of something more serious.
      if (count($this->comic_files = $this->read_comics_folder()) == 0) {
        $this->detailed_warnings[] = __("Your comics folder is empty!", 'comicpress-manager');
      }
    }
  }

}

?>