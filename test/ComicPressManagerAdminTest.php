<?php

require_once('PHPUnit/Framework.php');
require_once(realpath(dirname(__FILE__) . '/../classes/ComicPressManagerAdmin.php'));
require_once(realpath(dirname(__FILE__) . '/../../mockpress/mockpress.php'));

define("CPM_DATE_FORMAT", "Y-m-d");
define("CPM_DOCUMENT_ROOT", realpath(dirname(__FILE__)));
define("CPM_STRLEN_REALPATH_DOCUMENT_ROOT", strlen(realpath(CPM_DOCUMENT_ROOT)));

class ComicPressManagerAdminTest extends PHPUnit_Framework_TestCase {
  function setUp() {
    global $comicpress_manager;
    
    _reset_wp();
    $this->adm = new ComicPressManagerAdmin();
    unset($comicpress_manager);
  }
  
  function providerTestSetUpHooks() {
    return array(
      array('actions', 'add_category_form_pre', 'comicpress_categories_warning'),
      array('actions', 'pre_post_update', 'handle_pre_post_update'),
      array('actions', 'save_post', 'handle_edit_post'),
      array('actions', 'edit_form_advanced', 'show_comic_caller'),
      array('actions', 'delete_post', 'handle_delete_post'),
      array('actions', 'create_category', 'rebuild_storyline_structure'),
      array('actions', 'delete_category', 'rebuild_storyline_structure'),
      array('actions', 'edit_category', 'rebuild_storyline_structure'),
      array('filters',  'manage_posts_columns', 'manage_posts_columns'),
      array('actions', 'manage_posts_custom_column', 'manage_posts_custom_column'),
      array('actions', 'admin_menu', 'setup_admin_menu')
    );
  }
  
  /**
   * @dataProvider providerTestSetUpHooks
   */
  function testSetUpHooks($type, $name, $method) {
    global $wp_test_expectations;
    
    $this->assertEquals(array($this->adm, $method), $wp_test_expectations[$type][$name]);
  }
  
  function testComicPressCategoriesWarning() {
    ob_start();
    $this->adm->comicpress_categories_warning();
    $this->assertNotEquals("", ob_get_clean());
    
    add_category(1, (object)array('slug' => 'test'));
    ob_start();
    $this->adm->comicpress_categories_warning();
    $this->assertNotEquals("", ob_get_clean());
    
    add_category(2, (object)array('slug' => 'test'));
    ob_start();
    $this->adm->comicpress_categories_warning();
    $this->assertEquals("", ob_get_clean());
  }
  
  function testVerifyPostBeforeHook() {
    global $comicpress_manager;

    // managing posts?
    $comicpress_manager = $this->getMock("ComicPressManager", array('get_cpm_option'));
    $comicpress_manager->is_cpm_managing_posts = true;
    $comicpress_manager->expects($this->never())->method('get_cpm_option');    
    $this->assertFalse($this->adm->_verify_post_before_hook(1));
    
    // not managing posts? user called, but not managing
    $comicpress_manager = $this->getMock("ComicPressManager", array('get_cpm_option'));
    $comicpress_manager->is_cpm_managing_posts = false;
    $comicpress_manager->expects($this->once())
                       ->method('get_cpm_option')
                       ->will($this->returnValue("0"));
    $this->assertFalse($this->adm->_verify_post_before_hook(1));

    // user called, managing, but bad post
    $comicpress_manager = $this->getMock("ComicPressManager", array('get_cpm_option'));
    $comicpress_manager->is_cpm_managing_posts = false;
    $comicpress_manager->expects($this->once())
                       ->method('get_cpm_option')
                       ->will($this->returnValue("1"));
    $this->assertFalse($this->adm->_verify_post_before_hook(1));

    // user called, managing, good post but not an Entry
    $comicpress_manager = $this->getMock("ComicPressManager", array('get_cpm_option'));
    $comicpress_manager->is_cpm_managing_posts = false;
    $comicpress_manager->expects($this->once())
                       ->method('get_cpm_option')
                       ->will($this->returnValue("1"));
    $id = wp_insert_post(array(
      'post_type' => 'page'
    ));
    $this->assertFalse($this->adm->_verify_post_before_hook($id));
    
    // is a valid entry
    $comicpress_manager = $this->getMock("ComicPressManager", array('get_cpm_option'));
    $comicpress_manager->is_cpm_managing_posts = false;
    $comicpress_manager->expects($this->once())
                       ->method('get_cpm_option')
                       ->will($this->returnValue("1"));
    $id = wp_insert_post(array(
      'post_type' => 'entry'
    ));
    $this->assertEquals((object)array('post_type' => 'entry', 'ID' => $id), $this->adm->_verify_post_before_hook($id));
  }
  
  function testIsPostInComicCategory() {
    global $comicpress_manager;
    
    // not in a comic category    
    $comicpress_manager = $this->getMock("ComicPressManager", array('get_all_comic_categories'));
    $comicpress_manager->expects($this->once())
                       ->method('get_all_comic_categories')
                       ->will($this->returnValue(
                         array(
                           'category_tree' => array("0/2")
                         )
                       ));
    wp_set_post_categories(1, array(1));
    $this->assertFalse($this->adm->_is_post_in_comic_category(1));

    // in comic category
    $comicpress_manager = $this->getMock("ComicPressManager", array('get_all_comic_categories'));
    $comicpress_manager->expects($this->once())
                       ->method('get_all_comic_categories')
                       ->will($this->returnValue(
                         array(
                           'category_tree' => array("0/2")
                         )
                       ));
    wp_set_post_categories(2, array(2));
    $this->assertTrue($this->adm->_is_post_in_comic_category(2));
  }
  
