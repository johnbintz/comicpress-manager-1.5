<?php

require_once('PHPUnit/Framework.php');
require_once(realpath(dirname(__FILE__) . '/../classes/ComicPressManager.php'));
require_once(realpath(dirname(__FILE__) . '/../../mockpress/mockpress.php'));

define("CPM_TEST_DEFAULT_URL", "http://test");
define("CPM_TEST_DOCUMENT_ROOT", "/var/www/mysite/htdocs");
define("CPM_TEST_FILENAME_PATTERN", "Y-m-d");
define("CPM_WORDPRESS_DATE_FORMAT", 'Y-m-d H:i:s');
define("CPM_DATE_FORMAT", CPM_TEST_FILENAME_PATTERN);

class ComicPressManagerTest extends PHPUnit_Framework_TestCase {
  public function setUp() {
    global $comicpress_manager;
		_reset_wp();
    
    $this->cpm = new ComicPressManager();
    $comicpress_manager = $this->cpm;
  }

  public function testOptions() {
    $test_key = md5(rand());
    $this->assertEquals(CPM_OPTION_PREFIX . '-' . $test_key, $this->cpm->get_cpm_option_key($test_key));
    
    $this->cpm->set_cpm_option($test_key, $test_key);
    $this->assertEquals($test_key, get_option($this->cpm->get_cpm_option_key($test_key)));
    $this->assertEquals($test_key, $this->cpm->get_cpm_option($test_key));
    
    $new_value = md5(rand());
    $this->cpm->set_cpm_option($test_key, $new_value);
    $this->assertEquals($new_value, get_option($this->cpm->get_cpm_option_key($test_key)));
    $this->assertEquals($new_value, $this->cpm->get_cpm_option($test_key));
    $this->assertNotEquals($test_key, get_option($this->cpm->get_cpm_option_key($test_key)));
    $this->assertNotEquals($test_key, $this->cpm->get_cpm_option($test_key));
    
    $this->assertTrue(delete_option($this->cpm->get_cpm_option_key($test_key)));
    
    $this->assertFalse(get_option($this->cpm->get_cpm_option_key($test_key)));
    $this->assertFalse($this->cpm->get_cpm_option($test_key));
  }
  
  public function providerTestDocumentRoots() {
    return array(
      array('', array(), false),
      array('http:///', array(), false),
      array('http://test/', array(), false),
      array('http://test/', array(
        'SCRIPT_FILENAME' => '/index.php',
        'SCRIPT_NAME' => '/index.php'
      ), ''),
      array('http://test/', array(
        'SCRIPT_FILENAME' => '/index.php',
        'SCRIPT_URL' => '/index.php'
      ), ''),
      array('http://test/', array(
        'SCRIPT_FILENAME' => '/vhost/index.php',
        'SCRIPT_NAME' => '/index.php'
      ), '/vhost'),
      array('http://test/', array(
        'SCRIPT_FILENAME' => '/vhost/index.php',
        'SCRIPT_URL' => '/index.php'
      ), '/vhost'),
      array('http://test/', array(
        'SCRIPT_FILENAME' => '/vhost/index.php',
        'SCRIPT_URL' => '/index.php'
      ), '/vhost'),
      array('http://test/', array(
        'DOCUMENT_ROOT' => '/vhost'
      ), '/vhost'),
      array('http://test/test2/', array(
        'SCRIPT_FILENAME' => '/index.php',
        'SCRIPT_URL' => '/index.php'
      ), ''),
      array('http://test/test2/', array(
        'SCRIPT_FILENAME' => '/index.php',
        'SCRIPT_NAME' => '/index.php'
      ), ''),
      array('http://test/test2/', array(
        'DOCUMENT_ROOT' => '/',
      ), '/test2')
    );
  }
  
  /**
   * @dataProvider providerTestDocumentRoots
   */
  public function testDocumentRoots($home_url, $server_options, $expected_result) {
    update_option('home', $home_url);
    $this->cpm->_f = $this->getMock('ComicPressFileOperations', array('file_exists'));
    $this->cpm->_f->expects($this->any())->method('file_exists')->will($this->returnValue(true));
    $this->assertSame($expected_result, $this->cpm->calculate_document_root($server_options));
  }

