<?php

/*
 * Read file specified by option value
 * if the option value is present and the
 * file exists, place file content in complementary
 * parameter.
 */
function vipgocs_file_option_process_complementary(
	array &$options,
	string $option_name
) :void {
	$option_name_with_file =
		$option_name . '-file';

	if ( ! isset(
		$options[ $option_name_with_file ]
	) ) {
		return;
	}

	/*
	 * Cannot specify both parameters.
	 */
	if ( isset( $options[ $option_name ] ) ) {
		vipgoci_sysexit(
			'Cannot specify both --' . $option_name . ' and ' .
				'--' . $option_name_with_file
		);
	}

	vipgoci_option_file_handle(
		$options,
		$option_name_with_file
	);

	$tmp_file_contents =
		file_get_contents(
			$options[ $option_name_with_file ]
		);

	if ( false === $tmp_file_contents ) {
		vipgoci_sysexit(
			'Unable to read file specified by ' .
				'--' . $option_name_with_file,
			array(
				$option_name_with_file =>
					$options[ $option_name_with_file ],
			)
		);
	}

	$options[ $option_name ] =
		$tmp_file_contents;

	unset(
		$tmp_file_contents
	);
}