  function testHandlePrePostUpdate() {
    global $comicpress_manager;
    
    $target = realpath(dirname(__FILE__) . '/comics');
    foreach (glob($target . '/*') as $file ) { @unlink($file); }
    touch($target . '/2009-01-01.jpg');
        
    // is in comic category, but doesn't touch existing file because bad timestamp provided
    $comicpress_manager = $this->getMock("ComicPressManager", array('get_cpm_option', 'get_all_comic_categories', 'read_information_and_check_config'));
    $comicpress_manager->is_cpm_managing_posts = false;
    $comicpress_manager->expects($this->once())
                       ->method('get_cpm_option')
                       ->will($this->returnValue("1"));
    $comicpress_manager->expects($this->once())
                       ->method('get_all_comic_categories')
                       ->will($this->returnValue(
                         array(
                           'category_tree' => array("0/2")
                         )
                       ));
    $comicpress_manager->expects($this->never())->method('read_information_and_check_config');
    $id = wp_insert_post(array(
      'post_type' => 'entry',
      'post_date' => '2009-01-02'
    ));
    wp_set_post_categories($id, array(1));
    $_POST = array(
      'aa' => md5(rand()),
      'mm' => md5(rand()),
      'jj' => md5(rand())      
    );
    $this->adm->handle_pre_post_update($id);

    // is in comic category, but date didn't change
    $comicpress_manager = $this->getMock("ComicPressManager", array('get_cpm_option', 'get_all_comic_categories', 'read_information_and_check_config'));
    $comicpress_manager->is_cpm_managing_posts = false;
    $comicpress_manager->expects($this->once())
                       ->method('get_cpm_option')
                       ->will($this->returnValue("1"));
    $comicpress_manager->expects($this->once())
                       ->method('get_all_comic_categories')
                       ->will($this->returnValue(
                         array(
                           'category_tree' => array("0/2")
                         )
                       ));
    $comicpress_manager->expects($this->never())->method('read_information_and_check_config');
    $id = wp_insert_post(array(
      'post_type' => 'entry',
      'post_date' => '2009-01-01'
    ));
    wp_set_post_categories($id, array(2));
    $_POST = array(
      'aa' => '2009',
      'mm' => '01',
      'jj' => '01'
    );
    $this->adm->handle_pre_post_update($id);
    
    // is in comic category, date changed, file not moved
    $comicpress_manager = $this->getMock("ComicPressManager", array('get_cpm_option', 'get_all_comic_categories', 'read_information_and_check_config', 'breakdown_comic_filename'));
    $comicpress_manager->is_cpm_managing_posts = false;
    $comicpress_manager->expects($this->once())
                       ->method('get_cpm_option')
                       ->will($this->returnValue("1"));
    $comicpress_manager->expects($this->once())
                       ->method('get_all_comic_categories')
                       ->will($this->returnValue(
                         array(
                           'category_tree' => array("0/2")
                         )
                       ));
    $comicpress_manager->expects($this->never())->method('read_information_and_check_config');
    $id = wp_insert_post(array(
      'post_type' => 'entry',
      'post_date' => '2009-01-03'
    ));
    wp_set_post_categories($id, array(2));
    $_POST = array(
      'aa' => '2009',
      'mm' => '01',
      'jj' => '02'
    );
    $comicpress_manager->comic_files = array(
      'comics/2009-01-01.jpg'
    );
    $comicpress_manager->expects($this->once())
                       ->method('breakdown_comic_filename')
                       ->with($this->equalTo('2009-01-01.jpg'))
                       ->will($this->returnValue(
                         array(
                           'date' => '2009-01-01'
                         )
                       ));
    $this->adm->handle_pre_post_update($id);
    $this->assertFileExists($target . '/2009-01-01.jpg');

    // is in comic category, date changed, file moved
    $comicpress_manager = $this->getMock("ComicPressManager", array('get_cpm_option', 'get_all_comic_categories', 'read_information_and_check_config', 'breakdown_comic_filename'));
    $comicpress_manager->is_cpm_managing_posts = false;
    $comicpress_manager->expects($this->once())
                       ->method('get_cpm_option')
                       ->will($this->returnValue("1"));
    $comicpress_manager->expects($this->once())
                       ->method('get_all_comic_categories')
                       ->will($this->returnValue(
                         array(
                           'category_tree' => array("0/2")
                         )
                       ));
    $comicpress_manager->expects($this->never())->method('read_information_and_check_config');
    $id = wp_insert_post(array(
      'post_type' => 'entry',
      'post_date' => '2009-01-01'
    ));
    wp_set_post_categories($id, array(2));
    $_POST = array(
      'aa' => '2009',
      'mm' => '01',
      'jj' => '02'
    );
    $comicpress_manager->comic_files = array(
      realpath(dirname(__FILE__) . '/comics/2009-01-01.jpg')
    );
    $comicpress_manager->folders = array();
    $comicpress_manager->expects($this->once())
                       ->method('breakdown_comic_filename')
                       ->with($this->equalTo('2009-01-01.jpg'))
                       ->will($this->returnValue(
                         array(
                           'date' => '2009-01-01'
                         )
                       ));
    $this->adm->handle_pre_post_update($id);
    $this->assertFileExists($target . '/2009-01-02.jpg');
    
    @unlink($target . '/2009-01-02.jpg');
  }
  
  function testFindThumbnailsByName() {
    global $comicpress_manager;
    
    $target = dirname(__FILE__) . '/thumbs';
    foreach (glob($target . '/*') as $file ) { @unlink($file); }
    touch($target . '/2009-01-01.jpg');
    
    $comicpress_manager->folders = array(
      array('test', 'test', false, 'test'),
      array('test2', 'test2', false, 'test2'),
      array('test3', 'test3', false, 'test3')
    );
    
    $comicpress_manager->properties = array(
      'comic_folder' => 'comics',
      'test' => "thumbs",
      'test2' => "thumbs2",
      'test3' => "thumbs3",      
    );
    
    $comicpress_manager->separate_thumbs_folder_defined = array();
    $comicpress_manager->separate_thumbs_folder_defined['test'] = true;
    $comicpress_manager->separate_thumbs_folder_defined['test2'] = false;
    $comicpress_manager->separate_thumbs_folder_defined['test3'] = true;    
    
    $result = $this->adm->find_thumbnails_by_filename(dirname(__FILE__) . "/comics/2009-01-01.jpg");
    
    $this->assertEquals(array('test' => '/thumbs/2009-01-01.jpg'), $result);
  }
  
  function testHandleEditPost() {
    global $comicpress_manager;
    
    // no file uploaded, not in comic category, no meta data
    $comicpress_manager = $this->getMock("ComicPressManager", array('get_cpm_option', 'get_all_comic_categories'));
    $comicpress_manager->is_cpm_managing_posts = false;
    $comicpress_manager->expects($this->once())
                       ->method('get_cpm_option')
                       ->will($this->returnValue("1"));
    $comicpress_manager->expects($this->once())
                       ->method('get_all_comic_categories')
                       ->will($this->returnValue(
                         array(
                           'category_tree' => array("0/2")
                         )
                       ));
    $id = wp_insert_post(array(
      'post_type' => 'entry',
    ));
    wp_set_post_categories($id, array(1));

    $this->adm->handle_pre_post_update($id);
    $this->assertEquals(array(1), wp_get_post_categories($id));

    // no file uploaded, in comic category, meta data
    $comicpress_manager = $this->getMock("ComicPressManager", array('get_cpm_option', 'get_all_comic_categories'));
    $comicpress_manager->is_cpm_managing_posts = false;
    $comicpress_manager->expects($this->once())
                       ->method('get_cpm_option')
                       ->will($this->returnValue("1"));
    $comicpress_manager->expects($this->any())
                       ->method('get_all_comic_categories')
                       ->will($this->returnValue(
                         array(
                           'category_tree' => array("0/2")
                         )
                       ));
    $id = wp_insert_post(array(
      'post_type' => 'entry',
    ));
    wp_set_post_categories($id, array(2));
    $_POST['comicpress-img-title'] = "test";
    $_POST['comicpress-transcript'] = "test2";

    $this->adm->handle_edit_post($id);
    $this->assertEquals(array(2), wp_get_post_categories($id));
    $this->assertEquals("test", get_post_meta($id, 'hovertext', true));
    $this->assertEquals("test2", get_post_meta($id, 'transcript', true));
  }
  
