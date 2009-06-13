<?php if (count($comicpress_manager->comic_files) == 0) { ?>
  <div style="border: solid #daa 1px; background-color: #ffe7e7; padding: 5px">
    <strong>It looks like this is a new ComicPress install.</strong> You should test to make
    sure uploading works correctly by visiting <a href="admin.php?page=<?php echo plugin_basename(realpath(dirname(__FILE__) . '/../comicpress_manager_admin.php')) ?>">ComicPress -> Upload</a>.
  </div>
<?php } ?>

<?php if ($this->has_comic_file) { ?>
  <div id="comic-hover" style="border: solid black 1px; position: absolute; display: none" onmouseout="hide_comic()">
    <img id="preview-comic" src="<?php echo $this->comic_uri ?>" />
  </div>
  <a href="#" onclick="return false" onmouseover="show_comic()"><img id="comic-icon" src="<?php echo $this->icon_uri ?>" height="100" align="right" /></a>
  <p>
    <?php printf(__("The comic that will be shown with this post is %s.", 'comicpress-manager'), $this->link) ?>
    <?php _e("Mouse over the icon to the right to see a larger version of the image.", 'comicpress-manager') ?>
  </p>

  <?php
    if ($comicpress_manager->get_subcomic_directory() !== false) {
      printf(__("Comic files will be uploaded to the <strong>%s</strong> comic subdirectory.", 'comicpress-manager'), get_cat_name(get_option('comicpress-manager-manage-subcomic')));
    }
  ?>

  <?php if (count($this->thumbnails_found) > 0) { ?>
    <p><?php _e("The following thumbnails for this comic were also found:", 'comicpress-manager') ?>
      <?php foreach ($this->thumbnails_found as $type => $file) { ?>
        <a target="comic_window" href="<?php echo $comicpress_manager->build_comic_uri(CPM_DOCUMENT_ROOT . '/' . $file) ?>"><?php echo $type ?></a>
      <?php } ?>
    </p>
  <?php } ?>
<?php } ?>

<?php if ($comicpress_manager->get_cpm_option("cpm-edit-post-integrate") == 1) { ?>
  <p><em><strong>ComicPress Manager Edit Post file management is enabled.</strong></em> Any changes to post date, or deleting this post, will affect any associated comic files.</p>
<?php } ?>

<p><strong>NOTE: Upload errors will not be reported.</strong> If you are having trouble uploading files, use the <a href="admin.php?page=<?php echo plugin_basename(realpath(dirname(__FILE__) . '/../comicpress_manager_admin.php')) ?>">ComicPress -> Upload</a> screen.</p>
