<?php

include( "../../../includes/c_config.php" );

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_MANAGER );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

require_once( INCLUDES . 'display.php' );

function build_calendar( $month, $year ) {

	// Adapted from: https://css-tricks.com/snippets/php/build-a-calendar-table/

	// Create array containing abbreviations of days of week.
	$daysOfWeek = array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' );

	// What is the first day of the month in question?
	$firstDayOfMonth = mktime( 0, 0, 0, $month, 1, $year );

	// How many days does this month contain?
	$numberDays = date( 't', $firstDayOfMonth );

	// Retrieve some information about the first day of the
	// month in question.
	$dateComponents = getdate( $firstDayOfMonth );

	// What is the name of the month in question?
	$monthName = $dateComponents['month'];

	// What is the index value (0-6) of the first day of the
	// month in question.
	$dayOfWeek = $dateComponents['wday'];

	// Create the table tag opener and day headers

	$calendar = "<table class='table table-bordered weights-calendar'>";
	$calendar .= "<tr>";

	// Create the calendar headers

	foreach( $daysOfWeek as $day ) {
		$calendar .= "<th class='header'>$day</th>";
	}

	// Create the rest of the calendar

	// Initiate the day counter, starting with the 1st.

	$currentDay = 1;

	$calendar .= "</tr><tr>";

	// The variable $dayOfWeek is used to
	// ensure that the calendar
	// display consists of exactly 7 columns.

	if( $dayOfWeek > 0 ) {
		$calendar .= "<td colspan='$dayOfWeek'>&nbsp;</td>";
	}

	$month = str_pad( $month, 2, "0", STR_PAD_LEFT );

	while( $currentDay <= $numberDays ) {

		// Seventh column (Saturday) reached. Start a new row.

		if( $dayOfWeek == 7 ) {

			$dayOfWeek = 0;
			$calendar .= "</tr><tr>";

		}

		$currentDayRel = str_pad( $currentDay, 2, "0", STR_PAD_LEFT );

		$date = "$year-$month-$currentDayRel";

		$calendar .= sprintf( "<td class='day' rel='$date'>$monthName $currentDay<br><input type='text' name='%s' value='%s'></td>",
			'',
			'1'
		);

		// Increment counters

		$currentDay++;
		$dayOfWeek++;

	}


	// Complete the row of the last week in month, if necessary

	if( $dayOfWeek != 7 ) {

		$remainingDays = 7 - $dayOfWeek;
		$calendar .= "<td colspan='$remainingDays'>&nbsp;</td>";

	}

	$calendar .= "</tr>";

	$calendar .= "</table>";

	return $calendar;

}

$errors = array();
if( false && !empty( $_POST['submit'] ) ) {
	foreach( $_POST as $key => $val ) {
		if( preg_match( ' /^existingBusinessAmount_( [ 0 - 9 ] +)_( [ 0 - 9 ] +)$/', $key, $matches ) ) {
			$isValid = true;
			$existingKey = sprintf( 'existingBusinessAmount_ % s_ % s', $matches[1], $matches[2] );
			//$newKey = sprintf( 'newBusinessAmount_ % s_ % s', $matches[1], $matches[2] );
			if( !is_numeric( $val ) ) {
				$errors[$existingKey] = true;
				$isValid = false;
			}
			/*
			if( !is_numeric( $_POST[$newKey] ?? 0 ) ) {
				$errors[$newKey] = true;
				$isValid = false;
			}
			*/
			if( $isValid ) {
//				$leads->setExpectationValues( $matches[1], $matches[2], $val, $_POST[$newKey] ?? 0 );
				$leads->setExpectationValues( $matches[1], $matches[2], $val, $val * .2 );
			}
		}
	}
}

if( isset( $_REQUEST['d'] ) ) {
	switch( $_REQUEST['d'] ) {

		case 'errorCount':
			Display::errorCount();
			break;

		case 'errorList':
			Display::errorList();
			break;
	}
	exit;
}

$title = 'Calendar Weights';
include( INCLUDES . "c_header.php" );
?>
<body>
<?php include( INCLUDES . 'c_nav.php' ); ?>

<div class="container-fluid">

	<h2>Forecasting: Calendar Weights</h2>

	<?php

	if( empty( $_REQUEST['weightMonth'] ) || strlen( $_REQUEST['weightMonth'] ) != 6 ) {
		$weightMonth = date( 'Ym' );
	} else {
		$weightMonth = $_REQUEST['weightMonth'];
	}

	?>
	<form id="month-select" method="get">
		<p><strong>Revenue Month:</strong>
			<select name="weightMonth" id="weightMonth">
				<?php
				$startMonth = new \DateTime( '2018-01-01' );
				$endMonth = new \DateTime();
				try {
					$endMonth->add( new \DateInterval( 'P6M' ) );
				} catch( \Exception $e ) {
					// Do nothing
				}

				while( $endMonth >= $startMonth ) {
					printf( '<option value="%s"%s>%s</option> ',
						$endMonth->format( 'Ym' ), $weightMonth == $endMonth->format( 'Ym' ) ? ' selected = "selected"' : '', $endMonth->format( 'Y - m' ) );
					try {
						$endMonth->sub( new \DateInterval( 'P1M' ) );
					} catch( \Exception $e ) {
						// Do nothing
					}

				}
				?>
			</select> <select name="idFeedOut">
				<?php
				$feeds = $leads->getOutboundFeeds( null, 'active' );
				if( $feeds ) {
					foreach( $feeds as $feed ) {
						printf( '<option value="%s">%s - %s(%s)</option>' . PHP_EOL,
							$feed->idFeedOut,
							htmlentities( $feed->name ),
							htmlentities( $feed->label ),
							$feed->idFeedOut
						);
					}
				}
				?>
			</select></p>
	</form>

	<?php
	if( !empty( $errors ) ) {
		print '<p class="errors"> There were one or more errors while saving your data . Please check the highlighted fields below and only enter positive numeric values with no commas .</p> ' . PHP_EOL;
	}
	?>
	<form method="post">
		<?php
		echo build_calendar( substr( $weightMonth, 4, 2 ), substr( $weightMonth, 0, 4 ) );
		?>
		<p class="text-right">
			<input class="btn btn-primary" type="submit" name="submit" value="Save Changes"/>
		</p>
	</form>
</div>
<script type="text/javascript">
	$('#month-select select').change(function (e) {
		e.preventDefault();
		$('#month-select').submit();
	});
</script>
</body>
</html>
