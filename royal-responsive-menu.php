<?php 

/*
Plugin Name: Royal Responsive Menu
Plugin URI: http://royaltechbd.com
Description: Turn your menu into a select box at small viewport sizes
Version: 1.0.0
Author: Mehdi Akram
Author URI: http://shamokaldarpon.com
License: GPLv2
Copyright 2011-2013 Mehdi Akram, Royal Technologies http://royaltechbd.com (email : mehdi@royaltechbd.com) 
*/

define( 'RESPONSIVE_SELECT_MENU_VERSION', '1.0.0' );
define( 'RESPONSIVE_SELECT_MENU_SETTINGS', 'royal-responsive-menu' );

require_once( 'lib/royalmenu.class.php' );		//Royal Responsive Options Panel

class ResponsiveMenuSelect{

	private $enabled;
	private $enabled_determined;
		
	function __construct(){

		$this->settings = $this->optionsMenu();
		$this->enabled_determined = false;
		
		if( is_admin() ){
			
		}
		else{
			add_action( 'plugins_loaded' , array( $this , 'init' ) );
		}
	}
	
	function init(){

		$this->loadAssets();
		
		//Filters
		add_filter( 'wp_nav_menu_args' , array( $this , 'responsiveSelectAddFilter' ), 2100 );  	//filters arguments passed to wp_nav_menu
		
		add_filter( 'wp_nav_menu_args' , array( $this , 'responsiveSelectFilter' ), 2200 );			//second call, to print select menu
		
	}

	/**
	 * Determine whether we should load the responsive Menu on these pages 
	 * and cache the result.
	 */
	function isEnabled(){

		if( $this->enabled_determined ) return $this->enabled;

		$this->enabled_determined = true;
		$this->enabled = false;

		if( !$this->settings->op( 'display_only' ) ){
			$this->enabled = true;
		}
		else{
			$list = $this->settings->op( 'display_only' );
			$list = str_replace( ' ', '', $list );
			$ids = explode( ',' , $list );

			global $post;
			if( $post && in_array( $post->ID , $ids ) ) $this->enabled = true;
			else $this->enabled = false;
		}
		return $this->enabled;
	}

	/**
	 * Determine whether this particular menu location should be activated
	 */
	function isActivated( $args ){

		//Activate All?
		if( $this->settings->op( 'activate_theme_locations_all' ) ){
			return true;
		}

		//Activate this theme_location specifically?
		if( isset( $args['theme_location'] ) ){
			$location = $args['theme_location'];
			$active_theme_locations = $this->settings->op( 'active_theme_locations' );

			if( is_array( $active_theme_locations ) && in_array( $location, $active_theme_locations ) ){
				return true;
			}
		}
		return false;
	}


	function loadAssets(){
		
		if( !is_admin() ){
			add_action( 'wp_print_styles' , array( $this , 'loadCSS' ) );		
			add_action( 'wp_head', array( $this  , 'insertHeaderCode' ), 110 );			
		}
				
	}

	function loadCSS(){
		if( $this->isEnabled() ) wp_enqueue_script( 'jquery' );	
	}
	
