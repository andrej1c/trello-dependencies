<?php
function config_value( $key = 'empty' ) {
	$config = [
		'max_dependencies' => 5,
		'include_non_dependent' => true,
		'exclude_list_ids' => [
			'5a2025d44f29452f2d48f752',
		],
	];

	if ( isset( $config[ $key ] ) ) {
		return $config[ $key ];
	}

	return $config;
}