  public function providerTestGenerateExampleDate() {
    return array(
      array("Y", "YYYY"),
      array("m", "MM"),
      array("d", "DD"),
      array("123", "123"),
      array('\Y', "Y")
    )  ;
  }
  
  /**
   * @dataProvider providerTestGenerateExampleDate
   */
  function testGenerateExampleDate($source, $result) {
    $this->assertEquals($result, $this->cpm->generate_example_date($source));
  }

  public function providerTestBuildComicURI() {
    return array(
      array("test.gif", null, false),
      array("/test.gif", null, false),
      array("/mycomic/test.gif", null, false),
      array(CPM_TEST_DOCUMENT_ROOT . "/mycomic/test.gif", false, '/mycomic/test.gif'),
      array(CPM_TEST_DOCUMENT_ROOT . "/my_website/mycomic/test.gif", false, '/mycomic/test.gif'),
      array(CPM_TEST_DOCUMENT_ROOT . '\mycomic\test.gif', false, '/mycomic/test.gif'),
      array(CPM_TEST_DOCUMENT_ROOT . "/mycomic/test/test.gif", false, '/test/test.gif'),
      array(CPM_TEST_DOCUMENT_ROOT . "/my_website/mycomic/test/test.gif", false, '/test/test.gif'),
      array(CPM_TEST_DOCUMENT_ROOT . '\mycomic\test\test.gif', false, '/test/test.gif'),
      array(CPM_TEST_DOCUMENT_ROOT . "/mycomic/test/test.gif", 2, '/mycomic/test/test.gif'),
      array(CPM_TEST_DOCUMENT_ROOT . "/my_website/mycomic/test/test.gif", 2, '/mycomic/test/test.gif'),
      array(CPM_TEST_DOCUMENT_ROOT . '\mycomic\test\test.gif', 2, '/mycomic/test/test.gif'),
      array(CPM_TEST_DOCUMENT_ROOT . "/mycomic/test.gif", 2, false),
      array(CPM_TEST_DOCUMENT_ROOT . "/my_website/mycomic/test.gif", 2, false),
      array(CPM_TEST_DOCUMENT_ROOT . '\mycomic\test.gif', 2, false),
    );
  }

  /**
   * @dataProvider providerTestBuildComicURI
   */
  function testBuildComicURI($example_filename, $subdir_category, $expected_url) {
    update_option('home', '');
    update_option('comicpress-manager-manage-subcomic', $subdir_category);
    add_category(2, (object)array('slug' => 'test'));
    $this->cpm->properties['comiccat'] = 1;
    $this->assertEquals($expected_url, $this->cpm->build_comic_uri($example_filename, CPM_TEST_DOCUMENT_ROOT));
  }
  
  function providerTestBreakdownComicFilename() {
    $test_date = "2008-01-01";
    
    return array(
      array("meow", false),
      array("meow.xfm", false),
      array(md5(rand()), false),
      array("${test_date}.jpg", array("title" => "", "date" => $test_date, "converted_title" => "")),
      array("comics/${test_date}.jpg", false),
      array("${test_date}-Test.jpg", array("title" => "-Test", "date" => $test_date, "converted_title" => "Test")),
      array("${test_date}-test.jpg", array("title" => "-test", "date" => $test_date, "converted_title" => "Test")),
      array("1900-01-01.jpg", false),
    );
  }
  
  /**
   * @dataProvider providerTestBreakdownComicFilename
   */
  function testBreakdownComicFilename($filename, $expected_value) {
    $this->assertEquals($expected_value, $this->cpm->breakdown_comic_filename($filename, true, CPM_TEST_FILENAME_PATTERN));
  }

  function providerTestTransformDateString() {
    $valid_string = "Y-m-d";
    $valid_transform = array("Y" => "YYYY", "m" => "MM", "d" => "DD");
    
    return array(
      array($valid_string, null, false),
      array(null, $valid_transform, false),
      array($valid_string, array("Y"), false),
      array($valid_string, array("Y" => "YYYY"), false),
      array($valid_string, $valid_transform, "YYYY-MM-DD"),
      array('\\Y', $valid_transform, "Y"),
    );
  }

