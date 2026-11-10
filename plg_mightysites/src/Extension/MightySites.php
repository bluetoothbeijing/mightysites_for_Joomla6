<?php
/**
 * @package    MightySites (System plugin)
 * @copyright  Copyright (C) 2009-2017 AlterBrains.com. All rights reserved.
 * @license    GNU/GPL, see LICENSE.php
 */

namespace AlterBrains\Plugin\System\MightySites\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

/**
 * MightySites system plugin for Joomla 5/6.
 */
class MightySites extends CMSPlugin implements SubscriberInterface
{
	/**
	 * Hideplugins hack done?
	 */
	private static $hideDone = false;

	/**
	 * Current application client.
	 */
	private $client = 'site';

	public function __construct(DispatcherInterface $dispatcher, array $config = array())
	{
		parent::__construct($dispatcher, $config);

		try
		{
			$app = Factory::getApplication();
			$this->client = $app->isClient('administrator') ? 'admin' : 'site';
		}
		catch (\Throwable $e)
		{
			$this->client = 'cli';
		}

		if ($this->client === 'site')
		{
			$this->bootFrontend();
		}

		// Disable hidden plugins BEFORE they are instantiated (we are ordered first).
		$this->bootHidePlugins();
	}

	/**
	 * Returns event subscriptions.
	 */
	public static function getSubscribedEvents(): array
	{
		return array(
			'onAfterRoute'          => 'onAfterRoute',
			'onUserLogin'           => 'onUserLogin',
			'onUserLogout'          => 'onUserLogout',
			'onBeforeCompileHead'   => 'onBeforeCompileHead',
			'onAfterDispatch'       => 'onAfterDispatch',
			'onAfterCleanModuleList' => 'onAfterCleanModuleList',
			// Global configuration save (com_config, Joomla 5/6)
			'onApplicationAfterSave'  => 'onApplicationAfterSave',
			'onApplicationBeforeSave' => 'onApplicationBeforeSave',
		);
	}

	/**
	 * Frontend boot: language overrides executed as early as possible.
	 */
	protected function bootFrontend()
	{
		$config = Factory::getConfig();

		// Implicitely setup language.
		$lang_code = $config->get('mighty_language');

		if ($lang_code)
		{
			if (class_exists('PlgSystemLanguageFilter'))
			{
				$lparams = \Joomla\CMS\Component\ComponentHelper::getParams('com_languages');

				if ($lparams->get('site') !== $lang_code)
				{
					$lparams->set('site', $lang_code);
				}
			}
		}
	}

	/**
	 * Disable hidden plugins: they will never be instantiated.
	 */
	protected function bootHidePlugins()
	{
		if (self::$hideDone || $this->client === 'cli')
		{
			return;
		}

		self::$hideDone = true;

		$config = Factory::getConfig();

		$mighty_hideplugins = (array) $config->get('mighty_hideplugins');

		if (!$mighty_hideplugins)
		{
			return;
		}

		try
		{
			// Ensure the plugin list is loaded.
			$load = new \ReflectionMethod(PluginHelper::class, 'load');
			$load->setAccessible(true);
			$load->invoke(null);

			$prop = new \ReflectionProperty(PluginHelper::class, 'plugins');
			$prop->setAccessible(true);
			$plugins = $prop->getValue();

			foreach ($plugins as $plugin)
			{
				if (in_array($plugin->type . ':' . $plugin->name, $mighty_hideplugins, true))
				{
					// Disable it - it will be skipped on import.
					$plugin->type = '';
				}
			}

			$prop->setValue($plugins);
		}
		catch (\Throwable $e)
		{
			// Reflection not possible - ignore.
		}
	}

	/**
	 * Main route handler.
	 */
	public function onAfterRoute()
	{
		$app    = Factory::getApplication();
		$config = Factory::getConfig();

		if ($app->isClient('site'))
		{
			$this->frontendRoute($app, $config);
		}
		else
		{
			$this->backendRoute($app, $config);
		}
	}

