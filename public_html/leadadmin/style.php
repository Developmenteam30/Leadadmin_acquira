<?php
header("Content-type: text/css; charset: UTF-8");
include("../../includes/c_config.php");
include(INCLUDES."f_site.php");
include(INCLUDES."c_sitecustom.php");
?>html { 	
	margin: 0px; padding: 0px;
	height: 100%;
	overflow-y: scroll;
}

body { 
	background: url("images/bg.png"); 
	margin: 0px; padding: 0px;
	font-family: Verdana, Helvetica, sans-serif;
	font-size: 0.6em;
	height: 100%; 
}

p { margin: 0px 0px 0px 0px; padding: 5px;}

/* Clearfix */
.clr:after {
	clear: both;
	display: block;
	content: " ";
	height: 0px;
	visibility: hidden;
	font-size: 0px;
}

* html > body .clr {
	width: 100%;
	display: block;
}

* html .clr {
	height: 1%;
}

/* General Classes */
.aRight { text-align: right; }
.aCenter { text-align: center; }
.fl { float: left; }
.fl50 { float: left; width: 50%; }
.fl100 { float: left; width: 100%; }
.fr { float: right; }
.clrl { clear: left; }
.hidden { display:none; }
.ghost { visibility: hidden; }

.bgWhite { background: #FFFFFF; }
.bgGray { background: #EEEEEE; }

.w100 { width: 100%; }
input[type="text"] { width: 150px; }
input[type="text"].long { width: 300px; }

div.centered { margin: auto; text-align: center; }
div.divContainer { border-collapse: collapse; }
div.divRow { display: table-row;  }
div.divCol { display: table-cell; border: 1px solid #DDDDDD; }
div.absContainer { position: relative; }
div.contextMenu { display: none; position: absolute; background: #DDDDDD; border: 1px solid #DDDDDD; }

{
	opacity:0.6;
	filter:alpha(opacity=60); /* For IE8 and earlier */
}

#logo {
	max-height: 47px;
}

#logo-reports {
	max-height: 63px;
}

/*Site Styles*/
div.siteBorder { 
	border: 1px solid #414042;
	-webkit-border-radius: 5px;
	-moz-border-radius: 5px;
	border-radius: 5px;
}
.siteHeader { 
	color: #414042;
}
input.siteButton { 
	text-decoration: none;
	color: <?php echo $buttonFontColor; ?>;
	border: 0px;
	-webkit-border-radius: 5px;
	-moz-border-radius: 5px;
	padding: 15px 15px 15px 15px;
	margin: 0px;
	background-image: linear-gradient();
	background-image: -o-linear-gradient(<?php echo $gradient; ?>);
	background-image: -moz-linear-gradient(<?php echo $gradient; ?>);
	background-image: -webkit-linear-gradient(<?php echo $gradient; ?>);
	background-image: -ms-linear-gradient(<?php echo $gradient; ?>);

	background-image: -webkit-gradient(
		linear,
		left bottom,
		left top,
		color-stop(0.01, rgb(<?php echo hex2rgb($gradientColor1);?>)),
		color-stop(0.25, rgb(<?php echo hex2rgb($gradientColor2);?>)),
		color-stop(0.5, rgb(<?php echo hex2rgb($gradientColor3);?>))
	);
}
div.headerRow { 
	padding: 10px 10px 10px 10px; 
}
div.headerRow.client {
	background-color: #dcdcdc;
	overflow: auto;
	margin-bottom: 20px;
	padding: 10px 40px;
}

.payment {
	text-align: center;
	font-weight: bold;
	margin: 1em 0;
}

.footer {
	position: absolute;
	bottom: 0;
	left: 0;
	right: 0;
	margin-top: 30px;
	background-color: #dcdcdc;
	text-align: center;
	font-size: 1.25em;
	padding: 2em 0;
}

.client div.logoutContainer {
	margin: 20px 0 0 0;
	font-size: 1.50em;
}

div.navContainer, div.logoutContainer{ 
	/* border: 1px solid #000000; */
	-webkit-border-radius: 5px;
	-moz-border-radius: 5px;
	border-radius: 5px;
	overflow: hidden;
}
	a.navButton, a.navButtonLogout { 
		text-align: center;
		height: 18px;
		float: left; 
		display: block;
		text-decoration: none;
		color: <?php echo $buttonFontColor; ?>;
		border: 0px;
		border-right: 1px solid <?php echo $navDividers; ?>;
		padding: 15px;
		margin: 0px;
		background-image: linear-gradient();
		background-image: -o-linear-gradient(<?php echo $gradient; ?>);
		background-image: -moz-linear-gradient(<?php echo $gradient; ?>);
		background-image: -webkit-linear-gradient(<?php echo $gradient; ?>);
		background-image: -ms-linear-gradient(<?php echo $gradient; ?>);

		background-image: -webkit-gradient(
			linear,
			left bottom,
			left top,
			color-stop(0.01, rgb(<?php echo hex2rgb($gradientColor1);?>)),
			color-stop(0.25, rgb(<?php echo hex2rgb($gradientColor2);?>)),
			color-stop(0.5, rgb(<?php echo hex2rgb($gradientColor3);?>))
		);

	}
	a.navButtonLogout{ 
		width: 70px; 
		border-right: 0px;
	}
		img.navIcon{ 
			vertical-align: middle; margin-right: 5px;
		}
	a.navButton.navLast { 
		border-right: 0px;
	}

a:hover.navButton{
	text-decoration: underline;
}

div.mainContainer { 
	background: #FFFFFF;
	width: 95%; min-height: 100%;	
	min-width: 300px;
	padding-top: 1px; 
	margin:auto; margin-top: 0px; margin-bottom: 0px;
	-moz-box-shadow: 5px 0px 5px #000, -5px 0px 0px #000;
	-webkit-box-shadow: 5px 0px 5px #000, -5px 0px 0px #000;
	box-shadow: 5px 0px 5px #000, -5px 0px 0px #000;
}
div.mainContainer.client {
	min-height: 100%;
	position: relative;
}
div.mainContainer.client .content {
	margin: 0 60px;
	padding-bottom: 130px;
	font-size: 1.1em;
}


h1.boxTitle { 
	margin: 5px 0px 5px 0px;
	font-size: 1.3em;
	font-weight: normal;
}

div.logoContainer { width: 225px; }
	div.loginLogo { margin-top: 20px; margin-bottom: 50px; }
	div.navLogo { margin-right: 10px; }
	
div.loginContainer { width: 250px; margin: auto; }
div.loginBox { width: 100%;   }
input.loginBox { width: 230px; }

table.standard { width: 100%; border-collapse: collapse; }
table.standard td { border: 1px solid #CCCCCC; padding: 5px; vertical-align: top; }
table.standard td p { padding: 0 0 3px 0; }
table.standard thead { font-weight: bold; text-align: center; }

#jobs tr.accepted td,
#results tr.accepted td {
	background: #C4FFA7;
}

#jobs tr.duplicate td,
#results tr.duplicate td {
	background: #FFB84D;
}

#jobs tr.rejected td,
#results tr.rejected td {
	background: #FF9980;
}
#results tr.suppressed td {
	background: #E0B2F0;
}

