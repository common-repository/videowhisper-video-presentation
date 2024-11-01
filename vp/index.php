<?php

include("../../../../wp-config.php");  
 
$room=$_GET['room'];
if (!$room) $room=$_GET['r'];
 
sanitize_file_name( $room );

$agent = $_SERVER['HTTP_USER_AGENT'];
if( strstr($agent,'iPhone') || strstr($agent,'iPod') || strstr($agent,'iPad'))
{
echo do_shortcode("[videowhisperconsultation_hls channel=\"$room\"]");
exit;
}

  $bgcolor="#333333";
  echo do_shortcode("[videowhisperconsultation room=\"$room\" link=\"0\"]");
  ?>
<style type="text/css"> 
<!--
BODY
{
	margin:0px;
	background-color: <?php echo $bgcolor ?>;
}

#videowhisper_presentation_<?php echo $room; ?>
{
width: 100%;
height:100%;
}
-->
</style>

