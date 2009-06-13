<?php

function cpm_action_update_cpm_config() {
  global $comicpress_manager;

  include(realpath(dirname(__FILE__)) . '/../cpm_configuration_options.php');

  $all_valid = true;
  $target_update_options = array();
  foreach ($configuration_options as $option_info) {
    $target_key = 'comicpress-manager-' . $option_info['id'];
    switch ($option_info['type']) {
      case "text":
      case "textarea":
      case "dropdown":
        if (isset($_POST[$option_info['id']])) {
          $validate_function_name = "cpm_validate_cpm_option_" . str_replace("-", "_", $option_info['id']);
          $ok = true;
          if (function_exists($validate_function_name)) {
            $ok = call_user_func($validate_function_name, $_POST[$option_info['id']]);
          }
          if ($ok) { 
            $target_update_options[$target_key] = $_POST[$option_info['id']];
          } else {
            $all_valid = false;
            break 2;
          }
        }
        break;
      case "checkbox":
        $target_update_options[$target_key] = (isset($_POST[$option_info['id']]) ? "1" : "0");
        break;
      case "categories":
        if (isset($_POST[$option_info['id']])) {
          $all_categories = implode(",", $_POST[$option_info['id']]);
          $target_update_options[$target_key] = $all_categories;
        } else {
          $target_update_options[$target_key] = "";
        }
        break;
    }
  }

  if ($all_valid) {
    $comicpress_manager->read_information_and_check_config();

    foreach ($target_update_options as $option => $value) { update_option($option, $value); }
    $comicpress_manager->messages[] = __("<strong>ComicPress Manager configuration updated.</strong>", 'comicpress-manager');
  } else {
    $comicpress_manager->warnings[] = __("<strong>You entered invalid data into your configuration.</strong> Configuration  not updated.", 'comicpress-manager');
  }
}

function cpm_validate_cpm_option_cpm_default_post_time($value) {
  return (strtotime("2008-01-01 {$value}") !== false);
}

?>