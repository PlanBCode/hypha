<?php

/*
 * Module: mailing list
 *
 * Mailing list features.
 */

use DOMWrap\NodeList;

/*
 * Class: mailinglist
 */

class mailinglist extends HyphaDatatypePage {
	/** @var Xml */
	protected $xml;

	const FIELD_NAME_ADDRESSES_CONTAINER = 'addresses';
	const FIELD_NAME_MAILINGS_CONTAINER = 'mailings';
	const FIELD_NAME_MAILING = 'mailing';
	const FIELD_NAME_EMAIL = 'email';
	const FIELD_NAME_DESCRIPTION = 'description';
	const FIELD_NAME_EMAIL_WELCOME_TEXT = 'email_welcome_text';
	const FIELD_NAME_PRIVATE = 'private';
	const FIELD_NAME_PAGE_NAME = 'page_name';
	const FIELD_NAME_SUBJECT = 'subject';
	const FIELD_NAME_DATE = 'date';
	const FIELD_NAME_SENDER_EMAIL = 'sender_email';
	const FIELD_NAME_SENDER_NAME = 'sender_name';
	const FIELD_NAME_MESSAGE = 'message';
	const FIELD_NAME_ADDRESS = 'address';
	const FIELD_NAME_STATUS = 'status';
	const FIELD_NAME_TOTAL = 'total';
	const FIELD_NAME_PROGRESS = 'progress';
	const FIELD_NAME_CONFIRM_CODE = 'confirm-code';
	const FIELD_NAME_UNSUBSCRIBE_CODE = 'unsubscribe-code';
	const FIELD_NAME_REMINDED = 'reminded';
	const FIELD_NAME_RECEIVERS = 'receivers';

	const PATH_EDIT = 'edit';
	const PATH_MAILS = 'mails';
	const PATH_MAILS_NEW = 'mails_new';
	const PATH_MAILS_VIEW_ID = 'mails/[[id]]';
	const PATH_MAILS_EDIT = 'mails_edit';
	const PATH_MAILS_EDIT_ID = 'mails_edit/[[id]]';

	const PATH_ADDRESSES = 'addresses';
	const PATH_REMIND = 'remind';
	const PATH_REMIND_EMAIL = 'remind?email=[[email]]';
	const PATH_DELETE_ADDRESS = 'delete';
	const PATH_DELETE_ADDRESS_EMAIL = 'delete?email=[[email]]';

	const PATH_CONFIRM = 'confirm';
	const PATH_CONFIRM_CODE = 'confirm?code=[[code]]';
	const PATH_UNSUBSCRIBE = 'unsubscribe';
	const PATH_UNSUBSCRIBE_CODE = 'unsubscribe?address=[[address]]&code=[[code]]';
	const PATH_UNSUBSCRIBE_BY_ADMIN = 'unsubscribe_by_admin';
	const PATH_UNSUBSCRIBE_BY_ADMIN_EMAIL = 'unsubscribe_by_admin?email=[[email]]';

	const CMD_DELETE = 'delete';
	const CMD_SUBSCRIBE = 'subscribe';
	const CMD_SAVE = 'save';
	const CMD_SEND = 'send';
	const CMD_SEND_TEST = 'test_send';
	const CMD_PROGRESS = 'progress';

	const MAILING_STATUS_DRAFT = 'draft';
	const MAILING_STATUS_SENDING = 'sending';
	const MAILING_STATUS_SENT = 'sent';

	const ADDRESS_STATUS_PENDING = 'pending';
	const ADDRESS_STATUS_CONFIRMED = 'confirmed';
	const ADDRESS_STATUS_UNSUBSCRIBED = 'unsubscribed';

	const PROGRESS_UPDATE_INTERVAL_MS = 1000;
	const TIME_LIMIT_PER_MAIL = 30;

	public function __construct(HyphaDomElement $pageListNode, RequestContext $O_O) {
		parent::__construct($pageListNode, $O_O);
		$this->xml = new Xml('mailinglist', Xml::multiLingualOn, Xml::versionsOff);
		$this->xml->loadFromFile('data/pages/' . $pageListNode->getAttribute('id'));
	}

	/**
	 * @return HyphaDomElement|DOMElement
	 */
	protected function getDoc() {
		return $this->xml->documentElement;
	}

	/**
	 * @param HyphaRequest $request
	 * @return array|string|null
	 *
	 * @throws Exception
	 */
	public function process(HyphaRequest $request) {
		$this->html->writeToElement('pagename', showPagename($this->pagename).' '.asterisk($this->privateFlag));

		if ('edit' != $request->getView() && !$this->hasSender()) {
			notify('warning', __('ml-no-sender'));
		}

		$this->ensureStructure();

		switch ([$request->getView(), $request->getCommand()]) {
			case [null,                            null]:                return $this->defaultView($request);
			case [null,                            self::CMD_DELETE]:    return $this->deleteAction($request);
			case [null,                            self::CMD_SUBSCRIBE]: return $this->subscribeAction($request);
			case [self::PATH_EDIT,                 null]:                return $this->editView($request);
			case [self::PATH_EDIT,                 self::CMD_SAVE]:      return $this->editAction($request);
			case [self::PATH_ADDRESSES,            null]:                return $this->addressesView($request);
			case [self::PATH_REMIND,               null]:                return $this->remindAction($request);
			case [self::PATH_DELETE_ADDRESS,       null]:                return $this->deleteAddressAction($request);
			case [self::PATH_CONFIRM,              null]:                return $this->confirmEmailAction($request);
			case [self::PATH_UNSUBSCRIBE,          null]:                return $this->unsubscribeAction($request);
			case [self::PATH_UNSUBSCRIBE_BY_ADMIN, null]:                return $this->unsubscribeByAdminAction($request);
			case [self::PATH_MAILS_NEW,            null]:                return $this->mailingNewView($request);
			case [self::PATH_MAILS_NEW,            self::CMD_SAVE]:      return $this->mailingNewAction($request);
			case [self::PATH_MAILS,                null]:                return $this->mailingView($request);
			case [self::PATH_MAILS,                self::CMD_SEND]:      return $this->mailingSendAction($request);
			case [self::PATH_MAILS,                self::CMD_SEND_TEST]: return $this->mailingSendTestAction($request);
			case [self::PATH_MAILS,                self::CMD_PROGRESS]:  return $this->mailingProgressAction($request);
			case [self::PATH_MAILS_EDIT,           null]:                return $this->mailingEditView($request);
			case [self::PATH_MAILS_EDIT,           self::CMD_SAVE]:      return $this->mailingEditAction($request);
		}

		return '404';
	}

	public function getSortDateTime() {
		// TODO: Return last mailing sent date?
		return null;
	}