	function insertHeaderCode(){
		if( $this->isEnabled() ){
		?>
		
<!-- Responsive Select CSS 
================================================================ -->
<style type="text/css" id="responsive-select-css">
.responsiveSelectContainer select.responsiveMenuSelect, select.responsiveMenuSelect{
	display:none;
}

@media (max-width: <?php echo $this->settings->op( 'max-menu-width' ); ?>px) {
	.responsiveSelectContainer{
		border:none !important;
		background:none !important;
		box-shadow:none !important;
	}
	.responsiveSelectContainer ul, ul.responsiveSelectFullMenu, #megaMenu ul.megaMenu.responsiveSelectFullMenu{
		display: none !important;
	}
	.responsiveSelectContainer select.responsiveMenuSelect, select.responsiveMenuSelect { 
		display: inline-block; 
		width:100%;
	}
}	
</style>
<!-- end Responsive Select CSS -->



<!-- Responsive Select JS
================================================================ -->
<script type="text/javascript">
jQuery(document).ready( function($){
	$( '.responsiveMenuSelect' ).change(function() {
		var loc = $(this).find( 'option:selected' ).val();
		if( loc != '' && loc != '#' ) window.location = loc;
	});
	//$( '.responsiveMenuSelect' ).val('');
});
</script>
<!-- end Responsive Select JS -->
		
		
		
<?php
		}
	}

	
	function responsiveSelectAddFilter( $args ){

		if( $this->isEnabled() && $this->isActivated( $args ) ){
		
			//Don't add it twice (when it gets called again by selectNavMenu() )
			if( isset( $args['responsiveMenuSelect'] ) && $args['responsiveMenuSelect'] == true ) {
				return $args;
			}
			
			$selectNav = $this->selectNavMenu( $args );
			
			$args['container_class'].= ' responsiveSelectContainer';	
			$args['menu_class'].= ' responsiveSelectFullMenu';

			//This line would add a container if it doesn't exist, but has the potential to break certain theme menus
			//if( $args['container'] != 'nav' ) $args['container'] = 'div';	//make sure there's a container to add class to
			
			$args['items_wrap']	= '<ul id="%1$s" class="%2$s">%3$s</ul>'.$selectNav;

		}

		return $args;

	}
	
	function selectNavMenu( $args ){
		
		$args['responsiveMenuSelect'] = true;
		
		$select = wp_nav_menu( $args );
		
		return $select;
	}
	
	function responsiveSelectFilter( $args ){

		if( $this->isEnabled() ){

			if( !isset( $args['responsiveMenuSelect'] ) ) return $args;

			$itemName = $this->settings->op( 'first_item' );
			$selected = $this->settings->op( 'current_selected' ) ? '' : 'selected="selected"';
			$firstOp = '<option value="" '.$selected.'>'.$itemName.'</option>';

			$args['container'] = false;
			$args['menu_class'] = 'responsiveMenuSelect';
			$args['menu_id'] = '';
			$args['walker'] = new ResponsiveSelectWalker();
			$args['echo'] = false;
			$args['items_wrap'] = '<select class="%2$s">'.$firstOp.'%3$s</select>';
			
			$args['depth'] = $this->settings->op( 'max-menu-depth' );

		}
		
		return $args;
		
	}

	/*
	 * Create the RoyalMenu RoyalOptions Panel and Settings object
	 */
	function optionsMenu(){
	
		$RoyalOps = new ResponsiveMenuSelectOptions( 
			RESPONSIVE_SELECT_MENU_SETTINGS, 
			
			//Menu Page
			array(
				'parent_slug' 	=> 'themes.php',
				'page_title'	=> 'Royal Responsive Menu',
				'menu_title'	=> 'Royal Responsive Menu',
				'menu_slug'		=> 'royal-responsive-menu',
			),
			
			//Links
			array()
			
		);
		
		
		
		/*
		 * Basic Config Panel
		 */
		$basic = 'basic-config';
		$RoyalOps->registerPanel( $basic, 'Basic Configuration' );
		
		$RoyalOps->addHidden( $basic , 'current-panel-id' , $basic );


		$RoyalOps->addTextInput( $basic,
					'max-menu-width',
					'Maximum Menu Width',
					'Show the select box when the viewport is less than this width',
					768,
					'Royal-minitext',
					'px'
					);

		$RoyalOps->addTextInput( $basic,
					'max-menu-depth',
					'Menu Depth Limit',
					'The maximum number of levels of menu items to include in the select menu.  Set to 0 for no limit.',
					0,
					'Royal-minitext',
					''
					);

		$RoyalOps->addTextInput( $basic,
					'spacer',
					'Sub Item Spacer',
					'The character to use to indent sub items.',
					'&ndash; ',
					'Royal-minitext',
					''
					);

		$RoyalOps->addCheckbox( $basic,
					'exclude-hashes',
					'Exclude Items Without Links',
					'Exclude any items where the URL is set to "#" or blank',
					'on'
					);

		$RoyalOps->addTextInput( $basic,
					'first_item',
					'First Item Name',
					'Text to display for the first "dummy" item.',
					'&rArr; Menu',
					'',
					''
					);

		$RoyalOps->addCheckbox( $basic,
					'current_selected',
					'Show currently selected item',
					'Enable to show the currently selected item, rather than the first "dummy" item, when the page loads.',
					'on'
					);

		$RoyalOps->addSubHeader( $basic, 
					'activate_theme_locations_header',
					'Activate Theme Locations'
					);

		$RoyalOps->addCheckbox( $basic,
					'activate_theme_locations_all',
					'Activate All Theme Locations',
					'Apply the responsive select menu to all menus',
					'on'
					);

		$RoyalOps->addChecklist( $basic,
					'active_theme_locations', 
					'Selectively Activate Theme Locations',
					'Disable the above and activate only the theme locations you want.  These theme locations correspond to the Theme Locations Meta Box in Appearance > Menus',
					'get_registered_nav_menus'
					);



		$advanced = 'advanced-config';
		$RoyalOps->registerPanel( $advanced, 'Advanced Settings' );

		$RoyalOps->addTextInput( $advanced,
					'display_only',
					'Enable only on',
					'IDs of pages to enable responsive select menu on.  Other pages will use the standard theme menu.  Enter as a comma-separated list.',
					'',
					'',
					''
					);





		$ss = 'ss-config';
		$RoyalOps->registerPanel( $ss, 'More from Royal Technologies' );

		$RoyalOps->addCustom( $ss, 'ss_products' , 'ResponsiveMenuSelect::sevenRoyal_showcase' );


		return $RoyalOps;
	}


	function getSettings(){
		return $this->settings;
	}

	static function sevenRoyal_showcase(){
	
		$html = '
			<div class="social_media">
				<a target="_blank" href="https://www.twitter.com/mehdiakram" class="ss-twitter"></a> 
				<a target="_blank" href="https://www.facebook.com/mehdiakram" class="ss-facebook"></a> 
			</div>

			<div class="ss-infobox Royal-infobox">
				Like this plugin?  Check out even more from <a target="_blank" href="http://royaltechbd.com">Royal Technologies</a>
			</div>


		';

		return $html;

	}
	

}

