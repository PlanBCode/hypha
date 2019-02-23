<?php
/*
	Class: settingspage
	handles hypha project settings

	See Also:
	<Page>
*/
	class settingspage extends Page {
		function __construct($args) {
			parent::__construct('', $args);
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
			registerCommandCallback('settingsSaveStyles', Array($this, 'saveStyles'));
			registerCommandCallback('settingsSaveSiteElements', Array($this, 'saveSiteElements'));
			registerCommandCallback('settingsSaveMenu', Array($this, 'saveMenu'));
		}

		function build() {
//			global $isoLangList, $html;
//			if ($view=='register') $html->pagename =  'registration';
//			elseif ($this->hypha->login) $html->pagename = 'settings';
//			else return notify('error', __('login-to-view'));
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
			global $uiLangList;
			global $isoLangList;
			global $hyphaUser;
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
		<th><?=__('message')?>:</th>
		<td><textarea name="quitGoodbye" cols="36" rows="4"><?=__('goodbye-message')?></textarea></td>
	</tr>
</table>
<?php
			$this->html->writeToElement('main', ob_get_clean());
		}

		function quit($message) {
			global $hyphaUser;
			if (isUser()) {
				notify('error', sendMail(getUserEmailList(), $hyphaUser->getAttribute('fullname').__('has-left-project').'`'.hypha_getTitle().'`', nl2br($message)) );
				if (dropUser($hyphaUser->getAttribute('id'))) {
					logout();
					notify('success', __('bye'));
				}
			}
			return 'reload';
		}

		function editRegistration($key) {
			global $uiLangList;
			global $isoLangList;
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
		<td><select name="settingsDefaultLanguage" id="settingsDefaultLanguage"><?=languageOptionList(hypha_getDefaultLanguage(), null)?></select></td>
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
				$this->html->writeToElement('pagename', __('edit-html'));
				$this->html->writeToElement('pageCommands', makeButton(__('cancel'), makeAction('settings', '', '')));
				$this->html->writeToElement('pageCommands', makeButton(__('save'), makeAction('settings', 'settingsSaveMarkup', '')));
				ob_start();
?>
<table class="section">
	<tr>
		<td colspan="2"><textarea name="editHtml" id="editHtml" cols="100" rows="24" wrap="off"><?=htmlspecialchars(hypha_getHtml())?></textarea></td>
	</tr>
</table>
<?php
				$this->html->writeToElement('main', ob_get_clean());
			}
		}

		function saveMarkup($argument) {
			global $hyphaUrl, $hyphaQuery;
			if (isAdmin()) {
				hypha_setHtml($_POST['editHtml']);
			}
			return 'reload';
		}

		function editTheme() {
			if (isAdmin()) {
				ob_start();
				echo '<select name="editTheme" id="editTheme">';
				foreach (scandir('data/themes/') as $theme) {
					if ('.' !== $theme[0]) {
						echo '<option' . (Hypha::$data->theme === $theme ? ' selected' : '') . '>' . htmlspecialchars($theme) . '</option>';
					}
				}
				echo '</select>';
				$this->html->writeToElement('main', ob_get_clean());
				$this->html->writeToElement('pagename', __('select-theme'));
				$this->html->writeToElement('pageCommands', makeButton(__('cancel'), makeAction('settings', '', '')));
				$this->html->writeToElement('pageCommands', makeButton(__('save'), makeAction('settings', 'settingsSaveTheme', '')));
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

		function editStyles() {
			if (isAdmin()) {
				$this->html->writeToElement('pagename', __('edit-css-of-theme') . ' "' . Hypha::$data->theme . '"');
				$this->html->writeToElement('pageCommands', makeButton(__('cancel'), makeAction('settings', '', '')));
				$this->html->writeToElement('pageCommands', makeButton(__('save'), makeAction('settings', 'settingsSaveStyles', '')));
				ob_start();
?>
<textarea class="section" name="editCss" id="editCss" cols="100%" rows="18" wrap="off"><?=Hypha::$data->css->read()?></textarea>
<?php
				$this->html->writeToElement('main', ob_get_clean());
			}
		}

		function saveStyles($argument) {
			$hyphaUrl;
			$hyphaQuery;
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
				$user = hypha_getUserById($userId);
				$error = hypha_setUser($user, '', '', '', '', '', 'exmember');
				$hyphaXml->saveAndUnlock();
				if ($error) notify($error);
				else if ($user->getAttribute('rights')!='invitee') writeToDigest($hyphaUser->getAttribute('fullname').' '.__('removed-from-user-list').' '.$userId, 'settings');
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
				if ($error) notify($error);
				else writeToDigest($hyphaUser->getAttribute('fullname').' '.__('reincarnated-user').' '.$userId, 'settings');
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
