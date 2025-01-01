<?php

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\Filesystem\File;

// no direct access
defined('_JEXEC') or die('Restricted access');

class plgJ2StorePayment_redsysInstallerScript
{
	function preflight($type, $parent)
	{
		if (!ComponentHelper::isEnabled('com_j2store')) {
			throw new GenericDataException('J2Store not found. Please install J2Store before installing this plugin');
		}
		$version_file = JPATH_ADMINISTRATOR . '/components/com_j2store/version.php';

		if (File::exists($version_file)) {
			require_once($version_file);
			// abort if the current J2Store release is older
			if (version_compare(J2STORE_VERSION, '4.0.10', 'lt')) {
				throw new GenericDataException('You are using an old version of J2Commerce. Please upgrade to the latest version');
			}
		} else {
			throw new GenericDataException('J2Commerce not found or the version file is not found. Make sure that you have installed J2Commerce before installing this plugin');
		}
	}
}
