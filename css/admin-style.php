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
table#mbsb_attached_files {
	width:auto;
	margin-top: 5px;
}
table#mbsb_attached_files .media_row_hide {
	display:none;
}
table#mbsb_attached_files th {
	border-bottom: none;
}
table#mbsb_attached_files tr#mbsb_media_table_header th {
	background-color: #F1F1F1;
	border-bottom: 1px solid #DFDFDF;
}
table#mbsb_attached_files td {
	border-top: none;
}
table.mbsb_media_detail th {
	font-size:12px;
	font-family:sans-serif;
	font-weight: bold;
}
table.mbsb_media_detail th, table.mbsb_media_detail td {
	border: none;
	padding: 2px 7px;
}
table#mbsb_attached_files div.message {
	margin: 2px;
	padding: 8px;
	background-color: #FFFFE0;
	border: 1px solid #E6DB55;
	width: 100%;
}
