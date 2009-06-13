<?php

class ComicPressGDProcessing {
  function get_image_size($path) { return getimagesize($path); }

  function convert_to_rgb($source, $target, $quality) {
    $cmyk_data = imagecreatefromjpeg($source);
    imagejpeg($cmyk_data, $target, $quality);
    imagedestroy($cmyk_data);
    
    return file_exists($target);
  }
  
  function generate_thumbnails($source, $targets_and_constraints, $target_format, $thumbnail_quality) {
    $files_created_in_operation = array();
    
    list ($width, $height) = getimagesize($source);

    $ok = false;

    if ($width > 0) {
      $pixel_size_buffer = 1.25;

      $max_bytes = $comicpress_manager->convert_short_size_string_to_bytes(ini_get('memory_limit'));
      if ($max_bytes > 0) {
        $max_thumb_size = 0;

        foreach ($targets_and_constraints as $target_and_constraint) {
          list($target, $constraint) = $target_and_constraint;
        
          $width_to_use = $constraint['width'];
          $archive_comic_height = (int)(($width_to_use * $height) / $width);

          $max_thumb_size = max($width_to_use * $archive_comic_height * 4, $max_thumb_size);
        }

        $input_image_size = $width * $height * 4;
        if (strtolower(pathinfo($input, PATHINFO_EXTENSION)) == "gif") { $input_image_size *= 2; }

        $recommended_size = ($input_image_size + $max_thumb_size) * $pixel_size_buffer;
        if (function_exists('memory_get_usage')) { $recommended_size += memory_get_usage(); }

        if ($recommended_size > $max_bytes) {
          $comicpress_manager->warnings[] = sprintf(__("<strong>You don't have enough memory available to PHP and GD to process this file.</strong> You should <strong>set your PHP <tt>memory_size</tt></strong> to at least <strong><tt>%sM</tt></strong> and try again. For more information, read the ComicPress Manager FAQ.", 'comicpress-manager'), (int)($recommended_size / (1024 * 1024)));

          return false;
        }
      }

      foreach ($targets_and_constraints as $target_and_constraint) {
        list($target, $constraint) = $target_and_constraint;

        $width_to_use = $constraint['width'];
        $archive_comic_height = (int)(($width_to_use * $height) / $width);

        $pathinfo = pathinfo($source);

        $thumb_image = imagecreatetruecolor($width_to_use, $archive_comic_height);
        imagealphablending($thumb_image, true);
        switch(strtolower($pathinfo['extension'])) {
          case "jpg":
          case "jpeg":
            $comic_image = imagecreatefromjpeg($source);
            break;
          case "gif":
            $is_gif = true;

            $temp_comic_image = imagecreatefromgif($source);

            list($width, $height) = getimagesize($input);
            $comic_image = imagecreatetruecolor($width, $height);

            imagecopy($comic_image, $temp_comic_image, 0, 0, 0, 0, $width, $height);
            imagedestroy($temp_comic_image);
            break;
          case "png":
            $comic_image = imagecreatefrompng($source);
            break;
          default:
            return false;
        }
        imagealphablending($comic_image, true);

        if ($is_palette = !imageistruecolor($comic_image)) {
          $number_of_colors = imagecolorstotal($comic_image);
        }

        imagecopyresampled($thumb_image, $comic_image, 0, 0, 0, 0, $width_to_use, $archive_comic_height, $width, $height);

        $ok = true;

        @touch($target);
        if (file_exists($target)) {
          @unlink($target);
          switch(strtolower($target_format)) {
            case "jpg":
            case "jpeg":
              if (imagetypes() & IMG_JPG) {
                @imagejpeg($thumb_image, $target, $comicpress_manager->get_cpm_option("cpm-thumbnail-quality"));
              } else {
                return false;
              }
              break;
            case "gif":
              if (imagetypes() & IMG_GIF) {
                if (function_exists('imagecolormatch')) {
                  $temp_comic_image = imagecreate($width_to_use, $archive_comic_height);
                  imagecopymerge($temp_comic_image, $thumb_image, 0, 0, 0, 0, $width_to_use, $archive_comic_height, 100);
                  imagecolormatch($thumb_image, $temp_comic_image);

                  @imagegif($temp_comic_image, $target);
                  imagedestroy($temp_comic_image);
                } else {
                  @imagegif($thumb_image, $target);
                }
              } else {
                return false;
              }
              break;
            case "png":
              if (imagetypes() & IMG_PNG) {
                if ($is_palette) {
                  imagetruecolortopalette($thumb_image, true, $number_of_colors);
                }
                @imagepng($thumb_image, $target, 9);
              } else {
                return false;
              }
              break;
            default:
              return false;
          }
        }

        if (!file_exists($target)) {
          $ok = false;
        } else {
          @chmod($target, CPM_FILE_UPLOAD_CHMOD);
          $files_created_in_operation[] = $target;
        }

        imagedestroy($comic_image);
        imagedestroy($thumb_image);
      }
    }

    return ($ok) ? $files_created_in_operation :false;
  }
}

?>