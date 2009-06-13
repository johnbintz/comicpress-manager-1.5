<h2 style="padding-right:0;">
  <?php if ($this->zip_extension_loaded) {
    _e("Upload Image &amp; Zip Files", 'comicpress-manager');
  } else {
    _e("Upload Image Files", 'comicpress-manager');
  } ?>
</h2>
<h3>&mdash;
  <?php if ($comicpress_manager->get_cpm_option('cpm-obfuscate-filenames-on-upload') === "none") { ?>
    <?php _e("any existing files with the same name will be overwritten", 'comicpress-manager') ?>
  <?php } else { ?>
    <?php _e("uploaded filenames will be obfuscated, therefore no old files will be overwritten after uploading", 'comicpress-manager') ?>
  <?php } ?>
</h3>

<?php if (!function_exists('get_site_option')) { ?>
  <?php if (!$this->zip_extension_loaded) { ?>
    <div id="zip-upload-warning">
      <?php printf(__('<strong>You do not have the Zip extension installed.</strong> Uploading a Zip file <strong>will not work</strong>. Either upload files individually or <a href="%s">FTP/SFTP the files to your site and import them</a>.', 'comicpress-manager'), "?page=" .  plugin_basename(realpath(dirname(__FILE__) . '/../comicpress_manager_admin.php') . '-import')) ?>
    </div>
  <?php } ?>
<?php } ?>

<?php $target_url = add_query_arg("upload", "1") ?>

<form onsubmit="$('submit').disabled=true" action="<?php echo $target_url ?>" method="post" enctype="multipart/form-data">
  <input type="hidden" name="action" value="multiple-upload-file" />
  <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $comicpress_manager->convert_short_size_string_to_bytes(ini_get('upload_max_filesize')) ?>" />
  <div id="multiple-file-upload">
  </div>
  <div style="text-align: center">
    [<a href="#" onclick="add_file_upload(); return false"><?php _e("Add file to upload", 'comicpress-manager') ?></a>]
  </div>

  <table class="form-table">
    <tr>
      <th scope="row"><?php _e("Destination for uploaded files:", 'comicpress-manager') ?></th>
      <td>
        <select name="upload-destination" id="upload-destination">
          <option value="comic"><?php _e("Comics folder", 'comicpress-manager') ?></option>
          <option value="archive_comic"><?php _e("Archive folder", 'comicpress-manager') ?></option>
          <option value="rss_comic"><?php _e("RSS feed folder", 'comicpress-manager') ?></option>
        </select>
      </td>
    </tr>
    <?php if (count($comicpress_manager->comic_files) > 0) { ?>
      <tr id="overwrite-existing-holder">
        <th scope="row"><?php _e("Overwrite an existing file:", 'comicpress-manager') ?></th>
        <td>
          <select name="overwrite-existing-file-choice" id="overwrite-existing-file-choice">
            <option value=""><?php _e("-- no --", 'comicpress-manager') ?></option>
            <?php
              foreach ($comicpress_manager->comic_files as $file) {
                $basename = pathinfo($file, PATHINFO_BASENAME); ?>
                <option value="<?php echo $basename ?>"
                <?php echo ($_GET['replace'] == $basename) ? "selected" : "" ?>><?php echo $basename ?></option>
              <?php }
            ?>
          </select>
        </td>
      </tr>
      <tr id="rebuild-thumbnails">
        <th scope="row"><?php _e("Rebuild thumbnails?", 'comicpress-manager') ?></th>
        <td>
          <label>
            <input type="checkbox" id="replace-comic-rebuild-thumbnails" name="replace-comic-rebuild-thumbnails" value="yes" checked="checked" />
            <em>(if replacing a comic in the <strong>comic</strong> folder, you can also regenerate thumbnails)</em>
          </label>
        </td>
      </tr>
    <?php } ?>
    <tr>
      <td align="center" colspan="2">
        <input class="button" id="submit" type="submit" value="<?php
          if ($this->zip_extension_loaded) {
            _e("Upload Image &amp; Zip Files", 'comicpress-manager');
          } else {
            _e("Upload Image Files", 'comicpress-manager');
          }
        ?>" />
      </td>
    </tr>
  </table>

  <div id="upload-destination-holder">
    <table class="form-table">
      <tr>
        <th scope="row"><?php _e("Generate new posts for each uploaded file:", 'comicpress-manager') ?></th>
        <td>
          <input id="multiple-new-post-checkbox" type="checkbox" name="new_post" value="yes" checked="checked" />
          <label for="multiple-new-post-checkbox"><em>(if you only want to upload a series of files to replace others, leave this unchecked)</em></label>
        </td>
      </tr>
    </table>

    <div id="multiple-new-post-holder">
      <table class="form-table" id="specify-date-holder">
        <tr>
          <th scope="row"><?php _e("Date for uploaded file:", 'comicpress-manager') ?></th>
          <td>
            <div class="curtime"><input type="text" id="override-date" name="override-date" /> <?php _e("<em>(click to open calendar. for single file uploads only. can accept any date format parseable by <a href=\"http://us.php.net/strtotime\" target=\"php\">strtotime()</a>)</em>", 'comicpress-manager') ?></div>
          </td>
        </tr>
      </table>

      <?php $this->post_editor->render() ?>

      <table class="form-table">
        <tr>
          <td align="center">
            <input class="button" id="top-submit" type="submit" value="<?php
              if (extension_loaded("zip")) {
                _e("Upload Image &amp; Zip Files", 'comicpress-manager');
              } else {
                _e("Upload Image Files", 'comicpress-manager');
              }
            ?>" />
          </td>
        </tr>
      </table>
    </div>
  </div>
</form>
<script type="text/javascript">
  Calendar.setup({
    inputField: "override-date",
    ifFormat: "%Y-%m-%d",
    button: "override-date"
  });
</script>
