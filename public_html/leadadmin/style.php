<?php
header("Content-type: text/css; charset: UTF-8");
include("../../includes/c_config.php");
include(INCLUDES."f_site.php");
include(INCLUDES."c_sitecustom.php");
?>

html {
	overflow-y: scroll;
}

body {
	font-family: Arial,sans-serif;
}

table.standard { width: 100%; border-collapse: collapse; }
table.standard td { border: 1px solid #CCCCCC; padding: 5px; vertical-align: top; }
table.standard td p { padding: 0 0 3px 0; }
table.standard thead { font-weight: bold; text-align: center; }

table.table-small-font { font-size: 12px; }

table.revenue-report td { padding: 10px; vertical-align: middle; }
table.revenue-report tr.reverse { background-color: #fdfdfd; }
table.revenue-report thead td { font-weight: bold; background-color: <?php echo $gradientColor3; ?>; color: #fff; }
table.revenue-report .greencheck { text-align: center; }

#dialog_revenue_listowners_detail { margin-top: 30px; }

td.revenue { text-align: center; }
td.revenue span:before { content:'$'; }
td.revenue input { width: 70px; font-size: 1em; }
tr.subtotal { font-weight: bold; background-color: #dadada !important; }

h2 {
	margin-top: 0;
}

.hidden-custom {
	display: none;
}

input.input-long {
	width: 300px !important;
}

.navbar-custom {
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

.navbar-custom .navbar-nav > li > a,
.navbar-custom .navbar-nav > li > a:hover {
	color: #fff;
}

table.TF th,
.table th {
	background-color: <?php echo $gradientColor3; ?> !important;
	color: #fff !important;
	text-align: center;
	vertical-align: middle;
}

.table tfoot td {
	font-weight: bold;
}

.table-striped-double tbody:nth-child(odd),
.table-striped-custom tbody tr.striped-master:nth-child(4n-1) {
	background-color: #f9f9f9;
}

table tr.bg-gray {
	background: #dadada;
}

.clickable:hover {
	cursor: pointer;
}

.form-signin {
	max-width: 400px;
	margin: 0 auto;
}

.form-signin img {
	max-width: 400px;
	height: auto;
}

.form-input .checkbox-choices,
.form-input h3 {
	display: inline-block;
}

.form-input .checkbox-choices {
	margin: 5px 0;
}

.form-input label {
	display: inline-block;
	width: 150px;
	text-align: right;
	margin: 5px 10px;
	vertical-align: top;
}

.form-input textarea {
	width: 375px !important;
	height: 75px !important;
}

#new_opportunity textarea {
	height: 200px !important;
}

#note_opportunity textarea {
	width: 75% !important;
	height: 200px !important;
}

.form-input input[type='text'],
.form-input select {
	font-family: Verdana, Helvetica, sans-serif;
}

.form-input input[type='email'],
.form-input input[type='url'],
.form-input input[type='text'] {
	width: 375px;
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

.sort-arrow {
	display: inline !important;
}
.custom-descending {
	background-image: url("/v2/leadadmin/images/downsimple.png");
}

.custom-ascending {
	background-image: url("/v2/leadadmin/images/upsimple.png");
}

#new_pop select,
#edit_pop select,
#email_form select,
#newvertical select,
#edit_offlineledger select,
#new_offlineledger select,
#edit_ledger select,
#new_ledger select {
	width: 300px;
}

.modal-header {
	background: <?php echo $gradientColor1; ?>;
	color: #fff;
}

.btn-primary {
	background: #429038;
}

.close {
	color: #fff;
	opacity: 0.6;
}

.close:focus,
.close:hover {
	color: #dadada;
}

input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
	display: none;
	-webkit-appearance: none;
	-moz-appearance: none;
	margin: 0; /* <-- Apparently some margin are still there even though it's hidden */
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
	position: relative;
	bottom: 0;
	left: 0;
	right: 0;
	margin-top: 30px;
	background-color: #dcdcdc;
	text-align: center;
	font-size: 1.25em;
	padding: 1em 0;
}

.client {
	font-size: 1em;
}

.client div.logoutContainer {
	margin: 20px 0 0 0;
	font-size: 1.50em;
}

#logo-reports {
	max-height: 63px;
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

div.navContainer, div.logoutContainer{
	/* border: 1px solid #000000; */
	-webkit-border-radius: 5px;
	-moz-border-radius: 5px;
	border-radius: 5px;
	overflow: hidden;
}

div.logoContainer { width: 225px; }

.progress {
    margin-bottom: 0;
}

.crm-highlight {
	color: #FF0000;
	font-weight: bold;
}

.pnt-form-row {
	margin-bottom: 0.5em;
}

.pnt-nowrap {
	white-space: nowrap;
}

@media (min-width: 768px) {
	.modal-xl {
		width: 90%;
	}
}

@media (max-width: 768px){

	.table .hide-mobile {
		display: none;
	}

	.form-input textarea,
	.form-input input {
		width: auto;
	}

	.form-input label {
		display: block;
		text-align: left;
		width: auto;
		margin: 5px 0;
	}
}
