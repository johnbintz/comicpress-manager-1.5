<?php

require_once('PHPUnit/Framework.php');
require_once(realpath(dirname(__FILE__) . '/../classes/ComicPressView.php'));
require_once(realpath(dirname(__FILE__) . '/../classes/views/ComicPressEditPostShowComic.php'));
require_once(realpath(dirname(__FILE__) . '/../../mockpress/mockpress.php'));

class ComicPressEditPostShowComicTest extends PHPUnit_Framework_TestCase {
  function testPostIDZero() {
    global $comicpress_manager, $comicpress_manager_admin, $post;
    
    $comicpress_manager = $this->getMock('ComicPressManager', array('read_comicpress_config', 'get_thumbnails_to_generate', 'normalize_storyline_structure'));
    $comicpress_manager->expects($this->any())->method('normalize_storyline_structure')->will($this->returnValue(array('category_tree' => array('0/1'))));
    
    $post = (object)array('ID' => 0);
    $v = new ComicPressEditPostShowComic();
    
    $this->assertEquals(0, count($v->post_categories));
    $this->assertfalse($v->has_comic_file);
    $this->assertfalse($v->in_comics_category);
  }

  function testPostIDNotZero() {
    global $comicpress_manager, $comicpress_manager_admin, $post;
    
    $comicpress_manager = $this->getMock('ComicPressManager', array('read_comicpress_config', 'get_thumbnails_to_generate', 'normalize_storyline_structure'));
    $comicpress_manager->expects($this->any())->method('normalize_storyline_structure')->will($this->returnValue(array('category_tree' => array('0/1'))));
    
    $post = (object)array('ID' => 0);
    $v = new ComicPressEditPostShowComic();
    
    $this->assertEquals(0, count($v->post_categories));
    $this->assertfalse($v->has_comic_file);
    $this->assertfalse($v->in_comics_category);
  }
}

?>