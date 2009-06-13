<?php

//harmonious @hash

/**
 * The change dates dialog.
 */
function cpm_manager_dates() {
  global $comicpress_manager;

  $comicpress_manager->need_calendars = true;

  if ($comicpress_manager->get_subcomic_directory() !== false) {
    $comicpress_manager->messages[] = __("<strong>Subdirectory support enabled.</strong> Change Dates may not work as expected.", 'comicpress-manager');
  }

  $comic_format_date_string = date(CPM_DATE_FORMAT);

  $dates_output_format = "Y-m-d";

  $start_date = date($dates_output_format);
  $end_date = substr(pathinfo(end($comicpress_manager->comic_files), PATHINFO_BASENAME), 0, strlen($comic_format_date_string));
  $end_date = date($dates_output_format, strtotime($end_date));

  if (isset($_POST['start-date']) && !empty($_POST['start-date'])) {
    $target_start_date = strtotime($_POST['start-date']);
    if (($target_start_date != -1) && ($target_start_date !== false)) {
      $start_date = date($dates_output_format, $target_start_date);
    } else {
      $comicpress_manager->warnings[] = $_POST['start-date'] . " is an invalid date.  Resetting to ${start_date}";
    }
  
    $target_end_date = strtotime($_POST['end-date']);
    if (($target_end_date != -1) && ($target_end_date !== false)) {
      $end_date = date($dates_output_format, $target_end_date);
    } else {
      $comicpress_manager->warnings[] = $_POST['end-date'] . " is an invalid date.  Resetting to ${end_date}";
    }
  }

  if (strtotime($end_date) < strtotime($start_date)) {
    list($start_date, $end_date) = array($end_date, $start_date);
  }

  $visible_comic_files = array();
  $visible_comic_files_md5 = array();

  $start_date_timestamp = strtotime($start_date);
  $end_date_timestamp = strtotime($end_date);

  foreach ($comicpress_manager->comic_files as $file) {
    $filename = pathinfo($file, PATHINFO_BASENAME);
    $result = $comicpress_manager->breakdown_comic_filename($filename);
    $result_date_timestamp = strtotime($result['date']);

    if (($result_date_timestamp >= $start_date_timestamp) && ($result_date_timestamp <= $end_date_timestamp)) {
      $visible_comic_files[] = $file;
      $visible_comic_files_md5[] = "\"" . md5($file) . "\"";
    }
  }

  $help_content = __("<p><strong>Change post &amp; comic dates</strong> lets you change the comic file names and post dates for any and every comic published. You will only be able to move a comic file and its associated post if there is no comic or post that exists on the destination date, as ComicPress Manager cannot automatically resolve such conflicts.</p>", 'comicpress-manager');

  $help_content .= __("<p><strong>This is a potentialy dangerous and resource-intensive operation.</strong> Back up your database and comics/archive/RSS folders before performing large move operations.  Additionally, if you experience script timeouts while moving large numbers of posts, you may have to move posts & comic files by hand rather than through ComicPress Manager.</p>", 'comicpress-manager');

  ob_start();
  
  ?>
  
  <h2 style="padding-right:0;"><?php _e("Change Post &amp; Comic Dates", 'comicpress-manager') ?></h2>
  <h3>&mdash; <?php _e("date changes will affect comics that are associated or not associated with posts", 'comicpress-manager') ?></h3>

  <?php if (count($comicpress_manager->comic_files) > 0) { ?>
    <form action="" method="post">
      <?php printf(__('Show comics between %1$s and %2$s', 'comicpress-manager'),
                      "<input type=\"text\" id=\"start-date\" name=\"start-date\" size=\"12\" value=\"${start_date}\" />",
                      "<input type=\"text\" id=\"end-date\" name=\"end-date\" size=\"12\" value=\"${end_date}\" />") ?>

      <input type="submit" value="<?php _e("Filter", 'comicpress-manager') ?>" />
    </form>

    <script type="text/javascript">
      var comic_files_keys = [ <?php echo implode(", ", $visible_comic_files_md5); ?> ];

      var days_between_posts_message = "<?php _e("How many days between posts?  Separate multiple intervals with commas (Ex: MWF is 2,2,3):", 'comicpress-manager') ?>";
      var valid_interval_message = "<?php _e("is a valid interval", 'comicpress-manager') ?>";
    </script>
    <?php cpm_include_javascript("comicpress_dates.js") ?>

    <form onsubmit="$('submit').disabled=true" action="" method="post">
      <input type="hidden" name="action" value="change-dates" />
      <input type="hidden" name="start-date" value="<?php echo $start_date ?>" />
      <input type="hidden" name="end-date" value="<?php echo $end_date ?>" />

      <?php
      $field_to_setup = array();
      foreach ($visible_comic_files as $file) {
        $filename = pathinfo($file, PATHINFO_BASENAME);
        $result = $comicpress_manager->breakdown_comic_filename($filename);

        $key = md5($file);
        $fields_to_setup[] = "'dates[${key}]'";
        ?>
        <div id="holder-<?php echo $key ?>" style="border-bottom: solid #666 1px; padding-bottom: 3px; margin-bottom: 3px">
          <table cellspacing="0">
            <tr>
              <td width="300" class="form-title"><?php echo $filename ?></td>
              <td><input size="12" onchange="$('holder-<?php echo $key ?>').style.backgroundColor=(this.value != '<?php echo $result['date'] ?>' ? '#ddd' : '')" type="text" name="dates[<?php echo $key ?>]" id="dates[<?php echo $key ?>]" value="<?php echo $result['date'] ?>" />
              [<a title="<?php printf(__("Reset date to %s", 'comicpress-manager'), $result['date']) ?>" href="#" onclick="$('holder-<?php echo $key ?>').style.backgroundColor=''; $('dates[<?php echo $key ?>]').value = '<?php echo $result['date'] ?>'; return false">R</a> | <a title="<?php _e("Re-schedule posts from this date at a daily interval", 'comicpress-manager') ?>" href="#" onclick="reschedule_posts('<?php echo $key ?>'); return false">I</a>]</td>
            </tr>
          </table>
        </div>
      <?php } ?>
      <script type="text/javascript">
        var fields_to_setup = [ 'start-date', 'end-date', <?php echo implode(", ", $fields_to_setup) ?> ];

        for (var i = 0, len = fields_to_setup.length; i < len; ++i) {
          var format = (i < 2) ? "%Y-%m-%d" : "<?php echo preg_replace('/([a-zA-Z])/', '%\1', CPM_DATE_FORMAT) ?>";
          Calendar.setup({
            inputField: fields_to_setup[i],
            ifFormat: format,
            button: fields_to_setup[i]
          });
        }
      </script>
      <div style="text-align: center">
        <input class="button" type="submit" id="submit" value="<?php _e("Change Dates", 'comicpress-manager') ?>" />
      </div>
    </form>
  <?php } else { ?>
    <p><?php _e("You haven't uploaded any comics yet.", 'comicpress-manager') ?></p>
  <?php } ?>

  <?php

  $activity_content = ob_get_clean();

  cpm_wrap_content($help_content, $activity_content);
}

?>