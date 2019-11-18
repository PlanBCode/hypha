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

class mailinglist extends Page {
	/** @var Xml */
	private $xml;

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
	const FIELD_NAME_ID = 'id';
	const FIELD_NAME_ADDRESS = 'address';
	const FIELD_NAME_STATUS = 'status';
	const FIELD_NAME_CONFIRM_CODE = 'confirm-code';
	const FIELD_NAME_UNSUBSCRIBE_CODE = 'unsubscribe-code';
	const FIELD_NAME_UNSUBSCRIBE_EMAIL = 'unsubscribe-email';

	const PATH_EDIT = 'edit';
	const PATH_MAILS = 'mails';
	const PATH_NEW = 'new';
	const PATH_MAILS_NEW = 'mails/new';
	const PATH_MAILS_VIEW = 'mails/{id}';
	const PATH_MAILS_EDIT = 'mails/{id}/edit';
	const PATH_ADDRESSES = 'addresses';
	const PATH_CONFIRM = 'confirm';
	const PATH_CONFIRM_CODE = 'confirm?code={code}';
	const PATH_UNSUBSCRIBE = 'unsubscribe';
	const PATH_UNSUBSCRIBE_CODE = 'unsubscribe?address={address}&code={code}';

	const CMD_DELETE = 'delete';
	const CMD_SUBSCRIBE = 'subscribe';
	const CMD_EDIT = 'edit';
	const CMD_SAVE = 'save';
	const CMD_SEND = 'send';
	const CMD_SEND_TEST = 'test_send';

	const MAILING_STATUS_DRAFT = 'draft';
	const MAILING_STATUS_SENDING = 'sending';
	const MAILING_STATUS_SENT = 'sent';

	const ADDRESS_STATUS_PENDING = 'pending';
	const ADDRESS_STATUS_CONFIRMED = 'confirmed';
	const ADDRESS_STATUS_UNSUBSCRIBED = 'unsubscribed';

	/**
	 * @param DOMElement $pageListNode
	 * @param RequestContext $O_O
	 */
	public function __construct(DOMElement $pageListNode, RequestContext $O_O) {
		parent::__construct($pageListNode, $O_O);
		$this->xml = new Xml(get_called_class(), Xml::multiLingualOn, Xml::versionsOff);
		$this->xml->loadFromFile('data/pages/' . $pageListNode->getAttribute('id'));
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
			case [null,                   null]:                return $this->indexView($request);
			case [null,                   self::CMD_DELETE]:    return $this->deleteAction($request);
			case [null,                   self::CMD_SUBSCRIBE]: return $this->subscribeAction($request);
			case [self::PATH_EDIT,        null]:                return $this->editView($request);
			case [self::PATH_EDIT,        self::CMD_SAVE]:      return $this->editAction($request);
			case [self::PATH_ADDRESSES,   null]:                return $this->addressesView($request);
			case [self::PATH_CONFIRM,     null]:                return $this->confirmEmailAction($request);
			case [self::PATH_UNSUBSCRIBE, null]:                return $this->unsubscribeAction($request);
			case [self::PATH_MAILS,       null]:                break;
		}

		if ($request->getView() === self::PATH_MAILS) {
			$firstArg = $request->getArg(1);
			$secondArg = $request->getArg(2);
			switch ([$firstArg, $secondArg, $request->getCommand()]) {
				case [self::PATH_NEW, null,            null]:                return $this->mailingNewView($request);
				case [self::PATH_NEW, null,            self::CMD_SAVE]:      return $this->mailingNewAction($request);
				case [$firstArg,      null,            null]:                return $this->mailingView($request);
				case [$firstArg,      null,            self::CMD_SEND]:      return $this->mailingSendAction($request);
				case [$firstArg,      null,            self::CMD_SEND_TEST]: return $this->mailingSendTestAction($request);
				case [$firstArg,      self::PATH_EDIT, null]:                return $this->mailingEditView($request);
				case [$firstArg,      self::PATH_EDIT, self::CMD_SAVE]:      return $this->mailingEditAction($request);
			}
		}

