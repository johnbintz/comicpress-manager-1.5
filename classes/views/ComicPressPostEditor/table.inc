<table class="form-table">
  <tr>
    <th valign="top" scope="row"><?php _e("Storyline:", 'comicpress-manager') ?></th>
    <td valign="top"><?php echo $this->_display_storyline_checkboxes ?></td>
  </tr>

  <?php if (!empty($this->category_checkboxes)) { ?>
    <tr>
      <th valign="top" scope="row"><?php _e("Additional Categories", 'comicpress-manager') ?></th>
      <td valign="top"><?php echo implode("\n", $this->category_checkboxes) ?></td>
    </tr>
  <?php } ?>
  
  <tr>
    <th valign="top" scope="row"><?php _e("Time to post:", 'comicpress-manager') ?></th>
    <td valign="top">
      <input type="text" name="time" value="<?php echo $comicpress_manager->get_cpm_option('cpm-default-post-time') ?>" size="10" />
      <?php _e(" <em>(must be in the format <strong>HH:MM am/pm</strong> or <strong>now</strong>)</em>", 'comicpress-manager') ?>
    </td>
  </tr>
  
  <tr>
    <th valign="top" scope="row">
      <label for="publish"><?php _e("Publish post:", 'comicpress-manager') ?></label>
    </th>
    <td valign="top">
      <input id="publish" type="checkbox" name="publish" value="yes" checked="checked" />
      <label for="publish">
        <?php _e("(set the status of this post to <strong>published</strong> instead of <strong>draft</strong>)", 'comicpress-manager') ?>
      </label>
    </td>
  </tr>

  <?php if ($this->is_import) { ?>
    <input type="hidden" name="duplicate_check" value="yes" />
  <?php } else { ?>
    <tr>
      <th valign="top" scope="row">
        <label for="duplicate-check">
          <?php _e("Check for duplicate posts:", 'comicpress-manager') ?>
        </label>
      </th>
      <td valign="top">
        <input id="duplicate-check" type="checkbox" name="duplicate_check" value="yes" checked="checked" />
        <label for="duplicate-check">
          <?php _e("(if you've set up ComicPress to use multiple posts on the same day, you'll need to disable this option to allow ComicPress Manager to make multiple posts)", 'comicpress-manager') ?>
        </label>
      </td>
    </tr>
  <?php } ?>
  
  <?php if (!empty($this->thumbnail_writes)) { ?>
    <tr>
      <th valign="top" scope="row">
        <label for="thumbnails"><?php _e("Generate thumbnails:", 'comicpress-manager') ?></label>
      </th>
      <td valign="top">
        <input onclick="hide_show_div_on_checkbox('thumbnail-write-holder', this, true)" type="checkbox" name="thumbnails" id="thumbnails" value="yes" checked="checked" />
        <label for="thumbnails">
          <?php printf(__("If enabled, you'll be writing thumbnails to: %s", 'comicpress-manager'), implode(", ", $this->thumbnail_writes)) ?>
        </label>
      </td>
    </tr>
  <?php } ?>

  <tr>
    <th valign="top" scope="row">
      <?php _e("Title For All Posts:", 'comicpress-manager') ?>
    </th>
    <td valign="top">
      <input type="text" name="override-title-to-use" value="<?php echo $comicpress_manager->get_cpm_option('cpm-default-override-title') ?>" />
      <label>
        <?php _e("(the title to use for all posts)", 'comicpress-manager') ?>
      </label>
    </td>
  </tr>

  <tr>
    <th valign="top" scope="row">
      <?php _e("&lt;img title&gt;/Hovertext For All Posts:", 'comicpress-manager') ?>
    </th>
    <td valign="top">
      <input type="text" name="hovertext-to-use" value="<?php echo $comicpress_manager->get_cpm_option('cpm-default-hovertext') ?>" />
      <label>
        <?php _e('(the hovertext to use for all posts)', 'comicpress-manager') ?>
      </label>
    </td>
  </tr>

  <tr>
    <th valign="top" scope="row">
      <?php _e("Transcript For All Posts:", 'comicpress-manager') ?>
    </th>
    <td valign="top">
      <textarea name="transcript-to-use" rows="5" cols="30"></textarea>
      <label>
        <?php _e('(the transcript to use for all posts)', 'comicpress-manager') ?>
      </label>
    </td>
  </tr>
    
  <tr>
    <th valign="top" scope="row">
      <?php _e("Upload Date Format:", 'comicpress-manager') ?>
    </th>
    <td valign="top">
      <input type="text" name="upload-date-format" />
      <label>
        <?php _e('(if the files you are uploading have a different date format, specify it here. ex: <strong>Ymd</strong> for a file named <strong>20080101-my-new-years-day.jpg</strong>)', 'comicpress-manager') ?>
      </label>
    </td>
  </tr>

  <tr>
    <th valign="top" scope="row">
      <?php _e("Tags:", 'comicpress-manager') ?>
    </th>
    <td valign="top">
      <input type="text" id="tags" name="tags" value="<?php echo $comicpress_manager->get_cpm_option('cpm-default-post-tags') ?>" />
      <label>
        <?php _e("(any tags to add to the post, separated by commas. any tags you've used before will be listed below.)", 'comicpress-manager') ?>
      </label>
    </td>
  </tr>
  
  <?php if (!empty($this->all_tags)) { ?>
    <tr>
      <th valign="top" scope="row">
        <?php _e('Quick Tags (click to add):', 'comicpress-manager') ?>
      </th>
      <td valign="top">
        <?php foreach ($this->all_tags as $tag) { ?>
          <a href="#" class="tag"><?= $tag->name ?></a>
        <?php } ?>
      </td>
    </tr>
  <?php } ?>
</table>

<table class="form-table">
  <tr>
    <td valign="top">
      <strong>Post body template:</strong>
      <div id="title"></div>
      <div id="<?php echo user_can_richedit() ? 'postdivrich' : 'postdiv' ?>" class="postarea">
        <?php the_editor($comicpress_manager->get_cpm_option('cpm-default-post-content')) ?>
      </div>

      <br />
      (<em><?php _e("Available wildcards:", 'comicpress-manager') ?></em>)
      <ul>
        <li><strong>{category}</strong>: <?php _e("The name of the category", 'comicpress-manager') ?></li>
        <li><strong>{date}</strong>: <?php printf(__("The date of the comic (ex: <em>%s</em>)", 'comicpress-manager'), date("F j, Y", time())) ?></li>
        <li><strong>{title}</strong>: <?php _e("The title of the comic", 'comicpress-manager') ?></li>
      </ul>
    </td>
  </tr>
</table>
  