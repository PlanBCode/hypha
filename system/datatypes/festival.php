<?php
/*
	Module: festival

	Collects various festival-related features, like signup and
	timetables.
 */

/*
	Class: festivalpage
 */
	class festivalpage extends HyphaDatatypePage {
		protected $xml;

		const FIELD_NAME_AMOUNT = 'amount';
		const FIELD_NAME_CATEGORY = 'category';
		const FIELD_NAME_DESCRIPTION = 'description';
		const FIELD_NAME_EMAIL = 'email';
		const FIELD_NAME_IMAGE = 'image';
		const FIELD_NAME_IMAGE_UPLOAD = 'image_upload';
		const FIELD_NAME_NAME = 'name';
		const FIELD_NAME_NOTES = 'notes';
		const FIELD_NAME_PHONE = 'phone';
		const FIELD_NAME_TITLE = 'title';
		const FIELD_NAME_WEBSITE = 'website';

		const PATH_CONFIRMATION_NEEDED = 'confirmation-needed';
		const PATH_CONFIRM = 'confirm';
		const PATH_CONTRIBUTE = 'contribute';
		const PATH_CONTRIBUTIONS = 'contributions';
		const PATH_LINEUP = 'lineup';
		const PATH_PARTICIPANTS = 'participants';
		const PATH_PAYMENTHOOK = 'paymenthook';
		const PATH_PAY = 'pay';
		const PATH_SETTINGS = 'settings';
		const PATH_SIGNUP = 'signup';
		const PATH_TIMETABLE = 'timetable';

		const CMD_DELETE = 'delete';
		const CMD_PAY = 'pay';
		const CMD_SAVE = 'save';
		const CMD_SIGNUP = 'signup';

		const CONFIG_TAG = 'config';
		const CONFIG_TAG_FORM = 'form';
		const CONFIG_TAG_DAYS = 'config';
		const CONFIG_TAG_LOCATIONS = 'config';
		const CONFIG_ID_TITLE = 'festival-title';
		const CONFIG_ID_SIGNUP_FORM = 'signup-form';
		const CONFIG_ID_CONTRIBUTION_FORM = 'contribution-form';
		const CONFIG_ID_DAYS = 'days';
		const CONFIG_ID_LOCATIONS = 'locations';
		const CONFIG_ATTR_VALUE = 'value';

		const TAG_PARTICIPANTS_CONTAINER = 'participants';
		const TAG_PARTICIPANT = 'participant';
		const TAG_CONTRIBUTION_CONTAINER = 'contributions';
		const TAG_CONTRIBUTION = 'contribution';

		// Applies to both contribution and participant
		const ATTR_KEY = 'key';

		const ATTR_CONTRIBUTION_CATEGORY = 'category';
		const ATTR_CONTRIBUTION_IMAGE = 'image';
		const ATTR_CONTRIBUTION_KEY = self::ATTR_KEY;
		const ATTR_CONTRIBUTION_NAME = 'name';
		const ATTR_CONTRIBUTION_PARTICIPANT = 'participant';
		const ATTR_CONTRIBUTION_TITLE = 'title';
		const ATTR_CONTRIBUTION_WEBSITE = 'website';
		const TAG_CONTRIBUTION_DESCRIPTION = 'description';
		const TAG_CONTRIBUTION_NOTES = 'notes';
		const TAG_CONTRIBUTION_EVENT = 'event';

		const ATTR_PARTICIPANT_EMAIL_CONFIRMED = 'email-confirmed';
		const ATTR_PARTICIPANT_EMAIL = 'email';
		const ATTR_PARTICIPANT_KEY = self::ATTR_KEY;
		const ATTR_PARTICIPANT_NAME = 'name';
		const ATTR_PARTICIPANT_PAYMENT_AMOUNT = 'payment-amount';
		const ATTR_PARTICIPANT_PAYMENT_DESCRIPTION = 'payment-description';
		const ATTR_PARTICIPANT_PAYMENT_ID = 'payment-id';
		const ATTR_PARTICIPANT_PAYMENT_STATUS = 'payment-status';
		const ATTR_PARTICIPANT_PAYMENT_TIMESTAMP = 'payment-timestamp';
		const ATTR_PARTICIPANT_PHONE = 'phone';

		const ATTR_EVENT_DAY = 'day';
		const ATTR_EVENT_LOCATION = 'location';
		const ATTR_EVENT_BEGIN = 'begin';
		const ATTR_EVENT_END = 'end';

		const ATTR_DAY_DISPLAY = 'display';
		const ATTR_DAY_BEGIN = 'begin';
		const ATTR_DAY_END = 'end';
		const ATTR_LOCATION_DISPLAY = 'display';

		public function __construct($pageListNode, RequestContext $O_O) {
			parent::__construct($pageListNode, $O_O);
			$this->xml = new Xml('festival', Xml::multiLingualOn, Xml::versionsOff);
			$this->xml->loadFromFile('data/pages/'.$pageListNode->getAttribute('id'));
		}

		public static function getDatatypeName() {
			return __('datatype.name.festivalpage');
		}

		public function process(HyphaRequest $request) {
			$this->html->writeToElement('pagename', showPagename($this->pagename) . ' ' . asterisk($this->privateFlag));

			if (isUser() && !in_array($request->getView(), [self::PATH_SETTINGS])) {
				$commands = $this->html->find('#pageCommands');
				$commands->append($this->makeActionButton(__('settings'), self::PATH_SETTINGS));
				$commands->append($this->makeActionButton(__('festival-signup'), self::PATH_SIGNUP));
				$commands->append($this->makeActionButton(__('festival-contribute'), self::PATH_CONTRIBUTE));
				$commands->append($this->makeActionButton(__('festival-participants'), self::PATH_PARTICIPANTS));
				$commands->append($this->makeActionButton(__('festival-contributions'), self::PATH_CONTRIBUTIONS));
				$commands->append($this->makeActionButton(__('festival-lineup'), self::PATH_LINEUP));
				$commands->append($this->makeActionButton(__('festival-timetable'), self::PATH_TIMETABLE));

				if (isAdmin()) {
					$action = 'if(confirm(\'' . __('sure-to-delete') . '\'))' . makeAction($this->language . '/' . $this->pagename, 'delete', '');
					$commands->append(makeButton(__('delete'), $action));
				}
			}

			switch ([$request->getView(), $request->getCommand()]) {
				case [null,                           null]:             return $this->lineupView($request);
				case [null,                           self::CMD_DELETE]: return $this->deleteAction($request);
				case [self::PATH_CONFIRMATION_NEEDED, null]:             return $this->confirmationNeededView($request);
				case [self::PATH_CONFIRM,             null]:             return $this->confirmView($request);
				case [self::PATH_CONTRIBUTE,          null]:             return $this->contributeView($request);
				case [self::PATH_CONTRIBUTE,          self::CMD_SAVE]:   return $this->contributionSaveAction($request);
				case [self::PATH_CONTRIBUTIONS,       null]:             return $this->contributionsView($request);
				case [self::PATH_LINEUP,              null]:             return $this->lineupView($request);
				case [self::PATH_PARTICIPANTS,        null]:             return $this->participantsView($request);
				case [self::PATH_PAYMENTHOOK,         null]:             return $this->paymenthookView($request);
				case [self::PATH_PAY,                 null]:             return $this->payView($request);
				case [self::PATH_PAY,                 self::CMD_PAY]:    return $this->payAction($request);
				case [self::PATH_SETTINGS,            null]:             return $this->settingsView($request);
				case [self::PATH_SETTINGS,            self::CMD_SAVE]:   return $this->settingsSaveAction($request);
				case [self::PATH_SIGNUP,              null]:             return $this->signupView($request);
				case [self::PATH_SIGNUP,              self::CMD_SIGNUP]: return $this->signupAction($request);
				case [self::PATH_TIMETABLE,           null]:             return $this->timetableView($request);
			}

			return '404';
		}

		public function getSortDateTime() {
			// TODO: What to return here?
			return null;
		}

		/**
		 * Retrieve a config value from the XML. This finds a
		 * tag with the given id, and retrieves the given
		 * attribute from it. If no attribute is given, the
		 * "value" attribute is returned.
		 */
		protected function getConfig($id, $attribute = self::CONFIG_ATTR_VALUE) {
			$config = $this->getConfigElement($id);
			if (!$config)
				return '';
			return $config->getAttribute($attribute);
		}

		/**
		 * Retrieve the XML element with the given id and return
		 * it.
		 *
		 * If the element does not exist and a tagname is given,
		 * it is created using the given tagname. Otherwise,
		 * null is returned.
		 */
		protected function getConfigElement($id, $tagname = null) {
			$elem = $this->xml->getElementById($id);
			if (!$elem && $tagname) {
				$elem = $this->xml->createElement($tagname);
				$elem->setId($id);
				$this->xml->documentElement->appendChild($elem);
			}
			return $elem;
		}

		/**
		 * Set a config value in the XML. This finds a
		 * tag with the given id, sets the given attribute from
		 * it. If no attribute is given, the "value" attribute
		 * is set. If the tag does not exist, it is created,
		 * using the given tagname.
		 */
		protected function setConfig($id, $value, $tagname = self::CONFIG_TAG, $attribute = self::CONFIG_ATTR_VALUE) {
			$config = $this->getConfigElement($id, $tagname);
			return $config->setAttribute($attribute, $value);
		}

		/**
		 * Show the admin display with registrations.
		 */
		protected function participantsView(HyphaRequest $request) {
			if (!isUser()) return notify('error', __('login-to-edit'));

			$stats = [];
			$totalcount = 0;

			$table = new HTMLTable();
			$this->html->find('#main')->appendChild($table);
			$table->addHeaderRow()->addCells([__('name'), __('email'), __('phone'), __('price'), __('festival-participant-status')]);
			foreach ($this->xml->documentElement->getOrCreate(self::TAG_PARTICIPANTS_CONTAINER)->children() as $participant) {
				$payamount = $participant->getAttribute(self::ATTR_PARTICIPANT_PAYMENT_AMOUNT);
				if ($payamount)
					$status = $participant->getAttribute(self::ATTR_PARTICIPANT_PAYMENT_STATUS);
				else
					$status = $participant->getAttribute(self::ATTR_PARTICIPANT_EMAIL_CONFIRMED) ? 'confirmed' : 'unconfirmed';

				$row = $table->addRow();
				$row->addCell($participant->getAttribute(self::ATTR_PARTICIPANT_NAME));
				$row->addCell($participant->getAttribute(self::ATTR_PARTICIPANT_EMAIL));
				$row->addCell($participant->getAttribute(self::ATTR_PARTICIPANT_PHONE));
				$row->addCell($payamount ? '€' . $payamount : '-');
				$row->addCell($status);

				$totalcount += 1;
				if (!array_key_exists($status, $stats))
						$stats[$status] = ['paysum' => 0, 'count' => 0];

				$stats[$status]['count'] += 1;
				if ($payamount) {
					$stats[$status]['paysum'] += $payamount;
				}
			}

			$table = new HTMLTable();
			$this->html->find('#main')->appendChild($table);
			$table->addHeaderRow()->addCells(['', __('count'), __('amount')]);
			foreach ($stats as $category => $values) {
				$row = $table->addRow();
				$row->addCell($category);
				$row->addCell($values['count']);
				$row->addCell('€' . $values['paysum']);
			}
			$row = $table->addRow();
			$row->addCell(__('total'));
			$row->addCell($totalcount);
			return null;
		}

		/**
		 * Show the admin display with contributions.
		 */
		protected function contributionsView(HyphaRequest $request) {
			// TODO: Styling
			if (!isUser()) return notify('error', __('login-to-edit'));
			$table = new HTMLTable();
			$this->html->find('#main')->appendChild($table);
			$table->addClass('contributions');
			$table->addHeaderRow()->addCells([__('name'), __('title'), __('category'), __('website')]);
			foreach ($this->xml->documentElement->getOrCreate(self::TAG_CONTRIBUTION_CONTAINER)->children() as $contribution) {
				$row = $table->addRow();
				$row->addCell($contribution->getAttribute(self::ATTR_CONTRIBUTION_NAME));
				$row->addCell($contribution->getAttribute(self::ATTR_CONTRIBUTION_TITLE));
				$row->addCell($contribution->getAttribute(self::ATTR_CONTRIBUTION_CATEGORY));
				$row->addCell($contribution->getAttribute(self::ATTR_CONTRIBUTION_WEBSITE));

				$button = $this->makeActionButton(__('edit'), self::PATH_CONTRIBUTE . '/'.$contribution->getId());
				$row->addCell()->append($button);

				$description = $contribution->getOrCreate(self::TAG_CONTRIBUTION_DESCRIPTION)->text();
				$imgfilename = $contribution->getAttribute(self::ATTR_CONTRIBUTION_IMAGE);
				if ($description || $imgfilename) {
					$cell = $table->addRow()->addCell(__('description') . ': ' . $description);
					$cell->setAttribute('colspan', 5);
					$cell->addClass('description');
					if ($imgfilename) {
						$imgtag = $this->html->createElement('img');
						$cell->insertBefore($imgtag, $cell->firstChild);
						$image = new HyphaImage($contribution->getAttribute(self::ATTR_CONTRIBUTION_IMAGE));
						$imgtag->setAttribute('src', $image->getUrl(50, 50));
					}
				}
				$notes = $contribution->getOrCreate(self::TAG_CONTRIBUTION_NOTES)->text();
				if ($notes) {
					$cell = $table->addRow()->addCell(__('notes') . ': ' . $notes);
					$cell->setAttribute('colspan', 5);
					$cell->addClass('notes');
				}
			}
			return null;
		}

		/**
		 * @return HTMLForm
		 */
		protected function createSettingsForm(array $values=[]) {
			$html = <<<EOF
				<table>
					<tr>
						<td><label for="[[field-name-title]]">[[title]]</label></td>
						<td><input id="[[field-name-title]]" name="[[field-name-title]]"/></td>
					</tr>
				</table>
EOF;
			$vars = [
				'title' => __('festival-field-festival-title'),
				'field-name-title' => self::FIELD_NAME_TITLE,
			];

			$html = hypha_substitute($html, $vars);

			return new HTMLForm($html, $values);
		}

		protected function settingsView(HyphaRequest $request) {
			if (!isUser()) return notify('error', __('login-to-edit'));

			// create form
			$formData = [
				self::FIELD_NAME_TITLE => $this->getConfig(self::CONFIG_ID_TITLE),
			];

			$form = $this->createSettingsForm($formData);
			return $this->settingsViewRender($request, $form);
		}

		function settingsViewRender($request, $form) {
			// Update the form to include the data
			$form->updateDom();

			$this->html->find('#main')->append($form);

			$commands = $this->html->find('#pageCommands');
			$commands->append($this->makeActionButton(__('save'), self::PATH_SETTINGS, self::CMD_SAVE));
			$commands->append($this->makeActionButton(__('cancel'), ''));
			return null;
		}

		protected function settingsSaveAction(HyphaRequest $request) {
			if (!isUser()) return notify('error', __('login-to-edit'));

			// create form
			$form = $this->createSettingsForm($request->getPostData());

			$form->validateRequiredField(self::FIELD_NAME_TITLE);

			// process form if it was posted
			if (!empty($form->errors))
				return $this->settingsViewRender($request, $form);

			$this->xml->lockAndReload();
			$this->setConfig(self::CONFIG_ID_TITLE, $form->dataFor(self::FIELD_NAME_TITLE));
			$this->xml->saveAndUnlock();

			notify('success', ucfirst(__('festival-settings-saved')));
			return ['redirect', $this->constructFullPath($this->pagename)];
		}

		protected function deleteAction(HyphaRequest $request) {
			if (!isAdmin()) return notify('error', __('login-as-admin-to-delete'));

			$this->deletePage();

			notify('success', ucfirst(__('page-successfully-deleted')));
			return ['redirect', $request->getRootUrl()];
		}

		/**
		 * Create a signup form, based on the one configured.
		 */
		private function createSignupForm(array $values = []) {
			$html = $this->getConfigElement(self::CONFIG_ID_SIGNUP_FORM, self::CONFIG_TAG_FORM)->children();

			if ($html->count() == 0) {
				$html = <<<EOF
					<table class="festivalForm">
						<tr>
							<th><label for="[[field-name-name]]">[[name]]</label></th>
							<td><input type="text" name="[[field-name-name]]"></td>
							<td>*</td>
						</tr>
						<tr>
							<th><label for="[[field-name-email]]">[[email]]</label></th>
							<td><input type="text" name="[[field-name-email]]"></td>
							<td>*</td>
						</tr>
						<tr>
							<th><label for="[[field-name-phone]]">[[phone]]</label></th>
							<td><input type="text" name="[[field-name-phone]]"></td>
							<td></td>
						</tr>
						<tr>
							<th><label for="[[field-name-amount]]">[[amount]]</label></th>
							<td><input type="text" name="[[field-name-amount]]"></td>
							<td></td>
						</tr>
					</table>
EOF;
				$vars = [
					'name' => __('festival-field-name'),
					'field-name-name' => self::FIELD_NAME_NAME,
					'email' => __('festival-field-email'),
					'field-name-email' => self::FIELD_NAME_EMAIL,
					'phone' => __('festival-field-phone'),
					'field-name-phone' => self::FIELD_NAME_PHONE,
					'amount' => __('festival-field-amount'),
					'field-name-amount' => self::FIELD_NAME_AMOUNT,
				];

				$html = hypha_substitute($html, $vars);
			}

			return new HTMLForm($html, $values);
		}

		/**
		 * The signup form. Here's where the fun starts.
		 */
		protected function signupView(HyphaRequest $request) {
			$form = $this->createSignupForm();
			return $this->signupViewRender($request, $form);
		}

		protected function signupViewRender(HyphaRequest $request, HTMLForm $form) {
			$form->updateDom();

			$this->html->find('#main')->append($form);
			$this->html->find('#pagename')->text(__('festival-signup-for') . $this->getConfig(self::CONFIG_ID_TITLE));

			$commands = $this->html->find('#pageEndCommands');
			$commands->append($this->makeActionButton(__('signup'), self::PATH_SIGNUP, self::CMD_SIGNUP));

			return null;
		}

		/**
		 * Handle the signup form.  Redirects to either /pay or
		 * /contribute, depending on whether there is something to
		 * pay.
		 */
		protected function signupAction(HyphaRequest $request) {
			// create form
			$form = $this->createSignupForm($request->getPostData());

			$form->validateRequiredField(self::FIELD_NAME_NAME);
			$form->validateRequiredField(self::FIELD_NAME_EMAIL);
			$form->validateEmailField(self::FIELD_NAME_EMAIL);
			$form->validateMoneyField(self::FIELD_NAME_AMOUNT);

			if (!empty($form->errors))
				return $this->signupViewRender($request, $form);

			$this->xml->lockAndReload();

			$participant = $this->xml->createElement(self::TAG_PARTICIPANT);
			$participant->generateId();
			$participant->setAttribute(self::ATTR_PARTICIPANT_NAME, $form->dataFor(self::FIELD_NAME_NAME));
			$participant->setAttribute(self::ATTR_PARTICIPANT_EMAIL, $form->dataFor(self::FIELD_NAME_EMAIL));
			$participant->setAttribute(self::ATTR_PARTICIPANT_PHONE, $form->dataFor(self::FIELD_NAME_PHONE));
			$participant->setAttribute(self::ATTR_PARTICIPANT_KEY, bin2hex(openssl_random_pseudo_bytes(8)));
			$this->xml->documentElement->getOrCreate(self::TAG_PARTICIPANTS_CONTAINER)->append($participant);
			if ((float)$form->dataFor(self::FIELD_NAME_AMOUNT, 0) > 0)
				$this->setupPayment($participant, $form->dataFor(self::FIELD_NAME_AMOUNT, 0));
			$this->xml->saveAndUnlock();

			notify('success', __('festival-successful-signup-for') . $this->getConfig(self::CONFIG_ID_TITLE));
			$contribute_url = $this->constructFullPath($this->pagename . '/' . self::PATH_CONTRIBUTE . '/' . $participant->getId() . '/' . $participant->getAttribute(self::ATTR_PARTICIPANT_KEY));
			$digest = htmlspecialchars($participant->getAttribute(self::ATTR_PARTICIPANT_NAME) . __('festival-signed-up-for') . $this->getConfig(self::CONFIG_ID_TITLE));
			$digest .= ' (<a href="' . htmlspecialchars($contribute_url) . '">Add contribution</a>)';
			writeToDigest($digest, 'festival-registration');

			if ((float)$form->dataFor(self::FIELD_NAME_AMOUNT, 0) > 0) {
				$next_url = $this->constructFullPath($this->pagename . '/' . self::PATH_PAY . '/' . $participant->getId() . '/' . $participant->getAttribute(self::ATTR_PARTICIPANT_KEY));
			} else {
				$next_url = $this->constructFullPath($this->pagename . '/confirmation-needed');

				// Send email
				$confirm_url = $this->constructFullPath($this->pagename . '/' . self::PATH_CONFIRM . '/' . $participant->getId() . '/' . $participant->getAttribute(self::ATTR_PARTICIPANT_KEY));
				$vars = [
					'festival-title' => $this->getConfig(self::CONFIG_ID_TITLE),
					'confirmlink' => $confirm_url,
				];
				$rcpt = $participant->getAttribute(self::ATTR_PARTICIPANT_EMAIL);
				$this->sendMail($rcpt, 'festival-confirm-registration', $vars);
			}
			return ['redirect', $next_url];
		}

		/**
		 * For unpaid registrations, show a message that
		 * confirmation is needed.
		 */
		protected function confirmationNeededView(HyphaRequest $request) {
			$this->html->find('#pagename')->text(__('festival-confirmation-needed'));
			$main = $this->html->find('#main');
			$message = __('festival-complete-by-confirming');
			$main->append($message);
			return null;
		}

		/**
		 * For unpaid registrations, this link needs to be
		 * clicked to confirm the registration.
		 */
		protected function confirmView(HyphaRequest $request) {
			$this->xml->lockAndReload();
			$participant = $this->checkKeyArguments($request, [self::TAG_PARTICIPANT]);
			if (!$participant) {
				$this->xml->unlock();
				return '404';
			}
			$contribute_url = $this->constructFullPath($this->pagename . '/' . self::PATH_CONTRIBUTE . '/' . $participant->getId() . '/' . $participant->getAttribute(self::ATTR_PARTICIPANT_KEY));

			if ($participant->getAttribute(self::ATTR_PARTICIPANT_EMAIL_CONFIRMED)) {
				$this->xml->unlock();
				notify('success', __('festival-email-already-confirmed'));
			} else {
				notify('success', __('festival-email-confirmed-successfully'));

				// Mark e-mail as confirmed
				$participant->setAttribute(self::ATTR_PARTICIPANT_EMAIL_CONFIRMED, '1');
				$this->xml->saveAndUnlock();

				// Note in digest
				$digest = htmlspecialchars($participant->getAttribute(self::ATTR_PARTICIPANT_NAME) . __('festival-confirmed-for') . $this->getConfig(self::CONFIG_ID_TITLE));
				$digest .= ' (<a href="' . $contribute_url . '">Add contribution</a>)';
				writeToDigest($digest, 'festival-confirmation');

				// Send email
				$vars = [
					'festival-title' => $this->getConfig(self::CONFIG_ID_TITLE),
					'contributelink' => $contribute_url,
				];
				$rcpt = $participant->getAttribute(self::ATTR_PARTICIPANT_EMAIL);
				$this->sendMail($rcpt, 'festival-registration-confirmed', $vars);
			}

			// Redirect to contribution page
			return ['redirect', $contribute_url];
		}

		/**
		 * Show the payment screen, which has a message and a
		 * button to start the payment procedure. If the payment
		 * is already complete, this redirects to the
		 * /contribute page.
		 */
		protected function payView(HyphaRequest $request) {
			$this->xml->lockAndReload();
			$participant = $this->checkKeyArguments($request, [self::TAG_PARTICIPANT]);
			if (!$participant) {
				$this->xml->unlock();
				return null;
			}

			// Check the status of the payment
			$this->checkPayment($participant);
			$this->xml->saveAndUnlock();

			if ($participant->getAttribute(self::ATTR_PARTICIPANT_PAYMENT_TIMESTAMP)) {
				$url = $this->constructFullPath($this->pagename . '/' . self::PATH_CONTRIBUTE . '/' . $participant->getId() . '/' . $participant->getAttribute(self::ATTR_PARTICIPANT_KEY));
				notify('success', __('festival-successful-payment'));
				return ['redirect', $url];
			}

			// Render the page
			$this->html->find('#pagename')->text(__('festival-pay-for') . $this->getConfig(self::CONFIG_ID_TITLE));
			$main = $this->html->find('#main');
			$message = __('festival-complete-by-paying');
			$button = $this->makeActionButton(__('pay'), join('/', $request->getArgs()), self::CMD_PAY);
			$main->append($message);
			$main->append($button);
		}

		/**
		 * Handle the 'pay' command from the button on the /pay
		 * page. This redirect to the payment provider, unless
		 * the payment was already completed, then it redirects
		 * to the /contribute page.
		 */
		protected function payAction(HyphaRequest $request) {
			$this->xml->lockAndReload();
			$participant = $this->checkKeyArguments($request, [self::TAG_PARTICIPANT]);
			if(!$participant) {
				$this->xml->unlock();
				return null;
			}

			// Check the status of the payment, and create a
			// new one if it expired or failed.
			$url = $this->checkPayment($participant, true);
			$this->xml->saveAndUnlock();

			// If payment is completed, redirect to
			// contribute page. Otherwise, redirect to
			// payment provider.
			if (!$url)
				$url = $this->constructFullPath($this->pagename . '/' . self::PATH_CONTRIBUTE . '/' . $participant->getId() . '/' . $participant->getAttribute(self::ATTR_PARTICIPANT_KEY));

			return ['redirect', $url];
		}

		protected function paymenthookView(HyphaRequest $request) {
			$this->xml->lockAndReload();
			$participant = $this->checkKeyArguments($request, [self::TAG_PARTICIPANT]);
			if(!$participant) {
				// We need to return 403 to prevent
				// rewrites by the payment provider.
				// TODO: 403 in a more generic place?
				http_response_code(403);
				writeToDigest('Invalid participant id or key in payment hook: ' . $_SERVER['REQUEST_URI'] . '?' . $_SERVER['QUERY_STRING'], 'error');
				exit;
			}

			if($participant->getAttribute(self::ATTR_PARTICIPANT_PAYMENT_ID) != $_REQUEST['id']) {
				// We need to return 403 to prevent
				// rewrites by the payment provider.
				// TODO: 403 in a more generic place?
				http_response_code(403);
				writeToDigest('Invalid payment id in payment hook: ' . $_SERVER['REQUEST_URI'] . '?' . $_SERVER['QUERY_STRING'], 'error');
				exit;
			}

			// Update the payment status and send any mails
			// needed. No need to generate any output, just
			// letting PHP generate a 200 OK status is
			// sufficient.
			$this->checkPayment($participant);
			$this->xml->saveAndUnlock();
			exit;
		}

		private function createContributionForm(array $values = []) {
			$html = $this->getConfigElement(self::CONFIG_ID_CONTRIBUTION_FORM, self::CONFIG_TAG_FORM)->children();

			if ($html->count() == 0) {
				$html = <<<EOF
					<table class="festivalForm">
						<tr>
							<th><label for="[[field-name-name]]">[[name]]</label></th>
							<td><input type="text" name="[[field-name-name]]"/></td>
							<td>*</td>
						</tr>
						<tr>
							<th><label for="[[field-name-title]]">[[title]]</label></th>
							<td><input type="text" name="[[field-name-title]]"/></td>
							<td>*</td>
						</tr>
						<tr>
							<th><label for="[[field-name-description]]">[[description]]</label></th>
							<td><textarea name="[[field-name-description]]"></textarea></td>
							<td/>
						</tr>
							<tr><th><label for="[[field-name-image]]">[[image]]</label></th>
							<td>
								<input type="hidden" name="[[field-name-image]]"/>
								<img data-preview-for="[[field-name-image]]"/><br/>
								<input type="file" name="[[field-name-image-upload]]"/>
							</td>
							<td/>
						</tr>
						<tr>
							<th><label for="[[field-name-website]]">[[website]]</label></th>
							<td><input type="text" name="[[field-name-website]]"/></td>
							<td/>
						</tr>
						<tr>
							<th><label for="[[field-name-category]]">[[category]]</label></th>
							<td><select name="[[field-name-category]]">
								<option disabled="disabled" selected="selected">select</option>
								<option value="lecture">Lecture</option>
								<option value="workshop">Workshop</option>
								<option value="demonstration">Demonstration</option>
								<option value="hackathon">Hackathon</option>
								<option value="other">Other</option>
							</select></td>
							<td>*</td>
						</tr>
						<tr>
							<th><label for="[[field-name-notes]]">[[notes]]</label></th>
							<td><textarea name="[[field-name-notes]]"></textarea></td>
							<td/>
						</tr>
					</table>
EOF;
				$vars = [
					'name' => __('festival-field-contribution-name'),
					'field-name-name' => self::FIELD_NAME_NAME,
					'title' => __('festival-field-contribution-title'),
					'field-name-title' => self::FIELD_NAME_TITLE,
					'description' => __('festival-field-contribution-description'),
					'field-name-description' => self::FIELD_NAME_DESCRIPTION,
					'image' => __('festival-field-contribution-image'),
					'field-name-image' => self::FIELD_NAME_IMAGE,
					'field-name-image-upload' => self::FIELD_NAME_IMAGE_UPLOAD,
					'website' => __('festival-field-contribution-website'),
					'field-name-website' => self::FIELD_NAME_WEBSITE,
					'category' => __('festival-field-contribution-category'),
					'field-name-category' => self::FIELD_NAME_CATEGORY,
					'notes' => __('festival-field-contribution-notes'),
					'field-name-notes' => self::FIELD_NAME_NOTES,

				];

				$html = hypha_substitute($html, $vars);
			}

			return new HTMLForm($html, $values);
		}

		/**
		 * Show the contribution form.
		 */
		protected function contributeView(HyphaRequest $request) {
			$obj = $this->checkKeyArguments($request, [self::TAG_CONTRIBUTION, self::TAG_PARTICIPANT], true);
			if (!$obj)
				return '404';

			# If a contribution id is in the url, we're
			# editing that contribution. If a participant id
			# was passed, we are creating a new contribution.
			$editing = ($obj->tagName == self::TAG_CONTRIBUTION);

			$form = $this->createContributionForm();
			if ($editing) {
				// create form
				$description = $obj->get(self::TAG_CONTRIBUTION_DESCRIPTION);
				$notes = $obj->get(self::TAG_CONTRIBUTION_NOTES);
				$formData = [
					self::FIELD_NAME_NAME => $obj->getAttr(self::ATTR_CONTRIBUTION_NAME),
					self::FIELD_NAME_TITLE => $obj->getAttr(self::ATTR_CONTRIBUTION_TITLE),
					self::FIELD_NAME_CATEGORY => $obj->getAttr(self::ATTR_CONTRIBUTION_CATEGORY),
					self::FIELD_NAME_IMAGE => $obj->getAttr(self::ATTR_CONTRIBUTION_IMAGE),
					self::FIELD_NAME_WEBSITE => $obj->getAttr(self::ATTR_CONTRIBUTION_WEBSITE),
					self::FIELD_NAME_DESCRIPTION => $description ? $description->text() : null,
					self::FIELD_NAME_NOTES => $notes ? $notes->text() : null,
				];

				$form->setData($formData);
			}
			return $this->contributeViewRender($request, $form, $editing);
		}

		protected function contributeViewrender(HyphaRequest $request, HTMLForm $form, $editing) {
			// Update the form to include any data
			$form->updateDom();

			$this->html->find('#pagename')->text(__('festival-contribute-to') . $this->getConfig(self::CONFIG_ID_TITLE));
			$this->html->find('#main')->append($form);

			$commands = $this->html->find('#pageEndCommands');
			$title = $editing ? __('festival-modify') : __('festival-contribute');
			$commands->append($this->makeActionButton($title, join('/',$request->getArgs()), self::CMD_SAVE));
			return null;
		}

		/**
		 * Handle the contribution form.
		 */
		protected function contributionSaveAction(HyphaRequest $request) {
			$this->xml->lockAndReload();

			$obj = $this->checkKeyArguments($request, [self::TAG_CONTRIBUTION, self::TAG_PARTICIPANT], true);
			if (!$obj)
				return null;

			# If a contribution id is in the url, we're
			# editing that contribution. If a participant id
			# was passed, we are creating a new contribution.
			$editing = ($obj->tagName == self::TAG_CONTRIBUTION);

			$form = $this->createContributionForm($request->getPostData());

			$form->validateRequiredField(self::FIELD_NAME_NAME);
			$form->validateRequiredField(self::FIELD_NAME_TITLE);
			$form->validateRequiredField(self::FIELD_NAME_CATEGORY);
			if (array_key_exists('image_upload', $_FILES))
				$form->handleImageUpload(self::FIELD_NAME_IMAGE, $_FILES['image_upload']);

			if (!empty($form->errors)) {
				$this->xml->unlock();
				return $this->contributeViewRender($request, $form, $editing);
			}

			// get contribution element or create new contribution element
			if ($obj->tagName == self::TAG_CONTRIBUTION) {
				$contribution = $obj;
			} else {
				$contribution = $this->xml->createElement(self::TAG_CONTRIBUTION);
				$contribution->generateId();
				$contribution->setAttribute(self::ATTR_CONTRIBUTION_KEY, bin2hex(openssl_random_pseudo_bytes(8)));
				if ($obj ->tagName == self::TAG_PARTICIPANT)
					$contribution->setAttribute(self::ATTR_CONTRIBUTION_PARTICIPANT, $obj->getId());

				$this->xml->documentElement->getOrCreate(self::TAG_CONTRIBUTION_CONTAINER)->appendChild($contribution);
			}

			// set attributes
			$contribution->setAttribute(self::ATTR_CONTRIBUTION_NAME, $form->dataFor(self::FIELD_NAME_NAME));
			$contribution->setAttribute(self::ATTR_CONTRIBUTION_TITLE, $form->dataFor(self::FIELD_NAME_TITLE));
			$contribution->setAttribute(self::ATTR_CONTRIBUTION_CATEGORY, $form->dataFor(self::FIELD_NAME_CATEGORY));
			$contribution->setAttribute(self::ATTR_CONTRIBUTION_IMAGE, $form->dataFor(self::FIELD_NAME_IMAGE));
			$contribution->setAttribute(self::ATTR_CONTRIBUTION_WEBSITE, $form->dataFor(self::FIELD_NAME_WEBSITE));

			$description = $contribution->getOrCreate(self::TAG_CONTRIBUTION_DESCRIPTION);
			$description->setText($form->dataFor(self::FIELD_NAME_DESCRIPTION, ''));

			$notes = $contribution->getOrCreate(self::TAG_CONTRIBUTION_NOTES);
			$notes->setText($form->dataFor(self::FIELD_NAME_NOTES, ''));

			$this->xml->saveAndUnlock();
			$edit_url = $this->constructFullPath($this->pagename . '/' . self::PATH_CONTRIBUTE . '/' . $contribution->getId() . '/' . $contribution->getAttribute(self::ATTR_CONTRIBUTION_KEY));

			if (isUser()) {
				$user = $this->O_O->getUser();
				$name = getNameForUser($user);
				$email = $user->getAttribute('email');
			} else if ($obj ->tagName == self::TAG_PARTICIPANT) {
				$name = $obj->getAttribute(self::ATTR_PARTICIPANT_NAME);
				$email = $obj->getAttribute(self::ATTR_PARTICIPANT_EMAIL);
			} else {
				$name = __('anonymous');
				$email = false;
			}

			$vars = [
				'name' => htmlspecialchars($name),
				'contribution'=> htmlspecialchars($contribution->getAttribute(self::ATTR_CONTRIBUTION_TITLE) . ' - ' . $contribution->getAttribute(self::ATTR_CONTRIBUTION_NAME)),
			];
			if ($editing)
				$digest = __('festival-edited-contribution', $vars);
			else
				$digest = __('festival-added-contribution', $vars);

			$digest .= ' (<a href="' . $edit_url . '">' . __('festival-digest-edit-contribution') . '</a>)';
			writeToDigest($digest, 'festival-contribution');

			if (!$editing && $email) {
				$edit_url = $this->constructFullPath($this->pagename . '/' . self::PATH_CONTRIBUTE . '/' . $contribution->getId() . '/' . $contribution->getAttribute(self::ATTR_CONTRIBUTION_KEY));

				// Send email
				$vars = [
					'festival-title' => $this->getConfig(self::CONFIG_ID_TITLE),
					'title' => $contribution->getAttribute(self::ATTR_CONTRIBUTION_TITLE),
					'editlink' => $edit_url,
				];
				$this->sendMail($email, 'festival-contribution-added', $vars);
			}

			if ($editing)
				notify('success', __('festival-contribution-modified'));
			else
				notify('success', __('festival-contribution-added'));

			$lineup_url = $this->constructFullPath($this->pagename . '/' . self::PATH_LINEUP);
			return ['redirect', $lineup_url];
		}


		protected function lineupView(HyphaRequest $request) {
			$html = '';
			$contributions = $this->xml->documentElement->getOrCreate(self::TAG_CONTRIBUTION_CONTAINER)->children();
			foreach($contributions as $contribution) {
				$html.= $this->buildContribution($contribution);
				$html.= '<div class="hbar"></div>';
			}
			$this->html->find('#pagename')->text(__('festival-lineup-for') . $this->getConfig(self::CONFIG_ID_TITLE));
			$this->html->find('#main')->html($html);
		}

		/**
		 * Build the HTML for a single contribution in the
		 * lineup.
		 */
		protected function buildContribution($contribution) {
			$html = '<div class="contribution">';
			// artist and title
			$id = $contribution->getId();
			$url = $this->constructFullPath($this->pagename . '/' . self::PATH_LINEUP . '#' . $id);
			$editurl = $this->constructFullPath($this->pagename.'/' . self::PATH_CONTRIBUTE . '/'.$contribution->getId());

			$title = '';
			if ($contribution->getAttribute(self::ATTR_CONTRIBUTION_CATEGORY))
				$title .= $contribution->getAttribute(self::ATTR_CONTRIBUTION_CATEGORY) . ': ';
			if ($contribution->getAttribute(self::ATTR_CONTRIBUTION_NAME))
				$title .= $contribution->getAttribute(self::ATTR_CONTRIBUTION_NAME);
			if ($contribution->getAttribute(self::ATTR_CONTRIBUTION_NAME) && $contribution->getAttribute(self::ATTR_CONTRIBUTION_TITLE))
				$title .= ' - ';
			if ($contribution->getAttribute(self::ATTR_CONTRIBUTION_TITLE))
				$title .= $contribution->getAttribute(self::ATTR_CONTRIBUTION_TITLE);

			$html.= '<h2 id="'.htmlspecialchars($id).'"><a href="'.htmlspecialchars($url).'">'.htmlspecialchars($title).'</a>';
			if (isUser())
				$html.= ' (<a class="edit" href="'.$editurl.'">'.__('festival-edit-contribution').'</a>)';
			$html.= '</h2>';

			// image and description
			$image_filename = $contribution->getAttribute(self::ATTR_CONTRIBUTION_IMAGE);
			if ($image_filename) {
				$img_width = 150;
				$img_height = 150;
				$image = new HyphaImage($image_filename);
				$html.= '<a href="'.htmlspecialchars($image->getUrl()).'"><img src="'.htmlspecialchars($image->getUrl($img_width, $img_height)).'"/></a>';
			}
			$description = $contribution->getElementsByTagName(self::TAG_CONTRIBUTION_DESCRIPTION)->Item(0);
			if ($description) $html.= nl2br(htmlspecialchars($description->text()));

			$days = $this->getConfigElement(self::CONFIG_ID_DAYS, self::CONFIG_TAG_DAYS)->children();
			$locations = $this->getConfigElement(self::CONFIG_ID_LOCATIONS, self::CONFIG_TAG_LOCATIONS)->children();
			foreach($days as $day) {
				$timesHtml = '';
				foreach($contribution->getElementsByTagName(self::TAG_CONTRIBUTION_EVENT) as $event) {
					if ($event->getAttribute(self::ATTR_EVENT_DAY) == $day->getId()) {
						if ($event->getAttribute(self::ATTR_EVENT_BEGIN)) {
							$timesHtml .= '<div class="time-and-place">'.htmlspecialchars($event->getAttribute(self::ATTR_EVENT_BEGIN).'-'.$event->getAttribute(self::ATTR_EVENT_END));
							foreach($locations as $location) if ($location->getId() == $event->getAttribute(self::ATTR_EVENT_LOCATION)) {
								$timesHtml.= ', '.htmlspecialchars($location->getAttribute(self::ATTR_LOCATION_DISPLAY));
							}
							$timesHtml .= '</div>';
						}
					}
				}
				if ($timesHtml) $html.= '<div class="event"><div class="date">'.htmlspecialchars($day->getAttribute(self::ATTR_DAY_DISPLAY)).'</div>'.$timesHtml.'</div>';
			}
			$website = htmlspecialchars($contribution->getAttribute(self::ATTR_CONTRIBUTION_WEBSITE));
			if ($website)
				$html.= "<div class=\"website\"><a href=\"$website\">$website</a></div>";
			$html.= '</div>';

			return $html;
		}

		protected function timetableView(HyphaRequest $request) {
			// Make a list of all days, and per day all
			// locations and the begin and end time.
			$contributions = $this->xml->documentElement->getOrCreate(self::TAG_CONTRIBUTION_CONTAINER)->children();
			$days = $this->getConfigElement(self::CONFIG_ID_DAYS, self::CONFIG_TAG_DAYS)->children();
			$locations = $this->getConfigElement(self::CONFIG_ID_LOCATIONS, self::CONFIG_TAG_LOCATIONS)->children();

			// iterate over all dates
			$html = '';
			$d = 0;
			foreach($days as $day) {
				$daybegin = $day->getAttribute(self::ATTR_DAY_BEGIN);
				$dayend = $day->getAttribute(self::ATTR_DAY_END);

				foreach($contributions as $contribution) {
					$events = $contribution->getElementsByTagName(self::TAG_CONTRIBUTION_EVENT);
					foreach($events as $event) {
						$eventday = $event->getAttribute(self::ATTR_EVENT_DAY);
						$eventbegin = $event->getAttribute(self::ATTR_EVENT_BEGIN);
						$eventend = $event->getAttribute(self::ATTR_EVENT_END);
						if ($eventbegin && $eventend && $eventday == $day->getId()) {
							if (!$daybegin || $eventbegin < $daybegin)
								$daybegin = $eventbegin;
							if (!$dayend || $eventend > $dayend)
								$dayend = $eventend;
						}
					}
				}

				// output date header
				$html.= '<br/><br/><h1>'.$day->getAttribute(self::ATTR_DAY_DISPLAY).'</h1><br/>';
				$html.= "<table class=\"festivalTimetable\">";

				// output row of invisible images to force a more or less regular time grid
				// of 5 minute intervals (12 per hour)
				$html.= "<tr><td></td>";
				$hourstart = intval(substr($daybegin,0,2));
				$hourend = intval(substr($dayend,0,2));
				for ($c = 12*$hourstart; $c < 12*$hourend; $c++) $html.= '<td style="min-width: 10px;"></td>';
				$html.= '</tr>';

				// iterate over all locations
				$line = 0;
				$l = 0;
				foreach($locations as $location) {
					// generate a list of events for the given date and location
					$locevents = [];
					foreach($contributions as $contribution) {
						$events = $contribution->getElementsByTagName(self::TAG_CONTRIBUTION_EVENT);
						foreach($events as $event) {
							if ($event->getAttribute(self::ATTR_EVENT_DAY) == $day->getId() && $event->getAttribute(self::ATTR_EVENT_LOCATION) == $location->getId())
								$locevents[] = [
									$this->timetocols($daybegin, $event->getAttribute(self::ATTR_EVENT_BEGIN)),
									$this->timetocols($daybegin, $event->getAttribute(self::ATTR_EVENT_END)),
									$contribution->getAttribute(self::ATTR_CONTRIBUTION_NAME),
									$contribution->getId(),
									$contribution->getAttribute(self::ATTR_CONTRIBUTION_TITLE),
								];
						}
					}

					while(count($locevents)) {
						sort($locevents);
						$row=[];
						$endOfLastTimeSlot = 0;
						$p=0;
						while($p<count($locevents)) {
							$timeslot = $locevents[$p];
							if ($timeslot[0]>=$endOfLastTimeSlot) {
								$endOfLastTimeSlot = $timeslot[1];
								$row[] = $timeslot;
								array_splice($locevents, $p, 1);
							}
							else $p++;
						}
						// every 6 rows output time grid
						if ($line%6==0) {
							$html.= '<tr><th><div style="text-align:right;">'.__('time').'</div><div style="text-align:left;">'.__('location').'</div></th>';
							for ($c = $hourstart; $c < $hourend; $c++) {
								$html.= '<th class="timeGridOdd" colspan="6">'.$c.'</th>';
								$html.= '<th class="timeGridEven" colspan="6"></th>';
							}
							$html.= '</tr>';
						}
						// output events
						$id = 'a'.$d.'_'.$l;
						$html.= '<tr class="'.($line%2 ? 'tableRowOdd' : 'tableRowEven').'">';
						$html.= '<td id="'.$id.'" class="hover tableRowHeading '.($line%2 ? 'tableRowHeadingOdd' : 'tableRowHeadingEven').'" >'.$location->getAttribute(self::ATTR_LOCATION_DISPLAY).'</td>';
						$t = 0;
						for ($r=0; $r<count($row); $r++) {
							$timeslot = $row[$r];
							$id = "a".$d.'_'.$l.'_'.$r;
							if ($timeslot[0] - $t) $html.= '<td class="'.($line%2 ? 'tableRowOdd' : 'tableRowEven').'" colspan="'.($timeslot[0] - $t).'"></td>';
							$lineup_url = $this->constructFullPath($this->pagename . '/lineup');
							$html.= '<td id="'.$id.'" class="hover tableAct '.($line%2 ? 'tableRowOddAct' : 'tableRowEvenAct').'" colspan="'.($timeslot[1] - $timeslot[0]).'"><a href="'.htmlspecialchars($lineup_url).'#'.htmlspecialchars($timeslot[3]).'">'.htmlspecialchars($timeslot[2]);
							if ($timeslot[2] && $timeslot[4]) $html.= ' - ';
							$html.= '<i>'.$timeslot[4].'</i></a></td>';
							$t = htmlspecialchars($timeslot[1]);
						}
						if ($this->timetocols($daybegin, $dayend) - $t) $html.= '<td class="'.($line%2 ? 'tableRowOdd' : 'tableRowEven').'" colspan="'.($this->timetocols($daybegin, $dayend) - $t).'"></td>';
						$html.= '</tr>';
						$line++;
						$l++;
					}
				}
				$html.= '</table>';
				$d++;
			}
			$this->html->find('#pagename')->text(__('festival-timetable-for') . $this->getConfig(self::CONFIG_ID_TITLE));
			$this->html->find('#main')->html($html);
		}

		protected function timetocols($t1, $t2) {
			$c1=12*intval(substr($t1,0,2)) + intval(substr($t1,3,2))/5;
			$c2=12*intval(substr($t2,0,2)) + intval(substr($t2,3,2))/5;
			return $c2 - $c1;
		}

		/**
		 * Set up the payment-related properties for the given
		 * participant and create an initial payment.
		 * Should be called with the XML lock held.
		 */
		protected function setupPayment($participant, $amount) {
			$participant->ownerDocument->requireLock();
			$participant->setAttribute(self::ATTR_PARTICIPANT_PAYMENT_DESCRIPTION, $this->getConfig(self::CONFIG_ID_TITLE) . ' - ' . $participant->getAttribute(self::ATTR_PARTICIPANT_NAME));
			$participant->setAttribute(self::ATTR_PARTICIPANT_PAYMENT_AMOUNT, $amount);
			// Create a payment right away. This ensures
			// that even if the user never clicks the
			// payment button, this payment will expire and
			// send the user a new payment link via e-mail
			$this->createPayment($participant);
		}

		/**
		 * Create a new payment for the given participant and
		 * registers it in the participant object. This new
		 * payment replaces any previous payment, regardless of
		 * its status.
		 * Should be called with the XML lock held.
		 */
		protected function createPayment($participant) {
			$participant->ownerDocument->requireLock();
			$complete_url = $this->constructFullPath($this->pagename . '/' . self::PATH_PAY . '/' . $participant->getId() . '/' . $participant->getAttribute(self::ATTR_PARTICIPANT_KEY));
			$hook_url = $this->constructFullPath($this->pagename . '/' . self::PATH_PAYMENTHOOK . '/' . $participant->getId() . '/' . $participant->getAttribute(self::ATTR_PARTICIPANT_KEY));

			// load Mollie script
			require_once('system/Mollie/API/Autoloader.php');
			$mollie = new Mollie_API_Client;
			$mollie->setApiKey($this->getConfig('mollie-key'));

			// create payment
			$payment = $mollie->payments->create([
				"amount"       => $participant->getAttribute(self::ATTR_PARTICIPANT_PAYMENT_AMOUNT),
				"description"  => $participant->getAttribute(self::ATTR_PARTICIPANT_PAYMENT_DESCRIPTION),
				"redirectUrl"  => $complete_url,
				"webhookUrl"   => $hook_url,
			]);

			$participant->setAttribute(self::ATTR_PARTICIPANT_PAYMENT_ID, $payment->id);
			$participant->setAttribute(self::ATTR_PARTICIPANT_PAYMENT_STATUS, $payment->status);

			return $payment;
		}

		/**
		 * Check the status of the given participants's payment,
		 * sending out any mails or digests as needed.
		 *
		 * When the payment is complete (successful or
		 * unsuccessful), null is returned. If the payment is
		 * still open, the url to redirect to is returned.
		 *
		 * When create_new is true, a new payment is created if
		 * the existing one is not paid but no longer opened
		 * (e.g. expired, failed or cancelled). In this case, a
		 * null return value means the payment was successful.
		 *
		 * Should be called with the XML lock held.
		 */
		protected function checkPayment($participant, $create_new = false) {
			// load Mollie script
			require_once('system/Mollie/API/Autoloader.php');
			$mollie = new Mollie_API_Client;
			$mollie->setApiKey($this->getConfig('mollie-key'));

			$this->xml->requireLock();
			$payment = $mollie->payments->get($participant->getAttribute(self::ATTR_PARTICIPANT_PAYMENT_ID));

			// If the status changed, process the change. If
			// $create_new is true, do not send any "failed"
			// e-mails, which would only be confusing when
			// the user is about to start a new payment
			// attempt.
			if ($participant->getAttribute(self::ATTR_PARTICIPANT_PAYMENT_STATUS) != $payment->status)
				$this->processPaymentChange($participant, $payment, !$create_new);

			// If the payment is not complete and not open,
			// create a new payment ready to pay (if
			// requested).
			if ($create_new && !$payment->isPaid() && !$payment->isOpen())
				$payment = $this->createPayment($participant);

			if ($payment->isOpen())
				return $payment->getPaymentUrl();
			else
				return null;
		}

		/**
		 * Called when the payment status for a participant has
		 * changed. Takes care of updating the participant,
		 * sending e-mails and adding to the digest.
		 * When $mail_failed is false, no e-mail is sent on a
		 * failed payment, but a note is still added to the
		 * digest.
		 */
		protected function processPaymentChange($participant, $payment, $mail_failed) {
			$participant->setAttribute(self::ATTR_PARTICIPANT_PAYMENT_STATUS, $payment->status);
			if ($payment->isPaid()) {
				if (!$participant->getAttribute(self::ATTR_PARTICIPANT_PAYMENT_TIMESTAMP)) {
					$participant->setAttribute(self::ATTR_PARTICIPANT_PAYMENT_TIMESTAMP, $payment->paidDatetime);

					// Send email
					$contribute_url = $this->constructFullPath($this->pagename . '/' . self::PATH_CONTRIBUTE . '/' . $participant->getId() . '/' . $participant->getAttribute(self::ATTR_PARTICIPANT_KEY));
					$vars = [
						'festival-title' => $this->getConfig(self::CONFIG_ID_TITLE),
						'contributelink' => $contribute_url,
					];
					$rcpt = $participant->getAttribute(self::ATTR_PARTICIPANT_EMAIL);
					$this->sendMail($rcpt, 'festival-payment-succesful', $vars);

					// Add to digest
					$digest = htmlspecialchars($participant->getAttribute(self::ATTR_PARTICIPANT_NAME) . __('festival-payed-for') . $this->getConfig(self::CONFIG_ID_TITLE));
					$digest .= ' (€' . $participant->getAttribute(self::ATTR_PARTICIPANT_PAYMENT_AMOUNT) . ')';
					$digest .= ' (<a href="' . $contribute_url . '">Add contribution</a>)';
					writeToDigest($digest, 'festival-payment');
				}

				// Payment complete, no payment url to
				// redirect to
				return null;
			}

			$error_statuses = [
				Mollie_API_Object_Payment::STATUS_CANCELLED,
				Mollie_API_Object_Payment::STATUS_EXPIRED,
				Mollie_API_Object_Payment::STATUS_FAILED,
			];
			if (in_array($payment->status, $error_statuses)) {
				if ($mail_failed) {
					$pay_url = $this->constructFullPath($this->pagename . '/' . self::PATH_PAY . '/' . $participant->getId() . '/' . $participant->getAttribute(self::ATTR_PARTICIPANT_KEY));
					$vars = [
						'festival-title' => $this->getConfig(self::CONFIG_ID_TITLE),
						'paylink' => $pay_url,
					];
					$rcpt = $participant->getAttribute(self::ATTR_PARTICIPANT_EMAIL);
					$this->sendMail($rcpt, 'festival-payment-failed', $vars);
				}
				$digest = htmlspecialchars($participant->getAttribute(self::ATTR_PARTICIPANT_NAME) . __('festival-failed-to-pay-for') . $this->getConfig(self::CONFIG_ID_TITLE) . ' (' . $payment->status . ')');
				writeToDigest($digest, 'error');
			}
		}

		/**
		 * Retrieve the given mail from the translations and send it,
		 * interpolating the given variables.
		 */
		protected function sendMail($to, $id, $vars) {
			$subject = __($id . '-subject', $vars);
			$body = __($id . '-body', $vars);

			return sendMail($to, $subject, $body);
		}

		/**
		 * Checks url arguments 1 and 2 to see if they contain
		 * an xml id and a key that matches the key attribute of
		 * the element with that id.
		 *
		 * If the key matches, and the element has a tag name
		 * given in the $tags array, the element is returned.
		 * Otherwise, a notification is added and false is
		 * returned.
		 *
		 * If $allow_user is true, the element will be returned
		 * even if the key is not present. If no id is present
		 * either, the user object itself will be returned.
		 */
		protected function checkKeyArguments(HyphaRequest $request, array $tags, $allow_user = false) {
			$id = $request->getArg(1);
			if ($id) {
				$obj = $this->xml->getElementById($id);
				if ($obj && in_array($obj->tagName, $tags)) {
					$key = $request->getArg(2);
					if (!$key && $allow_user && $this->O_O->isUser() ||
					    $key && $obj->getAttribute(self::ATTR_KEY) == $key) {
						return $obj;
					}
				}
			} else if ($allow_user && $this->O_O->isUser()) {
				return $this->O_O->getUser();
			}
			notify('error', __('invalid-or-no-key'));
			return false;
		}

		/**
		 * @todo [LRM]: move so it can be used throughout Hypha
		 * @param string $label
		 * @param null|string $path
		 * @param null|string $command
		 * @param null|string $argument
		 *
		 * @return string
		 */
		protected function makeActionButton($label, $path = null, $command = null, $argument = null) {
			$path = $this->language . '/' . $this->pagename . ($path ? '/' . $path : '');
			$_action = makeAction($path, ($command ? $command : ''), ($argument ? $argument : ''));

			return makeButton(__($label), $_action);
		}

		/**
		 * @todo [LRM]: move so it can be used throughout Hypha
		 * @param string $path
		 * @param null|string $language
		 *
		 * @return string
		 */
		protected function constructFullPath($path, $language = null) {
			$rootUrl = $this->O_O->getRequest()->getRootUrl();
			$language = null == $language ? $this->language : $language;
			$path = '' == $path ? '' : '/' . $path;

			return $rootUrl . $language . $path;
		}
	}
