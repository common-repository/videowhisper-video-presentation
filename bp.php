<?php
//BuddyPress Integration

class videoPresentationGroup extends BP_Group_Extension {	

var $visibility = 'public'; // 'public' will show your extension to non-group members, 'private' means you have to be a member of the group to view your extension.

var $enable_create_step = false; // If your extension does not need a creation step, set this to false
var $enable_nav_item = true; // If your extension does not need a navigation item, set this to false
var $enable_edit_item = false; // If your extension does not need an edit screen, set this to false

	function videoPresentationGroup() {
		
		$this->name = 'Video Presentation';
		$this->slug = 'video-presentation';

		$this->create_step_position = 21;
		$this->nav_item_position = 31;
	}


	function display($group_id = NULL) {
		/* Use this function to display the actual content of your group extension when the nav item is selected */
		global $bp;
		$root_url = get_bloginfo( "url" ) . "/";
		
		$baseurl=$root_url . "wp-content/plugins/videowhisper-video-presentation/vp/";
		$swfurl=$baseurl."consultation.swf?room=".urlencode($bp->groups->current_group->slug);
		?>
	    <div id="videowhisper_videopresentation" style="height:650px" >
		<object width="100%" height="100%">
        <param name="movie" value="<?=$swfurl?>" /><param name="base" value="<?=$baseurl?>" /><param name="scale" value="noscale" /><param name="salign" value="lt"></param><param name="wmode" value="transparent" /><param name="allowFullScreen" value="true"></param><param name="allowscriptaccess" value="always"></param><embed width="100%" height="100%" scale="noscale" salign="lt" src="<?=$swfurl?>" base="<?=$baseurl?>"  wmode="transparent"  type="application/x-shockwave-flash" allowscriptaccess="always" allowfullscreen="true"></embed>
        </object>
		<noscript>
		<p align="center"><strong>Video Whisper <a href="http://www.videowhisper.com/?p=WordPress+Video+Presentation">Live Web Video presentation Software</a> requires the Adobe Flash Player:
		<a href="http://get.adobe.com/flashplayer/">Get Latest Flash</a></strong>!</p>
		</noscript>
		</div>
			<?php
	}

	function widget_display() { ?>
		<div class="info-group">
			<h4><?php echo attribute_escape( $this->name ) ?></h4>
			<p>
				Group Video Presentation allows video consultations and presentations on the group.
			</p>
		</div>
		<?php
	}
}


bp_register_group_extension( 'videoPresentationGroup' );
