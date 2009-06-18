<!-- ComicPress details -->
<div id="comicpress-details">
	<h2 style="padding-right: 0"><?php _e('ComicPress Details', 'comicpress-manager') ?></h2>
	<ul style="padding-left: 30px; margin: 0">
	
		<?php if (function_exists('get_site_option')) { ?>
			<li><strong><?php _e("Available disk space:", 'comicpress-manager') ?></strong>
			<?php printf(__("%0.2f MB"), cpm_wpmu_get_available_disk_space() / 1048576) ?>
		<?php } ?>
		
		<li>
			<strong><?php _e('Comics folder:', 'comicpress-manager') ?></strong>
			<?php
				echo $comicpress_manager->properties['comic_folder'] . $subdir_path;
				
				printf(
					__ngettext(
						' (%d comic in folder%s)', 
						' (%d comics in folder%s)', 
						count($comicpress_manager->comic_files),
						'comicpress-manager'
					),
					count($comicpress_manager->comic_files), 
					$this->too_many_comics_message
				);
			?>
		</li>

		<?php foreach (array('archive' => __('Archive folder:', 'comicpress-manager'),
												 'rss'     => __('RSS feed folder:', 'comicpress-manager'))
									 as $type => $title) { ?>
			<li>
			  <strong><?php echo $title ?></strong>
				<?php echo $comicpress_manager->properties["${type}_comic_folder"]  . $subdir_path; ?>
				(<?php
				  if ($this->thumbnail_generation[$type] === true) {
						echo "<em>";
						  _e('generating', 'comicpress-manager');
					  echo "</em>";
					} else {
						echo '<em style="cursor: help; text-decoration: underline" title="' . implode(", ", $this->thumbnail_generation[$type]) . '">';
							_e('not generating', 'comicpress-manager');
					  echo "</em>";
					}
				?>)
			</li>
		<?php } ?>

		<?php foreach (array('comic_category' => __("Comic categories:", 'comicpress-manager'),
		                     'blog_category' => __("Blog categories:", 'comicpress-manager'))
								   as $type => $title) { ?>
			<li>
				<strong><?php echo $title ?></strong>
				<?php if ($this->{$type} === false) { ?>
				  <em>Not defined!</em>
				<?php } else { ?>
				
				<?php if (is_array($comicpress_manager->properties['comiccat'])) { ?>
					<ul>
						<?php foreach ($comicpress_manager->properties['comiccat'] as $cat_id) { ?>
							<li><a href="<?php echo get_category_link($cat_id) ?>"><?php echo get_cat_name($cat_id) ?></a>
							<?php printf(__('(ID %s)', 'comicpress-manager'), $cat_id) ?></li>
						<?php } ?>
					</ul>
				<?php } else { ?>
					<a href="<?php echo get_category_link($comicpress_manager->properties['comiccat']) ?>"><?php echo $comicpress_manager->category_info['comiccat']['name'] ?></a>
					<?php printf(__('(ID %s)', 'comicpress-manager'), $comicpress_manager->properties['comiccat']) ?>
				<?php } ?>				
				<?php } ?>

			</li>
    <?php } ?>
		
		<?php if (!function_exists('get_site_option')) { ?>
			<li><strong><?php _e("PHP Version:", 'comicpress-manager') ?></strong> <?php echo phpversion() ?>
					<?php if (substr(phpversion(), 0, 3) < 5.2) { ?>
						(<a href="http://gophp5.org/hosts"><?php _e("upgrade strongly recommended", 'comicpress-manager') ?></a>)
					<?php } ?>
			</li>
			<li>
				<strong><?php _e('Theme folder:', 'comicpress-manager') ?></strong>
				<?php $theme_info = get_theme(get_current_theme());
							if (!empty($theme_info['Template'])) {
								echo $theme_info['Template'];
							} else {
								echo __("<em>Something's misconfigured with your theme...</em>", 'comicpress-manager');
							} ?>
			</li>
			<?php if (count($comicpress_manager->detailed_warnings) != 0) { ?>
				 <li>
						<strong><?php _e('Additional, non-fatal warnings:', 'comicpress-manager') ?></strong>
						<ul>
							<?php foreach ($comicpress_manager->detailed_warnings as $warning) { ?>
								<li><?php echo $warning ?></li>
							<?php } ?>
						</ul>
				 </li>
			<?php } ?>
			<li>
				<strong><a href="#" onclick="Element.show('debug-info'); $('cpm-right-column').style.minHeight = $('cpm-left-column').offsetHeight + 'px'; return false"><?php _e('Show debug info', 'comicpress-manager') ?></a></strong> (<em><?php _e("this data is sanitized to protect your server's configuration", 'comicpress-manager') ?></em>)
				<?php echo $comicpress_manager_admin->show_debug_info() ?>
			</li>
		<?php } ?>
	</ul>
</div>