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

	public static function displayForm( $name, $fields = array(), $title = '' )
	{
		print "<div class=\"form-input\">\n";
		if( !empty( $title ) ) {
			printf( '<h3>%s</h5>',
				htmlentities( $title )
			);
		}
		printf( "<form id=\"%s\">\n",
			htmlentities( $name )
		);

		foreach( $fields as $field ) {
    
			printf( "\t<div>\n" );

			if( in_array( $field['type'], array( 'text', 'number', 'tel', 'email', 'url' ) ) ) {

				printf( "\t<label for=\"%s\">%s</label>\n", 
						htmlentities( $field['id'] ), 
						htmlentities( $field['label'] )
				);
				printf( "\t<input type=\"%s\" name=\"%s\" id=\"%s\" value=\"%s\"%s%s />\n", 
						htmlentities( $field['type'] ),
						htmlentities( $field['id'] ),
						htmlentities( $field['id'] ),
						( !empty( $field['value'] ) ? htmlentities( $field['value'] ) : '' ),
						( 'number' == $field['type'] ? ' pattern="[0-9]*"' : '' ),
						( !empty( $field['required'] ) ? ' required' : '' )
				);

			} else if( 'textarea' == $field['type'] ) {

				printf( "\t<label for=\"%s\">%s</label>\n", 
						htmlentities( $field['id'] ), 
						htmlentities( $field['label'] )
				);
				printf( "\t<textarea name=\"%s\" id=\"%s\"%s>%s</textarea>\n",
						htmlentities( $field['id'] ),
						htmlentities( $field['id'] ),
						( !empty( $field['required'] ) ? ' required' : '' ),
						( !empty( $field['value'] ) ? htmlentities( $field['value'] ) : '' )
				);

			} else if( 'select' == $field['type'] ) {

				printf( "\t<label for=\"%s\">%s</label>\n", 
						htmlentities( $field['id'] ), 
						htmlentities( $field['label'] )
				);
				printf( "\t<select name=\"%s\" id=\"%s\">\n",
					htmlentities( $field['id'] ),
					htmlentities( $field['id'] )
				);
				printf( "\t\t<option value=\"\"></option>\n" );
				foreach( $field['choices'] as $key => $val ) {
					printf( "\t\t<option value=\"%s\"%s>%s</option>\n",
						htmlentities( $key ),
						( !empty( $field['value'] && $key == $field['value'] ) ? ' selected="selected"' : '' ),
						htmlentities( $val )
					);
				}
				printf( "\t</select>\n" );

			} else if( 'button' == $field['type'] ) {

				printf( "\t<input type=\"button\" value=\"%s\" />\n",
					htmlentities( $field['label'] )
				);

			} else if( 'hidden' == $field['type'] ) {

				printf( "\t<input type=\"hidden\" name=\"%s\" id=\"%s\" value=\"%s\" />\n",
					htmlentities( $field['id'] ),
					htmlentities( $field['id'] ),
					htmlentities( $field['value'] )
				);

			} else if( 'submit' == $field['type'] ) {

				printf( "\t<label></label>\n" );
				printf( "\t<input type=\"submit\" value=\"%s\" />\n",
					htmlentities( $field['label'] )
				);

			} else if( '_divider' == $field['type'] ) {

				printf( "\t<hr class=\"divider\" />\n" );

			} else if( '_header' == $field['type'] ) {

				printf( "\t<label></label>\n" );
				printf( "\t<h3>%s</h3>\n",
					htmlentities( $field['label'] )
				);

			}

			printf( "\t</div>\n" );

		}

		print "</form>\n";
		print "</div>\n";
	}	
}
