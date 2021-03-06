<?php
namespace gimle\i18n;
use gimle\common\System;

class Locale
{
	public static function pagePrefix ()
	{
		if ((ENV_LEVEL & ENV_WEB) && ((!isset(System::$config['langfreeurl'])) || (!in_array(\gimle\core\page(0), explode(', ', System::$config['langfreeurl']))))) {
			if ((\gimle\core\page(0) === false) || (!array_key_exists(\gimle\core\page(0), System::$config['locale']))) {
				$avail = array_keys(System::$config['locale']);
				$preferred = \gimle\common\get_preferred_language($avail);
				if ($preferred === false) {
					$preferred = $avail[0];
				}

				$location  = BASE_PATH . $preferred;
				$location .= str_replace(array("\r", "\n"), "", (\gimle\core\page(0) !== false ? '/' . implode('/', \gimle\core\page()) : ''));
				if (isset($_SERVER['PATH_INFO']) && (substr($_SERVER['PATH_INFO'], -1, 1) === '/')) {
					$location .= '/';
				}
				if ($_SERVER['QUERY_STRING'] !== '') {
					$location .= '&' . str_replace(array("\r", "\n"), "", $_SERVER['QUERY_STRING']);
				}
				header('Location: ' . $location);
				die();
			}

			if ((isset($_SERVER['PATH_INFO'])) && (trim($_SERVER['PATH_INFO'], '/') !== '')) {
				$path = explode('/', trim($_SERVER['PATH_INFO'], '/'));
				System::$settings['lang'] = array_shift($path);
			} else {
				trigger_error('Failed to load path info for i18n.', E_USER_ERROR);
				die();
			}

			function page ($part = false)
			{
				$path = array();
				if ((isset($_SERVER['PATH_INFO'])) && (trim($_SERVER['PATH_INFO'], '/') !== '')) {
					$path = explode('/', trim($_SERVER['PATH_INFO'], '/'));
					array_shift($path);
				}
				if ($part !== false) {
					if (isset($path[$part])) {
						return $path[$part];
					}
					return false;
				}
				return $path;
			}
		} elseif (ENV_LEVEL & ENV_WEB) {
			function page ($part = false)
			{
				return \gimle\core\page($part);
			}
		}
	}

	public static function load ()
	{
		if (isset(System::$settings['lang'])) {
			if (isset(System::$config['locale'][System::$settings['lang']]['LC'])) {
				foreach (System::$config['locale'][System::$settings['lang']]['LC'] as $key => $value) {
					if (defined('LC_' . $key)) {
						setlocale(constant('LC_' . $key), $value);
					}
				}
			}

			if (isset(System::$config['i18n']['gettext']['load'])) {
				include System::$config['i18n']['gettext']['load'];
			} elseif (file_exists('/usr/share/php/php-gettext/gettext.inc')) {
				include '/usr/share/php/php-gettext/gettext.inc';
			}

			if (isset(System::$config['locale'][System::$settings['lang']]['messages'])) {
				if (!function_exists('__')) {
					trigger_error('php-gettext is not installed or setup correctly. Messages can not be translated.', E_USER_WARNING);
				} else {
					T_setlocale(LC_MESSAGES, System::$config['locale'][System::$settings['lang']]['messages']);
					if ((isset(System::$config['i18n']['gettext']['i18n'])) && (isset(System::$config['locale'][System::$settings['lang']]['domain']))) {
						T_bindtextdomain(System::$config['locale'][System::$settings['lang']]['domain'], System::$config['i18n']['gettext']['i18n']);
						T_bind_textdomain_codeset(System::$config['locale'][System::$settings['lang']]['domain'], mb_internal_encoding());
						T_textdomain(System::$config['locale'][System::$settings['lang']]['domain']);
					}

					function _ ($string)
					{
						$tstring = T_($string);
						$retry = false;
						if ($tstring === $string) {
							$retry = true;
						}

						$pos = strrpos($tstring, '|');
						if ($pos !== false) {
							$tstring = substr($tstring, 0, $pos);
						}

						if ($retry === true) {
							$tstring = T_($tstring);
						}
						return $tstring;
					}
				}
			}
		}

		if (!function_exists('\gimle\i18n\_')) {
			function _ ($string)
			{
				$pos = strrpos($string, '|');
				if ($pos !== false) {
					$string = substr($string, 0, $pos);
				}

				return $string;
			}
		}

		function __ ($string)
		{
			return _($string);
		}
	}
}