  function testObfuscateFilename() {
    global $comicpress_manager;

    $comicpress_manager = $this->getMock("ComicPressManager", array("breakdown_comic_filename", "get_cpm_option"));    
    $comicpress_manager->expects($this->once())
                       ->method("breakdown_comic_filename")
                       ->will($this->returnValue(false));
    $comicpress_manager->expects($this->never())
                       ->method("get_cpm_option");
    $this->assertEquals("test.jpg", $this->adm->obfuscate_filename("test.jpg"));
    
    $comicpress_manager = $this->getMock("ComicPressManager", array("breakdown_comic_filename", "get_cpm_option"));
    $comicpress_manager->expects($this->once())
                       ->method("breakdown_comic_filename")
                       ->will($this->returnValue(array('date' => "2009-01-01")));
    $comicpress_manager->expects($this->once())
                       ->method("get_cpm_option")
                       ->will($this->returnValue("none"));
    $this->assertEquals("2009-01-01.jpg", $this->adm->obfuscate_filename("2009-01-01.jpg"));
    
    $comicpress_manager = $this->getMock("ComicPressManager", array("breakdown_comic_filename", "get_cpm_option"));
    $comicpress_manager->expects($this->once())
                       ->method("breakdown_comic_filename")
                       ->will($this->returnValue(array('date' => "2009-01-01", 'title' => '-test')));
    $comicpress_manager->expects($this->once())
                       ->method("get_cpm_option")
                       ->will($this->returnValue("append"));
    $this->assertTrue(strpos($this->adm->obfuscate_filename("2009-01-01-test.jpg"), "2009-01-01-test") !== false);

    $comicpress_manager = $this->getMock("ComicPressManager", array("breakdown_comic_filename", "get_cpm_option"));
    $comicpress_manager->expects($this->once())
                       ->method("breakdown_comic_filename")
                       ->will($this->returnValue(array('date' => "2009-01-01", 'title' => '-test')));
    $comicpress_manager->expects($this->once())
                       ->method("get_cpm_option")
                       ->will($this->returnValue("replace"));
    $result = $this->adm->obfuscate_filename("2009-01-01-test.jpg");
    $this->assertFalse(strpos($result, "2009-01-01-test") !== false);
    $this->assertTrue(strpos($result, "2009-01-01") !== false);
  }
  
  function testGoGDFileCheckOnUpload() {
    global $comicpress_manager;
    
    $comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename', 'get_cpm_option'));
    $comicpress_manager->expects($this->once())->method('breakdown_comic_filename')->will($this->returnValue(false));
    $comicpress_manager->expects($this->once())->method('get_cpm_option')->will($this->returnValue(0));
    $result = $this->adm->do_gd_file_check_on_upload("test.jpg", "test.jpg");
    $this->assertEquals(false, $result['result']);
    
    $comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename', 'get_cpm_option'));
    $comicpress_manager->expects($this->once())->method('breakdown_comic_filename')->will($this->returnValue(true));
    $comicpress_manager->expects($this->once())->method('get_cpm_option')->will($this->returnValue(1));
    $comicpress_manager->gd_processor = $this->getMock('ComicPressGDProcessor', array('get_image_size'));
    $comicpress_manager->gd_processor->expects($this->once())->method('get_image_size')->will($this->returnValue(false));
    $result = $this->adm->do_gd_file_check_on_upload("2009-01-01.jpg", "2009-01-01.jpg");
    $this->assertEquals(false, $result['file_ok']);
    $this->assertEquals(false, $result['is_cmyk']);

    $comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename', 'get_cpm_option'));
    $comicpress_manager->expects($this->once())->method('breakdown_comic_filename')->will($this->returnValue(true));
    $comicpress_manager->expects($this->once())->method('get_cpm_option')->will($this->returnValue(1));
    $comicpress_manager->gd_processor = $this->getMock('ComicPressGDProcessor', array('get_image_size'));
    $comicpress_manager->gd_processor->expects($this->once())->method('get_image_size')->will($this->returnValue(array('2' => IMAGETYPE_GIF)));
    $result = $this->adm->do_gd_file_check_on_upload("2009-01-01.gif", "2009-01-01.gif");
    $this->assertEquals(true, $result['file_ok']);
    $this->assertEquals(false, $result['is_cmyk']);
    $this->assertEquals(false, $result['gd_did_rename']);
    $this->assertEquals("2009-01-01.gif", $result['target_filename']);

    $comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename', 'get_cpm_option'));
    $comicpress_manager->expects($this->any())->method('breakdown_comic_filename')->will($this->onConsecutiveCalls(true, false));
    $comicpress_manager->expects($this->once())->method('get_cpm_option')->will($this->returnValue(1));
    $comicpress_manager->gd_processor = $this->getMock('ComicPressGDProcessor', array('get_image_size'));
    $comicpress_manager->gd_processor->expects($this->once())->method('get_image_size')->will($this->returnValue(array('2' => IMAGETYPE_GIF)));
    $result = $this->adm->do_gd_file_check_on_upload("2009-01-01.jpg", "2009-01-01.jpg");
    $this->assertEquals(true, $result['file_ok']);
    $this->assertEquals(false, $result['is_cmyk']);
    $this->assertEquals(true, $result['gd_did_rename']);
    $this->assertEquals("2009-01-01.gif", $result['target_filename']);

    $comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename', 'get_cpm_option'));
    $comicpress_manager->expects($this->any())->method('breakdown_comic_filename')->will($this->onConsecutiveCalls(true, false));
    $comicpress_manager->expects($this->at(1))->method('get_cpm_option')->with('cpm-perform-gd-check')->will($this->returnValue(1));
    $comicpress_manager->gd_processor = $this->getMock('ComicPressGDProcessor', array('get_image_size'));
    $comicpress_manager->gd_processor->expects($this->once())->method('get_image_size')->will($this->returnValue(array('2' => IMAGETYPE_JPEG, 'channels' => 4)));
    $comicpress_manager->scale_method = $this->getMock("ComicPressGDProcessor", array('convert_to_rgb'));
    $comicpress_manager->scale_method->expects($this->once())->method('convert_to_rgb')->will($this->returnValue(true));
    $comicpress_manager->expects($this->at(2))->method('get_cpm_option')->with('cpm-thumbnail-quality')->will($this->returnValue(80));
    $this->adm->_f = $this->getMock('ComicPressFileOperations');
    $this->adm->_f->expects($this->once())->method('rename');
    
    $result = $this->adm->do_gd_file_check_on_upload("2009-01-01.jpg", "2009-01-01.jpg");
    $this->assertEquals(true, $result['file_ok']);
    $this->assertEquals(true, $result['is_cmyk']);
    $this->assertEquals(false, $result['gd_did_rename']);
    $this->assertEquals("2009-01-01.jpg", $result['target_filename']);
  }
  
