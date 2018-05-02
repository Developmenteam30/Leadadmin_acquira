<?php

include( "../../includes/c_config.php" );

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_MANAGER );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

require_once( INCLUDES . 'display.php' );

$errors = array();
if( !empty( $_POST['submit'] ) ) {
	foreach( $_POST as $key => $val ) {
		if( preg_match( '/^existingBusinessAmount_([0-9]+)_([0-9]+)$/', $key, $matches ) ) {
			$isValid = true;
			$existingKey = sprintf( 'existingBusinessAmount_%s_%s', $matches[1], $matches[2] );
			//$newKey = sprintf( 'newBusinessAmount_%s_%s', $matches[1], $matches[2] );
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

$title = 'Revenue Expectations';
include( INCLUDES . "c_header.php" );
?>
<body>
<script type="text/javascript">
	$(document).ready(function () {
		display('dialog_revenue_listowners');
	});
</script>

<?php include( INCLUDES . 'c_nav.php' ); ?>

<div class="container-fluid">

	<h2>Revenue Expectations</h2>

	<?php

	if( empty( $_REQUEST['expectationMonth'] ) || strlen( $_REQUEST['expectationMonth'] ) != 6 ) {
		$expectationMonth = date( 'Ym' );
	} else {
		$expectationMonth = $_REQUEST['expectationMonth'];
	}

	?>
	<form id="month-select" method="get">
		<p><strong>Revenue Month:</strong>
			<select name="expectationMonth" id="expectationMonth">
				<?php
				$startMonth = new \DateTime( '2018-01-01' );
				$endMonth = new \DateTime();
				try {
					$endMonth->add( new \DateInterval( 'P6M' ) );
				} catch( \Exception $e ) {
					// Do nothing
				}

				while( $endMonth >= $startMonth ) {
					printf( ' <option value="%s"%s>%s</option>',
						$endMonth->format( 'Ym' ), $expectationMonth == $endMonth->format( 'Ym' ) ? ' selected="selected"' : '', $endMonth->format( 'Y-m' ) );
					try {
						$endMonth->sub( new \DateInterval( 'P1M' ) );
					} catch( \Exception $e ) {
						// Do nothing
					}

				}
				?>
			</select></p>
	</form>

	<?php
	if( !empty( $errors ) ) {
		print '<p class="errors">There were one or more errors while saving your data. Please check the highlighted fields below and only enter numeric values with no dollar signs or commas.</p>' . PHP_EOL;
	}
	?>
	<form method="post">
		<table class="table table-bordered table-condensed table-striped" id="expectationsTable">
			<thead>
			<tr>
				<th>Employee</th>
				<th>Existing Business Expectation</th>
				<th>New Business Expectation</th>
			</tr>
			</thead>
			<tbody>
			<?php
			$users = $leads->getStaffUsers();
			if( !empty( $users ) && is_array( $users ) ) {
				foreach( $users as $key => $val ) {
					$expectationValues = $leads->getExpectationValues( $key, $expectationMonth );
					print '<tr>' . PHP_EOL;
					printf( '<td>%s</td>' . PHP_EOL,
						htmlentities( $val ) );
					printf( '<td class="text-center%s"><input class="text-right" type="text" name="existingBusinessAmount_%s_%s" value="%s" /></td>' . PHP_EOL,
						isset( $errors['existingBusinessAmount_' . $key . '_' . $expectationMonth] ) ? ' error' : '',
						htmlentities( $key ),
						htmlentities( $expectationMonth ),
						htmlentities( isset( $_POST['existingBusinessAmount_' . $key . '_' . $expectationMonth] ) ? $_POST['existingBusinessAmount_' . $key . '_' . $expectationMonth] : ( $expectationValues->existingBusinessAmount ?? 0 ) ) );
					printf( '<td class="text-center">%s</td>' . PHP_EOL,
						htmlentities( isset( $expectationValues->existingBusinessAmount ) ? round( $expectationValues->existingBusinessAmount * .2, 0) : 0 ) );
					/*
										printf( '<td class="text-center%s"><input class="text-right" type="text" name="newBusinessAmount_%s_%s" value="%s" /></td>' . PHP_EOL,
											isset( $errors['newBusinessAmount_' . $key . '_' . $expectationMonth] ) ? ' error' : '',
											htmlentities( $key ),
											htmlentities( $expectationMonth ),
											htmlentities( isset( $_POST['newBusinessAmount_' . $key . '_' . $expectationMonth] ) ? $_POST['newBusinessAmount_' . $key . '_' . $expectationMonth] : ( $expectationValues->newBusinessAmount ?? 0 ) ) );
					*/
					print '</tr>' . PHP_EOL;
				}
			}
			?>
			</tbody>
		</table>
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
