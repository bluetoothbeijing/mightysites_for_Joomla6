<?php
/**
 * @package    MightySites
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

use AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper;
use Joomla\CMS\Language\Text;
?>
<div class="control-group mb-2">
	<div class="controls">
		<?php echo MightysitesHelper::sitesList('tableComponentsSelect', -1, "selectAllTo('tableComponents', this.value)", $this->item->id, Text::_('COM_MIGHTYSITES_SELECT_ALL_TO'), false, '-1', Text::_('COM_MIGHTYSITES_OWN_DATA'), ''); ?>
	</div>
</div>
<hr/>

<?php foreach ($this->tables as $table => $input) : ?>
<div class="control-group mb-2">
	<div class="control-label"><label><?php echo '#__', $table; ?></label></div>
	<div class="controls"><?php echo $input; ?></div>
</div>
<?php endforeach; ?>

<?php
jexit();
