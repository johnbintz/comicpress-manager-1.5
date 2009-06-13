<?php

/**
 * The config editor dialog.
 */
function cpm_manager_config() {
  global $comicpress_manager;
  extract($comicpress_manager->normalize_storyline_structure());

  ob_start(); ?>

  <h2 style="padding-right:0;"><?php _e("Edit ComicPress Config", 'comicpress-manager') ?></h2>
  <?php if (!$comicpress_manager->can_write_config && !function_exists('get_site_option')) { ?>
    <p>
      <?php
        _e("<strong>You won't be able to automatically update your configuration.</strong> After submitting, you will be shown the code to paste into comicpress-config.php. If you want to enable automatic updating, check the permissions of your theme folder and comicpress-config.php file.", 'comicpress-manager');
       ?>
    </p>
  <?php }
  echo cpm_manager_edit_config();
  ?>

  <?php if (get_option('comicpress-enable-storyline-support') == 1) { ?>
    <form action="" method="post">
      <input type="hidden" name="action" value="manage-subcomic" />
      <table class="form-table" cellspacing="0">
        <tr>
          <th scope="row">Manage a subcomic</th>
          <td>
            <select name="comic">
              <?php
                $first = true;
                $path = CPM_DOCUMENT_ROOT . '/' . $comicpress_manager->properties['comic_folder'];

                foreach ($category_tree as $node) {
                  $category = get_category(end(explode("/", $node)));

                  $selected = (get_option('comicpress-manager-manage-subcomic') == $category->term_id) ? " selected" : "";

                  if (is_dir($path . '/' . $category->slug) || $first) { ?>
                    <option value="<?php echo $category->term_id ?>"<?php echo $selected ?>><?php echo $category->name . ($first ? " (default)" : "") ?></option>
                  <?php }

                  $first = false;
                }
              ?>
            </select>
            <input type="submit" value="Submit" />
          </td>
        </tr>
      </table>
    </form>
  <?php }

  $activity_content = ob_get_clean();

  cpm_wrap_content(null, $activity_content);
}

?>