  function testHandleUploadedFile() {
    global $comicpress_manager;
    update_option('comicpress-manager-cpm-perform-gd-check', 0);
    
    // bad
    $comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename', 'get_cpm_option'));
    $comicpress_manager->expects($this->any())->method("breakdown_comic_filename")->will($this->returnValue(false));
    $this->assertEquals(array(array('not handled', 'meow.jpg')), $this->adm->handle_uploaded_file('/tmp/meow.jpg', 'comics', 'meow.jpg', 'meow.jpg'));
    
    // normal
    $this->adm->_f = $this->getMock('ComicPressFileOperations');
    $this->adm->_f->expects($this->once())->method('rename')->with('/tmp/meow.jpg', 'comics/2009-01-01-test.jpg');
    $this->adm->_f->expects($this->once())->method('file_exists')->will($this->returnValue(true));
    $comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename', 'get_cpm_option'));
    $comicpress_manager->expects($this->any())->method("breakdown_comic_filename")->will($this->returnValue(array('converted_title' => 'Test')));
    $this->assertEquals(
      array(
        array('file created', 'comics/2009-01-01-test.jpg'),
        array('file uploaded', '2009-01-01-test.jpg')
      ),
      $this->adm->handle_uploaded_file(
        '/tmp/meow.jpg', 
        'comics', 
        '2009-01-01-test.jpg', 
        '2009-01-01-test.jpg'
      )
    );    

    // filesystem error
    $this->adm->_f = $this->getMock('ComicPressFileOperations');
    $this->adm->_f->expects($this->once())->method('rename')->with('/tmp/meow.jpg', 'comics/2009-01-01-test.jpg');
    $this->adm->_f->expects($this->once())->method('file_exists')->will($this->returnValue(false));
    $comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename', 'get_cpm_option'));
    $comicpress_manager->expects($this->any())->method("breakdown_comic_filename")->will($this->returnValue(array('converted_title' => 'Test')));
    $this->assertEquals(
      array(
        array('not uploaded', '2009-01-01-test.jpg')
      ),
      $this->adm->handle_uploaded_file(
        '/tmp/meow.jpg', 
        'comics', 
        '2009-01-01-test.jpg', 
        '2009-01-01-test.jpg'
      )
    );    

    // obfuscate
    $this->adm->_f = $this->getMock('ComicPressFileOperations');
    $this->adm->_f->expects($this->once())->method('rename');
    $this->adm->_f->expects($this->once())->method('file_exists')->will($this->returnValue(true));
    $comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename', 'get_cpm_option'));
    $comicpress_manager->expects($this->at(1))->method('get_cpm_option')->with('cpm-perform-gd-check')->will($this->returnValue(0));
    $comicpress_manager->expects($this->at(3))->method('get_cpm_option')->with('cpm-obfuscate-filenames-on-upload')->will($this->returnValue(1));
    $comicpress_manager->expects($this->any())->method("breakdown_comic_filename")->will($this->returnValue(array('converted_title' => 'Test')));
    $result = $this->adm->handle_uploaded_file(
      '/tmp/meow.jpg', 
      'comics', 
      '2009-01-01-test.jpg', 
      '2009-01-01-test.jpg'
    );

    $needed = array('file created', 'file uploaded');
    foreach ($result as $message) {
      if (($index = array_search($message[0], $needed)) !== false) {
        array_splice($needed, $index, 1);
      }
    }
    
    $this->assertEquals(0, count($needed));

    // cmyk
    $this->adm->_f = $this->getMock('ComicPressFileOperations');
    $this->adm->_f->expects($this->any())->method('rename');
    $this->adm->_f->expects($this->once())->method('file_exists')->will($this->returnValue(true));
    $comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename', 'get_cpm_option'));
    $comicpress_manager->expects($this->at(1))->method('get_cpm_option')->with('cpm-perform-gd-check')->will($this->returnValue(1));
    $comicpress_manager->expects($this->at(2))->method('get_cpm_option')->with('cpm-thumbnail-quality')->will($this->returnValue(0));
    $comicpress_manager->expects($this->at(4))->method('get_cpm_option')->with('cpm-obfuscate-filenames-on-upload')->will($this->returnValue(""));
    $comicpress_manager->expects($this->any())->method("breakdown_comic_filename")->will($this->returnValue(array('converted_title' => 'Test')));
    $comicpress_manager->gd_processor = $this->getMock('ComicPressGDProcessor', array('get_image_size', 'convert_to_rgb'));
    $comicpress_manager->gd_processor->expects($this->once())->method('get_image_size')->will($this->returnValue(array('2' => IMAGETYPE_JPEG, 'channels' => 4)));
    $comicpress_manager->gd_processor->expects($this->once())->method('convert_to_rgb')->will($this->returnValue(true));
    $comicpress_manager->scale_method = $comicpress_manager->gd_processor;
    
    $this->assertEquals(
      array(
        array('file created', 'comics/2009-01-01-test.jpg'),
        array('file uploaded', '2009-01-01-test.jpg'),
        array('convert cmyk', '2009-01-01-test.jpg')
      ),
      $this->adm->handle_uploaded_file(
        '/tmp/meow.jpg', 
        'comics', 
        '2009-01-01-test.jpg', 
        '2009-01-01-test.jpg'
      )
    );    

    // gd rename
    $this->adm->_f = $this->getMock('ComicPressFileOperations');
    $this->adm->_f->expects($this->any())->method('rename');
    $this->adm->_f->expects($this->once())->method('file_exists')->will($this->returnValue(true));
    $comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename', 'get_cpm_option'));
    $comicpress_manager->expects($this->at(1))->method('get_cpm_option')->with('cpm-perform-gd-check')->will($this->returnValue(1));
    $comicpress_manager->expects($this->at(4))->method('get_cpm_option')->with('cpm-obfuscate-filenames-on-upload')->will($this->returnValue(""));
    $comicpress_manager->expects($this->any())->method("breakdown_comic_filename")->will($this->returnValue(array('converted_title' => 'Test')));
    $comicpress_manager->gd_processor = $this->getMock('ComicPressGDProcessor', array('get_image_size'));
    $comicpress_manager->gd_processor->expects($this->once())->method('get_image_size')->will($this->returnValue(array('2' => IMAGETYPE_JPEG)));
    $comicpress_manager->scale_method = $comicpress_manager->gd_processor;
    
    $this->assertEquals(
      array(
        array('file created', 'comics/2009-01-01-test.jpg'),
        array('file uploaded', '2009-01-01-test.jpg'),
        array('gd rename file', '2009-01-01-test.gif')
      ),
      $this->adm->handle_uploaded_file(
        '/tmp/meow.gif', 
        'comics', 
        '2009-01-01-test.gif', 
        '2009-01-01-test.gif'
      )
    );    

    // no extension
    $this->adm->_f = $this->getMock('ComicPressFileOperations');
    $this->adm->_f->expects($this->any())->method('rename');
    $this->adm->_f->expects($this->once())->method('file_exists')->will($this->returnValue(true));
    $comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename', 'get_cpm_option'));
    $comicpress_manager->expects($this->at(1))->method('get_cpm_option')->with('cpm-perform-gd-check')->will($this->returnValue(1));
    $comicpress_manager->expects($this->at(4))->method('get_cpm_option')->with('cpm-obfuscate-filenames-on-upload')->will($this->returnValue(""));
    $comicpress_manager->expects($this->any())->method("breakdown_comic_filename")->will($this->returnValue(array('converted_title' => 'Test')));
    $comicpress_manager->gd_processor = $this->getMock('ComicPressGDProcessor', array('get_image_size'));
    $comicpress_manager->gd_processor->expects($this->once())->method('get_image_size')->will($this->returnValue(array('2' => IMAGETYPE_JPEG)));
    $comicpress_manager->scale_method = $comicpress_manager->gd_processor;
    
    $this->assertEquals(
      array(
        array('file created', 'comics/2009-01-01-test-jpg.jpg'),
        array('file uploaded', '2009-01-01-test-jpg.jpg'),
        array('gd rename file', '2009-01-01-test-jpg')
      ),
      $this->adm->handle_uploaded_file(
        '/tmp/meow.gif', 
        'comics', 
        '2009-01-01-test-jpg', 
        '2009-01-01-test-jpg'
      )
    );    

    // invalid imagetype
    $this->adm->_f = $this->getMock('ComicPressFileOperations');
    $this->adm->_f->expects($this->any())->method('rename');
    $this->adm->_f->expects($this->never())->method('file_exists');
    $comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename', 'get_cpm_option'));
    $comicpress_manager->expects($this->at(1))->method('get_cpm_option')->with('cpm-perform-gd-check')->will($this->returnValue(1));
    $comicpress_manager->expects($this->any())->method("breakdown_comic_filename")->will($this->returnValue(array('converted_title' => 'Test')));
    $comicpress_manager->gd_processor = $this->getMock('ComicPressGDProcessor', array('get_image_size'));
    $comicpress_manager->gd_processor->expects($this->once())->method('get_image_size')->will($this->returnValue(false));
    $comicpress_manager->scale_method = $comicpress_manager->gd_processor;
    
    $this->assertEquals(
      array(
        array('invalid image type', '2009-01-01-test.doc'),
      ),
      $this->adm->handle_uploaded_file(
        '/tmp/meow.doc', 
        'comics', 
        '2009-01-01-test.doc', 
        '2009-01-01-test.doc'
      )
    );    
  }
  
