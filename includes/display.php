<?php

require_once( 'leads.php' );

class Display
{
	public static function errorCount()
	{
		$leads = Leads::getInstance();
		$errorCount = $leads->getErrorCount();
		if( $errorCount === false ) {
			print "X";
		} else {
			print $errorCount;
		}
	}

	public static function errorList()
	{
		$leads = Leads::getInstance();
		$errorList = $leads->getErrors();
?>
<div class="fr">
	<a href="#" class="nonLink" onclick="closeContent("errorList");" >Close [X]</a>
</div>
<?php

		if( $errorList === null ) {
			print "Error fetching the errors list.";
		} else if ( sizeOf( $errorList ) == 0 ) {
			print "No errors on file today.";
		} else {
			foreach( $errorList as $error ) {
				printf( '<p>(%s) [%s] %s</p>',
					htmlspecialchars( $error->stamp ),
					htmlspecialchars( $error->origination ),
					htmlspecialchars( $error->description ) );
			}
		}
	}

}
