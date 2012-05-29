<?php
/*
 * creates metabox
 */

session_start();

class Gc_Integration{
	
	public static $tracker = 1;
		
	/*
	 * contains all the hooks
	 */
	static function init(){
		add_action( 'add_meta_boxes', array(get_class(), 'meta_boxes' ));
		add_action('admin_menu', array(get_class(), 'admin_menu_gc'));
		
		add_action('admin_enqueue_scripts', array(get_class(), 'js_add'));
		//add_action('admin_enqueue_style', array(get_class(), 'css_add'));
		
		//saving calender data in wp and 
		add_action('save_post', array(get_class(), 'save_post'), 10, 2);
		
		add_action('init', array(get_class(), 'authenticate'));
				
	}
	
	
	/*
	 * controlling sessions to authenticate the google calender
	 */
	static function authenticate(){
		if(isset($_GET['code'])){
			
			$gcalender = self::get_calender();
			
			$_SESSION['gcCode'] = $_GET['code'];
			$tokens = $gcalender->get_authenticated_token($_SESSION['gcCode']);
			
			self::save_tokens($tokens);			
			
			
			if(!function_exists('wp_redirect')){
				include ABSPATH . '/wp-includes/pluggable.php';
			}
			
			if(is_ssl()){
				$url = 'https://' . $_SESSION['gc_redirect_url'];
			}
			else{
				$url = 'http://' . $_SESSION['gc_redirect_url'];
			}
					
			wp_redirect($url);
			exit;
		}
				
	}
	
	/*
	 * save the refress token to be used later
	 */
	
	static function save_tokens($tokens){
		if($tokens){
			$refresstoken = $tokens->refresh_token;
						
			update_option('gc_refress_token', $refresstoken);
		}
	}


	/*
	 * function with custom hook to save the event id and calender id to the database
	 */
	static function save_event_info($event, $post, $calender){
		if($event){
			update_post_meta($post->ID, 'gc_enabled', '1');
			update_post_meta($post->ID, 'event_info', array('cal_id'=>$calender, 'event_id'=>$event->id));
		}
	}
	
	/*
	 * return the associated event for a post
	 */
	static function get_event_info($post){
		return array(
			'enabled' => get_post_meta($post->ID, 'gc_enabled', true),
			'info' => get_post_meta($post->ID, 'event_info', true)
		);
		
		
	}




	/*
	 * saving post data
	 */
	static function save_post($post_ID, $post){
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
		self::push_to_gc($post_ID, $post);
			
	}
	
	
	/*
	 * push post to the google calender
	 */
	static function push_to_gc($post_ID, $post){
		if($_POST['gc_enabled'] == '1') :
			if(self::$tracker > 1) return;
			
			$start_time = trim($_POST['gc-event-date_start']) . ' ' . trim($_POST['gc-event-time_start']);
			if(empty($_POST['gc-event-time_end'])){
				$end_time = '11:59 PM';
			}
			else{
				$end_time = $_POST['gc-event-time_end'];
			}
			$end_time = trim($_POST['gc-event-date_end']) . ' ' .$end_time;
			
			$title = self::sanitized_title(trim($_POST['gc-event-title']), $post->post_title);
			$des = trim($_POST['gc-event-description']);
			$event_start = self::sanitized_datetime($start_time);
			
			$event_end = self::sanitized_datetime($end_time);
			
			
			$event = self::set_event($title, $des, $event_start, $event_end, $_POST['gc_id']);
			
			//do_action('save_the_gc_event', $event, $post, $_POST['gc_id']);
			self:: save_event_info($event, $post, $_POST['gc_id']);
			
			self::$tracker += 1;			
		endif;
	}
	
	/*
	 * set an event to the googel calender and return the event for further use
	 */
	static function set_event($summary, $description, $event_start, $event_end, $calender_id){
		$gcalender = self::get_calender();
		self::set_accessToken();
		if(self::is_new()){
			$event = $gcalender->createEvent($_SESSION['gcToken'], $calender_id, $summary, $description, $event_start, $event_end, self::get_timezone());
		}
		else{
			$event = $gcalender->updateEvent($calender_id, $_POST['event_prev_id'], $summary, $description, $event_start, $event_end, self::get_timezone(), $_SESSION['gcToken']);
		}
		return $event;
	}
	
	/*
	 * is update or new
	 */
	static function is_new(){
		if($_POST['gc_update'] == 'Y'){
			if($_POST['gc_prev_id'] == $_POST['gc_id']){
				return false;
			}
		}
		return true;
	}



	/*
	 * some sanitizing fuction
	 */
	static function sanitized_title($et='', $pt=''){
		if(strlen($et) < 2) return $pt;
		return $et;
	}
	
	
	/*
	 * format the date and time to the google calender format
	 */
	static function sanitized_datetime($date_time){
		if(strlen($date_time) < 5){
			$date_time = trim($_POST['gc-event-date_start']) . ' ' . trim($_POST['gc-event-time_start']);
			$dt = new DateTime($date_time, new DateTimeZone(self::get_timezone()));
			$timestamp = $dt->getTimestamp() + 3600;
			$dt->setTimestamp($timestamp);
		}
		else{		
			$dt = new DateTime($date_time, new DateTimeZone(self::get_timezone()));
		}
		
		//$timestamp -= self::get_gmt_offset();
		
		return $dt->format('c');
				
	}


