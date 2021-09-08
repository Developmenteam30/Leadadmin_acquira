<?php

if( !isset( $title ) ) {
	$title = CONFIG_COMPANY_NAME . ' Admin';
} else {
	$title = $title . ' | ' . CONFIG_COMPANY_NAME . ' Admin';
}

?><!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
	<title><?php echo $title; ?></title>
	<link rel="stylesheet" href="/leadadmin/libraries/jquery-ui-themes-1.11.4/themes/smoothness/jquery-ui.css"/>
	<link rel="stylesheet" href="/leadadmin/libraries/bootstrap-3.3.6-dist/css/bootstrap.min.css"/>
	<link rel="stylesheet" href="/leadadmin/libraries/bootstrap-3.3.6-dist/css/bootstrap-theme.min.css"/>
	<link rel="stylesheet" href="/leadadmin/libraries/bootstrap-toggle/bootstrap-toggle.min.css"/>
	<link rel="stylesheet" href="/leadadmin/libraries/select2-4.0.3/dist/css/select2.min.css"/>
	<link rel="stylesheet" href="/leadadmin/libraries/fine-uploader/fine-uploader-new.css"/>
    <?php if( file_exists( SITE_ROOT . 'public_html/assets/css/admin.css' ) ) { ?>
        <link href="/v<?php echo filemtime( SITE_ROOT . 'public_html/assets/css/admin.css' ); ?>/assets/css/admin.css" rel="stylesheet" type="text/css"/>
    <?php } ?>
    <?php if( file_exists( SITE_ROOT . 'public_html/assets/css/branding.css' ) ) { ?>
        <link href="/v<?php echo filemtime( SITE_ROOT . 'public_html/assets/css/branding.css' ); ?>/assets/css/branding.css" rel="stylesheet" type="text/css"/>
    <?php } ?>
	<script src="/leadadmin/libraries/jquery-2.2.3.min.js"></script> 
	<script src="/leadadmin/libraries/jquery-ui-1.11.4/jquery-ui.min.js"></script>
	<script src="/leadadmin/libraries/bootstrap-3.3.6-dist/js/bootstrap.min.js"></script>
	<script src="/leadadmin/libraries/bootstrap-toggle/bootstrap-toggle.min.js"></script>
	<script src="/leadadmin/libraries/select2-4.0.3/dist/js/select2.min.js"></script>
	<script src="/leadadmin/libraries/fine-uploader/fine-uploader.js"></script>
	<script src="/leadadmin/libraries/default.js"></script>
	<script src="/leadadmin/libraries/calx-1.1.4/jquery-calx-1.1.4.min.js" type="text/javascript"></script>
	<script src="/v2/leadadmin/libraries/tablefilter/tablefilter.js" type="text/javascript"></script>

	<!--link rel="stylesheet" href="./libraries/jquery-ui-themes-1.11.4/themes/smoothness/jquery-ui.css"/>
	<link rel="stylesheet" href="./libraries/bootstrap-3.3.6-dist/css/bootstrap.min.css"/>
	<link rel="stylesheet" href="./libraries/bootstrap-3.3.6-dist/css/bootstrap-theme.min.css"/>
	<link rel="stylesheet" href="./libraries/bootstrap-toggle/bootstrap-toggle.min.css"/>
	<link rel="stylesheet" href="./libraries/select2-4.0.3/dist/css/select2.min.css"/>
	<link rel="stylesheet" href="./libraries/fine-uploader/fine-uploader-new.css"/>
    <?php if( file_exists( SITE_ROOT . 'public_html/assets/css/admin.css' ) ) { ?>
        <link href="/v<?php echo filemtime( SITE_ROOT . 'public_html/assets/css/admin.css' ); ?>/assets/css/admin.css" rel="stylesheet" type="text/css"/>
    <?php } ?>
    <?php if( file_exists( SITE_ROOT . 'public_html/assets/css/branding.css' ) ) { ?>
        <link href="/v<?php echo filemtime( SITE_ROOT . 'public_html/assets/css/branding.css' ); ?>/assets/css/branding.css" rel="stylesheet" type="text/css"/>
    <?php } ?>
	<script src="./libraries/jquery-2.2.3.min.js"></script>
	<script src="./libraries/jquery-ui-1.11.4/jquery-ui.min.js"></script>
	<script src="./libraries/bootstrap-3.3.6-dist/js/bootstrap.min.js"></script>
	<script src="./libraries/bootstrap-toggle/bootstrap-toggle.min.js"></script>
	<script src="./libraries/select2-4.0.3/dist/js/select2.min.js"></script>
	<script src="./libraries/fine-uploader/fine-uploader.js"></script>
	<script src="./libraries/default.js"></script>
	<script src="./libraries/calx-1.1.4/jquery-calx-1.1.4.min.js" type="text/javascript"></script>
	<script src="../v2/leadadmin/libraries/tablefilter/tablefilter.js" type="text/javascript"></script-->
	<!--[if lt IE 9]>
	<script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
	<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
	<![endif]-->
</head>
