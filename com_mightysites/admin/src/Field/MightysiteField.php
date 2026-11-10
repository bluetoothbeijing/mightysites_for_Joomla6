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
 * Site select list.
 */
class MightysiteField extends ListField
{
	protected $type = 'MightySite';

	protected function getOptions()
	{
		$options = array();

		$sites = MightysitesHelper::getSites();

		foreach ($sites as $site)
		{
			if ($site->type == 1 || (string) $this->element['database'] !== 'false')
			{
				$options[] = HTMLHelper::_('select.option', $site->id, $site->domain);
			}
		}

		// Merge any additional options in the XML definition.
		return array_merge(parent::getOptions(), $options);
	}
}
