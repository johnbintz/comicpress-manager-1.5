<?php

require_once('ComicPressPostEditor.php');

class ComicPressUpload extends ComicPressView {
  function ComicPressUpload() {
    global $comicpress_manager;

    $comicpress_manager->need_calendars = true;
    $this->example_date = $comicpress_manager->generate_example_date(CPM_DATE_FORMAT);
    $this->example_real_date = date(CPM_DATE_FORMAT);
    $this->zip_extension_loaded = extension_loaded('zip');
    $this->post_editor = new ComicPressPostEditor(420);
  }
  
  function render() {
    global $comicpress_manager;
    if ($comicpress_manager->get_subcomic_directory() !== false) {
      $comicpress_manager->messages[] = sprintf(__("<strong>Reminder:</strong> You are managing the <strong>%s</strong> comic subdirectory.", 'comicpress-manager'), get_cat_name(get_option('comicpress-manager-manage-subcomic')));
    }

    // need to do checks better
    //     if ($comicpress_manager->get_cpm_option('cpm-skip-checks') != 1) {
    //       if (!function_exists('get_comic_path')) {
    //         $comicpress_manager->warnings[] =  __('<strong>It looks like you\'re running an older version of ComicPress.</strong> Storyline, hovertext, and transcript are fully supported in <a href="http://comicpress.org/">ComicPress 2.7</a>. You can use hovertext and transcripts in earlier themes by using <tt>get_post_meta($post->ID, "hovertext", true)</tt> and <tt>get_post_meta($post->ID, "transcript", true)</tt>.', 'comicpress-manager');
    //       }
    //     }

    if (count($_POST) == 0 && isset($_GET['upload'])) {
      $comicpress_manager->warnings[] = sprintf(__("Your uploaded files were larger than the <strong><tt>post_max_size</tt></strong> setting, which is currently <strong><tt>%s</tt></strong>. Either upload fewer/smaller files, upload them via FTP/SFTP, or increase your server's <strong><tt>post_max_size</tt></strong>.", 'comicpress-manager'), ini_get('post_max_size'));
    }

    include($this->_partial_path('upload'));
  }
}

?>