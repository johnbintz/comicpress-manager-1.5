<?php

/**
 * The config editor dialog.
 */
function cpm_manager_cpm_config() {
  global $comicpress_manager;

  include(realpath(dirname(__FILE__)) . '/../cpm_configuration_options.php');

  $is_table = false;

  ob_start(); ?>

  <h2 style="padding-right:0;"><?php _e("Edit ComicPress Manager Config", 'comicpress-manager') ?></h2>

  <form action="" method="post" id="config-editor">
    <input type="hidden" name="action" value="update-cpm-config" />
      <?php foreach ($configuration_options as $option) {
        $ok = true;
        if (function_exists('get_site_option')) { $ok = !isset($option['strip-wpmu']); }
        if (is_string($option)) { $ok = true; }

        if ($option['type'] == "categories") {
          $ok = (count($category_checkboxes = cpm_generate_additional_categories_checkboxes($option['id'], explode(",", $result))) > 0);
        }

        if ($ok) { ?>
          <?php if (is_string($option)) { ?>
            <?php if ($is_table) { ?>
              </table></div>
              <?php $is_table = false;
            } ?>
            <h3><?php echo $option ?></h3>
          <?php } else {
            if (!$is_table) { ?>
              <div style="overflow: hidden"><table class="form-table">
              <?php $is_table = true;
            } ?>
            <tr>
              <th scope="row"><?php echo $option['name'] ?></th>
              <td>
                <?php
                  $result = $comicpress_manager->get_cpm_option($option['id']);
                  switch($option['type']) {
                    case "checkbox":
                      $ok = true;
                      if (isset($option['imagemagick-only'])) {
                        $ok = (is_a($comicpress_manager->scale_method, "ComicPressImageMagickProcessing"));
                      }

                      if ($ok) { ?>
                        <input type="checkbox" id="<?php echo $option['id'] ?>" name="<?php echo $option['id'] ?>" value="yes" <?php echo ($result == 1) ? " checked" : "" ?> />
                      <?php }

                      break;
                    case "text": ?>
                      <input type="text" size="<?php echo (isset($option['size']) ? $option['size'] : 10) ?>" name="<?php echo $option['id'] ?>" value="<?php echo $result ?>" />
                      <?php break;
                    case "textarea": ?>
                      <textarea name="<?php echo $option['id'] ?>" rows="4" cols="30"><?php echo $result ?></textarea>
                      <?php break;
                    case "dropdown":
                      $dropdown_parts = array();

                      foreach (explode("|", $option['options']) as $dropdown_option) {
                        $parts = explode(":", $dropdown_option);
                        $key = array_shift($parts);
                        $dropdown_parts[$key] = implode(":", $parts);
                      }
                      ?>
                      <select name="<?php echo $option['id'] ?>">
                        <?php foreach ($dropdown_parts as $key => $value) { ?>
                          <option value="<?php echo $key ?>" <?php echo ($result == $key) ? " selected" : "" ?>><?php echo $value ?></option>
                        <?php } ?>
                      </select>
                      <?php break;
                    case "categories":
                      echo implode("\n", $category_checkboxes);
                      break;
                  }
                ?>
                <em><label for="<?php echo $option['id'] ?>">(<?php echo $option['message'] ?>)<label></em>
              </td>
            </tr>
          <?php } ?>
        <?php } ?>
      <?php } ?>
      <tr>
        <td colspan="2" align="center">
          <input class="button" type="submit" value="Change Configuration" />
        </td>
      </tr>
    </table></div>
  </form>

  <div id="first-run-holder">
    <p><strong>Re-run the &quot;First Run&quot; action? This will attempt to create the default comic folders on your site.</strong></p>

    <form action="<?php echo $target_page ?>" method="post">
      <input type="hidden" name="action" value="do-first-run" />
      <input class="button" type="submit" value="Yes, try and make my comic directories" />
    </form>
  </div>

  <?php

  $activity_content = ob_get_clean();

  cpm_wrap_content(null, $activity_content);
}

?>