<?php
	header ('Cache-Control: max-age=290304000, public');
	header ('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time()+290304000));
	header ('Content-type: text/css');
	$date = @filemtime(__FILE__);
	if ($date)
		header ('Last-Modified: '.gmdate('D, d M Y H:i:s \G\M\T', $date));
?>
.mbsb_hide {
	display:none;
}
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
	margin-top: 10px;
}
table#mbsb_attached_files h3 {
	cursor:auto;
}
table#mbsb_attached_files div.attachment_actions  {
	vertical-align: bottom;
	padding-bottom: 10px;
	padding-right: 10px;
	float: right;
}
table#mbsb_attached_files .attachment_actions a.unattach {
	color:red;
	padding-bottom: 1px;
	border-bottom: 1px solid red;
	display:none;
}
table#mbsb_attached_files .attachment_actions a:hover.unattach {
	color:white;
	background-color:red;
}
table#mbsb_attached_files div.message {
	margin: 2px;
	padding: 8px;
	background-color: #FFFFE0;
	border: 1px solid #E6DB55;
}
table#mbsb_attached_files .attachment_actions {
	display: inline;
	float: right;
	margin-top: 10px;
	margin-right: 10px;
}
table#mbsb_attached_files td, table#mbsb_attached_files th {
	padding:0;
}
table#mbsb_attached_files img.thumbnail {
	float:left;
	margin: 10px;
}
table.mbsb_media_detail {
	margin-top: 8px;
}
table.mbsb_media_detail th {
	font-size:12px;
	font-family:sans-serif;
	font-weight: bold;
	vertical-align:top;
	white-space:nowrap;
}
table#mbsb_attached_files table.mbsb_media_detail th, table#mbsb_attached_files table.mbsb_media_detail td {
	border: none;
	padding: 2px 7px;
}