table.revenue-report td { padding: 10px; vertical-align: middle; }
table.revenue-report tr:nth-child(even) { background-color: #fdfdfd; }
table.revenue-report thead td { font-weight: bold; background-color: <?php echo $gradientColor3; ?>; color: #fff; }
table.revenue-report .greencheck { text-align: center; }

#dialog_revenue_listowners_detail { margin-top: 30px; }

td.revenue { text-align: center; }
td.revenue span:before { content:'$'; }
td.revenue input { width: 70px; font-size: 1em; }
tr.subtotal { font-weight: bold; background-color: #dadada !important; }

/*Feed Tables Outgoing*/
table.feedTableOutgoing { width: 100%; border-collapse: collapse; }
	table.feedTableOutgoing td { border: 1px solid #CCCCCC; }
	table.feedTableOutgoing td p { margin: 3px; }
td.fTO_companyName { width: 21%;  }
	td.fTO_idFeedOut { width: 3%;  }
	td.fTO_label { width: 18%;  }
td.fTO_feedOverview { width: 46%;  }
	td.fTO_description { width: 20%; word-break:break-all;  }
	td.fTO_statusPop { width: 10%;  }
	td.fTO_statusFeed { width: 8%;  }
	td.fTO_statusCron { width: 8%;  }
td.fTO_accepted { width: 8%;  }
td.fTO_rejected { width: 8%;  }
td.fTO_options { width: 20%; }

/*Feed Tables Incoming*/
table.feedTableIncoming { width: 100%; border-collapse: collapse; }
	table.feedTableIncoming td { border: 1px solid #CCCCCC; }
	table.feedTableIncoming td p { margin: 3px; }
td.fTI_companyName { width: 21%;  }
	td.fTI_idFeedOut { width: 3%;  }
	td.fTI_label { width: 18%;  }
td.fTI_feedOverview { width: 25%;  }
	td.fTI_description { width: 25%;  }
td.fTI_accepted { width: 8%;  }
td.fTI_rejected { width: 8%;  }
td.fTI_options { width: 35%; }

div.dashboardIncoming { width: 50%; }
div.dashboardOutgoing { width: 50%; }

#search_email,
#search_url { 
	width: 250px;
}

/* Rejections Table */
.rejectionsTable {
	border: 1px solid;
	border-collapse: collapse;
	margin: 20px 0 20px 0;
	width: 100%;
}

.rejectionsTable th {
	border: 1px solid #909090;
	font-family:Arial;
	font-weight:bold;
	padding: 4px;
	vertical-align: middle;
}

.rejectionsTable td {
	border: 1px solid #909090;
	font-family:Arial;
	font-weight:normal;
	padding: 4px;
	vertical-align: middle;
}

.rejectionsTable tbody tr:nth-child(4n),
.rejectionsTable tbody tr:nth-child(4n-1) {
	background-color:#ffffff;
}

.rejectionsTable tbody tr:nth-child(4n-2),
.rejectionsTable tbody tr:nth-child(4n-3) {
	background-color:#ffd4aa;
}

.rejectionsTable .error {
	border: 1px solid #909090;
}

.status-retired {
	color: #800000;
	text-decoration:line-through;
}

.status-hidden {
	color: #800000;
	text-decoration:line-through;
}

.error {
	color: #ff0000;
	font-weight: bold;
}

.mobileRow {
	display: none;
}

.form-input h3 {
	display: inline-block;
}

.form-input label {
	display: inline-block;
	width: 100px;
	text-align: right;
	margin: 5px 10px;
	vertical-align: top;
}

.form-input textarea {
	width: 400px;
	height: 75px;
}

.form-input input,
.form-input select {
	font-family: Verdana, Helvetica, sans-serif;
}

.form-input input {
	width: 400px;
}

.form-input input[type="submit"] {
	width: auto;
	color: <?php echo $buttonFontColor; ?>;
	border: 0px;
	-webkit-border-radius: 5px;
	-moz-border-radius: 5px;
	padding: 5px 10px;
	margin: 5px 0;
	background-image: linear-gradient();
	background-image: -o-linear-gradient(<?php echo $gradient; ?>);
	background-image: -moz-linear-gradient(<?php echo $gradient; ?>);
	background-image: -webkit-linear-gradient(<?php echo $gradient; ?>);
	background-image: -ms-linear-gradient(<?php echo $gradient; ?>);

	background-image: -webkit-gradient(
		linear,
		left bottom,
		left top,
		color-stop(0.01, rgb(<?php echo hex2rgb($gradientColor1);?>)),
		color-stop(0.25, rgb(<?php echo hex2rgb($gradientColor2);?>)),
		color-stop(0.5, rgb(<?php echo hex2rgb($gradientColor3);?>))
	);
}

input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
	display: none;
	-webkit-appearance: none;
	-moz-appearance: none;
	margin: 0; /* <-- Apparently some margin are still there even though it's hidden */
}

@media (max-width: 1662px){
	div.navContainer { margin: 20px auto 0; }
}
@media (max-width: 800px) {
	div.dashboardIncoming { width: 100%; }
	div.dashboardOutgoing { width: 100%; }
	.mobileRow {
		display: block;
	}
	.desktopRow {
		display: none;
	}

	.form-input textarea,
	.form-input input {
		width: auto;
	}

	div.mainContainer.client .logoutContainer {
		text-align: center;
		width: 100%;
		float: none;
	}

	div.mainContainer.client .content {
		margin: 0;
	}

	div.mainContainer.client .logoContainer {
		text-align: center;
		width: 100%;
		margin: 20px 0;
	}

	div.headerRow.client {
		padding: 10px 0;
		width: 100%;
	}
}
@media (max-width: 600px){
	div.navContainer { margin-top: 0px; }
	div.headerRow { width: 95%; }
		div.logoutContainer { width: 100%; }
		div.logoutContainer a.navButton { width: 100%; }
		div.navLogo { margin: auto; margin-top: 20px; margin-bottom: 20px; }
		a.navButton, a.navButtonLogout { 
			text-align: center;
			margin: 0px; border: 0px;
			padding: 5%;
		}
		a.navButton{ 			
			width: 40%; 
		}
		a.navButtonLogout { 
			width: 100%; 
		}
}
@media (max-width: 300px){
	div.headerRow { width: 260px; }
		a.navButton, a.navButtonLogout{ width: 100%; }
}