	/**
	 * Checks if the status is new and if so builds the structure and sets the status to draft.
	 */
	protected function ensureStructure() {
		$dataStructure = [
			self::FIELD_NAME_DESCRIPTION => [],
			self::FIELD_NAME_ADDRESSES_CONTAINER => [],
			self::FIELD_NAME_MAILINGS_CONTAINER => [],
			self::FIELD_NAME_EMAIL_WELCOME_TEXT => [],
		];
		$this->xml->lockAndReload();
		$build = function (HyphaDomElement $doc, array $structure) use (&$build) {
			foreach ($structure as $name => $children) {
				$doc->append($build($doc->getOrCreate($name), $children));
			}
			return $doc;
		};
		$build($this->getDoc(), $dataStructure);

		// set initial status, create timestamp and title
		$this->getDoc()->setAttribute(self::FIELD_NAME_SENDER_NAME, '');
		$this->getDoc()->setAttribute(self::FIELD_NAME_SENDER_EMAIL, '');
		$this->xml->saveAndUnlock();
	}

	/**
	 * @param HyphaRequest $request
	 * @return string|null
	 */
	protected function defaultView(HyphaRequest $request) {
		// create form
		$form = $this->createSubscribeForm();

		return $this->defaultViewRender($request, $form);
	}

	protected function defaultViewRender(HyphaRequest $request, WymHTMLForm $form) {
		// add edit button for registered users
		if (isUser()) {
			/** @var HyphaDomElement $commands */
			$commands = $this->html->find('#pageCommands');
			$commands->append($this->makeActionButton(__('edit'), self::PATH_EDIT));
			if (isAdmin()) {
				$path = $this->language . '/' . $this->pagename;
				$commands->append(makeButton(__('delete'), 'if(confirm(\'' . __('sure-to-delete') . '\'))' . makeAction($path, self::CMD_DELETE, '')));
			}
		}

		/** @var NodeList $main */
		$main = $this->html->find('#main');

		// display page name and description
		$description = $this->getDoc()->get(self::FIELD_NAME_DESCRIPTION)->getHtml();
		$main->append($description);

		// display form
		$viewAddresses = isUser() ? '<span style="float: right;">' . $this->makeActionButton(__('ml-view-addresses'), self::PATH_ADDRESSES) . '</span>' : '';
		$main->append('<div><h3>' . htmlspecialchars(__('subscribe')) . $viewAddresses . '</h3></div>');

		// update the form dom so that values and errors can be displayed
		$form->updateDom();

		/** @var NodeList $main */
		$main = $this->html->find('#main');
		$main->append($form);

		$formContainer = $main->children()->end();
		$formContainer->append($this->makeActionButton(__('subscribe'), null, self::CMD_SUBSCRIBE));

		// display archive (non-users only get to see sent items)
		$main->append('<div><h3>' . htmlspecialchars(__('archive')) . '</h3></div>');
		$this->appendMailingsList($main);

		return null;
	}

	/**
	 * Deletes the mailing list.
	 *
	 * @param HyphaRequest $request
	 * @return array
	 */
	protected function deleteAction(HyphaRequest $request) {
		if (!isAdmin()) {
			notify('error', __(isUser() ? 'admin-rights-needed-to-perform-action' : 'login-to-perform-action'));
			return null;
		}

		$this->deletePage();

		notify('success', ucfirst(__('page-successfully-deleted')));
		return ['redirect', $request->getRootUrl()];
	}

	/**
	 * Displays the form to edit the mailing list data.
	 *
	 * @param HyphaRequest $request
	 * @return array|null
	 */
	protected function editView(HyphaRequest $request) {
		if (!isUser()) {
			notify('error', __('login-to-perform-action'));

			return ['redirect', $this->path()];
		}

		// create form
		$senderData = $this->getSenderData();
		$formData = [
			self::FIELD_NAME_PAGE_NAME => showPagename($this->pagename),
			self::FIELD_NAME_PRIVATE => $this->privateFlag,
			self::FIELD_NAME_SENDER_EMAIL => $senderData['email'],
			self::FIELD_NAME_SENDER_NAME => $senderData['name'],
			self::FIELD_NAME_DESCRIPTION => $this->getDoc()->get(self::FIELD_NAME_DESCRIPTION)->getHtml(),
			self::FIELD_NAME_EMAIL_WELCOME_TEXT => $this->getDoc()->get(self::FIELD_NAME_EMAIL_WELCOME_TEXT)->getHtml(),
		];

		$form = $this->createEditForm($formData);

		return $this->editViewRender($request, $form);
	}

	protected function editViewRender(HyphaRequest $request, HTMLForm $form) {
		// update the form dom so that values and errors can be displayed
		$form->updateDom();

		/** @var HyphaDomElement $main */
		$main = $this->html->find('#main');
		$main->append($form);

		/** @var HyphaDomElement $commands */
		$commands = $this->html->find('#pageCommands');
		$commands->append($this->makeActionButton(__('save'), self::PATH_EDIT, self::CMD_SAVE));
		$commands->append($this->makeActionButton(__('cancel'), ''));

		return null;
	}

	/**
	 * Updates the mailing list with the posted data.
	 *
	 * @param HyphaRequest $request
	 * @return array|null
	 */
	protected function editAction(HyphaRequest $request) {
		if (!isUser()) {
			notify('error', __('login-to-perform-action'));

			return ['redirect', $this->path()];
		}

		// create form
		$form = $this->createEditForm($request->getPostData());

		// validate form
		$form->validateRequiredField(self::FIELD_NAME_SENDER_EMAIL);
		$form->validateRequiredField(self::FIELD_NAME_SENDER_NAME);
		$form->validateEmailField(self::FIELD_NAME_SENDER_EMAIL);

		// return form if there are errors
		if (!empty($form->errors)) {
			return $this->editViewRender($request, $form);
		}

		$pagename = validatePagename($form->dataFor(self::FIELD_NAME_PAGE_NAME));
		$private = $form->dataFor(self::FIELD_NAME_PRIVATE, false);

		$this->savePage($pagename, null, $private);

		$this->xml->lockAndReload();
		$senderEmail = $form->dataFor(self::FIELD_NAME_SENDER_EMAIL);
		$senderName = $form->dataFor(self::FIELD_NAME_SENDER_NAME);
		$this->getDoc()->setAttribute('sender-email', $senderEmail);
		$this->getDoc()->setAttribute('sender-name', $senderName);
		$this->getDoc()->get(self::FIELD_NAME_DESCRIPTION)->setHtml($form->dataFor(self::FIELD_NAME_DESCRIPTION), true);
		$this->getDoc()->get(self::FIELD_NAME_EMAIL_WELCOME_TEXT)->setHtml($form->dataFor(self::FIELD_NAME_EMAIL_WELCOME_TEXT), true);

		$this->xml->saveAndUnlock();

		notify('success', ucfirst(__('ml-successfully-updated')));

		return ['redirect', $this->constructFullPath($pagename)];
	}

