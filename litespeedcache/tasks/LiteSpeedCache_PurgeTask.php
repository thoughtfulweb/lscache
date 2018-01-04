<?php
namespace Craft;

// getTotalSteps nicked from supercool's cacheMonster plugin [https://github.com/supercool/Cache-Monster]

class LiteSpeedCache_PurgeTask extends BaseTask
{

	// Properties
	// =========================================================================

	/**
	 * @var
	 */
	private $_paths;


	// Public Methods
	// =========================================================================


	/**
	 * @inheritDoc ITask::getDescription()
	 *
	 * @return string
	 */
	public function getDescription()
	{
		return Craft::t('Purging the LS Cache...');
	}

	/**
	 * @inheritDoc ITask::getTotalSteps()
	 *
	 * @return int
	 */
	public function getTotalSteps()
	{


		// Get the actual paths out of the settings
		$paths = $this->getSettings()->paths;

		// Make our internal paths array
		$this->_paths = array();

		// Split the $paths array into chunks of 20 - each step
		// will be a batch of 20 requests
		$this->_paths = array_chunk($paths, 20);

		// Count our final chunked array
		return count($this->_paths);
	}

	/**
	 * @inheritDoc ITask::runStep()
	 *
	 * @param int $step
	 *
	 * @return bool
	 */
	public function runStep($step)
	{

		// Loop the paths in this step
		$mh = curl_multi_init();

		$upOne = realpath(__DIR__ . '/..');
		if (!is_dir($upOne.'/logs')) {
			mkdir($upOne.'/logs', 0777, true);
		}
		array_map('unlink', glob($upOne.'/logs/*'));

		foreach ($this->_paths[$step] as $key=>$path)
		{
			LiteSpeedCachePlugin::log('Purging URL: ' . $path);

			$ch[$key] = curl_init();

			// Set query data here with the URL
			curl_setopt($ch[$key], CURLOPT_URL, $path);
			curl_setopt($ch[$key], CURLOPT_VERBOSE, true);
			$fp = fopen($upOne.'/logs/curl-log'.$key.'.txt', 'w');
			curl_setopt($ch[$key], CURLOPT_STDERR, $fp);
			curl_setopt($ch[$key], CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch[$key], CURLOPT_TIMEOUT, 3);

			curl_setopt($ch[$key], CURLOPT_CUSTOMREQUEST, "PURGE");
			$remove = craft()->db->createCommand()->delete('lsclearance', 'path=:path', array(':path'=>$path));

			curl_multi_add_handle($mh, $ch[$key]);
		}

		do {
			curl_multi_exec($mh, $running);
			curl_multi_select($mh);
		} while ($running > 0);

		foreach(array_keys($ch) as $key){
			curl_multi_remove_handle($mh, $ch[$key]);
		}

		curl_multi_close($mh);

		return true;

	}

	// Protected Methods
	// =========================================================================

	/**
	 * @inheritDoc BaseSavableComponentType::defineSettings()
	 *
	 * @return array
	 */
	protected function defineSettings()
	{
		return array(
			'paths'  => AttributeType::Mixed
		);
	}

	// Private Methods
	// =========================================================================

}