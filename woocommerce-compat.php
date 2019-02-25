<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wc_print_r' ) ) {
	/**
	 * Prints human-readable information about a variable.
	 *
	 * Some server environments blacklist some debugging functions. This function provides a safe way to
	 * turn an expression into a printable, readable form without calling blacklisted functions.
	 *
	 * @since 3.0
	 *
	 * @param mixed $expression The expression to be printed.
	 * @param bool $return Optional. Default false. Set to true to return the human-readable string.
	 *
	 * @return string|bool False if expression could not be printed. True if the expression was printed.
	 *     If $return is true, a string representation will be returned.
	 */
	function wc_print_r( $expression, $return = false ) {
		$alternatives = array(
			array(
				'func' => 'print_r',
				'args' => array( $expression, true ),
			),
			array(
				'func' => 'var_export',
				'args' => array( $expression, true ),
			),
			array(
				'func' => 'json_encode',
				'args' => array( $expression ),
			),
			array(
				'func' => 'serialize',
				'args' => array( $expression ),
			),
		);

		$alternatives = apply_filters( 'woocommerce_print_r_alternatives', $alternatives, $expression );

		foreach ( $alternatives as $alternative ) {
			if ( function_exists( $alternative['func'] ) ) {
				$res = call_user_func_array( $alternative['func'], $alternative['args'] );
				if ( $return ) {
					return $res;
				} else {
					echo $res; // WPCS: XSS ok.

					return true;
				}
			}
		}

		return false;
	}
}