	/**
	 * Frontend onAfterRoute.
	 */
	protected function frontendRoute($app, $config)
	{
		// Implicitely setup language, cookie & raw override.
		$lang_code = $config->get('mighty_language');

		if ($lang_code)
		{
			// Always use this one.
			$cookie_expire = time() + 365 * 86400;
			$cookie_domain = $app->get('cookie_domain');
			$cookie_path   = $app->get('cookie_path', '/');
			$cookie_secure = $app->isSSLConnection();

			$app->input->cookie->set(ApplicationHelper::getHash('language'), $lang_code, $cookie_expire, $cookie_path, $cookie_domain, $cookie_secure);

			if (!class_exists('PlgSystemLanguageFilter'))
			{
				unset($_COOKIE[ApplicationHelper::getHash('language')]);
			}

			if ($lang_code !== Factory::getLanguage()->getTag() && empty($_COOKIE[ApplicationHelper::getHash('language')]) && empty($_REQUEST['language']))
			{
				$config->set('language', $lang_code);

				// Raw override
				$lang = Language::getInstance($lang_code, $config->get('debug_lang'));

				$app->loadLanguage($lang);
				Factory::$language = $app->getLanguage();
			}
		}

		// Implicitely setup home menu item
		if ($config->get('mighty_home'))
		{
			$menu = $app->getMenu();
			$lang = Factory::getLanguage();

			if ($menu->getDefault())
			{
				$menu->getDefault()->home = 0;
			}

			if ($menu->getDefault($lang->getTag()))
			{
				$menu->getDefault($lang->getTag())->home = 0;
			}

			$menu->setDefault($config->get('mighty_home'), $lang->getTag());
			$menu->setDefault($config->get('mighty_home'), '*');

			if ($menu->getDefault())
			{
				$menu->getDefault()->home = 1;
			}
		}

		// Remove menu items (not with Falang which overrides them later).
		if (!class_exists('plgSystemFalangdriver'))
		{
			try
			{
				$this->removeMenuItems($app, $config);
			}
			catch (\Exception $e)
			{
				throw $e;
			}
		}

		// Implicitely setup template style.
		if ($config->get('mighty_template') || $config->get('mighty_yootheme_style'))
		{
			$Itemid = $app->input->getInt('Itemid');
			$active = $Itemid ? $app->getMenu()->getItem($Itemid) : $app->getMenu()->getActive();

			if (!$active || !$active->template_style_id || $config->get('mighty_force_template'))
			{
				if ($mighty_template = $config->get('mighty_template'))
				{
					// Assign the template style object directly.
					try
					{
						$db    = Factory::getDbo();
						$query = $db->getQuery(true)
							->select('*')
							->from($db->quoteName('#__template_styles'))
							->where('id = ' . (int) $mighty_template);
						$db->setQuery($query);
						$style = $db->loadObject();

						if ($style)
						{
							$style->params = new Registry($style->params);
							$this->setAppTemplate($app, $style);
						}
					}
					catch (\Throwable $e)
					{
					}
				}

				if ($mighty_yootheme_style = $config->get('mighty_yootheme_style'))
				{
					list($yootheme_state_var, $yootheme_template, $yootheme_style) = explode('.', $mighty_yootheme_style);

					$app->input->set('template', $yootheme_template);
					$app->setUserState($yootheme_state_var, $yootheme_style);

					// Reset template initialized before our code.
					$this->setAppTemplate($app, null);
				}
			}
		}

		// Guard content categories.
		$hidecontentcats = $config->get('mighty_hidecontentcats');
		$onlycontentcats = $config->get('mighty_onlycontentcats');

		if (!empty($hidecontentcats) || !empty($onlycontentcats))
		{
			if ($app->input->get('option') === 'com_content')
			{
				switch ($app->input->get('view'))
				{
					case 'categories':
					case 'category':
						if ($catid = $app->input->getInt('id'))
						{
							if (($hidecontentcats && in_array($catid, (array) $hidecontentcats)) || ($onlycontentcats && !in_array($catid, (array) $onlycontentcats)))
							{
								throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_COMPONENT_NOT_FOUND'), 404);
							}
						}
						break;

					case 'article':
						// Closure listeners receive the Event object (not unpacked arguments).
						$app->registerEvent('onContentPrepare', function ($event) use ($hidecontentcats, $onlycontentcats)
						{
							if (!is_object($event) || !method_exists($event, 'getArgument'))
							{
								return;
							}

							$context = $event->getArgument('context', '');
							$item    = $event->getArgument('subject');

							if ($context === 'com_content.article' && is_object($item) && !empty($item->catid))
							{
								if (($hidecontentcats && in_array($item->catid, (array) $hidecontentcats)) || ($onlycontentcats && !in_array($item->catid, (array) $onlycontentcats)))
								{
									throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_COMPONENT_NOT_FOUND'), 404);
								}
							}
						});
						break;
				}
			}
		}

		// Receive single login from other site.
		$this->receiveSingleLogin($app, $config);

		// Receive single logout from other site.
		if ($app->input->getInt('mighty_logout') === 1 && !Factory::getUser()->guest)
		{
			$app->getSession()->destroy();
			$app->redirect(Uri::root(true) . '/');
		}
	}

