<?php

class ComicPressSidebarStandard extends ComicPressView {
	function ComicPressSidebarStandard() {}
	
	function _get_subdir_path() {
		global $comicpress_manager;
		
		$this->subdir_path = '';
		if (($subdir = $comicpress_manager->get_subcomic_directory()) !== false) {
			$this->subdir_path .= '/' . $subdir;
		}	
	}
	
	function _all_comic_dates_ok() {
		global $comicpress_manager;
		
		$this->all_comic_dates_ok = true;
		$this->all_comic_dates = array();
		
		foreach ($comicpress_manager->comic_files as $comic_file) {
			if (($result = $comicpress_manager->breakdown_comic_filename(pathinfo($comic_file, PATHINFO_BASENAME))) !== false) {
				if (isset($this->all_comic_dates[$result['date']])) { $this->all_comic_dates_ok = false; break; }
				$this->all_comic_dates[$result['date']] = true;
			}
		}

    $this->too_many_comics_message = "";
    if ($this->all_comic_dates_ok) {
		  $this->too_many_comics_message = ", <em>" . __("multiple files on the same date!", 'comicpress-manager')  . "</em>";
		}
	}
	
	function render() {
		global $comicpress_manager;
		
		$this->_get_subdir_path();
		$this->_all_comic_dates_ok();
		$this->_get_thumbnail_generation_info();
		
		import($this->_partial_path("sidebar"));
	}
	
	function _get_thumbnail_generation_info() {
		global $comicpress_manager;
		
		$this->thumbnail_generation = array();
		
    foreach (array('archive', 'rss') as $type) {
		  $option = $comicpress_manager->get_cpm_option("cpm-${type}-generate-thumbnails");
			if (
				($comicpress_manager->scale_method !== false) &&
				($option == 1) &&
				($comicpress_manager->separate_thumbs_folder_defined[$type]) &&
				($comicpress_manager->thumbs_folder_writable[$type])
			) {
				$this->thumbnail_generation[$type] = true;
			} else {
				$reasons = array();

				if ($comicpress_manager->scale_method == false) { $reasons[] = __("No scaling software", 'comicpress-manager'); }
				if ($option == 0) {
					$reasons[] = __("Generation disabled", 'comicpress-manager');
				} else {
					if (!$comicpress_manager->separate_thumbs_folder_defined[$type]) { $reasons[] = __("Same as comics folder", 'comicpress-manager'); }
					if (!$comicpress_manager->thumbs_folder_writable[$type]) { $reasons[] = __("Not writable", 'comicpress-manager'); }
				}
				$this->thumbnail_generation[$type] = $reasons;
			}
		}
		return $this->thumbnail_generation;
	}
}

?>