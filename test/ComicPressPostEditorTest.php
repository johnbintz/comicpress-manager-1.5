<?php

require_once('PHPUnit/Framework.php');
require_once(realpath(dirname(__FILE__) . '/../classes/ComicPressView.php'));
require_once(realpath(dirname(__FILE__) . '/../classes/views/ComicPressUpload.php'));
require_once(realpath(dirname(__FILE__) . '/../../mockpress/mockpress.php'));

class ComicPressPostEditorTest extends PHPUnit_Framework_TestCase {
  function testNoComicsCategory() {
    global $comicpress_manager;
    
    $comicpress_manager = $this->getMock('ComicPressManager');
    $comicpress_manager->expects($this->never())->method('normalize_storyline_structure')->will($this->returnValue(array('category_tree' => '0/1')));
    
    $a = new ComicPressPostEditor();
    
    $this->assertTrue(empty($a->category_tree));
    $this->assertTrue(empty($a->width));
  }
  
  function testGetPostCategories() {
    global $comicpress_manager;
    
    add_category(1, (object)array('name' => 'comics'));
    add_category(2, (object)array('name' => 'comics2'));
    
    // subdirectory
    update_option('comicpress-manager-manage-subcomic', "2");
    
    $comicpress_manager = $this->getMock('ComicPressManager', array('normalize_storyline_structure', 'get_subcomic_directory'));
    $comicpress_manager->expects($this->once())->method('normalize_storyline_structure')->will($this->returnValue(array('category_tree' => array('0/1'))));
    $comicpress_manager->properties = array('comiccat' => 1);

    $comicpress_manager->expects($this->once())->method('get_subcomic_directory')->will($this->returnValue("comic2"));

    $a = new ComicPressPostEditor();
    
    $this->assertEquals(array(2), $a->post_categories);

    // first in storyline
    update_option('comicpress-manager-manage-subcomic', "0");
    
    $comicpress_manager = $this->getMock('ComicPressManager', array('normalize_storyline_structure', 'get_subcomic_directory', 'get_cpm_option'));
    $comicpress_manager->expects($this->once())->method('normalize_storyline_structure')->will($this->returnValue(array('category_tree' => array('0/1', '0/2'))));
    $comicpress_manager->properties = array('comiccat' => 1);

    $comicpress_manager->expects($this->once())->method('get_subcomic_directory')->will($this->returnValue(false));
    $comicpress_manager->expects($this->once())->method('get_cpm_option')->with('cpm-default-comic-category-is-last-storyline')->will($this->returnValue(0));
  
    $a = new ComicPressPostEditor();
    
    $this->assertEquals(array(1), $a->post_categories);

    // last in storyline
    update_option('comicpress-manager-manage-subcomic', "0");
    
    $comicpress_manager = $this->getMock('ComicPressManager', array('normalize_storyline_structure', 'get_subcomic_directory', 'get_cpm_option'));
    $comicpress_manager->expects($this->once())->method('normalize_storyline_structure')->will($this->returnValue(array('category_tree' => array('0/1', '0/2'))));
    $comicpress_manager->properties = array('comiccat' => 1);

    $comicpress_manager->expects($this->once())->method('get_subcomic_directory')->will($this->returnValue(false));
    $comicpress_manager->expects($this->once())->method('get_cpm_option')->with('cpm-default-comic-category-is-last-storyline')->will($this->returnValue(1));
  
    $a = new ComicPressPostEditor();
    
    $this->assertEquals(array(2), $a->post_categories);
  }
}

?>