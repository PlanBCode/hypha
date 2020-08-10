<?php
/*
	Class: settingspage
	handles hypha project settings

	See Also:
	<Page>
*/
	class settingspage extends HyphaSystemPage {
		function __construct(RequestContext $O_O) {
			parent::__construct($O_O);
			registerCommandCallback('settingsInvite', Array($this, 'invite'));
			registerCommandCallback('settingsRemind', Array($this, 'remindNewUser'));
			registerCommandCallback('settingsRegister', Array($this, 'register'));
			registerCommandCallback('settingsQuit', Array($this, 'quit'));
			registerCommandCallback('settingsAdmin', Array($this, 'makeAdmin'));
			registerCommandCallback('settingsUnadmin', Array($this, 'unmakeAdmin'));
			registerCommandCallback('settingsRemove', Array($this, 'removeUser'));
			registerCommandCallback('settingsReincarnate', Array($this, 'reincarnateUser'));
			registerCommandCallback('settingsSaveAccount', Array($this, 'saveAccount'));
			registerCommandCallback('settingsSaveHyphaSettings', Array($this, 'saveHyphaSettings'));
			registerCommandCallback('settingsSaveMarkup', Array($this, 'saveMarkup'));
			registerCommandCallback('settingsSaveTheme', Array($this, 'saveTheme'));
			registerCommandCallback('settingsCopyTheme', Array($this, 'copyTheme'));
			registerCommandCallback('settingsCopyThemeAndActivate', Array($this, 'copyThemeAndActivate'));
			registerCommandCallback('settingsPreviewTheme', Array($this, 'previewTheme'));
			registerCommandCallback('settingsCancelPreviewTheme', Array($this, 'cancelPreviewTheme'));
			registerCommandCallback('settingApplyPreviewTheme', Array($this, 'applyPreviewTheme'));
			registerCommandCallback('settingsSaveStyles', Array($this, 'saveStyles'));
			registerCommandCallback('settingsSaveSiteElements', Array($this, 'saveSiteElements'));
			registerCommandCallback('settingsSaveMenu', Array($this, 'saveMenu'));
		}

		function process(HyphaRequest $request) {
			if (!isUser() && $this->getArg(0) != 'register')
				return ['redirect', $request->getRootUrl()];

			switch ($this->getArg(0)) {
				case 'user': $this->editAccount($this->getArg(1)); break;
				case 'invite': $this->editInvitation(); break;
				case 'quit': $this->editQuitMessage(); break;
				case 'register': $this->editRegistration($this->getArg(1)); break;
				case 'hypha': $this->editHyphaSettings(); break;
				case 'markup': $this->editMarkup(); break;
				case 'theme': $this->editTheme(); break;
				case 'styles': $this->editStyles(); break;
				case 'elements': $this->editSiteElements(); break;
				case 'menu': $this->editMenu(); break;
				default: $this->showSettings();
			}
		}

		function editAccount($username) {
			global $hyphaUser;
			$uiLangList = Language::getInterfaceLanguageList();
			$isoLangList = Language::getIsoList();
			if (isAdmin() || $hyphaUser->getAttribute('username') == $username) {
				$account = hypha_getUserByName($username);
				$this->html->writeToElement('pagename', __('change-account').' `'.$username.'`');
				$this->html->writeToElement('pageCommands', makeButton(__('cancel'), makeAction('settings', '', '')));
				$this->html->writeToElement('pageCommands', makeButton(__('save'), makeAction('settings', 'settingsSaveAccount', $username)));
				if ($account->getAttribute('rights')=='admin') $this->html->writeToElement('pageCommands', makeButton(__('unadmin'), makeAction('settings', 'settingsUnadmin', $username)));
				ob_start();
?>
<table class="section" width="100%">
	<tr>
		<th><?=__('username')?>:</th>
		<td><input name="settingsUsername" id="settingsUsername" type="text" size="20" value="<?=$account->getAttribute('username')?>" /></td>
		<th><?=__('fullname')?>:</th>
		<td><input name="settingsFullname" id="settingsFullname" type="text" size="20" value="<?=$account->getAttribute('fullname')?>" /></td>
	</tr>
	<tr>
		<th><?=__('password')?>:</th>
		<td><input name="settingsPassword1" id="settingsPassword1" type="password" size="20" /></td>
		<th><?=__('confirm-password')?>:</th>
		<td><input name="settingsPassword2" id="settingsPassword2" type="password" size="20" /></td>
	</tr>
	<tr>
		<th><?=__('email')?>:</th>
		<td><input name="settingsEmail" id="settingsEmail" type="text" size="20" value="<?=$account->getAttribute('email')?>" /></td>
		<th><?=__('interface-language')?>:</th>
		<td><select name="settingsUiLang" id="settingsUiLang"><?php foreach($uiLangList as $lang) echo '<option value="'.$lang.'"'.($account->getAttribute('language')==$lang?' selected':'').'>'.$isoLangList[$lang].'</option>'; ?></select></td>
	</tr>
</table>
<?php
				$this->html->writeToElement('main', ob_get_clean());
			}
		}

		function editInvitation() {
			$this->html->writeToElement('pagename', __('invite-to-project'));
			$this->html->writeToElement('pageCommands', makeButton(__('cancel'), makeAction('settings', '', '')));
			$this->html->writeToElement('pageCommands', makeButton(__('send'), makeAction('settings', 'settingsInvite', '')));
			ob_start();
?>
<table class="section">
	<tr>
		<th><?=__('email')?>:</th>
		<td><input name="inviteEmail" type="text" size="48" /></td>
	</tr>
	<tr>
		<th><?=__('message')?>:</th>
		<td><textarea name="inviteWelcome" cols="72" rows="4"><?=__('invite-message')?> `<?=hypha_getTitle()?>`</textarea></td>
	</tr>
</table>
<?php
			$this->html->writeToElement('main', ob_get_clean());
		}

		function invite() {
			global $hyphaUrl;
			global $hyphaUser;
			global $hyphaXml;
			if (isUser()) {
				foreach(explode(',', $_POST['inviteEmail']) as $email) {
					$hyphaXml->lockAndReload();
					hypha_addUser('', '', '', $email, 'en', 'invitee');
					$newUser = hypha_getUserByEmail($email);
					$key = uniqid(rand(), true);
					hypha_addUserRegistrationKey($newUser, $key);
					hypha_addUser('', '', '', $email, 'en', 'invitee');
					$hyphaXml->saveAndUnlock();
					writeToDigest($hyphaUser->getAttribute('fullname').' '.__('invited').' '.$email, 'settings');
					$mailBody = '<a href="mailto:'.$newUser->getAttribute('email').'">'.$hyphaUser->getAttribute('username').'</a>'.__('invites-to-join').'\''.hypha_getTitle().'\'. '.__('follow-link-to-register').'<br /><a href="'.$hyphaUrl.'settings/register/'.$key.'">'.$hyphaUrl.'settings/register/'.$key.'</a><hr/>'.$_POST['inviteWelcome'];
					$result = sendMail($email, __('invitation').'\''.hypha_getTitle().'\'', nl2br($mailBody));
					notify('success', $result ? $result : $email.__('was-invited'));
				}
			}
			return 'reload';
		}

		function editQuitMessage() {
			$this->html->writeToElement('pagename', __('quit'));
			$this->html->writeToElement('pageCommands', makeButton(__('cancel'), makeAction('settings', '', '')));
			$this->html->writeToElement('pageCommands', makeButton(__('send'), 'if(confirm(\''.__('sure-to-quit').'\'))'.makeAction('settings', 'settingsQuit', '')));
			ob_start();
?>
<table class="section">
	<tr>
		<td colspan="2"><?=nl2br(__('quit-explain-message'));?></td>
	</tr>
	<tr>
		<th><?=__('message');?>:</th>
		<td><textarea name="quitGoodbye" cols="36" rows="4"></textarea></td>
	</tr>
</table>
<?php
			$this->html->writeToElement('main', ob_get_clean());
		}

		function quit() {
			global $hyphaUser, $hyphaXml, $O_O;
			$message = $O_O->getRequest()->getPostValue('quitGoodbye');
			if (isUser()) {
				$hyphaXml->lockAndReload();
				$userId = $hyphaUser->getAttribute('id');
				$user = hypha_getUserById($userId);
				hypha_retireUser($userId, /* by_admin */ false);
				$hyphaXml->saveAndUnlock();

				$subject = __('user-has-left-project', ['name' => $user->getAttribute('fullname'), 'project' => hypha_getTitle()]);
				$body = nl2br(htmlspecialchars($message));
				$error = sendMail(getUserEmailList(), $subject, $body);
				if ($error)
					notify('error', $error);

				// Leave will have worked, even when the email failed
				notify('success', __('bye'));

				return logout();
			}
			return 'reload';
		}

		function editRegistration($key) {
			$uiLangList = Language::getInterfaceLanguageList();
			$isoLangList = Language::getIsoList();
			foreach(hypha_getUserList() as $user) if ($user->hasAttribute('key') && $user->getAttribute('key')==$key) {
				$this->html->writeToElement('pagename', __('register'));
				$this->html->writeToElement('pageCommands', makeButton(__('submit'), makeAction('settings/register', 'settingsRegister', $key)));
				ob_start();
?>
<table class="section">
	<tr>
		<th><?=__('username')?>:</th>
		<td><input name="settingsUsername" id="settingsUsername" type="text" size="20" value="<?=$user->getAttribute('username')?>" /></td>
		<th><?=__('fullname')?>:</th>
		<td><input name="settingsFullname" id="settingsFullname" type="text" size="20" value="<?=$user->getAttribute('fullname')?>" /></td>
	</tr>
	<tr>
		<th><?=__('password')?>:</th>
		<td><input name="settingsPassword1" id="settingsPassword1" type="password" size="20" /></td>
		<th><?=__('confirm-password')?>:</th>
		<td><input name="settingsPassword2" id="settingsPassword2" type="password" size="20" /></td>
	</tr>
	<tr>
		<th><?=__('email')?>:</th>
		<td><input name="settingsEmail" id="settingsEmail" type="text" size="20" value="<?=$user->getAttribute('email')?>" /></td>
		<th><?=__('interface-language')?>:</th>
		<td><select name="settingsUiLang" id="settingsUiLang"><?php foreach($uiLangList as $lang) echo '<option value="'.$lang.'"'.($user->getAttribute('language')==$lang?' selected':'').'>'.$isoLangList[$lang].'</option>'; ?></select></td>
	</tr>
</table>
<?php
				$this->html->writeToElement('main', ob_get_clean());
				return;
			}
			notify('error', __('unknown-registration'));
		}

		function register($key) {
			global $hyphaUrl, $hyphaXml;
			$hyphaXml->lockAndReload();
			foreach(hypha_getUserList() as $user) if ($user->hasAttribute('key') && $user->getAttribute('key')==$key) {
				$otheruser = hypha_getUserByName($_POST['settingsUsername']);
				if ($otheruser && $otheruser != $user) {
					notify('error', __('error-user-exists'));
					$hyphaXml->unlock();
					return 'reload';
				}

				$rights = $user->getAttribute('rights');
				if ($rights == 'invitee')
					$rights = 'user';

				if (!hypha_setUser($user, $_POST['settingsUsername'], $_POST['settingsPassword1'], $_POST['settingsFullname'], $_POST['settingsEmail'], $_POST['settingsUiLang'], $rights)) {
					hypha_removeUserRegistrationKey($user);
					writeToDigest($user->getAttribute('fullname').' '.__('has-joined'), 'settings');
					notify('success', __('registration-successful'));
					$hyphaXml->saveAndUnlock();
					return ['redirect', $hyphaUrl];
				}
			}
			$hyphaXml->unlock();
			notify('error', __('error-registration'));
			return 'reload';
		}

		function typesOptionList($select) {
			$html = '';
			foreach (hypha_getDataTypes() as $type => $name) {
				$html.= '<option value=' . htmlspecialchars($type) . ($type == $select ? ' selected' : '') . '>' . htmlspecialchars($name) . '</option>';
			}
			return $html;
		}

		function editHyphaSettings() {
			if (isAdmin()) {
				$seconds = hypha_getDigestInterval();
				$days = floor($seconds / 86400);
				$seconds-= $days * 86400;
				$hours = floor($seconds / 3600);
				$seconds-= $hours * 3600;
				$minutes = floor($seconds / 60);
				$this->html->writeToElement('pagename', __('change-project-settings'));
				$this->html->writeToElement('pageCommands', makeButton(__('cancel'), makeAction('settings', '', '')));
				$this->html->writeToElement('pageCommands', makeButton(__('save'), makeAction('settings', 'settingsSaveHyphaSettings', '')));
				ob_start();
?>
<table class="section">
	<tr>
		<th><?=__('default-language')?>:</th>
		<td><select name="settingsDefaultLanguage" id="settingsDefaultLanguage"><?=Language::getLanguageOptionList(hypha_getDefaultLanguage(), null)?></select></td>
	</tr>
    <tr>
        <th><?=__('default-interface-language')?>:</th>
        <td><select name="settingsDefaultInterfaceLanguage" id="settingsDefaultInterfaceLanguage"><?=Language::getInterfaceLanguageOptionList(hypha_getDefaultInterfaceLanguage(), null)?></select></td>
    </tr>
	<tr>
		<th><?=__('default-page')?>:</th>
		<td><input name="settingsDefaultPage" id="settingsDefaultPage" type="text" size="20" value="<?=showPagename(hypha_getDefaultPage())?>" onblur="validatePagename(this);" onkeyup="validatePagename(this);"/></td>
	</tr>
	<tr>
		<th><?=__('system-email')?>:</th>
		<td><input name="settingsSystemEmail" id="settingsSystemEmail" type="text" size="40" value="<?=hypha_getEmail()?>" /></td>
	</tr>
	<tr>
		<th><?=__('digest-interval')?>:</th>
		<td><input name="settingsIntervalDays" id="settingsIntervalDays" type="text" size="3" value="<?=$days?>" /><?=__('D')?> <input name="settingsIntervalHours" id="settingsIntervalHours" type="text" size="2" value="<?=$hours?>" /><?=__('H')?> <input name="settingsIntervalMinutes" id="settingsIntervalMinutes" type="text" size="2" value="<?=$minutes?>" /><?=__('m')?></td>
	</tr>
	<tr>
		<th><?=__('default-new-page-type')?>:</th>
		<td><select name="settingsDefaultNewPageType" id="settingsDefaultNewPageType"><?=self::typesOptionList(hypha_getDefaultNewPageType())?></select></td>
	</tr>
</table>
<?php
			$this->html->writeToElement('main', ob_get_clean());
			}
		}

		function saveHyphaSettings() {
			global $hyphaXml;
			if (isAdmin()) {
				$hyphaXml->lockAndReload();
				hypha_setDefaultLanguage($_POST['settingsDefaultLanguage']);
				hypha_setDefaultNewPageType($_POST['settingsDefaultNewPageType']);
				hypha_setDefaultInterfaceLanguage($_POST['settingsDefaultInterfaceLanguage']);
				hypha_setDefaultPage($_POST['settingsDefaultPage']);
				hypha_setEmail($_POST['settingsSystemEmail']);
				$digestInterval = 86400 * $_POST['settingsIntervalDays'] + 3600 * $_POST['settingsIntervalHours'] + 60 * $_POST['settingsIntervalMinutes'];
				hypha_setDigestInterval($digestInterval);
				$hyphaXml->saveAndUnlock();
			}
			return 'reload';
		}

		function editMarkup() {
			if (isAdmin()) {
				$this->html->writeToElement('pageCommands', makeButton(__('cancel'), makeAction('settings', '', '')));
                ob_start();
				if ($this->O_O->getThemeName() === 'default') {
					global $hyphaUrl;
					$this->html->writeToElement('pagename', __('view-html-of-theme', ['theme' => $this->O_O->getThemeName()]));
					$this->html->writeToElement('main', __('cannot-edit-default-theme-explanation', ['link' => $hyphaUrl.'settings/theme']));
?>
<blockquote><pre><code><?=htmlspecialchars(hypha_getHtml());?></code></pre></blockquote>
<?php
				} else {
					$this->html->writeToElement('pagename', __('edit-html-of-theme', ['theme' => $this->O_O->getThemeName()]));
					$this->html->writeToElement('pageCommands', makeButton(__('save'), makeAction('settings', 'settingsSaveMarkup', '')));
?>
<table class="section">
	<tr>
		<td colspan="2"><textarea name="editHtml" id="editHtml" cols="100" rows="24" wrap="off"><?=htmlspecialchars(hypha_getHtml())?></textarea></td>
	</tr>
</table>
<?php
				}
				$this->html->writeToElement('main', ob_get_clean());
			}
		}

		function saveMarkup($argument) {
			global $hyphaUrl;
			if (isAdmin()) {
				hypha_setHtml($_POST['editHtml']);
			}
			return 'reload';
		}

		function editTheme() {
			if (isAdmin()) {
				$this->html->writeToElement('pageCommands', makeButton(__('cancel'), makeAction('settings', '', '')));
				$this->html->writeToElement('pagename', __('select-theme'));
				ob_start();
				echo '<select name="editTheme" id="editTheme">';
				$themes = $this->getThemes();
				$normalTheme = hypha_getNormalTheme();
				foreach ($themes as $theme) {
					echo '<option' . ($normalTheme === $theme ? ' selected' : '') . '>' . htmlspecialchars($theme) . '</option>';
				}
				echo '</select>';
				echo makeButton(__('save'), makeAction('settings/theme', 'settingsSaveTheme', ''));
				echo makeButton(__('preview-theme'), makeAction('settings/theme', 'settingsPreviewTheme', ''));
				$this->html->writeToElement('main', ob_get_clean());

				$this->html->writeToElement('main', '<div id="copy-theme"></div>');
				$this->html->writeToElement('copy-theme', '<h2>'.__('copy-theme').'</h2>');
				ob_start();
				echo '<div class="theme-options">' . "\n";
				foreach ($themes as $theme) {
					$value = htmlspecialchars($theme);
					echo '<div class="theme-option"><input type="radio" id="'.$value.'" name="srcTheme" value="'.$value.'"' . ($this->O_O->getThemeName() === $theme ? ' checked' : '') . '><label for="'.$value.'">'.$theme.'</label></div>' . "\n";
				}
				echo '</div>' . "\n";
				echo '<div class="new-theme-name"><input type="text" name="dstTheme" placeholder="'.__('new-theme-name').'"></div>';
				echo makeButton(__('copy-theme'), makeAction('settings/theme', 'settingsCopyTheme', ''));
				echo makeButton(__('copy-theme-and-activate'), makeAction('settings/theme', 'settingsCopyThemeAndActivate', ''));
				$this->html->writeToElement('copy-theme', ob_get_clean());

				$this->html->writeToElement('main', '<div id="preview-theme"></div>');
			}
		}

		function saveTheme($argument) {
			if (isAdmin()) {
				global $hyphaUrl, $hyphaXml;
				$hyphaXml->lockAndReload();
				hypha_setTheme($_POST['editTheme']);
				$hyphaXml->saveAndUnlock();
			}
			return 'reload';
		}

		function copyTheme($argument) {
			if (isAdmin()) {
				$errors = $this->validateAndCopyTheme();
				foreach ($errors as $error) notify('error', $error);
				if (empty($errors)) {
					notify('success', __('copied-theme-successful'));
				}
			}
			return 'reload';
		}

		function copyThemeAndActivate($argument) {
			if (isAdmin()) {
				$errors = $this->validateAndCopyTheme();
				foreach ($errors as $error) notify('error', $error);
				if (empty($errors)) {
					global $hyphaXml;
					$hyphaXml->lockAndReload();
					hypha_setTheme($_POST['dstTheme']);
					$hyphaXml->saveAndUnlock();
					notify('success', __('copied-theme-successful'));
				}
			}
			return 'reload';
		}

		function previewTheme($argument) {
			if (isAdmin()) {
				// get list of existing themes
				$themes = $this->getThemes();

				$errors = [];
				// validate srcTheme
				if (!isset($_POST['editTheme']) || '' == $_POST['editTheme']) {
					$errors[] = __('source-theme-name-required');
				} elseif (!in_array($_POST['editTheme'], $themes)) {
					$errors[] = __('source-theme-name-not-found');
				}
				foreach ($errors as $error) notify('error', $error);
				if (empty($errors)) {
					$this->O_O->setPreviewThemeName($_POST['editTheme']);
					notify('success', __('preview-theme-set-successful'));
				}
			}
			return 'reload';
		}

		function cancelPreviewTheme($argument) {
			if (isAdmin() && $this->O_O->getPreviewThemeName()) {
				$this->O_O->setPreviewThemeName(null);
			}
			return 'reload';
		}

		function applyPreviewTheme($argument) {
			$preview = $this->O_O->getPreviewThemeName();
			if (isAdmin() && $preview) {
				global $hyphaUrl, $hyphaXml;
				$hyphaXml->lockAndReload();
				hypha_setTheme($preview);
				$hyphaXml->saveAndUnlock();
				$this->cancelPreviewTheme($argument);
			}
			return 'reload';
		}

		function validateAndCopyTheme() {
			// get list of existing themes
			$themes = $this->getThemes();

			$errors = [];
			// validate dstTheme
			if (empty($_POST['dstTheme'])) {
				$errors[] = __('destination-theme-name-required');
			} elseif (strpbrk($_POST['dstTheme'], "\\/?%*:|\"<>") !== false) {
				$errors[] = __('destination-theme-not-valid');
			} elseif (in_array($_POST['dstTheme'], $themes)) {
				$errors[] = __('destination-theme-name-already-taken');
			}

			// validate srcTheme
			if (empty($_POST['srcTheme'])) {
				$errors[] = __('source-theme-name-required');
			} elseif (!in_array($_POST['srcTheme'], $themes)) {
				$errors[] = __('source-theme-name-not-found');
			}

			// return the errors if there are any
			if (empty($errors)) {
				// TODO [LRM]: move this copy function so it can be used throughout the system.
				// create an anonymous function to cursively copy the theme directory
				$dirCopy = function ($src, $dst) use (&$dirCopy) {
					mkdir($dst, 0744);
					foreach (scandir($src) as $file) {
						if (in_array($file, ['.', '..'])) {
							continue;
						}
						if (is_dir($src . '/' . $file)) {
							$dirCopy($src . '/' . $file, $dst . '/' . $file);
						} else {
							copy($src . '/' . $file, $dst . '/' . $file);
						}
					}
				};
				$dirCopy('data/themes/' . $_POST['srcTheme'], 'data/themes/' . $_POST['dstTheme']);
			}

			return $errors;
		}

		function getThemes() {
			$themes = [];
			foreach (scandir('data/themes/') as $theme) {
				if (!in_array($theme, ['.', '..'])) {
					$themes[] = $theme;
				}
			}
			return $themes;
		}

		function editStyles() {
			if (isAdmin()) {
				$this->html->writeToElement('pageCommands', makeButton(__('cancel'), makeAction('settings', '', '')));
				ob_start();
				if ($this->O_O->getThemeName() === 'default') {
					global $hyphaUrl;
					$this->html->writeToElement('pagename', __('view-css-of-theme', ['theme' => $this->O_O->getThemeName()]));
					$this->html->writeToElement('main', __('cannot-edit-default-theme-explanation', ['link' => $hyphaUrl.'settings/theme']));
?>
<blockquote><pre><code><?=hypha_getCss();?></code></pre></blockquote>
<?php
				} else {
					$this->html->writeToElement('pagename', __('edit-css-of-theme', ['theme' => $this->O_O->getThemeName()]));
					$this->html->writeToElement('pageCommands', makeButton(__('save'), makeAction('settings', 'settingsSaveStyles', '')));
?>
<textarea class="section" name="editCss" id="editCss" cols="100%" rows="18" wrap="off"><?=hypha_getCss();?></textarea>
<?php
				}
				$this->html->writeToElement('main', ob_get_clean());
			}
		}

		function saveStyles($argument) {
			$hyphaUrl;
			if (isAdmin()) {
				hypha_setCss($_POST['editCss']);
			}
			return 'reload';
		}

		function editSiteElements() {
			if (isAdmin()) {
				$this->html->writeToElement('pagename', __('site-elements'));
				$this->html->writeToElement('pageCommands', makeButton(__('cancel'), makeAction('settings', '', '')));
				$this->html->writeToElement('pageCommands', makeButton(__('save'), makeAction('settings', 'settingsSaveSiteElements', '')));
				ob_start();
?>
<table class="section">
	<tr>
		<th><?=__('title')?>:</th>
		<td><input name="siteTitle" type="text" size="20" value="<?=hypha_getTitle()?>" /></td>
	</tr>
	<tr>
		<th><?=__('favicon')?></th>
	</tr>
	<tr>
		<th><?=__('header')?></th>
		<td><editor name="siteHeader"><?=hypha_getHeader();?></editor></td>
	</tr>
	<tr>
		<th><?=__('footer')?></th>
		<td><editor name="siteFooter"><?=hypha_getFooter();?></editor></td>
	</tr>
</table>
<?php
				$this->html->writeToElement('main', ob_get_clean());
			}
		}
		function saveSiteElements($argument) {
			global $hyphaXml;
			if (isAdmin()) {
				$hyphaXml->lockAndReload();
				hypha_setTitle($_POST['siteTitle']);
				hypha_setHeader(wikify_html($_POST['siteHeader']));
				hypha_setFooter(wikify_html($_POST['siteFooter']));
				$hyphaXml->saveAndUnlock();
			}
			return 'reload';
		}

		function editMenu() {
			$this->html->writeToElement('pagename', __('edit-menu'));
			$this->html->writeToElement('pageCommands', makeButton(__('cancel'), makeAction('settings', '', '')));
			$this->html->writeToElement('pageCommands', makeButton(__('save'), makeAction('settings', 'settingsSaveMenu', '')));
			ob_start();
?>
<table class="section">
	<tr>
		<td colspan="2"><editor name="siteMenu"><?=hypha_getMenu();?></editor></td>
	</tr>
</table>
<?php
			$this->html->writeToElement('main', ob_get_clean());
		}

		function saveMenu($argument) {
			global $hyphaXml;
			if (isAdmin()) {
				$html = wikify_html($_POST['siteMenu']);
				$hyphaXml->lockAndReload();
				hypha_setMenu($html);
				$hyphaXml->saveAndUnlock();
			}
			return 'reload';
		}

		function showSettings() {
			global $hyphaUser;
			$seconds = hypha_getDigestInterval();
			$days = floor($seconds / 86400);
			$seconds-= $days * 86400;
			$hours = floor($seconds / 3600);
			$seconds-= $hours * 3600;
			$minutes = floor($seconds / 60);
			$seconds-= $minutes * 60;
			if(isAdmin()) {
				$this->html->writeToElement('pagename', __('settings'));
				$this->html->writeToElement('pageCommands', makeButton(__('system-tools'), makeAction('hypha.php?maintenance', '', '')));
				$this->html->writeToElement('pageCommands', makeButton(__('hypha-settings'), makeAction('settings/hypha', '', '')));
				$this->html->writeToElement('pageCommands', makeButton(__('markup'), makeAction('settings/markup', '', '')));
				$this->html->writeToElement('pageCommands', makeButton(__('theme'), makeAction('settings/theme', '', '')));
				$this->html->writeToElement('pageCommands', makeButton(__('styles'), makeAction('settings/styles', '', '')));
				$this->html->writeToElement('pageCommands', makeButton(__('site-elements'), makeAction('settings/elements', '', '')));
				$this->html->writeToElement('pageCommands', makeButton(__('menu'), makeAction('settings/menu', '', '')));
			}
			ob_start();
?>
<h3><?=__('personal-settings')?> `<?=$hyphaUser->getAttribute('username')?>`</h3>
<table class="section personal-settings">
	<tr>
		<th><?=__('username')?>:</th>
		<td><?=$hyphaUser->getAttribute('username')?></td>
		<th><?=__('fullname')?>:</th>
		<td><?=$hyphaUser->getAttribute('fullname')?></td>
	</tr>
	<tr>
		<th><?=__('email')?>:</th>
		<td><?=$hyphaUser->getAttribute('email')?></td>
		<th><?=__('interface-language')?>:</th>
		<td><?=$hyphaUser->getAttribute('language')?></td>
	</tr>
	<tr>
		<td colspan="4"><input type="button" class="button edit" value="<?=__('edit')?>" onclick="hypha('settings/user/<?=$hyphaUser->getAttribute('username')?>', '', '');" /><input type="button" class="button quit" value="<?=__('quit')?>" onclick="hypha('settings/quit', '', '');" /></td>
	</tr>
</table>
<h3><?=__('member-list')?></h3>
<table class="section user-list">
	<tr>
		<th></th>
		<th><?=__('username')?></th>
		<th><?=__('fullname')?></th>
		<th><?=__('email')?></th>
		<td><input type="button" class="button invite" value="<?=__('invite')?>" onclick="hypha('settings/invite', '', '');" /></td>
	</tr>
<?php
			$numAdmins = 0;
			foreach(hypha_getUserList() as $user) {
				$prio = '0';
				if ($user->getAttribute('rights') == 'invitee') {
					$prio = '1';
				} elseif ($user->getAttribute('rights')=='exmember') {
					$prio = '2';
					if (!isAdmin())
						continue;
				}
				$userList[$prio . $user->getAttribute('username') . $user->getAttribute('email')] = $user;
				if ($user->getAttribute('rights') == 'admin') $numAdmins++;
			}
			ksort($userList);
			foreach($userList as $user) {
				echo '<tr>';
				echo '<td>'.asterisk($user->getAttribute('rights')=='admin').'</td>';
				if ($user->getAttribute('rights') == 'invite') echo '<td colspan="2" style="text-align:center;"><i>'.__('invitation-pending').'</i></td>';
				elseif ($user->getAttribute('rights')!='exmember' || isAdmin()) {
					echo '<td>'.$user->getAttribute('username').'</td>';
					echo '<td>'.$user->getAttribute('fullname').'</td>';
				}
				echo '<td>'.$user->getAttribute('email').'</td>';
				if (isAdmin()) {
					echo '<td>';
					if ($user->getAttribute('rights')!='exmember') {
						if ($user->getAttribute('username'))
							echo makeButton(__('edit'), makeAction('settings/user/'.$user->getAttribute('username'), '', ''), '', 'edit');

						if ($user->getAttribute('rights')=='none') echo makeButton(__('restore'), makeAction('settings', 'settingsRestore', $user->getAttribute('id')), '', 'restore');
						else echo makeButton(__('remove'), makeAction('settings', 'settingsRemove', $user->getAttribute('id')), '', 'remove');

						if ($user->getAttribute('rights')=='invitee') echo makeButton(__('remind'), makeAction('settings', 'settingsRemind', $user->getAttribute('id')), '', 'remind');
						elseif ($user->getAttribute('rights') !== 'none') {
							if ($user->getAttribute('rights') !== 'admin') echo makeButton(__('admin'), makeAction('settings', 'settingsAdmin', $user->getAttribute('id')), '', 'admin');
							elseif ($numAdmins>1) echo makeButton(__('unadmin'), makeAction('settings', 'settingsUnadmin', $user->getAttribute('id')), '', 'unadmin');
						}
					}
					else {
						echo makeButton(__('reincarnate'), makeAction('settings', 'settingsReincarnate', $user->getAttribute('id')), '', 'reincarnate');
					}
					echo '</td>';
				}
				echo '</tr>';
			}
?>
</table>
<?php
			$this->html->writeToElement('main', ob_get_clean());
		}

		function saveAccount($account) {
			global $hyphaUser, $hyphaXml;
			if (isAdmin() || $hyphaUser->getAttribute('username') == $account) {
				$hyphaXml->lockAndReload();
				$error = hypha_setUser(hypha_getUserByName($account), $_POST['settingsUsername'], $_POST['settingsPassword1'], $_POST['settingsFullname'], $_POST['settingsEmail'], $_POST['settingsUiLang'], '');
				$hyphaXml->saveAndUnlock();
				if ($error) notify('error', __('error-save-settings').' '.$error);
				else notify('success', __('user-settings-saved'));
			}
			return 'reload';
		}

		function makeAdmin($userId) {
			global $hyphaUser, $hyphaXml;
			if (isAdmin()) {
				$hyphaXml->lockAndReload();
				$user = hypha_getUserById($userId);
				hypha_setUser($user, '', '', '', '', '', 'admin');
				$hyphaXml->saveAndUnlock();
				writeToDigest($hyphaUser->getAttribute('fullname').__('granted-admin-rights-to').$user->getAttribute('fullname'), 'settings');
			}
			return 'reload';
		}

		function unmakeAdmin($userId) {
			global $hyphaUserList;
			global $hyphaUser;
			global $hyphaXml;
			if (isAdmin()) {
				$hyphaXml->lockAndReload();
				foreach(hypha_getUserList() as $user) if ($user->getAttribute('rights') == 'admin') $admins++;
				if ($admins>1) {
					$user = hypha_getUserById($userId);
					hypha_setUser($user, '', '', '', '', '', 'user');
					$hyphaXml->saveAndUnlock();
					writeToDigest($hyphaUser->getAttribute('fullname').__('took-admin-rights-from').$user->getAttribute('fullname'), 'settings');
				} else {
					$hyphaXml->unlock();
					notify('error', __('error-last-admin'));
				}
			}
			return 'reload';
		}

		function removeUser($userId) {
			global $hyphaUser, $hyphaXml;
			if (isAdmin()) { // only admin can remove users
				$hyphaXml->lockAndReload();
				hypha_retireUser($userId, /* by_admin */ true);
				$hyphaXml->saveAndUnlock();
			}
			return 'reload';
		}

		function reincarnateUser($userId) {
			global $hyphaUser, $hyphaXml;
			if (isAdmin()) { // only admin can reincarnate users
				$hyphaXml->lockAndReload();
				$user = hypha_getUserById($userId);
				$error = hypha_setUser($user, '', '', '', '', '', 'user');
				$hyphaXml->saveAndUnlock();
				if ($error) {
					notify($error);
				} else {
					$msg = __('admin-reincarnated-user', [
						'admin' => $hyphaUser->getAttribute('fullname'),
						'username' => $user->getAttribute('username'),
						'fullname' => $user->getAttribute('fullname'),
					]);

					writeToDigest($msg, 'settings');
				}
			}
			return 'reload';
		}

		function remindNewUser($userId) {
			global $hyphaUrl;
			if (isAdmin()) {
				$user = hypha_getUserById($userId);
				$email = $user->getAttribute('email');
				$url = $hyphaUrl.'settings/register/'.$user->getAttribute('key');
				$mailBody = __('remind-to-join').'\''.hypha_getTitle().'\'. '.__('follow-link-to-register').'<br /><a href="'.$url.'">'.$url.'</a>';
				$result = sendMail($email, __('invitation').'\''.hypha_getTitle().'\'', nl2br($mailBody));
				notify('success', $result ? $result : $email.__('was-invited'));
			}
			return 'reload';
		}
	}