  /**
   * @dataProvider providerTestTransformDateString
   */
  function testTransformDateString($string, $replacements, $result) {
      $transform_result = $this->cpm->transform_date_string($string, $replacements);
      if (empty($transform_result)) {
        $this->assertTrue($transform_result === false);
      } else {
        $this->assertEquals($result, $transform_result);
      }
    }

  function testGetAllComicCategories() {
    add_category(1, (object)array('parent' => 0));
    $this->cpm->properties['comiccat'] = 1;
    
    $this->cpm->get_all_comic_categories();
    $this->assertEquals(array("0/1"), $this->cpm->category_tree);

    add_category(2, (object)array('parent' => 1));
    $this->cpm->get_all_comic_categories();
    $this->assertEquals(array("0/1", "0/1/2"), $this->cpm->category_tree);

    add_category(3, (object)array('parent' => 0));
    $this->cpm->get_all_comic_categories();
    $this->assertEquals(array("0/1", "0/1/2"), $this->cpm->category_tree);
  }
  
  function providerTestGeneratePostHash() {
    $test_date = "2009-01-01";
    $test_time = "12:34:56";
    $test_title = "test title";

    $test_date_time = strtotime($test_date);

    $test_date_string = date(CPM_WORDPRESS_DATE_FORMAT, $test_date_time);
    $test_time_string = date(CPM_WORDPRESS_DATE_FORMAT, strtotime($test_date . " " . $test_time));

    return array(
      // all empty
      array(null, null, null, false),
      // only test date
      array($test_date, null, null, false),
      // test date and title
      array($test_date, "", null, false),
      // test date, title, bad comic category
      array($test_date, "", array(
        'in-comic-category' => "1"
      ), false),
      // test date, title, bad additional category
      array($test_date, "", array(
        'additional-categories' => "1"
      ), false),
      // missing comic category
      array($test_date, "", array(
        'in-comic-category' => array("2")
      ), false),
      // missing additional category
      array($test_date, "", array(
        'additional-categories' => array("2")
      ), false),
      // comic category, nothing else
      array($test_date, "", array(
        'in-comic-category' => array("1")
      ), array(
        'post_title' => '01/01/2009',
        'post_date' => $test_date_string,
        'post_category' => array(1)
      )),
      // additional category, nothing else
      array($test_date, "", array(
        'additional-categories' => array("1")
      ), array(
        'post_title' => '01/01/2009',
        'post_date' => $test_date_string,
        'post_category' => array(1)
      )),
      // add time but blank
      array($test_date, "", array(
        'in-comic-category' => array("1"),
        'time' => ""
      ), array(
        'post_title' => '01/01/2009',
        'post_date' => $test_date_string,
        'post_category' => array(1)
      )),
      // add time
      array($test_date, "", array(
        'in-comic-category' => array("1"),
        'time' => $test_time
      ), array(
        'post_title' => '01/01/2009',
        'post_date' => $test_time_string,
        'post_category' => array(1)
      )),
      // add invalid time
      array($test_date, "", array(
        'in-comic-category' => array("1"),
        'time' => md5(rand())
      ), false),
      // provide title
      array($test_date, $test_title, array(
        'in-comic-category' => array("1"),
      ), array(
        'post_title' => $test_title,
        'post_date' => $test_date_string,
        'post_category' => array(1)
      )),
      // post content tests
      array($test_date, $test_title, array(
        'in-comic-category' => array("1"),
        'content' => 'test'
      ), array(
        'post_title' => $test_title,
        'post_date' => $test_date_string,
        'post_category' => array(1),
        'post_content' => 'test'
      )),
      array($test_date, $test_title, array(
        'in-comic-category' => array("1"),
        'content' => '{date}'
      ), array(
        'post_title' => $test_title,
        'post_date' => $test_date_string,
        'post_category' => array(1),
        'post_content' => date("F j, Y", $test_date_time)
      )),
      array($test_date, $test_title, array(
        'in-comic-category' => array("1"),
        'content' => '{title}'
      ), array(
        'post_title' => $test_title,
        'post_date' => $test_date_string,
        'post_category' => array(1),
        'post_content' => $test_title
      )),
      array($test_date, $test_title, array(
        'in-comic-category' => array("1"),
        'content' => '{category}'
      ), array(
        'post_title' => $test_title,
        'post_date' => $test_date_string,
        'post_category' => array(1),
        'post_content' => "test"
      )),
      // tags
      array($test_date, $test_title, array(
        'in-comic-category' => array("1"),
        'tags' => "tag"
      ), array(
        'post_title' => $test_title,
        'post_date' => $test_date_string,
        'post_category' => array(1),
        'tags_input' => 'tag'
      )),
      // override title
      array($test_date, $test_title, array(
        'in-comic-category' => array("1"),
        'override-title-to-use' => 'test'
      ), array(
        'post_title' => 'test',
        'post_date' => $test_date_string,
        'post_category' => array(1)
      )),
      // override title with content
      array($test_date, $test_title, array(
        'in-comic-category' => array("1"),
        'override-title-to-use' => 'test',
        'content' => '{title}'
      ), array(
        'post_title' => 'test',
        'post_date' => $test_date_string,
        'post_category' => array(1),
        'post_content' => "test"        
      )),
      // comic + additional categories in content
      array($test_date, $test_title, array(
        'in-comic-category' => array("1"),
        'additional-categories' => array("3"),
        'override-title-to-use' => 'test',
        'content' => '{category}'
      ), array(
        'post_title' => 'test',
        'post_date' => $test_date_string,
        'post_category' => array(1, 3),
        'post_content' => "test, meow"
      )),
      // post_status
      array(date('Y-m-d', time() + 86400), $test_title, array(
        'in-comic-category' => array("1"),
      ), array(
        'post_status' => 'draft'
      )),      
      array(date('Y-m-d', time() + 86400), $test_title, array(
        'in-comic-category' => array("1"),
        'publish' => 'true'
      ), array(
        'post_status' => 'future',
      )),      
      array($test_date, $test_title, array(
        'in-comic-category' => array("1"),
      ), array(
        'post_status' => 'draft',
      )),      
      array($test_date, $test_title, array(
        'in-comic-category' => array("1"),
        'publish' => 'true'
      ), array(
        'post_status' => 'publish',
      )),      
    );
  }
  
