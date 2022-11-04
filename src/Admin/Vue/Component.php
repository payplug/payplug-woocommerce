<?php

namespace Payplug\PayplugWoocommerce\Admin\Vue;

class Component {

	/**
	 * @param $text
	 * @param $url
	 * @param $target
	 *
	 * @return array
	 */
	public static function link( $text, $url, $target ) {
		return [
			"text"   => $text,
			"url"    => $url,
			"target" => $target
		];
	}

}