	/**
	 * @param HyphaRequest $request
	 * @return string|null
	 */
	protected function subscribeAction(HyphaRequest $request) {
		// create form
		$form = $this->createSubscribeForm($request->getPostData());

		// validate form
		$form->validateRequiredField(self::FIELD_NAME_EMAIL);
		$form->validateEmailField(self::FIELD_NAME_EMAIL);

		// process form if there are no errors
		if (!empty($form->errors)) {
			return $this->defaultViewRender($request, $form);
		}

		// check if email is already in the list, no need to have it twice.
		$this->xml->lockAndReload();
		$email = strtolower($form->dataFor(self::FIELD_NAME_EMAIL));

		$xpath = './/' . self::FIELD_NAME_ADDRESS . '[@' . self::FIELD_NAME_EMAIL . '=' . xpath_encode($email) . ' and @' . self::FIELD_NAME_STATUS . '!="' . self::ADDRESS_STATUS_UNSUBSCRIBED . '"]';
		$address = $this->findAddresses($xpath)->first();

		$addAddress = !$address instanceof HyphaDomElement;
		if ($addAddress) {
			$sendEmail = true;
			$code = $this->constructCode();
		} else {
			// if the email is there just send another email if it is still pending
			$sendEmail = $address->getAttribute(self::FIELD_NAME_STATUS) == self::ADDRESS_STATUS_PENDING;
			$code = $address->getAttribute(self::FIELD_NAME_CONFIRM_CODE);
		}

		if ($addAddress) {
			// add email with status pending
			/** @var HyphaDomElement $address */
			$address = $this->xml->createElement(self::FIELD_NAME_ADDRESS);
			$address->setAttribute(self::FIELD_NAME_EMAIL, $email);
			$address->setAttribute(self::FIELD_NAME_STATUS, self::ADDRESS_STATUS_PENDING);
			$address->setAttribute(self::FIELD_NAME_CONFIRM_CODE, $code);
			$address->setAttribute(self::FIELD_NAME_REMINDED, false);
			/** @var HyphaDomElement $addressesContainer */
			$addressesContainer = $this->getDoc()->get(self::FIELD_NAME_ADDRESSES_CONTAINER);
			$addressesContainer->append($address);
			$this->xml->saveAndUnlock();
		} else {
			$this->xml->unlock();
		}

		if ($sendEmail) {
			$this->sendConfirmationMail($code, $email);
			notify('success', ucfirst(__('ml-confirmation-mail-sent')));
		} else {
			notify('success', ucfirst(__('ml-successfully-subscribed')));
		}

		// all is success refresh page with success notification
		return 'reload';
	}

	/**
	 * Send email so that pending subscribed can confirm email address.
	 *
	 * @param string $code
	 * @param string $email
	 * @return void
	 */
	protected function sendConfirmationMail($code, $email) {
		$confirmUrl = $this->path(self::PATH_CONFIRM_CODE, ['code' => $code]);
		$confirmLink = '<a href="' . htmlspecialchars($confirmUrl) . '">' . __('ml-please-confirm-email') . '</a>';
		$welcomeText = $this->getDoc()->get(self::FIELD_NAME_EMAIL_WELCOME_TEXT)->getHtml();
		$welcomeText .= '<br><br>' . $confirmLink;
		$subject = hypha_getTitle() . ' - ' . __('ml-confirmation-email-subject');
		return $this->sendMail($subject, $welcomeText, [$email]);
	}

	protected function confirmEmailAction(HyphaRequest $request) {
		$code = isset($_GET['code']) ? $_GET['code'] : null;
		if (null == $code) {
			notify('error', __('missing-arguments'));
			return null;
		}

		$this->xml->lockAndReload();

		$xpath = './/' . self::FIELD_NAME_ADDRESS . '[@' . self::FIELD_NAME_CONFIRM_CODE . '=' . xpath_encode($code) . ' and @' . self::FIELD_NAME_STATUS . '!="' . self::ADDRESS_STATUS_UNSUBSCRIBED . '"]';
		$address = $this->findAddresses($xpath)->first();

		if (!$address instanceof HyphaDomElement) {
			$this->xml->unlock();
			notify('error', __('invalid-code'));
			return null;
		}

		if ($address->getAttribute(self::FIELD_NAME_STATUS) !== self::ADDRESS_STATUS_CONFIRMED) {
			$address->setAttribute(self::FIELD_NAME_STATUS, self::ADDRESS_STATUS_CONFIRMED);
			$address->setAttribute(self::FIELD_NAME_UNSUBSCRIBE_CODE, $this->constructCode());
			$this->xml->saveAndUnlock();
		} else {
			$this->xml->unlock();
		}

		notify('success', ucfirst(__('ml-successfully-subscribed')));
		return ['redirect', $this->path()];
	}

	protected function unsubscribeAction(HyphaRequest $request) {
		$code = isset($_GET['code']) ? $_GET['code'] : null;
		if (null == $code) {
			notify('error', __('missing-arguments'));
			return null;
		}

		try {
			$xpath = './/' . self::FIELD_NAME_ADDRESS . '[@' . self::FIELD_NAME_UNSUBSCRIBE_CODE . '=' . xpath_encode($code) . ']';
			$this->unsubscribe($xpath);
		} catch (\Exception $e) {
			$msg = $e->getCode() === 404 ? __('invalid-code') : $e->getMessage();
			notify('error', $msg);
			return null;
		}

		notify('success', ucfirst(__('ml-successfully-unsubscribed')));

		return ['redirect', $this->path()];
	}

	/**
	 * Unsubscribe a confirmed address. Can only be executed by admins.
	 *
	 * @param HyphaRequest $request
	 * @return array|null
	 */
	protected function unsubscribeByAdminAction(HyphaRequest $request) {
		if (!isAdmin()) {
			notify('error', __(isUser() ? 'admin-rights-needed-to-perform-action' : 'login-to-perform-action'));
			return ['redirect', $this->path()];
		}

		$email = isset($_GET['email']) ? $_GET['email'] : null;
		if (null == $email) {
			notify('error', __('missing-arguments'));
			return null;
		}

		try {
			$xpath = './/' . self::FIELD_NAME_ADDRESS . '[@' . self::FIELD_NAME_EMAIL . '=' . xpath_encode($email) . ']';
			$this->unsubscribe($xpath);
		} catch (\Exception $e) {
			notify('error', $e->getMessage());
			return null;
		}

		notify('success', ucfirst(__('ml-successfully-unsubscribed-by-admin', ['email' => $email])));

		return ['redirect', $this->path(self::PATH_ADDRESSES)];
	}

	/**
	 * @param string $xpath
	 * @throws Exception
	 */
	protected function unsubscribe($xpath) {
		$this->xml->lockAndReload();

		$address = $this->findAddresses($xpath)->first();
		if (!$address instanceof HyphaDomElement) {
			$this->xml->unlock();
			throw new \Exception(__('not-found'), 404);
		}

		if ($address->getAttribute(self::FIELD_NAME_STATUS) === self::ADDRESS_STATUS_PENDING) {
			// weird case, should never happen, let's handle it anyway
			$this->xml->unlock();
			throw new \Exception(__('ml-mail-has-invalid-status'), 409);
		}

		if ($address->getAttribute(self::FIELD_NAME_STATUS) != self::ADDRESS_STATUS_UNSUBSCRIBED) {
			$address->setAttribute(self::FIELD_NAME_STATUS, self::ADDRESS_STATUS_UNSUBSCRIBED);
			$this->xml->saveAndUnlock();
		} else {
			$this->xml->unlock();
		}
	}