$responsiveMenuSelect = new ResponsiveMenuSelect();





class ResponsiveSelectWalker extends Walker_Nav_Menu{

	private $index = 0;
	protected $menuItemOptions;
	protected $noRoyalOps;
	
	function start_lvl( &$output, $depth = 0 , $args = array() ) {
		$indent = str_repeat( "\t", $depth );
		//$output .= "\n$indent<ul class=\"sub-menu sub-menu-".($depth+1)."\">\n";
	}
	
	function end_lvl(&$output, $depth = 0 , $args = array() ) {
		$indent = str_repeat("\t", $depth);
		//$output .= "$indent</ul>\n";
	}
	
	function start_el( &$output, $item, $depth = 0, $args = array(), $current_object_id = 0 ){
		
		global $responsiveMenuSelect;
		global $wp_query;
		$indent = ( $depth ) ? str_repeat( "\t", $depth ) : '';
		$dashes = ( $depth ) ? str_repeat( $responsiveMenuSelect->getSettings()->op( 'spacer' ), $depth ) : '';	//"&ndash; "

		$class_names = $value = '';

		$classes = empty( $item->classes ) ? array() : (array) $item->classes;
		$classes[] = 'menu-item-' . $item->ID;

		$class_names = join( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args ) );
		$class_names = ' class="' . esc_attr( $class_names ) . '"';

		$id = apply_filters( 'nav_menu_item_id', 'menu-item-'. $item->ID, $item, $args );
		$id = strlen( $id ) ? ' id="' . esc_attr( $id ) . '"' : '';

		if( ( $item->url == '#' || $item->url == '' ) && $responsiveMenuSelect->getSettings()->op( 'exclude-hashes' ) ){
			return;
		}

	
		//$attributes = ! empty( $item->url )        ? ' value="'   . esc_attr( $item->url        ) .'"' : '';
		$attributes = ' value="'   . esc_attr( $item->url        ) .'"';
		
		if( $responsiveMenuSelect->getSettings()->op( 'current_selected' ) && strpos( $class_names , 'current-menu-item' ) > 0 ){
			$attributes.= ' selected="selected"';
		}
		
		$output .= $indent . '<option ' . $id . $attributes . '>';

		$item_output = $args->before;
		$item_output .= $dashes . $args->link_before . apply_filters( 'the_title', $item->title, $item->ID ) . $args->link_after;
		$item_output .= $args->after;

		$output.= str_replace( '%', '%%', $item_output );

		$output.= "</option>\n";
	}
	
	function end_el(&$output, $item, $depth = 0 , $args = array() ) {
		return;		
	}


}
