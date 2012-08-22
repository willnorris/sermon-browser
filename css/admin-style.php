<?php
	header ('Cache-Control: max-age=290304000, public');
	header ('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time()+290304000));
	header ('Content-type: text/css');
	$date = @filemtime(__FILE__);
	if ($date)
		header ('Last-Modified: '.gmdate('D, d M Y H:i:s \G\M\T', $date));
?>
div.icon32-posts-mbsb_sermons {
	background-image: url('../images/icon-32-color.png');
}
div.postbox th[scope=row] {
	text-align:right;
}
#mbsb_sermon_details label {
	font-weight: bold;
}
#mbsb_sermon_details select, #mbsb_sermon_details input[type=text] {
	width:100%;
}

#mbsb_sermon_details input#mbsb_time {
	width:auto;
}