	/**
	 * Resend a confirmation email message to a pending address. Can only be executed by admins.
	 *
	 * @param HyphaRequest $request
	 * @return null|array
	 */
	protected function remindAction(HyphaRequest $request) {
		if (!isAdmin()) {
			notify('error', __(isUser() ? 'admin-rights-needed-to-perform-action' : 'login-to-perform-action'));
			return ['redirect', $this->path()];
		}

		$email = isset($_GET['email']) ? $_GET['email'] : null;
		if (null == $email) {
			notify('error', __('missing-arguments'));
			return null;
		}

		$this->xml->lockAndReload();

		$xpath = './/' . self::FIELD_NAME_ADDRESS . '[@' . self::FIELD_NAME_EMAIL . '=' . xpath_encode($email) . ' and @' . self::FIELD_NAME_STATUS . '!="' . self::ADDRESS_STATUS_UNSUBSCRIBED . '"]';
		$address = $this->findAddresses($xpath)->first();

		if (!$address instanceof HyphaDomElement) {
			$this->xml->unlock();
			notify('error', __('not-found'));
			return null;
		}

		if ($address->getAttribute(self::FIELD_NAME_STATUS) !== self::ADDRESS_STATUS_PENDING) {
			$this->xml->unlock();
			notify('error', __('ml-no-reminder-address-no-longer-pending'));
			return null;
		}

		if ($address->getAttribute(self::FIELD_NAME_REMINDED) != true) {
			$code = $address->getAttribute(self::FIELD_NAME_CONFIRM_CODE);
			$address->setAttribute(self::FIELD_NAME_REMINDED, true);
			$this->xml->saveAndUnlock();

			$this->sendConfirmationMail($code, $email);
			notify('success', ucfirst(__('ml-reminder-sent')));
		} else {
			notify('success', ucfirst(__('ml-reminder-already-sent')));
		}

		return ['redirect', $this->path(self::PATH_ADDRESSES)];
	}

	/**
	 * Deleted a pending address. Can only be executed by admins.
	 *
	 * @param HyphaRequest $request
	 * @return null|array
	 */
	protected function deleteAddressAction(HyphaRequest $request) {
		if (!isAdmin()) {
			notify('error', __(isUser() ? 'admin-rights-needed-to-perform-action' : 'login-to-perform-action'));
			return ['redirect', $this->path()];
		}

		$email = isset($_GET['email']) ? $_GET['email'] : null;
		if (null == $email) {
			notify('error', __('missing-arguments'));
			return null;
		}

		$this->xml->lockAndReload();

		$xpath = './/' . self::FIELD_NAME_ADDRESS . '[@' . self::FIELD_NAME_EMAIL . '=' . xpath_encode($email) . ' and @' . self::FIELD_NAME_STATUS . '!="' . self::ADDRESS_STATUS_UNSUBSCRIBED . '"]';
		$address = $this->findAddresses($xpath)->first();

		if (!$address instanceof HyphaDomElement) {
			$this->xml->unlock();
			notify('error', __('not-found'));
			return null;
		}

		if ($address->getAttribute(self::FIELD_NAME_STATUS) !== self::ADDRESS_STATUS_PENDING) {
			$this->xml->unlock();
			notify('error', __('ml-cannot-delete-address-no-longer-pending'));
			return null;
		}

		/** @var HyphaDomElement $addressesContainer */
		$addressesContainer = $this->getDoc()->get(self::FIELD_NAME_ADDRESSES_CONTAINER);
		$addressesContainer->removeChild($address);
		$this->xml->saveAndUnlock();

		notify('success', ucfirst(__('ml-address-deleted')));
		return ['redirect', $this->path(self::PATH_ADDRESSES)];
	}

	/**
	 * Builds a table with addresses.
	 * @param HyphaRequest $request
	 * @return array|null
	 */
	protected function addressesView(HyphaRequest $request) {
		if (!isUser()) {
			notify('error', __('login-to-perform-action'));
			return ['redirect', $this->path()];
		}

		$table = new HTMLTable();
		/** @var HyphaDomElement $main */
		$main = $this->html->find('#main');
		$main->appendChild($table);
		$table->addClass('section');
		$table->addHeaderRow()->addCells(['', __('addresses'), __('status'), __('action')]);
		$xpath = './/' . self::FIELD_NAME_ADDRESS . '[@' . self::FIELD_NAME_STATUS . '="' . self::ADDRESS_STATUS_CONFIRMED . '" or @' . self::FIELD_NAME_STATUS . '="' . self::ADDRESS_STATUS_PENDING . '"]';
		$addresses = $this->findAddresses($xpath);
		/** @var HyphaDomElement $address */
		foreach ($addresses as $address) {
			$status = $address->getAttribute(self::FIELD_NAME_STATUS);
			$statusText = __('ml-address-status-' . $status);
			$actions = [];
			$email = $address->getAttribute(self::FIELD_NAME_EMAIL);
			if (isAdmin()) {
				if ($status === self::ADDRESS_STATUS_PENDING) {
					if ($address->getAttribute(self::FIELD_NAME_REMINDED) != true) {
						$remindPath = $this->substituteSpecial(self::PATH_REMIND_EMAIL, ['email' => $email]);
						$actions[] = $this->makeActionButton(__('ml-send-reminder'), $remindPath);
					} else {
						$statusText .= ', ' . __('ml-reminded');
					}
					$deletePath = $this->substituteSpecial(self::PATH_DELETE_ADDRESS_EMAIL, ['email' => $email]);
					$actions[] = $this->makeActionButton(__('delete'), $deletePath);
				}
				if ($status === self::ADDRESS_STATUS_CONFIRMED) {
					$unsubscribePath = $this->substituteSpecial(self::PATH_UNSUBSCRIBE_BY_ADMIN_EMAIL, ['email' => $email]);
					$actions[] = $this->makeActionButton(__('ml-unsubscribe'), $unsubscribePath);
				}
			}
			$row = $table->addRow();
			$row->addCells(['', $email, $statusText]);
			$row->addCell()->setHtml(implode(' ', $actions));
		}
		$table->addRow()->addCells([__('total'), count($addresses), '', '']);

		// add buttons
		/** @var HyphaDomElement $commands */
		$commands = $this->html->find('#pageCommands');
		$commands->append($this->makeActionButton(__('back')));
		return null;
	}