  function testTryUploadFiles() {
    global $comicpress_manager;
    
    $_POST['overwrite-existing-file-choice'] = "";
    $this->assertFalse($this->adm->_try_upload_replace("meow.jpg", 'comics'));
    
    $this->adm->_f = $this->getMock('ComicPressFileOperations');
    $this->adm->_f->expects($this->never())->method('unlink')->will($this->returnValue(false));
    $_POST['overwrite-existing-file-choice'] = "hiss.jpg";
    
    $this->assertEquals("hiss.jpg", $this->adm->_try_upload_replace("meow.jpg", 'comics'));

    $this->adm->_f = $this->getMock('ComicPressFileOperations');
    $this->adm->_f->expects($this->once())->method('unlink')->will($this->returnValue(false));
    $_POST['overwrite-existing-file-choice'] = "hiss.gif";
    
    $this->assertEquals("hiss.jpg", $this->adm->_try_upload_replace("meow.jpg", 'comics'));

    $this->adm->_f = $this->getMock('ComicPressFileOperations');
    $this->adm->_f->expects($this->exactly(2))->method('unlink')->will($this->returnValue(true));
    $_POST['overwrite-existing-file-choice'] = "hiss.gif";
    $comicpress_manager = $this->getMock('ComicPressManager', array('get_thumbnails_to_generate', 'get_subcomic_directory'));
    $comicpress_manager->expects($this->once())->method('get_thumbnails_to_generate')->will($this->returnValue(array('rss')));
    
    $this->assertEquals("hiss.jpg", $this->adm->_try_upload_replace("meow.jpg", 'comics'));
  }
  
  function testGeneratePostForUploadFile() {
    global $comicpress_manager, $wp_test_expectations;
    
    $comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename'));
    $comicpress_manager->expects($this->once())->method('breakdown_comic_filename')->will($this->returnValue(false));
    $this->assertEquals(array('invalid filename', 'meow.jpg'), $this->adm->_generate_post_for_uploaded_file('meow.jpg'));
    
    $comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename', 'generate_post_hash'));
    $comicpress_manager->expects($this->once())->method('breakdown_comic_filename')->will($this->returnValue(array('date' => '2009-01-01', 'converted_title' => "Test")));
    $comicpress_manager->expects($this->once())->method('generate_post_hash')->will($this->returnValue(array('post_title' => "Test")));
    unset($_POST['duplicate_check']);
    $_POST['hovertext-to-use'] = "test";
    $_POST['transcript-to-use'] = "test2";

    $result = $this->adm->_generate_post_for_uploaded_file("2009-01-01-test.jpg");
    $this->assertEquals("Test", $wp_test_expectations['posts'][1]->post_title);
    $this->assertEquals("test", get_post_meta(1, "hovertext", true));
    $this->assertEquals("test2", get_post_meta(1, "transcript", true));
    $this->assertEquals('post created', $result[0]);

    $comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename', 'generate_post_hash'));
    $comicpress_manager->expects($this->once())->method('breakdown_comic_filename')->will($this->returnValue(array('date' => '2009-01-01', 'converted_title' => "Test")));
    $comicpress_manager->expects($this->once())->method('generate_post_hash')->will($this->returnValue(array('post_title' => "Test")));
    $_POST['duplicate_check'] = true;

    $result = $this->adm->_generate_post_for_uploaded_file("2009-01-01-test.jpg");
    $this->assertEquals('duplicate post', $result[0]);
  }
  
  function testWriteThumbnails() {
    global $comicpress_manager;
    
    $comicpress_manager = $this->getMock('ComicPressManager');
    $comicpress_manager->separate_thumbs_folder_defined = array();
    $this->assertNull($this->adm->write_thumbnail('', '', false));
    
    $comicpress_manager = $this->getMock('ComicPressManager', array('get_subcomic_directory'));
    $comicpress_manager->separate_thumbs_folder_defined = array('rss' => true);
    $comicpress_manager->thumbs_folder_writable = array('rss' => true);    
    $this->adm->_f = $this->getMock('ComicPressFileOperations', array('file_exists', 'filemtime'));
    $this->adm->_f->expects($this->any())->method('file_exists')->will($this->returnValue(false));
    $this->assertNull($this->adm->write_thumbnail('', '', false));

    $comicpress_manager = $this->getMock('ComicPressManager', array('get_subcomic_directory'));
    $comicpress_manager->separate_thumbs_folder_defined = array('rss' => true);
    $comicpress_manager->thumbs_folder_writable = array('rss' => true);    
    $this->adm->_f = $this->getMock('ComicPressFileOperations', array('file_exists', 'filemtime'));
    $this->adm->_f->expects($this->any())->method('file_exists')->will($this->returnValue(true));
    $this->adm->_f->expects($this->at(2))->method('filemtime')->will($this->returnValue(1));
    $this->adm->_f->expects($this->at(3))->method('filemtime')->will($this->returnValue(2));
    $this->assertNull($this->adm->write_thumbnail('', '', false));

    $comicpress_manager = $this->getMock('ComicPressManager', array('get_subcomic_directory', 'get_cpm_option'));
    $comicpress_manager->expects($this->once())->method('get_subcomic_directory')->will($this->returnValue(false));
    $comicpress_manager->separate_thumbs_folder_defined = array('rss' => true);
    $comicpress_manager->thumbs_folder_writable = array('rss' => true);    
    $this->adm->_f = $this->getMock('ComicPressFileOperations', array('file_exists', 'filemtime'));
    $this->adm->_f->expects($this->any())->method('file_exists')->will($this->returnValue(true));
    $this->adm->_f->expects($this->at(1))->method('filemtime')->will($this->returnValue(2));
    $this->adm->_f->expects($this->at(2))->method('filemtime')->will($this->returnValue(1));
    $comicpress_manager->scale_method = $this->getMock('ComicPressGDProcessor', array('generate_thumbnails'));
    $comicpress_manager->properties = array('rss_comic_width' => 1, 'rss_comic_folder' => 'rss');
    $comicpress_manager->scale_method->expects($this->once())->method('generate_thumbnails')->with("test.jpg", array(array(CPM_DOCUMENT_ROOT . "/rss/test2.jpg", array('width' => 1))))->will($this->returnValue(1));
    
    $this->assertEquals(1, $this->adm->write_thumbnail('test.jpg', 'test2.jpg', false));
  }
  