	/*
	 * css add
	 */
	static function css_add(){
		//date picker
		wp_register_style('query-ui-datepicker-addon_css', GCALENDERURL . '/date-time-picker/css/ui-lightness/jquery-ui-1.8.20.custom.css');
		wp_enqueue_style('query-ui-datepicker-addon_css');	
		
		//time picker
		wp_register_style('query-ui-timepicker-addon_css', GCALENDERURL . '/date-time-picker/jquery-ui-timepicker-addon.css');
		wp_enqueue_style('query-ui-timepicker-addon_css');	
		
	}




	/*
	 * js addition
	 */
	static function js_add(){
		wp_enqueue_script('jquery');
		
		//date picker
		wp_register_script('jquery-ui-datepicker-addon_js', GCALENDERURL . '/date-time-picker/js/jquery-ui-1.8.20.custom.min.js');
		wp_enqueue_script('jquery-ui-datepicker-addon_js');
		
		//time picker
		wp_register_script('jquery-ui-timepicker-addon_js', GCALENDERURL . '/date-time-picker/jquery-ui-timepicker-addon.js');
		wp_enqueue_script('jquery-ui-timepicker-addon_js');
		
				
		
		
		self :: css_add();
	}
		
	
	/*
	 * settigs page
	 */
	static function admin_menu_gc(){
		add_options_page('gc setting page', 'GClaneder', 'manage_options', 'gc_options_page', array(get_class(), 'options_page_content'));
	}
	
	/*
	 * Options page content
	 */
	static function options_page_content(){
		if($_POST['gc_saved'] == 'Y'){			
			$gc_data = array(
				'app_name' => trim($_POST['gc_app_name']),
				'client_id' => trim($_POST['gc_client_id']),
				'client_secret' =>  trim($_POST['gc_client_secret'])				
			);
			
			update_option('gc_app_info', $gc_data);
			update_option('gc_timezone', trim($_POST['gc_timezone']));
		}
		
		if($_POST['deactivate_the_Claneder'] == "Y"){
			self::deactivate_calender();
		}
		
		$gc = get_option('gc_app_info');
		$timezone = get_option('gc_timezone');
		
		include dirname(__FILE__) . '/includes/options-page.php';
	}
	
	
	/*
	 * deletes the offline access and remvoe the refresh token
	 * */
	static function deactivate_calender(){
		delete_option('gc_refress_token');
	}
	 
	
	/*
	 * returns the timezone
	 */
	static function get_timezone(){
		return get_option('gc_timezone');				
		
	}
	
	
	/*
	 * get calender access options
	 */
	static function get_calender_access_info(){
		return get_option('gc_app_info');
	}
	



	/*
	 * add meta boxes
	 */
	static function meta_boxes(){
		$post_types=get_post_types();
		foreach($post_types as $post_type){
			add_meta_box( 'gc_metabox', 'Google Calender', array(get_class(), 'the_box'), $post_type, 'advanced', 'high');
		}
	}
	
	
	/*
	 * metabox content
	 */
	static function the_box(){
		$gcalender = self::get_calender();
		global $post;
		if(self::calender_enabled()){
			self::set_accessToken();
			$calenders = $gcalender->getAllCalendars($_SESSION['gcToken']);
			
			$event_data = self::get_event_info($post);
			$event_meta = $event_data['info'];
			$enabled = $event_data['enabled'];
			$event = $gcalender->getEvent($event_meta['cal_id'], $event_meta['event_id'], $_SESSION['gcToken']);
			//var_dump($event);
			
			include dirname(__FILE__) . '/metabox/metabox.php';
		}		
		else{
			echo "<div class='wrap'>
				<h2>Calender Service is disabled! Please contact with the admin </h2>
			</div>";			
		}	

	}
	
	
	/*
	 * if the access token has expired it uses the refresh token to get a new access token
	 * */
	static function set_accessToken(){
		if(isset($_SESSION['gcToken'])){
			if($_SESSION['gcValidTime'] > time()) return;
		}
		
		$gcalender = self::get_calender();
		$tokens = $gcalender->get_new_accesstoken(self::get_refresh_token());
		
		if($tokens){
			$_SESSION['gcToken'] = $tokens->access_token;
			$_SESSION['gcValidTime'] = time() + $tokens->expires_in;
		}
				
	}
	
		
	

	/*
	 * google calelnder format to normal format 
	 */
	static function get_normalized_date($rfc){
		if(empty($rfc)) return '';
		
		$dt = new DateTime($rfc, new DateTimeZone(self::get_timezone()));
		//$rfc = strtotime($rfc) + self::get_gmt_offset();
		return $dt->format('m/d/Y');
	}
	
	static function get_normalized_time($rfc){
		if(empty($rfc)) return '';
		
		$dt = new DateTime($rfc, new DateTimeZone(self::get_timezone()));
		//$rfc = strtotime($rfc) + self::get_gmt_offset();
		return $dt->format('h:i A');
	}
	
	
	
	/*
	 * get_timezones option
	 */
	static function get_timezone_options($selected){
		$option = '';
		$zones = DateTimeZone::listIdentifiers();
		foreach($zones as $zone){
			$option .= '<option ' . selected($zone, $selected) . ' value="' . $zone . '">' . $zone . '</option>';
		}
		
		return $option;
	}
	
	/**
	 * returns the google calender
	 */
	static function get_calender(){
		$info = self::get_calender_access_info();
		$redirect_uri = get_option('siteurl') . '/wp-admin/google-calender?redirect=yes';
		$gcalender = new GCalendar($info['client_id'], $info['client_secret'], $redirect_uri);
		return $gcalender;
	}
		
	
	static function calender_enabled(){
		return self::get_refresh_token();
	}
	
	static function get_refresh_token(){
		return get_option('gc_refress_token');
	}
		
}
