<?php
/*
        Module: mailinglist



	Mailinglist features.
 */
require_once __DIR__ . '/../core/WymHTMLForm.php';

use DOMWrap\NodeList;

$hyphaPageTypes[] = 'mailinglist';

/*
	Class: mailinglist
*/

class mailinglist extends Page {

	const FIELD_NAME_EMAIL = 'email';
	const FIELD_NAME_DESCRIPTION = 'description';
	const FIELD_NAME_PRIVATE = 'private';
	const FIELD_NAME_PAGE_NAME = 'page_name';
	const FIELD_NAME_SUBJECT = 'subject';
	const FIELD_NAME_SENDER_EMAIL = 'sender_email';
	const FIELD_NAME_SENDER_NAME = 'sender_name';
	const FIELD_NAME_MESSAGE = 'message';
	const FIELD_NAME_ID = 'id';

	const FORM_CMD_LIST_SUBSCRIBE = 'subscribe';
	const FORM_CMD_LIST_EDIT = 'edit';
	const FORM_CMD_LIST_DELETE = 'delete';
	const FORM_CMD_MAILING_SAVE = 'save';
	const FORM_CMD_MAILING_SEND = 'send';

	const MAILING_STATUS_DRAFT = 'draft';
	const MAILING_STATUS_SENT = 'sent';
	const MAILING_STATUS_SENDING = 'sending';

	const ADDRESS_STATUS_PENDING = 'pending';
	const ADDRESS_STATUS_CONFIRMED = 'confirmed';
	const ADDRESS_STATUS_UNSUBSCRIBED = 'unsubscribed';

	/** @var Xml */
	private $xml;

	/**
	 * @param DOMElement $pageListNode
	 * @param array $args
	 */
	public function __construct(DOMElement $pageListNode, $args) {
		parent::__construct($pageListNode, $args);
		$this->xml = new Xml('mailinglist', Xml::multiLingualOn, Xml::versionsOff);
		$this->xml->loadFromFile('data/pages/' . $pageListNode->getAttribute('id'));
	}

	/**
	 * @return HyphaDomElement|DOMElement
	 */
	private function getDoc() {
		return $this->xml->documentElement;
	}

	public function build() {
		$this->html->writeToElement('pagename', showPagename($this->pagename).' '.asterisk($this->privateFlag));

		$firstArgument = $this->getArg(0);
		if ('edit' != $firstArgument && !$this->hasSender()) {
			notify('warning', __('ml-no-sender'));
		}

		switch ($firstArgument) {
			case null:
				return $this->indexAction();
			case 'edit':
				return $this->editAction();
			case 'delete':
				return $this->deleteAction();
			case 'confirm':
				return $this->confirmAction();
			case 'unsubscribe':
				return $this->unsubscribeAction();
			case 'addresses':
				return $this->addressesAction();
			case 'create':
				return $this->createMailingAction();
			default:
				switch ($this->getArg(1)) {
					case 'edit':
						return $this->editMailingAction($firstArgument);
					default:
						return $this->showMailingAction($firstArgument);
				}
		}
	}

