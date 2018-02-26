<?php
function load_json() {
	$json = file_get_contents( 'trello.json' );
	$json_obj = json_decode( $json );
	return $json_obj;
}

/*
 * Return lists
 *
 * @param $json_object JSON Object from trello
 * @return array $lists associative array of list_id => list_name
 */
function get_lists( $json_object ) {
	/*
	params of lists
		id
		name
	*/
	$lists_a = $json_object->lists;
	$lists = [];
	foreach ( $lists_a as $list_obj ) {
		$lists[ $list_obj->id ] = $list_obj->name;
	}
	// exclude lists
	$exclude_list_ids = config_value( 'exclude_list_ids' );
	foreach ( $exclude_list_ids as $list_id ) {
		unset ( $lists[ $list_id ] );
	}
	return $lists;
}

/**
 * Get list colors
 *
 * @param $lists array Associative array of list_id => list_name
 * @return $list_colors Associative array of list_id => list_color
 */
function get_colors( $lists ) {
	$colors = array(
		'yellowgreen',
		'yellow',
		'wheat',
		'violet',
		'turquoise',
		'tomato',
		'thistle',
		'tan',
		'steelblue',
		'springgreen',
		'slategray',
		'slateblue',
		'skyblue',
		'sienna',
		'seashell',
		'seagreen',
		'sandybrown',
		'salmon',
		'saddlebrown',
		'royalblue',
		'rosybrown',
		'red',
		'purple',
		'powderblue',
		'plum',
		'pink',
	);
	$list_colors = [];
	$color_index = 0;
	foreach ( array_keys( $lists ) as $list_id ) {
		$list_colors[ $list_id ] = $colors[ $color_index++ ];
	}
	return $list_colors;
}

function get_cards() {
	/*
	params of cards
		id
		name
		url
		idList
	*/
	$json_object = load_json();
	$cards_a = $json_object->cards;

	$cards = [];
	$lists = get_lists( $json_object );
	$list_colors = get_colors( $lists );
	$list_ids = array_keys( $lists );

	foreach ( $cards_a as $card ) {
		if ( ! in_array( $card->idList, $list_ids, true ) ) {
			continue;
		}
		if ( $card->closed ) {
			continue;
		}
		$cards[ $card->id ] = sprintf( '%s {bg:%s}', $card->name, $list_colors[ $card->idList ] );
	}
	asort( $cards );
	return $cards;
}

function card_dropdown( $cards, $select_name, $selected_id = 0 ) {
	// var_dump( $cards, $select_name, $selected_id ); die();

	$return_str = sprintf( '<select name="%s">', $select_name );
	$return_str .= sprintf( '<option value="0" %s>--Select Dependency--</option>' . PHP_EOL, ( 0 === $selected_id ) ? ' selected="selected"' : '' );
	foreach ( $cards as $card_id => $card_name ) {
		$selected = ( $selected_id === $card_id ) ? ' selected="selected" ' : '';
		$return_str .= sprintf( '<option value="%s" %s>%s</option>' . PHP_EOL, $card_id, $selected, $card_name );
	}
	$return_str .= '</select>';
	return $return_str;
}

function save_dependencies( $data ) {
	$fp = fopen( 'data.json', 'w' );
	fwrite( $fp, json_encode( $data ) );
	fclose( $fp );
}

function load_dependencies() {
	$json = file_get_contents( 'data.json' );
	$json_obj = json_decode( $json );

	$array_of_obj = (array) $json_obj;
	$array_of_array = [];
	foreach ( $array_of_obj as $id => $obj ) {
		$array_of_array[ $id ] = (array) $obj;
	}

	return $array_of_array;
}

function load_dependency( $dependencies, $card_id, $index ) {
	if ( ! isset( $dependencies[ $card_id ] ) ) {
		return 0;
	}
	foreach ( $dependencies[ $card_id ] as $key => $value ) {
		if ( $key === (string) $index ) {
			return $value;
		}
	}
	return 0;
}

function generate_uml_legend_markup() {
	$json_object = load_json();
	$lists = get_lists( $json_object );
	$list_colors = get_colors( $lists );
	$list_ids = array_keys( $lists );
	$colored_lists_a = [];
	$i = 0;
	foreach ( $lists as $list_id => $list_name ) {
		$colored_lists_a[] = sprintf( '[%s {bg:%s}]', treat_card_name( $list_name ), $list_colors[ $list_id ] );
	}
	// Reverse sorting because that's how yuml.me generates the chart
	krsort( $colored_lists_a );

	$return_str = implode( ', ', $colored_lists_a );

	print '<h2>Copy the following legend to paste to yuml.me.</h2>';
	printf( '<textarea cols="80" rows="20">%s</textarea>', $return_str );
}

function generate_uml_markup( $dependencies, $cards ) {
	$max_dependencies = config_value( 'max_dependencies' );
	$dependency_pairs = [];
	$used_cards = [];
	foreach ( $dependencies as $from => $to_array ) {
		$used_tos = [];
		foreach ( $to_array as $to ) {
			if ( empty( $to ) ) {
				continue;
			}
			if ( in_array( $to, $used_tos, true ) ) {
				continue;
			}
			$dependency_pairs[] = [ $from, $to ];
			$used_tos[] = $to;
			$used_cards[] = $to;
			$used_cards[] = $from;
		}
	}

	$return_str_a = [ 'How Goes It Dependencies' ];
	foreach ( $dependency_pairs as $pair ) {
		$return_str_a[] = sprintf( '[%s]->[%s]', treat_card_name( $cards[ $pair[1] ] ), treat_card_name( $cards[ $pair[0] ] ) );
	}


	$include_non_dependent = config_value( 'include_non_dependent' );
	if ( $include_non_dependent ) {
		foreach ( array_keys( $cards ) as $card_id ) {
			if ( ! in_array( $card_id, $used_cards, true ) ) {
				$return_str_a[] = sprintf( '[%s]', treat_card_name( $cards[ $card_id ] ) );
			}
		}
	}

	$return_str = implode( ', ', $return_str_a );
	print '<h2>Copy the following to paste to yuml.me.</h2>';
	printf( '<textarea cols="80" rows="20">%s</textarea>', $return_str );
}

function treat_card_name( $string ) {
	$string = str_replace( '#', 'Task:', $string );
	$string = str_replace( '"', '', $string );
	$string = str_replace( ',', ' ', $string );

	return $string;
}

function config_value( $key = 'empty' ) {
	global $config;

	if ( isset( $config[ $key ] ) ) {
		return $config[ $key ];
	}

	return $config;
}

function generate_form() {
	if ( $_POST['dependencies'] ) {
		save_dependencies( $_POST['dependencies'] );
	}

	$saved_dependencies = (array) load_dependencies();

	$cards = get_cards();

	generate_uml_legend_markup();

	generate_uml_markup( $saved_dependencies, $cards );




?>
<form method="post">
	<table border="1">
		<?php
		$max_dependencies = config_value( 'max_dependencies' );
		foreach ( $cards as $card_id => $card_name ) {
			for ( $i = 1; $i <= $max_dependencies; $i++ ) {
				$dropdown[$i] = card_dropdown(
					$cards,
					sprintf( 'dependencies[%s][%d]', $card_id, $i ),
					load_dependency( $saved_dependencies, $card_id, $i )
				);
			}
			$dropdowns_str = implode( '<br />', $dropdown );
			printf( '<tr><td>%s</td><td>%s</td></tr>', $card_name, $dropdowns_str );
		}
		?>
	</table>
	<input type="submit" name="Submit" />
</form>
<?php
}
