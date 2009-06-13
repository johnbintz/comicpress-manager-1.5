<?php

require_once('PHPUnit/Framework.php');
require_once(realpath(dirname(__FILE__) . '/../classes/ComicPressView.php'));
require_once(realpath(dirname(__FILE__) . '/../../mockpress/mockpress.php'));

class ComicPressViewTest extends PHPUnit_Framework_TestCase {
  function setUp() {
    $this->v = new ComicPressView();
  }

  function testStorylineSetup() {
    global $comicpress_manager;
    
    $comicpress_manager = $this->getMock("ComicPressManager", array('normalize_storyline_structure'));
    $comicpress_manager->expects($this->once())->method('normalize_storyline_structure')->will($this->returnValue(array('category_tree' => array('0/1'))));
    
    $this->v->_storyline_setup();
    $this->assertType('array', $this->v->post_categories);
    $this->assertEquals(array(1), $this->v->comic_categories);
  }
  
  function testPartialPath() {
    $this->assertTrue(empty($this->v->_partial_path));
    $this->assertTrue(preg_match('#/views/ComicPressView/test.inc$#', $this->v->_partial_path("test")) > 0);
    $this->assertFalse(empty($this->v->_partial_path));
  }

  function testDisplayStorylineCheckboxes() {
    $this->v->category_tree = array("0/1","0/1/3","0/2");
    $this->v->post_categories = array(1);
    
    ob_start();
    $this->v->_display_storyline_checkboxes();
    $this->assertTrue(($xml = _to_xml(ob_get_clean())) !== false);
    
    foreach (array(
      '//div[3]' => true,
      '//div[4]' => false,
      '//div[1][contains(@style, "margin-left: 0px")]' => true,
      '//div[2][contains(@style, "margin-left: 20px")]' => true,
      '//div[1]//input[@name="in-comic-category[]" and @value="1" and @checked="checked"]' => true,
      '//div[2]//input[@name="in-comic-category[]" and @value="3" and not(@checked="checked")]' => true,
      '//div[3]//input[@name="in-comic-category[]" and @value="2" and not(@checked="checked")]' => true
    ) as $node => $action) {
      if ($action === true) {
        $this->assertTrue(_node_exists($xml, $node), $node);
      }
      if ($action === false) {
        $this->assertFalse(_node_exists($xml, $node), $node);
      }
    }
  }
  
  function testGenerateAdditionalCategoriesCheckboxes() {
    global $comicpress_manager;
    
    $comicpress_manager = $this->getMock("ComicPressManager", array('get_cpm_option'));
    $comicpress_manager->properties['blogcat'] = 1;
    $this->v->category_tree = array('0/2');
    $comicpress_manager->expects($this->once())->method('get_cpm_option')->will($this->returnValue("4"));
    
    add_category(3, (object)array('cat_name' => 'Test', 'parent' => 0));
    add_category(4, (object)array('cat_name' => 'Test 2', 'parent' => 0));
    
    $result = $this->v->_generate_additional_categories_checkboxes();
    $this->assertTrue(count($result) == 2);
    $this->assertTrue(count($this->v->category_checkboxes) == 2);
    
    $this->assertTrue(($first = _to_xml($result[0])) !== false);
    $this->assertTrue(_node_exists($first, '//label/input[@value="3" and not(@checked="checked")]'));

    $this->assertTrue(($second = _to_xml($result[1])) !== false);
    $this->assertTrue(_node_exists($second, '//label/input[@value="4" and @checked="checked"]'));
  }
}

?>