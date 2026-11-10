<?php
/**
 * @package    MightySites
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

namespace AlterBrains\Component\MightySites\Administrator\Field;

defined('_JEXEC') or die;

use AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * Data source select list.
 */
class MightydatabaseField extends ListField
{
	protected $type = 'MightyDatabase';

	protected function getOptions()
	{
		$options = array();

		$sites = MightysitesHelper::getDatabases();

		foreach ($sites as $site)
		{
			$options[] = HTMLHelper::_('select.option', $site->id, $site->domain);
		}

		// Merge any additional options in the XML definition.
		return array_merge(parent::getOptions(), $options);
	}
}
