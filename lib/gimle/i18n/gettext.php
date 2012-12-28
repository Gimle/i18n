<?php
namespace gimle\i18n;

class Gettext
{
	/**
	 * Variable containing last error.
	 * Can be retrieved by calling getLastError method.
	 *
	 * @var mixed
	 */
	private $lastError = null;

	/**
	 * Extract translatable string from .php and .inc files.
	 *
	 * @param mixed $loc string or array for location(s) to search for files.
	 * @param array $params Optional parameters for xgettext.
	 * @return mixed boolean|string String with the po content, or false on failure.
	 */
	public function extract ($loc, $params = array())
	{
		$files = $this->getFileList($loc);
		if ($files === false) {
			return false;
		} elseif (empty($files)) {
			$this->lastError = 'No files found.';
			return false;
		}

		$tempFileList = \gimle\common\make_temp_file();
		$tempOutput = \gimle\common\make_temp_file();

		file_put_contents($tempFileList, implode("\n", $files));

		$options['-kT_gettext'] = '';
		$options['-kT_'] = '';
		$options['-L'] = 'PHP';
		$options['--from-code'] = mb_internal_encoding();
		$options['-o'] = '"' . $tempOutput . '"';
		$options['--no-wrap'] = '';
		$options['-f'] = '"' . $tempFileList . '"';

		$options = array_merge($options, $params);

		$command = 'xgettext';
		foreach ($options as $key => $value) {
			$command .= ' ' . $key;
			if ($value != '') {
				$command .= ' ' . $value;
			}
		}

		$command = 'cd ' . $_SERVER['options']['scan'] . '; ' . $command;
		$result = \gimle\common\run($command);

		$pot = file_get_contents($tempOutput);

		unlink($tempOutput);
		unlink($tempFileList);

		if (($result['return'] !== 0) || (!empty($result['stout']))) {
			$this->lastError = $result;
			$this->lastError['command'] = $command;
			return false;
		}

		return $pot;
	}

	/**
	 * Merge one or more po or pot files.
	 *
	 * @param mixed $files string or array with po file(s) to compile.
	 * @return mixed boolean|binary Binary mo content, or false on failure.
	 */
	public function merge ($files)
	{
		if (!is_array($files)) {
			$files = array($files);
		}

		$temp = \gimle\common\make_temp_file();

		$command = 'msgcat -t ' . mb_internal_encoding() . ' -o ' . $temp . ' ' . implode(' ', $files);
		$result = \gimle\common\run($command);

		if ($result['return'] !== 0) {
			unlink($temp);
			$this->lastError = $result;
			$this->lastError['command'] = $command;
			return false;
		}

		$return = file_get_contents($temp);
		unlink($temp);
		return $return;
	}

	/**
	 * Compile po file(s) to a mo file.
	 *
	 * @param mixed $files string or array with po file(s) to compile.
	 * @return mixed boolean|binary Binary mo content, or false on failure.
	 */
	public function compile ($files)
	{
		$merged = $this->merge($files);
		if ($merged === false) {
			return false;
		}

		$tempPo = \gimle\common\make_temp_file();
		$tempMo = \gimle\common\make_temp_file();

		file_put_contents($tempPo, $merged);

		$command = 'msgfmt -c -v -o ' . $tempMo . ' ' . $tempPo;

		$mo = file_get_contents($tempMo);
		unlink($tempPo);
		unlink($tempMo);

		if ($result['return'] !== 0) {
			$this->lastError = $result;
			$this->lastError['command'] = $command;
			return false;
		}

		return $mo;
	}

	/**
	 * Returns the last error message.
	 */
	public function getLastError ()
	{
		return $this->lastError;
	}

	/**
	 * Make a list of files to search for translations in.
	 *
	 * @param mixed $loc string or array for location(s) to search for files.
	 * @return mixed boolean|array Arry with files, or false on failure.
	 */
	private function getFileList ($loc)
	{
		if (!is_array($loc)) {
			$loc = array($loc);
		}
		foreach ($loc as $dir) {
			$command = 'cd ' . $dir . '; find . -name "*.php" -o -name "*.inc"';
			$result = \gimle\common\run($command);
			if ($result['return'] !== 0) {
				$this->lastError = $result;
				$this->lastError['command'] = $command;
				return false;
			}

			$files = array();
			if (!empty($result['stout'])) {
				foreach ($result['stout'] as $value) {
					$files[] = $value;
				}
			}
		}
		return $files;
	}
}
