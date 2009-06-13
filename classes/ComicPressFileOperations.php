<?php

class ComicPressFileOperations {
  function rename($source, $target) { return rename($source, $target); }
  function file_exists($target) { return file_exists($target); }
  function file_get_contents($target) { return file_get_contents($target); }
  function file_write_contents($file, $data) {
    //harmonious file_put_contents
    if (function_exists('file_put_contents')) {
      return file_put_contents($file, $data);
    } else {
      if (($fh = fopen($file, "w")) !== false) {
        fwrite($fh, $data);
        fclose($fh);
      }
    }
  }
  function chmod($file, $mode) { @chmod($file, $mode); }
  function unlink($target) { return @unlink($target); }
  
  function filemtime($file) { return @filemtime($file); }
  function glob($pattern) { return glob($pattern); }
  
  function realpath($path) { return @realpath($path); }
}

?>