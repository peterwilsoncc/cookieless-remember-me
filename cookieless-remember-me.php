<?php
/**
 * Cookieless Remember Me
 *
 * @package           CookielessRememberMe
 * @author            Peter Wilson
 * @copyright         YYYY Peter Wilson
 * @license           MIT
 *
 * @wordpress-plugin
 * Plugin Name: Cookieless Remember Me
 * Description: Cookieless Remember Me
 * Version: 1.0.0
 * Requires at least: 6.6
 * Requires PHP: 8.0
 * Author: Peter Wilson
 * Author URI: https://peterwilson.cc
 * License: MIT
 * Text Domain: cookieless-remember-me
 */

namespace PWCC\CookielessRememberMe;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/inc/namespace.php';

bootstrap();