	public function indexAction() {
		// check if form is posted and get form data
		$formData = [];
		$formPosted = $this->isPosted(self::FORM_CMD_LIST_SUBSCRIBE);
		if ($formPosted) {
			$formData = $_POST;
		}

		// create form
		$form = $this->createSubscribeForm($formData);

		// process form if it was posted
		if ($formPosted) {
			$form->validateRequiredField(self::FIELD_NAME_EMAIL);
			$form->validateEmailField(self::FIELD_NAME_EMAIL);
			if (empty($form->errors)) {
				// check if email is already in the list, no need to have it twice.
				$this->xml->lockAndReload();
				$email = strtolower($form->dataFor(self::FIELD_NAME_EMAIL));
				/** @var HyphaDomElement $addresses */
				$addresses = $this->getDoc()->getOrCreate('addresses');
				/** @var NodeList $addressCollection */
				$addressCollection = $addresses->children()->findXPath('//address[@email="' . $email . '"]');
				$address = $addressCollection->first();

				if (!$address instanceof HyphaDomElement) {
					$addAddress = true;
					$sendEmail = true;
					$code = $this->constructCode();
				} else {
					// if the email is there just send another email if it is still pending
					$sendEmail = $address->getAttribute('status') == self::ADDRESS_STATUS_PENDING;
					$addAddress = false;
					$code = $address->getAttribute('code');
				}

				if ($addAddress) {
					/** @var HyphaDomElement $address */
					// add email with status pending
					$address = $this->xml->createElement('address');
					$address->setAttribute('email', $email);
					$address->setAttribute('status', self::ADDRESS_STATUS_PENDING);
					$address->setAttribute('confirm-code', $code);
					/** @var HyphaDomElement $addresses */
					$addresses = $this->getDoc()->getOrCreate('addresses');
					$addresses->append($address);
					$this->xml->saveAndUnlock();
				} else {
					$this->xml->unlock();
				}

				if ($sendEmail) {
					// send email so that action can be confirmed
					$link = $this->constructFullPath(sprintf('%s/confirm?code=%s', urlencode($this->pagename), urlencode($code)));
					$confirmTxt = __('ml-please-confirm-email');
					$subject = hypha_getTitle() . ' - ' . $confirmTxt;
					$this->send($subject, '<a href="' . $link . '">' . $confirmTxt . '</a>', [$email]);

					notify('success', ucfirst(__('ml-successfully-sent') . ', ' . $confirmTxt));
				} else {
					notify('success', ucfirst(__('ml-successfully-subscribed')));
				}

				// all is success refresh page with success notification
				return 'reload';
			}
		}

		// update the form dom so that error can be displayed, if there are any
		$form->updateDom();

		// add edit button for registered users
		if (isUser()) {
			$commands = $this->findBySelector('#pageCommands');
			$commands->append($this->makeActionButton(__('edit'), 'edit'));
			if (isAdmin()) {
				$path = $this->language . '/' . $this->pagename . '/delete';
				$commands->append(makeButton(__('delete'), 'if(confirm(\'' . __('sure-to-delete') . '\'))' . makeAction($path, self::FORM_CMD_LIST_DELETE, '')));
			}
		}

		// display page name and description
		/** @var HyphaDomElement $description */
		$description = $this->getDoc()->getOrCreate('description');
		$html = $description->getHtml();
		$this->html->writeToElement('main', $html);

		// display form
		$viewAddresses = isUser() ? '<span style="float: right;">' . $this->makeActionButton(__('ml-view-addresses'), 'addresses') . '</span>' : '';
		$this->html->writeToElement('main', '<div><h3>' . __('subscribe') . $viewAddresses . '</h3></div>');
		$this->findBySelector('#main')->append($form->elem->children());

		// display archive (anonymous users only get to see sent items)
		$this->displayArchive();

		return null;
	}

	private function displayArchive() {
		if (isUser()) {
			/** @var HyphaDomElement $mailingsContainer */
			$mailingsContainer = $this->getDoc()->getOrCreate('mailings');
			$mailings = $mailingsContainer->children();
		} else {
			$mailings = $this->getDoc()->findXPath('//mailing[@status="' . self::MAILING_STATUS_SENT . '"]');
		}

		/** @var HyphaDomElement $main */
		$main = $this->findBySelector('#main');
		$main->append('<div><h3>' . __('archive') . '</h3></div>');
		$table = new HTMLTable();
		$main->appendChild($table);
		$header = $table->addHeaderRow();
		$table->addClass('section');
		$header->addCell();
		$header->addCell(__('subject'));
		$header->addCell(__('status'));
		if (isUser()) {
			$header->addCell();
		}
		$header->addCell()->setHtml(isUser() ? $this->makeActionButton('add', 'create') : '');
		$header->addCell();
		foreach ($mailings as $mailing) {
			$status = $mailing->getAttribute('status');
			$row = $table->addRow();
			$row->addCell()->setHtml('<span style="color:#b90;">∗</span>');
			$row->addCell($mailing->getAttribute('subject'));
			$row->addCell(__('ml-mailing-status-' . $status));
			$date = $mailing->getAttribute('date');
			if ($date) {
				$date = new \DateTime($date);
				$date = $date->format(__('ml-date-format'));
			} else {
				$date = '';
			}
			$row->addCell($date);
			$buttons = $this->makeActionButton(__('read'), $mailing->getId());
			if (self::MAILING_STATUS_DRAFT == $status) {
				$buttons .= $this->makeActionButton(__('edit'), $mailing->getId() . '/edit');
			}
			$row->addCell()->setHtml($buttons);
		}
	}