  function testFindComicByDate() {
    global $comicpress_manager;
    $comicpress_manager = $this->getMock('ComicPressManager', array('get_comic_folder_path'));
    $comicpress_manager->expects($this->any())->method("get_comic_folder_path")->will($this->returnValue("test"));
    
    $this->assertFalse($this->adm->find_comic_by_date("meow"));
    
    $this->adm->_f = $this->getMock('ComicPressFileOperations', array('glob'));
    $this->adm->_f->expects($this->once())->method("glob")->will($this->returnValue(false));
    $this->assertFalse($this->adm->find_comic_by_date(time()));

    $comicpress_manager->allowed_extensions = array("jpg");
    $this->adm->_f = $this->getMock('ComicPressFileOperations', array('glob'));
    $this->adm->_f->expects($this->once())->method("glob")->will($this->returnValue(array("test.swf", "test.jpg")));
    $this->assertEquals("test.jpg", $this->adm->find_comic_by_date(time()));
  }
  
  function testHandleDeletePost() {
    global $comicpress_manager;
    
    $comicpress_manager = $this->getMock('ComicPressManager');
    $comicpress_manager->is_cpm_managing_posts = true;
    $comicpress_manager->expects($this->never())->method("get_cpm_option");
    $this->adm->handle_delete_post(null);
    
    $comicpress_manager = $this->getMock('ComicPressManager', array('get_cpm_option'));
    $comicpress_manager->is_cpm_managing_posts = false;
    $comicpress_manager->expects($this->once())->method("get_cpm_option")->will($this->returnValue('0'));
    $comicpress_manager->expects($this->never())->method("get_all_comic_categories");
    $this->adm->handle_delete_post(null);

    $comicpress_manager = $this->getMock('ComicPressManager', array('get_cpm_option'));
    $comicpress_manager->is_cpm_managing_posts = false;
    $comicpress_manager->expects($this->once())->method("get_cpm_option")->will($this->returnValue('1'));
    $comicpress_manager->expects($this->never())->method("get_all_comic_categories");
    $this->adm->handle_delete_post(null);

    wp_insert_post(array('ID' => 1, 'post_type' => 'page'));

    $comicpress_manager = $this->getMock('ComicPressManager', array('get_cpm_option'));
    $comicpress_manager->is_cpm_managing_posts = false;
    $comicpress_manager->expects($this->once())->method("get_cpm_option")->will($this->returnValue('1'));
    $comicpress_manager->expects($this->never())->method("get_all_comic_categories");
    $this->adm->handle_delete_post(1);

    wp_insert_post(array('ID' => 2, 'post_type' => 'entry', 'post_date' => md5(rand())));
    wp_set_post_categories(2, array(1));

    $comicpress_manager = $this->getMock('ComicPressManager', array('get_cpm_option', 'get_all_comic_categories'));
    $comicpress_manager->is_cpm_managing_posts = false;
    $comicpress_manager->expects($this->once())->method("get_cpm_option")->will($this->returnValue('1'));
    $comicpress_manager->expects($this->once())->method("get_all_comic_categories")->will($this->returnValue(array('category_tree' => array('0/1'))));
    $comicpress_manager->expects($this->never())->method("read_information_and_check_config");
    $this->adm->handle_delete_post(2);

    wp_insert_post(array('ID' => 3, 'post_type' => 'entry', 'post_date' => "2009-01-01"));
    wp_set_post_categories(3, array(1));

    $comicpress_manager = $this->getMock('ComicPressManager', array('get_cpm_option', 'get_all_comic_categories', "read_information_and_check_config"));
    $comicpress_manager->is_cpm_managing_posts = false;
    $comicpress_manager->expects($this->once())->method("get_cpm_option")->will($this->returnValue('1'));
    $comicpress_manager->expects($this->once())->method("get_all_comic_categories")->will($this->returnValue(array('category_tree' => array('0/1'))));
    $comicpress_manager->expects($this->once())->method("read_information_and_check_config");
    $comicpress_manager->expects($this->never())->method("breakdown_comic_filename");
    $this->adm->handle_delete_post(3);

    $comicpress_manager = $this->getMock('ComicPressManager', array('get_cpm_option', 'get_all_comic_categories', "breakdown_comic_filename"));
    $comicpress_manager->is_cpm_managing_posts = false;
    $comicpress_manager->expects($this->once())->method("get_cpm_option")->will($this->returnValue('1'));
    $comicpress_manager->expects($this->once())->method("get_all_comic_categories")->will($this->returnValue(array('category_tree' => array('0/1'))));
    $comicpress_manager->expects($this->never())->method("read_information_and_check_config");
    $comicpress_manager->comic_files = array('meow.jpg');
    $comicpress_manager->expects($this->once())->method("breakdown_comic_filename")->with('meow.jpg')->will($this->returnValue(false));
    $this->adm->_f = $this->getMock('ComicPressFileOperations');
    $this->adm->_f->expects($this->never())->method("unlink");
    $this->adm->handle_delete_post(3);
    
    $comicpress_manager = $this->getMock('ComicPressManager', array('get_cpm_option', 'get_all_comic_categories', "breakdown_comic_filename"));
    $comicpress_manager->is_cpm_managing_posts = false;
    $comicpress_manager->expects($this->once())->method("get_cpm_option")->will($this->returnValue('1'));
    $comicpress_manager->expects($this->once())->method("get_all_comic_categories")->will($this->returnValue(array('category_tree' => array('0/1'))));
    $comicpress_manager->expects($this->never())->method("read_information_and_check_config");
    $comicpress_manager->comic_files = array('meow.jpg');
    $comicpress_manager->expects($this->once())->method("breakdown_comic_filename")->with('meow.jpg')->will($this->returnValue(array('date' => '2009-01-02')));
    $this->adm->_f = $this->getMock('ComicPressFileOperations');
    $this->adm->_f->expects($this->never())->method("unlink");
    $this->adm->handle_delete_post(3);

    $adm = $this->getMock('ComicPressManagerAdmin', array('find_thumbnails_by_filename'));
    $comicpress_manager = $this->getMock('ComicPressManager', array('get_cpm_option', 'get_all_comic_categories', "breakdown_comic_filename"));
    $comicpress_manager->is_cpm_managing_posts = false;
    $comicpress_manager->expects($this->once())->method("get_cpm_option")->will($this->returnValue('1'));
    $comicpress_manager->expects($this->once())->method("get_all_comic_categories")->will($this->returnValue(array('category_tree' => array('0/1'))));
    $comicpress_manager->expects($this->never())->method("read_information_and_check_config");
    $comicpress_manager->comic_files = array('2009-01-01.jpg');
    $comicpress_manager->expects($this->once())->method("breakdown_comic_filename")->with('2009-01-01.jpg')->will($this->returnValue(array('date' => '2009-01-01')));
    $adm->_f = $this->getMock('ComicPressFileOperations');
    $adm->_f->expects($this->once())->method("unlink");
    $adm->expects($this->once())->method('find_thumbnails_by_filename')->with('2009-01-01.jpg')->will($this->returnValue(array()));
    $adm->handle_delete_post(3);
  }
  
