<?php 
//ADMIN_ROOT/d_shared.php
//Version 1.0
//Shared display ajax items.
switch($_REQUEST['d']){
	case 'errorCount':		
		$errorCount = getErrorCount();
		if($errorCount === false){ echo "X"; } else { echo $errorCount; }
		exit;
	break;
	case 'errorList':
		$errorList = getErrors();
?>
<div class='fr'>
	<a href='#' class='nonLink' onclick='closeContent("errorList");' >Close [X]</a>
</div>
<?php
if($errorList === false){ echo "Error fetching errors list."; } 
elseif($errorList == 0){ echo "No errors listed for today."; } 
else { 
	foreach($errorList as $error){ 
?>
<p>(<?php echo $error->stamp; ?>) [<?php echo $error->origination; ?>] : <?php echo $error->description; ?></p>
<?php
	}
}
		exit;
	break;
}
?>