	private function editAction() {
		// throw error if edit is requested without client logged in
		if (!isUser()) {
			notify('error', __('login-to-edit'));

			return null;
		}

		// check if form is posted and get form data
		$formPosted = $this->isPosted(self::FORM_CMD_LIST_EDIT);
		if ($formPosted) {
			$formData = $_POST;
		} else {
			$senderData = $this->getSenderData();
			$formData = [
				self::FIELD_NAME_PAGE_NAME => showPagename($this->pagename),
				self::FIELD_NAME_PRIVATE => $this->privateFlag,
				self::FIELD_NAME_SENDER_EMAIL => $senderData['email'],
				self::FIELD_NAME_SENDER_NAME => $senderData['name'],
				self::FIELD_NAME_DESCRIPTION => $this->getDoc()->getOrCreate('description'),
			];
		}

		// create form
		$form = $this->createEditForm($formData);

		// process form if it was posted
		if ($formPosted) {
			$form->validateRequiredField(self::FIELD_NAME_SENDER_EMAIL);
			$form->validateRequiredField(self::FIELD_NAME_SENDER_NAME);
			$form->validateEmailField(self::FIELD_NAME_SENDER_EMAIL);
			if (empty($form->errors)) {
				global $hyphaXml;

				$pagename = validatePagename($form->dataFor(self::FIELD_NAME_PAGE_NAME));
				$private = $form->dataFor(self::FIELD_NAME_PRIVATE, false);
				$senderEmail = $form->dataFor(self::FIELD_NAME_SENDER_EMAIL);
				$senderName = $form->dataFor(self::FIELD_NAME_SENDER_NAME);

				$hyphaXml->lockAndReload();
				// After reloading, our page list node might
				// have changed, so find it in the newly loaded
				// XML. This seems a bit dodgy, though...
				$this->replacePageListNode(hypha_getPage($this->language, $this->pagename));
				// check if pagename and/or privateFlag have changed
				if ($pagename != $this->pagename || $private != $this->privateFlag) {
					hypha_setPage($this->pageListNode, $this->language, $pagename, $private);
					$hyphaXml->saveAndUnlock();
				} else {
					$hyphaXml->unlock();
				}

				$this->xml->lockAndReload();
				$this->getDoc()->setAttribute('sender-email', $senderEmail);
				$this->getDoc()->setAttribute('sender-name', $senderName);
				/** @var HyphaDomElement $description */
				$description = $this->getDoc()->getOrCreate('description');
				$description->setHtml(wikify_html($form->dataFor(self::FIELD_NAME_DESCRIPTION)));
				$this->xml->saveAndUnlock();

				notify('success', ucfirst(__('ml-successfully-updated')));
				return ['redirect', $this->constructFullPath($pagename)];
			}
		}

		// update the form dom so that error can be displayed, if there are any
		$form->updateDom();

		$this->findBySelector('#main')->append($form->elem->children());

		return null;
	}