  /**
   * @dataProvider providerTestGeneratePostHash
   */
  function testGeneratePostHash($filename_date, $filename_converted_title, $override_post, $expected_result) {
    add_category(1, (object)array('name' => 'test'));
    add_category(3, (object)array('name' => 'meow'));
    
    if (!is_array($expected_result)) {
      $this->assertEquals($expected_result, $this->cpm->generate_post_hash($filename_date, $filename_converted_title, $override_post));
    } else {
      $result = $this->cpm->generate_post_hash($filename_date, $filename_converted_title, $override_post);
      foreach ($expected_result as $key => $value) {
        $this->assertEquals($value, $result[$key]);
      }
    }
  }
  
  function providerTestNormalizeStorylineStructure() {
    return array(
      array(null, 
            array("1" => "0"),
            "0/1"),
      array(null, 
            array(
              "1" => "0",
              "2" => "0"
            ),
            "0/1"),
      array(null, 
            array(
              "1" => "0",
              "2" => "1",
              "3" => "1"
            ),
            "0/1,0/1/2,0/1/3"),
      array("0/1,0/1/3,0/1/2", 
            array(
              "1" => "0",
              "2" => "1",
              "3" => "1"
            ),
            "0/1,0/1/3,0/1/2"),
      array("0/1,0/1/3,0/1/2", 
            array(
              "1" => "0",
              "2" => "1",
              "3" => "1",
              "4" => "1"
            ),
            "0/1,0/1/3,0/1/2,0/1/4"),
      array("0/1,0/1/3,0/1/2,0/1/4", 
            array(
              "1" => "0",
              "2" => "1",
              "3" => "1"
            ),
            "0/1,0/1/3,0/1/2"),
      array("0/1,0/1/3,0/1/2,0/1/4", 
            array(
              "1" => "0",
              "2" => "1",
              "4" => "1"
            ),
            "0/1,0/1/2,0/1/4"),
      array("0/1,0/1/3,0/1/2,0/1/4", 
            array(
              "1" => "0",
              "3" => "1",
              "4" => "1"
            ),
            "0/1,0/1/3,0/1/4"),
    );
  }
  