  function testManagePostsCustomColumn() {
    global $comicpress_manager, $post;

    unset($this->adm->broken_down_comic_files);
    $this->adm->manage_posts_custom_column("test");
    $this->assertTrue(empty($this->adm->broken_down_comic_files));

    $comicpress_manager = $this->getMock('ComicPressManager', array('read_information_and_check_config'));
    $comicpress_manager->expects($this->once())->method('read_information_and_check_config');
    unset($this->adm->broken_down_comic_files);
    $comicpress_manager->comic_files = array();
    ob_start();
    $this->adm->manage_posts_custom_column("comic");
    $result = ob_get_clean();
    $this->assertTrue(empty($this->adm->broken_down_comic_files));
    $this->assertEquals("", $result);
    
    $comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename'));
    $comicpress_manager->expects($this->never())->method('read_information_and_check_config');
    unset($this->adm->broken_down_comic_files);
    $comicpress_manager->comic_files = array("meow.jpg");
    $comicpress_manager->expects($this->once())->method('breakdown_comic_filename')->with("meow.jpg")->will($this->returnValue(false));
    $comicpress_manager->expects($this->never())->method('get_subcomic_directory');
    ob_start();
    $this->adm->manage_posts_custom_column("comic");
    $result = ob_get_clean();
    $this->assertTrue(empty($this->adm->broken_down_comic_files));
    $this->assertEquals("", $result);

    wp_set_post_categories(1, array(2));
    $post = (object)array('ID' => 1);

    $comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename', 'get_subcomic_directory', 'get_all_comic_categories'));
    $comicpress_manager->expects($this->never())->method('read_information_and_check_config');
    unset($this->adm->broken_down_comic_files);
    $comicpress_manager->comic_files = array("2009-01-01.jpg");
    $comicpress_manager->expects($this->once())->method('breakdown_comic_filename')->with("2009-01-01.jpg")->will($this->returnValue(array('date' => '2009-01-01')));
    $comicpress_manager->expects($this->once())->method('get_subcomic_directory')->will($this->returnValue(false));
    $comicpress_manager->expects($this->once())->method('get_all_comic_categories')->will($this->returnValue(array('category_tree' => array("0/1"))));
    $comicpress_manager->expects($this->never())->method('find_thumbnails_by_filename');
    ob_start();
    $this->adm->manage_posts_custom_column("comic");
    $result = ob_get_clean();
    $this->assertFalse(empty($this->adm->broken_down_comic_files));
    
    $this->assertTrue(($xml = _to_xml($result)) !== false);
    $this->assertTrue(_node_exists($xml, '//script[@type="text/javascript"]'));
    $this->assertFalse(_node_exists($xml, '//img[@width="100"]'));
    
    wp_set_post_categories(2, array(1));
    $post = (object)array('ID' => 2, 'post_date' => '2009-01-02');

    $comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename', 'get_subcomic_directory', 'get_all_comic_categories'));
    $comicpress_manager->expects($this->never())->method('read_information_and_check_config');
    unset($this->adm->broken_down_comic_files);
    $comicpress_manager->comic_files = array("2009-01-01.jpg");
    $comicpress_manager->expects($this->once())->method('breakdown_comic_filename')->with("2009-01-01.jpg")->will($this->returnValue(array('date' => '2009-01-01')));
    $comicpress_manager->expects($this->once())->method('get_subcomic_directory')->will($this->returnValue(false));
    $comicpress_manager->expects($this->once())->method('get_all_comic_categories')->will($this->returnValue(array('category_tree' => array("0/1"))));
    $comicpress_manager->expects($this->never())->method('find_thumbnails_by_filename');
    ob_start();
    $this->adm->manage_posts_custom_column("comic");
    $result = ob_get_clean();
    $this->assertFalse(empty($this->adm->broken_down_comic_files));
    
    $this->assertTrue(($xml = _to_xml($result)) !== false);
    $this->assertTrue(_node_exists($xml, '//script[@type="text/javascript"]'));
    $this->assertFalse(_node_exists($xml, '//img[@width="100"]'));
    
    wp_set_post_categories(3, array(1));
    $post = (object)array('ID' => 3, 'post_date' => '2009-01-01');

    $adm = $this->getMock('ComicPressManagerAdmin', array('find_thumbnails_by_filename'));
    
    $comicpress_manager = $this->getMock('ComicPressManager', array('breakdown_comic_filename', 'get_subcomic_directory', 'get_all_comic_categories', 'build_comic_uri'));
    $comicpress_manager->expects($this->never())->method('read_information_and_check_config');
    unset($adm->broken_down_comic_files);
    $comicpress_manager->comic_files = array("2009-01-01.jpg");
    $comicpress_manager->expects($this->once())->method('breakdown_comic_filename')->with("2009-01-01.jpg")->will($this->returnValue(array('date' => '2009-01-01')));
    $comicpress_manager->expects($this->once())->method('get_subcomic_directory')->will($this->returnValue(false));
    $comicpress_manager->expects($this->once())->method('get_all_comic_categories')->will($this->returnValue(array('category_tree' => array("0/1"))));
    $adm->expects($this->once())->method('find_thumbnails_by_filename')->will($this->returnValue(array()));
    ob_start();
    $adm->manage_posts_custom_column("comic");
    $result = ob_get_clean();
    $this->assertFalse(empty($adm->broken_down_comic_files));
    
    $this->assertTrue(($xml = _to_xml($result)) !== false);
    $this->assertTrue(_node_exists($xml, '//script[@type="text/javascript"]'));
    $this->assertTrue(_node_exists($xml, '//img[@width="100"]'));
  }
  
  function testIncludeJavascript() {
    $a = $this->getMock('ComicPressManagerAdmin', array('get_plugin_path'));
    update_option('siteurl', 'http://test');
    $a->expects($this->any())->method('get_plugin_path')->will($this->returnValue('plu'));
    
    $a->_f = $this->getMock('ComicPressFileOperations', array('realpath', 'file_exists'));
    $a->_f->expects($this->once())->method('realpath')->will($this->returnValue('/site/js'));
    $a->_f->expects($this->once())->method('file_exists')->with('/site/js/minified-test.js')->will($this->returnValue(false));
    
    ob_start();
    $a->include_javascript("test.js");
    $result = ob_get_clean();
    $this->assertTrue(strpos($result, "http://test/plu/js/test.js") !== false);
    
    $a->_f = $this->getMock('ComicPressFileOperations', array('realpath', 'file_exists', 'filemtime'));
    $a->_f->expects($this->once())->method('realpath')->will($this->returnValue('/site/js'));
    $a->_f->expects($this->once())->method('file_exists')->with('/site/js/minified-test.js')->will($this->returnValue(true));
    $a->_f->expects($this->at(2))->method('filemtime')->with('/site/js/minified-test.js')->will($this->returnValue(1));
    $a->_f->expects($this->at(3))->method('filemtime')->with('/site/js/test.js')->will($this->returnValue(2));
    
    ob_start();
    $a->include_javascript("test.js");
    $result = ob_get_clean();
    $this->assertTrue(strpos($result, "http://test/plu/js/test.js") !== false);
    
    $a->_f = $this->getMock('ComicPressFileOperations', array('realpath', 'file_exists', 'filemtime'));
    $a->_f->expects($this->once())->method('realpath')->will($this->returnValue('/site/js'));
    $a->_f->expects($this->once())->method('file_exists')->with('/site/js/minified-test.js')->will($this->returnValue(true));
    $a->_f->expects($this->at(2))->method('filemtime')->with('/site/js/minified-test.js')->will($this->returnValue(12));
    $a->_f->expects($this->at(3))->method('filemtime')->with('/site/js/test.js')->will($this->returnValue(1));
    
    ob_start();
    $a->include_javascript("test.js");
    $result = ob_get_clean();
    $this->assertTrue(strpos($result, "http://test/plu/js/minified-test.js") !== false);
  }
  