	public function deleteAction() {
		// check if form is posted and get form data
		$formPosted = $this->isPosted(self::FORM_CMD_LIST_DELETE);
		if (!$formPosted) {
			return null;
		}

		global $hyphaUrl;

		$this->deletePage();

		notify('success', ucfirst(__('page-successfully-deleted')));
		return ['redirect', $hyphaUrl];
	}

	private function confirmAction() {
		$code = isset($_GET['code']) ? $_GET['code'] : null;
		if (null == $code) {
			notify('error', __('missing-arguments'));

			return null;
		}

		$this->xml->lockAndReload();

		/** @var NodeList $addressCollection */
		$addressCollection = $this->getDoc()->findXPath('//address[@confirm-code="' . $code . '"]');
		$address = $addressCollection->first();
		if (!$address instanceof HyphaDomElement) {
			$this->xml->unlock();
			notify('error', __('invalid-code'));

			return null;
		}

		if ($address->getAttribute('status') == self::ADDRESS_STATUS_CONFIRMED) {
			$this->xml->unlock();
			notify('success', ucfirst(__('ml-successfully-subscribed')));
			return ['redirect', $this->constructFullPath($this->pagename)];
		}

		$address->setAttribute('status', self::ADDRESS_STATUS_CONFIRMED);
		$address->setAttribute('unsubscribe-code', $this->constructCode());
		/** @var HyphaDomElement $addresses */
		$addresses = $this->getDoc()->getOrCreate('addresses');
		$addresses->append($address);
		$this->xml->saveAndUnlock();

		notify('success', ucfirst(__('ml-successfully-subscribed')));
		return ['redirect', $this->constructFullPath($this->pagename)];
	}

	private function unsubscribeAction() {
		$code = isset($_GET['code']) ? $_GET['code'] : null;
		if (null == $code) {
			notify('error', __('missing-arguments'));

			return null;
		}

		$this->xml->lockAndReload();
		/** @var NodeList $addressCollection */
		$addressCollection = $this->getDoc()->findXPath('//address[@unsubscribe-code="' . $code . '"]');
		$address = $addressCollection->first();
		if (!$address instanceof HyphaDomElement) {
			$this->xml->unlock();
			notify('error', __('invalid-code'));

			return null;
		}

		if ($address->getAttribute('status') == self::ADDRESS_STATUS_PENDING) {
			// weird case, should never happen, let's handle it anyway
			$this->xml->unlock();
			notify('error', __('ml-mail-has-invalid-status'));

			return null;
		}

		if ($address->getAttribute('status') != self::ADDRESS_STATUS_UNSUBSCRIBED) {
			$address->setAttribute('status', self::ADDRESS_STATUS_UNSUBSCRIBED);
			$address->removeAttribute('email');
			$this->xml->saveAndUnlock();
		} else {
			$this->xml->unlock();
		}

		notify('success', ucfirst(__('ml-successfully-unsubscribed')));
		return ['redirect', $this->constructFullPath($this->pagename)];
	}

	/**
	 * Builds a table with addresses.
	 */
	private function addressesAction() {
		$table = new HTMLTable();
		/** @var HyphaDomElement $main */
		$main = $this->findBySelector('#main');
		$main->appendChild($table);
		$table->addClass('section');
		$table->addHeaderRow()->addCells(['', __('addresses'), __('status')]);
		/** @var HyphaDomElement[] $addresses */
		$addresses = $this->getDoc()->findXPath('//address[@status="' . self::ADDRESS_STATUS_CONFIRMED . '" or @status="' . self::ADDRESS_STATUS_PENDING . '"]');
		foreach ($addresses as $child) {
			$status = $child->getAttribute('status');
			$table->addRow()->addCells(['', $child->getAttribute('email'), __('ml-address-status-' . $status)]);
		}
		$table->addRow()->addCells([__('total'), count($addresses), '']);

		// buttons
		$commands = $this->findBySelector('#pageCommands');
		$commands->append($this->makeActionButton(__('back'), ''));
	}

