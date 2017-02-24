<?php

require_once( INCLUDES . 'leads.php' );

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
		printf( "<form class=\"form-inline\" id=\"%s\">\n",
			htmlspecialchars( $name, ENT_QUOTES | ENT_HTML5 )
		);

		foreach( $fields as $field ) {

			if( isset( $field['active'] ) && false === $field['active'] ) {
				continue;
			}

			printf( "\t<div>\n" );

			if( in_array( $field['type'], array( 'text', 'number', 'tel', 'date', 'email', 'password', 'url' ) ) ) {

				printf( "\t<label data-for=\"%s\">%s</label>\n",
						htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
						htmlentities( $field['label'] )
				);
				printf( "\t<input class=\"form-control\" type=\"%s\" name=\"%s\" id=\"%s\" value=\"%s\"%s%s%s />\n",
						htmlspecialchars( $field['type'], ENT_QUOTES | ENT_HTML5 ),
						htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
						htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
						( !empty( $field['value'] ) ? htmlspecialchars( $field['value'], ENT_QUOTES | ENT_HTML5 ) : '' ),
						( 'number' == $field['type'] ? ' pattern="[0-9]*"' : '' ),
						( !empty( $field['required'] ) ? ' required' : '' ),
						( !empty( $field['readonly'] ) ? ' readonly' : '' )
				);

			} else if( 'currency' == $field['type'] ) {

				printf( "\t<label data-for=\"%s\">%s</label>\n",
						htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
						htmlentities( $field['label'] )
				);
				printf( "\t<input class=\"form-control\" type=\"text\" name=\"%s\" id=\"%s\" pattern=\"^\\$?(([1-9](\\d*|\\d{0,2}(,\\d{3})*))|0)(\\.\\d{1,2})?$\" value=\"%s\"%s%s />\n",
						htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
						htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
						( !empty( $field['value'] ) ? htmlspecialchars( $field['value'], ENT_QUOTES | ENT_HTML5 ) : '' ),
						( !empty( $field['required'] ) ? ' required' : '' ),
						( !empty( $field['readonly'] ) ? ' readonly' : '' )
				);

			} else if( 'checkbox' == $field['type'] ) {

				printf( "\t<label data-for=\"%s\">%s%s</label>%s\n",
						htmlspecialchars( $field['id'] ),
						htmlspecialchars( $field['label'] ),
						( !empty( $field['required'] ) ? ' <span class="required">*</span> ' : '' ),
						( !empty( $field['label_append'] ) ? $field['label_append'] : '' )
				);
				print '<div class="checkbox-choices">';
				if( !empty( $field['choices'] ) && is_array( $field['choices'] ) ) {
					foreach( $field['choices'] as $key => $val ) {
						printf( "\t<input class=\"form-control\" type=\"checkbox\" name=\"%s%s\" value=\"%s\"%s /> %s%s\n",
							htmlspecialchars( $field['id'] ),
							( sizeOf( $field['choices'] ) > 1 ? '[]' : '' ),
							htmlspecialchars( $key ),
							( !empty( $field['value'][$key] ) ? ' checked="checked"' : '' ),
							htmlspecialchars( $val ),
							( !empty( $field['choice_append'] ) ? $field['choice_append'] : '' )
						);
					}
				}
				print '</div>';

			} else if( 'radio' == $field['type'] ) {

				printf( "\t<label data-for=\"%s\">%s%s</label>\n",
						htmlspecialchars( $field['id'] ),
						htmlspecialchars( $field['label'] ),
						( !empty( $field['required'] ) ? ' <span class="required">*</span> ' : '' )
				);
				if( !empty( $field['choices'] ) && is_array( $field['choices'] ) ) {
					foreach( $field['choices'] as $key => $val ) {
						printf( "\t<input type=\"radio\" name=\"%s\" value=\"%s\"%s%s /> %s%s\n",
							htmlspecialchars( $field['id'] ),
							htmlspecialchars( $key ),
							( !empty( $field['value'] ) && $key == $field['value'] ) ? ' checked="checked"' : '',
							( !empty( $field['required'] ) ? ' required="required" ' : '' ),
							htmlspecialchars( $val ),
							( !empty( $field['choice_append'] ) ? $field['choice_append'] : '' )
						);
					}
				}

			} else if( 'textarea' == $field['type'] ) {

				printf( "\t<label data-for=\"%s\">%s</label>\n",
						htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
						htmlentities( $field['label'] )
				);
				printf( "\t<textarea class=\"form-control\" name=\"%s\" id=\"%s\"%s>%s</textarea>\n",
						htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
						htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
						( !empty( $field['required'] ) ? ' required' : '' ),
						( !empty( $field['value'] ) ? htmlentities( $field['value'] ) : '' )
				);

			} else if( 'select' == $field['type'] ) {

				printf( "\t<label data-for=\"%s\">%s</label>\n",
						htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
						htmlentities( $field['label'] )
				);
				printf( "\t<select class=\"form-control\" name=\"%s%s\" id=\"%s\"%s%s>\n",
					htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
					( !empty( $field['multiple'] ) ? '[]' : '' ),
					htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
					( !empty( $field['readonly'] ) ? ' readonly' : '' ),
					( !empty( $field['multiple'] ) ? ' multiple' : '' )
				);
				if( isset( $field['placeholder'] ) ) {
					if( !empty( $field['placeholder'] ) ) {
						printf( "\t\t<option disabled=\"disabled\"%s value=\"\">%s</option>\n",
							empty( $field['value'] ) ? ' selected="selected"' : '',
							htmlentities( $field['placeholder'] )
						);
					}
				} else {
					printf( "\t\t<option value=\"\"></option>\n" );
				}
				foreach( $field['choices'] as $key => $val ) {
					if( is_array( $val ) ) {
						printf( "\t\t<optgroup label=\"%s\">\n",
							htmlentities( $key, ENT_QUOTES | ENT_HTML5 )
						);
						foreach( $val as $rec_key => $rec_val ) {
							$selected = false;
							if( isset( $field['value'] ) && is_array( $field['value'] ) ) {
								if( array_key_exists( $rec_key, $field['value'] ) ) {
									$selected = true;
								}
							} else if( !empty( $field['value'] ) && $rec_key == $field['value'] ) {
								$selected = true;
							}
							printf( "\t\t\t<option value=\"%s\"%s>%s</option>\n",
								htmlentities( $rec_key, ENT_QUOTES | ENT_HTML5 ),
								$selected ? ' selected="selected"' : '',
								htmlentities( $rec_val, ENT_HTML5 )
							);
						}
						print "\t\t</optgroup>\n";
					} else {
						$selected = false;
						if( isset( $field['value'] ) && is_array( $field['value'] ) ) {
							if( array_key_exists( $key, $field['value'] ) ) {
								$selected = true;
							}
						} else if( !empty( $field['value'] ) && $key == $field['value'] ) {
							$selected = true;
						}
						printf( "\t\t<option value=\"%s\"%s>%s</option>\n",
							htmlentities( $key, ENT_QUOTES | ENT_HTML5 ),
							$selected ? ' selected="selected"' : '',
							htmlentities( $val, ENT_HTML5 )
						);
					}
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

			} else if( '_html' == $field['type'] ) {

				printf( "\t<label data-for=\"%s\">%s</label>\n",
						htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
						htmlspecialchars( $field['label'], ENT_QUOTES | ENT_HTML5 )
				);
				printf( "\t<span>%s</span>\n", $field['value'] );

			} else if( '_text' == $field['type'] ) {

				printf( "\t<label data-for=\"%s\">%s</label>\n",
						htmlspecialchars( $field['id'], ENT_QUOTES | ENT_HTML5 ),
						htmlspecialchars( $field['label'], ENT_QUOTES | ENT_HTML5 )
				);
				printf( "\t<span>%s</span>\n", htmlspecialchars( $field['value'], ENT_QUOTES | ENT_HTML5 ) );

			}

			printf( "\t</div>\n" );

		}

		print "</form>\n";
		print "</div>\n";
	}
}