  /**
   * @dataProvider providerTestNormalizeStorylineStructure
   */
  function testNormalizeStorylineStructure($start_order, $categories, $ending_order) {
    foreach ($categories as $id => $parent) {
      add_category($id, (object)array('parent' => $parent));
    }
    $this->cpm->properties['comiccat'] = 1;
    
    update_option('comicpress-storyline-category-order', $start_order);
    $this->cpm->normalize_storyline_structure();
    $this->assertEquals($ending_order, get_option('comicpress-storyline-category-order'));
  }
  
  function providerTestConvertShortSize() {
    return array(
      array("1", 1),
      array("1k", 1024),
      array("1m", 1024 * 1024),
      array("1g", 1024 * 1024 * 1024),
      array("1k ", 1024),
      array("1 k ", 1024),
    );
  }
  
  /**
   * @dataProvider providerTestConvertShortSize
   */
  function testConvertShortSize($given, $expected) {
    $this->assertEquals($expected, $this->cpm->convert_short_size_string_to_bytes($given));
  }
  
  function providerTestReadComicsFolder() {
    return array(
      array(
        array(),
        array(),
        0
      ),
      array(
        array("meow"),
        array(),
        0
      ),
      array(
        array("meow"),
        array(),
        1
      ),
      array(
        array("meow.png"),
        array(),
        0
      ),
      array(
        array("meow.png"),
        array("meow.png"),
        1
      ),
      array(
        array("1990-01-01.png"),
        array("1990-01-01.png"),
        0
      ),
      array(
        array("1990-01-01.png"),
        array("1990-01-01.png"),
        0
      )
    );
  }
  
  /**
   * @dataProvider providerTestReadComicsFolder
   */
  function testReadComicsFolder($given, $expected, $skip_checks) {
    update_option('comicpress-manager-cpm-skip-checks', $skip_checks);
    $this->assertEquals($expected, $this->cpm->read_comics_folder($given));
  }
  
  function testGetComicFolderPath() {
    $this->assertEquals(CPM_DOCUMENT_ROOT . '/' . $this->cpm->properties['comic_folder'], $this->cpm->get_comic_folder_path());
    
    add_category("4", (object)array("slug" => "test"));
    update_option('comicpress-manager-manage-subcomic', '4');

    $this->assertEquals(CPM_DOCUMENT_ROOT . '/' . $this->cpm->properties['comic_folder'] . '/test', $this->cpm->get_comic_folder_path());
  }
  
  function providerTestGetThumbnailsToGenerate() {
    return array(
      array(
        array(
          'scale_method'     => false,
          'writable'         => array('rss' => true, 'archive' => true),
          'separate_folders' => array('rss' => true, 'archive' => true),
          'generate_thumbs'  => array('rss' => 1, 'archive' => 1)
        ),
        array()
      ),
      array(
        array(
          'scale_method'     => new ComicPressGDProcessing(),
          'writable'         => array('rss' => false, 'archive' => false),
          'separate_folders' => array('rss' => true, 'archive' => true),
          'generate_thumbs'  => array('rss' => 1, 'archive' => 1)
        ),
        array()
      ),
      array(
        array(
          'scale_method'     => new ComicPressGDProcessing(),
          'writable'         => array('rss' => true, 'archive' => true),
          'separate_folders' => array('rss' => false, 'archive' => false),
          'generate_thumbs'  => array('rss' => 1, 'archive' => 1)
        ),
        array()
      ),
      array(
        array(
          'scale_method'     => new ComicPressGDProcessing(),
          'writable'         => array('rss' => true, 'archive' => true),
          'separate_folders' => array('rss' => true, 'archive' => true),
          'generate_thumbs'  => array('rss' => 0, 'archive' => 0)
        ),
        array()
      ),
      array(
        array(
          'scale_method'     => new ComicPressGDProcessing(),
          'writable'         => array('rss' => true, 'archive' => true),
          'separate_folders' => array('rss' => true, 'archive' => true),
          'generate_thumbs'  => array('rss' => 1, 'archive' => 0)
        ),
        array('rss')
      ),
    );
  }
  
