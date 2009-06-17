<?php
 
require_once('PHPUnit/Framework.php');
require_once(realpath(dirname(__FILE__) . '/../classes/ComicPressView.php'));
require_once(realpath(dirname(__FILE__) . '/../classes/views/ComicPressSidebarStandard.php'));
require_once(realpath(dirname(__FILE__) . '/../../mockpress/mockpress.php'));
 
class ComicPressSidebarStandardTest extends PHPUnit_Framework_TestCase {
	function testAllComicDatesOK() {
		global $comicpress_manager;
		
		// no comics
		$comicpress_manager = $this->getMock('ComicPressManager');
		$comicpress_manager->comic_files = array();

		$v = new ComicPressSidebarStandard();
		$v->_all_comic_dates_ok();
		$this->assertTrue(empty($v->too_many_comics_message));
		
		// one comic
		$comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename'));
		$comicpress_manager->comic_files = array("test");
		$comicpress_manager->expects($this->once())->method('breakdown_comic_filename')->will($this->returnValue(array('date' => "test")));
		
		$v = new ComicPressSidebarStandard();
		$v->_all_comic_dates_ok();
		$this->assertTrue(empty($v->too_many_comics_message));
		
		// two comics	
		
		$comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename'));
		$comicpress_manager->comic_files = array("test", "test2");
		$comicpress_manager->expects($this->exactly(2))->method('breakdown_comic_filename')->will($this->returnValue(array('date' => "test")));
		
		$v = new ComicPressSidebarStandard();
		$v->_all_comic_dates_ok();
		$this->assertTrue(!empty($v->too_many_comics_message));
	}
	
	function providerTestThumbnailGenerationInfo() {
		return array(
			array(
				array(
					'option-archive' => 1, 'option-rss' => 1,
					'scale_method' => true,
					'separate_thumbs_folder_defined' => array('rss' => true, 'archive' => true),
					'thumbs_folder_writable' => array('rss' => true, 'archive' => true),
					'result' => array('rss' => true, 'archive' => true)
				),
		  ),
			array(
				array(
					'option-archive' => 1, 'option-rss' => 1,
					'scale_method' => false,
					'separate_thumbs_folder_defined' => array('rss' => true, 'archive' => true),
					'thumbs_folder_writable' => array('rss' => true, 'archive' => true),
					'result' => array('rss' => array("No scaling software"), 'archive' => array("No scaling software"))
				),
		  ),
			array(
				array(
					'option-archive' => 0, 'option-rss' => 1,
					'scale_method' => true,
					'separate_thumbs_folder_defined' => array('rss' => true, 'archive' => true),
					'thumbs_folder_writable' => array('rss' => true, 'archive' => true),
					'result' => array('rss' => true, 'archive' => array("Generation disabled"))
				),
			),
			array(
				array(
					'option-archive' => 1, 'option-rss' => 1,
					'scale_method' => true,
					'separate_thumbs_folder_defined' => array('rss' => false, 'archive' => true),
					'thumbs_folder_writable' => array('rss' => true, 'archive' => true),
					'result' => array('rss' => array("Same as comics folder"), 'archive' => true)
				),
			),
			array(
				array(
					'option-archive' => 1, 'option-rss' => 1,
					'scale_method' => true,
					'separate_thumbs_folder_defined' => array('rss' => true, 'archive' => true),
					'thumbs_folder_writable' => array('rss' => true, 'archive' => false),
					'result' => array('rss' => true, 'archive' => array("Not writable"))
				),
			)
		);
	}
	
	/**
	 * @dataProvider providerTestThumbnailGenerationInfo
	 */
	function testThumbnailGenerationInfo($info) {
		global $comicpress_manager;
		
		$comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename', 'get_subcomic_directory', 'get_cpm_option'));

		$comicpress_manager->expects($this->at(0))
											 ->method('get_cpm_option')
											 ->with('cpm-archive-generate-thumbnails')
											 ->will($this->returnValue($info['option-archive']));
		$comicpress_manager->expects($this->at(1))
											 ->method('get_cpm_option')
											 ->with('cpm-rss-generate-thumbnails')
											 ->will($this->returnValue($info['option-rss']));
	  foreach (array('scale_method', 'separate_thumbs_folder_defined', 'thumbs_folder_writable') as $field) {
			$comicpress_manager->{$field} = $info[$field];
		}
	
		$s = new ComicPressSidebarStandard();
		$result = $s->_get_thumbnail_generation_info();
		$this->assertTrue(!empty($result));
		$this->assertEquals($info['result'], $result);
	}
	
	function testRender() {
		global $comicpress_manager;

		// 
	}
}

?>