	private function createMailingAction() {
		return $this->editMailingAction();
	}

	/**
	 * @param null|string $mailingId
	 *
	 * @return array|null
	 */
	private function editMailingAction($mailingId = null) {
		// throw error if edit is requested without client logged in
		if (!isUser()) {
			notify('error', __('login-to-edit'));

			return null;
		}

		$sendMailing = $this->isPosted(self::FORM_CMD_MAILING_SEND);
		$saveMailing = $this->isPosted(self::FORM_CMD_MAILING_SAVE);
		if ($this->isPosted() && !$sendMailing && !$saveMailing) {
			notify('error', __('ml-invalid-command'));

			return null;
		}
		$isPosted = $sendMailing || $saveMailing;

		if (isset($_POST[self::FIELD_NAME_ID])) {
			if (null == $mailingId) {
				$mailingId = $_POST[self::FIELD_NAME_ID];
			} elseif ($_POST[self::FIELD_NAME_ID] != $mailingId) {
				notify('error', __('ml-inconsistent-parameters'));

				return null;
			}
		}

		// check given id
		/** @var HyphaDomElement $mailing */
		if (null != $mailingId) {
			$mailing = $this->xml->document()->getElementById($mailingId);
			if (!$mailing instanceof HyphaDomElement) {
				notify('error', __('not-found')); // 404

				return null;
			}
			// check if status is correct
			$status = $mailing->getAttribute('status');
			if (self::MAILING_STATUS_SENT == $status && $sendMailing) {
				notify('success', ucfirst(__('ml-successfully-sent')));
				return ['redirect', $this->constructFullPath($this->pagename . '/' . $mailingId)];
			}
			if (self::MAILING_STATUS_DRAFT != $status) {
				notify('error', __('unable-to-edit'));

				return null;
			}
		}

		// create form
		$form = $this->handleMailingForm($mailingId);

		// process posted form
		if ($isPosted && empty($form->errors)) {
			// get the mailing id, it could be that it was created by handling the form
			$mailingId = $form->dataFor(self::FIELD_NAME_ID);

			// send form if send button was used
			if ($sendMailing) {
				try {
					$this->sendMailing($mailingId);
					$msg = ucfirst(__('ml-successfully-sent'));
					$msgType = 'success';
				} catch (\Exception $e) {
					$msg = $e->getMessage();
					$msgType = 'error';
				}
			} else {
				$msg = ucfirst(__('ml-successfully-created'));
				$msgType = 'success';
			}

			// goto view page with notification
			notify($msgType, $msg);
			return ['redirect', $this->constructFullPath($this->pagename . '/' . $mailingId)];
		}

		// display form
		$this->findBySelector('#main')->append($form->elem->children());

		return null;
	}

