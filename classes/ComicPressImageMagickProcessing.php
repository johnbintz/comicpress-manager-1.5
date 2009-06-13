<?php

class ComicPressImageMagickProcessing {
  function get_image_size($path) { return false; }
  
  function convert_to_rgb($source, $target, $quality) {
    exec(implode(" ", array("convert",
                      "\"{$source}\"",
                      "-colorspace rgb",
                      "\"${target}\"")));
    
    return file_exists($target);    
  }
  
  function generate_thumbnails($source, $targets_and_constraints, $target_format, $thumbnail_quality) {
    $files_created_in_operation = array();

    $unique_colors = exec("identify -format '%k' '${$source}'");
    if (empty($unique_colors)) { $unique_colors = 256; }

    $ok = true;
    foreach ($targets_and_constraints as $target_and_constraint) {
      list($target, $constraint) = $target_and_constraint;
    
      $width_to_use = $constraint['width'];

      $command = array("convert",
                       "\"${source}\"",
                       "-filter Lanczos",
                       "-resize " . $width_to_use . "x");

      $im_target = $target;

      switch(strtolower($target_format)) {
        case "jpg":
        case "jpeg":
          $command[] = "-quality " . $thumbnail_quality;
          break;
        case "gif":
          $command[] = "-colors ${unique_colors}";
          break;
        case "png":
          if ($unique_colors <= 256) {
            $im_target = "png8:${im_target}";
            $command[] = "-colors ${unique_colors}";
          }
          $command[] = "-quality 100";
          break;
        default:
      }

      $command[] = "\"${im_target}\"";

      $convert_to_thumb = escapeshellcmd(implode(" ", $command));

      exec($convert_to_thumb);

      if (!file_exists($target)) {
        $ok = false;
      } else {
        @chmod($target, CPM_FILE_UPLOAD_CHMOD);
        $files_created_in_operation[] = $target;
      }
    }

    return ($ok) ? $files_created_in_operation :false;
  }
}

?>