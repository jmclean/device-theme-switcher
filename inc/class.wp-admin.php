<?php
    class DTS_Admin {
        //Display some output in the WP Admin Dashboard 'Right Now' section
        //      + Show what device these have been selected below what default theme is active
        public function right_now () { ?>
            <br />Handheld Theme <a href="<?php bloginfo('url') ?>/wp-admin/themes.php?page=device-themes"><strong><?php echo get_option('dts_handheld_theme') ?></strong></a> 
            <br />Tablet Theme <a href="<?php bloginfo('url') ?>/wp-admin/themes.php?page=device-themes"><strong><?php echo get_option('dts_tablet_theme') ?></strong></a><?php
        }//right_now
        
        // ------------------------------------------------------------------------------
        // CALLBACK MEMBER FUNCTION FOR: add_action('admin_menu', array('device_theme_switcher', 'admin_menu'));
        // ------------------------------------------------------------------------------
        public function admin_menu () {
            //Create the admin menu page
            add_submenu_page('themes.php',  __('Device Theme Switcher'), __('Device Themes'), 'manage_options', 'device-themes', array('DTS_Admin', 'generate_admin_settings_page'));
        }//admin_menu

        // ------------------------------------------------------------------------------
        // CALLED MEMBER FUNCTION FOR: if ($_POST) : $dts->update; ...
        // ------------------------------------------------------------------------------
        public function load () {
            //Unfortunetly we can't use the settings api on a subpage, so we need to check for and update any options this plugin uses
            if ($_POST) : if ($_POST['dts_settings_update'] == "true") :
                //Loop through the 3 device <select>ed <option>s in the admin form
                foreach ($_POST['dts'] as $selected_device => $chosen_theme) : 
                    if ($chosen_theme != "Use Handheld Setting") : 
                        //Update each of the 3 dts database options with a urlencoded array of the selected theme 
                        //The array contains 3 values: name, template, and stylesheet - these are all we need for use later on
                        update_option($selected_device, $chosen_theme);
                    endif;
                endforeach ; 
                //Display an admin notice letting the user know the save was successfull
                add_action('admin_notices', array('DTS_Admin', 'admin_save_settings_notice'));
            endif; endif;
        }//update
        
        // ------------------------------------------------------------------------------
        // CALLBACK MEMBER FUNCTION SPECIFIED IN: add_options_page()
        // ------------------------------------------------------------------------------
        public function generate_admin_settings_page() {
            //Gather all of the currently installed theme names so they can be displayed in the <select> boxes below
            if (function_exists('wp_get_themes')) : 
                $installed_themes = wp_get_themes();
            else :
                $installed_themes = get_themes();
            endif;

            //Loop through each of the installed themes and build an custom array of theme for use below
            foreach ($installed_themes as $theme) : 
                //Gather each theme's theme data
                //wp_get_theme was introduced in WordPress v3.4 - this check ensures we're backwards compatible
                if (function_exists('wp_get_theme')) : $theme_data = wp_get_theme( $theme['Stylesheet'] );
                else : $theme_data = get_theme_data( get_theme_root() . '/' . $theme['Stylesheet'] . '/style.css' ); endif;

                //We'll only display a theme if it is an actual / functioning theme with theme data
                if (isset($theme_data)) : 
                    //Check if the theme is a child theme
                    //In this instance the 'Template' variable will be empty and we're supposed to submit the stylesheet instead
                    if (!empty($theme_data['Template'])) : $template = $theme_data['Template'];
                    else : $template = $theme['Stylesheet']; endif;

                    //Increment $available_themes with each functional theme    
                    //We're going to output each array in the value of each theme <option> below
                    $available_themes[] = array(
                        'name' => $theme->Name,
                        'template' => $template,
                        'stylesheet' => $theme['Stylesheet']);

                    //Store the theme names so we can use array_multisort on $available_theme to sort by name
                    $available_theme_names[] = $theme->Name;
                endif;
            endforeach;

            //Alphabetically sort the theme name list for display in the selection dropdowns
            array_multisort($available_theme_names, SORT_ASC, $available_theme_names);

            //Retrieve any DTS theme options which were previously saved
            //The theme option is a url encoded string containing 3 values for name, template, and stylesheet
            parse_str(get_option('dts_handheld_theme'), $dts['themes']['handheld']);
            parse_str(get_option('dts_tablet_theme'), $dts['themes']['tablet']);
            parse_str(get_option('dts_low_support_theme'), $dts['themes']['low_support']);

            //Ensure there are default values in each of the $dts['themes']
            foreach ($dts['themes'] as $device => $theme) : 
                if (empty($theme)) : $dts['themes'][$device] = array('name' => '', 'template' => '', 'stylesheet' => ''); endif;
            endforeach ?>
            <style type="text/css">
                div.wrap.device-theme-switcher-settings table td {
                    padding: 0 5px 0 5px ;
                }
                    div.wrap.device-theme-switcher-settings select {
                        width: 155px ;
                    }
                .advanced-options-toggle, .help-and-support-toggle {
                    font-size: 0.9em ;
                    outline: none ;
                }
                .advanced-options, .help-and-support {
                    width: 806px ;
                    display: none ; /* We'll enable this via JavaScript */
                }
            </style>
            
            <div class="wrap device-theme-switcher-settings">
                <div id="icon-themes" class="icon32"><br></div>
                <h2>Device Themes<br /><br /></h2>
                <form method="post" action="<?php echo admin_url() ?>themes.php?page=device-themes">
                    <table>
                        <tr>
                            <th scope="row" align="right" width="150px">
                                <label for="dts_handheld_theme"><?php _e("Handheld Theme") ?></label>
                            </th><td>
                                <select name="dts[dts_handheld_theme]">
                                    <?php foreach ($available_themes as $theme) : ?>
                                        <option value="<?php echo build_query($theme)?>" <?php selected($theme['name'], $dts['themes']['handheld']['name']) ?>><?php echo $theme['name'] ?> &nbsp; </option>
                                    <?php endforeach ?>
                                </select>
                            </td>
                            <td><span class="description"> <?php _e("Handheld devices like Apple iPhone, Android, BlackBerry, and more.") ?></span></td>                 
                        </tr><tr>
                            <th scope="row" align="right">
                                <label for="dts_tablet_theme"><?php _e("Tablet Theme") ?> </label>
                            </th><td>
                                <select name="dts[dts_tablet_theme]">
                                    <?php foreach ($available_themes as $theme) : ?>
                                        <option value="<?php echo build_query($theme)?>" <?php selected($theme['name'], $dts['themes']['tablet']['name']) ?>><?php echo $theme['name'] ?> &nbsp; </option>
                                    <?php endforeach ?>
                                </select>
                            </td>
                            <td><span class="description"> <?php _e("Tablet devices like Apple iPad, Galaxy Tab, Kindle Fire, and more.") ?></span></td>
                        </tr><tr>
                            <th scope="row" align="right">
                                <a href="#" class="advanced-options-toggle"><?php _e("Show Advanced Options") ?></a> 
                            </th><td colspan="2"></td>
                        </tr>
                    </table>
                    <div class="advanced-options">
                        <table>
                            <tr>
                                <th scope="row" align="right" width="150px">
                                    <label for="dts_low_support_theme"><?php _e("Low-Support Theme") ?> </label>
                                </th><td>
                                    <select name="dts[dts_low_support_theme]">
                                        <option>Use Handheld Setting</option><?php 
                                        /*
                                            By default the active theme should be used
                                            if a handheld theme is set that should be used for ALL handheld devices
                                            if a low support theme is set, use that one
                                        */
                                        //print_r($dts);

                                        //Still does not work properly.. the <select> needs to be on 'none' or something

                                        foreach ($available_themes as $theme) : ?>
        
                                        <option value="<?php echo build_query($theme)?>" <?php selected($theme['name'], $dts['themes']['low_support']['name']) ?>><?php echo $theme['name'] ?> &nbsp; </option><?php endforeach ?>

                                    </select>
                                </td><td>
                                    <span class="description"> <?php _e("Set a theme for devices that lack complete CSS & JavaScipt Support.") ?></span>
                                </td>
                            </tr><tr>
                                <th scope="row" align="right"valign="top">
                                    <label for="dts_session_timeout"><?php _e("Session timeout") ?> </label>
                                </th><td valign="top">
                                    <select name="dts_session_timeout_value">
                                        <option value="none"><?php _e("None") ?></option>
                                        <option value="900"><?php _e("15 Minutes") ?></option>
                                        <option value="1800"><?php _e("30 Minutes") ?></option>
                                        <option value="2700"><?php _e("45 Minutes") ?></option>
                                        <option value="3600"><?php _e("60 Minutes") ?></option>
                                        <option value="4500"><?php _e("75 Minutes") ?></option>
                                        <option value="5400"><?php _e("90 Minutes") ?></option>
                                    </select>
                                </td><td>
                                    <span class="description">
                                    <?php _e("Set a length of time until a user is kicked back to their device theme") ?><br />  
                                    <?php _e("after they've requested the 'Desktop Version.'") ?></span>
                                </td>                 
                            </tr><tr>
                                <th scope="row" align="right" valign="top">
                                    <label for="dts_disable_mobile_theme_caching"><?php _e("Theme Caching") ?> </label>
                                </th><td valign="top">
                                    <input type="checkbox" name="dts_disable_mobile_theme_caching" /> <?php _e("Disable caching") ?>
                                </td><td>
                                    <span class="description">
                                         <?php _e("Disable theme caching for handheld and tablet devices.") ?><br />
                                         <?php _e("This setting may be needed if you're using a plugin like W3 Total Cache or WP Super Cache.") ?>
                                    </span>
                                </td>                 
                            </tr>
                        </table>
                    </div>
                    <table>
                        <tr>
                            <th scope="row" align="right" width="150px">
                                <input type="hidden" name="dts_settings_update" value="true" />
                                <input type="submit" value="<?php _e("Save Settings") ?>" class="button button-primary" /> 
                            </th></td colspan="2"></td>
                        </tr>
                    </table>
                </form>
                <br /><br />
                <table>
                    <tr>
                        <th scope="row" align="right" width="150px">
                            <a href="#" class="help-and-support-toggle"><?php _e("Help & Support") ?></a> 
                        </th><td colspan="2"></td>
                    </tr>
                </table>
                <div class="help-and-support">
                    <table>
                        <tr>
                            <th scope="row" align="right" width="150px">
                                <?php _e("Helpful Links") ?>
                            </th><td align="left">
                                <a href="http://wordpress.org/support/plugin/device-theme-switcher" title="Device Theme Switcher Support Forum" target="_blank">Support Forum</a> | 
                                <a href="http://wordpress.org/plugins/device-theme-switcher/faq/" title="Device Theme Switcher FAQ" target="_blank">FAQ</a>
                            </td>
                        </tr><tr>
                            <th scope="row" align="right" width="150px">
                                <?php _e("Shortcodes") ?> 
                            </th><td align="left">
                                Blah
                            </td>
                        </tr><tr>
                            <th scope="row" align="right" valign="top">
                                <?php _e("Template Tags") ?> 
                            </th><td align="left" >
                                <?php echo htmlentities("<?php") . "<br />" ?>
                                &nbsp; &nbsp; //View Full Website<br />
                                &nbsp; &nbsp; link_to_full_website($link_text = "View Full Website", $css_classes = array(), $echo = true);<br /><br />
                                &nbsp; &nbsp; //Return to Mobile Website<br />
                                &nbsp; &nbsp; link_back_to_device($link_text = "Return to Mobile Website", $css_classes = array(), $echo = true);<br />
                                <?php echo "?>" ?>
                            </td>
                        </tr><tr>
                            <th scope="row" align="right" valign="top">
                                <?php _e("URL Paramaters") ?> 
                            </th><td align="left">
                                <a href="<?php bloginfo('url') ?>/?theme=handheld" title="View Handheld Theme" target="_blank"><?php bloginfo('url') ?>/?theme=handheld</a><br />
                                <a href="<?php bloginfo('url') ?>/?theme=tablet" title="View Tablet Theme" target="_blank"><?php bloginfo('url') ?>/?theme=tablet</a><br />
                                <a href="<?php bloginfo('url') ?>/?theme=low_support" title="View Low-Support Theme" target="_blank"><?php bloginfo('url') ?>/?theme=low_support</a><br />
                                <a href="<?php bloginfo('url') ?>/?theme=active" title="View Active Theme" target="_blank"><?php bloginfo('url') ?>/?theme=active</a>
                            </td>
                        </tr>
                    </table>
                </div>
                <script type="text/javascript">
                    (function($){
                        $('.advanced-options-toggle').click(function(){
                            oThis = $(this)
                            $('.advanced-options').slideToggle(600, function(){
                                if ($(this).is(':visible')) {
                                    oThis.text('Hide Advanced Options')
                                } else {
                                    oThis.text('Show Advanced Options')
                                }
                            })
                        })
                        $('.help-and-support-toggle').click(function(){
                            $('.help-and-support').slideToggle(600, function(){
                                //complete
                            })
                        })
                    })(jQuery)
                </script>
            </div><?php
        } //generate_admin_settings_page
        // ------------------------------------------------------------------------------
        // ADMIN NOTICES
        // ------------------------------------------------------------------------------
        static public function admin_activation_notice(){
            //Print a message to the admin window letting the user know thier settings have been saved
            //The CSS used to style this message is located in dts_admin_output.php
            echo '<div class="dts activated"><p>Welcome to Device Theme Switcher!</p></div>';
        }//admin_activation_notice
        static public function admin_save_settings_notice(){
            //Print a message to the admin window letting the user know thier settings have been saved
            //The CSS used to style this message is located in dts_admin_output.php
            echo '<div class="dts updated"><p>Settings saved.</p></div>';
        }//admin_save_settings_notice
    }//Class DTS_Admin