<?php
/*
Plugin Name: VideoWhisper Video Presentation
Plugin URI: http://www.videowhisper.com/?p=WordPress+Video+Presentation
Description: Video Presentation
Version: 4.6.6
Author: VideoWhisper.com
Author URI: http://www.videowhisper.com/
Contributors: videowhisper, VideoWhisper.com
*/

if (!class_exists("VWvideoPresentation"))
{

	class VWvideoPresentation
	{

		function VWvideoPresentation()
			{ //constructor
		}

		function settings_link($links) {
			$settings_link = '<a href="options-general.php?page=videowhisper_presentation.php">'.__("Settings").'</a>';
			array_unshift($links, $settings_link);
			return $links;
		}

		function init()
		{
			$plugin = plugin_basename(__FILE__);
			add_filter("plugin_action_links_$plugin",  array('VWvideoPresentation','settings_link') );

			add_filter("the_content", array('VWvideoPresentation','presentation_page'));

			wp_register_sidebar_widget('videoPresentationWidget','VideoWhisper Presentation', array('VWvideoPresentation', 'widget') );


			//shortcodes
			add_shortcode('videowhisperconsultation_hls', array( 'VWvideoPresentation', 'shortcode_hls'));
			add_shortcode('videowhisperconsultation', array( 'VWvideoPresentation', 'shortcode'));
			add_shortcode('videowhisperconsultation_manage',array( 'VWvideoPresentation', 'shortcode_manage'));

			//! ajax def

			//web app ajax calls
			add_action( 'wp_ajax_vwcns', array('VWvideoPresentation','vwcns_callback') );
			add_action( 'wp_ajax_nopriv_vwcns', array('VWvideoPresentation','vwcns_callback') );

			//transcode
			add_action( 'wp_ajax_vwcns_trans', array('VWvideoPresentation','vwcns_trans') );
			add_action( 'wp_ajax_nopriv_vwcns_trans', array('VWvideoPresentation','vwcns_trans'));


			//update page if not exists or deleted
			$page_id = get_option("vw_vp_page_manage");
			$page_id2 = get_option("vw_vp_page");

			if (!$page_id || $page_id == "-1" || !$page_id2 || $page_id2 == "-1")
				add_action('wp_loaded', array('VWvideoPresentation','updatePages'));

			//check db
			$vw_dbvp_version = "2.0";

			global $wpdb;
			$table_name = $wpdb->prefix . "vw_vpsessions";
			$table_name3 = $wpdb->prefix . "vw_vprooms";

			$installed_ver = get_option( "vw_dbvp_version" );

			if( $installed_ver != $vw_dbvp_version )
			{
				$wpdb->flush();

				$sql = "DROP TABLE IF EXISTS `$table_name`;
CREATE TABLE IF NOT EXISTS `$table_name` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session` varchar(64) NOT NULL,
  `username` varchar(64) NOT NULL,
  `room` varchar(64) NOT NULL,
  `message` text NOT NULL,
  `sdate` int(11) NOT NULL,
  `edate` int(11) NOT NULL,
  `status` tinyint(4) NOT NULL,
  `type` tinyint(4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `type` (`type`),
  KEY `room` (`room`),
  KEY `session` (`session`),
  KEY `edate` (`edate`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COMMENT='Video Whisper: Sessions - 2009@videowhisper.com' AUTO_INCREMENT=1 ;


		DROP TABLE IF EXISTS `$table_name3`;
		CREATE TABLE `$table_name3` (
		  `id` int(11) NOT NULL auto_increment,
		  `name` varchar(64) NOT NULL,
		  `owner` int(11) NOT NULL,
		  `sdate` int(11) NOT NULL,
		  `edate` int(11) NOT NULL,
		  `status` tinyint(4) NOT NULL,
		  `type` tinyint(4) NOT NULL,
		  PRIMARY KEY  (`id`),
		  KEY `name` (`name`),
		  KEY `status` (`status`),
		  KEY `type` (`type`),
		  KEY `owner` (`owner`)
		) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='Video Whisper: Rooms - 2014@videowhisper.com' AUTO_INCREMENT=1 ;

INSERT INTO `$table_name3` ( `name`, `owner`, `sdate`, `edate`, `status`, `type`) VALUES ( 'Lobby', '1', NOW(), NOW(), '1', '1');

		";

				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				dbDelta($sql);

				if (!$installed_ver) add_option("vw_dbvp_version", $vw_dbvp_version);
				else update_option( "vw_dbvp_version", $vw_dbvp_version );

				$wpdb->flush();
			}

			$options = VWvideoPresentation::getAdminOptions();

		}


		function updatePages()
		{

			$options = get_option('VWvideoPresentationOptions');

			//if not disabled create
			if ($options['disablePage']=='0')
			{
				global $user_ID;
				$page = array();
				$page['post_type']    = 'page';
				$page['post_content'] = '[videowhisperconsultation_manage]';
				$page['post_parent']  = 0;
				$page['post_author']  = $user_ID;
				$page['post_status']  = 'publish';
				$page['comment_status'] ='closed';
				$page['post_title']   = 'Setup Presentation';

				$page_id = get_option("vw_vp_page_manage");
				if ($page_id>0) $page['ID'] = $page_id;

				$pageid = wp_insert_post ($page);
				update_option( "vw_vp_page_manage", $pageid);
			}

			if ($options['disablePageC']=='0')
			{
				global $user_ID;
				$page = array();
				$page['post_type']    = 'page';
				$page['post_content'] = '[videowhisperconsultation]';
				$page['post_parent']  = 0;
				$page['post_author']  = $user_ID;
				$page['post_status']  = 'publish';
				$page['comment_status'] ='closed';
				$page['post_title']   = 'Video Presentation';

				$page_id = get_option("vw_vp_page");
				if ($page_id>0) $page['ID'] = $page_id;

				$pageid = wp_insert_post ($page);
				update_option( "vw_vp_page", $pageid);
			}

		}


		function deletePages()
		{
			$options = get_option('VWvideoPresentationOptions');

			if ($options['disablePage'])
			{
				$page_id = get_option("vw_vp_page_manage");
				if ($page_id > 0)
				{
					wp_delete_post($page_id);
					update_option( "vw_vp_page_manage", -1);
				}
			}

			if ($options['disablePageC'])
			{
				$page_id = get_option("vw_vp_page");
				if ($page_id > 0)
				{
					wp_delete_post($page_id);
					update_option( "vw_vp_page", -1);
				}
			}

		}

		//! Presentation Custom Post Type
		function presentation_post() {

			$options = get_option('VWvideoPresentationOptions');

			//only if missing
			if (post_type_exists('presentation')) return;

			$labels = array(
				'name'                => _x( 'Presentations', 'Post Type General Name', 'text_domain' ),
				'singular_name'       => _x( 'Presentation', 'Post Type Singular Name', 'text_domain' ),
				'menu_name'           => __( 'Presentations', 'text_domain' ),
				'parent_item_colon'   => __( 'Parent Presentation:', 'text_domain' ),
				'all_items'           => __( 'All Presentations', 'text_domain' ),
				'view_item'           => __( 'View Presentation', 'text_domain' ),
				'add_new_item'        => __( 'Add New Presentation', 'text_domain' ),
				'add_new'             => __( 'New Presentation', 'text_domain' ),
				'edit_item'           => __( 'Edit Presentation', 'text_domain' ),
				'update_item'         => __( 'Update Presentation', 'text_domain' ),
				'search_items'        => __( 'Search Presentations', 'text_domain' ),
				'not_found'           => __( 'No Presentations found', 'text_domain' ),
				'not_found_in_trash'  => __( 'No Presentations found in Trash', 'text_domain' ),
			);
			$args = array(
				'label'               => __( 'channel', 'text_domain' ),
				'description'         => __( 'Video Presentation', 'text_domain' ),
				'labels'              => $labels,
				'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'comments', 'custom-fields', 'page-attributes', ),
				'taxonomies'          => array( 'category', 'post_tag' ),
				'hierarchical'        => false,
				'public'              => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => true,
				'show_in_admin_bar'   => true,
				'menu_position'       => 5,
				'can_export'          => true,
				'has_archive'         => true,
				'exclude_from_search' => false,
				'publicly_queryable'  => true,
				'capability_type'     => 'post',
			);
			register_post_type( 'presentation', $args );

			flush_rewrite_rules();
		}

		function presentation_page($content)
		{

			if (!is_single()) return $content;
			$postID = get_the_ID() ;
			if (get_post_type( $postID ) != 'presentation') return $content;

			$room = sanitize_file_name(get_the_title($postID));
			$addCode =  "[videowhisperconsultation room=\"$room\"]";


			//give participant bonus
			global $current_user;
			get_currentuserinfo();
			$userID = $current_user->ID;

			if ($userID)
			{
				$options = get_option('VWvideoPresentationOptions');

				$mBonus = get_post_meta($postID, 'vw_bonus', true);
				$mParticipantsBonus = get_post_meta($postID, 'vw_participantsBonus', true);

				//$addCode .= 'DBG:bonusfor'.$userID.'value-'.$mBonus.'post-'.$postID.'from-';

				if (!$mParticipantsBonus) $mParticipantsBonus = array();
				if ($options['myCred'])  if ($mBonus)
						if (!array_key_exists($userID, $mParticipantsBonus))
						{
							$presentation = get_post( $postID );


							//pay only if balance permits
							if (VWvideoPresentation::balance($presentation->post_author) >= $mBonus)
							{
								$debug .= 'transaction';
								VWvideoPresentation::transaction('pay_participant_bonus', $presentation->post_author , -$mBonus, 'Pay video presentation participant bonus for ' . $current_user->display_name . ' in room <a href="'.get_permalink($postID).'">'.$room.'</a>');

								VWvideoPresentation::transaction('participant_bonus', $userID, $mBonus, 'Video presentation participant bonus for room <a href="'.get_permalink($postID).'">'.$room.'</a>');

								$mParticipantsBonus[$userID] = $mBonus;
								update_post_meta($postID, 'vw_participantsBonus', $mParticipantsBonus);
							};
						}
			}


			return $addCode . $content;
		}

		function single_template($single_template)
		{

			if (!is_single())  return $single_template;

			$options = get_option('VWvideoPresentationOptions');
			//if (!$options['custom_post']) $options['custom_post'] = 'channel';

			$postID = get_the_ID();
			
			$page_id = get_option("vw_vp_page");

			if ( get_post_type( $postID ) != $options['custom_post'] && $postID != $page_id) return $single_template;

			if ($options['postTemplate'] == '+plugin')
			{
				$single_template_new = dirname( __FILE__ ) . '/template-presentation.php';
				if (file_exists($single_template_new)) return $single_template_new;
			}


			$single_template_new = get_stylesheet_directory() . '/' . $options['postTemplate'];

			if (file_exists($single_template_new)) return $single_template_new;
			else return $single_template;
		}

		function widgetContent()
		{

			$options = get_option('VWvideoPresentationOptions');

			global $wpdb;
			$table_name = $wpdb->prefix . "vw_vpsessions";
			$table_name3 = $wpdb->prefix . "vw_vprooms";

			$root_url = get_bloginfo( "url" ) . "/";
			$raw_url = $root_url . "wp-content/plugins/videowhisper-video-presentation/vp/";

			$page_id = get_option("vw_vp_page");
			if ($page_id > 0) $permalink = get_permalink( $page_id );
			else $permalink = $raw_url;

			//clean expired users
			//do not clean more often than 20s (mysql table invalidate)
			$lastClean = 0; $cleanNow = false;
			$lastCleanFile = $options['uploadsPath'] . 'lastclean.txt';

			if (file_exists($lastCleanFile)) $lastClean = file_get_contents($lastCleanFile);
			if (!$lastClean) $cleanNow = true;
			else if ($ztime - $lastClean > 20) $cleanNow = true;

				if ($cleanNow)
				{
					if (!$options['onlineExpiration']) $options['onlineExpiration'] = 310;
					$exptime=$ztime-$options['onlineExpiration'];
					$sql="DELETE FROM `$table_name` WHERE edate < $exptime";
					$wpdb->query($sql);
					file_put_contents($lastCleanFile, $ztime);
				}

			$wpdb->flush();

			$items =  $wpdb->get_results( "SELECT o.room AS room, count(*) AS users FROM `$table_name` AS o, `$table_name3` AS r WHERE o.room=r.name AND o.status='1' AND r.type='1' GROUP BY room ORDER BY users DESC");

			echo "<ul>";
			if ($items) foreach ($items as $item) echo "<li><B><a href='".VWvideoPresentation::roomURL($item->room)."' target='_blank'>".$item->room."</a></B> (" . $item->users .")</a></li>";
				else echo "<li>No active presentation rooms.</li>";
				echo "</ul>";

			?><a href="<?php echo $permalink; ?>"><img src="<?php echo $root_url; ?>wp-content/plugins/videowhisper-video-presentation/vp/templates/consultation/i_webcam.png" align="absmiddle" border="0">Enter Presentation</a>
	<?php

			$state = 'block' ;
			if (!$options['videowhisper']) $state = 'none';
			echo '<div id="VideoWhisper" style="display: ' . $state . ';"><p>Powered by VideoWhisper <a href="http://www.videowhisper.com/?p=WordPress+Video+Presentation">Live Video Presentation Software</a>.</p></div>';

		}

		function widget($args)
		{
			extract($args);
			echo $before_widget;
			echo $before_title;?>Video Presentation<?php echo $after_title;
			VWvideopresentation::widgetContent();
			echo $after_widget;
		}

		function menu() {
			add_options_page('Video Presentation Options', 'Video Presentation', 9, basename(__FILE__), array('VWvideoPresentation', 'options'));
		}

		//if any key matches any listing
		function inList($keys, $data)
		{
			if (!$keys) return 0;

			$list=explode(",", strtolower(trim($data)));

			foreach ($keys as $key)
				foreach ($list as $listing)
					if ( strtolower(trim($key)) == trim($listing) ) return 1;

					return 0;
		}

		function getCurrentURL()
		{
			$currentURL = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
			$currentURL .= $_SERVER["SERVER_NAME"];

			if($_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443")
			{
				$currentURL .= ":".$_SERVER["SERVER_PORT"];
			}

			$currentURL .= $_SERVER["REQUEST_URI"];
			return $currentURL;
		}

		function roomURL($room)
		{

			$options = get_option('VWvideoPresentationOptions');

			if ($options['accessLink']=='site')
			{

				//post page?
				global $wpdb;
				$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_name = '" . sanitize_file_name($room) . "' and post_type='presentation' LIMIT 0,1" );

				if ($postID) return get_post_permalink($postID);

				//landing page?
				$page_id = get_option("vw_vp_page");
				if ($page_id>0)
				{
					$permalink = get_permalink($page_id);
					if ($permalink)
						return add_query_arg(array('r'=>sanitize_file_name($room)),$permalink);
				}

			}

			//else just load full page
			return plugin_dir_url(__FILE__) ."vp/?r=" . urlencode(sanitize_file_name($room));
		}

		function path2url($file, $Protocol='http://') {
			return $Protocol.$_SERVER['HTTP_HOST'].str_replace($_SERVER['DOCUMENT_ROOT'], '', $file);
		}

	//! Billing Integration: MyCred, TeraWallet (WooWallet)

		static function balances($userID, $options = null)
		{
			//get html code listing balances
			if (!$options) $options = get_option('VWvideoPresentationOptions');
			if (!$options['walletMulti']) return ''; //disabled

			$balances = self::walletBalances($userID, '', $options);

			$walletTransfer = sanitize_text_field( $_GET['walletTransfer'] );

			global $wp;
			foreach ($balances as $key=>$value)
			{
				$htmlCode .= '<br>'. $key . ': ' . $value;

				if ($options['walletMulti'] == 2 && $walletTransfer != $key && $options['wallet'] != $key && $value>0) $htmlCode .= ' <a class="ui button compact tiny" href=' . add_query_arg(array('walletTransfer'=>$key),$wp->request) . ' data-tooltip="Transfer to Active Balance">Transfer</a>';

				if ($walletTransfer == $key || ($value>0 && $options['walletMulti'] == 3 && $options['wallet'] != $key))
				{
					self::walletTransfer($key, $options['wallet'], get_current_user_id(), $options);
					$htmlCode .= ' Transferred to active balance.';
				}

			}


			return $htmlCode;
		}

		static function walletBalances($userID, $view = 'view', $options = null)
		{
			$balances = array();
			if (!$userID) return $balances;

			//woowallet
			if ($GLOBALS['woo_wallet'])
			{
				$wooWallet = $GLOBALS['woo_wallet'];
				$balances['WooWallet'] = $wooWallet->wallet->get_wallet_balance( $userID, $view);
			}

			//mycred
			if (function_exists( 'mycred_get_users_balance')) $balances['MyCred'] = mycred_get_users_balance($userID);

			return  $balances;
		}


		static function walletTransfer($source, $destination, $userID, $options = null)
		{
			//transfer balance from a wallet to another wallet

			if ($source == $destination) return;

			if (!$options) $options = get_option('VWvideoPresentationOptions');

			$balances = self::walletBalances($userID, '', $options);

			if ($balances[$source] > 0)
			{
				self::walletTransaction($destination, $balances[$source], $userID, "Wallet balance transfer from $source to $destination.", 'wallet_transfer');
				self::walletTransaction($source, - $balances[$source], $userID, "Wallet balance transfer from $source to $destination.", 'wallet_transfer');
			}

		}

		static function walletTransaction($wallet, $amount, $user_id, $entry, $ref, $ref_id = null, $data = null)
		{
			//transactions on all supported wallets
			//$wallet : MyCred/WooWallet

			if ($amount == 0) return; //no transaction

			//mycred
			if ($wallet == 'MyCred')
				if ($amount>0)
				{
					if (function_exists('mycred_add')) mycred_add($ref, $user_id, $amount, $entry, $ref_id, $data);
				}
			else
			{
				if (function_exists('mycred_subtract')) mycred_subtract( $ref, $user_id, $amount, $entry, $ref_id, $data );
			}

			//woowallet
			if ($wallet == 'WooWallet')
				if ($GLOBALS['woo_wallet'])
				{
					$wooWallet = $GLOBALS['woo_wallet'];

					if ($amount>0)
					{
						$wooWallet->wallet->credit( $user_id, $amount, $entry );
					}
					else
					{
						$wooWallet->wallet->debit( $user_id, -$amount, $entry );
					}

				}

		}

		static function balance($userID, $live = false, $options = null)
		{
			//get current user balance (as value)
			// $live also estimates active (incomplete) session costs for client

			if (!$userID) return 0;

			if (!$options) $options = get_option('VWvideoPresentationOptions');

			$balance = 0;

			$balances = self::walletBalances($userID, '', $options);

			if ($options['wallet'])
				if (array_key_exists($options['wallet'], $balances)) $balance = $balances[$options['wallet']];

				if ($live)
				{
					$updated = get_user_meta($userID, 'vw_ppv_tempt', true);

					if (time() - $updated < 15) //updated recently: use that estimation
						$temp = get_user_meta($userID, 'vw_ppv_temp', true);
					else $temp = self::billSessions($userID, 0, false); //estimate charges for current sessions

					$balance = $balance - $temp; //deduct temporary charge
				}

			return $balance;
		}

		static function transaction($ref = "video_presentation", $user_id = 1, $amount = 0, $entry = "Video Presentation transaction.", $ref_id = null, $data = null, $options = null)
		{
			//ref = explanation ex. ppv_client_payment
			//entry = explanation ex. PPV client payment in room.
			//utils: ref_id (int|string|array) , data (int|string|array|object)

			if ($amount == 0) return; //nothing


			if (!$options) $options = get_option('VWvideoPresentationOptions');

			//active wallet
			if ($options['wallet']) $wallet = $options['wallet'];
			if (!$wallet) $wallet = 'MyCred';
			if (!function_exists('mycred_add')) if ($GLOBALS['woo_wallet']) $wallet = 'WooWallet';


				self::walletTransaction($wallet, $amount, $user_id, $entry, $ref, $ref_id, $data);
		}



		//! Shortcodes

		function shortcode($atts)
		{

			$atts = shortcode_atts(array('room' => '', 'link' => 1), $atts, 'videowhisperconsultation');

			$room = $atts['room'];
			if (!$room) $room = $_GET['room'];
			if (!$room) $room = $_GET['r'];
			$room = sanitize_file_name($room);

			//iOS?
			$agent = $_SERVER['HTTP_USER_AGENT'];
			if( strstr($agent,'iPhone') || strstr($agent,'iPod') || strstr($agent,'iPad'))
				return do_shortcode("[videowhisperconsultation_hls channel=\"$room\"]");


			$baseurl="";
			$bgcolor="#333333";

			$swfurl = plugin_dir_url(__FILE__) . 'vp/consultation.swf?ssl=1&room=' . urlencode($room);
			//$swfurl .= "&prefix=" . urlencode(plugin_dir_url(__FILE__) . 'vp/');
			//$swfurl .= '&ws_res=' . urlencode( plugin_dir_url(__FILE__) . 'vp/');

			$swfurl .= "&prefix=" . urlencode(admin_url() . 'admin-ajax.php?action=vwcns&task=');
			$swfurl .= '&extension='.urlencode('_none_');
			$swfurl .= '&ws_res=' . urlencode( plugin_dir_url(__FILE__) . 'vp/');



			$htmlCode = <<<HTMLCODE
<div id="videowhisper_presentation_$room">
<object width="100%" height="100%" type="application/x-shockwave-flash" data="$swfurl">
<param name="movie" value="$swfurl" /><param name="bgcolor" value="$bgcolor" /><param name="salign" value="lt" /><param name="scale" value="noscale" /><param name="allowFullScreen" value="true" /><param name="allowscriptaccess" value="always" /> <param name="base" value="$baseurl" /> <param name="wmode" value="transparent" />
</object>
<noscript>
<p align=center><a href="https://videowhisper.com/?p=WordPress+Video+Presentation"><strong>WordPress Live Video Presentation Plugin</strong></a></p>
<p align="center"><strong>This content requires the Adobe Flash Player:
<a href="http://www.macromedia.com/go/getflash/">Get Flash</a></strong>!</p>
</noscript>
</div>
<br style="clear:both" />
<style type="text/css">
<!--

#videowhisper_presentation_$room
{
width: 100%;
height: 800px;
background: $bgcolor;
}

-->
</style>
HTMLCODE;

			if ($atts['link']) $htmlCode .= "<a class='button' target='_top' href='".plugin_dir_url(__FILE__) . 'vp/?room='.urlencode($room)."'>Open Room in Full Page Layout</a>";

			$options = get_option('VWvideoPresentationOptions');

			if (!$options['disableTranscoder'])
			{
				//moderator?
				$userName =  $options['userName']; if (!$userName) $userName='user_nicename';

				//username
				global $current_user;
				get_currentuserinfo();
				if ($current_user->$userName) $username=sanitize_file_name($current_user->$userName);

				//access keys
				if ($current_user)
				{
					$userkeys = $current_user->roles;
					$userkeys[] = $current_user->user_login;
					$userkeys[] = $current_user->ID;
					$userkeys[] = $current_user->user_email;
					$userkeys[] = $current_user->display_name;
				}

				//get apartenence if used with a BuddyPress group
				if ($room)
					if (class_exists('BP_Groups_Group'))
					{
						$group_id =  BP_Groups_Group::group_exists( $room );
						$group = new BP_Groups_Group( $group_id );
						$group_member = $group->is_member;

						$group_admin=0;
						if ($group->admins) if (is_array($group->admins)) foreach ($group->admins as $usr) if ( $usr->user_login == $current_user->user_login ) $group_admin=1;

									if ($group_admin) $administrator=1;
					}

				//username
				//if ($current_user->$userName) $username=urlencode($current_user->$userName);
				//$username=preg_replace("/[^0-9a-zA-Z_]/","-",$username);



				if (!$room && !$visitor)
				{
					if ($options['landingRoom']=='username')  //can create
						{
						$room=$username;
						$administrator=1;
					}
					else $room = $options['lobbyRoom']; //or go to default
				}
				else if (!$room) $room = $options['lobbyRoom'];  //visitor can't create room

					//if room name == username -> administrator
					if (!$options['disableModeratorByName'])
						if ($room == $username) $administrator = 1;

						if (VWvideoPresentation::inList($userkeys, $options['moderatorList'])) $administrator = 1;

						if ($administrator)
						{
							$stream = $username;

							$admin_ajax = admin_url() . 'admin-ajax.php';

							$htmlCode .= <<<HTMLCODE
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>

<div id="vwinfo">
iOS Transcoding (iPhone/iPad)<BR>
<input type="text" id="stream" name="stream" size="24" maxlength="64" value="$stream" class="social-input" ><BR>
<a href='#' class="button" id="transcoderon">ON</a>
<a href='#' class="button" id="transcoderoff">OFF</a>

<div id="result">A stream must be broadcast for transcoder to start.</div>
<p align="right">(<a href="javascript:void(0)" onClick="vwinfo.style.display='none';">hide</a>)</p>
</div>

<style type="text/css">
<!--

#vwinfo
{
	font-family: Verdana;
	font-size: 14px;
	color:#333;

	float: right;
	width: 25%;
	position: absolute;
	bottom: 10px;
	right: 10px;
	text-align:left;
	padding: 10px;
	margin: 10px;
	background-color: #666;
	border: 1px dotted #AAA;
	z-index: 1;

	filter: progid:DXImageTransform.Microsoft.gradient(startColorstr='#999', endColorstr='#666'); /* for IE */
	background: -webkit-gradient(linear, left top, left bottom, from(#999), to(#666)); /* for webkit browsers */
	background: -moz-linear-gradient(top,  #999,  #666); /* for firefox 3.6+ */

	box-shadow: 2px 2px 2px #333;


	-moz-border-radius: 9px;
	border-radius: 9px;
}

#vwinfo > a {
	color: #F77;
	text-decoration: none;
}

#vwinfo > .button, .button {
	-moz-box-shadow:inset 0px 1px 0px 0px #f5978e;
	-webkit-box-shadow:inset 0px 1px 0px 0px #f5978e;
	box-shadow:inset 0px 1px 0px 0px #f5978e;
	background:-webkit-gradient( linear, left top, left bottom, color-stop(0.05, #db4f48), color-stop(1, #944038) );
	background:-moz-linear-gradient( center top, #db4f48 5%, #944038 100% );
	filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#db4f48', endColorstr='#944038');
	background-color:#db4f48;
	border:1px solid #d02718;
	display:inline-block;
	color:#ffffff;
	font-family: Verdana;
	font-size: 12px;
	font-weight:normal;
	font-style:normal;
	text-decoration:none;
	text-align:center;
	text-shadow:1px 1px 0px #810e05;
	padding: 5px;
	margin: 2px;
}
#vwinfo > .button:hover, .button:hover {
	background:-webkit-gradient( linear, left top, left bottom, color-stop(0.05, #944038), color-stop(1, #db4f48) );
	background:-moz-linear-gradient( center top, #944038 5%, #db4f48 100% );
	filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#944038', endColorstr='#db4f48');
	background-color:#944038;
}

-->
</style>

<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js"></script>
<script type="text/javascript">
	$.ajaxSetup ({
		cache: false
	});
	var ajax_load = "Loading...";

	$("#transcoderon").click(function(){
		$("#result").html(ajax_load).load("$admin_ajax?action=vwcns_trans&task=mp4&room=$room&stream="+ $("#stream").val());
	});

	$("#transcoderoff").click(function(){
	$("#result").html(ajax_load).load("$admin_ajax?action=vwcns_trans&task=close&room=$room&stream="+ $("#stream").val());
	});
</script>
HTMLCODE;
						} //end administrator

			}//end transcoding

			return $htmlCode;

		}


		function shortcode_hls($atts)
		{
			//[videowhisperconsultation_hls channel="username" width="480px" height="360px" transcoder="1"]

			$stream = '';
			$options = get_option('VWvideoPresentationOptions');

			$atts = shortcode_atts(array('channel' => $stream, 'width' => '480px', 'height' => '360px', 'transcoder' =>'1'), $atts, 'videowhisperconsultation_hls');


			if (!$stream) $stream = $atts['channel']; //parameter channel="name"
			if (!$stream) $stream = $_GET['n'];

			$stream = sanitize_file_name($stream);

			$width=$atts['width']; if (!$width) $width = "480px";
			$height=$atts['height']; if (!$height) $height = "360px";

			if (!$stream)
			{
				return "Watch HLS Error: Missing channel name!";
			}

			if ($atts['transcoder'] && !$options['disableTranscoder']) $streamName = "i_$stream";
			else $streamName = $stream;

			$streamURL = "${options['httpstreamer']}$streamName/playlist.m3u8";



			$dir = $options['uploadsPath']. "/_thumbs";
			$thumbFilename = "$dir/" . $stream . ".jpg";

			$htmlCode = <<<HTMLCODE
<video id="videowhisper_hls_$stream" width="$width" height="$height" autobuffer autoplay controls poster="">
 <source src="$streamURL" type='video/mp4'>
    <div class="fallback">
	    <p>You must have an HTML5 capable browser with HLS support (Ex. Safari) to open this live stream: $streamURL</p>
	</div>
</video>

HTMLCODE;
			return $htmlCode;
		}


		function shortcode_manage()
		{

			//! can user create room?
			$options = get_option('VWvideoPresentationOptions');


			$canBroadcast = $options['canBroadcast'];
			$broadcastList = $options['broadcastList'];
			$userName =  $options['userName']; if (!$userName) $userName='user_nicename';

			$loggedin=0;

			global $current_user;
			get_currentuserinfo();
			if ($current_user->$userName) $username = $current_user->$userName;

			//access keys
			$userkeys = $current_user->roles;
			$userkeys[] = $current_user->user_login;
			$userkeys[] = $current_user->ID;
			$userkeys[] = $current_user->user_email;
			$userkeys[] = $current_user->display_name;

			switch ($canBroadcast)
			{
			case "members":
				if ($username) $loggedin=1;
				else $htmlCode .= "<a href=\"/\">Please login first or register an account if you don't have one!</a>";
				break;
			case "list";
				if ($username)
					if (VWvideoPresentation::inList($userkeys, $broadcastList)) $loggedin=1;
					else $htmlCode .= "<a href=\"/\">$username, you are not allowed to setup rooms.</a>";
					else $htmlCode .= "<a href=\"/\">Please login first or register an account if you don't have one!</a>";
					break;
			}

			if (!$loggedin)
			{
				$htmlCode .='<p>This pages allows creating and managing conferencing rooms for register members that have this feature enabled.</p>' . $canBroadcast;
				return $htmlCode;
			}

			//! save

			//setup price
			$myCred =  $options['myCred'] && VWvideoPresentation::inList($userkeys, $options['canSell']);

			$this_page    =   get_permalink();

			if ($loggedin)
			{
				global $wpdb;
				$table_name = $wpdb->prefix . "vw_vpsessions";
				$table_name3 = $wpdb->prefix . "vw_vprooms";

				$wpdb->flush();
				$rmn = $wpdb->get_row("SELECT count(id) as no FROM $table_name3 where owner='".$current_user->ID."'");

				//! delete
				if ($delid=(int) $_GET['delete'])
				{
					//post
					$rdata = $wpdb->get_row("SELECT * FROM $table_name3 where id='$delid'");
					if ($rdata)
					{
						$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_name = '" . sanitize_file_name($room) . "' and post_type='presentation' LIMIT 0,1" );
						if ($postID) wp_delete_post($postID);
					}

					//room
					$sql = $wpdb->prepare("DELETE FROM $table_name3 where owner='".$current_user->ID."' AND id='%d'", array($delid));
					$wpdb->query($sql);
					$wpdb->flush();
					$htmlCode .=  "<div class='update'>Room #$delid was deleted.</div>";

					$rmn = $wpdb->get_row("SELECT count(id) as no FROM $table_name3 where owner='".$current_user->ID."'");
				}

				//!save: edit/new
				$room = sanitize_file_name($_POST['room']);
				if ($room)
				{

					// post save
					$post = array(
						'post_content'   => sanitize_text_field($_POST['description']),
						'post_name'      => $room,
						'post_title'     => $room,
						'post_author'    => $current_user->ID,
						'post_type'      => 'presentation',
						'post_status'    => 'publish',
						'comment_status' => sanitize_file_name($_POST['newcomments']),
					);

					$category = (int) $_POST['newcategory'];

					$newPrice = round($_POST['price'],2);
					$newDuration = (int) $_POST['duration'];

					if ($myCred && $newPrice)
					{
						$mCa = array(
							'status'       => 'enabled',
							'price'        => round($_POST['price'],2),
							'button_label' => 'Buy Now', // default button label
							'expire'       => $newDuration,
							'recurring'    => 0
						);
					}

					$ztime=time();

					$sql = $wpdb->prepare("SELECT owner FROM $table_name3 where name='%s'", array($room));
					$rdata = $wpdb->get_row($sql);

					$editID= (int) $_POST['editRoom'];

					if (!$rdata || $editID>0)
					{


						if ($editID) //edit
							{

							$rdata = $wpdb->get_row("SELECT * FROM $table_name3 where id='$editID'");
							if ($rdata)
							{

								$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_name = '" . sanitize_file_name($room) . "' and post_type='presentation' LIMIT 0,1" );

								if ($postID>0)
								{
									$presentation = get_post( $postID );
									if ($presentation->post_author == $current_user->ID) $post['ID'] = $postID;
									else return "<div class='error'>Not allowed!</div>";
									$htmlCode .= "<div class='update'>Presentation $room was updated!</div>";
								}
								else $htmlCode .= "<div class='update'>Presentation $room was created!</div>";

								$postID = wp_insert_post($post);
								if ($postID)
								{
									wp_set_post_categories($postID, array($category));
									if ($myCred && $newPrice) update_post_meta($postID, 'myCRED_sell_content', $mCa);
									else delete_post_meta($postID, 'myCRED_sell_content');
								}


								$sql="UPDATE `$table_name3` set name = '$room', `type`='".((int) $_POST['type'])."' where id ='$editID' AND owner='".$current_user->ID."'";
								$wpdb->query($sql);


							} else $htmlCode .=  "Room $editID not found!";

						} else //new
							{

							if ($rmn->no < $options['maxRooms'])
							{
								$sql=$wpdb->prepare("INSERT INTO `$table_name3` ( `name`, `owner`, `sdate`, `edate`, `status`, `type`) VALUES ('%s', '".$current_user->ID."', '$ztime', '0', 1, '%d')",array($room, $_POST['type']));
								$wpdb->query($sql);
								$wpdb->flush();
								$htmlCode .=  "<div class='update'>Room '$room' was created.</div>";
								$postID = wp_insert_post($post);
								if ($postID)
								{
									wp_set_post_categories($postID, array($category));
									if ($myCred && $newPrice) update_post_meta($postID, 'myCRED_sell_content', $mCa);
									else delete_post_meta($postID, 'myCRED_sell_content');
								}



								$rmn = $wpdb->get_row("SELECT count(id) as no FROM $table_name3 where owner='".$current_user->ID."'");

							}else $htmlCode .=  "<div class='error'>Room limit reached!</div>";
						}

					}
					else
					{
						$htmlCode .=  "<div class='error'>Room name '$room' is already in use. Please choose another name!</div>";
						$room="";
					}

					//meta save
					if ($postID)
					{
						foreach (array('participants', 'access', 'layout', 'bonus') as $meta)
						{
							$value = sanitize_text_field($_POST[$meta]);
							update_post_meta($postID,'vw_'.$meta, $value);
						}

						// special handling - make sure it starts with &
						$value = trim(sanitize_text_field($_POST['parameters']));
						if ($value) if ($value[0]!='&') $value = '&' . $value;
							update_post_meta($postID,'vw_parameters', $value);
					}

				}


				//! list rooms
				$wpdb->flush();

				$sql = "SELECT * FROM $table_name3 where owner='".$current_user->ID."'";
				$rooms=$wpdb->get_results($sql);

				$htmlCode .=  "<H3>My Rooms (" . $rmn->no . '/' . $options['maxRooms'].")</H3>";
				$table_nameC = $wpdb->prefix . "myCRED_log";

				if (count($rooms))
				{
					$htmlCode .=  "<table>";
					$htmlCode .=  "<tr><th>Room</th><th>Link (use to invite)</th><th>Online</th><th>Type</th><th>Manage</th></tr>";
					$root_url = plugins_url() . "/";
					foreach ($rooms as $rd)
					{
						$rm=$wpdb->get_row("SELECT count(*) as no, group_concat(username separator ' <BR> ') as users, room as room FROM `$table_name` where status='1' and type='1' AND room='".$rd->name."' GROUP BY room");

						$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_name = '" . sanitize_file_name($rd->name) . "' and post_type='presentation' LIMIT 0,1" );

						$participants = (int) get_post_meta($postID,'vw_participants', true);
						if ($participants > 3) $participants = 0;
						$participantsLabel = array('Default', 'Invite Only', 'Passive', 'Active');

						$htmlCode .=  "<tr><td><a href='" . VWvideoPresentation::roomURL($rd->name)."'><B>".$rd->name."</B></a></td> <td>" . VWvideoPresentation::roomURL($rd->name) ."</td> <td>".($rm->no>0?$rm->users:'0')."</td><td>".($rd->type==1?'Public':($rd->type==2?"Private":$rd->type)). ' / '. $participantsLabel[$participants]. "</td> <td><a href='".$this_page.(strstr($this_page,'?')?'&':'?')."edit=".$rd->id."'>Edit </a>| <a href='".$this_page.(strstr($this_page,'?')?'&':'?')."notify=".$postID."'>Notify</a> | <a href='".$this_page.(strstr($this_page,'?')?'&':'?')."delete=".$rd->id."'>Delete</a></td> </tr>";

						if ($myCred)
						{

							if ($postID)
							{
								$buyers = $wpdb->get_col( $sql = "SELECT DISTINCT user_id FROM {$table_nameC} WHERE ref = 'buy_content' AND ref_id = {$postID} AND creds < 0" );
								$buyerList = '';
								if ($buyers)
									foreach ($buyers as $buyerID)
									{
										if (function_exists('bp_core_get_userlink')) $buyerlink = bp_core_get_userlink($buyerID);
										else {
											$buyer = get_userdata($buyerID);
											$buyerlink = '<a href="'.$buyer->user_url.'">'.$buyer->user_nicename . '</a>';
										}

										$buyerList .= ($buyerList?', ':'') . $buyerlink ;
									}

								if ($buyerList) $htmlCode .=  "<tr><th>Clients</th><td colspan='4'>$buyerList</td></tr>";
							}

						}
					}
					$htmlCode .=  "</table>";

				}
				else $htmlCode .=  "You don't currently have any rooms.";


				if ($notifyid=(int) $_GET['notify'])
				{
					$postID = $notifyid;
					$presentation = get_post( $postID );
					if ($presentation->post_author != $current_user->ID) return "<div class='error'>Not allowed!</div>";

					$htmlCode .= 'Notifying users from <b>'.$presentation->post_title.'</b> invite list: ';

					$search = array('#room#', '#link#');
					$replace = array($presentation->post_title, get_permalink($postID));

					$subject = str_replace($search, $replace, html_entity_decode(stripslashes($option['notifySubject'])));
					$message = str_replace($search, $replace, html_entity_decode(stripslashes($option['notifyMessage'])));

					$newAccess = get_post_meta($postID, 'vw_access', true);
					$list=explode(",", trim($newAccess));

					$headers = array('Content-Type: text/html; charset=UTF-8');

					foreach ($list as $listing)
					{
						$user = null;
						$listing = trim($listing);
						if (filter_var($listing, FILTER_VALIDATE_EMAIL)) $user = get_user_by('email', $listing);
						else $user = get_user_by('login', $listing);


						if ($user) {
							$notifyCode .= ($notifyCode?', ':'') .$user->user_login;
							if (wp_mail($user->user_email, $subject, $message, $headers)) $notifyCode .= '(success)';
							else $notifyCode .= '(failed)';
						}
					}

					if ($notifyCode) $htmlCode .=$notifyCode . '.';
					else $htmlCode .= 'No users identified to notify.';

				}

				//! form: save room
				$newName = 'Room_'.base_convert((time()-1225000000),10,36);
				$newPrice = '0.00';


				if ($editid=(int) $_GET['edit'])
				{
					$rdata = $wpdb->get_row("SELECT * FROM $table_name3 where id='$editid' AND owner='".$current_user->ID."'");
					if ($rdata)
					{
						$newName = $rdata->name;
						$newType = $rdata->type;
						$editRoom = $editid;

						$newNameL = "#$editid $newName";


						$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_name = '" . sanitize_file_name($newName) . "' and post_type='presentation' LIMIT 0,1" );

						if ($postID>0)
						{
							$presentation = get_post( $postID );
							if ($presentation->post_author != $current_user->ID) return "<div class='error'>Not allowed!</div>";

							//post
							$newDescription = $presentation->post_content;
							$newName = $presentation->post_title;
							$newComments = $presentation->comment_status;

							$cats = wp_get_post_categories( $postID);
							if (count($cats)) $newCat = array_pop($cats);

							//mycred
							if ($myCred)
							{
								$mCa = get_post_meta( $postID, 'myCRED_sell_content', true );
								if ($mCa)
								{
									$newPrice = $mCa['price'];
									$newDuration = $mCa['expire'];
								}
							}

							//meta
							foreach (array('participants', 'access', 'parameters', 'layout', 'bonus') as $meta)
							{
								${'new'. ucwords($meta)} = get_post_meta($postID, 'vw_'.$meta, true);
							}

						}

					} else $htmlCode .=  "Room $editid not found!";
				} else
				{
					$newNameL = 'New';
					$newParameters = html_entity_decode($options['parametersCustom']);
				}

				$commentsCode = '';
				$commentsCode .= '<select id="newcomments" name="newcomments">';
				$commentsCode .= '<option value="closed" ' . ($newComments=='closed'?'selected':'') . '>Closed</option>';
				$commentsCode .= '<option value="open" ' . ($newComments=='open'?'selected':'') . '>Open</option>';
				$commentsCode .= '</select>';

				if (!$newType) $newType = 2;
				$typeCode .= '<select id="type" name="type">';
				$typeCode .= '<option ' . ($newType=='2'?'selected':'') . ' value="2">Private</option>';
				$typeCode .= '<option ' . ($newType=='1'?'selected':'') . ' value="1">Public</option>';
				$typeCode .= '</select> All your rooms will be accessible for you in presentation room list. Public rooms will be listed for everybody by widget when online.';


				if (!$newParticipants) $newParticipants = 0;
				$participantsCode .= '<select id="participants" name="participants">';
				$participantsCode .= '<option ' . ($newParticipants=='3'?'selected':'') . ' value="3">Active</option>';
				$participantsCode .= '<option ' . ($newParticipants=='2'?'selected':'') . ' value="2">Passive</option>';       $participantsCode .= '<option ' . ($newParticipants=='1'?'selected':'') . ' value="1">Invite Only</option>';
				$participantsCode .= '<option ' . ($newParticipants=='0'?'selected':'') . ' value="0">Default</option>';
				$participantsCode .= '</select> ';
				$participantsCode .= 'How can people access and participate room. Passive will allow all to participate but only users in invite list to interact.';

				$accessCode .= '<textarea rows=2 name="access" id="access">';
				$accessCode .= $newAccess;
				$accessCode .= '</textarea>';
				$accessCode .= 'Comma separated list of usernames, emails, roles that can access when Invite Only is enabled. Users need to be registered and logged in to be identified. Leave blank for default access settings. Use this to email notifications to invite list (only usernames and emails).';

				$parametersCode .= '<textarea rows=2 name="parameters" id="parameters">';
				$parametersCode .= $newParameters;
				$parametersCode .= '</textarea>';
				$parametersCode .= 'Custom room parameters. Ex: &publicVideosN=2&publicVideo1=user1&publicVideo2=user2&publicVideosMax=8';

				$layoutCode .= '<textarea rows=2 name="layout" id="layout">';
				$layoutCode .= $newLayout;
				$layoutCode .= '</textarea>';
				$layoutCode .= 'Generate by writing and sending "/videowhisper layout" in chat (contains panel positions, sizes, move and resize toggles). Copy and paste code here.';

				$categories = wp_dropdown_categories('show_count=1&echo=0&name=newcategory&hide_empty=0&selected=' . $newCat);
				//create form
				if ($editRoom > 0 || $rmn->no < $options['maxRooms'])
				{
					$htmlCode .=  '<h3>Setup Presentation ('.$newNameL.')</h3><form method="post" action="' . $this_page .'"  name="adminForm">
<table class="g-input" width="500px">
<tr><td>Room name</td><td><input name="room" type="text" id="room" value="'.$newName.'" size="20" maxlength="64" /> <input type="submit" name="button" id="button1" value="Save" /></td></tr>';

					if ($myCred)
					{
						$htmlCode .=  '<tr><td>Sell Price</td><td><input name="price" type="text" id="price" value="'.$newPrice.'" size="6" maxlength="6" />Users need to pay this price to access. Set 0 for free access.</td></tr>';
						$htmlCode .=  '<tr><td>Access Duration</td><td><input name="duration" type="text" id="duration" value="'.$newDuration.'" size="6" maxlength="6" /> hours.  Set 720 for 30 days, 0 for unlimited time access (one time flat fee). </td></tr>';

					}

					$htmlCode .=  '<tr><td>Participant Bonus</td><td><input name="bonus" type="text" id="bonus" value="'.$newBonus.'" size="6" maxlength="6" />Participants get this bonus first time when accessing room page, paid by room owner, if balance permits. Set 0 for no bonus.</td></tr>';

					$htmlCode .=  '<tr><td>Privacy</td><td>' . $typeCode . ' </td></tr>';
					$htmlCode .=  '<tr><td>Participants</td><td>' . $participantsCode . ' </td></tr>';
					$htmlCode .=  '<tr><td>Invite List</td><td>' . $accessCode . ' </td></tr>';

					$htmlCode .=  '<tr><td>Parameters</td><td>' . $parametersCode . ' </td></tr>';
					$htmlCode .=  '<tr><td>Layout Code</td><td>' . $layoutCode . ' </td></tr>';

					$htmlCode .= '<tr><td>Description</td><td><textarea rows=4 name="description" id="description">'.$newDescription.'</textarea>Shows under application container.</td></tr>';
					$htmlCode .= '<tr><td>Category</td><td>'.$categories.'</td></tr>';

					$htmlCode .= '<tr><td>Page Comments</td><td>'.$commentsCode.'</td></tr>';

					$htmlCode .= '<tr><td colspan=2><input type="submit" name="button" id="button" value="Save" />
		<input type="hidden" name="editRoom" id="editRoom" value="'.$editRoom.'" /></td></tr>
	</table>
		</form>
		';
				} elseif ($rmn->no > $options['maxRooms'])
					$htmlCode .= "You can't setup new rooms because you reached room limit (".$options['maxRooms'].").";

			}

			return $htmlCode;

		}

		//! Application Callbacks
		function rexit($output)
		{
			echo $output;
			exit;

		}

		function vwcns_callback()
		{

			//error_reporting(E_ALL);
			ini_set('display_errors', 'On');

			global $wpdb;
			$options = get_option('VWvideoPresentationOptions');

			ob_clean();

			switch ($_GET['task'])
			{
				//! login

			case 'c_login':


				$userName =  $options['userName']; if (!$userName) $userName='user_nicename';
				$canAccess = $options['canAccess'];
				$accessList = $options['accessList'];

				$camRes = explode('x',$options['camResolution']);


				$room=$_GET['room_name'];
				$room = sanitize_file_name( $room );

				//username
				global $current_user;
				get_currentuserinfo();
				if ($current_user->$userName) $username=sanitize_file_name($current_user->$userName);

				$userID = $current_user->ID;

				//access keys
				if ($current_user)
				{
					$userkeys = $current_user->roles;
					$userkeys[] = $current_user->user_login;
					$userkeys[] = $current_user->ID;
					$userkeys[] = $current_user->user_email;
					$userkeys[] = $current_user->display_name;
				}



				//get apartenence if used with a BuddyPress group
				if ($room)
					if (class_exists('BP_Groups_Group'))
					{
						$group_id =  BP_Groups_Group::group_exists( $room );
						$group = new BP_Groups_Group( $group_id );
						$group_member = $group->is_member;

						$group_admin=0;
						if ($group->admins) if (is_array($group->admins))
								foreach ($group->admins as $usr) if ( $usr->user_login == $current_user->user_login ) $group_admin=1;

									if ($group_admin) $administrator=1;

									if ($group_member)
									{
										$userkeys[] = $room;
										$regularCams=1;
										$regularWatch=1;
										$privateTextchat=1;

										$extra_info = "<BR><font color=\"#3CA2DE\">&#187;</font> You are group member in this video presentation room. A group administrator is required to manage presentations.";
									}
					}

				//username
				//if ($current_user->$userName) $username=urlencode($current_user->$userName);
				//$username=preg_replace("/[^0-9a-zA-Z_]/","-",$username);

				$loggedin=0;
				$msg="";


				switch ($canAccess)
				{
				case "all":
					$loggedin=1;
					if (!$username)
					{
						$username="Guest".base_convert((time()-1224350000).rand(0,10),10,36);
						$visitor=1; //ask for username
					}
					break;
				case "members":
					if ($username) $loggedin=1;
					else $msg="<a href=\"/\">Please login first or register an account if you don't have one! Click here to return to website.</a>";
					break;
				case "list";
					if ($username)
						if (VWvideoPresentation::inList($userkeys, $accessList)) $loggedin=1;
						else $msg = "<a href=\"/\">$username, you are not in the video presentation access list.</a>";
						else $msg = "<a href=\"/\">Please login first or register an account if you don't have one! Click here to return to website.</a>";
						break;
				}

				if (!$room && !$visitor)
				{
					if ($options['landingRoom']=='username')  //can create
						{
						$room=$username;
						$administrator=1;
					}
					else $room = $options['lobbyRoom']; //or go to default
				}
				else if (!$room) $room = $options['lobbyRoom'];  //visitor can't create room


					global $wpdb;
				$table_name3 = $wpdb->prefix . "vw_vprooms";
				$wpdb->flush();

				//room owner?
				$rm = $wpdb->get_row("SELECT owner FROM $table_name3 where name='$room'");
				if ($rm) if ($rm->owner == $current_user->ID) $administrator=1;

					if (!$options['anyRoom']) //room must exist
						if ($room != $options['lobbyRoom'] || $options['landingRoom'] !='lobby') //not lobby
							{

							$wpdb->flush();
							$rm = $wpdb->get_row("SELECT count(id) as no FROM $table_name3 where name='$room'");
							if (!$rm->no)
							{
								$msg="Room $room does not exist!";
								$loggedin=0;
							}
						}

					//get post
					$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_name = '" . sanitize_file_name($room) . "' and post_type='presentation' LIMIT 0,1" );




				//paid room and not moderator? - check if purchased
				if ($options['myCred'] && !$administrator)
				{

					if ($postID)
					{

						$table_nameC = $wpdb->prefix . "myCRED_log";

						$mCa = get_post_meta( $postID, 'myCRED_sell_content', true );
						if ($mCa) if ($mCa['price']>0)
							{
								$buyer = $wpdb->get_col( $sql = "SELECT DISTINCT user_id FROM {$table_nameC} WHERE ref = 'buy_content' AND user_id = $userID AND ref_id = {$postID} AND creds < 0" );
								if (!$buyer)
								{
									$msg="Access purchase required!";
									$loggedin=0;
								}

							}
					}
				}



				//if room name == username -> administrator
				if (!$options['disableModeratorByName']) if ($room == $username) $administrator = 1;
					if (VWvideoPresentation::inList($userkeys, $options['moderatorList'])) $administrator = 1;

					$parameters = html_entity_decode($options['parameters']);

				if ($administrator)
				{

					$parameters = html_entity_decode($options['parametersAdmin']);
					//&change_background=0&administrator=0&regularCams=0&regularWatch=0&privateTextchat=0&externalStream=0&slideShow=0&publicVideosAdd=<0

					$extra_info = "<BR><font color=\"#3CA2DE\">&#187;</font> You are moderator in this video presentation room. You can set any user as main speaker or inquirer on public video panels, show presentation slides, kick users.";
				}

				//meta
				foreach (array('participants', 'access', 'parameters', 'layout', 'bonus','participantsBonus') as $meta)
				{
					${'m'. ucwords($meta)} = get_post_meta($postID, 'vw_'.$meta, true);
				}

				//$extra_info .= 'Participants:' . $mParticipants;

				//participants setting
				if (!$administrator)
				{
					switch ($mParticipants)
					{
					case 0: //Default
						break;

					case 1: //Invite Only
						if (VWvideoPresentation::inList($userkeys, $mAccess))
						{
							$activeParticipant = 1;
						}
						else
						{
							$loggedin=0;
							$msg .= ' ' . $username . ' <a href="/">,you are not in room invite list.</a>';
						}
						break;

					case 2: //Passive
						if (VWvideoPresentation::inList($userkeys, $mAccess))
						{
							$active = 1;
						}
						else $activeParticipant = 0;
						break;

					case 3: //Active
						$activeParticipant = 1;
						break;
					}
					if (!$activeParticipant) $parameters = html_entity_decode($options['parametersPassive']);
				}



				$parameters .= html_entity_decode($mParameters);
				$layoutCode = urlencode(html_entity_decode($options['layoutCode']));
				if ($mLayout) $layoutCode .= urlencode(html_entity_decode($mLayout));

				//replace bad words or expression
				$filterRegex=urlencode("(?i)(fuck|cunt)(?-i)");
				$filterReplace=urlencode(" ** ");

				//message
				$welcome=urlencode( html_entity_decode(stripslashes($options['welcome'])) . $extra_info);

				?>firstVar=fixed&server=<?php echo urlencode(trim($options['rtmp_server']))?>&serverRecord=<?php echo urlencode($options['rtmp_server_record'])?>&serverAMF=<?php echo $options['rtmp_amf']?>&serverRTMFP=<?php echo urlencode(trim($options['serverRTMFP']))?>&p2pGroup=<?php echo $options['p2pGroup']?>&supportRTMP=<?php echo $options['supportRTMP']?>&supportP2P=<?php echo $options['supportP2P']?>&alwaysRTMP=<?php echo $options['alwaysRTMP']?>&alwaysP2P=<?php echo $options['alwaysP2P']?>&disableBandwidthDetection=<?php echo $options['disableBandwidthDetection']?>&disableUploadDetection=<?php echo $options['disableBandwidthDetection']?>&room=<?php echo $room?>&welcome=<?php echo $welcome?>&username=<?php echo $username?>&msg=<?php echo urlencode($msg)?>&visitor=0&loggedin=<?php echo $loggedin?>&background_url=<?php echo urlencode( site_url() . "/wp-content/plugins/videowhisper-video-presentation/vp/templates/consultation/background.jpg")?>&camWidth=<?php echo $camRes[0];?>&camHeight=<?php echo $camRes[1];?>&camFPS=<?php echo $options['camFPS']?>&camBandwidth=<?php echo $options['camBandwidth'] ?>&camMaxBandwidth=<?php echo $options['camMaxBandwidth'] ?>&videoCodec=<?php echo $options['videoCodec']?>&codecProfile=<?php echo $options['codecProfile']?>&codecLevel=<?php echo $options['codecLevel']?>&soundCodec=<?php echo $options['soundCodec']?>&soundQuality=<?php echo $options['soundQuality']?>&micRate=<?php echo $options['micRate']?>&layoutCode=<?php echo $layoutCode; ?>&filterRegex=<?php echo $filterRegex?>&filterReplace=<?php echo $filterReplace?>&uploadsURL=<?php echo urlencode(self::path2url($options['uploadsPath'].'/'))?>&loadstatus=1<?php echo $parameters; ?>&debugmessage=<?php echo urlencode($debug)?>
<?php
				break;

				//! c_status
			case 'c_status':
				$cam = (int) $_POST['cam'];
				$mic = (int) $_POST['mic'];

				$timeUsed = $currentTime = (int) $_POST['ct'];
				$lastTime = (int) $_POST['lt'];

				$s = sanitize_file_name($_POST['s']);
				$u = sanitize_file_name($_POST['u']);
				$room_name = $r = sanitize_file_name($_POST['r']);

				$m = sanitize_text_field($_POST['m']);

				$ztime=time();

				//exit if no valid session name or room name
				if (!$s) VWvideoPresentation::rexit('noSession=1');
				if (!$r) VWvideoPresentation::rexit('noRoom=1');

				global $wpdb;
				$table_name = $wpdb->prefix . "vw_vpsessions";
				$wpdb->flush();

				$ztime=time();

				$sql = "SELECT * FROM $table_name where session='$s' and status='1'";
				$session = $wpdb->get_row($sql);
				if (!$session)
				{
					$sql="INSERT INTO `$table_name` ( `session`, `username`, `room`, `message`, `sdate`, `edate`, `status`, `type`) VALUES ('$s', '$u', '$r', '$m', $ztime, $ztime, 1, 1)";
					$wpdb->query($sql);
				}
				else
				{
					$sql="UPDATE `$table_name` set edate=$ztime, room='$r', username='$u', message='$m' where session='$s' and status='1'";
					$wpdb->query($sql);
				}

				//do not clean more often than 25s (mysql table invalidate)
				$lastClean = 0; $cleanNow = false;
				$lastCleanFile = $options['uploadsPath'] . 'lastclean.txt';

				if (file_exists($lastCleanFile)) $lastClean = file_get_contents($lastCleanFile);
				if (!$lastClean) $cleanNow = true;
				else if ($ztime - $lastClean > 25) $cleanNow = true;

					if ($cleanNow)
					{
						if (!$options['onlineExpiration']) $options['onlineExpiration'] = 310;
						$exptime=$ztime-$options['onlineExpiration'];
						$sql="DELETE FROM `$table_name` WHERE edate < $exptime";
						$wpdb->query($sql);
						file_put_contents($lastCleanFile, $ztime);
					}

				$maximumSessionTime=0; //900000ms=15 minutes

				$disconnect=""; //anything else than "" will disconnect with that message
				?>timeTotal=<?php echo $maximumSessionTime?>&timeUsed=<?php echo $currentTime?>&lastTime=<?php echo $currentTime?>&disconnect=<?php echo $disconnect?>&loadstatus=1<?php
				break;

			case 'c_logout':
				header("Location: /?Disconnected=" . urlencode($message));
				break;

				//! vc_chatlog
			case 'vc_chatlog':

				//Public and private chat logs
				$private = sanitize_file_name( $_POST['private']); //private chat username, blank if public chat
				$username = sanitize_file_name($_POST['u']);
				$session = sanitize_file_name($_POST['s']);
				$room = sanitize_file_name($_POST['r']);
				$message = sanitize_text_field( $_POST['msg'] );
				$time = (int) ($_POST['msgtime']);

				//do not allow uploads to other folders

				if (!$room)
				{
					echo 'error=NoRoom';
					exit;
				}

				$message = strip_tags($message,'<p><a><img><font><b><i><u>');

				//generate same private room folder for both users
				if ($private)
				{
					if ($private > $session) $proom=$session ."_". $private;
					else $proom=$private ."_". $session;
				}

				$dir=$options['uploadsPath'];
				if (!file_exists($dir)) mkdir($dir);

				$dir.="/$room";
				if (!file_exists($dir)) mkdir($dir);

				if ($proom)
				{
					$dir.="/$proom";
					if (!file_exists($dir)) mkdir($dir);
				}

				$day=date("y-M-j",time());

				$dfile = fopen($dir."/Log$day.html","a");
				fputs($dfile,$message."<BR>");
				fclose($dfile);

				//update html chat log

				$pos = strpos($message,': ')+1;
				$message = substr($message, $pos); //message without username

				if ($message)
				{
					$table_chatlog = $wpdb->prefix . "vw_vmls_chatlog";
					$ztime = time();

					$sql="INSERT INTO `$table_chatlog` ( `username`, `room`, `message`, `mdate`, `type`) VALUES ('$username', '$room', '$message', $ztime, '1')";
					$wpdb->query($sql);
				}

				?>loadstatus=1<?php
				break;


				//! snapshots
			case 'vw_snapshots':


				$stream = sanitize_file_name($_GET['name']);
				$room_name = sanitize_file_name($_GET['room']);

				if (strstr($stream,'.php')) VWvideoPresentation::rexit('badStreamExtension=1');
				if (!$stream) VWvideoPresentation::rexit('missingStreamArgument=1');
				if (!$room_name) VWvideoPresentation::rexit('missingRoomArgument=1');

				//get jpg bytearray
				$jpg = $GLOBALS["HTTP_RAW_POST_DATA"];
				if (!$jpg) $jpg = file_get_contents("php://input");

				//setup folders if needed
				$dir=$options['uploadsPath'];
				if (!file_exists($dir)) mkdir($dir);
				@chmod($dir, 0777);
				$dir.="/_sessions";
				if (!file_exists($dir)) mkdir($dir);
				@chmod($dir, 0777);

				$filepath = $dir .'/$stream';

				// save file
				$fp=fopen($filepath.".jpg","w");
				if ($fp)
				{
					fwrite($fp,$jpg);
					fclose($fp);
				}

				//generate thumbnail
				$source = @imagecreatefromjpeg($filepath.".jpg");
				$destination = @imagecreatetruecolor(240,180);
				@imagecopyresized($destination, $source, 0, 0, 0, 0, 240, 180,  @imagesx($source), @imagesy($source));
				@imagejpeg($destination,$filepath . "_240.jpg",90);
				$destination2 = @imagecreatetruecolor(64,48);
				@imagecopyresized($destination2, $destination, 0, 0, 0, 0, 64, 48,  @imagesx($destination), @imagesy($destination));
				@imagejpeg($destination2,$filepath . "_64.jpg",95);

				break;

				//! v4 Presentation AJAX calls
				//! vw_files
			case 'vw_files':
				if ($_GET["room"]) $room=sanitize_file_name($_GET["room"]);
				if ($_POST["room"]) $room=sanitize_file_name($_POST["room"]);

				if (!$room) exit;

				echo '<files>';

				$dir=$options['uploadsPath'];
				if (!file_exists($dir)) mkdir($dir);
				$dir.="/$room";
				if (!file_exists($dir)) mkdir($dir);

				$handle=opendir($dir);
				while
				(($file = readdir($handle))!==false)
				{
					if (($file != ".") && ($file != "..") && (!is_dir("$dir/".$file)))
						echo "<file file_name=\"".$file."\" file_size=\"".filesize("$dir/".$file)."\" file_path=\"" . urlencode($dir.'/'.$file). "\"/>";
				}
				closedir($handle);

				echo '</files>';
				break;

				//! vw_upload
			case 'vw_upload':
				if (!is_user_logged_in()) exit;

				if ($_GET["room"]) $room=sanitize_file_name($_GET["room"]);
				if ($_POST["room"]) $room=sanitize_file_name($_POST["room"]);

				$slides = sanitize_file_name($_GET["slides"]);
				$addSlide = sanitize_file_name($_GET["addSlide"]);

				$filename=sanitize_file_name($_FILES['vw_file']['name']);

				if (!$room) exit;
				if (strstr($filename,".php")) $filename = ""; //duplicate extension not allowed
				$filename = preg_replace(array('#[\\s]+#', '#[^A-Za-z0-9\. -]+#'), array('_', ''), $filename);

				if (!$filename) exit;

				$dir=$options['uploadsPath'];
				if (!file_exists($dir)) mkdir($dir);
				
				$destination = $options['uploadsPath'] . "/$room/";
				if (!file_exists($destination)) mkdir($destination);

				if ($slides)
				{
					$destination .= "slides/";
					if (!file_exists($destination)) mkdir($destination);
				}

				//verify extension
				$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

				$allowed = array('swf','jpg','jpeg','png','gif','txt','doc','docx','pdf', 'mp4', 'flv', 'avi', 'mpg', 'mpeg', 'ppt','pptx', 'pps', 'ppsx', 'doc', 'docx', 'odt', 'odf', 'rtf', 'xls', 'xlsx');

				if (in_array($ext,$allowed))
				{
					move_uploaded_file($_FILES['vw_file']['tmp_name'], $destination . $filename);

					if ($slides && $addSlide)
					{

						$source = VWvideoPresentation::path2url($destination . $filename);
						$root_url = VWvideoPresentation::path2url($options['uploadsPath'] . '/');
						$label = basename($filename, strrchr($filename, '.'));
						$type = 'Graphic';

						if ( in_array($ext, array('ppt', 'pptx', 'pps', 'ppsx', 'txt', 'doc', 'docx', 'odt', 'odf', 'rtf', 'xls', 'xlsx')) )  VWvideoPresentation::importPPT($room, $label, $destination . $filename, $root_url);
						if ( in_array($ext, array('pdf')) )  VWvideoPresentation::importPDF($room, $label, $destination . $filename, $root_url);
						if ( in_array($ext, array('png', 'jpg', 'swf', 'jpeg')) )  VWvideoPresentation::addSlide($room, $label, $source, $type);
					}

					$debug = $destination . $filename;

					echo 'debug='.urlencode($debug). '&';

				}else echo 'uploadFailed=badExtension&';

				echo 'loadstatus=1';

				break;

			case 'vw_fdelete':

				if (!is_user_logged_in()) exit;

				$room = sanitize_file_name($_GET["room"]);
				$filename = sanitize_file_name($_GET["filename"]);

				if (!$room) exit;
				if (!$filename) exit;

				unlink($options['uploadsPath'] . "/$room/$filename");

				break;

				//! vw_slides
			case 'vw_slides':
				$room=sanitize_file_name($_POST['room']);
				if (!$room) exit;

				$dir = $options['uploadsPath'] ."/$room";
				if (!file_exists($dir)) @mkdir($dir);
				$dir .= "/slides";
				if (!file_exists($dir)) @mkdir($dir);

				if (file_exists($dir . "/slideshow.xml")) echo file_get_contents($dir . "/slideshow.xml");
				else echo "<SLIDES></SLIDES>";
				break;

			case 'vw_slidesa':
				$room = sanitize_file_name($_POST['room']);


				if (!$room) exit;

				$label = $_POST['label'];
				$source = $_POST['source'];
				$type = $_POST['type'];

				VWvideoPresentation::addSlide($room, $label, $source, $type);


				echo 'loadstatus=1';

				break;
			
			case 'vw_slidecam':
				$room=sanitize_file_name($_POST['room']);

				if (!$room) exit;

				$stream=$_POST['stream'];
				$recording=$_POST['recording'];
				$rectime=$_POST['rectime'];

				VWvideoPresentation::addData($room, $stream, "stream=$stream&duration=$rectime", 'Stream');


				echo 'loadstatus=1';
				break;

			case 'vw_slidesd':
				if (!is_user_logged_in())
				{
					echo 'success=0&msg=' . urlencode('login-required');
					exit;
				}

				$room = sanitize_file_name($_POST['room']);
				if (!$room) exit;

				$label = sanitize_file_name($_POST['label']);
				$index = sanitize_file_name($_POST['ix']);
				$slide = sanitize_file_name($_POST['id']);


				$filename = $options['uploadsPath']  . "/$room/slides/slideshow.xml";
				if (file_exists($filename)) $txt = implode(file($filename)); else exit;

				$txt=preg_replace("/<SLIDE index=\"$index\" label=\"$label\" [^>]+ \/>\r/","",$txt);

				//some cleanup
				$txt=str_replace("  "," ",$txt);
				$txt=str_replace("\r \r","\r",$txt);
				$txt=str_replace("\r\r","\r",$txt);

				//assign good order numbers
				preg_match_all("|<SLIDE (.*) />|U",  $txt, $out, PREG_SET_ORDER);
				$k=1;
				for ($i=0;$i<count($out);$i++)
				{
					$repl=preg_replace('/index="(\d+)"/','index="'.sprintf("%02d",$k++).'"',$out[$i][0]);
					$txt=str_replace($out[$i][0],$repl,$txt);
				}

				// save file
				$fp=fopen($filename,"w");
				if ($fp)
				{
					fwrite($fp, $txt);
					fclose($fp);
				}

				//delete slide files

				if ($slide != '')
				{
					$dir = $options['uploadsPath'];
					$dir.="/$room";
					$dir.='/slides';
					$dir.="/$slide";

					if (file_exists($dir))
					{
						$files = glob($dir . '/*'); // get all file names
						foreach($files as $file){ // iterate files
							if(is_file($file))
								unlink($file); // delete file
						}
					}
				}

				echo "&slide=$slide&loadstatus=1";

				break;


				//! comments
			case 'comments':
				echo '<comments>';

				foreach (array('_session', 'room', 'slide') as $vname)
				{
					${$vname} = sanitize_file_name($_POST[$vname]);
				}

				if (!$room) exit;

				$slide = (int) $slide;
				if (!$slide) $slide='0';

				$destination = $options['uploadsPath']  . '/' . $room .'/';
				if (!file_exists($destination)) mkdir($destination);

				$destination .= 'slides/';
				if (!file_exists($destination)) mkdir($destination);

				$destination .= $slide . '/';
				if (!file_exists($destination)) mkdir($destination);

				$comments = VWvideoPresentation::varLoad($destination . '_comments');

				if (is_array($comments))
				{
					//comment types
					$types['Text'] = 1;
					$types['Video'] = 2;
					$types['Audio'] = 3;
					$types['File'] = 4;
					$types['Whiteboard'] = 5;
					$code_types = array_flip($types);

					$i=0;
					foreach ($comments as $id => $comment)
					{
						echo '<comment index="'.(++$i).'" id="' . $id . '" data="' . htmlspecialchars($comment['data']) . '" start="' .  $comment['start']  . '" duration="' .  $comment['duration']  . '" order="' .  $comment['order'] .'" created="'.date("F j, Y, g:i a", $comment['rdate']). '" email="' .  $comment['aid']  .'" type="'.$code_types[$comment['type']].'" />';

						if ($comment['order'] != $i) $comments[$id]['order'] = $i; //update order if not set right

					}

				}
				echo '</comments>';

				break;

			case 'comment-edit':

				foreach (array( '_session', 'room', 'slide', 'type', 'data', 'add', 'start', 'duration', 'del', 'id', 'ID', 'Slideshow', 'Slide', 'Type', 'Author', 'Start', 'Duration', 'Data') as $vname)
				{
					${$vname} = sanitize_text_field($_POST[$vname]);
				}

				$room = sanitize_file_name($room);
				if (!$room) exit;

				$tid = $room;

				$sid = $slide = (int) $slide;
				$ID = (int) $ID;

				//path to slide contents
				$dir = $options['uploadsPath'];
				if (!file_exists($dir)) mkdir($dir);
				$dir.="/$room";
				if (!file_exists($dir)) mkdir($dir);
				$dir.='/slides';
				if (!file_exists($dir)) mkdir($dir);
				$dir.="/$slide";
				if (!file_exists($dir)) mkdir($dir);


				//add
				if ($add)
				{
					//comment types
					$types['Text'] = 1;
					$types['Video'] = 2;
					$types['Audio'] = 3;
					$types['File'] = 4;
					$types['Whiteboard'] = 5;
					$code_types = array_flip($types);


					$status = 1;

					$id = VWvideoPresentation::commentAdd($dir, $room, $sid, $data, $types[$type], $start, $duration, $_uid, $status);
					echo 'success=1&id='.$id.'&msg='. urlencode(__('Comment added successfully!'));
					exit;
				}

				//edit
				if ($ID)
				{
					echo 'success=1&id='.$ID.'&msg='. urlencode(__('Not implemented, yet!'));
					exit;
				}

				//delete
				if ($del)
				{
					$id = (int) $id;

					//Check owner
					//only owner can delete thread comments

					if ($tid)
					{

						$comments = VWvideoPresentation::varLoad($dir . '/_comments');
						$whiteboard = VWvideoPresentation::varLoad($dir . '/_whiteboard');

						$c0=count($comments);

						$cd=$wd = 0;

						if (is_array($comments))
						{
							$comment = $comments[$id];

							$commentStart = $comment['start']*1000; //in ms wb time
							$commentEnd = ($comment['start']+$comment['duration'])*1000; //in ms wb time

							//delete  whiteboard elements occuring at same time from same author
							if (is_array($whiteboard))
								foreach ($whiteboard as $idw => $element)
								{
									if ($element['start'] >= $commentStart && $element['end'] <= $commentEnd && ($element['aid'] == $comment['aid'] || !$element['aid']))
									{
										unset($whiteboard[$idw]);
										$wd++;
									}
								}

							//delete comment
							unset($comments[$id]);
							$cd++;
						}

						//save updated data
						if (is_array($whiteboard)) $whiteboard2 = array_values($whiteboard);
						else $whiteboard2 = array();
						VWvideoPresentation::varSave($dir . '/_whiteboard', $whiteboard2);

						if (is_array($comments))  $comments2 = array_values($comments);
						else $comments2 = array();
						VWvideoPresentation::varSave($dir . '/_comments', $comments2);

						$aff .= "&whiteboard=" . $wd;
						$aff .= "&comments=" . $cd;

						echo 'success=1&id='.$id.$aff.'&dbg='.urlencode($c0).'&msg='. urlencode(__('Comment deleted successfully!'));
					}
					else echo 'success=0&msg=' . urlencode('wrong-thread-not-moderator');

					exit;
				}
				break;

			case 'comment-upload':
				foreach (array('room', 'slide', '_session', 'start', 'duration') as $vname)
				{
					${$vname} = sanitize_text_field($_GET[$vname]);
				}

				$tid = $room;

				$sid = $slide = (int) $slide;
				if (!$tid) exit;

				if ($tid)
				{

					$filename=sanitize_file_name($_FILES['vw_file']['name']);

					if (strstr($filename,'.php')) $filename = '';
					if (preg_replace('([^\w\s\d\-_~,;:\[\]\(\).])', '', $filename) != $filename) $filename = '' ;
					if (preg_replace('([\.]{2,})', '', $filename) != $filename) $filename = '' ;

					if (!$filename)
					{
						echo 'success=0&msg=' . urlencode('bad-filename');
						exit;
					}

					$dir = $options['uploadsPath'];
					if (!file_exists($dir)) mkdir($dir);
					$dir.="/$room";
					if (!file_exists($dir)) mkdir($dir);
					$dir.='/slides';
					if (!file_exists($dir)) mkdir($dir);
					$dir.="/$slide";
					if (!file_exists($dir)) mkdir($dir);

					$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

					$allowed = array('swf','jpg','jpeg','png','gif', 'pdf', 'mp3','wav', 'aac', 'ogg','3gp', '3g2', 'avi', 'f4v', 'flv', 'm2v', 'm4p', 'm4v', 'mp2', 'mkv', 'mov', 'mp4', 'mpg', 'mpe', 'mpeg', 'mpv', 'mwv', 'ogv', 'rm', 'rmvb', 'svi','ts', 'qt', 'vob', 'webm', 'wmv','ppt','pptx', 'pps', 'ppsx', 'txt', 'doc', 'docx', 'odt', 'odf', 'rtf', 'xls', 'xlsx');

					if (in_array($ext, $allowed))
					{
						move_uploaded_file($_FILES['vw_file']['tmp_name'], $dir .'/'. $filename);

						$source = $rootURL. $dir .'/'. $filename;
						$label = basename($filename, ".".$ext);
						$prefix = $tid.'-'.$sid.'-';
						$status = 1;

						$imported = 0;

						//comment types
						$types['Text'] = 1;
						$types['Video'] = 2;
						$types['Audio'] = 3;
						$types['File'] = 4;
						$types['Whiteboard'] = 5;
						$code_types = array_flip($types);

						//  if ( in_array($ext, array('pdf', 'png', 'jpg', 'swf', 'jpeg', 'ppt', 'pptx', 'pps', 'ppsx', 'txt', 'doc', 'docx', 'odt', 'odf', 'rtf', 'xls', 'xlsx')) )

						//video
						if (in_array($ext, array('3gp', '3g2', 'avi', 'f4v', 'flv', 'm2v', 'm4p', 'm4v', 'mp2', 'mkv', 'mov', 'mp4', 'mpg', 'mpe', 'mpeg', 'mpv', 'mwv', 'ogv', 'rm', 'rmvb', 'svi','ts', 'qt', 'vob', 'webm', 'wmv')) )
						{
							$type = $types['Video'];
							$data = 'stream=mp4:' . urlencode($prefix . $label) . '.mp4';
							VWvideoPresentation::commentImportVideo($dir, $tid, $sid, $data, $type, $start, $_uid, $status, $dir, $filename, $prefix . $label );
							$imported = 1;
						}

						//audio
						if (in_array($ext, array('mp3', 'wav', 'aac', 'ogg')) )
						{
							$type = $types['Audio'];
							$data = 'stream=mp3:' . urlencode($prefix . $label) . '.mp3';
							VWvideoPresentation::commentImportAudio($dir, $tid, $sid, $data, $type, $start, $_uid, $status, $dir, $filename, $prefix . $label );
							$imported = 1;
						}

						//add file download
						if (!$imported)
						{
							$type = $types['Text'];
							$data = 'text=' . urlencode("File: <U><A HREF=\"$source\">$label</A></U>");

							VWvideoPresentation::commentAdd($dir, $tid, $sid, $data, $type, $start, $duration, $_uid, $status );
						}


					}

					echo 'success=1&name='. urlencode($filename);
				}
				else echo 'success=0&msg=' . urlencode('no-thread');

				break;

			case 'comment-webcam':

				if (!is_user_logged_in())
				{
					echo 'success=0&msg=' . urlencode('login-required');
					exit;
				}

				foreach (array( '_session', 'room', 'stream', 'recording', 'rectime') as $vname)
				{
					${$vname} = sanitize_text_field($_POST[$vname]);
				}

				foreach (array( 'slide', 'start', 'content') as $vname)
				{
					${$vname} = sanitize_text_field($_GET[$vname]);
				}

				$sid = (int) $slide;
				$tid = $room;

				if (!$room) exit;


				//path to slide contents
				$dir = $options['uploadsPath'];
				if (!file_exists($dir)) mkdir($dir);
				$dir.="/$room";
				if (!file_exists($dir)) mkdir($dir);
				$dir.='/slides';
				if (!file_exists($dir)) mkdir($dir);
				$dir.="/$slide";
				if (!file_exists($dir)) mkdir($dir);

				if ($tid)
				{
					//comment types
					$types['Text'] = 1;
					$types['Video'] = 2;
					$types['Audio'] = 3;
					$types['File'] = 4;
					$types['Whiteboard'] = 5;
					$code_types = array_flip($types);

					if ($content == 'audio') $type = $types['Audio'];
					else $type = $types['Video'];

					$data = 'stream=' . urlencode($stream);
					$status = 1;

					VWvideoPresentation::commentAdd($dir, $tid, $sid, $data, $type, $start, $rectime + 1, $_uid, $status );

				}
				else echo 'success=0&msg=' . urlencode('nope');

				break;


				//! whiteboard

			case 'whiteboard':

				foreach (array('_session', 'room', 'slide') as $vname)
				{
					${$vname} = sanitize_text_field($_POST[$vname]);
				}

				$slide = (int) $slide;

				$room = sanitize_file_name($room);
				if (!$room) exit;

				echo '<whiteboard>';

				//path to slide contents
				$dir = $options['uploadsPath'];
				if (!file_exists($dir)) mkdir($dir);
				$dir.="/$room";
				if (!file_exists($dir)) mkdir($dir);
				$dir.='/slides';
				if (!file_exists($dir)) mkdir($dir);
				$dir.="/$slide";
				if (!file_exists($dir)) mkdir($dir);

				$whiteboard = VWvideoPresentation::varLoad($dir . '/_whiteboard');

				if (is_array($whiteboard))
				{
					$i=0;
					foreach ($whiteboard as $id => $element)
					{
						echo '<item index="'.(++$i).'" id="' . $id . '" data="' . htmlspecialchars($element['data']) . '" start="' .  $element['start']  . '" end="' .  $element['end']  . '"  created="'.date("F j, Y, g:i a", $element['rdate']). '" />';

					}
				}

				echo '</whiteboard>';

				break;

			case 'whiteboard-add':

				foreach (array( '_session', 'room', 'slide', 'start', 'duration', 'recordings') as $vname)
				{
					${$vname} = sanitize_text_field($_POST[$vname]);
				}

				$tid = $room;
				$sid = $slide = (int) $slide;


				if (!is_user_logged_in())
				{
					echo 'success=0&msg=' . urlencode('login-required');
					exit;
				}

				$current_user = wp_get_current_user();
				$aid = $current_user->display_name;
				//$aid = $_uid;

				$end = $start + $duration;
				$rdate = time();
				$status = 1;

				//path to slide contents
				$dir = $options['uploadsPath'];
				if (!file_exists($dir)) mkdir($dir);
				$dir.="/$room";
				if (!file_exists($dir)) mkdir($dir);
				$dir.='/slides';
				if (!file_exists($dir)) mkdir($dir);
				$dir.="/$slide";
				if (!file_exists($dir)) mkdir($dir);

				$whiteboard = VWvideoPresentation::varLoad($dir . '/_whiteboard');

				if (!is_array($whiteboard)) $whiteboard = array();

				$id = count($whiteboard);

				if ($recordings>0)
				{
					for ($i=0; $i<$recordings; $i++)
					{
						$data = $_POST['r'.$i];
						$start1 = $start + $_POST['r'.$i.'_time'];

						//'tid' =>$tid, 'sid' => $sid,
						$element = array('data' => $data, 'start' => $start1, 'end' => $end, 'rdate' => $rdate, 'aid' => $aid, 'status' => $status);
						$whiteboard[] = $element;

					}

					VWvideoPresentation::varSave($dir . '/_whiteboard', $whiteboard);

					echo 'success=1&msg='. urlencode(__('Whiteboard elements added successfully!'));
					exit;
				}

				echo 'success=0&msg='. urlencode(__('No whiteboard elements to add!'));

				break;
				// - Presentation
				


			case 'translation':
				echo html_entity_decode(stripslashes($options['translationCode']));

				break;   default:
				echo 'task=' . $_GET['task'] . '&status=notImplemented';
			}

			//end vwcns_callback
			die();
		}

		//! Presentation Functions
		function addSlide($room, $label, $source, $type)
		{
			VWvideoPresentation::addData($room, $label, "src=$source", $type);
		}

		function addData($room, $label, $data, $type)
		{

			if (!is_user_logged_in())
			{
				echo 'success=0&msg=' . urlencode('login-required');
				exit;
			}

			$options = get_option('VWvideoPresentationOptions');

			$filename = $options['uploadsPath'] . "/$room/slides/slideshow.xml";
			if (file_exists($filename)) $txt = implode(file($filename));
			if (!$txt) $txt="<SLIDES>\r</SLIDES>";

			$txt = str_ireplace("</SLIDES>"," <SLIDE index=\"00\" label=\"$label\" type=\"$type\" data=\"$data\" />\r</SLIDES>",$txt);

			//assign good order numbers
			preg_match_all("|<SLIDE (.*) />|U",  $txt, $out, PREG_SET_ORDER);
			$k=1;
			for ($i=0;$i<count($out);$i++)
			{
				$repl=preg_replace('/index="(\d+)"/','index="'.sprintf("%02d",$k++).'"',$out[$i][0]);
				$txt=str_replace($out[$i][0],$repl,$txt);
			}

			// save file
			$fp=fopen($filename,"w");
			if ($fp)
			{
				fwrite($fp, $txt);
				fclose($fp);
			}
		}

		/*
PPT, PDF conversion requires:
1. Apache_OpenOffice
2. unoconv
3. ImageMagick
*/

		function importPPT($room, $label, $filename, $root_url)
		{
			if (!is_user_logged_in())
			{
				echo 'success=0&msg=' . urlencode('login-required');
				exit;
			}

			$filepath =  $filename;

			$options = get_option('VWvideoPresentationOptions');

			$folder = $options['uploadsPath'] .  "/$room/slides/";
			$outpath = $folder;

			$newFolder = $outpath . $label . '/';


			if (!file_exists($newFolder)) mkdir($newFolder);

			$debug = $outpath;

			/*
	Paths:
	[~]# which unoconv
	/usr/bin/unoconv
	[~]# which convert
	/usr/bin/convert
	*/

			//convert to pdf
			$cmd = $options['unoconvPath'] . ' -f pdf -o \'' . $outpath . $label . '.pdf\' \'' . $filepath . '\'';
			exec($cmd, $output, $returnvalue);

			//$debug = $cmd;

			//convert to png
			$cmd = $options['convertPath'] . ' \'' . $outpath . $label . '.pdf\' \'' . $newFolder . '%03d.png\'';
			exec($cmd, $output, $returnvalue);

			$files = scandir($newFolder);
			foreach ($files as $file)
			{
				$ext = pathinfo($file, PATHINFO_EXTENSION);
				$no = basename($file, strrchr($file, '.'));
				if ($ext == 'png') VWvideoPresentation::addSlide($room, $label . ' #' . $no, $root_url . $folder . $label .'/'. $file, 'Graphic');
			}

			echo 'importDebug=' . $debug . '&';

		}

		function importPDF($room, $label, $filename, $root_url)
		{
			if (!is_user_logged_in())
			{
				echo 'success=0&msg=' . urlencode('login-required');
				exit;
			}

			$filepath = $filename;

			$options = get_option('VWvideoPresentationOptions');

			$folder =  $options['uploadsPath'] . "/$room/slides/";
			$outpath = $folder;

			$newFolder = $outpath . $label . '/';

			if (!file_exists($newFolder)) mkdir($newFolder);

			//convert to png
			$cmd = $options['convertPath'] . ' \'' . $filepath . '\' \'' . $newFolder . '%03d.png\'';
			exec($cmd, $output, $returnvalue);

			$files = scandir($newFolder);
			foreach ($files as $file)
			{
				$ext = pathinfo($file, PATHINFO_EXTENSION);
				$no = basename($file, strrchr($file, '.'));
				if ($ext == 'png') VWvideoPresentation::addSlide($room, $label . ' #' . $no, $root_url . $folder . $label .'/'. $file, 'Graphic');
			}


			echo 'importDebug=' . $debug . '&';

		}

		function commentAdd($dir, $tid, $sid, $data, $type, $start, $duration, $aid, $status )
		{
			if (!is_user_logged_in())
			{
				echo 'success=0&msg=' . urlencode('login-required');
				exit;
			}

			$current_user = wp_get_current_user();

			$comments = VWvideoPresentation::varLoad($dir . '/_comments');
			if (!is_array($comments)) $comments = array();
			$id = count($comments);

			$comment = array(
				'tid'=>$tid,
				'sid' => $sid,
				'data' => $data,
				'type'=> $type,
				'start'=> $start,
				'duration' => $duration,
				'order' => $id,
				'rdate' => time(),
				'aid' => $current_user->display_name,
				'uid' => $current_user->ID,
				'status' => $status
			);

			$comments[$id] =  $comment;

			VWvideoPresentation::varSave($dir . '/_comments', $comments);

			echo 'success=1&id='.$id.'&msg='. urlencode(__('Comment added successfully!'));

			return $id;
		}

		function commentImportVideo($dir, $tid, $sid, $data, $type, $start, $_uid, $status, $path, $filename, $label )
		{
			if (!is_user_logged_in())
			{
				echo 'success=0&msg=' . urlencode('login-required');
				exit;
			}


			$options = get_option('VWvideoPresentationOptions');

			//rtmp video streams folder
			$streams_path = $options['streamsPath'];

			if (!file_exists($streams_path))
			{
				echo 'importError=StreamsPathMissing';
				exit;
			}

			$stream = $label;

			//ffmpeg
			$ffmpegcall = $options['ffmpegPath'] . " -y -vb 512k -vcodec libx264 -coder 0 -bf 0 -level 3.1 -g 30 -maxrate 768k -acodec libfaac -ac 2 -ar 22050 -ab 96k -x264opts vbv-maxrate=364:qpmin=4:ref=4";

			//mp4
			$output_file = $streams_path . $stream . ".mp4";
			$log_file =  $dir . '/' . $stream  . ".txt";
			$filepath =  $dir . '/' .  $filename;

			$cmd = $ffmpegcall . " '$output_file' -i '$filepath' >&'$log_file' &";
			exec($cmd, $output, $returnvalue);

			//get duration
			$cmd = $options['ffmpegPath'] . ' -y -i "'. $filepath . '" 2>&1';
			$info = shell_exec($cmd);
			preg_match('/Duration: (.*?),/', $info, $matches);
			$duration = explode(':', $matches[1]);
			$videoDuration = intval($duration[0]) * 3600 + intval($duration[1]) * 60 + intval($duration[2]);

			if (!$videoDuration) $videoDuration = 30;

			$duration = $videoDuration + 3;

			VWvideoPresentation::commentAdd($dir, $tid, $sid, $data, $type, $start, $duration, $_uid, $status );

			//$debug = "$filepath++$output_file";

			echo 'importDebug=' . $debug . '&';
		}

		function commentImportAudio($dir, $tid, $sid, $data, $type, $start, $_uid, $status, $path, $filename, $label )
		{
			if (!is_user_logged_in())
			{
				echo 'success=0&msg=' . urlencode('login-required');
				exit;
			}

			$options = get_option('VWvideoPresentationOptions');

			//rtmp video streams folder
			$streams_path = $options['streamsPath'];

			if (!file_exists($streams_path))
			{
				echo 'importError=StreamsPathMissing';
				exit;
			}

			$stream = $label;

			//ffmpeg
			$ffmpegcall = $options['ffmpegPath'] . " -y -acodec libmp3lame";

			//mp3
			$output_file = $streams_path . $stream . ".mp3";
			$log_file =  $dir . '/' . $stream  . ".txt";
			$filepath =  $dir . '/' .  $filename;

			$cmd = $ffmpegcall . " '$output_file' -i '$filepath' >&'$log_file' &";
			exec($cmd, $output, $returnvalue);

			//get duration
			$cmd = $options['ffmpegPath'] . ' -y -i "'. $filepath . '" 2>&1';
			$info = shell_exec($cmd);
			preg_match('/Duration: (.*?),/', $info, $matches);
			$duration = explode(':', $matches[1]);
			$videoDuration = intval($duration[0]) * 3600 + intval($duration[1]) * 60 + intval($duration[2]);

			if (!$videoDuration) $videoDuration = 30;

			$duration = $videoDuration + 3;

			VWvideoPresentation::commentAdd($dir, $tid, $sid, $data, $type, $start, $duration, $_uid, $status );

			//$debug = "$filepath++$output_file";

			echo 'importDebug=' . $debug . '&';
		}

		//! tools
		function fixPath($p) {

			//adds ending slash if missing

			//    $p=str_replace('\\','/',trim($p));
			return (substr($p,-1)!='/') ? $p.='/' : $p;
		}

		function path2stream($path, $withExtension=true, $withPrefix=true)
		{
			$options = get_option( 'VWvideoPresentationOptions' );

			$stream = substr($path, strlen($options['streamsPath']));
			if ($stream[0]=='/') $stream = substr($stream, 1);

			if ($withPrefix)
			{
				$ext = pathinfo($stream, PATHINFO_EXTENSION);
				$prefix = $ext . ':';
			}else $prefix = '';

			if (!file_exists($options['streamsPath'] . '/' . $stream)) return '';
			elseif ($withExtension) return $prefix.$stream;
			else return $prefix.pathinfo($stream, PATHINFO_FILENAME);
		}

		function stream2path($stream)
		{

			$options = get_option( 'VWvideoPresentationOptions' );

			//mp4:
			if (strstr($stream, ':')) $stream = substr($stream, strpos($stream, ':') + 1);
			$path = $options['streamsPath'] .'/'. $stream;

			return $path;
		}

		function varSave($path, $var)
		{
			file_put_contents($path, serialize($var));
		}

		function varLoad($path)
		{
			if (!file_exists($path)) return false;

			return unserialize(file_get_contents($path));
		}

		function stringSave($path, $var)
		{
			file_put_contents($path, $var);
		}

		function stringLoad($path)
		{
			if (!file_exists($path)) return false;

			return file_get_contents($path);
		}


		//! ajax transcoding
		function vwcns_trans()
		{


			ob_clean();

			$stream = sanitize_file_name($_GET['stream']);
			$room = sanitize_file_name($_GET['room']);

			if (!$stream)
			{
				echo "No stream name provided!";
				return;
			}

			if (!$room)
			{
				echo "No room name provided!";
				return;
			}


			$options = get_option('VWvideoPresentationOptions');

			$uploadsPath = $options['uploadsPath'];
			if (!file_exists($uploadsPath)) mkdir($uploadsPath);
			//if (!$uploadsPath) echo "Missing uploadsPath!";

			$upath = $uploadsPath . "/$room/";
			if (!file_exists($upath)) mkdir($upath);

			$rtmp_server=$options['rtmp_server'];

			switch ($_GET['task'])
			{
			case 'mp4':

				if ( !is_user_logged_in() )
				{
					echo "Not authorised!";
					exit;
				}

				$cmd = "ps aux | grep '/i_$room -i rtmp'";
				exec($cmd, $output, $returnvalue);
				//var_dump($output);

				$transcoding = 0;

				foreach ($output as $line) if (strstr($line, "ffmpeg"))
					{
						$columns = preg_split('/\s+/',$line);
						echo "Transcoder Already Active (".$columns[1]." CPU: ".$columns[2]." Mem: ".$columns[3].")";
						$transcoding = 1;
					}



				if (!$transcoding)
				{

					global $current_user;
					get_currentuserinfo();

					global $wpdb;
					$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_name = '" . sanitize_file_name($stream) . "' and post_type='consultation' LIMIT 0,1" );

					if ($options['externalKeysTranscoder'])
					{
						$key = md5('vw' . $options['webKey'] . $current_user->ID . $postID);

						$keyView = md5('vw' . $options['webKey']. $postID);

						//?session&room&key&broadcaster&broadcasterid
						$rtmpAddress = $options['rtmp_serverX'] . '?'. urlencode('i_' . $room) .'&'. urlencode($room) .'&'. $key . '&1&' . $current_user->ID . '&videowhisper';
						$rtmpAddressView = $options['rtmp_server'] . '?'. urlencode('ffmpeg_' . $stream) .'&'. urlencode($room) .'&'. $keyView . '&0&videowhisper';

					}
					else
					{
						$rtmpAddress = $options['rtmp_serverX'];
						$rtmpAddressView = $options['rtmp_server'];
					}

					echo "Transcoding '$stream' ($postID) to '$room'... <BR>";
					$log_file =  $upath . "videowhisper_transcoder.log";
					$cmd = $options['ffmpegPath'] .' ' .  $options['ffmpegTranscode'] . " -threads 1 -rtmp_pageurl \"http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] . "\" -rtmp_swfurl \"http://".$_SERVER['HTTP_HOST']."\" -f flv \"" .
						$rtmpAddress . "/i_". $room . "\" -i \"" . $rtmpAddressView ."/". $stream . "\" >&$log_file & ";

					//echo $cmd;
					exec($cmd, $output, $returnvalue);
					exec("echo '$cmd' >> $log_file.cmd", $output, $returnvalue);

					$cmd = "ps aux | grep '/i_$stream -i rtmp'";
					exec($cmd, $output, $returnvalue);
					//var_dump($output);

					foreach ($output as $line) if (strstr($line, "ffmpeg"))
						{
							$columns = preg_split('/\s+/',$line);
							echo "Transcoder Started (".$columns[1].")<BR>";
						}

				}

				$admin_ajax = admin_url() . 'admin-ajax.php';

				echo "<BR><a target='_blank' href='".$admin_ajax . "?action=vwcns_trans&task=html5&room=$room&stream=$room'> Preview </a> (open in Safari)";
				break;


			case 'close':
				if ( !is_user_logged_in() )
				{
					echo "Not authorised!";
					exit;
				}

				$cmd = "ps aux | grep '/i_$room -i rtmp'";
				exec($cmd, $output, $returnvalue);
				//var_dump($output);

				$transcoding = 0;
				foreach ($output as $line) if (strstr($line, "ffmpeg"))
					{
						$columns = preg_split('/\s+/',$line);
						$cmd = "kill -9 " . $columns[1];
						exec($cmd, $output, $returnvalue);
						echo "<BR>Closing ".$columns[1]." CPU: ".$columns[2]." Mem: ".$columns[3];
						$transcoding = 1;
					}

				if (!$transcoding)
				{
					echo "Transcoder not found for '$room'!";
				}

				break;
			case "html5";
?>
<p>iOS live stream link (open with Safari or test with VLC): <a href="<?php echo $options['httpstreamer']?>i_<?php echo $stream?>/playlist.m3u8"><br />
  <?php echo $stream?> Video</a></p>


<p>HTML5 live video embed below should be accessible <u>only in <B>Safari</B> browser</u> (PC or iOS):</p>
<?php
				echo do_shortcode('[videowhisperconsultation_hls channel="'.$stream.'"]');
?>
<p> Due to HTTP based live streaming technology limitations, video can have 15s or more latency. Use a browser with flash support for faster interactions based on RTMP. </p>
<p>Most devices other than iOS, support regular flash playback for live streams.</p>
</div>
<style type="text/css">
<!--
BODY
{
	margin:0px;
	background: #333;
	font-family: Arial, Helvetica, sans-serif;
	font-size: 14px;
	color: #EEE;
	padding: 20px;
}

a {
	color: #F77;
	text-decoration: none;
}
-->
</style>
<?php

				break;
			}
			die;
		}


		function adminOptionsDefault()
		{
			$upload_dir = wp_upload_dir();
			$root_url = plugins_url();
			$root_ajax = admin_url( 'admin-ajax.php?action=vmls&task=');

			return array(

				'disablePage' => '0',
				'disablePageC' => '0',
				'postTemplate' => '+plugin',
				'custom_post' => 'presentation',

				'userName' => 'display_name',
				'rtmp_server' => 'rtmp://[your-rtmp-server-ip-or-domain]/videowhisper',
				'rtmp_serverX' => 'rtmp://[your-rtmp-server-ip-or-domain]/videowhisper-x',
				'rtmp_server_record' => 'rtmp://[your-rtmp-server-ip-or-domain]/videowhisper-record',

				'rtmp_amf' => 'AMF3',

				'canAccess' => 'all',
				'accessList' => 'Super Admin, Administrator, Editor, Author, Contributor, Subscriber',

				'disableModeratorByName' => '0',
				'moderatorList' => 'Super Admin, Administrator, Editor',

				'canBroadcast' => 'members',
				'broadcastList' => 'Super Admin, Administrator, Editor, Author',
				'maxRooms' => '3',
				'accessLink' => 'site',
				'anyRoom' => '1',

				'wallet' =>'MyCred',
				'walletMulti'=>'2',

				'balancePage' => '',
				
				'myCred' => '1',
				'canSell' =>'Super Admin, Administrator, Editor, Author',

				'disableTranscoder' => '0',
				'httpstreamer' => 'http://localhost:1935/videowhisper-x/',
				'ffmpegPath' => '/usr/local/bin/ffmpeg',
				'ffmpegTranscode' => '-vcodec copy -acodec libfaac -ac 2 -ar 22050 -ab 96k',

				'unoconvPath' => '/usr/bin/unoconv',
				'convertPath' => '/usr/bin/convert',

				'uploadsPath' => $upload_dir['basedir'] . '/vw-presentation',
				'streamsPath' => '/home/[account]/public_html/streams',

				'landingRoom' => 'lobby',
				'lobbyRoom' => 'Lobby',

				'camResolution' => '480x360',
				'camFPS' => '30',

				'camBandwidth' => '75000',
				'camMaxBandwidth' => '200000',

				'videoCodec'=>'H264',
				'codecProfile' => 'main',
				'codecLevel' => '3.1',

				'soundCodec'=> 'Nellymoser',
				'soundQuality' => '9',
				'micRate' => '22',

				'serverRTMFP' => 'rtmfp://stratus.adobe.com/f1533cc06e4de4b56399b10d-1a624022ff71/',
				'p2pGroup' => 'VideoWhisper',
				'supportRTMP' => '1',
				'supportP2P' => '0',
				'alwaysRTMP' => '1',
				'alwaysP2P' => '0',
				'disableBandwidthDetection' => '0',

				'notifySubject'=>'You were invited to room: #room#',
				'notifyMessage'=>'Use this link to access room: <A HREF="#link">#room#</A>',
				'welcome' =>  "Welcome!<BR><font color=\"#3CA2DE\">&#187;</font> Click top bar icons to enable/disable features and panels. <BR><font color=\"#3CA2DE\">&#187;</font> Click any participant from users list for more options depending on your permissions. <BR><font color=\"#3CA2DE\">&#187;</font> Try pasting urls, youtube movie urls, picture urls, emails, twitter accounts as @videowhisper in your text chat. <BR><font color=\"#3CA2DE\">&#187;</font> Download daily chat logs from file list.",
				'layoutCode' => 'id=0&label=Chat&x=661&y=52&width=348&height=246&system=absolute&resize=true&move=true&title=Chat;
id=1&label=Files&x=855&y=563&width=338&height=233&system=absolute&resize=true&move=true&title=Files;
id=2&label=Users&x=660&y=310&width=189&height=480&system=absolute&resize=true&move=true&title=Participants;
id=3&label=RichMedia&x=10&y=50&width=642&height=535&system=absolute&resize=true&move=true&title=;
id=4&label=Video&x=20&y=330&width=322&height=295&system=absolute&resize=true&move=true&title=;
id=5&label=Webcam&x=20&y=90&width=266&height=253&system=absolute&resize=true&move=true&title=;
id=6&label=Form&x=100&y=100&width=409&height=164&system=absolute&resize=true&move=true&title=;
id=7&label=Slides&x=12&y=596&width=362&height=196&system=absolute&resize=true&move=true&title=;
id=8&label=Comments&x=384&y=595&width=272&height=194&system=absolute&resize=true&move=true&title=',
				'onlineExpiration' =>'310',
				'parameters' => '&bufferLive=0.2&bufferFull=0.2&bufferLivePlayback=0.2&bufferFullPlayback=0.2&files_enabled=1&file_upload=1&file_delete=1&chat_enabled=1&floodProtection=3&writeText=1&room_limit=200&showTimer=1&showCredit=1&disconnectOnTimeout=1&showCamSettings=1&advancedCamSettings=1&configureSource=1&disableVideo=0&disableSound=0&users_enabled=1&fillWindow=0&generateSnapshots=1&pushToTalk=1&change_background=0&administrator=0&regularCams=0&regularWatch=0&privateTextchat=0&externalStream=0&slideShow=0&publicVideosAdd=0&statusInterval=300000&slideComments=1&writeAnnotations=0&editAnnotations=0',
				'parametersPassive' => '&bufferLive=0.2&bufferFull=0.2&bufferLivePlayback=0.2&bufferFullPlayback=0.2&files_enabled=1&file_upload=0&file_delete=0&chat_enabled=1&floodProtection=10&writeText=0&room_limit=200&showTimer=1&showCredit=1&disconnectOnTimeout=1&showCamSettings=0&advancedCamSettings=0&configureSource=0&disableVideo=1&disableSound=1&users_enabled=1&fillWindow=0&generateSnapshots=1&pushToTalk=1&change_background=0&administrator=0&regularCams=0&regularWatch=0&privateTextchat=0&externalStream=0&slideShow=0&publicVideosAdd=0&statusInterval=300000&slideComments=1&writeAnnotations=0&editAnnotations=0',
				'parametersCustom' =>'&publicVideosN=1&publicVideo1=test&publicVideosMax=8',
				'parametersAdmin' => '&bufferLive=0.2&bufferFull=0.2&bufferLivePlayback=0.2&bufferFullPlayback=0.2&files_enabled=1&file_upload=1&file_delete=1&chat_enabled=1&floodProtection=3&writeText=1&room_limit=200&showTimer=1&showCredit=1&disconnectOnTimeout=1&showCamSettings=1&advancedCamSettings=1&configureSource=1&disableVideo=0&disableSound=0&users_enabled=1&fillWindow=0&generateSnapshots=1&pushToTalk=0&change_background=1&administrator=1&regularCams=1&regularWatch=1&privateTextchat=1&externalStream=1&slideShow=1&publicVideosAdd=1&statusInterval=60000&internalOpen=0&writeAnnotations=1&editAnnotations=1&restorePaused=0&videoControl=1&videoRecorder=1&webcamSlides=0&slideComments=1&selectCam=1&selectMic=1',
				'translationCode' => '<translations>
<t text="Files" translation="Files"/>
<t text="Background" translation="Background"/>
<t text="Download" translation="Download"/>
<t text="Size" translation="Size"/>
<t text="Name" translation="Name"/>
<t text="Chat" translation="Chat"/>
<t text="You are participating in room" translation="You are participating in room"/>
<t text="Available" translation="Available"/>
<t text="Request" translation="Request"/>
<t text="Away" translation="Away"/>
<t text="Busy" translation="Busy"/>
<t text="Set Speaker" translation="Set Speaker"/>
<t text="Set Inquirer" translation="Set Inquirer"/>
<t text="Kick" translation="Kick"/>
<t text="Block" translation="Block"/>
<t text="Private Chat" translation="Private Chat"/>
<t text="UnBlock" translation="UnBlock"/>
<t text="Watch (Privately)" translation="Watch (Privately)"/>
<t text="Please wait. Connecting..." translation="Please wait. Connecting..."/>
</translations>',
				'videowhisper' => 0
			);
		}


		function getAdminOptions()
		{
			$adminOptions = VWvideopresentation::adminOptionsDefault();

			$options = get_option('VWvideoPresentationOptions');
			
			if (!empty($options)) {
				foreach ($options as $key => $option)
					$adminOptions[$key] = $option;
			}
			
			update_option('VWvideoPresentationOptions', $adminOptions);
			
			return $adminOptions;
		}

		function options()
		{
			$options = VWvideopresentation::getAdminOptions();

			if (isset($_POST))
			{

				foreach ($options as $key => $value)
					if (isset($_POST[$key])) $options[$key] = $_POST[$key];
					
					update_option('VWvideoPresentationOptions', $options);
			}
			
	
			$optionsDefault = VWvideopresentation::adminOptionsDefault();


			$page_id = get_option("vw_vp_page_manage");
			if ($page_id != '-1' && $options['disablePage']!='0') VWvideopresentation::deletePages();

			$page_idC = get_option("vw_vp_page");
			if ($page_idC != '-1' && $options['disablePageC']!='0') VWvideopresentation::deletePages();

			$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'server';

?>
<div class="wrap">
<?php screen_icon(); ?>
<h2>VideoWhisper Video Presentation Settings</h2>

<h2 class="nav-tab-wrapper">
	<a href="<?php echo $_SERVER["REQUEST_URI"]; ?>&tab=server" class="nav-tab <?php echo $active_tab=='server'?'nav-tab-active':'';?>">Server</a>
    <a href="<?php echo $_SERVER["REQUEST_URI"]; ?>&tab=video" class="nav-tab <?php echo $active_tab=='video'?'nav-tab-active':'';?>">Video</a>
	<a href="<?php echo $_SERVER["REQUEST_URI"]; ?>&tab=integration" class="nav-tab <?php echo $active_tab=='integration'?'nav-tab-active':''; ?>">Integration</a>
   <a href="<?php echo $_SERVER["REQUEST_URI"]; ?>&tab=moderators" class="nav-tab <?php echo $active_tab=='moderators'?'nav-tab-active':''; ?>">Moderators</a>
    <a href="<?php echo $_SERVER["REQUEST_URI"]; ?>&tab=participants" class="nav-tab <?php echo $active_tab=='participants'?'nav-tab-active':''; ?>">Participants</a>
   <a href="<?php echo $_SERVER["REQUEST_URI"]; ?>&tab=sell" class="nav-tab <?php echo $active_tab=='sell'?'nav-tab-active':''; ?>">Billing / Paid Room</a>
    <a href="<?php echo $_SERVER["REQUEST_URI"]; ?>&tab=documentation" class="nav-tab <?php echo $active_tab=='documentation'?'nav-tab-active':''; ?>">Documentation</a>
    <a href="<?php echo $_SERVER["REQUEST_URI"]; ?>&tab=requirements" class="nav-tab <?php echo $active_tab=='requirements'?'nav-tab-active':''; ?>">Requirements</a>

</h2>

<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">

<?php
			switch ($active_tab)
			{

			case 'requirements':
?>
<h3>Requirements Overview</h3>

<h4>FFMPEG & Codecs</h4>
FFMPEG and specific codecs are required for transcoding live streams for Wowza mobile delivery, converting videos, extracting snapshots.
<BR><BR>
<?php
				echo "Path from settings: " . $options['ffmpegPath'] . '<br>';

				$cmd =$options['ffmpegPath'] . ' -version';
				exec($cmd, $output, $returnvalue);
				if ($returnvalue == 127)  echo "<b>Warning: not detected: $cmd</b>"; else
				{
					echo "<u>Detected:</u>";
					echo '<BR>' . $output[0];
					echo '<BR>' . $output[1];
				}

				$cmd =$options['ffmpegPath'] . ' -codecs';
				exec($cmd, $output, $returnvalue);

				//detect codecs
				if ($output) if (count($output))
					{
						echo "<br>Codec libraries:";
						foreach (array('h264', 'vp6','speex', 'nellymoser', 'fdk_aac', 'faac') as $cod)
						{
							$det=0; $outd="";
							echo "<BR>$cod : ";
							foreach ($output as $outp) if (strstr($outp,$cod)) { $det=1; $outd=$outp; };
							if ($det) echo "detected ($outd)"; else echo "<b>missing: configure and install FFMPEG with lib$cod if you don't have another library for that codec</b>";
						}
					}
?>
<BR><BR>You need only 1 AAC codec. Depending on <a href="https://trac.ffmpeg.org/wiki/Encode/AAC#libfaac">AAC library available on your system</a> you may need to update transcoding parameters. Latest FFMPEG also includes a native encoder (aac).

<h4>Unoconv</h4>
Unoconv is required for converting documents to accessible formats.
<BR><BR>
<?php
				echo "Path from settings: " . $options['unoconvPath'] . '<br>';

				$cmd =$options['unoconvPath'] . ' --version';
				$output = '';
				exec($cmd, $output, $returnvalue);
				if ($returnvalue == 127)  echo "<b>Warning: not detected: $cmd</b>"; else
				{
					echo "<u>Detected:</u>";
					echo '<BR>' . $output[0];
					echo '<BR>' . $output[1];
				}
?>
<h4>ImageMagick Convert</h4>
ImageMagick Convert is required for converting documents to slides.
<BR><BR>
<?php
				echo "Path from settings: " . $options['convertPath'] . '<br>';

				$cmd =$options['convertPath'];
				$output = '';
				exec($cmd, $output, $returnvalue);
				if ($returnvalue == 127)  echo "<b>Warning: not detected: $cmd</b>"; else
				{
					echo "<u>Detected:</u>";
					echo '<BR>' . $output[0];
					echo '<BR>' . $output[1];
				}

?>

<?php
				break;

			case 'server':
?>
<h3>Server and Streaming Settings</h3>
<h4>RTMP Address</h4>
<p>To run this, make sure your hosting environment meets all <a href="http://www.videowhisper.com/?p=Requirements" target="_blank">requirements</a>.  If you don't have a <a href="http://www.videowhisper.com/?p=RTMP+Hosting">videowhisper rtmp address</a> yet (from a managed rtmp host), go to <a href="http://www.videowhisper.com/?p=RTMP+Applications" target="_blank">RTMP Application Setup</a> for  installation details.</p>
<input name="rtmp_server" type="text" id="rtmp_server" size="100" maxlength="256" value="<?php echo $options['rtmp_server']?>"/>

<h4>RTMP Address for Recording</h4>
<input name="rtmp_server_record" type="text" id="rtmp_server_record" size="100" maxlength="256" value="<?php echo $options['rtmp_server_record']?>"/>
<BR>An address configured for recording. Used for recording in presentation mode, if enabled by parameters.


<?php submit_button(); ?>

<h4>Disable Bandwidth Detection</h4>
<p>Required on some rtmp servers that don't support bandwidth detection and return a Connection.Call.Fail error.</p>
<select name="disableBandwidthDetection" id="disableBandwidthDetection">
  <option value="0" <?php echo $options['disableBandwidthDetection']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['disableBandwidthDetection']?"selected":""?>>Yes</option>
</select>

<h4>Transcoder</h4>
<p>If requirements are available, moderators can transcode web based video streams to <a href="http://www.videowhisper.com/?p=iPhone-iPad-Apps#hls">iOS HLS</a> compatible formats.</p>
<select name="disableTranscoder" id="disableTranscoder">
  <option value="0" <?php echo $options['disableTranscoder']=='0'?"selected":""?>>Yes</option>
  <option value="1" <?php echo $options['disableTranscoder']=='1'?"selected":""?>>No</option>
</select>
<BR> This requires the 'Always do RTMP Streaming' Yes so streaming can be started and transcoded without watchers in web applications.

<h4>HTTP Streaming URL</h4>
This is used for accessing transcoded streams on HLS playback. Usually available with <a href="http://www.videowhisper.com/?p=Wowza+Media+Server+Hosting">Wowza Hosting</a> .<br>
<input name="httpstreamer" type="text" id="httpstreamer" size="100" maxlength="256" value="<?php echo $options['httpstreamer']?>"/>
<BR>Application folder must match rtmp application. Ex. http://localhost:1935/videowhisper-x/ works when publishing to rtmp://localhost/videowhisper-x .

<h4>Publish Transcoding to RTMP Address</h4>
<input name="rtmp_serverX" type="text" id="rtmp_serverX" size="64" maxlength="256" value="<?php echo $options['rtmp_serverX']?>"/>
<br>Can be same as source and must match http setting above.

<h4>FFMPEG Path</h4>
<input name="ffmpegPath" type="text" id="ffmpegPath" size="100" maxlength="256" value="<?php echo $options['ffmpegPath']?>"/>
<BR> Path to latest FFMPEG. Required for transcoding of web based streams.
<?php
				echo "<BR>FFMPEG: ";
				$cmd =$options['ffmpegPath'] . ' -version';
				exec($cmd, $output, $returnvalue);
				if ($returnvalue == 127)  echo "not detected: $cmd"; else
				{
					echo "detected";
					echo '<BR>' . $output[0];
					echo '<BR>' . $output[1];
				}

				$cmd =$options['ffmpegPath'] . ' -codecs';
				exec($cmd, $output, $returnvalue);

				//detect codecs
				if ($output) if (count($output))
					{
						echo "<br>Codecs:";
						foreach (array('h264', 'vp6', 'faac','speex', 'nellymoser') as $cod)
						{
							$det=0; $outd="";
							echo "<BR>$cod codec: ";
							foreach ($output as $outp) if (strstr($outp,$cod)) { $det=1; $outd=$outp; };
							if ($det) echo "detected ($outd)"; else echo "missing: please configure and install ffmpeg with $cod";
						}
					}
?>

<h4>FFMPEG Transcoding Parameters</h4>
<input name="ffmpegTranscode" type="text" id="ffmpegTranscode" size="100" maxlength="256" value="<?php echo $options['ffmpegTranscode']?>"/>
<BR>For lower server load and higher performance, web clients should be configured to broadcast video already suitable for target device (H.264 Baseline 3.1 for most iOS devices) so only audio needs to be encoded.
<BR>Ex.(transcode audio for iOS): -vcodec copy -acodec libfaac -ac 2 -ar 22050 -ab 96k
<BR>Ex.(transcode video+audio): -vcodec libx264 -s 480x360 -r 15 -vb 512k -x264opts vbv-maxrate=364:qpmin=4:ref=4 -coder 0 -bf 0 -analyzeduration 0 -level 3.1 -g 30 -maxrate 768k -acodec libfaac -ac 2 -ar 22050 -ab 96k
<BR>For advanced settings see <a href="https://developer.apple.com/library/ios/technotes/tn2224/_index.html#//apple_ref/doc/uid/DTS40009745-CH1-SETTINGSFILES">iOS HLS Supported Codecs<a> and <a href="https://trac.ffmpeg.org/wiki/Encode/AAC">FFMPEG AAC Encoding Guide</a>.



<h4>Unoconv Path</h4>
<input name="unoconvPath" type="text" id="unoconvPath" size="100" maxlength="256" value="<?php echo $options['unoconvPath']?>"/>
<BR>This is required for converting documents to accessible formats.

<h4>ImageMagick Convert Path</h4>
<input name="convertPath" type="text" id="convertPath" size="100" maxlength="256" value="<?php echo $options['convertPath']?>"/>
<BR>This is required for converting documents to slides.

<h4>Uploads Path</h4>
<p>Path where logs and snapshots will be uploaded.</p>
<input name="uploadsPath" type="text" id="uploadsPath" size="80" maxlength="256" value="<?php echo $options['uploadsPath']?>"/>
<br>Should be accessible by scripts and web. Default:
<BR><?php echo $optionsDefault['uploadsPath']?>

<h4>Streams Path</h4>
<input name="streamsPath" type="text" id="streamsPath" size="100" maxlength="256" value="<?php echo $options['streamsPath']?>"/>

<h4>RTMFP Address</h4>
<p> Get your own independent RTMFP address by registering for a free <a href="https://www.adobe.com/cfusion/entitlement/index.cfm?e=cirrus" target="_blank">Adobe Cirrus developer key</a>. This is required for P2P support.</p>
<input name="serverRTMFP" type="text" id="serverRTMFP" size="80" maxlength="256" value="<?php echo $options['serverRTMFP']?>"/>
<h4>P2P Group</h4>
<input name="p2pGroup" type="text" id="p2pGroup" size="32" maxlength="64" value="<?php echo $options['p2pGroup']?>"/>
<h4>Support RTMP Streaming</h4>
<select name="supportRTMP" id="supportRTMP">
  <option value="0" <?php echo $options['supportRTMP']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['supportRTMP']?"selected":""?>>Yes</option>
</select>
<h4>Always do RTMP Streaming</h4>
<p>Enable this if you want all streams to be published to server, no matter if there are registered subscribers or not (in example if you're using server side video archiving and need all streams published for recording).</p>
<select name="alwaysRTMP" id="alwaysRTMP">
  <option value="0" <?php echo $options['alwaysRTMP']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['alwaysRTMP']?"selected":""?>>Yes</option>
</select>
<h4>Support P2P Streaming</h4>
<select name="supportP2P" id="supportP2P">
  <option value="0" <?php echo $options['supportP2P']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['supportP2P']?"selected":""?>>Yes</option>
</select>
<br>Not recommended as P2P is highly dependant on client network and ISP restrictions. Often results in video streaming failure or huge latency.
P2P may be suitable when all clients are in same network or broadcasters have server grade connection (with high upload and dedicated public IP accessible externally).
<h4>Always do P2P Streaming</h4>
<select name="alwaysP2P" id="alwaysP2P">
  <option value="0" <?php echo $options['alwaysP2P']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['alwaysP2P']?"selected":""?>>Yes</option>
</select>

<?php
				break;
				//! Integration Settings
			case 'integration':

				$options['welcome'] = htmlentities(stripslashes($options['welcome']));
				$options['layoutCode'] = htmlentities(stripslashes($options['layoutCode']));


?>
<h4>Page for Management</h4>
<p>Add room management page (Page ID <a href='post.php?post=<?php echo get_option("vw_vp_page_manage"); ?>&action=edit'><?php echo $vw_vp_page_manage = get_option("vw_vp_page_manage"); ?></a>) with shortcode [videowhisperconsultation_manage]</p>
<select name="disablePage" id="disablePage">
  <option value="0" <?php echo $options['disablePage']=='0'?"selected":""?>>Yes</option>
  <option value="1" <?php echo $options['disablePage']=='1'?"selected":""?>>No</option>
</select>
<br><a href="<?php echo get_permalink( $vw_vp_page_manage ) ?>"><?php echo get_permalink( $vw_vp_page_manage ) ?></a>

<h4>Page for Presentations</h4>
<p>Add landing presentation page (Page ID <a href='post.php?post=<?php echo get_option("vw_vp_page"); ?>&action=edit'><?php echo $vw_vp_page = get_option("vw_vp_page"); ?></a>) with shortcode [videowhisperconsultation]</p>
<select name="disablePageC" id="disablePageC">
  <option value="0" <?php echo $options['disablePageC']=='0'?"selected":""?>>Yes</option>
  <option value="1" <?php echo $options['disablePageC']=='1'?"selected":""?>>No</option>
</select>
<br><a href="<?php echo get_permalink( $vw_vp_page ) ?>"><?php echo get_permalink( $vw_vp_page ) ?></a>

<h4>Username</h4>
<select name="userName" id="userName">
  <option value="display_name" <?php echo $options['userName']=='display_name'?"selected":""?>>Display Name</option>
  <option value="user_login" <?php echo $options['userName']=='user_login'?"selected":""?>>Login (Username)</option>
  <option value="user_nicename" <?php echo $options['userName']=='user_nicename'?"selected":""?>>Nicename</option>
</select>

<h4>Access Link</h4>
<select name="accessLink" id="accessLink">
  <option value="site" <?php echo $options['accessLink']=='site'?"selected":""?>>Site Page</option>
  <option value="full" <?php echo $options['accessLink']=='full'?"selected":""?>>Full Page</option>
</select>
<br>Full page will load presentation room in a full page without site template (useful when template does not provide enough space to load room layout).

<h4>Post Template Filename</h4>
<input name="postTemplate" type="text" id="postTemplate" size="20" maxlength="64" value="<?php echo $options['postTemplate']?>"/>
<br>Site template file located in current theme folder, that should be used to render channel post page. Ex: page.php, single.php
<?php
				if ($options['postTemplate'] != '+plugin')
				{
					echo '<br>';
					$single_template = get_template_directory() . '/' . $options['postTemplate'];
					echo $single_template . ' : ';
					if (file_exists($single_template)) echo 'Found.';
					else echo 'Not Found! Use another theme file!';
				}
?>
<br>Set "+plugin" to use a template provided by this plugin, instead of theme templates.
<br>Post template applies to custom post pages yoursite.com/presentation/[room], not the static landing page (edit content/options for page #<a href='post.php?post=<?php echo get_option("vw_vp_page"); ?>&action=edit'><?php echo get_option("vw_vp_page"); ?></a>) .

<h4>Default landing room</h4>

<select name="landingRoom" id="landingRoom">
  <option value="lobby" <?php echo $options['landingRoom']=='lobby'?"selected":""?>>Lobby</option>
  <option value="username" <?php echo $options['landingRoom']=='username'?"selected":""?>>Username</option>

</select>
<BR>Username will allow registered users to start their own rooms on access when no room name is provided. Enable 'Moderator by Name' option below for them to be able to moderate in their rooms.

<h4>Lobby room name</h4>
<input name="lobbyRoom" type="text" id="lobbyRoom" size="16" maxlength="16" value="<?php echo $options['lobbyRoom']?>"/>
<BR>Ex: Lobby

<h4>Allow Any Room</h4>
<select name="anyRoom" id="anyRoom">
  <option value="1" <?php echo $options['anyRoom']=='1'?"selected":""?>>Yes</option>
  <option value="0" <?php echo $options['anyRoom']=='0'?"selected":""?>>No</option>
</select>
<br>Any room name will be accessible if this is enabled (required by username rooms). Disable to allow accessing only previously setup rooms and landing room.

<h4>Welcome Message</h4>
<textarea name="welcome" id="welcome" cols="100" rows="8"><?php echo $options['welcome']?></textarea>
<br>Shows in chatbox when entering video presentation.
Default:<br><textarea readonly cols="100" rows="3"><?php echo $optionsDefault['welcome']?></textarea>

<h4>Custom Layout Code</h4>
<textarea name="layoutCode" id="layoutCode" cols="100" rows="8"><?php echo $options['layoutCode']?></textarea>
<br>Generate by writing and sending "/videowhisper layout" in chat (contains panel positions, sizes, move and resize toggles). Copy and paste code here.
Default:<br><textarea readonly cols="100" rows="3"><?php echo $optionsDefault['layoutCode']?></textarea>


<h4>Translation Code for Chat Application</h4>
<?php
				$options['translationCode'] = htmlentities(stripslashes($options['translationCode']));
?>
<textarea name="translationCode" id="translationCode" cols="100" rows="5"><?php echo $options['translationCode']?></textarea>
<br>Generate by writing and sending "/videowhisper translation" in chat (contains xml tags with text and translation attributes). Texts are added to list only after being shown once in interface. If any texts don't show up in generated list you can manually add new entries for these. Same translation file is used for all interfaces so setting should cumulate all translation texts.
As translations are configured using XML, any strings containing special chars should be <a target="_xmlencoder" href="http://coderstoolbox.net/string/#!encoding=xml&action=encode&charset=us_ascii">XML Encoded</a>. Make sure translation items are enclosed in a tag (&lt;translations&gt; ... translation item tags ... &lt;/translations&gt;).
Default:<br><textarea readonly cols="100" rows="3"><?php echo $optionsDefault['translationCode']?></textarea>


<h4>Online Expiration</h4>
<p>How long to consider user online if no web status update occurs.</p>
<input name="onlineExpiration" type="text" id="onlineExpiration" size="5" maxlength="6" value="<?php echo $options['onlineExpiration']?>"/>s
<br>Should be 10s higher than maximum statusInterval (ms) configured in parameters. A higher statusInterval decreases web server load caused by status updates.



<h4>Show VideoWhisper Powered by</h4>
<select name="videowhisper" id="videowhisper">
  <option value="0" <?php echo $options['videowhisper']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['videowhisper']?"selected":""?>>Yes</option>
</select>

<?php
				break;
			case 'video':
?>
<h4>Default Webcam Resolution</h4>
<select name="camResolution" id="camResolution">
<?php
				foreach (array('160x120','240x180','320x240','480x360', '640x480', '720x480', '720x576', '1280x720', '1440x1080', '1920x1080') as $optItm)
				{
?>
  <option value="<?php echo $optItm;?>" <?php echo $options['camResolution']==$optItm?"selected":""?>> <?php echo $optItm;?> </option>
  <?php
				}
?>
 </select>
 <br>Higher resolution will require <a target="_blank" href="http://www.videochat-scripts.com/recommended-h264-video-bitrate-based-on-resolution/">higher bandwidth</a> to avoid visible blocking and quality loss (ex. 1Mbps required for 640x360). Webcam capture resolution should be similar to video size in player/watch interface (capturing higher resolution will require more resources without visible quality improvement and lower will display pixelation when zoomed in player).

<h4>Default Webcam Frames Per Second</h4>
<select name="camFPS" id="camFPS">
<?php
				foreach (array('1','8','10','12','15','29','30','60') as $optItm)
				{
?>
  <option value="<?php echo $optItm;?>" <?php echo $options['camFPS']==$optItm?"selected":""?>> <?php echo $optItm;?> </option>
  <?php
				}
?>
 </select>


<h4>Video Stream Bandwidth</h4>
<input name="camBandwidth" type="text" id="camBandwidth" size="7" maxlength="7" value="<?php echo $options['camBandwidth']?>"/> (bytes/s)
<h4>Maximum Video Stream Bandwidth (at runtime)</h4>
<input name="camMaxBandwidth" type="text" id="camMaxBandwidth" size="7" maxlength="7" value="<?php echo $options['camMaxBandwidth']?>"/> (bytes/s)

<h4>Video Codec</h4>
<select name="videoCodec" id="videoCodec">
  <option value="H264" <?php echo $options['videoCodec']=='H264'?"selected":""?>>H264</option>
  <option value="H263" <?php echo $options['videoCodec']=='H263'?"selected":""?>>H263</option>
</select>
<br>Some older rtmp server versions may not support latest codecs like H264.

<h4>H264 Video Codec Profile</h4>
<select name="codecProfile" id="codecProfile">
  <option value="main" <?php echo $options['codecProfile']=='main'?"selected":""?>>main</option>
  <option value="baseline" <?php echo $options['codecProfile']=='baseline'?"selected":""?>>baseline</option>
</select>

<h4>H264 Video Codec Level</h4>
<input name="codecLevel" type="text" id="codecLevel" size="32" maxlength="64" value="<?php echo $options['codecLevel']?>"/> (1, 1b, 1.1, 1.2, 1.3, 2, 2.1, 2.2, 3, 3.1, 3.2, 4, 4.1, 4.2, 5, 5.1)

<h4>Sound Codec</h4>
<select name="soundCodec" id="soundCodec">
  <option value="Speex" <?php echo $options['soundCodec']=='Speex'?"selected":""?>>Speex</option>
  <option value="Nellymoser" <?php echo $options['soundCodec']=='Nellymoser'?"selected":""?>>Nellymoser</option>
</select>
<br>Some older rtmp server versions may not support latest codecs like Speex.

<h4>Speex Sound Quality</h4>
<input name="soundQuality" type="text" id="soundQuality" size="3" maxlength="3" value="<?php echo $options['soundQuality']?>"/> (0-10)

<h4>Nellymoser Sound Rate</h4>
<input name="micRate" type="text" id="micRate" size="3" maxlength="3" value="<?php echo $options['micRate']?>"/> (11/22/44)

<?php
				break;
			case 'moderators':

				$options['parametersAdmin'] = htmlentities(stripslashes($options['parametersAdmin']));

?>
<h4>Who can create rooms</h4>
<select name="canBroadcast" id="canBroadcast">
  <option value="members" <?php echo $options['canBroadcast']=='members'?"selected":""?>>All Members</option>
  <option value="list" <?php echo $options['canBroadcast']=='list'?"selected":""?>>Members in List *</option>
</select>
<br>Room owners are moderators in their rooms.

<h4>* Members in List: allowed to setup rooms (comma separated user names, roles, emails, IDs)</h4>
<textarea name="broadcastList" cols="100" rows="3" id="broadcastList"><?php echo $options['broadcastList']?>
</textarea>
<br>This allows setting up membership sites by assigning room setup permissions only to paid roles. Paid roles can be setup with a plugin like <a href="http://affiliates.websharks-inc.com/3546-5-3-17.html">s2Member</a>.

<h4>Room limit</h4>
<input name="maxRooms" type="text" id="maxRooms" size="3" maxlength="3" value="<?php echo $options['maxRooms']?>"/>
<br>Maximum number of rooms each user can have.

<h4>Moderator by Name</h4>
<p>When room has same name as user, user becomes moderator.</p>
<select name="disableModeratorByName" id="disableModeratorByName">
  <option value="0" <?php echo $options['disableModeratorByName']=='0'?"selected":""?>>Yes</option>
  <option value="1" <?php echo $options['disableModeratorByName']=='1'?"selected":""?>>No</option>
</select>

<h4>Moderators (in all rooms)</h4>
<p>Comma separated roles, BP groups, usernames, emails, IDs</p>
<textarea name="moderatorList" cols="100" rows="3" id="moderatorList"><?php echo $options['moderatorList']?>
</textarea>

<h4>Parameters for Moderators</h4>
<textarea name="parametersAdmin" id="parametersAdmin" cols="100" rows="8"><?php echo $options['parametersAdmin']?></textarea>
<br>Should include special permissions for moderators.
<br>Recommended low latency buffering: 0.2 (s).
Default:<br><textarea readonly cols="100" rows="3"><?php echo htmlentities($optionsDefault['parametersAdmin'])?></textarea>


<?php
				break;
			case 'participants':
				$options['parameters'] = htmlentities(stripslashes($options['parameters']));
				$options['parametersPassive'] = htmlentities(stripslashes($options['parametersPassive']));
				$options['parametersCustom'] = htmlentities(stripslashes($options['parametersCustom']));

				$options['notifySubject'] = htmlentities(stripslashes($options['notifySubject']));
				$options['notifyMessage'] = htmlentities(stripslashes($options['notifyMessage']));

?>


<h4>Who can access video presentation</h4>
<select name="canAccess" id="canAccess">
  <option value="all" <?php echo $options['canAccess']=='all'?"selected":""?>>Anybody</option>
  <option value="members" <?php echo $options['canAccess']=='members'?"selected":""?>>All Members</option>
  <option value="list" <?php echo $options['canAccess']=='list'?"selected":""?>>Members in List</option>
</select>

<h4>Members allowed to access video presentation</h4>
<p>Comma separated roles, BP groups, usernames, emails, IDs</p>
<textarea name="accessList" cols="100" rows="3" id="accessList"><?php echo $options['accessList']?>
</textarea>

<h4>Parameters for Participants</h4>
<textarea name="parameters" id="parameters" cols="100" rows="8"><?php echo $options['parameters']?></textarea>
<br>Documented on <a href="http://www.videowhisper.com/?p=php+video+consultation#customize">PHP Video Consultation</a> edition page.
Default:<br><textarea readonly cols="100" rows="3"><?php echo htmlentities($optionsDefault['parameters'])?></textarea>

<h4>Parameters for Passive Participants</h4>
<textarea name="parametersPassive" id="parametersPassive" cols="100" rows="8"><?php echo $options['parametersPassive']?></textarea>
<br>Passive participants should not be able to interact or disrupt room.
Default:<br><textarea readonly cols="100" rows="3"><?php echo htmlentities($optionsDefault['parametersPassive'])?></textarea>

<h4>Custom Parameters</h4>
<textarea name="parametersCustom" id="parametersCustom" cols="100" rows="4"><?php echo $options['parametersCustom']?></textarea>
<br>Custom parameters are editable per room by each user. Configure defaults here.
Default:<br><textarea readonly cols="100" rows="3"><?php echo htmlentities($optionsDefault['parametersCustom'])?></textarea>

<h4>Notify Email</h4>
Room owners can notify invite list (usernames or emails of existing users) of a new room.
<br>Use #room# and #link# to insert room name and link into the email.

<h4>Subject</h4>
<input name="notifySubject" type="text" id="notifySubject" size="100" maxlength="128" value="<?php echo $options['notifySubject']?>"/>
<br>Notify email subject.
Default:<br><textarea readonly cols="100" rows="3"><?php echo $optionsDefault['notifySubject']?></textarea>

<h4>Message</h4>
<textarea name="notifyMessage" id="notifyMessage" cols="100" rows="4"><?php echo $options['notifyMessage']?></textarea>
<br>Notify email message.
Default:<br><textarea readonly cols="100" rows="3"><?php echo $optionsDefault['notifyMessage']?></textarea>

<?php
				break;

			case 'sell':
?>
<h4>Sell Content Setup Interface</h4>
<select name="myCred" id="myCred">
  <option value="0" <?php echo $options['myCred']=='0'?"selected":""?>>No</option>
  <option value="1" <?php echo $options['myCred']=='1'?"selected":""?>>Yes</option>
</select>
<br>Enabling this will allow room owners to setup a price for their presentation rooms using room setup interface.
<BR>MyCred with SellContent addon is required to setup this functionality. Optionally, TeraWallet can be used to enable WooCommerce gateways.

<br>For more advanced features, see <a href="https://paidvideochat.com">PaidVideochat pay per minute videochat site solution</a> with <a href="https://paidvideochat.com/features/group-chat-modes/">Presentation Mode</a>.

<h4>Members allowed to sell video presentations</h4>
<p>Comma separated roles, BP groups, usernames, emails, IDs</p>
<textarea name="canSell" cols="100" rows="3" id="canSell"><?php echo $options['canSell']?>
</textarea>

<h3>Billing Wallets</h3>

<h4>Active Wallet</h4>
<select name="wallet" id="wallet">
  <option value="MyCred" <?php echo $options['wallet']=='MyCred'?"selected":""?>>MyCred</option>
  <option value="WooWallet" <?php echo $options['wallet']=='WooWallet'?"selected":""?>>WooWallet</option>
</select>
<BR>Select wallet to use with solution. MyCred with Sell Content addon is required for paid rooms.

<h4>Multi Wallet</h4>
<select name="walletMulti" id="walletMulti">
  <option value="0" <?php echo $options['walletMulti']=='0'?"selected":""?>>Disabled</option>
  <option value="1" <?php echo $options['walletMulti']=='1'?"selected":""?>>Show</option>
  <option value="2" <?php echo $options['walletMulti']=='2'?"selected":""?>>Manual</option>
  <option value="3" <?php echo $options['walletMulti']=='3'?"selected":""?>>Auto</option>
</select>
<BR>Show will display balances for available wallets, manual will allow transferring to active wallet, auto will automatically transfer all to active wallet.

<?php

				submit_button();
?>


<h3>Setup and Configure myCRED</h3>
Follow steps below to make sure myCRED is setup and configured to manage channel access sales.

<h4>1) myCRED</h4>
<?php
				if (is_plugin_active('mycred/mycred.php')) echo 'MyCred Plugin Detected'; else echo 'Not detected. Please install and activate <a target="_mycred" href="https://wordpress.org/plugins/mycred/">myCRED</a> from <a href="plugin-install.php">Plugins > Add New</a>!';

				if (function_exists( 'mycred_get_users_balance'))
				{
					$balance = mycred_get_users_balance(get_current_user_id());

					echo '<br>Testing MyCred balance: You have ' . $balance  .' '. htmlspecialchars($options['currencyLong']) . '. ';

					if (!strlen($balance)) echo 'Warning: No balance detected! Unless this account is excluded, there should be a MyCred balance. MyCred plugin may not be configured/enabled correctly.';
?>
	<ul>
		<li><a class="secondary button" href="admin.php?page=mycred">Transactions Log</a></li>
		<li><a class="secondary button" href="users.php">User Credits History & Adjust</a></li>
	</ul>
					<?php
				}
?>
<a target="_mycred" href="https://wordpress.org/plugins/mycred/">myCRED</a> is a stand alone adaptive points management system that lets you award / charge your users for interacting with your WordPress powered website. The Buy Content add-on allows you to sell any publicly available post types, including webcam posts created by this plugin. You can select to either charge users to view the content or pay the post's author either the whole sum or a percentage.

	<br> + After installing and enabling myCRED, activate these <a href="admin.php?page=mycred-addons">addons</a>: buyCRED, Sell Content are required and optionally Notifications, Statistics or other addons, as desired for project.

	<br> + Configure in <a href="admin.php?page=mycred-settings ">Core Setting > Format > Decimals</a> at least 2 decimals to record fractional token usage. With 0 decimals, any transactions under 1 token will not be recorded.
	
h4>2) myCRED buyCRED Module</h4>
 <?php
				if (class_exists( 'myCRED_buyCRED_Module' ) )
				{
					echo 'Detected';
?>
	<ul>
		<li><a class="secondary button" href="edit.php?post_type=buycred_payment">Pending Payments</a></li>
		<li><a class="secondary button" href="admin.php?page=mycred-purchases-mycred_default">Purchase Log</a> - If you enable BuyCred separate log for purchases.</li>
		<li><a class="secondary button" href="edit-comments.php">Troubleshooting Logs</a> - MyCred logs troubleshooting information as comments.</li>
	</ul>
					<?php
				} else echo 'Not detected. Please install and activate myCRED with <a href="admin.php?page=mycred-addons">buyCRED addon</a>!';
?>

<p> + myCRED <a href="admin.php?page=mycred-addons">buyCRED addon</a> should be enabled and at least 1 <a href="admin.php?page=mycred-gateways">payment gateway</a> configured, for users to be able to buy credits.
<br> + Setup a page for users to buy credits with shortcode <a target="mycred" href="http://codex.mycred.me/shortcodes/mycred_buy_form/">[mycred_buy_form]</a> or use <a href="https://wordpress.org/plugins/paid-membership/">Paid Membership & Content</a> - My Wallet page (that can manage multi wallet MyCred, TeraWallet).
<br> + "Thank You Page", "Cancellation Page" should be configured from <a href="admin.php?page=mycred-settings">buyCred settings</a>.</p>
<p>Troubleshooting: If you experience issues with IPN tests, check recent access logs (recent Visitors from CPanel) to identify exact requests from billing site, right after doing a test.</p>

<h4>3) myCRED Sell Content Module: Enable Paid Rooms and Content</h4>
 <?php
				if (class_exists( 'myCRED_Sell_Content_Module' ) ) echo 'Detected'; else echo 'Not detected. Please install and activate myCRED with <a href="admin.php?page=myCRED_page_addons">Sell Content addon</a>!';
?>
<p>
myCRED <a href="admin.php?page=myCRED_page_addons">Sell Content addon</a> should be enabled and "presentation" added to Post Types in <a href="admin.php?page=myCRED_page_settings">Sell Content settings tab</a> (configure to use for Presentations - I Manually Select) so access to presentation rooms can be sold. You can also configure payout to content author from there, if necessary. 
You can also configure payout to content author (Profit Share) and expiration, if necessary.



<h3>TeraWallet (WooWallet WooCommerce Wallet)</h3>
<?php
				if (is_plugin_active('woo-wallet/woo-wallet.php'))
				{
					echo 'WooWallet Plugin Detected';

					if ($GLOBALS['woo_wallet'])
					{
						$wooWallet = $GLOBALS['woo_wallet'];

						if ($wooWallet->wallet)
						{
							echo '<br>Testing balance: You have: ' .  $wooWallet->wallet->get_wallet_balance( get_current_user_id() );

?>
	<ul>
		<li><a class="secondary button" href="admin.php?page=woo-wallet">User Credits History & Adjust</a></li>
		<li><a class="secondary button" href="users.php">User List with Balance</a></li>
	</ul>
					<?php
						}
						else echo 'Error: WooWallet->wallet not ready! Make sure <a href="https://woocommerce.com/?aff=18336&cid=1980980" target="_woocommerce">WooCommerce</a> is also installed and active. <a href="plugin-install.php">Plugins > Add New Plugin</a>';

					}else echo 'Error: woo_wallet not found!';


				}
				else echo 'Not detected. Please install and activate <a target="_plugin" href="https://wordpress.org/plugins/woo-wallet/">WooCommerce Wallet</a> from <a href="plugin-install.php">Plugins > Add New</a>!';

				?><br>
WooCommerce Wallet plugin is based on <a href="https://woocommerce.com/?aff=18336&cid=1980980" target="_woocommerce">WooCommerce</a> plugin and allows customers to store their money in a digital wallet. The customers can add money to their wallet using various payment methods set by the admin, available in WooCommerce. The customers can also use the wallet money for purchasing products from the WooCommerce store.
<br> + Configure WooCommerce payment gateways from <a target="_gateways" href="admin.php?page=wc-settings&tab=checkout">WooCommerce > Settings, Payments tab</a>.
<br> + Enable payment gateways from <a target="_gateways" href="admin.php?page=woo-wallet-settings">Woo Wallet Settings</a>.
<br> + Setup a page for users to buy credits with shortcode [woo-wallet]. My Wallet section is also available in WooCommerce My Account page (/my-account).

<h4>WooCommerce Memberships, Subscriptions and Conversion Tools</h4>
<ul>
	<LI><a href="https://woocommerce.com/products/woocommerce-memberships/?aff=18336&cid=1980980">WooCommerce Memberships</a> Setup paid membership as products. Leveraged with Subscriptions plugin allows membership subscriptions.</LI>
	<LI><a href="https://woocommerce.com/products/woocommerce-subscriptions/?aff=18336&cid=1980980">WooCommerce Subscriptions</a> Setup subscription products, content. Leverages Membership plugin to setup membership subscriptions.</LI>
	<LI><a href="https://woocommerce.com/products/follow-up-emails/?aff=18336&cid=1980980">WooCommerce Follow Up</a> Follow Up by emails and twitter automatically, drip campaigns.</LI>
	<LI><a href="https://woocommerce.com/products/woocommerce-bookings/?aff=18336&cid=1980980">WooCommerce Bookings</a> Let your customers book reservations, appointments on their own.</LI>
</ul>


<h3>Receive Tips and Site Contributions in Crypto</h3>
<a href="https://brave.com/vid857">Brave</a> is a special build of the popular Chrome browser, focused on privacy and speed, already used by millions. Users get airdrops and rewards from ads they are willing to watch and content creators (publishers) like site owners get tips and automated revenue from visitors. This is done in $BAT and can be converted to other cryptocurrencies like Bitcoin or withdrawn in USD, EUR.
	<p>How to receive contributions and tips for your site:
	<br>+ Get the <a href="https://brave.com/vid857">Brave Browser</a>. You will get a browser wallet, airdrops and get to see how tips and contributions work. 
	<br>+ Join <a href="https://creators.brave.com/">Brave Creators Publisher Program</a> and add your site(s) as channels. If you have an established site, you may have automated contributions or tips already available from site users that accessed using Brave. Your site(s) will show with a Verified Publisher badge in Brave browser and users know they can send you tips directly. 
	<br>+ You can setup and connect an Uphold wallet to receive your earnings and be able to withdraw to bank account or different wallet. You can select to receive your deposits in various currencies and cryptocurrencies (USD, EUR, BAT, BTC, ETH and many more).
</p>		


<?php
				break;

			case 'documentation':
?>

<h4>Video Presentation/Consultation Installation Overview</h4>

<p>Users can manage rooms from page #<a href='post.php?post=<?php echo get_option("vw_vp_page_manage"); ?>&action=edit'><?php echo $vw_vp_page_manage = get_option("vw_vp_page_manage"); ?></a> with shortcode [videowhisperconsultation_manage]:
<br><a href="<?php echo get_permalink( $vw_vp_page_manage ) ?>"><?php echo get_permalink( $vw_vp_page_manage ) ?></a>
</p>

<p>Users can access Video Consultation in full page (landing room or different room by adding ?r=[room-name]) at:
<br><a href="<?php echo $url = get_site_url() . '/wp-content/plugins/videowhisper-video-presentation/vp/';
 ?>"><?php echo $url ?></a>
</p>

<p>Video Consultation also renders on site page #<a href='post.php?post=<?php echo get_option("vw_vp_page"); ?>&action=edit'><?php echo $vw_vp_page = get_option("vw_vp_page"); ?></a> with shortcode [videowhisperconsultation]
<br><a href="<?php echo get_permalink( $vw_vp_page ) ?>"><?php echo get_permalink( $vw_vp_page ) ?></a>
</p>

<h3>Shortcodes</h3>

<h4>[videowhisperconsultation room="Lobby" link="1"]</h4>
Displays Video Consultation application interface for specified room, with link to open in full page layout.

<h4>[videowhisperconsultation_hls channel="username"]</h4>
Displays HTML5 HLS video code for specified stream name.
<?php
				break;
			}

			if (!in_array($active_tab, array('documentation'))) submit_button();
?>
</form>
	 <?php
		}

	}
}

//instantiate
if (class_exists("VWvideoPresentation")) {
	$videoPresentation = new VWvideoPresentation();
}

//Actions and Filters
if (isset($videoPresentation))
{
	add_action("plugins_loaded", array(&$videoPresentation , 'init'));
	add_action('admin_menu', array(&$videoPresentation , 'menu'));
	add_action( 'init', array(&$videoPresentation, 'presentation_post'));

	/* Only load code that needs BuddyPress to run once BP is loaded and initialized. */
	function videopresentationBP_init()
	{
		if (class_exists('BP_Group_Extension'))  require( dirname( __FILE__ ) . '/bp.php' );
	}

	add_action( 'bp_init', 'videoPresentationBP_init' );
	add_filter( "single_template", array(&$videoPresentation,'single_template') );
}
?>