	/**
	 * Backend onAfterRoute.
	 */
	protected function backendRoute($app, $config)
	{
		// Check config file, no own settings means possible override.
		if ($config->get('mighty_enable') === null)
		{
			require_once JPATH_ADMINISTRATOR . '/components/com_mightysites/src/Helper/MightysitesHelper.php';

			try
			{
				\AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper::patchConfiguration();
			}
			catch (\Throwable $e)
			{
			}
		}

		// Check logins (token based cross-site admin login).
		if (isset($_REQUEST['mighty_token']) && strlen($_REQUEST['mighty_token']))
		{
			$token  = $app->input->getString('mighty_token');
			$folder = $app->get('tmp_path');

			$files = \Joomla\Filesystem\Folder::files($folder, '\.mighty$', false, false);

			if (sizeof($files))
			{
				$data = false;

				foreach ($files as $file)
				{
					if ($file === md5($token . $app->get('secret')) . '.mighty')
					{
						$data = @file_get_contents($folder . '/' . $file);
					}

					\Joomla\Filesystem\File::delete($folder . '/' . $file);
				}

				if ($data)
				{
					$data = @unserialize($data);

					if ($data && !Factory::getUser()->id)
					{
						// Clear any stale queue messages (e.g. "Unable to load user").
						$app->getSession()->set('application.queue', null);

						$username = '';

						if (!empty($data['user_id']))
						{
							$user = new User();
							$user->load((int) $data['user_id']);

							if ($user->id)
							{
								$username = $user->username;
							}
						}

						if (!$username && !empty($data['username']))
						{
							$username = $data['username'];
						}

						if ($username)
						{
							$this->performTrustedLogin($username);
						}
					}

					if ($data)
					{
						$return = $data['return'] ?? 'index.php';

						if (strpos($return, 'index.php') === 0)
						{
							$return = Uri::base(true) . '/' . ltrim($return, '/');
						}

						$app->redirect($return);
					}
				}
			}
		}

		// Media Manager overrides.
		if ($app->input->get('option') === 'com_media')
		{
			if ($mighty_file_path = $config->get('mighty_file_path'))
			{
				\Joomla\CMS\Component\ComponentHelper::getParams('com_media')->set('file_path', $mighty_file_path);
			}

			if ($mighty_image_path = $config->get('mighty_image_path'))
			{
				\Joomla\CMS\Component\ComponentHelper::getParams('com_media')->set('image_path', $mighty_image_path);
			}
		}

		// Users Manager overrides.
		if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && in_array($app->input->get('option'), array('com_users', 'com_comprofiler', 'com_community', 'com_easysocial', 'com_k2', 'com_easyprofile')))
		{
			if ($mighty_new_usertype = $config->get('mighty_new_usertype'))
			{
				\Joomla\CMS\Component\ComponentHelper::getParams('com_users')->set('new_usertype', $mighty_new_usertype);
			}
		}
	}

	/**
	 * Start single login propagation.
	 *
	 * Joomla 5/6 dispatches a LoginEvent with named arguments:
	 * 'subject' = authentication response array, 'options' = login options.
	 */
	public function onUserLogin($event)
	{
		$app = Factory::getApplication();

		if (!$app->isClient('site'))
		{
			return;
		}

		$config = Factory::getConfig();

		if ($config->get('mighty_slogin') && $config->get('mighty_sdomains') && !isset($_REQUEST['mighty_login']))
		{
			$subject = is_object($event) && method_exists($event, 'getArgument') ? $event->getArgument('subject', array()) : array();
			$options = is_object($event) && method_exists($event, 'getArgument') ? $event->getArgument('options', array()) : array();

			if (empty($subject['username']))
			{
				return;
			}

			// Since Joomla 5 the authentication response no longer carries a plain-text
			// password, so we propagate a signed username token instead (trusted login).
			$app->getSession()->set('mightylogin', array(
				'username' => $subject['username'],
				'remember' => (int) ($options['remember'] ?? 0),
			));
		}
	}

	/**
	 * Start single logout propagation.
	 *
	 * Joomla 5/6 dispatches a LogoutEvent with named arguments 'subject' and 'options'.
	 */
	public function onUserLogout($event)
	{
		$app = Factory::getApplication();

		if (!$app->isClient('site'))
		{
			return;
		}

		$config = Factory::getConfig();

		if ($config->get('mighty_slogout') && $config->get('mighty_sdomains') && !isset($_REQUEST['mighty_logout']))
		{
			setcookie('mightylogout', 1, time() + 3600, '/');

			// No page cache! (Factory::getCache() may not exist on all Joomla versions.)
			try
			{
				if (method_exists(Factory::class, 'getCache'))
				{
					Factory::getCache()->clean('com_plugins');
				}
			}
			catch (\Throwable $e)
			{
				// Cache cleaning is best-effort only.
			}
		}
	}

	/**
	 * Propagate login/logout to other sites + custom CSS/JS/favicon.
	 */
	public function onBeforeCompileHead()
	{
		$app = Factory::getApplication();

		if (!$app->isClient('site'))
		{
			return;
		}

		$config = Factory::getConfig();
		$document = Factory::getDocument();

		if ($document->getType() === 'html' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET')
		{
			$uri = Uri::getInstance();

			// Login there
			if (isset($_SESSION['mightylogin']))
			{
				$user = $_SESSION['mightylogin'];
				unset($_SESSION['mightylogin']);

				$domains = $config->get('mighty_sdomains');

				foreach ((array) $domains as $domain => $secret)
				{
					$hash = $this->buildSingleToken($user, $secret);

					if (substr($domain, 0, 7) !== 'http://' && substr($domain, 0, 8) !== 'https://')
					{
						$domain = ($uri->getScheme() === 'https' ? 'https://' : 'http://') . $domain;
					}

					$document->addScript($domain . '/index.php?' . md5($_SERVER['REQUEST_TIME']) . '=1&amp;mighty_login=' . $hash, array('defer', 'async'), true);
				}
			}

			// Logout there
			if (isset($_COOKIE['mightylogout']))
			{
				setcookie('mightylogout', '', time() - 86400, '/');

				$domains = $config->get('mighty_sdomains');

				foreach ((array) $domains as $domain => $secret)
				{
					if (substr($domain, 0, 7) !== 'http://' && substr($domain, 0, 8) !== 'https://')
					{
						$domain = ($uri->getScheme() === 'https' ? 'https://' : 'http://') . $domain;
					}

					$document->addScript($domain . '/index.php?' . md5($_SERVER['REQUEST_TIME']) . '=1&amp;mighty_logout=1', array('defer', 'async'), true);
				}
			}

			// Custom CSS files
			if ($config->get('mighty_css'))
			{
				foreach ((array) $config->get('mighty_css') as $css_file)
				{
					if ($css_file)
					{
						$document->addStylesheet($css_file);
					}
				}
			}

			// Custom JavaScript
			if ($config->get('mighty_js'))
			{
				$document->addScriptDeclaration($config->get('mighty_js'));
			}

			// Custom Favicon
			if ($config->get('mighty_favicon'))
			{
				if (property_exists($document, '_links'))
				{
					foreach ($document->_links as $i => $link)
					{
						if (isset($link['relation']) && $link['relation'] === 'shortcut icon')
						{
							unset($document->_links[$i]);
						}
					}
				}

				$document->addFavicon($config->get('mighty_favicon'));
			}
		}
	}

	/**
	 * Restore the MightySites db handler if it was replaced.
	 */
	public function onAfterDispatch()
	{
		$config = Factory::getConfig();

		if ($config->get('mighty_enable') && class_exists('JDatabaseMightysites') && !(Factory::getDbo() instanceof JDatabaseMightysites))
		{
			JDatabaseMightysites::changeHandler();
		}
	}

	/**
	 * Hide modules globally (in addition to the menu/module options).
	 *
	 * Joomla 5/6 dispatches an AfterCleanModuleListEvent carrying the module list
	 * as the 'modules' argument; ModuleHelper reads the argument back after the
	 * event, so modifications must be written back to the event object.
	 */
	public function onAfterCleanModuleList($event)
	{
		$config = Factory::getConfig();

		$mighty_hidemodules = (array) $config->get('mighty_hidemodules');
		$mighty_onlymodules = (array) $config->get('mighty_onlymodules');

		if (empty($mighty_hidemodules) && empty($mighty_onlymodules))
		{
			return;
		}

		// Get the current module list from the event.
		if (is_object($event) && method_exists($event, 'getModules'))
		{
			$modules = $event->getModules();
		}
		elseif (is_object($event) && method_exists($event, 'getArgument'))
		{
			$modules = $event->getArgument('modules', array());
		}
		else
		{
			$modules = is_array($event) ? $event : array();
		}

		if (!is_array($modules))
		{
			return;
		}

		$filtered = array();

		foreach ($modules as $module)
		{
			if (in_array($module->id, $mighty_hidemodules) || ($mighty_onlymodules && !in_array($module->id, $mighty_onlymodules)))
			{
				continue;
			}

			$filtered[] = $module;
		}

		// Normalize keys and write the result back to the event.
		$filtered = array_values($filtered);

		if (is_object($event) && method_exists($event, 'updateModules'))
		{
			$event->updateModules($filtered);
		}
		elseif (is_object($event) && method_exists($event, 'setArgument'))
		{
			$event->setArgument('modules', $filtered);
		}
	}

	/**
	 * Global configuration was saved - re-patch configuration.php.
	 *
	 * Joomla 5/6 dispatches an AfterSaveConfigurationEvent (named argument 'subject').
	 */
	public function onApplicationAfterSave($event = null)
	{
		if (!Factory::getApplication()->isClient('administrator'))
		{
			return;
		}

		try
		{
			require_once JPATH_ADMINISTRATOR . '/components/com_mightysites/src/Helper/MightysitesHelper.php';
			\AlterBrains\Component\MightySites\Administrator\Helper\MightysitesHelper::patchConfiguration();
		}
		catch (\Throwable $e)
		{
		}
	}

	public function onApplicationBeforeSave($event = null)
	{
		// Nothing to do before save, absorbing happens after.
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////////
	// Helpers
	//////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * Build HMAC-signed single login token for a username (trusted login, no password travels).
	 */
	protected function buildSingleToken($user, $secret)
	{
		$payload = base64_encode(json_encode(array(
			'username' => (string) ($user['username'] ?? ''),
			'remember' => (int) ($user['remember'] ?? 0),
			'time'     => time(),
		)));

		$sig = hash_hmac('sha256', $payload, $secret . ($_SERVER['HTTP_USER_AGENT'] ?? ''));

		return rawurlencode($payload . '.' . $sig);
	}

	/**
	 * Receive single login token and perform a trusted login.
	 *
	 * The token is signed with the shared per-domain secret (mighty_sdomains) and is
	 * bound to the user agent, with a short validity window. Since the token can only
	 * be produced by a sibling MightySites site holding the secret, we establish the
	 * session directly instead of running the authentication plugins (Joomla 5/6 no
	 * longer exposes plain-text passwords to plugins).
	 */
	protected function receiveSingleLogin($app, $config)
	{
		$token = $app->input->getString('mighty_login', '');

		if (!$token)
		{
			return;
		}

		$parts = explode('.', rawurldecode($token));

		if (count($parts) !== 2)
		{
			return;
		}

		list($payload, $sig) = $parts;

		// Verify signature with our own secret.
		$our_secret = Factory::getConfig()->get('secret');

		$expected = hash_hmac('sha256', $payload, $our_secret . ($_SERVER['HTTP_USER_AGENT'] ?? ''));

		if (!hash_equals($expected, $sig))
		{
			return;
		}

		$data = json_decode(base64_decode($payload), true);

		if (!$data || empty($data['username']) || (time() - (int) ($data['time'] ?? 0)) > 60)
		{
			return;
		}

		// Only when already enabled for this site.
		if (!$config->get('mighty_slogin') || !Factory::getUser()->guest)
		{
			return;
		}

		$this->performTrustedLogin($data['username']);

		// Clean the request to avoid login loops.
		$uri = Uri::getInstance();

		if ($uri->getVar('mighty_login') !== null)
		{
			$uri->delVar('mighty_login');
			$app->redirect($uri->toString());
		}
	}

	/**
	 * Establish a real user session for a trusted username (no password required).
	 *
	 * @return  boolean  True when the session was established.
	 */
	protected function performTrustedLogin($username)
	{
		try
		{
			$app = Factory::getApplication();
			$db  = Factory::getDbo();

			$query = $db->getQuery(true)
				->select('id')
				->from($db->quoteName('#__users'))
				->where($db->quoteName('username') . ' = ' . $db->quote($username))
				->where($db->quoteName('block') . ' = 0');
			$db->setQuery($query);

			$userId = (int) $db->loadResult();

			if (!$userId)
			{
				return false;
			}

			$user = new User();
			$user->load($userId);

			if (!$user->id)
			{
				return false;
			}

			// Mark the user as logged in.
			$user->set('guest', 0);

			$session = $app->getSession();
			$session->set('user', $user);

			$app->checkSession();

			// Update the user related fields for the Joomla sessions table.
			$query = $db->getQuery(true)
				->update($db->quoteName('#__session'))
				->set($db->quoteName('guest') . ' = 0')
				->set($db->quoteName('username') . ' = ' . $db->quote($user->get('username')))
				->set($db->quoteName('userid') . ' = ' . (int) $user->get('id'))
				->where($db->quoteName('session_id') . ' = ' . $db->quote($session->getId()));
			$db->setQuery($query);
			$db->execute();

			// Hit the user last visit field.
			$user->setLastVisit();

			return true;
		}
		catch (\Throwable $e)
		{
			return false;
		}
	}

	/**
	 * Remove menu items according to settings (reflection into SiteMenu).
	 */
	protected function removeMenuItems($app, $config)
	{
		$hidemenus     = $config->get('mighty_hidemenus');
		$onlymenus     = $config->get('mighty_onlymenus');
		$hidemenuitems = $config->get('mighty_hidemenuitems');
		$onlymenuitems = $config->get('mighty_onlymenuitems');

		if (empty($hidemenus) && empty($onlymenus) && empty($hidemenuitems) && empty($onlymenuitems))
		{
			return;
		}

		$menu   = $app->getMenu();
		$active = $menu->getActive();

		// Access private properties.
		$items = $this->getMenuProperty($menu, '_items');

		if ($items === null)
		{
			return;
		}

		// Hide menu items
		if (!empty($hidemenuitems))
		{
			foreach ((array) $hidemenuitems as $hidemenuitem)
			{
				unset($items[$hidemenuitem]);
			}
		}

		$hidemenus     = $hidemenus ? array_flip((array) $hidemenus) : array();
		$onlymenus     = $onlymenus ? array_flip((array) $onlymenus) : array();
		$onlymenuitems = $onlymenuitems ? array_flip((array) $onlymenuitems) : array();

		foreach ($items as $key => &$item)
		{
			// Hide menus
			if ($hidemenus && isset($hidemenus[$item->menutype]))
			{
				unset($items[$key]);
				continue;
			}

			// Only menus
			if ($onlymenus && !isset($onlymenus[$item->menutype]))
			{
				unset($items[$key]);
				continue;
			}

			// Only menu items. Preserve homes!
			if ($onlymenuitems && !isset($onlymenuitems[$item->id]) && !$item->home)
			{
				unset($items[$key]);
				continue;
			}

			// Hide menu items without parents
			if ($item->parent_id != 1 && !isset($items[$item->parent_id]))
			{
				unset($items[$key]);
			}
		}

		$this->setMenuProperty($menu, '_items', $items);

		// Check default items.
		$_default = $this->getMenuProperty($menu, '_default');

		if (is_array($_default))
		{
			foreach ($_default as $lang_code => $item_id)
			{
				if (!isset($items[$item_id]))
				{
					unset($_default[$lang_code]);
				}
			}

			$this->setMenuProperty($menu, '_default', $_default);
		}

		// Raise error on known removed menu item.
		if ($active)
		{
			if ((!empty($hidemenuitems) && in_array($active->id, (array) $hidemenuitems))
				|| (!empty($hidemenus) && isset($hidemenus[$active->menutype])))
			{
				$this->setMenuProperty($menu, '_active', 0);

				throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_COMPONENT_NOT_FOUND'), 404);
			}
		}
	}

	protected function getMenuProperty($menu, $name)
	{
		try
		{
			$prop = new \ReflectionProperty(get_class($menu), $name);
			$prop->setAccessible(true);

			return $prop->getValue($menu);
		}
		catch (\Throwable $e)
		{
			return null;
		}
	}

	protected function setMenuProperty($menu, $name, $value)
	{
		try
		{
			$prop = new \ReflectionProperty(get_class($menu), $name);
			$prop->setAccessible(true);
			$prop->setValue($menu, $value);
		}
		catch (\Throwable $e)
		{
		}
	}

	/**
	 * Set/reset SiteApplication template property (public in J4+ but we use reflection for safety).
	 */
	protected function setAppTemplate($app, $value)
	{
		try
		{
			$prop = new \ReflectionProperty(get_class($app), 'template');
			$prop->setAccessible(true);
			$prop->setValue($app, $value);
		}
		catch (\Throwable $e)
		{
			$app->template = $value;
		}
	}
}
