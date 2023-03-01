<?php
/**
 * Created by PhpStorm.
 * User: Vladimir Zabara <wlady2001@gmail.com>
 * Date: 01.03.2023
 * Time: 10:42
 */

namespace Plugin\Env;


interface PluginInterface {
	public function plugin_name();
	public function plugin_text_domain();
	public function plugin_version();
	public function supported_wp();
	public function supported_wc();
	public function supported_php();
}