  /**
   * @dataProvider providerTestGetThumbnailsToGenerate
   */
  function testGetThumbnailsToGenerate($config, $result) {
    $this->cpm->scale_method = $config['scale_method'];
    $this->cpm->thumbs_folder_writable = $config['writable'];
    $this->cpm->separate_thumbs_folder_defined = $config['separate_folders'];
    
    foreach ($config['generate_thumbs'] as $key => $value) {
      update_option("comicpress-manager-cpm-{$key}-generate-thumbnails", $value);
    }
    
    $this->assertEquals($result, $this->cpm->get_thumbnails_to_generate());
  }
  
  function testCheckSeparateThumbnailFolders() {
    $this->cpm->properties = array(
      'comic_folder' => 'test',
      'rss_comic_folder' => 'test',
      'archive_comic_folder' => 'test2'
    );
    
    $this->cpm->_check_separate_thumbnail_folders();
    
    $this->assertFalse($this->cpm->separate_thumbs_folder_defined['rss']);
    $this->assertTrue($this->cpm->separate_thumbs_folder_defined['archive']);  
  }
  
  function testImageFolderWritable() {
    $this->assertEquals($this->cpm->error_types['NOT_A_FOLDER'],
                        $this->cpm->_test_image_folder_writable(md5(rand()), "rss"));
    
    $dir = dirname(__FILE__) . "/write-test-folder";
    if (!is_dir($dir)) { mkdir($dir); }
    chmod($dir, 0700);
    
    foreach ($this->cpm->folders as $folder_info) {
      list ($name, $property, $is_fatal, $thumb_type) = $folder_info;
      if ($thumb_type != "") {
        $this->cpm->thumbs_folder_writable[$thumb_type] = null;
      }
    }
    
    $this->assertEquals("", $this->cpm->_test_image_folder_writable($dir, ""));

    $this->assertFalse(isset($this->cpm->thumbs_folder_writable[""]));

    chmod($dir, 0000);
    
    $this->assertEquals($this->cpm->error_types['NOT_WRITABLE'], $this->cpm->_test_image_folder_writable($dir, ""));
    
    $this->assertEquals("", $this->cpm->_test_image_folder_writable($dir, "rss"));
    
    $this->assertFalse($this->cpm->thumbs_folder_writable["rss"]);

    foreach ($this->cpm->folders as $folder_info) {
      list ($name, $property, $is_fatal, $thumb_type) = $folder_info;
      if ($thumb_type != "") {
        $this->cpm->thumbs_folder_writable[$thumb_type] = null;
      }
    }
    
    $this->cpm->set_cpm_option('cpm-rss-generate-thumbnails', 1);

    $this->assertEquals($this->cpm->error_types['NOT_WRITABLE'], $this->cpm->_test_image_folder_writable($dir, "rss"));

    $this->assertFalse($this->cpm->thumbs_folder_writable["rss"]);
    
    rmdir($dir);
  }
  
  function testCheckCategory() {
    $this->cpm->properties['test'] = null;
    $this->assertEquals($this->cpm->error_types['INVALID_CATEGORY'], $this->cpm->_check_category('test'));

    $this->cpm->properties['test'] = 1;
    $this->assertEquals($this->cpm->error_types['CATEGORY_DOES_NOT_EXIST'], $this->cpm->_check_category('test'));
    
    add_category(1, (object)array('slug' => 'test'));
    $this->assertTrue(is_null($this->cpm->category_info['test']));
    $this->assertEquals("", $this->cpm->_check_category('test'));
    $this->assertEquals(array('slug' => 'test', 'term_id' => 1, 'cat_ID' => 1), $this->cpm->category_info['test']);
  }
}

?>
