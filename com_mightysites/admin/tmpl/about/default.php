<?php
/**
 * @package    MightySites
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Table\Extension;

$component = new Extension(Factory::getDbo());
$component->load(ComponentHelper::getComponent('com_mightysites')->id);
$version = '4.0.0';

if ($component->manifest_cache)
{
	$data = json_decode($component->manifest_cache);

	if ($data && !empty($data->version))
	{
		$version = $data->version;
	}
}
?>
<div class="row">
	<div class="col-md-12">
		<h1><?php echo Text::_('COM_MIGHTYSITES'); ?></h1>
		<p class="lead">Joomla multi-site manager (Joomla 6 port).</p>

		<table class="table w-auto">
			<tr>
				<td><b><?php echo Text::_('COM_MIGHTYSITES_YOUR_VERSION'); ?></b></td>
				<td><?php echo $version; ?></td>
			</tr>
			<tr>
				<td><b>Requires:</b></td>
				<td>Joomla 5.0+ / Joomla 6.x, PHP 8.1+</td>
			</tr>
		</table>

		<h3><?php echo Text::_('COM_MIGHTYSITES_CONFIGURATION'); ?></h3>
		<p>1. Install this component and the <b>System - MightySites</b> plugin.<br/>
		2. Open MightySites &rarr; Sites, add your additional domains.<br/>
		3. configuration.php is patched automatically; each domain gets its own configuration file in <code>/components/com_mightysites/configuration/</code>.</p>
	</div>
</div>