	/**
	 * @param string $mailingId
	 *
	 * @return array|null
	 */
	private function showMailingAction($mailingId) {
		if ($this->isPosted(self::FORM_CMD_MAILING_SEND)) {
			try {
				$this->sendMailing($mailingId);
				$msg = ucfirst(__('ml-successfully-sent'));
				$msgType = 'success';
			} catch (\Exception $e) {
				$msg = $e->getMessage();
				$msgType = 'error';
			}

			// goto view page with notification
			notify($msgType, $msg);
			return ['redirect', $this->constructFullPath($this->pagename . '/' . $mailingId)];
		}

		// check if given id was correct
		/** @var HyphaDomElement $mailing */
		$mailing = $this->xml->document()->getElementById($mailingId);
		if (!$mailing instanceof HyphaDomElement) {
			notify('error', __('not-found')); // 404

			return null;
		}

		// get status
		$status = $mailing->getAttribute('status');
		$inDraft = self::MAILING_STATUS_DRAFT == $status;

		// throw error if status is draft without client logged in
		if ($inDraft && !isUser()) {
			notify('error', __('not-found')); // 404

			return null;
		}

		// display mailing
		$date = $mailing->getAttribute('date');
		$main = $this->findBySelector('#main');
		$main->append('<div><h2>' . $mailing->getAttribute('subject') . '</h2></div>');
		if ($date) {
			$date = new \DateTime($date);
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
		$commands = $this->findBySelector('#pageCommands');
		$commands->append($this->makeActionButton(__('back'), ''));

		// add edit button when in draft
		if ($inDraft) {
			$commands->append($this->makeActionButton(__('edit'), $mailingId . '/edit'));
			$commands->append($this->makeActionButton(__('send'), $mailingId, self::FORM_CMD_MAILING_SEND));
		}

		return null;
	}

	/**
	 * @param array $data
	 *
	 * @return WymHTMLForm
	 */
	private function createSubscribeForm(array $data = []) {
		$email = __('email');
		$emailFieldName = self::FIELD_NAME_EMAIL;
		$html = <<<EOF
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<label for="$emailFieldName">$email</label>: <input type="text" id="$emailFieldName" name="$emailFieldName" placeholder="$email" />
			</div>
EOF;
		/** @var HyphaDomElement $form */
		$form = $this->html->createElement('form');
		/** @var \DOMWrap\Element $elem */
		$elem = $form->html($html);

		// buttons
		/** @var NodeList $field */
		$field = $elem->find('#' . $emailFieldName);
		$field->parent()->append($this->makeActionButton(__('subscribe'), null, self::FORM_CMD_LIST_SUBSCRIBE));

		return $this->createForm($elem, $data);
	}

	/**
	 * @param array $data
	 *
	 * @return WymHTMLForm
	 */
	private function createEditForm(array $data = []) {
		$title = __('title');
		$pageNameFieldName = self::FIELD_NAME_PAGE_NAME;
		$senderName = __('mailing-sender-name');
		$senderNameFieldName = self::FIELD_NAME_SENDER_NAME;
		$senderEmail = __('mailing-sender-email');
		$senderEmailFieldName = self::FIELD_NAME_SENDER_EMAIL;
		$privatePage = __('private-page');
		$privateFieldName = self::FIELD_NAME_PRIVATE;
		$description = __('description');
		$descriptionFieldName = self::FIELD_NAME_DESCRIPTION;
		$html = <<<EOF
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="$pageNameFieldName">$title</label></strong> <input type="text" id="$pageNameFieldName" name="$pageNameFieldName" />
				<strong><label for="$senderNameFieldName"> $senderName </label></strong><input type="text" id="$senderNameFieldName" name="$senderNameFieldName" />
				<strong><label for="$senderEmailFieldName"> $senderEmail </label></strong><input type="text" id="$senderEmailFieldName" name="$senderEmailFieldName" />
				<strong><label for="$privateFieldName"> $privatePage </label></strong><input type="checkbox" name="$privateFieldName" id="$privateFieldName" />
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="$descriptionFieldName"> $description </label></strong><editor name="$descriptionFieldName"></editor>
			</div>
EOF;
		/** @var HyphaDomElement $form */
		$form = $this->html->createElement('form');
		/** @var \DOMWrap\Element $elem */
		$elem = $form->html($html);

		// buttons
		$commands = $this->findBySelector('#pageCommands');
		$commands->append($this->makeActionButton(__('cancel')));
		$commands->append($this->makeActionButton(__('save'), 'edit', self::FORM_CMD_LIST_EDIT));

		return $this->createForm($elem, $data);
	}

	/**
	 * @param array $data
	 *
	 * @return WymHTMLForm
	 */
	private function createMailingForm(array $data = []) {
		$idFieldName = self::FIELD_NAME_ID;
		$subject = __('subject');
		$subjectFieldName = self::FIELD_NAME_SUBJECT;
		$message = __('message');
		$messageFieldName = self::FIELD_NAME_MESSAGE;
		$html = <<<EOF
			<input type="hidden" id="$idFieldName" name="$idFieldName" />
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<label for="$subjectFieldName"> $subject </label> <input type="text" id="$subjectFieldName" name="$subjectFieldName" />
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="$messageFieldName"> $message </label></strong><editor id="$messageFieldName" name="$messageFieldName"></editor>
			</div>
EOF;
		/** @var HyphaDomElement $form */
		$form = $this->html->createElement('form');
		/** @var \DOMWrap\Element $elem */
		$elem = $form->html($html);

		// buttons
		$commands = $this->findBySelector('#pageCommands');

		// disable send button if there is no sender.
		$canSend = $this->hasSender();

		if (isset($data[self::FIELD_NAME_ID])) {
			$commands->append($this->makeActionButton(__('cancel'), $data[self::FIELD_NAME_ID]));
			$commands->append($this->makeActionButton(__('save'), $data[self::FIELD_NAME_ID] . '/edit', self::FORM_CMD_MAILING_SAVE));
			if ($canSend) {
				$commands->append($this->makeActionButton(__('send'), $data[self::FIELD_NAME_ID] . '/edit', self::FORM_CMD_MAILING_SEND));
			}
		} else {
			$commands->append($this->makeActionButton(__('cancel')));
			$commands->append($this->makeActionButton(__('save'), 'create', self::FORM_CMD_MAILING_SAVE));
			if ($canSend) {
				$commands->append($this->makeActionButton(__('send'), 'create', self::FORM_CMD_MAILING_SEND));
			}
		}

		return $this->createForm($elem, $data);
	}

	/**
	 * @param null|string $mailingId
	 *
	 * @return WymHTMLForm
	 */
	private function handleMailingForm($mailingId = null) {
		// check if form is posted and get form data
		$formPosted = $this->isPosted();
		$formData = [];
		if ($formPosted) {
			$formData = $_POST;
		} elseif (null != $mailingId) {
			/** @var HyphaDomElement $mailing */
			// get mailing if id was given
			$mailing = $this->xml->document()->getElementById($mailingId);
			if ($mailing instanceof HyphaDomElement) {
				$formData = [
					self::FIELD_NAME_ID => $mailingId,
					self::FIELD_NAME_SUBJECT => $mailing->getAttribute('subject'),
					self::FIELD_NAME_MESSAGE => $mailing->getHtml(),
				];
			}
		}

		// create form
		$form = $this->createMailingForm($formData);

		// process form if it was posted
		if ($formPosted) {
			$form->validateRequiredField(self::FIELD_NAME_SUBJECT);
			$form->validateRequiredField(self::FIELD_NAME_MESSAGE);
			if (empty($form->errors)) {
				// create or update mailing with given data
				$mailing = $this->createOrUpdateMailing($form->data);
				$form->data = array_merge($form->data, [self::FIELD_NAME_ID => $mailing->getId()]);
			}
		}

		// update the form dom so that error can be displayed, if there are any
		$form->updateDom();

		return $form;
	}

	/**
	 * @param array $data
	 *
	 * @return HyphaDomElement
	 */
	private function createOrUpdateMailing(array $data) {
		$this->xml->lockAndReload();
		/** @var HyphaDomElement $mailing */
		if (isset($data[self::FIELD_NAME_ID]) && $data[self::FIELD_NAME_ID]) {
			$mailing = $this->xml->document()->getElementById($data[self::FIELD_NAME_ID]);
		} else {
			$mailing = $this->xml->createElement('mailing');
			$mailing->generateId();
		}
		$mailing->setAttribute('subject', $data[self::FIELD_NAME_SUBJECT]);
		$mailing->setAttribute('status', self::MAILING_STATUS_DRAFT);
		$message = wikify_html($data[self::FIELD_NAME_MESSAGE]);
		$mailing->setHtml($message);
		/** @var HyphaDomElement $mailings */
		$mailings = $this->getDoc()->getOrCreate('mailings');
		$mailings->append($mailing);
		$this->xml->saveAndUnlock();

		return $mailing;
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
	 * @param string $mailingId
	 *
	 * @throws Exception
	 */
	private function sendMailing($mailingId) {
		if (!$this->hasSender()) {
			throw new \Exception(__('ml-no-sender'));
		}

		$senderName = $this->getDoc()->getAttribute('sender-name');
		$senderEmail = $this->getDoc()->getAttribute('sender-email');

		// mark mailing as sending
		$this->xml->lockAndReload();
		/** @var HyphaDomElement $mailing */
		$mailing = $this->xml->document()->getElementById($mailingId);
		$mailing->setAttribute('status', self::MAILING_STATUS_SENDING);
		/** @var HyphaDomElement $mailings */
		$mailings = $this->getDoc()->getOrCreate('mailings');
		$mailings->append($mailing);
		$this->xml->saveAndUnlock();

		// iterate over the confirmed addresses and send the mailing
		/** @var HyphaDomElement[] $receivers */
		$receivers = $this->getDoc()->findXPath('//address[@status="' . self::ADDRESS_STATUS_CONFIRMED . '"]');
		$linkToMailing = $this->constructFullPath($this->pagename . '/' . $mailingId);
		foreach ($receivers as $receiver) {
			$email = $receiver->getAttribute('email');
			$code = $receiver->getAttribute('unsubscribe-code');
			$linkToUnsubscribe = $this->constructFullPath(sprintf('%s/unsubscribe?address=%s&code=%s', $this->pagename, $receiver->getId(), $code));
			$message = '<p><a href="' . $linkToMailing . '">' . __('ml-if-unreadable-use-link') . '</a></p>';
			$message .= $mailing->getHtml();
			$message .= '<p><a href="' . $linkToUnsubscribe . '">' . __('ml-unsubscribe') . '</a></p>';
			$this->send($mailing->getAttribute('subject'), $message, [$email], $senderEmail, $senderName);
		}

		// mark mailing as sent
		$this->xml->lockAndReload();
		$mailing = $this->xml->document()->getElementById($mailingId);
		$mailing->setAttribute('status', self::MAILING_STATUS_SENT);
		$mailing->setAttribute('date', date('Y-m-d H:i:s'));
		$mailing->setAttribute('receivers', count($receivers));
		/** @var HyphaDomElement $mailings */
		$mailings = $this->getDoc()->getOrCreate('mailings');
		$mailings->append($mailing);
		$this->xml->saveAndUnlock();
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
		} catch (\Exception $e) {
		}

		return bin2hex(openssl_random_pseudo_bytes($length));
	}

	/**
	 * @todo [LRM]: move so it can be used throughout Hypha
	 * @param DOMElement $elem
	 * @param array $data
	 *
	 * @return WymHTMLForm
	 */
	private function createForm(DOMElement $elem, array $data = []) {
		$form = new WymHTMLForm($elem);
		$form->setData($data);

		return $form;
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
	 * @param string $selector
	 *
	 * @return NodeList
	 */
	private function findBySelector($selector) {
		return $this->html->find($selector);
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

	/**
	 * @todo [LRM]: move so it can be used throughout Hypha
	 * @param null|string $command
	 *
	 * @return bool
	 */
	private function isPosted($command = null) {
		return 'POST' == $_SERVER['REQUEST_METHOD'] && (null == $command || $command == $_POST['command']);
	}

	/**
	 * @param string $subject
	 * @param string $message
	 * @param array $receivers
	 * @param null|string $senderEmail
	 * @param null|string $senderName
	 */
	private function send($subject, $message, array $receivers, $senderEmail = null, $senderName = null) {
		$style = 'body {margin-top:10px; margin-left: 10px; font-size:10pt; font-family: Sans; color:black; background-color:white;}';
		foreach ($receivers as $receiver) {
			sendMail($receiver, $subject, $message, $senderEmail, $senderName, $style);
			usleep(rand(500000, 2000000));
		}
	}
}