		return '404';
	}

	/**
	 * Checks if the status is new and if so builds the structure and sets the status to draft.
	 */
	private function ensureStructure() {
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
		$build($this->xml->documentElement, $dataStructure);

		// set initial status, create timestamp and title
		$this->xml->documentElement->setAttribute(self::FIELD_NAME_SENDER_NAME, '');
		$this->xml->documentElement->setAttribute(self::FIELD_NAME_SENDER_EMAIL, '');
		$this->xml->saveAndUnlock();
	}

	/**
	 * @param HyphaRequest $request
	 * @return string|null
	 */
	public function indexView(HyphaRequest $request) {
		// create form
		$form = $this->createSubscribeForm();

		return $this->indexViewRender($request, $form);
	}

	private function indexViewRender(HyphaRequest $request, WymHTMLForm $form) {
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

		/** @var DOMWrap\NodeList $main */
		$main = $this->html->find('#main');

		// display page name and description
		$description = $this->xml->find(self::FIELD_NAME_DESCRIPTION)->getHtml();
		$main->append($description);

		// display form
		$viewAddresses = isUser() ? '<span style="float: right;">' . $this->makeActionButton(__('ml-view-addresses'), self::PATH_ADDRESSES) . '</span>' : '';
		$main->append('<div><h3>' . __('subscribe') . $viewAddresses . '</h3></div>');

		// update the form dom so that values and errors can be displayed
		$form->updateDom();

		/** @var DOMWrap\NodeList $main */
		$main = $this->html->find('#main');
		$main->append($form);

		$formContainer = $main->children()->end();
		$formContainer->append($this->makeActionButton(__('subscribe'), null, self::CMD_SUBSCRIBE));

		// display archive (non-users only get to see sent items)
		$main->append('<div><h3>' . __('archive') . '</h3></div>');
		$this->appendMailingsList($main);

		return null;
	}

	/**
	 * Deletes the mailing list.
	 *
	 * @param HyphaRequest $request
	 * @return array
	 */
	public function deleteAction(HyphaRequest $request) {
		if (!isAdmin()) {
			return ['errors' => [__('insufficient-rights-to-perform-action')]];
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
	public function editView(HyphaRequest $request) {
		if (!isUser()) {
			notify('error', __('login-to-perform-action'));

			return ['redirect', $this->constructFullPath($this->pagename)];
		}

		// create form
		$senderData = $this->getSenderData();
		$formData = [
			self::FIELD_NAME_PAGE_NAME => showPagename($this->pagename),
			self::FIELD_NAME_PRIVATE => $this->privateFlag,
			self::FIELD_NAME_SENDER_EMAIL => $senderData['email'],
			self::FIELD_NAME_SENDER_NAME => $senderData['name'],
			self::FIELD_NAME_DESCRIPTION => $this->xml->find(self::FIELD_NAME_DESCRIPTION)->getHtml(),
			self::FIELD_NAME_EMAIL_WELCOME_TEXT => $this->xml->find(self::FIELD_NAME_EMAIL_WELCOME_TEXT)->getHtml(),
		];

		$form = $this->createEditForm($formData);

		return $this->editViewRender($request, $form);
	}

	private function editViewRender(HyphaRequest $request, HTMLForm $form) {
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
	public function editAction(HyphaRequest $request) {
		if (!isUser()) {
			notify('error', __('login-to-perform-action'));

			return ['redirect', $this->constructFullPath($this->pagename)];
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
		$this->xml->documentElement->setAttribute('sender-email', $senderEmail);
		$this->xml->documentElement->setAttribute('sender-name', $senderName);
		/** @var HyphaDomElement $description */
		$this->xml->find(self::FIELD_NAME_DESCRIPTION)->setHtml($form->dataFor(self::FIELD_NAME_DESCRIPTION), true);
		$this->xml->find(self::FIELD_NAME_EMAIL_WELCOME_TEXT)->setHtml($form->dataFor(self::FIELD_NAME_EMAIL_WELCOME_TEXT), true);

		$this->xml->saveAndUnlock();

		notify('success', ucfirst(__('ml-successfully-updated')));

		return ['redirect', $this->constructFullPath($pagename)];
	}

	/**
	 * @param HyphaRequest $request
	 * @return string|null
	 */
	public function subscribeAction(HyphaRequest $request) {
		// create form
		$form = $this->createSubscribeForm($request->getPostData());

		// validate form
		$form->validateRequiredField(self::FIELD_NAME_EMAIL);
		$form->validateEmailField(self::FIELD_NAME_EMAIL);

		// process form if there are no errors
		if (!empty($form->errors)) {
			return $this->indexViewRender($request, $form);
		}

		// check if email is already in the list, no need to have it twice.
		$this->xml->lockAndReload();
		$email = strtolower($form->dataFor(self::FIELD_NAME_EMAIL));
		/** @var HyphaDomElement $addresses */
		$addresses = $this->xml->find(self::FIELD_NAME_ADDRESSES_CONTAINER);
		/** @var NodeList $addressCollection */
		$xpath = '//' . self::FIELD_NAME_ADDRESS . '[@' . self::FIELD_NAME_EMAIL . '="' . $email . '"]';
		$addressCollection = $addresses->children()->findXPath($xpath);
		$address = $addressCollection->first();

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
			$addresses->append($address);
			$this->xml->saveAndUnlock();
		} else {
			$this->xml->unlock();
		}

		if ($sendEmail) {
			// send email so that pending subscribed can confirm email address
			$confirmPath = str_replace('{code}', $code, self::PATH_CONFIRM_CODE);
			$confirmUrl = $this->constructFullPath($this->pagename . '/' . $confirmPath);
			$confirmLink = '<a href="' . $confirmUrl . '">' . __('ml-please-confirm-email') . '</a>';
			$welcomeText = $this->xml->find(self::FIELD_NAME_EMAIL_WELCOME_TEXT)->getHtml();
			$welcomeText .= '<br><br>' . $confirmLink;
			$subject = hypha_getTitle() . ' - ' . __('ml-confirmation-email-subject');
			$this->sendMail($subject, $welcomeText, [$email]);

			notify('success', ucfirst(__('ml-confirmation-mail-sent')));
		} else {
			notify('success', ucfirst(__('ml-successfully-subscribed')));
		}

		// all is success refresh page with success notification
		return 'reload';
	}

	private function confirmEmailAction(HyphaRequest $request) {
		$code = isset($_GET['code']) ? $_GET['code'] : null;
		if (null == $code) {
			notify('error', __('missing-arguments'));
			return null;
		}

		$this->xml->lockAndReload();

		/** @var NodeList $addressCollection */
		$xpath = '//' . self::FIELD_NAME_ADDRESS . '[@' . self::FIELD_NAME_CONFIRM_CODE . '="' . $code . '" and @' . self::FIELD_NAME_STATUS . ' != "' . self::ADDRESS_STATUS_UNSUBSCRIBED . '"]';
		$addressCollection = $this->xml->findXPath($xpath);
		$address = $addressCollection->first();
		if (!$address instanceof HyphaDomElement) {
			$this->xml->unlock();
			notify('error', __('invalid-code'));
			return null;
		}

		if ($address->getAttribute(self::FIELD_NAME_STATUS) == self::ADDRESS_STATUS_CONFIRMED) {
			$this->xml->unlock();
			notify('success', ucfirst(__('ml-successfully-subscribed')));
			return ['redirect', $this->constructFullPath($this->pagename)];
		}

		$address->setAttribute(self::FIELD_NAME_STATUS, self::ADDRESS_STATUS_CONFIRMED);
		$address->setAttribute(self::FIELD_NAME_UNSUBSCRIBE_CODE, $this->constructCode());
		/** @var HyphaDomElement $addresses */
		$addresses = $this->xml->find(self::FIELD_NAME_ADDRESSES_CONTAINER);
		$addresses->append($address);
		$this->xml->saveAndUnlock();

		notify('success', ucfirst(__('ml-successfully-subscribed')));
		return ['redirect', $this->constructFullPath($this->pagename)];
	}

	private function unsubscribeAction(HyphaRequest $request) {
		$code = isset($_GET['code']) ? $_GET['code'] : null;
		if (null == $code) {
			notify('error', __('missing-arguments'));
			return null;
		}

		$this->xml->lockAndReload();

		/** @var NodeList $addressCollection */
		$xpath = '//' . self::FIELD_NAME_ADDRESS . '[@' . self::FIELD_NAME_UNSUBSCRIBE_CODE . '=' . xpath_encode($code) . ']';
		$addressCollection = $this->xml->findXPath($xpath);
		$address = $addressCollection->first();
		if (!$address instanceof HyphaDomElement) {
			$this->xml->unlock();
			notify('error', __('invalid-code'));
			return null;
		}

		if ($address->getAttribute(self::FIELD_NAME_STATUS) == self::ADDRESS_STATUS_PENDING) {
			// weird case, should never happen, let's handle it anyway
			$this->xml->unlock();
			notify('error', __('ml-mail-has-invalid-status'));
			return null;
		}

		if ($address->getAttribute(self::FIELD_NAME_STATUS) != self::ADDRESS_STATUS_UNSUBSCRIBED) {
			$address->setAttribute(self::FIELD_NAME_STATUS, self::ADDRESS_STATUS_UNSUBSCRIBED);
			$address->setAttribute(self::FIELD_NAME_UNSUBSCRIBE_EMAIL, $address->getAttribute(self::FIELD_NAME_EMAIL));
			$address->removeAttribute(self::FIELD_NAME_EMAIL);
			$this->xml->saveAndUnlock();
		} else {
			$this->xml->unlock();
		}

		notify('success', ucfirst(__('ml-successfully-unsubscribed')));
		return ['redirect', $this->constructFullPath($this->pagename)];
	}

	/**
	 * Builds a table with addresses.
	 * @param HyphaRequest $request
	 * @return array|null
	 */
	private function addressesView(HyphaRequest $request) {
		if (!isUser()) {
			notify('error', __('login-to-perform-action'));
			return ['redirect', $this->constructFullPath($this->pagename)];
		}

		$table = new HTMLTable();
		/** @var HyphaDomElement $main */
		$main = $this->html->find('#main');
		$main->appendChild($table);
		$table->addClass('section');
		$table->addHeaderRow()->addCells(['', __('addresses'), __('status')]);
		/** @var HyphaDomElement[] $addresses */
		$xpath = '//' . self::FIELD_NAME_ADDRESS . '[@' . self::FIELD_NAME_STATUS . '="' . self::ADDRESS_STATUS_CONFIRMED . '" or @' . self::FIELD_NAME_STATUS . '="' . self::ADDRESS_STATUS_PENDING . '"]';
		$addresses = $this->xml->findXPath($xpath);
		foreach ($addresses as $child) {
			$status = $child->getAttribute(self::FIELD_NAME_STATUS);
			$table->addRow()->addCells(['', $child->getAttribute(self::FIELD_NAME_EMAIL), __('ml-address-status-' . $status)]);
		}
		$table->addRow()->addCells([__('total'), count($addresses), '']);

		// add buttons
		/** @var HyphaDomElement $commands */
		$commands = $this->html->find('#pageCommands');
		$commands->append($this->makeActionButton(__('back')));
		return null;
	}

	/**
	 * @param HyphaRequest $request
	 *
	 * @return null
	 * @throws Exception
	 */
	private function mailingView(HyphaRequest $request) {
		$mailingId = $request->getArg(1);

		// check if given id was correct
		/** @var HyphaDomElement $mailing */
		$mailing = $this->xml->document()->getElementById($mailingId);
		if (!$mailing instanceof HyphaDomElement) {
			notify('error', __('not-found')); // 404
			return null;
		}

		// return false if status is draft without client logged in
		if (self::MAILING_STATUS_DRAFT == $mailing->getAttribute('status') && !isUser()) {
			notify('error', __('not-found')); // 404
			return null;
		}

		// display mailing
		/** @var HyphaDomElement $main */
		$main = $this->html->find('#main');
		$main->append('<div><h2>' . $mailing->getAttribute('subject') . '</h2></div>');
		$date = $mailing->getAttribute('date');
		if ($date) {
			$date = new DateTime($date);
			$main->append('<div>' . __('date') . ': ' . $date->format(__('ml-date-format')) . '</h2></div>');
		}
		if (isUser()) {
			$receivers = $mailing->getAttribute('receivers');
			if (null != $receivers) {
				$main->append('<div>' . __('ml-received-by') . ': ' . $receivers . '</div>');
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
			$path = str_replace('{id}', $mailing->getId(), self::PATH_MAILS_EDIT);
			$commands->append($this->makeActionButton(__('edit'), $path));
			$xpath = '//' . self::FIELD_NAME_ADDRESS . '[@' . self::FIELD_NAME_STATUS . '="' . self::ADDRESS_STATUS_CONFIRMED . '"]';
			$num = count($this->xml->findXPath($xpath));

			$path = str_replace('{id}', $mailing->getId(), self::PATH_MAILS_VIEW);
			$path = $this->language . '/' . $this->pagename . '/' . $path;
			$commands->append(makeButton(__('send'), 'if(confirm(\'' . __('ml-sure-to-send', array("count" => $num)) . '\'))' . makeAction($path, self::CMD_SEND, '')));
			$commands->append(makeButton(__('ml-test-send'), 'hypha(\''.$path.'\', \''.self::CMD_SEND_TEST.'\', prompt(\'' . __('email') . '\'), $(this).closest(\'form\'));'));
		}

		return null;
	}

	/**
	 * @param HyphaRequest $request
	 * @return array|null
	 */
	public function mailingNewView(HyphaRequest $request) {
		if (!isUser()) {
			notify('error', __('login-to-perform-action'));
			return null;
		}

		// create form
		$form = $this->createMailingForm();

		return $this->mailingFormViewRender($form, '', self::PATH_MAILS_NEW);
	}

	private function mailingFormViewRender(WymHTMLForm $form, $cancelPath, $submitPath) {
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
	 * @return array|null
	 */
	public function mailingNewAction(HyphaRequest $request) {
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
		/** @var NodeList $mailings */
		$mailings = $this->xml->find(self::FIELD_NAME_MAILINGS_CONTAINER);

		/** @var HyphaDomElement $mailing */
		$mailing = $this->xml->createElement(self::FIELD_NAME_MAILING);
		$mailings->append($mailing);

		$mailing->generateId();
		$mailing->setAttr(self::FIELD_NAME_STATUS, self::MAILING_STATUS_DRAFT);
		$mailing->setAttr(self::FIELD_NAME_SUBJECT, $form->dataFor(self::FIELD_NAME_SUBJECT));
		$mailing->setHtml(wikify_html($form->dataFor(self::FIELD_NAME_MESSAGE)));

		$this->xml->saveAndUnlock();

		notify('success', ucfirst(__('ml-successfully-created')));
		$path = str_replace('{id}', $mailing->getId(), self::PATH_MAILS_EDIT);

		return ['redirect', $this->constructFullPath($this->pagename . '/' . $path)];
	}

	/**
	 * @param HyphaRequest $request
	 * @return array|null
	 */
	private function mailingEditView(HyphaRequest $request) {
		if (!isUser()) {
			notify('error', __('login-to-perform-action'));

			return null;
		}

		$mailingId = $request->getArg(1);

		// check given id
		/** @var HyphaDomElement $mailing */
		$mailing = $this->xml->document()->getElementById($mailingId);
		if (!$mailing instanceof HyphaDomElement) {
			notify('error', __('not-found')); // 404
			return null;
		}

		if (self::MAILING_STATUS_DRAFT != $mailing->getAttribute('status')) {
			notify('error', __('unable-to-edit'));
			return null;
		}

		// create form
		$formData = [
			self::FIELD_NAME_ID => $mailingId,
			self::FIELD_NAME_SUBJECT => $mailing->getAttribute('subject'),
			self::FIELD_NAME_MESSAGE => $mailing->getHtml(),
		];
		$form = $this->createMailingForm($formData);

		$cancelPath = str_replace('{id}', $mailingId, self::PATH_MAILS_VIEW);
		$submitPath = str_replace('{id}', $mailingId, self::PATH_MAILS_EDIT);

		return $this->mailingFormViewRender($form, $cancelPath, $submitPath);
	}

	/**
	 * no user
	 * check mailing id
	 * check mailing
	 * check draft status
	 *
	 * @param HyphaRequest $request
	 *
	 * @return array|null
	 */
	private function mailingEditAction(HyphaRequest $request) {
		// throw error if edit is requested without client logged in
		if (!isUser()) {
			notify('error', __('login-to-edit'));
			return null;
		}

		$mailingId = $request->getArg(1);

		/** @var HyphaDomElement $mailing */
		$mailing = $this->xml->document()->getElementById($mailingId);
		if (!$mailing instanceof HyphaDomElement) {
			notify('error', __('not-found')); // 404
			return null;
		}

		$postedId = $request->getPostValue(self::FIELD_NAME_ID);
		if ($postedId != $mailingId) {
			notify('error', __('ml-inconsistent-parameters'));
			return null;
		}

		// check if status is correct
		$status = $mailing->getAttribute('status');
		if (self::MAILING_STATUS_SENT == $status) {
			notify('success', __('ml-successfully-sent'));
			return ['redirect', $this->constructFullPath($this->pagename . '/' . $mailingId)];
		}
		if (self::MAILING_STATUS_DRAFT != $status) {
			notify('error', __('unable-to-edit'));
			return null;
		}

		// create form
		$form = $this->createMailingForm($request->getPostData());

		// process form if it was posted
		$form->validateRequiredField(self::FIELD_NAME_SUBJECT);
		$form->validateRequiredField(self::FIELD_NAME_MESSAGE);
		if (!empty($form->errors)) {
			$cancelPath = str_replace('{id}', $mailingId, self::PATH_MAILS_VIEW);
			$submitPath = str_replace('{id}', $mailingId, self::PATH_MAILS_EDIT);
			return $this->mailingFormViewRender($form, $cancelPath, $submitPath);
		}

		$this->xml->lockAndReload();

		/** @var HyphaDomElement $mailing */
		$mailing = $this->xml->document()->getElementById($mailingId);
		$mailing->setAttr(self::FIELD_NAME_SUBJECT, $form->dataFor(self::FIELD_NAME_SUBJECT));
		$mailing->setHtml(wikify_html($form->dataFor(self::FIELD_NAME_MESSAGE)));

		$this->xml->saveAndUnlock();

		// goto view page with notification
		notify('success', __('ml-successfully-created'));
		return ['redirect', $this->constructFullPath($this->pagename . '/' . $mailingId)];
	}

	/**
	 * Creates a HTML form object for the subscribers.
	 *
	 * @param array $values
	 * @return WymHTMLForm
	 */
	private function createSubscribeForm(array $values = []) {
		$html = <<<EOF
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<label for="[[emailFieldName]]">[[email]]</label>: <input type="text" id="[[emailFieldName]]" name="[[emailFieldName]]" placeholder="[[email]]" />
			</div>
EOF;

		$vars = [
			'email' => __('email'),
			'emailFieldName' => self::FIELD_NAME_EMAIL,
		];

		$html = hypha_substitute($html, $vars);

		return new WymHTMLForm($html, $values);
	}

	/**
	 * @param array $values
	 *
	 * @return WymHTMLForm
	 */
	private function createEditForm(array $values = []) {
		$html = <<<EOF
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="[[pageNameFieldName]]">[[title]]</label></strong> <input type="text" id="[[pageNameFieldName]]" name="[[pageNameFieldName]]" />
				<strong><label for="[[senderNameFieldNameFieldName]]"> [[senderName]] </label></strong><input type="text" id="[[senderNameFieldName]]" name="[[senderNameFieldName]]" />
				<strong><label for="[[senderEmailFieldName]]"> [[senderEmail]] </label></strong><input type="text" id="[[senderEmailFieldName]]" name="[[senderEmailFieldName]]" />
				<strong><label for="[[privateFieldName]]"> [[privatePage]] </label></strong><input type="checkbox" name="[[privateFieldName]]" id="[[privateFieldName]]" />
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="[[descriptionFieldName]]"> [[description]] </label></strong><editor name="[[descriptionFieldName]]"></editor>
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="[[emailWelcomeTextFieldName]]"> [[emailWelcomeText]] </label></strong><editor name="[[emailWelcomeTextFieldName]]"></editor>
			</div>
EOF;

		$vars = [
			'title' => __('title'),
			'pageNameFieldName' => self::FIELD_NAME_PAGE_NAME,
			'senderName' => __('mailing-sender-name'),
			'senderNameFieldName' => self::FIELD_NAME_SENDER_NAME,
			'senderEmail' => __('mailing-sender-email'),
			'senderEmailFieldName' => self::FIELD_NAME_SENDER_EMAIL,
			'privatePage' => __('private-page'),
			'privateFieldName' => self::FIELD_NAME_PRIVATE,
			'description' => __('description'),
			'descriptionFieldName' => self::FIELD_NAME_DESCRIPTION,
			'emailWelcomeText' => __('email-welcome-text'),
			'emailWelcomeTextFieldName' => self::FIELD_NAME_EMAIL_WELCOME_TEXT,
		];

		$html = hypha_substitute($html, $vars);

		return new WymHTMLForm($html, $values);
	}

	/**
	 * @param array $data
	 *
	 * @return WymHTMLForm
	 */
	private function createMailingForm(array $data = []) {
		$html = <<<EOF
			<input type="hidden" id="[[field-name-id]]" name="[[field-name-id]]" />
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<field-name-labelsubjectFieldName]]"> [[subject]] </field-name-label> <input type="text" id="[[subjectFieldName]]" name="[[subjectFieldName]]" />
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="[[field-name-message]]"> [[message]] </label></strong><editor id="[[field-name-message]]" name="[[field-name-message]]"></editor>
			</div>
EOF;

		$vars = [
			'field-name-id' => self::FIELD_NAME_ID,
			'subject' => __('subject'),
			'field-name-subject' => self::FIELD_NAME_SUBJECT,
			'message' => __('message'),
			'field-name-message' => self::FIELD_NAME_MESSAGE,
		];

		$html = hypha_substitute($html, $vars);

		return new WymHTMLForm($html, $data);
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
	 *
	 * @throws Exception
	 * @return null
	 */
	private function mailingSendAction(HyphaRequest $request) {
		if (!$this->hasSender()) {
			notify('error', __('login-to-edit'));
			return null;
		}

		$mailingId = $request->getArg(1);

		// check if given id was correct
		/** @var HyphaDomElement $mailing */
		$mailing = $this->xml->document()->getElementById($mailingId);
		if (!$mailing instanceof HyphaDomElement) {
			notify('error', __('not-found')); // 404
			return null;
		}

		// get status
		$status = $mailing->getAttribute('status');
		if (self::MAILING_STATUS_DRAFT !== $status) {
			notify('error', __('ml-mail-has-invalid-status'));
			return null;
		}

		$senderName = $this->xml->documentElement->getAttribute('sender-name');
		$senderEmail = $this->xml->documentElement->getAttribute('sender-email');

		// mark mailing as sending
		$this->xml->lockAndReload();
		$mailing->setAttribute('status', self::MAILING_STATUS_SENDING);
		/** @var HyphaDomElement $mailings */
		$mailings = $this->xml->documentElement->getOrCreate('mailings');
		$mailings->append($mailing);
		$this->xml->saveAndUnlock();

		// iterate over the confirmed addresses and send the mailing
		/** @var HyphaDomElement[] $receivers */
		$xpath = '//' . self::FIELD_NAME_ADDRESS . '[@' . self::FIELD_NAME_STATUS . '="' . self::ADDRESS_STATUS_CONFIRMED . '"]';
		$receivers = $this->xml->findXPath($xpath);
		$linkToMailing = $this->constructFullPath($this->pagename . '/' . $mailingId);
		foreach ($receivers as $receiver) {
			$email = $receiver->getAttribute('email');
			$code = $receiver->getAttribute('unsubscribe-code');
			$linkToUnsubscribe = $this->constructFullPath($this->pagename . '/' . str_replace(['{address}', '{code}'], [$receiver->getId(), $code], self::PATH_UNSUBSCRIBE_CODE));
			$message = '<p><a href="' . $linkToMailing . '">' . __('ml-if-unreadable-use-link') . '</a></p>';
			$message .= $mailing->getHtml();
			$message .= '<p><a href="' . $linkToUnsubscribe . '">' . __('ml-unsubscribe') . '</a></p>';
			$this->sendMail($mailing->getAttribute('subject'), $message, [$email], $senderEmail, $senderName);
		}

		// mark mailing as sent
		$this->xml->lockAndReload();
		$mailing = $this->xml->document()->getElementById($mailingId);
		$mailing->setAttribute(self::FIELD_NAME_STATUS, self::MAILING_STATUS_SENT);
		$mailing->setAttribute('date', date('Y-m-d H:i:s'));
		$mailing->setAttribute('receivers', count($receivers));
		/** @var HyphaDomElement $mailings */
		$mailings = $this->xml->documentElement->getOrCreate('mailings');
		$mailings->append($mailing);
		$this->xml->saveAndUnlock();

		// goto view page with notification
		notify('success', __('ml-successfully-sent'));
		return 'reload';
	}

	private function mailingSendTestAction(HyphaRequest $request) {
		if (!$this->hasSender()) {
			notify('error', __('login-to-edit'));
			return null;
		}

		$mailingId = $request->getArg(1);

		// check if given id was correct
		/** @var HyphaDomElement $mailing */
		$mailing = $this->xml->document()->getElementById($mailingId);
		if (!$mailing instanceof HyphaDomElement) {
			notify('error', __('not-found')); // 404
			return null;
		}

		$email = $request->getPostValue('argument');
		$code = 'this-is-your-unsubscribe-code';
		$linkToMailing = $this->constructFullPath($this->pagename . '/' . $mailingId);
		$linkToUnsubscribe = $this->constructFullPath($this->pagename . '/' . str_replace(['{address}', '{code}'], [$email, $code], self::PATH_UNSUBSCRIBE_CODE));
		$message = '<p><a href="' . $linkToMailing . '">' . __('ml-if-unreadable-use-link') . '</a></p>';
		$message .= $mailing->getHtml();
		$message .= '<p><a href="' . $linkToUnsubscribe . '">' . __('ml-unsubscribe') . '</a></p>';

		$senderName = $this->xml->documentElement->getAttribute('sender-name');
		$senderEmail = $this->xml->documentElement->getAttribute('sender-email');

		$subject = __('ml-test-mail-subject-prefix') . ' - ';
		$subject .= $mailing->getAttribute('subject');

		$this->sendMail($subject, $message, [$email], $senderEmail, $senderName);

		// goto view page with notification
		notify('success', __('ml-successfully-sent'));
		return 'reload';
	}

	/**
	 * @return bool
	 */
	private function hasSender() {
		$senderData = $this->getSenderData();

		return '' != $senderData['name'] && '' != $senderData['email'];
	}

	/**
	 * @return array
	 */
	private function getSenderData() {
		$senderData = [
			'name' => $this->xml->documentElement->getAttribute('sender-name'),
			'email' => $this->xml->documentElement->getAttribute('sender-email'),
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
	 *
	 * @return string
	 */
	private function constructCode($length = 16) {
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

	/**
	 * @param string $subject
	 * @param string $message
	 * @param array $receivers
	 * @param null|string $senderEmail
	 * @param null|string $senderName
	 */
	private function sendMail($subject, $message, array $receivers, $senderEmail = null, $senderName = null) {
		$style = 'body {margin-top:10px; margin-left: 10px; font-size:10pt; font-family: Sans; color:black; background-color:white;}';
		foreach ($receivers as $receiver) {
			dump($receiver, $subject, $message);
//			sendMail($receiver, $subject, $message, $senderEmail, $senderName, $style);
			usleep(rand(500000, 2000000));
		}
	}

	private function savePage($pagename = null, $language = null, $privateFlag = null) {
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
	private function appendMailingsList(DOMWrap\NodeList $container) {
		if (isUser()) {
			/** @var HyphaDomElement $mailingsContainer */
			$mailingsContainer = $this->xml->find(self::FIELD_NAME_MAILINGS_CONTAINER);
			$mailings = $mailingsContainer->children();
		} else {
			$xpath = '//' . self::FIELD_NAME_MAILING . '[@' . self::FIELD_NAME_STATUS . '="' . self::MAILING_STATUS_SENT . '"]';
			$mailings = $this->xml->findXPath($xpath);
		}

		$table = new HTMLTable();
		$container->appendChild($table);

		$table->addClass('section');

		$header = $table->addHeaderRow();
		$header->addCell();
		$header->addCell(__('subject'));
		$header->addCell(__('status'));
		$header->addCell();
		$header->addCell()->setHtml(isUser() ? $this->makeActionButton('add-mailing', self::PATH_MAILS_NEW) : '');
		foreach ($mailings as $mailing) {
			$status = $mailing->getAttribute(self::FIELD_NAME_STATUS);
			$date = $mailing->getAttribute(self::FIELD_NAME_DATE);
			try {
				$date = $date ? (new DateTime($date))->format(__('ml-date-format')) : '';
			} catch (Exception $e) {
				$date = '';
			}
			$path = str_replace('{id}', $mailing->getId(), self::PATH_MAILS_VIEW);
			$buttons = $this->makeActionButton(__('view'), $path);
			if (self::MAILING_STATUS_DRAFT == $status) {
				$path = str_replace('{id}', $mailing->getId(), self::PATH_MAILS_EDIT);
				$buttons .= $this->makeActionButton(__('edit'), $path);
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
	 * @todo [LRM]: move so it can be used throughout Hypha
	 * @param string $label
	 * @param null|string $path
	 * @param null|string $command
	 * @param null|string $argument
	 *
	 * @return string
	 */
	private function makeActionButton($label, $path = null, $command = null, $argument = null) {
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
	private function constructFullPath($path, $language = null) {
		global $hyphaUrl;
		$language = null == $language ? $this->language : $language;
		$path = '' == $path ? '' : '/' . $path;

		return $hyphaUrl . $language . $path;
	}
}
