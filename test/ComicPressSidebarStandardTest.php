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
		$this->assertTrue($v->all_comic_dates_ok);
		
		// one comic
		$comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename'));
		$comicpress_manager->comic_files = array("test");
		$comicpress_manager->expects($this->once())->method('breakdown_comic_filename')->will($this->returnValue(array('date' => "test")));
		
		$v = new ComicPressSidebarStandard();
		$v->_all_comic_dates_ok();
		$this->assertTrue($v->all_comic_dates_ok);
		
		// two comics	
		
		$comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename'));
		$comicpress_manager->comic_files = array("test", "test2");
		$comicpress_manager->expects($this->exactly(2))->method('breakdown_comic_filename')->will($this->returnValue(array('date' => "test")));
		
		$v = new ComicPressSidebarStandard();
		$v->_all_comic_dates_ok();
		$this->assertFalse($v->all_comic_dates_ok);
	}
	
	function providerTestThumbnailGenerationInfo() {
		return array(
			array(
				'option-archive' => 1, 'option-rss' => 1,
				'scale_method' => true,
				'separate_thumbs_folder_defined' => array('rss' => true, 'archive' => true),
				'thumbs_folder_writable' => array('rss' => true, 'archive' => true),
				'result' => array('rss' => true, 'archive' => true)
			),
			array(
				'option-archive' => 1, 'option-rss' => 1,
				'scale_method' => false,
				'separate_thumbs_folder_defined' => array('rss' => true, 'archive' => true),
				'thumbs_folder_writable' => array('rss' => true, 'archive' => true),
				'result' => array('rss' => array("No scaling software"), 'archive' => array("No scaling software"))
			),
			array(
				'option-archive' => 0, 'option-rss' => 1,
				'scale_method' => true,
				'separate_thumbs_folder_defined' => array('rss' => true, 'archive' => true),
				'thumbs_folder_writable' => array('rss' => true, 'archive' => true),
				'result' => array('rss' => true, 'archive' => array("Generation disabled"))
			),
			array(
				'option-archive' => 1, 'option-rss' => 1,
				'scale_method' => true,
				'separate_thumbs_folder_defined' => array('rss' => false, 'archive' => true),
				'thumbs_folder_writable' => array('rss' => true, 'archive' => true),
				'result' => array('rss' => array("Same as comics folder"), 'archive' => true)
			),
			array(
				'option-archive' => 1, 'option-rss' => 1,
				'scale_method' => true,
				'separate_thumbs_folder_defined' => array('rss' => true, 'archive' => true),
				'thumbs_folder_writable' => array('rss' => true, 'archive' => false),
				'result' => array('rss' => true, 'archive' => array("Not writable"))
			),
		);
	}
	
	/**
	 * @dataProvider providerTestThumbnailGenerationInfo
	 */
	function testThumbnailGenerationInfo($info) {
		global $comicpress_manager;
		
		$comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename', 'get_subcomic_directory', 'get_cpm_option'));

		$comicpress_manager->expects($this->at(1))
											 ->method('get_cpm_option')
											 ->with('cpm-archive-generate-thumbnails')
											 ->will($this->returnValue($info['option-archive']));
		$comicpress_manager->expects($this->at(2))
											 ->method('get_cpm_option')
											 ->with('cpm-rss-generate-thumbnails')
											 ->will($this->returnValue($info['option-archive']));
	  foreach (array('scale_method', 'separate_thumbs_folder_defined', 'thumbs_folder_writable') as $field) {
			$comicpress_manager->{$field} = $info['field'];
		}
		$comicpress_manager->comic_files = array();
	
		$s = new ComicPressSidebarStandard();
		$this->assertEquals($info['result'], $s->_get_thumbnail_generation_info());
	}
}

?>