<?php
/*
Plugin Name: Brilliant Easy Exclude Posts (BEEP)
Description: Hide posts from public view so users need the direct link (but not a password) to view the hidden posts. Activate this feature with a simple checkbox on the editor screen. 
Version: 1.0
Author: bGentry
Author URI: http://bryangentry.us
Plugin URI: http://bryangentry.us/brilliant-easy-exclude-posts-plugin/
License: GPL2
*/



add_action( 'admin_init', 'BEEP_settings_init' );

function BEEP_settings_init() {
register_setting( 'reading', 'BEEP-double-exclude' ); 

register_setting( 'reading', 'BEEP-allow-search-engines' ); 

add_settings_section( 'BEEPsettings', 'Brilliant Easy Exclude Posts settings', 'BEEPsettingsSection', 'reading' );


add_settings_field( 'BEEP-double-exclude', 'Show links to hidden posts on hidden posts?', 'BEEPsettings_double_exclude', 'reading', 'BEEPsettings' );
add_settings_field( 'BEEP-allow-search-engines', 'Allow search engines to index posts that you have marked to be excluded or hidden?', 'BEEPsettings_search_engines', 'reading', 'BEEPsettings');
}

function BEEPsettingsSection( $args ) {
	echo "<p>Brilliant Easy Exclude Posts allows you to hide selected posts so that they do not shop up in your blog feeds, widgets, etc.</p>
	<p><small>Like exculding posts? Please consider <a href='https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=T5537T362UF3L' target='_blank'>a donation to support this plugin.</a> </small></p>";
}

function BEEPsettings_double_exclude() {
	echo '<input name="BEEP-double-exclude" id="BEEP-double-exclude" type="checkbox" value="1" class="code" ' . checked( 1, get_option( 'BEEP-double-exclude' ), false ) . ' /> If you check this box, then an excluded post can appear in recent post widgets and other navigation features when another excluded post is being displayed. Leave it blank to leave excluded posts completely hidden.';
}

function BEEPsettings_search_engines() {
	echo '<input name="BEEP-allow-search-engines" id="BEEP-allow-search-engines" type="checkbox" value="1" class="code" ' . checked( 1, get_option( 'BEEP-allow-search-engines' ), false ) . ' /> By default, a hidden post will ask search engines to not index hidden pages. Check this box if you would like to allow search engines to index pages that you select to hide from news feeds and other places on your site.';
}

add_filter ( 'get_next_post_where', 'hide_hidden_adjacent_post' );
add_filter ( 'get_previous_post_where', 'hide_hidden_adjacent_post');
function hide_hidden_adjacent_post($where) {
	global $wpdb;
	return $where . " AND p.ID NOT IN ( SELECT post_id FROM $wpdb->postmeta WHERE ($wpdb->postmeta.post_id = p.ID ) AND $wpdb->postmeta.meta_key = 'bgExclude'  )";
}

function bgExclude_remove($query){

	if ( is_admin() ) {
		return $query;
	}
	if ( !is_single() ) {
		$query->set( 'meta_query', array(

			array(
			'key' => 'bgExclude',
             'compare' => 'NOT EXISTS'
        )

    ));
} else {
		global $post;
		if ( isset ( $post) ) {
		
		$hidden = get_post_meta( $post->ID, 'bgExclude', true );
			if ( get_option( 'BEEP-double-exclude' ) == "1" ) {		
				if ( $hidden == "1" ) {
					return $query;
				} 
			}
		
		if ( $hidden == "1" ) {
			if ( get_option( 'BEEP-allow-search-engines' ) !== "1" ) {
						add_action('wp_head', 'beep_add_nofollow_link');
				}
			}
			
			$query->set( 'meta_query', array(
			array(
			'key' => 'bgExclude',
             'compare' => 'NOT EXISTS'
			)));
			
		}
			 
}
return $query;
}

function beep_add_nofollow_link() {
	echo '<meta name="robots" content="noindex, nofollow">';
}

add_action("pre_get_posts","bgExclude_remove");

add_action( 'add_meta_boxes', 'bgExclude_add_meta_boxes' );
function bgExclude_add_meta_boxes() {

add_meta_box( 'bgExcludeBox', 'Exclude this post?', 'bgExcludeBox_function', 'post', 'side',
         'high' );
		 }
		 
function bgExcludeBox_function() {
	global $post;
	$current_value = get_post_meta ( $post->ID, 'bgExclude', true);
	wp_nonce_field( 'bgExclude_inner_custom_box', 'bgExclude_inner_custom_box_nonce' );
	$checked = ( $current_value == "1" ) ? ' checked="checked"' : '';
	?>
		<p>Exclude this? from news feeds, blog index, post navigation, searches, etc?<input type="checkbox" name="bgExclude" value="1"<?php echo $checked; ?>></input> <small>Make this post visible ONLY to readers with a link.</small></p>
		<p><small>Like exculding posts? Please consider <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=T5537T362UF3L" target="_blank">a donation to support this plugin.</a> </small></p>
	<?php
}

function save_bgExcludeData( $post_id ) {

  /*
   * We need to verify this came from the our screen and with proper authorization,
   * because save_post can be triggered at other times.
   */

  // Check if our nonce is set.
  if ( ! isset( $_POST['bgExclude_inner_custom_box_nonce'] ) )
    return $post_id;
  $nonce = $_POST['bgExclude_inner_custom_box_nonce'];
  // Verify that the nonce is valid.
  if ( ! wp_verify_nonce( $nonce, 'bgExclude_inner_custom_box' ) )
      return $post_id;
  // If this is an autosave, our form has not been submitted, so we don't want to do anything.
  if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
      return $post_id;
  // Check the user's permissions.
  if ( 'page' == $_POST['post_type'] ) {
    if ( ! current_user_can( 'edit_page', $post_id ) )
        return $post_id;
  } else {

    if ( ! current_user_can( 'edit_post', $post_id ) )
        return $post_id;
  }

  /* OK, its safe for us to save the data now. */

  // Sanitize user input.
  $mydata = $_POST['bgExclude'];
	if ( $mydata == "1" ) {
  // Update the meta field in the database.
		update_post_meta( $post_id, 'bgExclude', $mydata );
	}
	else {
		delete_post_meta( $post_id, 'bgExclude' );
	}
}
add_action( 'save_post', 'save_bgExcludeData' );






?>