	protected function getNumberOfAddresses(){
		$xpath = './/' . self::FIELD_NAME_ADDRESS . '[@' . self::FIELD_NAME_STATUS . '="' . self::ADDRESS_STATUS_CONFIRMED . '"]';
		$addresses = $this->findAddresses($xpath);
		return count($addresses);
	}
	/**
	 * @param HyphaRequest $request
	 *
	 * @return null
	 * @throws Exception
	 */
	protected function mailingView(HyphaRequest $request) {
		$mailingId = $request->getArg(1);

		// check if given id was correct
		/** @var HyphaDomElement $mailing */
		$mailing = $this->xml->getElementById($mailingId);
		if (!$mailing instanceof HyphaDomElement) {
			return '404';
		}

		// return false if status is draft without client logged in
		if (self::MAILING_STATUS_DRAFT == $mailing->getAttribute('status') && !isUser()) {
			return '404';
		}

		// display mailing
		/** @var HyphaDomElement $main */
		$subject = $mailing->getAttribute('subject');
		$main = $this->html->find('#main');
		$main->append('<div><h2>' . htmlspecialchars($subject) . '</h2></div>');
		$date = $mailing->getAttribute('date');
		if ($date) {
			$date = new DateTime($date);
			$main->append('<div>' . htmlspecialchars(__('date')) . ': ' . $date->format(__('ml-date-format')) . '</h2></div>');
		}
		if (isUser()) {
			$receivers = $mailing->getAttribute('receivers');
			if (null != $receivers) {
				$main->append('<div>' . htmlspecialchars(__('ml-received-by')) . ': ' . $receivers . '</div>');
			}
		}
		$main->append($mailing->getHtml());

		// buttons
		/** @var HyphaDomElement $commands */
		$commands = $this->html->find('#pageCommands');
		$commands->append($this->makeActionButton(__('back')));

		// get status
		$status = $mailing->getAttribute('status');

		// add edit button when in draft
		if (self::MAILING_STATUS_DRAFT == $status) {
			$editPath = $this->substituteSpecial(self::PATH_MAILS_EDIT_ID, ['id' => $mailing->getId()]);
			$commands->append($this->makeActionButton(__('edit'), $editPath));
			$num = $this->getNumberOfAddresses();
			$linkToMailing = $this->path(self::PATH_MAILS_VIEW_ID, ['id' => $mailingId]);

			$action = 'if(confirm(' . json_encode(__('ml-sure-to-send', ['count' => $num])) . ')){
				const divPopUp = document.createElement("div"); // create a popup
				divPopUp.className = "messagebox modal";
				const divProgress = document.createElement("div");
				divProgress.className = "progress";

				const spanProgressText = document.createElement("span");
				spanProgressText.innerHTML= "'.__('ml-send-in-progress').'";
				spanProgressText.className = "progress-text";
				const spanProgressIndicator = document.createElement("span");
				spanProgressIndicator.className = "progress-indicator";
				spanProgressIndicator.innerHTML = "0/'.$num.'";
				const divWarning = document.createElement("div");
				divWarning.className = "warning";
				divWarning.innerHTML = "'.__('ml-do-not-close').'";
				divProgress.appendChild(spanProgressText);
				divProgress.appendChild(spanProgressIndicator);
				divPopUp.appendChild(divProgress);
				divPopUp.appendChild(divWarning);

				const divGray = document.createElement("div"); // create a gray layer
				divGray.className="gray-modal-background";
				document.body.appendChild(divGray);
				document.body.appendChild(divPopUp);
				let checkProgressInterval;
				const onCompleted = function(){
					if(checkProgressInterval) clearInterval(checkProgressInterval);
					checkProgressInterval = null;
					if(divPopUp && divPopUp.parentNode === document.body) document.body.removeChild(divPopUp);
					if(divGray && divGray.parentNode === document.body) document.body.removeChild(divGray);
					window.onbeforeunload = undefined;
				};
				const updateProgress = function(result){
					const {'.self::FIELD_NAME_STATUS.','.self::FIELD_NAME_PROGRESS.','.self::FIELD_NAME_TOTAL.'} = JSON.parse(result);
					if('.self::FIELD_NAME_PROGRESS.'>='.self::FIELD_NAME_TOTAL.' || '.self::FIELD_NAME_STATUS.' === "'.self::MAILING_STATUS_SENT.'") onCompleted();
					else spanProgressIndicator.innerHTML = '.self::FIELD_NAME_PROGRESS.'+"/"+'.self::FIELD_NAME_TOTAL.';
				};
				const checkProgress = function(){'. // retrieve the progress of the send action
					makeAction($linkToMailing, self::CMD_PROGRESS, '', null, 'updateProgress')
				.'};
				checkProgressInterval = setInterval(checkProgress,'.self::PROGRESS_UPDATE_INTERVAL_MS.');
				window.onbeforeunload = function(){return \''.__('ml-sure-to-close').'\';};' // lock the browser from exiting.
				 . makeAction($linkToMailing, self::CMD_SEND, '', null, 'onCompleted').';
			 }';

			if ($num > 0){ // hide send button if no addresses available
				$commands->append(makeButton(__('send'), $action));
			}
			$action = 'hypha('.json_encode($linkToMailing).', '.json_encode(self::CMD_SEND_TEST).', prompt(' . json_encode(__('email')) . '), $(this).closest(\'form\'));';
			$commands->append(makeButton(__('ml-test-send'), $action));
		}

		return null;
	}

	/**
	 * @param HyphaRequest $request
	 * @return array|null|void
	 */
	protected function mailingNewView(HyphaRequest $request) {
		if (!isUser()) {
			notify('error', __('login-to-perform-action'));
			return null;
		}

		// create form
		$form = $this->createMailingForm();

		return $this->mailingFormViewRender($form, '', self::PATH_MAILS_NEW);
	}

	protected function mailingFormViewRender(WymHTMLForm $form, $cancelPath, $submitPath) {
		// update the form dom so that values and errors can be displayed
		$form->updateDom();

		/** @var HyphaDomElement $main */
		$main = $this->html->find('#main');
		$main->append($form);

		/** @var HyphaDomElement $commands */
		$commands = $this->html->find('#pageCommands');
		$commands->append($this->makeActionButton(__('cancel'), $cancelPath));
		$commands->append($this->makeActionButton(__('save'), $submitPath, self::CMD_SAVE));
	}

	/**
	 * @param HyphaRequest $request
	 * @return array|null|void
	 */
	protected function mailingNewAction(HyphaRequest $request) {
		if (!isUser()) {
			notify('error', __('login-to-perform-action'));
			return null;
		}

		// create form
		$form = $this->createMailingForm($request->getPostData());

		$form->validateRequiredField(self::FIELD_NAME_SUBJECT);
		$form->validateRequiredField(self::FIELD_NAME_MESSAGE);
		if (!empty($form->errors)) {
			return $this->mailingFormViewRender($form, '', self::PATH_MAILS_NEW);
		}

		$this->xml->lockAndReload();

		// create mailing with given data
		/** @var HyphaDomElement|NodeList $mailingsContainer */
		$mailingsContainer = $this->getDoc()->get(self::FIELD_NAME_MAILINGS_CONTAINER);

		/** @var HyphaDomElement $mailing */
		$mailing = $this->xml->createElement(self::FIELD_NAME_MAILING);
		$mailingsContainer->append($mailing);

		$mailing->generateId();
		$mailing->setAttr(self::FIELD_NAME_STATUS, self::MAILING_STATUS_DRAFT);
		$mailing->setAttr(self::FIELD_NAME_SUBJECT, $form->dataFor(self::FIELD_NAME_SUBJECT));
		$mailing->setHtml(wikify_html($form->dataFor(self::FIELD_NAME_MESSAGE)));

		$this->xml->saveAndUnlock();

		notify('success', ucfirst(__('ml-successfully-created')));

		return ['redirect', $this->path(self::PATH_MAILS_EDIT_ID, ['id' => $mailing->getId()])];
	}

	/**
	 * @param HyphaRequest $request
	 * @return string|array|null
	 */
	protected function mailingEditView(HyphaRequest $request) {
		if (!isUser()) {
			notify('error', __('login-to-perform-action'));

			return null;
		}

		$mailingId = $request->getArg(1);

		// check given id
		/** @var HyphaDomElement $mailing */
		$mailing = $this->xml->getElementById($mailingId);
		if (!$mailing instanceof HyphaDomElement) {
			return '404';
		}

		if (self::MAILING_STATUS_DRAFT != $mailing->getAttribute('status')) {
			notify('error', __('unable-to-edit'));
			return null;
		}

		// create form
		$formData = [
			self::FIELD_NAME_SUBJECT => $mailing->getAttribute('subject'),
			self::FIELD_NAME_MESSAGE => $mailing->getHtml(),
		];
		$form = $this->createMailingForm($formData);

		$cancelPath = $this->substituteSpecial(self::PATH_MAILS_VIEW_ID, ['id' => $mailingId]);
		$submitPath = $this->substituteSpecial(self::PATH_MAILS_EDIT_ID, ['id' => $mailingId]);

		return $this->mailingFormViewRender($form, $cancelPath, $submitPath);
	}

	/**
	 * no user
	 * check mailing id
	 * check mailing
	 * check draft status
	 *
	 * @param HyphaRequest $request
	 * @return string|array|null
	 */
	protected function mailingEditAction(HyphaRequest $request) {
		// throw error if edit is requested without client logged in
		if (!isUser()) {
			notify('error', __('login-to-edit'));
			return null;
		}

		$mailingId = $request->getArg(1);

		/** @var HyphaDomElement $mailing */
		$mailing = $this->xml->getElementById($mailingId);
		if (!$mailing instanceof HyphaDomElement) {
			return '404';
		}

		// check if status is correct
		$status = $mailing->getAttribute('status');
		if (self::MAILING_STATUS_DRAFT !== $status) {
			notify('error', __('unable-to-edit'));
			return null;
		}

		// create form
		$form = $this->createMailingForm($request->getPostData());

		// process form if it was posted
		$form->validateRequiredField(self::FIELD_NAME_SUBJECT);
		$form->validateRequiredField(self::FIELD_NAME_MESSAGE);
		if (!empty($form->errors)) {
			$cancelPath = $this->substituteSpecial(self::PATH_MAILS_VIEW_ID, ['id' => $mailingId]);
			$submitPath = $this->substituteSpecial(self::PATH_MAILS_EDIT_ID, ['id' => $mailingId]);
			return $this->mailingFormViewRender($form, $cancelPath, $submitPath);
		}

		$this->xml->lockAndReload();

		/** @var HyphaDomElement $mailing */
		$mailing = $this->xml->getElementById($mailingId);
		$mailing->setAttr(self::FIELD_NAME_SUBJECT, $form->dataFor(self::FIELD_NAME_SUBJECT));
		$mailing->setHtml(wikify_html($form->dataFor(self::FIELD_NAME_MESSAGE)));

		$this->xml->saveAndUnlock();

		// goto view page with notification
		notify('success', __('ml-successfully-created'));
		return ['redirect', $this->path(self::PATH_MAILS_VIEW_ID, ['id' => $mailingId])];
	}

	/**
	 * Creates a HTML form object for the subscribers.
	 *
	 * @param array $values
	 * @return WymHTMLForm
	 */
	protected function createSubscribeForm(array $values = []) {
		$html = <<<EOF
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<label for="[[field-name-email]]">[[email]]</label>: <input type="text" id="[[field-name-email]]" name="[[field-name-email]]" placeholder="[[email]]" />
			</div>
EOF;

		$vars = [
			'email' => __('email'),
			'field-name-email' => self::FIELD_NAME_EMAIL,
		];

		$html = hypha_substitute($html, $vars);

		return new WymHTMLForm($html, $values);
	}

	/**
	 * @param array $values
	 * @return WymHTMLForm
	 */
	protected function createEditForm(array $values = []) {
		$html = <<<EOF
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="[[field-name-page-name]]">[[title]]</label></strong> <input type="text" id="[[field-name-page-name]]" name="[[field-name-page-name]]" />
				<strong><label for="[[field-name-sender-name]]"> [[sender-name]] </label></strong><input type="text" id="[[field-name-sender-name]]" name="[[field-name-sender-name]]" />
				<strong><label for="[[field-name-sender-email]]"> [[sender-email]] </label></strong><input type="text" id="[[field-name-sender-email]]" name="[[field-name-sender-email]]" />
				<strong><label for="[[field-name-private-page]]"> [[private-page]] </label></strong><input type="checkbox" name="[[field-name-private-page]]" id="[[field-name-private-page]]" />
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="[[field-name-description]]"> [[description]] </label></strong><editor name="[[field-name-description]]"></editor>
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="[[field-name-email-welcome-text]]"> [[email-welcome-text]] </label></strong><editor name="[[field-name-email-welcome-text]]"></editor>
			</div>
EOF;

		$vars = [
			'title' => __('title'),
			'field-name-page-name' => self::FIELD_NAME_PAGE_NAME,
			'sender-name' => __('mailing-sender-name'),
			'field-name-sender-name' => self::FIELD_NAME_SENDER_NAME,
			'sender-email' => __('mailing-sender-email'),
			'field-name-sender-email' => self::FIELD_NAME_SENDER_EMAIL,
			'private-page' => __('private-page'),
			'field-name-private-page' => self::FIELD_NAME_PRIVATE,
			'description' => __('description'),
			'field-name-description' => self::FIELD_NAME_DESCRIPTION,
			'email-welcome-text' => __('email-welcome-text'),
			'field-name-email-welcome-text' => self::FIELD_NAME_EMAIL_WELCOME_TEXT,
		];

		$html = hypha_substitute($html, $vars);

		return new WymHTMLForm($html, $values);
	}

	/**
	 * @param array $values
	 *
	 * @return WymHTMLForm
	 */
	protected function createMailingForm(array $values = []) {
		$html = <<<EOF
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<label for="[[field-name-subject]]"> [[subject]] </label> <input type="text" id="[[field-name-subject]]" name="[[field-name-subject]]" />
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="[[field-name-message]]"> [[message]] </label></strong><editor id="[[field-name-message]]" name="[[field-name-message]]"></editor>
			</div>
EOF;

		$vars = [
			'subject' => __('subject'),
			'field-name-subject' => self::FIELD_NAME_SUBJECT,
			'message' => __('message'),
			'field-name-message' => self::FIELD_NAME_MESSAGE,
		];

		$html = hypha_substitute($html, $vars);

		return new WymHTMLForm($html, $values);
	}

	/**
	 * @todo [LRM]: find a way to prevent timeouts from happening
	 *  copy addresses and remove each one after sending
	 *  implement continue
	 *  implement progress bar
	 *  implement 1) increase timeout or 2) client batch
	 *  implement if option (2) message with 'do not close client'
	 *  store date with locale
	 *
	 * @param HyphaRequest $request
	 * @throws Exception
	 * @return string|null
	 */
	protected function mailingSendAction(HyphaRequest $request) {
		if (!$this->hasSender()) {
			notify('error', __('ml-no-sender'));
			return null;
		}

		$mailingId = $request->getArg(1);

		// check if given id was correct
		/** @var HyphaDomElement $mailing */
		$mailing = $this->xml->getElementById($mailingId);
		if (!$mailing instanceof HyphaDomElement) {
			return '404';
		}

		// get status
		$status = $mailing->getAttribute(self::FIELD_NAME_STATUS);
		if (self::MAILING_STATUS_DRAFT !== $status) {
			notify('error', __('ml-mail-has-invalid-status'));
			return null;
		}

		$senderData = $this->getSenderData();

		// mark mailing as sending
		$this->xml->lockAndReload();
		$mailing = $this->xml->getElementById($mailingId);
		$mailing->setAttribute(self::FIELD_NAME_STATUS, self::MAILING_STATUS_SENDING);
		$count = 0;
		$mailing->setAttribute(self::FIELD_NAME_PROGRESS, $count);
		/** @var HyphaDomElement $mailings */
		$mailings = $this->getDoc()->getOrCreate(self::FIELD_NAME_MAILINGS_CONTAINER);
		$mailings->append($mailing);
		$this->xml->saveAndUnlock();

		// iterate over the confirmed addresses and send the mailing
		$xpath = './/' . self::FIELD_NAME_ADDRESS . '[@' . self::FIELD_NAME_STATUS . '="' . self::ADDRESS_STATUS_CONFIRMED . '"]';
		$receivers = $this->findAddresses($xpath);
		if (count($receivers) === 0) {
			notify('error', __('ml-mail-has-invalid-status'));
			return null;
		}

		$linkToMailing = $this->path(self::PATH_MAILS_VIEW_ID, ['id' => $mailingId]);
		/** @var HyphaDomElement $receiver */
		foreach ($receivers as $receiver) {
			set_time_limit(self::TIME_LIMIT_PER_MAIL);
			$email = $receiver->getAttribute(self::FIELD_NAME_EMAIL);
			$code = $receiver->getAttribute(self::FIELD_NAME_UNSUBSCRIBE_CODE);
			// include email for server record purposes
			$linkToUnsubscribe = $this->path(self::PATH_UNSUBSCRIBE_CODE, ['address' => $email, 'code' => $code]);
			$message = '<p><a href="' . $linkToMailing . '">' . __('ml-if-unreadable-use-link') . '</a></p>';
			$message .= $mailing->getHtml();
			$message .= '<p><a href="' . $linkToUnsubscribe . '">' . __('ml-unsubscribe') . '</a></p>';
			$this->sendMail($mailing->getAttribute(self::FIELD_NAME_SUBJECT), $message, [$email], $senderData['email'], $senderData['name']);

			$this->xml->lockAndReload();
			$mailing = $this->xml->getElementById($mailingId);
			$mailing->setAttribute(self::FIELD_NAME_PROGRESS, ++$count);
			$this->xml->saveAndUnlock();

		}

		// mark mailing as sent
		$this->xml->lockAndReload();
		$mailing = $this->xml->getElementById($mailingId);
		$mailing->setAttribute(self::FIELD_NAME_STATUS, self::MAILING_STATUS_SENT);
		$mailing->removeAttribute(self::FIELD_NAME_PROGRESS);
		$mailing->setAttribute(self::FIELD_NAME_DATE, date('Y-m-d H:i:s'));
		$mailing->setAttribute(self::FIELD_NAME_RECEIVERS, count($receivers));
		/** @var HyphaDomElement $mailings */
		$mailings = $this->getDoc()->getOrCreate(self::FIELD_NAME_MAILINGS_CONTAINER);
		$mailings->append($mailing);
		$this->xml->saveAndUnlock();

		// goto view page with notification
		notify('success', __('ml-successfully-sent'));
		return 'reload';
	}

	protected function mailingSendTestAction(HyphaRequest $request) {
		if (!$this->hasSender()) {
			notify('error', __('ml-no-sender'));
			return null;
		}

		$mailingId = $request->getArg(1);

		// check if given id was correct
		/** @var HyphaDomElement $mailing */
		$mailing = $this->xml->getElementById($mailingId);
		if (!$mailing instanceof HyphaDomElement) {
			return '404';
		}

		$email = $request->getPostValue('argument');
		$code = 'this-is-your-unsubscribe-code';
		$linkToMailing = $this->path(self::PATH_MAILS_VIEW_ID, ['id' => $mailingId]);
		$linkToUnsubscribe = $this->path(self::PATH_UNSUBSCRIBE_CODE, ['address' => $email, 'code' => $code]);
		$message = '<p><a href="' . $linkToMailing . '">' . __('ml-if-unreadable-use-link') . '</a></p>';
		$message .= $mailing->getHtml();
		$message .= '<p><a href="' . $linkToUnsubscribe . '">' . __('ml-unsubscribe') . '</a></p>';

		$senderData = $this->getSenderData();

		$subject = __('ml-test-mail-subject-prefix') . ' - ';
		$subject .= $mailing->getAttribute(self::FIELD_NAME_SUBJECT);

		$this->sendMail($subject, $message, [$email], $senderData['email'], $senderData['name']);

		// goto view page with notification
		notify('success', __('ml-successfully-sent'));
		return 'reload';
	}

	protected function mailingProgressAction(HyphaRequest $request) {
		$mailingId = $request->getArg(1);

		// check if given id was correct
		/** @var HyphaDomElement $mailing */
		$mailing = $this->xml->getElementById($mailingId);
		if (!$mailing instanceof HyphaDomElement) {
			return '404';
		}
		$status = $mailing->getAttribute(self::FIELD_NAME_STATUS);
		$progress = $mailing->getAttribute(self::FIELD_NAME_PROGRESS);
		$total = $this->getNumberOfAddresses();
		$result = [ self::FIELD_NAME_PROGRESS => $progress, self::FIELD_NAME_TOTAL => $total, self::FIELD_NAME_STATUS => $status];
		header('Content-Type: application/json');
		echo json_encode($result);
		exit; // since this is a data only endpoint no further html needs to be generated
	}

	/**
	 * @return bool
	 */
	protected function hasSender() {
		$senderData = $this->getSenderData();

		return '' != $senderData['name'] && '' != $senderData['email'];
	}

	/**
	 * @return array
	 */
	protected function getSenderData() {
		$senderData = [
			'name' => $this->getDoc()->getAttribute('sender-name'),
			'email' => $this->getDoc()->getAttribute('sender-email'),
		];
		if ('' == $senderData['name']) {
			$senderData['name'] = hypha_getTitle();
		}
		if ('' == $senderData['email']) {
			$senderData['email'] = hypha_getEmail();
		}

		return $senderData;
	}

	/**
	 * @param int $length
	 * @return string
	 */
	protected function constructCode($length = 16) {
		try {
			if (function_exists('random_bytes')) {
				return bin2hex(random_bytes($length));
			}
			if (function_exists('mcrypt_create_iv')) {
				return bin2hex(mcrypt_create_iv($length, MCRYPT_DEV_URANDOM));
			}
		} catch (Exception $e) {
		}

		return bin2hex(openssl_random_pseudo_bytes($length));
	}

	protected function savePage($pagename = null, $language = null, $privateFlag = null) {
		$updateHyphaXml = false;
		foreach (['pagename', 'language', 'privateFlag'] as $argument) {
			if (null === $$argument) {
				$$argument = $this->{$argument};
			} elseif ($$argument !== $this->{$argument}) {
				$updateHyphaXml = true;
			}
		}
		if ($updateHyphaXml) {
			global $hyphaXml;
			$hyphaXml->lockAndReload();
			// After reloading, our page list node might
			// have changed, so find it in the newly loaded
			// XML. This seems a bit dodgy, though...
			$this->replacePageListNode(hypha_getPage($this->language, $this->pagename));
			hypha_setPage($this->pageListNode, $language, $pagename, $privateFlag);
			$hyphaXml->saveAndUnlock();
		}
	}

	/**
	 * Creates archive index table and appends it to the given container.
	 *
	 * @param NodeList $container
	 */
	protected function appendMailingsList(NodeList $container) {
		/** @var HyphaDomElement $mailingsContainer */
		$mailingsContainer = $this->getDoc()->get(self::FIELD_NAME_MAILINGS_CONTAINER);
		if (isUser()) {
			$mailings = $mailingsContainer->children();
		} else {
			$xpath = './/' . self::FIELD_NAME_MAILING . '[@' . self::FIELD_NAME_STATUS . '="' . self::MAILING_STATUS_SENT . '"]';
			$mailings = $mailingsContainer->findXPath($xpath);
		}
		$mailings = array_reverse($mailings->toArray());

		$table = new HTMLTable();
		$container->appendChild($table);

		$table->addClass('section');

		$header = $table->addHeaderRow();
		$header->addCell();
		$header->addCell(__('subject'));
		$header->addCell(__('status'));
		$header->addCell();
		$header->addCell()->setHtml(isUser() ? $this->makeActionButton(__('add-mailing'), self::PATH_MAILS_NEW) : '');
		foreach ($mailings as $mailing) {
			$status = $mailing->getAttribute(self::FIELD_NAME_STATUS);
			$date = $mailing->getAttribute(self::FIELD_NAME_DATE);
			try {
				$date = $date ? (new DateTime($date))->format(__('ml-date-format')) : '';
			} catch (Exception $e) {
				$date = '';
			}
			$viewPath = $this->substituteSpecial(self::PATH_MAILS_VIEW_ID, ['id' => $mailing->getId()]);
			$buttons = $this->makeActionButton(__('view'), $viewPath);
			if (self::MAILING_STATUS_DRAFT == $status) {
				$editPath = $this->substituteSpecial(self::PATH_MAILS_EDIT_ID, ['id' => $mailing->getId()]);
				$buttons .= $this->makeActionButton(__('edit'), $editPath);
			}

			$row = $table->addRow();
			$row->addCell()->setHtml('<span style="color:#b90;">∗</span>');
			$row->addCell($mailing->getAttribute(self::FIELD_NAME_SUBJECT));
			$row->addCell(__('ml-mailing-status-' . $status));
			$row->addCell($date);
			$row->addCell()->setHtml($buttons);
		}
	}

	/**
	 * Find addresses by xpath.
	 *
	 * @param string $xpath
	 * @return NodeList
	 */
	protected function findAddresses($xpath) {
		/** @var HyphaDomElement $addressesContainer */
		$addressesContainer = $this->getDoc()->get(self::FIELD_NAME_ADDRESSES_CONTAINER);

		return $addressesContainer->findXPath($xpath);
	}

	/**
	 * Construct a full path with the pagename as path prefix.
	 *
	 * The given vars will be substituted.
	 *
	 * @param string $path The path, within this datatype.
	 * @param array $vars An associative array with variables to substitute.
	 * @return string
	 */
	protected function path($path = null, $vars = []) {
		$path = $path ? '/' . $this->substituteSpecial($path, $vars) : '';
		return $this->constructFullPath($this->pagename . $path);
	}

	protected function substituteSpecial($string, $vars) {
		foreach ($vars as $key => &$val) $val = htmlspecialchars($val);
		return hypha_substitute($string, $vars);
	}

	/**
	 * @todo [LRM]: move so it can be used throughout Hypha
	 * @param string $label
	 * @param null|string $path
	 * @param null|string $command
	 * @param null|string $argument
	 * @return string
	 */
	protected function makeActionButton($label, $path = null, $command = null, $argument = null) {
		$path = $this->language . '/' . $this->pagename . ($path ? '/' . $path : '');
		$_action = makeAction($path, ($command ? $command : ''), ($argument ? $argument : ''));

		return makeButton($label, $_action);
	}

	/**
	 * @todo [LRM]: move so it can be used throughout Hypha
	 * @param string $path
	 * @param null|string $language
	 * @return string
	 */
	protected function constructFullPath($path, $language = null) {
		global $hyphaUrl;
		$language = null == $language ? $this->language : $language;
		$path = '' == $path ? '' : '/' . $path;

		return $hyphaUrl . $language . $path;
	}

	/**
	 * @param string $subject
	 * @param string $message
	 * @param array $receivers
	 * @param null|string $senderEmail
	 * @param null|string $senderName
	 */
	protected function sendMail($subject, $message, array $receivers, $senderEmail = null, $senderName = null) {
		$style = 'body {margin-top:10px; margin-left: 10px; font-size:10pt; font-family: Sans; color:black; background-color:white;}';
		foreach ($receivers as $receiver) {
			sendMail($receiver, $subject, $message, $senderEmail, $senderName, $style);
			usleep(rand(500000, 2000000));
		}
	}
}
