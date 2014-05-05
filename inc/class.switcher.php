<?php
	// ------------------------------------------------------------------------
	// DEVICE THEME SWITCHER CONTROLLER CLASS 
	//		+ This class is instantiated in dts_controller.php on each WordPress load (only public, nothing is instantiated within the admin)
	// ------------------------------------------------------------------------
	class DTS_Switcher {
		public function __construct() {
			//Retrieve the admin's saved device theme
			$this->retrieve_saved_device_themes();
			//Detect the user's device			
			$this->device = $this->detect_device();
			//Deliver theme
			$this->deliver_theme();
		}//__construct
		// ------------------------------------------------------------------------
		// RETRIEVE DEVICE THEME SELECTION SET BY ADMIN
		// ------------------------------------------------------------------------
		public function retrieve_saved_device_themes () {			
		    //The theme option is a url encoded string containing 3 values for name, template, and stylesheet
		    //See the ->active_theme array below for an idea of what is within each 
		    parse_str(get_option('dts_handheld_theme'), $this->handheld_theme);
		    parse_str(get_option('dts_tablet_theme'), $this->tablet_theme);
		    parse_str(get_option('dts_low_support_theme'), $this->low_support_theme);
		    //Retrieve the current active theme
		    $this->active_theme = array(
		    	'name' => get_option('current_theme'),
		    	'template' => get_option('template'),
				'stylesheet' => get_option('stylesheet')
		   	);
		}//retrieve_saved_device_themes
		// ------------------------------------------------------------------------
		// DEVICE DETECTION
		// ------------------------------------------------------------------------
		public function detect_device () {
			//Default is active (default computer theme set by the admin) until it's overridden
			$device = 'active';
			//Give the handheld theme to any low_support device
			//UNLESS one has been set in the admin already
			$low_support_device = 'handheld' ;
			$low_support_theme = get_option('dts_low_support_theme');
			if (!empty($low_support_theme) && is_array($low_support_theme)) : 
				if (isset($low_support_theme['name'])) : 
					if (!empty($low_support_theme['name'])) : 
						//Detect if the device is a low support device (poor css and javascript rendering / older devices)
						$low_support_device = 'low_support' ;
					endif;
				endif;
			endif;

			//Check for Varnish Device Detect: https://github.com/varnish/varnish-devicedetect/
			//Thanks to Tim Broder for this addition! https://github.com/broderboy | http://timbroder.com/
			if (isset($_SERVER['HTTP_X_UA_DEVICE'])) :
				if (in_array($_SERVER['HTTP_X_UA_DEVICE'], array('mobile-iphone', 'mobile-android', 'mobile-firefoxos', 'mobile-smartphone', 'mobile-generic'))) :
					$device = 'handheld' ;
				elseif (in_array($_SERVER['HTTP_X_UA_DEVICE'], array('tablet-ipad', 'tablet-android'))) :
                    $device = 'tablet' ;
                endif;
			else : //DEFAULT ACTION - Use MobileESP to sniff the UserAgent string
				//Include the MobileESP code library for acertaining device user agents
				include_once('mobile-esp.php');
				//Setup the MobileESP Class
				$ua = new uagent_info;
				//Detect if the device is a handheld
				if ($ua->DetectSmartphone() || $ua->DetectTierRichCss()) $device = 'handheld' ;
				//Detect if the device is a tablet
				if ($ua->DetectTierTablet() || $ua->DetectKindle() || $ua->DetectAmazonSilk()) $device = 'tablet' ;
				//Detect if the device is a low_support device (poor javascript and css support / text-only)
				if ($ua->DetectBlackBerryLow() || $ua->DetectTierOtherPhones()) $device = $low_support_device ;
			endif;
			//Return the user's device
			return $device ;
		}//deliver_theme_to_device
		// ------------------------------------------------------------------------
		// THEME DELIVER LOGIC
		// ------------------------------------------------------------------------
		//Called via add_filter('template', array('DTS_Switcher', 'deliver_template'), 10, 0); in dts_controller.php
		public function deliver_theme () {
			$this->theme_override = $requested_theme = "";

			//Determine the 'slug' of the website name
			//we'll use this for the cookie name so that it refernces the website not dts
			$cookie_name = get_bloginfo('sitename');
			$cookie_name = preg_replace('/[^a-zA-Z0-9_%\[().\]\\/-]/s', '', $cookie_name); #remove special characters
			$cookie_name = str_replace(' ', '-', $cookie_name); #change spaces to hyphens
			$cookie_name = strtolower($cookie_name); #lowercase everything
			$cookie_name = $cookie_name . '-alternate-theme';

			//Is the user requesting a theme override?
			//This is how users can 'view full website' and vice versa
			if (isset($_GET['theme'])) : //i.e. site.com?theme=active
				//Clean the input data we're testing against
				$requested_theme = $_GET['theme'];				
				//Does the requested theme match the detected device theme?
				if ($requested_theme == $this->device) : 
					//The default/active theme is given back and their cookie is going to be removed
					//this condition typically exsits when someone is navigating "Back to Mobile"
					setcookie($cookie_name, "", 1, COOKIEPATH, COOKIE_DOMAIN, false);
				else : 
					//No it does not
					//this condition generally means that the user has just clicked "View full website"
					//Kill the request if it isn't valid, i.e. don't try to load ?theme=fooeybear unless it really exists
					if (isset($this->{$requested_theme . "_theme"})) :
						//Only proceed if $this->requested_theme is not empty, hence it's a valid theme
						if (!empty($this->{$requested_theme . "_theme"})) : 							
							//Retrieve the stored cookie lifespan
							//chosen in Appeance > Device Themes 
							$cookie_lifespan = get_option('dts_cookie_lifespan');
							//Build an array of the requested theme settings we'll need to know about when
							//the user returns to the site (or navigates to another page)
							//this way we can load the theme they previously requested
							$cookie_settings = array('theme' => $requested_theme, 'device' => $this->device);
							//Set a cookie in the users browser to identify the theme they've requested
							setcookie($cookie_name, json_encode($cookie_settings), time() + $cookie_lifespan, COOKIEPATH, COOKIE_DOMAIN, false);
							//Return the requested
							$this->theme_override = $requested_theme;
						endif;
					endif;
				endif;
			else :			
				//there is no new override being requested
				//Check if there is an already existant override stored in a cookie
				if (isset($_COOKIE[$cookie_name])) : 
					//The requested theme is an array so we stored it as a json encoded string
					//lets decode that so we can see whats in it
					//Note: once decoded we'll be working with an object not an array
					$_COOKIE[$cookie_name] = json_decode($_COOKIE[$cookie_name]);
					if (isset($_COOKIE[$cookie_name]->theme)) : 
						//Kill the request if it isn't valid
						if (isset($this->{$_COOKIE[$cookie_name]->theme . "_theme"})) : 
							//allow the override to continue
							$this->theme_override = $_COOKIE[$cookie_name]->theme;
						endif;
					endif;
				endif;
			endif;
			//If none of the above conditions triggered the user is given their device-assigned theme
		}//deliver_theme
		// ------------------------------------------------------------------------------
		// CALLBACK FUNCTION FOR: add_filter('template', array('device_theme_switcher', 'deliver_handheld_template')); located in dts_controller.php
		// ------------------------------------------------------------------------------
		static function deliver_template(){
			return DTS_Switcher::deliver_theme_file('template');
		} //deliver_template
		// ------------------------------------------------------------------------------
		// CALLBACK FUNCTION FOR: add_filter('stylesheet', array('device_theme_switcher', 'deliver_handheld_stylesheet')); located in dts_controller.php
		// ------------------------------------------------------------------------------
		static function deliver_stylesheet(){
			return DTS_Switcher::deliver_theme_file('stylesheet');
		} //deliver_stylesheet
		// ------------------------------------------------------------------------------
		// Return a theme file, template or stylesheet
		// ------------------------------------------------------------------------------
		static function deliver_theme_file ($file) {
			global $dts; //see the dts::__contruct for a walkthrough on how this object is created
			//Update the active theme setting (so that other plugins can modify pre_option_template or pre_option_stylesheet)
		    $dts->active_theme = array(
		    	'name' => get_option('current_theme'),
		    	'template' => get_option('template'),
				'stylesheet' => get_option('stylesheet')
		   	);

			if (!empty($dts->{$dts->theme_override . "_theme"})) : return $dts->{$dts->theme_override . "_theme"}[$file]; 
			elseif (!empty($dts->{$dts->device . "_theme"})) : return $dts->{$dts->device . "_theme"}[$file]; 
			else : return $dts->active_theme[$file] ; endif;
		}
		// ------------------------------------------------------------------------
		// SWITCH THEMES HTML LINK OUTPUT
		// ------------------------------------------------------------------------
		public static function build_html_link ($link_type, $link_text = "Return to Mobile Website", $css_classes = array(), $echo = true) {
			global $dts;
			$html_output = $target_theme = "";
			//The link_type will either be 'active' or 'device'
			//'active' pertains to the link for devices to 'view the full website'\'active' theme
			//'device' pertains to the link for devices which viewed the full website to go 'Back to the Mobile Theme'
			switch ($link_type) : 
				case 'active' : 
					//add a css class to help identify the link type for developers to style the output how they please
					array_unshift($css_classes, 'to-full-website');
					//determine if the 'View Full Website' link should be displayed or not
					if ((($dts->device != 'active') || ($dts->device == 'active' && !empty($dts->theme_override))) && ## don't show it if the user receives the active theme by default
						$dts->theme_override != 'active' && ## don't show it if the user has requested the active theme
						!empty($dts->{$dts->device . "_theme"})) ## don't show it if the device theme does not exist, and the user is currently viewing the active theme
							$target_theme = "active"; ## display the link under all other conditions
				break;
				case 'device' : 
					//add a css class to help identify the link type for developers to style the output how they please
					array_unshift($css_classes, 'back-to-mobile');
					//check for a current session amd start it up if one doesn't exist
		            if (session_id() == '') : @session_start(); endif;
		            //check for the dts array within session (this indicates the user has requested an alternate theme)
					if (isset($_SESSION['device-theme-switcher']) && $dts->device != 'active') ## only show the link to return if the user is not viewing their default theme (they've requested another theme)
						$target_theme = $dts->device; ## display the link under all other conditions
				break;
			endswitch;
			if (!empty($target_theme)) : ## only output the html link if the above logic determines if a link should be shown or not
				array_unshift($css_classes, 'dts-link'); ## ensure 'dts-link' is the first css class in the link
				//Build the HTML link
				$protocol = !empty($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http';
				$html_output = "<a href='$protocol://" . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?') . "?theme=$target_theme' title='$link_text' class='" . implode(' ', $css_classes) . "'>$link_text</a>\n";
			endif; 
			if ($echo) : echo $html_output; ## Echo the HTML link
			else : return $html_output; endif; ## Return the HTML link
		}
	} //END DTS_Switcher Class
