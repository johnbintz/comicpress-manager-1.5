<table class="form-table">
  <tr>
    <th scope="row">
      <?php if ($this->has_comic_file) { ?>
        <?php _e("Replace This Image", 'comicpress-manager') ?>
      <?php } else { ?>
        <?php _e("Upload a New Single Image", 'comicpress-manager') ?>
      <?php } ?>
    </th>
    <td>
      <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $comicpress_manager->convert_short_size_string_to_bytes(ini_get('upload_max_filesize')) ?>" />
      <input type="file" id="comicpress-replace-image" name="comicpress-replace-image" class="button" /> <?php echo (empty($this->thumbnails_to_generate)) ? "" : __("<em>(thumbnails will be generated)</em>", 'comicpress-manager') ?><br />
      <?php if ($this->has_comic_file) { ?>
        <input type="hidden" name="overwrite-existing-file-choice" value="<?php echo $this->comic_filename ?>" />
      <?php } ?>
      <input type="hidden" name="upload-destination" value="comic" />
      <input type="hidden" name="thumbnails" value="yes" />
    </td>
    <script type="text/javascript">
      Event.observe('comicpress-replace-image', 'click', function() {
        [<?php echo (is_array($comicpress_manager->properties['comiccat'])) ?
                    implode(",", $comicpress_manager->properties['comiccat']) :
                    $comicpress_manager->properties['comiccat'] ?>].each(function(i) {
          $('in-category-' + i).checked = true;
        });
      });
    </script>
  </tr>
  <?php
    if ($comicpress_manager->get_cpm_option('cpm-skip-checks') != 1) {
      if (!function_exists('get_comic_path')) { ?>
        <tr>
          <td colspan="2" style="background-color: #fee; border: solid #daa 1px">
            <?php _e('<strong>It looks like you\'re running an older version of ComicPress.</strong> Storyline, hovertext, and transcript are fully supported in <a href="http://comicpress.org/">ComicPress 2.7</a>. You can use hovertext and transcripts in earlier themes by using <tt>get_post_meta($post->ID, "hovertext", true)</tt> and <tt>get_post_meta($post->ID, "transcript", true)</tt>.', 'comicpress-manager') ?>
          </td>
        </tr>
      <?php }
    } ?>
  <?php if (get_option('comicpress-enable-storyline-support') == 1) { ?>
    <tr>
      <th scope="row">
        <?php
          if (count($this->category_tree) > 1) {
            _e("Storyline", 'comicpress-manager');
          } else {
            _e("Category", 'comicpress-manager');
          }
        ?>
      </th>
      <td>
        <?php $this->_display_storyline_checkboxes(null, "post_category") ?>
      </td>
    </tr>
  <?php } ?>
  <tr>
    <th scope="row"><?php _e('&lt;img title&gt;/hover text', 'comicpress-manager') ?></th>
    <td><input type="text" name="comicpress-img-title" size="50" value="<?php echo get_post_meta($post->ID, 'hovertext', true) ?>" /></td>
  </tr>
  <tr>
    <th scope="row"><?php _e("Transcript", 'comicpress-manager') ?></th>
    <td><textarea name="comicpress-transcript" rows="8" cols="50"><?php echo get_post_meta($post->ID, 'transcript', true) ?></textarea></td>
  </tr>
</table>