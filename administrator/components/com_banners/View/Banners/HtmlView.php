<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_banners
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Banners\Administrator\View\Banners;

defined('_JEXEC') or die;

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Component\Banners\Administrator\Model\BannersModel;
use Joomla\Registry\Registry;

/**
 * View class for a list of banners.
 *
 * @since  1.6
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * The search tools form
	 *
	 * @var    Form
	 * @since  1.6
	 */
	public $filterForm;

	/**
	 * The active search filters
	 *
	 * @var    array
	 * @since  1.6
	 */
	public $activeFilters = [];

	/**
	 * Category data
	 *
	 * @var    array
	 * @since  1.6
	 */
	protected $categories = [];

	/**
	 * An array of items
	 *
	 * @var    array
	 * @since  1.6
	 */
	protected $items = [];

	/**
	 * The pagination object
	 *
	 * @var    Pagination
	 * @since  1.6
	 */
	protected $pagination;

	/**
	 * The model state
	 *
	 * @var    Registry
	 * @since  1.6
	 */
	protected $state;

	/**
	 * Method to display the view.
	 *
	 * @param   string  $tpl  A template file to load. [optional]
	 *
	 * @return  void
	 *
	 * @since   1.6
	 * @throws  Exception
	 */
	public function display($tpl = null): void
	{
		/** @var BannersModel $model */
		$model               = $this->getModel();
		$this->categories    = $model->getCategoryOrders();
		$this->items         = $model->getItems();
		$this->pagination    = $model->getPagination();
		$this->state         = $model->getState();
		$this->filterForm    = $model->getFilterForm();
		$this->activeFilters = $model->getActiveFilters();

		// Check for errors.
		if (count($errors = $this->get('Errors')))
		{
			throw new GenericDataException(implode("\n", $errors), 500);
		}

		$this->addToolbar();

		// We do not need to filter by language when multilingual is disabled
		if (!Multilanguage::isEnabled())
		{
			unset($this->activeFilters['language']);
			$this->filterForm->removeField('language', 'filter');
		}

		parent::display($tpl);
	}

	/**
	 * Add the page title and toolbar.
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	protected function addToolbar(): void
	{
		$canDo = ContentHelper::getActions('com_banners', 'category', $this->state->get('filter.category_id'));
		$user  = Factory::getUser();

		// Get the toolbar object instance
		$toolbar = Toolbar::getInstance('toolbar');

		ToolbarHelper::title(Text::_('COM_BANNERS_MANAGER_BANNERS'), 'bookmark banners');

		if ($canDo->get('core.edit.state') || ($this->state->get('filter.published') == -2 && $canDo->get('core.delete')))
		{
			$dropdown = $toolbar->dropdownButton('status-group')
				->text('JTOOLBAR_SELECT_ACTION')
				->toggleSplit(false)
				->icon('mouse-pointer-highlighter')
				->buttonClass('btn btn-white')
				->listCheck(true);

			$childBar = $dropdown->getChildToolbar();

			if ($canDo->get('core.edit.state'))
			{
				if ($this->state->get('filter.published') != 2)
				{
					$childBar->publish('banners.publish')->listCheck(true);

					$childBar->unpublish('banners.unpublish')->listCheck(true);
				}

				if ($this->state->get('filter.published') != -1)
				{
					if ($this->state->get('filter.published') != 2)
					{
						$childBar->archive('banners.archive')->listCheck(true);
					}
					elseif ($this->state->get('filter.published') == 2)
					{
						$childBar->publish('publish')->task('banners.publish')->listCheck(true);
					}
				}

				$childBar->checkin('banners.checkin')->listCheck(true);

				if ($this->state->get('filter.published') != -2)
				{
					$childBar->trash('banners.trash')->listCheck(true);
				}
			}

			if ($this->state->get('filter.published') == -2 && $canDo->get('core.delete'))
			{
				$toolbar->delete('banners.delete')
					->text('JTOOLBAR_EMPTY_TRASH')
					->message('JGLOBAL_CONFIRM_DELETE')
					->listCheck(true);
			}

			// Add a batch button
			if ($user->authorise('core.create', 'com_banners')
				&& $user->authorise('core.edit', 'com_banners')
				&& $user->authorise('core.edit.state', 'com_banners'))
			{
				$childBar->popupButton('batch')
					->text('JTOOLBAR_BATCH')
					->selector('collapseModal')
					->listCheck(true);
			}
		}

		$toolbar->help('JHELP_COMPONENTS_BANNERS_BANNERS');

		if ($user->authorise('core.admin', 'com_banners') || $user->authorise('core.options', 'com_banners'))
		{
			$toolbar->preferences('com_banners');
		}

		if (count($user->getAuthorisedCategories('com_banners', 'core.create')) > 0)
		{
			$toolbar->addNew('banner.add');
		}
	}

	/**
	 * Returns an array of fields the table can be sorted by
	 *
	 * @return  array  Array containing the field name to sort by as the key and display text as value
	 *
	 * @since   3.0
	 */
	protected function getSortFields(): array
	{
		return [
			'ordering'    => Text::_('JGRID_HEADING_ORDERING'),
			'a.state'     => Text::_('JSTATUS'),
			'a.name'      => Text::_('COM_BANNERS_HEADING_NAME'),
			'a.sticky'    => Text::_('COM_BANNERS_HEADING_STICKY'),
			'client_name' => Text::_('COM_BANNERS_HEADING_CLIENT'),
			'impmade'     => Text::_('COM_BANNERS_HEADING_IMPRESSIONS'),
			'clicks'      => Text::_('COM_BANNERS_HEADING_CLICKS'),
			'a.language'  => Text::_('JGRID_HEADING_LANGUAGE'),
			'a.id'        => Text::_('JGRID_HEADING_ID'),
		];
	}
}
