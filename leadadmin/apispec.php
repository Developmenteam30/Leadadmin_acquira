<?php
//ADMINROOT/apispec.php
//Version 1.0
//ES20130808 Version 1.0: API Spec automatic generation.
session_start();
$mysqlErrorSource = 'API Spec Page';
include("../c_config.php");
$forceMysqlLogFile = SITE_ROOT."error".FD."log_apispec"; 
include(SITE_ROOT."_connx.php");
include(ADMIN_ROOT."loginCheck.php");
include(ADMIN_ROOT."f_site.php");
include(ADMIN_ROOT."c_loginRequired.php"); //Login is required for this page.

if(!$adminLoggedIn){ 
	if(isset($_REQUEST['a'])){
		$result = array('status' => 0, 'error'=> 'You are no longer logged in. Log back in and try again.');
		echo json_encode($result); exit;
	}
	elseif(isset($_REQUEST['d'])){
		echo "You are no longer logged in. Log back in and try again.";	exit;
	} else { 
		header("Location: index.php"); exit;
	}
}

if(isset($_REQUEST['idFeedIn'])){ 
	$feed = getIncomingFeed($_REQUEST['idFeedIn']);
	$company = getCompany($feed->idCompany);
?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<HTML>
<HEAD>
	<META HTTP-EQUIV="CONTENT-TYPE" CONTENT="text/html; charset=utf-8">
	<TITLE></TITLE>
	<META NAME="GENERATOR" CONTENT="OpenOffice.org 3.1  (Win32)">
	<STYLE TYPE="text/css">
	<!--
		@page { margin: 0.79in }
		TD P { margin-bottom: 0in }
		P { margin-bottom: 0.08in }
	-->
	</STYLE>
</HEAD>
<BODY DIR="LTR">
<P STYLE="margin-bottom: 0in"><FONT FACE="Verdana, sans-serif"><B><?php echo CONFIG_COMPANY_NAME; ?></B></FONT></P>
<P STYLE="margin-bottom: 0in"><BR>
</P>
<P STYLE="margin-bottom: 0in"><FONT FACE="Verdana, sans-serif"><B>Lead
Submission API Specifications</B></FONT></P>
<P STYLE="margin-bottom: 0in"><BR>
</P>
<P STYLE="margin-bottom: 0in"><FONT FACE="Verdana, sans-serif"><B>For:
</B></FONT><FONT FACE="Verdana, sans-serif"><SPAN STYLE="font-weight: normal"><?php echo $company->name; ?></SPAN>
</FONT></P>
<P STYLE="margin-bottom: 0in"><BR>
</P>
<P STYLE="margin-bottom: 0in"><FONT FACE="Verdana, sans-serif">The
lead submission system performs on a key-value pair submission via
HTTP POST or HTTP GET. An XML response is produced after an attempt
to post a lead to the system.</FONT></P>
<P STYLE="margin-bottom: 0in"><BR>
</P>
<P STYLE="margin-bottom: 0in"><FONT FACE="Verdana, sans-serif"><B>Posting
URL</B></FONT><FONT FACE="Verdana, sans-serif">: 
https://<?php echo SITE_URL; ?>/<?php echo LIVE_FOLDER; ?>/<?php echo $feed->label; ?>/livefeed.php</FONT></P>
<P STYLE="margin-bottom: 0in"><BR>
</P>
<P STYLE="margin-bottom: 0in"><FONT FACE="Verdana, sans-serif">Use
the following listcodes for these URLs: </FONT>
</P>
</P>
<P STYLE="margin-bottom: 0in"><FONT FACE="Verdana, sans-serif">Allowed
Fields</FONT></P>
<TABLE WIDTH=666 BORDER=1 BORDERCOLOR="#000000" CELLPADDING=4 CELLSPACING=0>
	<COL WIDTH=66>
	<COL WIDTH=100>
	<COL WIDTH=62>
	<COL WIDTH=165>
	<COL WIDTH=231>
	<TR VALIGN=TOP>
		<TD WIDTH=66>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>Field</FONT></FONT></P>
		</TD>
		<TD WIDTH=100>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>Type</FONT></FONT></P>
		</TD>
		<TD WIDTH=62>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>Required</FONT></FONT></P>
		</TD>
		<TD WIDTH=165>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>Format
			Requirements</FONT></FONT></P>
		</TD>
		<TD WIDTH=231>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>Description</FONT></FONT></P>
		</TD>
	</TR>
	<TR VALIGN=TOP>
		<TD WIDTH=66>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>pswd</FONT></FONT></P>
		</TD>
		<TD WIDTH=100>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>varchar(16)</FONT></FONT></P>
		</TD>
		<TD WIDTH=62>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>Yes</FONT></FONT></P>
		</TD>
		<TD WIDTH=165>
			<P><BR>
			</P>
		</TD>
		<TD WIDTH=231>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2><?php echo $feed->password; ?></FONT></FONT></P>
		</TD>
	</TR>
	<TR VALIGN=TOP>
		<TD WIDTH=66>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>listcode</FONT></FONT></P>
		</TD>
		<TD WIDTH=100>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>varchar(20)</FONT></FONT></P>
		</TD>
		<TD WIDTH=62>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>No</FONT></FONT></P>
		</TD>
		<TD WIDTH=165>
			<P><BR>
			</P>
		</TD>
		<TD WIDTH=231>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>Campaign ID or
			List Descriptor</FONT></FONT></P>
		</TD>
	</TR>
	<TR VALIGN=TOP>
		<TD WIDTH=66>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>url</FONT></FONT></P>
		</TD>
		<TD WIDTH=100>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>varchar(500)</FONT></FONT></P>
		</TD>
		<TD WIDTH=62>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>Yes</FONT></FONT></P>
		</TD>
		<TD WIDTH=165>
			<P><BR>
			</P>
		</TD>
		<TD WIDTH=231>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>Source of the
			Lead</FONT></FONT></P>
		</TD>
	</TR>
	<TR VALIGN=TOP>
		<TD WIDTH=66>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>ip</FONT></FONT></P>
		</TD>
		<TD WIDTH=100>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>varchar(16)</FONT></FONT></P>
		</TD>
		<TD WIDTH=62>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>Yes</FONT></FONT></P>
		</TD>
		<TD WIDTH=165>
			<P><BR>
			</P>
		</TD>
		<TD WIDTH=231>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>IP Address</FONT></FONT></P>
		</TD>
	</TR>
	<TR VALIGN=TOP>
		<TD WIDTH=66>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>stamp</FONT></FONT></P>
		</TD>
		<TD WIDTH=100>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>datetime</FONT></FONT></P>
		</TD>
		<TD WIDTH=62>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>Yes</FONT></FONT></P>
		</TD>
		<TD WIDTH=165>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>YYYY-mm-dd
			HH:ii:ss</FONT></FONT></P>
		</TD>
		<TD WIDTH=231>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>Lead Action Date</FONT></FONT></P>
		</TD>
	</TR>
	<TR VALIGN=TOP>
		<TD WIDTH=66>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>email</FONT></FONT></P>
		</TD>
		<TD WIDTH=100>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>varchar(150)</FONT></FONT></P>
		</TD>
		<TD WIDTH=62>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>Yes</FONT></FONT></P>
		</TD>
		<TD WIDTH=165>
			<P><BR>
			</P>
		</TD>
		<TD WIDTH=231>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>Email Address</FONT></FONT></P>
		</TD>
	</TR>
	<TR VALIGN=TOP>
		<TD WIDTH=66>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>fname</FONT></FONT></P>
		</TD>
		<TD WIDTH=100>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>varchar(50)</FONT></FONT></P>
		</TD>
		<TD WIDTH=62>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>No</FONT></FONT></P>
		</TD>
		<TD WIDTH=165>
			<P><BR>
			</P>
		</TD>
		<TD WIDTH=231>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>First Name</FONT></FONT></P>
		</TD>
	</TR>
	<TR VALIGN=TOP>
		<TD WIDTH=66>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>lname</FONT></FONT></P>
		</TD>
		<TD WIDTH=100>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>varchar(50)</FONT></FONT></P>
		</TD>
		<TD WIDTH=62>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>No</FONT></FONT></P>
		</TD>
		<TD WIDTH=165>
			<P><BR>
			</P>
		</TD>
		<TD WIDTH=231>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>Last Name</FONT></FONT></P>
		</TD>
	</TR>
	<TR VALIGN=TOP>
		<TD WIDTH=66>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>addr</FONT></FONT></P>
		</TD>
		<TD WIDTH=100>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>varchar(150)</FONT></FONT></P>
		</TD>
		<TD WIDTH=62>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>No</FONT></FONT></P>
		</TD>
		<TD WIDTH=165>
			<P><BR>
			</P>
		</TD>
		<TD WIDTH=231>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>Address Line 1</FONT></FONT></P>
		</TD>
	</TR>
	<TR VALIGN=TOP>
		<TD WIDTH=66>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>addr2</FONT></FONT></P>
		</TD>
		<TD WIDTH=100>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>varchar(150)</FONT></FONT></P>
		</TD>
		<TD WIDTH=62>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>No</FONT></FONT></P>
		</TD>
		<TD WIDTH=165>
			<P><BR>
			</P>
		</TD>
		<TD WIDTH=231>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>Address Line 2</FONT></FONT></P>
		</TD>
	</TR>
	<TR VALIGN=TOP>
		<TD WIDTH=66>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>city</FONT></FONT></P>
		</TD>
		<TD WIDTH=100>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>varchar(75)</FONT></FONT></P>
		</TD>
		<TD WIDTH=62>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>No</FONT></FONT></P>
		</TD>
		<TD WIDTH=165>
			<P><BR>
			</P>
		</TD>
		<TD WIDTH=231>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>City</FONT></FONT></P>
		</TD>
	</TR>
	<TR VALIGN=TOP>
		<TD WIDTH=66>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>state</FONT></FONT></P>
		</TD>
		<TD WIDTH=100>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>varchar(25)</FONT></FONT></P>
		</TD>
		<TD WIDTH=62>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>No</FONT></FONT></P>
		</TD>
		<TD WIDTH=165>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>XX</FONT></FONT></P>
		</TD>
		<TD WIDTH=231>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>State</FONT></FONT></P>
		</TD>
	</TR>
	<TR VALIGN=TOP>
		<TD WIDTH=66>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>zip</FONT></FONT></P>
		</TD>
		<TD WIDTH=100>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>varchar(20)</FONT></FONT></P>
		</TD>
		<TD WIDTH=62>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>No</FONT></FONT></P>
		</TD>
		<TD WIDTH=165>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>#####</FONT></FONT></P>
		</TD>
		<TD WIDTH=231>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>Zip</FONT></FONT></P>
		</TD>
	</TR>
	<TR VALIGN=TOP>
		<TD WIDTH=66>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>country</FONT></FONT></P>
		</TD>
		<TD WIDTH=100>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>char(2)</FONT></FONT></P>
		</TD>
		<TD WIDTH=62>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>No</FONT></FONT></P>
		</TD>
		<TD WIDTH=165>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>XX</FONT></FONT></P>
		</TD>
		<TD WIDTH=231>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>2-letter ISO-3166 country code</FONT></FONT></P>
		</TD>
	</TR>
	<TR VALIGN=TOP>
		<TD WIDTH=66>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>dob</FONT></FONT></P>
		</TD>
		<TD WIDTH=100>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>date</FONT></FONT></P>
		</TD>
		<TD WIDTH=62>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>No</FONT></FONT></P>
		</TD>
		<TD WIDTH=165>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>YYYY-mm-dd</FONT></FONT></P>
		</TD>
		<TD WIDTH=231>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>Date of Birth</FONT></FONT></P>
		</TD>
	</TR>
	<TR VALIGN=TOP>
		<TD WIDTH=66>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>gender</FONT></FONT></P>
		</TD>
		<TD WIDTH=100>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>varchar(10)</FONT></FONT></P>
		</TD>
		<TD WIDTH=62>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>No</FONT></FONT></P>
		</TD>
		<TD WIDTH=165>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>M, F</FONT></FONT></P>
		</TD>
		<TD WIDTH=231>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>Gender</FONT></FONT></P>
		</TD>
	</TR>
	<TR VALIGN=TOP>
		<TD WIDTH=66>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>landline</FONT></FONT></P>
		</TD>
		<TD WIDTH=100>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>varchar(20)</FONT></FONT></P>
		</TD>
		<TD WIDTH=62>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>No</FONT></FONT></P>
		</TD>
		<TD WIDTH=165>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>##########</FONT></FONT></P>
		</TD>
		<TD WIDTH=231>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>Default Phone</FONT></FONT></P>
		</TD>
	</TR>
	<TR VALIGN=TOP>
		<TD WIDTH=66>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>cellphone</FONT></FONT></P>
		</TD>
		<TD WIDTH=100>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>varchar(20)</FONT></FONT></P>
		</TD>
		<TD WIDTH=62>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>No</FONT></FONT></P>
		</TD>
		<TD WIDTH=165>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>##########</FONT></FONT></P>
		</TD>
		<TD WIDTH=231>
			<P><FONT FACE="Verdana, sans-serif"><FONT SIZE=2>Alternate Phone</FONT></FONT></P>
		</TD>
	</TR>
</TABLE>
<P STYLE="margin-bottom: 0in"><BR>
</P>
<P STYLE="margin-bottom: 0in; page-break-before: always"><BR>
</P>
<P STYLE="margin-bottom: 0in"><FONT FACE="Verdana, sans-serif"><B>Valid
Response Example</B></FONT></P>
<P STYLE="margin-bottom: 0in"><FONT FACE="Verdana, sans-serif">&lt;?xml
version=&quot;1.0&quot; encoding=&quot;UTF-8&quot;?&gt;</FONT></P>
<P STYLE="margin-bottom: 0in"><FONT FACE="Verdana, sans-serif">&lt;response&gt;</FONT></P>
<P STYLE="margin-bottom: 0in">  <FONT FACE="Verdana, sans-serif">&lt;success&gt;true&lt;/success&gt;</FONT></P>
<P STYLE="margin-bottom: 0in">  <FONT FACE="Verdana, sans-serif">&lt;reason&gt;Successfully
inserted new record.&lt;/reason&gt;</FONT></P>
<P STYLE="margin-bottom: 0in"><FONT FACE="Verdana, sans-serif">&lt;/response&gt;</FONT></P>
<P STYLE="margin-bottom: 0in"><BR>
</P>
<P STYLE="margin-bottom: 0in"><BR>
</P>
<P STYLE="margin-bottom: 0in"><BR>
</P>
<P STYLE="margin-bottom: 0in"><FONT FACE="Verdana, sans-serif"><B>Invalid
Response Examples</B></FONT></P>
<P STYLE="margin-bottom: 0in"><FONT FACE="Verdana, sans-serif">&lt;?xml
version=&quot;1.0&quot; encoding=&quot;UTF-8&quot;?&gt;</FONT></P>
<P STYLE="margin-bottom: 0in"><FONT FACE="Verdana, sans-serif">&lt;response&gt;</FONT></P>
<P STYLE="margin-bottom: 0in">  <FONT FACE="Verdana, sans-serif">&lt;success&gt;false&lt;/success&gt;</FONT></P>
<P STYLE="margin-bottom: 0in">  <FONT FACE="Verdana, sans-serif">&lt;reason&gt;Unauthorized
access.&lt;/reason&gt;</FONT></P>
<P STYLE="margin-bottom: 0in"><FONT FACE="Verdana, sans-serif">&lt;/response&gt;</FONT></P>
<P STYLE="margin-bottom: 0in"><BR>
</P>
<P STYLE="margin-bottom: 0in"><FONT FACE="Verdana, sans-serif">&lt;?xml
version=&quot;1.0&quot; encoding=&quot;UTF-8&quot;?&gt;</FONT></P>
<P STYLE="margin-bottom: 0in"><FONT FACE="Verdana, sans-serif">&lt;response&gt;</FONT></P>
<P STYLE="margin-bottom: 0in">  <FONT FACE="Verdana, sans-serif">&lt;success&gt;false&lt;/success&gt;</FONT></P>
<P STYLE="margin-bottom: 0in">  <FONT FACE="Verdana, sans-serif">&lt;reason&gt;Email
is a required field, and may not be empty.&lt;/reason&gt;</FONT></P>
<P STYLE="margin-bottom: 0in"><FONT FACE="Verdana, sans-serif">&lt;/response&gt;</FONT></P>
</BODY>
</HTML>
<?php
}
?>
