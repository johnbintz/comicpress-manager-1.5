<?php

require_once('PHPUnit/Framework.php');
require_once(realpath(dirname(__FILE__) . '/../classes/ComicPressView.php'));
require_once(realpath(dirname(__FILE__) . '/../classes/views/ComicPressQuomicPressWidget.php'));
require_once(realpath(dirname(__FILE__) . '/../../mockpress/mockpress.php'));

class ComicPressQuomicPressWidgetTest extends PHPUnit_Framework_TestCase {
  function testWidgetErrors() {
    global $comicpress_manager, $comicpress_manager_admin;
    
    $comicpress_manager = $this->getMock('ComicPressManager');
    $comicpress_manager->expects($this->once())->method('get_subcomic_directory')->will($this->returnValue(false));
    $comicpress_manager->errors = array('test');
    $w = new ComicPressQuomicPressWidget();
    
    $this->assertFalse($w->show_widget);
    $this->assertTrue(empty($w->thumbnails_to_generate));
    
    $comicpress_manager_admin = new ComicPressManagerAdmin();
    
    ob_start();
    $w->render();
    $this->assertTrue(($xml = _to_xml(ob_get_clean())) !== false);
  }
  
  function testChooseRightCategory() {
    global $comicpress_manager;
    
    // subdir
    $comicpress_manager = $this->getMock('ComicPressManager', array('get_subcomic_directory', 'get_thumbnails_to_generate', 'get_cpm_option'));
    $comicpress_manager->expects($this->any())->method('get_subcomic_directory')->will($this->returnValue("test"));
    $comicpress_manager->errors = array();
    update_option("comicpress-manager-manage-subcomic", 1);
    
    $v = $this->getMock('ComicPressQuomicPressWidget', array('_storyline_setup'));
    $this->assertEquals(1, $v->category_to_use);

    // last in storyline
    $comicpress_manager = $this->getMock('ComicPressManager', array('get_subcomic_directory', 'get_thumbnails_to_generate', 'get_cpm_option', 'normalize_storyline_structure'));
    $comicpress_manager->errors = array();
    $comicpress_manager->expects($this->any())->method('get_subcomic_directory')->will($this->returnValue(false));
    $comicpress_manager->expects($this->at(6))->method('get_cpm_option')->with('cpm-default-comic-category-is-last-storyline')->will($this->returnValue(1));
    $comicpress_manager->expects($this->any())->method('normalize_storyline_structure')->will($this->returnValue(array('category_tree' => array('0/3', '0/2'))));
    update_option("comicpress-manager-manage-subcomic", 0);
    $v = new ComicPressQuomicPressWidget();
    $this->assertEquals(2, $v->category_to_use);
    
    // comiccat
    $comicpress_manager = $this->getMock('ComicPressManager', array('get_subcomic_directory', 'get_thumbnails_to_generate', 'get_cpm_option', 'normalize_storyline_structure'));
    $comicpress_manager->errors = array();
    $comicpress_manager->expects($this->any())->method('get_subcomic_directory')->will($this->returnValue(false));
    $comicpress_manager->expects($this->at(6))->method('get_cpm_option')->with('cpm-default-comic-category-is-last-storyline')->will($this->returnValue(0));
    $comicpress_manager->expects($this->any())->method('normalize_storyline_structure')->will($this->returnValue(array('category_tree' => array('0/3', '0/2'))));
    update_option("comicpress-manager-manage-subcomic", 0);
    $v = new ComicPressQuomicPressWidget();
    $this->assertEquals(3, $v->category_to_use);
  }
}