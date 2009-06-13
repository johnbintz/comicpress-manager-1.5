<?php

require_once('PHPUnit/Framework.php');
require_once(realpath(dirname(__FILE__) . '/../classes/ComicPressView.php'));
require_once(realpath(dirname(__FILE__) . '/../classes/ComicPressManager.php'));
require_once(realpath(dirname(__FILE__) . '/../classes/views/ComicPressUpload.php'));
require_once(realpath(dirname(__FILE__) . '/../../mockpress/mockpress.php'));

class ComicPressUploadTest extends PHPUnit_Framework_TestCase {
  function setUp() {
    global $comicpress_manager;
    
    _reset_wp();

    $comicpress_manager = $this->getMock('ComicPressManager', array('generate_example_date', 'get_subcomic_directory', 'normalize_storyline_structure'));
    $comicpress_manager->expects($this->once())->method('generate_example_date');
    $comicpress_manager->expects($this->once())->method('get_subcomic_directory')->will($this->returnValue(false));
    $comicpress_manager->expects($this->once())->method('normalize_storyline_structure')->will($this->returnValue(array('category_tree' => array('0/1'))));
    $comicpress_manager->properties = array('comiccat' => 1);
    
    add_category(1, (object)array('name' => 'comic'));
    
    $this->u = new ComicPressUpload();
  }

  function testSubcomicReminder() {
    global $comicpress_manager;
    
    $comicpress_manager = $this->getMock('ComicPressManager', array('get_subcomic_directory'));
    $comicpress_manager->expects($this->once())->method('get_subcomic_directory')->will($this->returnValue("comic"));
    $comicpress_manager->messages = array();
    
    update_option('comicpress-manager-manage-subcomic', 1);
    
    ob_start();
    $this->u->render();
    $result = ob_get_clean();
    
    $this->assertTrue(strpos($comicpress_manager->messages[0], "You are managing the <strong>comic</strong> comic subdirectory") !== false);
  }


  function testBadUpload() {
    global $comicpress_manager;
    
    $comicpress_manager = $this->getMock('ComicPressManager', array('get_subcomic_directory'));
    $comicpress_manager->expects($this->once())->method('get_subcomic_directory')->will($this->returnValue(false));
    $comicpress_manager->warnings = array();

    $_POST = array();
    $_GET['upload'] = 1;

    ob_start();
    $this->u->render();
    $result = ob_get_clean();
    
    $this->assertTrue(strpos($comicpress_manager->warnings[0], "Your uploaded files were larger than") !== false);
  }
  
  function testZipExtensionChanges() {
    global $comicpress_manager;
    
    $comicpress_manager = $this->getMock('ComicPressManager', array('get_subcomic_directory'));
    $comicpress_manager->expects($this->any())->method('get_subcomic_directory')->will($this->returnValue(false));

    $this->u->zip_extension_loaded = true;
    
    ob_start();
    $this->u->render();
    $result = ob_get_clean();
    
    $this->assertTrue(($xml = _to_xml($result)) !== false);
    
    foreach (array(
      '//h2[contains(text(), "Upload Image & Zip Files")]' => true,
      '//h2[contains(text(), "Upload Image Files")]' => false,
      '//input[@id="submit" and @value="Upload Image & Zip Files"]' => true,
      '//div[@id="zip-upload-warning"]' => false
    ) as $xpath => $value) {
      $this->assertTrue(_xpath_test($xml, $xpath, $value), $xpath);
    }

    $this->u->zip_extension_loaded = false;
    
    ob_start();
    $this->u->render();
    $result = ob_get_clean();
    
    $this->assertTrue(($xml = _to_xml($result)) !== false);
    
    foreach (array(
      '//h2[contains(text(), "Upload Image & Zip Files")]' => false,
      '//h2[contains(text(), "Upload Image Files")]' => true,
      '//input[@id="submit" and @value="Upload Image & Zip Files"]' => false,
      '//div[@id="zip-upload-warning"]' => true
    ) as $xpath => $value) {
      $this->assertTrue(_xpath_test($xml, $xpath, $value), $xpath);
    }
  }
  
  function testOverwriteExisting() {
    global $comicpress_manager;
    
    $comicpress_manager = $this->getMock('ComicPressManager', array('get_subcomic_directory'));
    $comicpress_manager->expects($this->any())->method('get_subcomic_directory')->will($this->returnValue(false));

    $comicpress_manager->comic_files = array("/test/test2.gif");

    ob_start();
    $this->u->render();
    $result = ob_get_clean();
    
    $this->assertTrue(($xml = _to_xml($result)) !== false);
    
    foreach (array(
      '//tr[@id="overwrite-existing-holder"]' => true,
      '//option[@value="test2.gif"]' => "test2.gif"
    ) as $xpath => $value) {
      $this->assertTrue(_xpath_test($xml, $xpath, $value), $xpath);
    }
  }
  
  function testObfuscateFilenames() {
    global $comicpress_manager;
    
    $comicpress_manager = $this->getMock('ComicPressManager', array('get_subcomic_directory', 'get_cpm_option'));
    $comicpress_manager->expects($this->any())->method('get_subcomic_directory')->will($this->returnValue(false));
    $comicpress_manager->expects($this->at(1))->method('get_cpm_option')->with('cpm-obfuscate-filenames-on-upload')->will($this->returnValue("test"));

    ob_start();
    $this->u->render();
    $result = ob_get_clean();
    
    $this->assertTrue(($xml = _to_xml($result)) !== false);

    foreach (array(
      '//h3[contains(text(), "uploaded filenames will be obfuscated")]' => true,
    ) as $xpath => $value) {
      $this->assertTrue(_xpath_test($xml, $xpath, $value), $xpath);
    }
  }
}

?>