  function testGetBackupFiles() {
    global $comicpress_manager;
    
    $comicpress_manager = (object)array('config_filepath' => '/test/test2.php');
    $this->adm->_f = $this->getMock('ComicPressFileOperations');
    $this->adm->_f->expects($this->once())->method("glob")->with("/test/comicpress-config.php.*")->will($this->returnValue(array()));
    
    $this->assertTrue(count($this->adm->get_backup_files()) == 0);
    
    $this->adm->_f = $this->getMock('ComicPressFileOperations');
    $this->adm->_f->expects($this->once())->method("glob")->with("/test/comicpress-config.php.*")->will($this->returnValue(array("/test/comicpress-config.php.meow", "/test/comicpress-config.php.12345")));
    
    $this->assertEquals(array('12345'), $this->adm->get_backup_files());
  }
  
  function testHandleWarnings() {
    global $comicpress_manager;
    
    foreach (array('messages', 'warnings') as $type) {
      $comicpress_manager = (object)array($type => array('test'), 'show_config_editor' => false);
      
      ob_start();
      $this->assertTrue($this->adm->handle_warnings());
      $result = ob_get_clean();
      $this->assertTrue(!empty($result), $type);
      $this->assertTrue(($xml = _to_xml($result)) !== false);
      $this->assertTrue(_node_exists($xml, '//div[@id="cpm-' . $type . '"]'));

      $this->assertFalse(strpos($result, "You won't be able") !== false);
      $this->assertFalse(strpos($result, "Debug info") !== false);
    }
    
    $adm = $this->getMock('ComicPressManagerAdmin', array('edit_config', 'get_backup_files'));
    $adm->expects($this->any())->method('edit_config');
    $adm->expects($this->any())->method('get_backup_files')->will($this->returnValue(array()));
    
    $comicpress_manager = $this->getMock('ComicPressManager', array('get_subcomic_directory'));
    $comicpress_manager->errors = array('test');
    $comicpress_manager->show_config_editor = false;
    $comicpress_manager->config_method = "";
    
    ob_start();
    $this->assertFalse($adm->handle_warnings());
    $result = ob_get_clean();
    
    $this->assertTrue(strpos($result, "You won't be able") !== false);
    $this->assertTrue(strpos($result, "Debug info") !== false);
    
    $comicpress_manager = $this->getMock('ComicPressManager', array('get_subcomic_directory'));
    $comicpress_manager->errors = array('test');
    $comicpress_manager->show_config_editor = true;
    $comicpress_manager->config_method = "";
    
    add_category(1, (object)array('name' => 'Test'));
    
    ob_start();
    $this->assertFalse($adm->handle_warnings());
    $result = ob_get_clean();
    
    $this->assertTrue(strpos($result, "You won't be able") !== false);
    $this->assertTrue(strpos($result, "<td>Test</td>") !== false);    
    $this->assertTrue(strpos($result, '<td align="center">1</td>') !== false);    

    $adm = $this->getMock('ComicPressManagerAdmin', array('edit_config', 'get_backup_files'));
    $adm->expects($this->never())->method('edit_config');
    $adm->expects($this->once())->method('get_backup_files')->will($this->returnValue(array('12345')));

    $comicpress_manager = $this->getMock('ComicPressManager', array('get_subcomic_directory'));
    $comicpress_manager->errors = array('test');
    $comicpress_manager->show_config_editor = false;
    $comicpress_manager->can_write_config = true;
    $comicpress_manager->config_method = "comicpress-config.php";
    
    ob_start();
    $this->assertFalse($adm->handle_warnings());
    $result = ob_get_clean();
    
    $this->assertFalse(strpos($result, "You won't be able") !== false);
    $this->assertTrue(strpos($result, "Some backup") !== false);
    $this->assertTrue(strpos($result, '<option value="12345">') !== false);
    $this->assertTrue(strpos($result, "Debug info") !== false);
  }

  function testSetupAdminMenu() {
    global $comicpress_manager, $plugin_page, $wp_test_expectations, $pagenow;
    
    $comicpress_manager = $this->getMock('ComicPressManager', array('read_information_and_check_config'));
    $comicpress_manager->expects($this->any())->method('read_information_and_check_config');
    
    $plugin_page = "meow";
    
    $this->adm->setup_admin_menu();
    
    foreach (array(
      array('menu', 'ComicPress', '_index_caller'),
      array('submenu', 'Upload', '_index_caller'),
      array('submenu', 'Import', '_import_caller'),
      array('submenu', 'Bulk Edit', '_bulk_edit_caller'),
      array('submenu', 'Storyline Structure', '_storyline_caller'),
      array('submenu', 'Change Dates', '_dates_caller'),
      array('submenu', 'ComicPress Config', '_comicpress_config_caller'),
      array('submenu', 'Manager Config', '_manager_config_caller'),
    ) as $info) {
      list ($type, $name, $function) = $info;
      $found = false;
      foreach ($wp_test_expectations['pages'] as $page) {
        if ($page['menu_title'] == $name) {
          switch ($type) {
            case "menu":
              $this->assertEquals("", $page['parent']);
              break;
            case "submenu":
              $this->assertNotEquals("", $page['parent']);
              break;
          }
          $this->assertEquals(array($this->adm, $function), $page['function'], "callback for ${name} not set");
          $found = true;
          break;
        }
      }
      if (!$found) {
        $this->assertFalse(true, "${name} not found");
      }
    }
    
    $this->assertFalse(_did_wp_enqueue_script("prototype"));
    
    _reset_wp();
    
    $pagenow = "post.php";
    $this->adm->setup_admin_menu();
    $this->assertFalse(_did_wp_enqueue_script("prototype"));
    
    _reset_wp();

    $_REQUEST['action'] = "edit";
    $this->adm->setup_admin_menu();
    $this->assertTrue(_did_wp_enqueue_script("prototype"));

    _reset_wp();
    $pagenow = "";
    $_REQUEST['action'] = "";
    $plugin_page = realpath(dirname(__FILE__) . '/../classes/ComicPressManagerAdmin.php');
    
    $this->adm->setup_admin_menu();
    $this->assertTrue(_did_wp_enqueue_script("prototype"));
  }
}

?>