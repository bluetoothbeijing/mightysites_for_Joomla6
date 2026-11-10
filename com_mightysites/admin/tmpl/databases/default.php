<?php
/**
 * @package    MightySites
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.multiselect');

$user      = Factory::getApplication()->getIdentity();
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
?>
<form action="<?php echo Route::_('index.php?option=com_mightysites&view=databases'); ?>" method="post" name="adminForm" id="adminForm">
	<div class="row">
		<div class="col-md-12">
			<div id="j-main-container" class="j-main-container">
				<?php echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>
				<?php if (empty($this->items)) : ?>
					<div class="alert alert-info">
						<span class="icon-info-circle" aria-hidden="true"></span>
						<?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
					</div>
				<?php else : ?>
				<table class="table table-striped" id="databasesList">
					<thead>
						<tr>
							<th class="w-1 text-center">
								<?php echo HTMLHelper::_('grid.checkall'); ?>
							</th>
							<th>
								<?php echo HTMLHelper::_('searchtools.sort', 'COM_MIGHTYSITES_HEADING_DATABASE_TITLE', 'a.domain', $listDirn, $listOrder); ?>
							</th>
							<th class="w-15 text-center d-none d-md-table-cell">
								<?php echo HTMLHelper::_('searchtools.sort', 'COM_MIGHTYSITES_HEADING_DATABASE_ORIGIN', 'a.type', $listDirn, $listOrder); ?>
							</th>
							<th class="w-15 text-center">
								<?php echo HTMLHelper::_('searchtools.sort', 'COM_MIGHTYSITES_HEADING_DATABASE_NAME', 'a.db', $listDirn, $listOrder); ?>
							</th>
							<th class="w-15 text-center">
								<?php echo HTMLHelper::_('searchtools.sort', 'COM_MIGHTYSITES_HEADING_DATABASE_PREFIX', 'a.dbprefix', $listDirn, $listOrder); ?>
							</th>
							<th class="w-1 text-center">
								<?php echo HTMLHelper::_('searchtools.sort', 'ID', 'a.id', $listDirn, $listOrder); ?>
							</th>
						</tr>
					</thead>

					<tfoot>
						<tr>
							<td colspan="5">
								<?php echo $this->pagination->getListFooter(); ?>
							</td>
						</tr>
					</tfoot>

					<tbody>
					<?php foreach ($this->items as $i => $item) :
						$canEdit    = $user->authorise('core.edit', 'com_mightysites');
						$canCheckin = $user->authorise('core.manage', 'com_checkin') || $item->checked_out == $user->get('id') || is_null($item->checked_out_time) || $item->checked_out == 0;

						// Sites are edited via own link!
						if ($item->type == 1)
						{
							$link = 'index.php?option=com_mightysites&task=site.edit&id=' . (int) $item->id;
						}
						else
						{
							$link = 'index.php?option=com_mightysites&task=database.edit&id=' . (int) $item->id;
						}
					?>
						<tr class="row<?php echo $i % 2; ?>">
							<td class="text-center">
								<?php if ($item->type == 2) : ?>
									<?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
								<?php endif; ?>
							</td>

							<td>
								<?php if ($item->checked_out) : ?>
									<?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'databases.', $canCheckin); ?>
								<?php endif; ?>

								<?php if ($canEdit) : ?>
									<a href="<?php echo Route::_($link); ?>"><?php echo $item->domain; ?></a>
								<?php else : ?>
									<?php echo $item->domain; ?>
								<?php endif; ?>
							</td>

							<td class="text-center d-none d-md-table-cell">
								<?php echo ($item->type == 1) ? Text::_('COM_MIGHTYSITES_DATABASE_ORIGIN_SITE') : Text::_('COM_MIGHTYSITES_DATABASE_ORIGIN_DATABASE'); ?>
							</td>

							<td class="text-center"><?php echo @$item->db; ?></td>
							<td class="text-center"><?php echo @$item->dbprefix; ?></td>
							<td class="text-center"><?php echo $item->id; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php endif; ?>

				<input type="hidden" name="delete_tables" value="" />
				<input type="hidden" name="task" value="" />
				<input type="hidden" name="boxchecked" value="0" />
				<?php echo HTMLHelper::_('form.token'); ?>
			</div>
		</div>
	</div>
</form>

<script type="text/javascript">
	Joomla.submitbutton = function(task) {
		if (task === 'databases.remove' && window.confirm('<?php echo Text::_('COM_MIGHTYSITES_REALLY_DELETE_DB'); ?>')) {
			document.adminForm.delete_tables.value = 'true';
		}
		Joomla.submitform(task, document.getElementById('adminForm'));
	}
</script>
