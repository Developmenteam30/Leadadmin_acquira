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
					htmlentities( $error->stamp ),
					htmlentities( $error->origination ),
					htmlentities( $error->description ) );
			}
		}
	}

	public static function displayForm( $name, $fields = array(), $title = '' )
	{
		print "<div class=\"form-input\">\n";
		if( !empty( $title ) ) {
			printf( '<h3>%s</h3>',
				htmlentities( $title )
			);
		}
		printf( "<form id=\"%s\">\n",
			htmlspecialchars( $name, ENT_QUOTES | ENT_HTML5 )
		);

		foreach( $fields as $field ) {

			printf( "\t<div>\n" );

			if( in_array( $field['type'], array( 'text', 'number', 'tel', 'date', 'email', 'password', 'url' ) ) ) {

				printf( "\t<label for=\"%s\">%s</label>\n",
						htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
						htmlentities( $field['label'] )
				);
				printf( "\t<input type=\"%s\" name=\"%s\" id=\"%s\" value=\"%s\"%s%s%s />\n",
						htmlspecialchars( $field['type'], ENT_QUOTES | ENT_HTML5 ),
						htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
						htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
						( !empty( $field['value'] ) ? htmlspecialchars( $field['value'], ENT_QUOTES | ENT_HTML5 ) : '' ),
						( 'number' == $field['type'] ? ' pattern="[0-9]*"' : '' ),
						( !empty( $field['required'] ) ? ' required' : '' ),
						( !empty( $field['readonly'] ) ? ' readonly' : '' )
				);

			} else if( 'currency' == $field['type'] ) {

				printf( "\t<label for=\"%s\">%s</label>\n",
						htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
						htmlentities( $field['label'] )
				);
				printf( "\t<input type=\"text\" name=\"%s\" id=\"%s\" pattern=\"^\\$?(([1-9](\\d*|\\d{0,2}(,\\d{3})*))|0)(\\.\\d{1,2})?$\" value=\"%s\"%s%s />\n",
						htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
						htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
						( !empty( $field['value'] ) ? htmlspecialchars( $field['value'], ENT_QUOTES | ENT_HTML5 ) : '' ),
						( !empty( $field['required'] ) ? ' required' : '' ),
						( !empty( $field['readonly'] ) ? ' readonly' : '' )
				);

			} else if( 'checkbox' == $field['type'] ) {

				printf( "\t<label for=\"%s\">%s%s</label>%s\n",
						htmlspecialchars( $field['id'] ),
						htmlspecialchars( $field['label'] ),
						( !empty( $field['required'] ) ? ' <span class="required">*</span> ' : '' ),
						( !empty( $field['label_append'] ) ? $field['label_append'] : '' )
				);
				print '<div class="checkbox-choices">';
				foreach( $field['choices'] as $key => $val ) {
					printf( "\t<input type=\"checkbox\" name=\"%s[]\" value=\"%s\"%s /> %s%s\n",
						htmlspecialchars( $field['id'] ),
						htmlspecialchars( $key ),
						( !empty( $field['value'][$key] ) ? ' checked="checked"' : '' ),
						htmlspecialchars( $val ),
						( !empty( $field['choice_append'] ) ? $field['choice_append'] : '' )
					);
				}
				print '</div>';

			} else if( 'textarea' == $field['type'] ) {

				printf( "\t<label for=\"%s\">%s</label>\n",
						htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
						htmlentities( $field['label'] )
				);
				printf( "\t<textarea name=\"%s\" id=\"%s\"%s>%s</textarea>\n",
						htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
						htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
						( !empty( $field['required'] ) ? ' required' : '' ),
						( !empty( $field['value'] ) ? htmlentities( $field['value'] ) : '' )
				);

			} else if( 'select' == $field['type'] ) {

				printf( "\t<label for=\"%s\">%s</label>\n",
						htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
						htmlentities( $field['label'] )
				);
				printf( "\t<select name=\"%s\" id=\"%s\">\n",
					htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
					htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 )
				);
				if( !empty( $field['placeholder'] ) ) {
					printf( "\t\t<option disabled=\"disabled\"%s value=\"\">%s</option>\n",
						empty( $field['value'] ) ? ' selected="selected"' : '',
						htmlentities( $field['placeholder'] )
					);
				} else {
					printf( "\t\t<option value=\"\"></option>\n" );
				}
				foreach( $field['choices'] as $key => $val ) {
					printf( "\t\t<option value=\"%s\"%s>%s</option>\n",
						htmlspecialchars( $key, ENT_QUOTES | ENT_HTML5 ),
						( !empty( $field['value'] ) && $key == $field['value'] ? ' selected="selected"' : '' ),
						htmlentities( $val )
					);
				}
				printf( "\t</select>\n" );

			} else if( 'button' == $field['type'] ) {

				printf( "\t<input type=\"button\" value=\"%s\" />\n",
					htmlspecialchars( $field['label'], ENT_QUOTES | ENT_HTML5 )
				);

			} else if( 'hidden' == $field['type'] ) {

				printf( "\t<input type=\"hidden\" name=\"%s\" id=\"%s\" value=\"%s\" />\n",
					htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
					htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
					htmlspecialchars( $field['value'], ENT_QUOTES | ENT_HTML5 )
				);

			} else if( 'submit' == $field['type'] ) {

				printf( "\t<label></label>\n" );
				printf( "\t<input type=\"submit\" value=\"%s\" />\n",
					htmlspecialchars( $field['label'], ENT_QUOTES | ENT_HTML5 )
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
