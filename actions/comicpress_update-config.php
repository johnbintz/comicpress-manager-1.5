<?php

function cpm_action_update_config() {
  global $comicpress_manager;

  $comicpress_manager->is_cpm_managing_posts = true;

  $do_write = false;
  $use_default_file = false;

  if ($comicpress_manager->config_method == "comicpress-config.php") {
    $do_write = !isset($_POST['just-show-config']);
  } else {
    $use_default_file = true;
  }

  include(realpath(dirname(__FILE__)) . '/../cp_configuration_options.php');

  $original_properties = $comicpress_manager->properties;

  foreach ($comicpress_configuration_options as $field_info) {
    extract($field_info);

    $config_id = (isset($field_info['variable_name'])) ? $field_info['variable_name'] : $field_info['id'];

    switch ($type) {
      case "folder":
        $comicpress_manager->properties[$config_id] = $_POST[$_POST["folder-{$config_id}"] . "-" . $config_id];
        break;
      default:
        if (isset($_POST[$config_id])) {
          $comicpress_manager->properties[$config_id] = $_POST[$config_id];
        }
        break;
    }
  }

  if (function_exists('get_site_option')) {
    cpm_wpmu_save_options();
    $comicpress_manager->is_wp_options = true;
  }

  if (!$comicpress_manager->is_wp_options) {
    if (!$do_write) {
      $file_output = write_comicpress_config_functions_php($comicpress_manager->config_filepath, true, $use_default_file);
      $comicpress_manager->properties = $original_properties;
      if ($use_default_file) {
        $comicpress_manager->messages[] = __("<strong>No comicpress-config.php file was found in your theme folder.</strong> Using default configuration file.", 'comicpress-manager');
      }
      $comicpress_manager->messages[] = __("<strong>Your configuration:</strong>", 'comicpress-manager') . "<pre class=\"code-block\">" . htmlentities($file_output) . "</pre>";
    } else {
      if (!is_null($comicpress_manager->config_filepath)) {
        if (is_array($file_output = write_comicpress_config_functions_php($comicpress_manager->config_filepath))) {
          $comicpress_manager->read_comicpress_config();
          $comicpress_manager->path = $comicpress_manager->get_comic_folder_path();
          $comicpress_manager->plugin_path = PLUGINDIR . '/' . plugin_basename(__FILE__);

          $comicpress_manager->read_information_and_check_config();

          $backup_file = pathinfo($file_output[0], PATHINFO_BASENAME);

          $comicpress_manager->messages[] = sprintf(__("<strong>Configuration updated and original config backed up to %s.</strong> Rename this file to comicpress-config.php if you are having problems.", 'comicpress-manager'), $backup_file);

        } else {
          $relative_path = substr(realpath($comicpress_manager->config_filepath), CPM_STRLEN_REALPATH_DOCUMENT_ROOT);
          $comicpress_manager->warnings[] = sprintf(__("<strong>Configuration not updated</strong>, check the permissions of %s and the theme folder.  They should be writable by the Webserver process. Alternatively, copy and paste the following code into your comicpress-config.php file:", 'comicpress-manager'), $relative_path) . "<pre class=\"code-block\">" . htmlentities($file_output) . "</pre>";

          $comicpress_manager->properties = $original_properties;
        }
      }
    }
  } else {
    $comicpress_manager->read_comicpress_config();

    $comicpress_manager->messages[] = sprintf(__("<strong>Configuration updated in database.</strong>", 'comicpress-manager'), $backup_file);
  }


}

?>