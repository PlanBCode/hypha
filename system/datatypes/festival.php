<?php
/*
        Module: festival

	Collects various festival-related features, like signup and
	timetables.
 */

	$hyphaPageTypes[] = 'festivalpage';

/*
	Class: festivalpage
*/
	class festivalpage extends Page {
		function __construct($pageListNode, $args) {
			parent::__construct($pageListNode, $args);
			$this->xml = new Xml('festival', Xml::multiLingualOn, Xml::versionsOff);
			$this->xml->loadFromFile('data/pages/'.$pageListNode->getAttribute('id'));

			registerCommandCallback('save', Array($this, 'handleSaveSettings'));
			registerCommandCallback('signup', Array($this, 'handleSignup'));
			registerCommandCallback('contribute', Array($this, 'handleContribute'));
			registerCommandCallback('pay', Array($this, 'handlePay'));
		}

		function build() {
			if (isUser() && !in_array($this->getArg(0), ['edit'])) {
				$commands = $this->html->find('#pageCommands');

				$action = makeAction($this->language . '/' . $this->pagename . '/edit', '', '');
				$button = makeButton(__('settings'), $action);
				$commands->append($button);

				$action = makeAction($this->language . '/' . $this->pagename . '/signup', '', '');
				$button = makeButton(__('festival-signup'), $action);
				$commands->append($button);

				$action = makeAction($this->language . '/' . $this->pagename . '/contribute', '', '');
				$button = makeButton(__('festival-contribute'), $action);
				$commands->append($button);

				$action = makeAction($this->language . '/' . $this->pagename . '/participants', '', '');
				$button = makeButton(__('festival-participants'), $action);
				$commands->append($button);

				$action = makeAction($this->language . '/' . $this->pagename . '/contributions', '', '');
				$button = makeButton(__('festival-contributions'), $action);
				$commands->append($button);

				$action = makeAction($this->language . '/' . $this->pagename . '/lineup', '', '');
				$button = makeButton(__('festival-lineup'), $action);
				$commands->append($button);
			}

			switch ($this->getArg(0)) {
			/*
				case 'translate':
					return $this->translate();
			*/
				case 'edit':
					return $this->showSettings();
				case 'participants':
					return $this->showParticipants();
				case 'contributions':
					return $this->showContributions();

				case 'signup':
					return $this->showSignup();
				case 'pay':
					return $this->showPay();
				case 'paymenthook':
					return $this->handlePaymentHook();
				case 'contribute':
					return $this->showContribute();
				default:
				case 'lineup':
					return $this->showLineup();
				case 'timetable':
					return $this->showTimetable();
			}
		}

		/**
		 * Retrieve a config value from the XML. This finds a
		 * tag with the given id, and retrieves the given
		 * attribute from it. If no attribute is given, the
		 * "value" attribute is returned.
		 */
		function getConfig($id, $attribute = 'value') {
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
		function getConfigElement($id, $tagname = null) {
			$elem = $this->xml->getElementById($id);
			if (!$elem && $tagname) {
				$elem = $this->xml->createElement($tagname);
				$elem->setAttribute('xml:id', $id);
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
		function setConfig($id, $value, $tagname = 'config', $attribute = 'value') {
			$config = $this->getConfigElement($id, $tagname);
			return $config->setAttribute($attribute, $value);
		}

		/**
		 * Show the admin display with registrations.
		 */
		function showParticipants() {
			if (!isUser()) return notify('error', __('login-to-edit'));
			$table = new HTMLTable();
			$this->html->find('#main')->appendChild($table);
			$table->addHeaderRow()->addCells([__('name'), __('email'), __('phone'), __('price'), __('payment-status')]);
			foreach ($this->xml->documentElement->getOrCreate('participants')->children() as $participant) {
				$row = $table->addRow();
				$row->addCell($participant->getAttribute('name'));
				$row->addCell($participant->getAttribute('email'));
				$row->addCell($participant->getAttribute('phone'));
				$row->addCell($participant->getAttribute('payment-amount'));
				$row->addCell($participant->getAttribute('payment-status'));
			}
		}

		/**
		 * Show the admin display with contributions.
		 */
		function showContributions() {
			if (!isUser()) return notify('error', __('login-to-edit'));
			$table = new HTMLTable();
			$this->html->find('#main')->appendChild($table);
			$table->addHeaderRow()->addCells([__('name'), __('title'), __('category'), __('website')]);
			foreach ($this->xml->documentElement->getOrCreate('contributions')->children() as $contribution) {
				$row = $table->addRow();
				$row->addCell($contribution->getAttribute('name'));
				$row->addCell($contribution->getAttribute('title'));
				$row->addCell($contribution->getAttribute('category'));
				$row->addCell($contribution->getAttribute('website'));

				$description = $contribution->getOrCreate('description')->text();
				$imgfilename = $contribution->getAttribute('image');
				if ($description || $imgfilename) {
					$cell = $table->addRow()->addCell(__('description') . ': ' . $description);
					$cell->setAttribute('colspan', 4);
					$cell->setAttribute('style', 'padding-left: 50px;');
					if ($imgfilename) {
						$imgtag = $this->html->createElement('img');
						$cell->insertBefore($imgtag, $cell->firstChild);
						$image = new HyphaImage($contribution->getAttribute('image'));
						$imgtag->setAttribute('src', $image->getUrl(50, 50));
						$imgtag->setAttribute('style', 'float: left; margin: 5px 10px 0 0;');
					}
				}
				$notes = $contribution->getOrCreate('notes')->text();
				if ($notes) {
					$cell = $table->addRow()->addCell(__('notes') . ': ' . $notes);
					$cell->setAttribute('colspan', 4);
					$cell->setAttribute('style', 'padding-left: 50px;');
				}
			}
		}


		function getSettingsForm() {
$html = <<<'EOF'
<table>
	<tr><td><label for="festival-title">Festival title</label> *</td><td><input name="festival-title"/></td></tr>
</table>
EOF;
			$elem = $this->html->createElement('form')->html($html);
			return new HTMLForm($elem);
		}

		function showSettings($form = null) {
			if (!isUser()) return notify('error', __('login-to-edit'));

			if (!$form) {
				$form = $this->getSettingsForm();
				$form->setData([
					'festival-title' => $this->getConfig('festival-title'),
				]);
			}

			$form->updateDom();
			$this->html->find('#main')->append($form->elem->children());

			// show 'cancel' button
			$action = makeAction($this->language.'/'.$this->pagename, '', '');
			$button = makeButton(__('cancel'), $action);
			$this->html->writeToElement('pageCommands', $button);

			// show 'save' button
			$action = makeAction($this->language.'/'.$this->pagename, 'save', '');
			$button = makeButton(__('save'), $action);
			$this->html->writeToElement('pageCommands', $button);
		}

		function handleSaveSettings($arg) {
			global $hyphaUrl, $hyphaLanguage, $hyphaPage;

			if (!isUser()) return notify('error', __('login-to-edit'));
			$form = $this->getSettingsForm();
			$form->setData($_POST);
			$form->validateRequiredField('festival-title');

			if ($form->errors) {
				// HACK: Prevent index.php from
				// rendering the page normally, since we
				// already rendered it. There should be
				// a better way to achive this.
				$hyphaPage = false;
				return $this->showSettings($form);
			}
			$this->xml->lockAndReload();
			$this->setConfig('festival-title', $form->dataFor('festival-title'));
			$this->xml->saveAndUnlock();
			return 'reload';
		}

		/**
		 * The signup form. Here's where the fun starts.
		 */
		function showSignup($form = null) {
			if (!$form)
				$form = $this->getConfigElement('signup-form');

			$this->html->find('#pagename')->text(__('festival-signup-for') . $this->getConfig('festival-title'));
			$main = $this->html->find('#main');
			$main->append($form->children());

			$action = makeAction($this->language.'/'.$this->pagename, 'signup', '');
			$button = makeButton(__('signup'), $action);
			$main->append($button);
		}

		/**
		 * Handle the signup form.  Redirects to either /pay or
		 * /contribute, depending on whether there is something to
		 * pay.
		 */
		function handleSignup($arg) {
			global $hyphaUrl, $hyphaLanguage, $hyphaPage;
			$form = new HTMLForm($this->getConfigElement('signup-form')->cloneNode(true));
			$form->setData($_POST);
			$form->validateRequiredField('name');
			$form->validateRequiredField('email');
			$form->validateEmailField('email');
			$form->validateMoneyField('amount');
			if ($form->errors) {
				// HACK: Prevent index.php from
				// rendering the page normally, since we
				// already rendered it. There should be
				// a better way to achive this.
				$hyphaPage = false;
				// Reshow the form with submitted values
				// and errors
				$form->updateDom();
				return $this->showSignup($form->elem);
			}

			$this->xml->lockAndReload();

			$participant = $this->xml->createElement('participant');
			$participant->generateId();
			$participant->setAttribute('name', $form->dataFor('name'));
			$participant->setAttribute('email', $form->dataFor('email'));
			$participant->setAttribute('phone', $form->dataFor('phone'));
			$participant->setAttribute('key', bin2hex(openssl_random_pseudo_bytes(8)));
			$this->xml->documentElement->getOrCreate('participants')->append($participant);
			if ((float)$form->dataFor('amount', 0) > 0)
				$this->setupPayment($participant, $form->dataFor('amount', 0));
			$this->xml->saveAndUnlock();

			notify('success', __('festival-succesful-signup-for') . $this->getConfig('festival-title'));
			$contribute_url = $hyphaUrl . $hyphaLanguage . '/' . $this->pagename . '/contribute/' . $participant->getAttribute('xml:id') . '/' . $participant->getAttribute('key');
			$digest = htmlspecialchars($participant->getAttribute('name') . __('festival-signed-up-for') . $this->getConfig('festival-title'));
			$digest .= ' (<a href="' . $contribute_url . '">Add contribution</a>)';
			writeToDigest($digest, 'festival-registration');

			if ((float)$form->dataFor('amount', 0) > 0) {
				$next_url = $hyphaUrl . $hyphaLanguage . '/' . $this->pagename . '/pay/' . $participant->getAttribute('xml:id') . '/' . $participant->getAttribute('key');
			} else {
				// Send email
				$contribute_url = $hyphaUrl . $hyphaLanguage . '/' . $this->pagename . '/contribute/' . $participant->getAttribute('xml:id') . '/' . $participant->getAttribute('key');
				$append = '<p><a href="'.htmlspecialchars($contribute_url).'">'.__('festival-contribute') . '</a></p>';
				$this->sendMail($participant->getAttribute('email'), 'mail-signed-up-free', $append);

				$next_url = $hyphaUrl . $hyphaLanguage . '/' . $this->pagename . '/contribute/' . $participant->getAttribute('xml:id') . '/' . $participant->getAttribute('key');
			}

			return ['redirect', $next_url];
		}

		/**
		 * Show the payment screen, which has a message and a
		 * button to start the payment procedure. If the payment
		 * is already complete, this redirects to the
		 * /contribute page.
		 */
		function showPay() {
			global $hyphaUrl, $hyphaLanguage;

			$this->xml->lockAndReload();
			$participant = $this->checkKeyArguments(['participant']);
			if (!$participant)
				return;

			// Check the status of the payment
			$this->checkPayment($participant);
			$this->xml->saveAndUnlock();

			if ($participant->getAttribute('payment-timestamp')) {
				$url = $hyphaUrl . $hyphaLanguage . '/' . $this->pagename . '/contribute/' . $participant->getAttribute('xml:id') . '/' . $participant->getAttribute('key');
				notify('success', __('festival-successful-payment'));
				return ['redirect', $url];
			}

			// Render the page
			$this->html->find('#pagename')->text(__('festival-pay-for') . $this->getConfig('festival-title'));
			$main = $this->html->find('#main');
			$message = __('festival-complete-by-paying');
			$action = makeAction($this->language.'/'.$this->pagename.'/'.join('/',$this->args), 'pay', '');
			$button = makeButton(__('pay'), $action);
			$main->append($message);
			$main->append($button);
		}

		/**
		 * Handle the 'pay' command from the button on the /pay
		 * page. This redirect to the payment provider, unless
		 * the payment was already completed, then it redirects
		 * to the /contribute page.
		 */
		function handlePay() {
			global $hyphaUrl, $hyphaLanguage;
			$this->xml->lockAndReload();
			$participant = $this->checkKeyArguments(['participant']);
			if(!$participant) {
				$this->xml->unlock();
				return;
			}

			// Check the status of the payment, and create a
			// new one if it expired or failed.
			$url = $this->checkPayment($participant, true);
			$this->xml->saveAndUnlock();

			// If payment is completed, redirect to
			// contribute page. Otherwise, redirect to
			// payment provider.
			if (!$url)
				$url = $hyphaUrl . $hyphaLanguage . '/' . $this->pagename . '/contribute/' . $participant->getAttribute('xml:id') . '/' . $participant->getAttribute('key');

			return ['redirect', $url];
		}

		function handlePaymentHook() {
			$this->xml->lockAndReload();
			$participant = $this->checkKeyArguments(['participant']);
			if(!$participant) {
				http_response_code(403);
				writeToDigest('Invalid participant id or key in payment hook: ' . $_SERVER['REQUEST_URI'] . '?' . $_SERVER['QUERY_STRING'], 'error');
				exit;
			}

			if($participant->getAttribute('payment-id') != $_REQUEST['id']) {
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

		/**
		 * Show the contribution form.
		 */
		function showContribute($form = null, $editing = false) {
			$obj = $this->checkKeyArguments(['contribution', 'participant']);
			if (!$obj && !isUser())
				return;
			$editing = ($obj && $obj->tagName == 'contribution');

			if (!$form) {
				$form = new HTMLForm($this->getConfigElement('contribution-form')->cloneNode(true));
				if ($editing) {
					$form->setData($obj);
					$form->updateDom();
				}
			}

			$this->html->find('#pagename')->text(__('festival-contribute-to') . $this->getConfig('festival-title'));
			$main = $this->html->find('#main');
			$main->append($form->elem->children());

			$action = makeAction($this->language.'/'.$this->pagename.'/'.join('/',$this->args), 'contribute', '');
			if ($editing)
				$button = makeButton(__('festival-modify'), $action);
			else
				$button = makeButton(__('festival-contribute'), $action);
			$main->append($button);
		}

		/**
		 * Handle the contribution form.
		 */
		function handleContribute() {
			global $hyphaUrl, $hyphaLanguage, $hyphaPage, $hyphaUser;
			$this->xml->lockAndReload();

			$obj = $this->checkKeyArguments(['contribution', 'participant']);
			if (!$obj && !isUser())
				return;
			$errors = array();

			$form = new HTMLForm($this->getConfigElement('contribution-form')->cloneNode(true));
			$form->setData($_POST);
			$form->validateRequiredField('name');
			$form->validateRequiredField('title');
			$form->validateRequiredField('category');
			if (array_key_exists('image_upload', $_FILES))
				$form->handleImageUpload('image', $_FILES['image_upload']);

			if ($form->errors) {
				$this->xml->unlock();
				// HACK: Prevent index.php from
				// rendering the page normally, since we
				// already rendered it. There should be
				// a better way to achive this.
				$hyphaPage = false;
				// Reshow the form with submitted values
				// and errors
				$form->updateDom();
				return $this->showContribute($form);
			}

			// get contribution element or create new contribution element
			if ($obj && $obj->tagName == 'contribution') {
				$contribution = $obj;
				$editing = true;
				$participant_id = $contribution->getAttribute('participant');
				if ($participant_id)
					$participant = $this->xml->getElementById($participant_id);
			} else {
				$contribution = $this->xml->createElement('contribution');
				$contribution->generateId();
				$contribution->setAttribute('key', bin2hex(openssl_random_pseudo_bytes(8)));
				if ($obj)
					$contribution->setAttribute('participant', $obj->getAttribute('xml:id'));

				$this->xml->documentElement->getOrCreate('contributions')->appendChild($contribution);
				$editing = false;
				$participant = $obj;
			}

			// set attributes
			$contribution->setAttribute('name', $form->dataFor('name'));
			$contribution->setAttribute('title', $form->dataFor('title'));
			$contribution->setAttribute('category', $form->dataFor('category'));
			$contribution->setAttribute('image', $form->dataFor('image'));

			$description = $contribution->getOrCreate('description');
			$description->setText($form->dataFor('description', ''));

			$contribution->setAttribute('website', $form->dataFor('website'));

			$notes = $contribution->getOrCreate('notes');
			$notes->setText($form->dataFor('notes', ''));

			$this->xml->saveAndUnlock();
			$edit_url = $hyphaUrl . $hyphaLanguage . '/' . $this->pagename . '/contribute/' . $contribution->getAttribute('xml:id') . '/' . $contribution->getAttribute('key');

			if (isUser())
				$digest = htmlspecialchars(getNameForUser());
			else
				$digest = htmlspecialchars($participant->getAttribute('name'));

			if ($editing)
				$digest .= __('festival-edited-contribution');
			else
				$digest .= __('festival-added-contribution');

			$digest .= $contribution->getAttribute('title') . ' - ' . $contribution->getAttribute('name');
			$digest .= ' (<a href="' . $edit_url . '">Edit contribution</a>)';
			writeToDigest($digest, 'festival-contribution');

			if (!$editing) {
				$edit_url = $hyphaUrl . $hyphaLanguage . '/' . $this->pagename . '/contribute/' . $contribution->getAttribute('xml:id') . '/' . $contribution->getAttribute('key');
				$append = '<p><a href="'.htmlspecialchars($edit_url).'">'.__('festival-edit-contribution') . '</a></p>';
				if (isUser())
					$this->sendMail($hyphaUser->getAttribute('email'), 'mail-added-contribution', $append);
				else
					$this->sendMail($participant->getAttribute('email'), 'mail-added-contribution', $append);
			}

			if ($editing)
				notify('success', __('festival-contribution-modified'));
			else
				notify('success', __('festival-contribution-added'));

			$lineup_url = $hyphaUrl . $hyphaLanguage . '/' . $this->pagename . '/lineup';
			return ['redirect', $lineup_url];
		}


		function showLineup() {
			global $hyphaHtml;
			$html = '';
			$contributions = $this->xml->documentElement->getOrCreate('contributions')->children();
			foreach($contributions as $contribution) {
				$html.= $this->buildContribution($contribution);
				$html.= '<div class="hbar"></div>';
			}
			$this->html->find('#pagename')->text(__('festival-lineup-for') . $this->getConfig('festival-title'));
			$this->html->find('#main')->html($html);
		}

		/**
		 * Build the HTML for a single contribution in the
		 * lineup.
		 */
		function buildContribution($contribution) {
			$html = '<div class="infoact">';
			// artist and title
			if ($contribution->getAttribute('name')) $html.= '<a id="'.$contribution->getAttribute('xml:id').'" style="font-size:15pt; font-weight:bold; clear:left;">'.$contribution->getAttribute('name').($contribution->hasAttribute('title') ? ' - '.$contribution->getAttribute('title') : '').'</a>';
			$html.= '<p/>';
			// image and description
			$image_filename = $contribution->getAttribute('image');
			if ($image_filename) {
				$img_width = 150;
				$img_height = 150;
				$image = new HyphaImage($image_filename);
				$html.= '<a href="'.$image->getUrl().'"><img style="float:left; border:0px; margin-right:10px;" src="'.$image->getUrl($img_width, $img_height).'"/></a>';
			}
			$description = $contribution->getElementsByTagName('description')->Item(0);
			if ($description) $html.= getInnerHtml($description);
			$html.= '<p style="clear:left;"/>';
			// icons
			$html.= '<div style="float:right; padding-top:5px; padding-left:5px;">';
			foreach($contribution->getElementsByTagName('icon') as $icon) {
				$html.= '<a href="'.$icon->getAttribute('website').'"><img style="border:0px;" src="images/'.$icon->getAttribute('source').'"/></a>';
			}
			$html.= '</div>';
			// price and duration
			if ($contribution->hasAttribute('price') || $contribution->hasAttribute('duration')) {
				$price = $contribution->hasAttribute('price') && $contribution->getAttribute('price') ? __('price').': '.$contribution->getAttribute('price') : '';
				$duration = $contribution->hasAttribute('duration') && $contribution->getAttribute('duration') ? $contribution->getAttribute('duration') : '';
				$html.= '<b>'.$price.($price && $duration ? ' | ' : '').$duration.'</b><br/>';
			}
			// additional urls
			foreach($contribution->getElementsByTagName('contact') as $contact) {
				if ($contact->hasAttribute('website') && $contact->getAttribute('website')) $html.= '<a style="text-decoration: none;color:#d2691e;" href="'.$contact->getAttribute('website').'">'.$contact->getAttribute('website').'</a>';
			}
			$html.= '</div>';
			$html.= '<p style="clear:right;"/>';
			
			return $html;
		}

		/**
		 * Set up the payment-related properties for the given
		 * participant and create an initial payment.
		 * Should be called with the XML lock held.
		 */
		function setupPayment($participant, $amount) {
			$participant->ownerDocument->requireLock();
			$participant->setAttribute('payment-description', $this->getConfig('festival-title') . ' - ' . $participant->getAttribute('name'));
			$participant->setAttribute('payment-amount', $amount);
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
		function createPayment($participant) {
			global $hyphaUrl, $hyphaLanguage;
			$participant->ownerDocument->requireLock();
			$complete_url = $hyphaUrl . $hyphaLanguage . '/' . $this->pagename . '/pay/' . $participant->getAttribute('xml:id') . '/' . $participant->getAttribute('key');
			$hook_url = $hyphaUrl . $hyphaLanguage . '/' . $this->pagename . '/paymenthook/' . $participant->getAttribute('xml:id') . '/' . $participant->getAttribute('key');

			// load Mollie script
			require_once('system/Mollie/API/Autoloader.php');
			$mollie = new Mollie_API_Client;
			$mollie->setApiKey($this->getConfig('mollie-key'));

			// create payment
			$payment = $mollie->payments->create([
				"amount"       => $participant->getAttribute('payment-amount'),
				"description"  => $participant->getAttribute('payment-description'),
				"redirectUrl"  => $complete_url,
				"webhookUrl"   => $hook_url,
			]);

			$participant->setAttribute('payment-id', $payment->id);
			$participant->setAttribute('payment-status', $payment->status);

			return $payment;
		}

		/**
		 * Check the status of the given participants's payment,
		 * sending out any mails or digests as needed.
		 *
		 * When the payment is complete (succesful or
		 * unsuccesful), NULL is returned. If the payment is
		 * still open, the url to redirect to is returned.
		 *
		 * When create_new is true, a new payment is created if
		 * the existing one is not paid but no longer opened
		 * (e.g. expired, failed or cancelled). In this case, a
		 * NULL return value means the payment was succesful.
		 *
		 * Should be called with the XML lock held.
		 */
		function checkPayment($participant, $create_new = false) {
			// load Mollie script
			require_once('system/Mollie/API/Autoloader.php');
			$mollie = new Mollie_API_Client;
			$mollie->setApiKey($this->getConfig('mollie-key'));
			$changed = false;

			$this->xml->requireLock();
			$payment = $mollie->payments->get($participant->getAttribute('payment-id'));

			// If the status changed, process the change. If
			// $create_new is true, do not send any "failed"
			// e-mails, which would only be confusing when
			// the user is about to start a new payment
			// attempt.
			if ($participant->getAttribute('payment-status') != $payment->status)
				$this->processPaymentChange($participant, $payment, !$create_new);

			// If the payment is not complete and not open,
			// create a new payment ready to pay (if
			// requested).
			if ($create_new && !$payment->isPaid() && !$payment->isOpen())
				$payment = $this->createPayment($participant);

			if ($payment->isOpen())
				return $payment->getPaymentUrl();
			else
				return NULL;
		}

		/**
		 * Called when the payment status for a participant has
		 * changed. Takes care of updating the participant,
		 * sending e-mails and adding to the digest.
		 * When $mail_failed is false, no e-mail is sent on a
		 * failed payment, but a note is still added to the
		 * digest.
		 */
		function processPaymentChange($participant, $payment, $mail_failed) {
			global $hyphaUrl, $hyphaLanguage;
			$participant->setAttribute('payment-status', $payment->status);
			if ($payment->isPaid()) {
				if (!$participant->getAttribute('payment-timestamp')) {
					$participant->setAttribute('payment-timestamp', $payment->paidDatetime);

					// Send email
					$contribute_url = $hyphaUrl . $hyphaLanguage . '/' . $this->pagename . '/contribute/' . $participant->getAttribute('xml:id') . '/' . $participant->getAttribute('key');
					$append = '<p><a href="'.htmlspecialchars($contribute_url).'">'.__('festival-contribute') . '</a></p>';
					$this->sendMail($participant->getAttribute('email'), 'mail-signed-up-payed', $append);

					// Add to digest
					$digest = htmlspecialchars($participant->getAttribute('name') . __('festival-payed-for') . $this->getConfig('festival-title'));
					$digest .= ' (€' . $participant->getAttribute('payment-amount') . ')';
					$digest .= ' (<a href="' . $contribute_url . '">Add contribution</a>)';
					writeToDigest($digest, 'festival-payment');
				}

				// Payment complete, no payment url to
				// redirect to
				return NULL;
			}

			$error_statuses = [
				Mollie_API_Object_Payment::STATUS_CANCELLED,
				Mollie_API_Object_Payment::STATUS_EXPIRED,
				Mollie_API_Object_Payment::STATUS_FAILED,
			];
			if (in_array($payment->status, $error_statuses)) {
				if ($mail_failed) {
					$pay_url = $hyphaUrl . $hyphaLanguage . '/' . $this->pagename . '/pay/' . $participant->getAttribute('xml:id') . '/' . $participant->getAttribute('key');
					$append = '<p><a href="'.htmlspecialchars($pay_url).'">'.__('festival-restart-payment') . '</a></p>';
					$this->sendMail($participant->getAttribute('email'), 'mail-payment-failed', $append);
				}
				$digest = htmlspecialchars($participant->getAttribute('name') . __('festival-failed-to-pay-for') . $this->getConfig('festival-title') . ' ( ' . $payment->status . ')');
				writeToDigest($digest, 'error');
			}
		}

		/**
		 * Retrieve the given mail from the config and send it,
		 * appending the given bit of HTML.
		 */
		function sendMail($to, $id, $append) {
			$elem = $this->getConfigElement($id);
			$body = $elem->html() . $append;
			$subject = $elem->getAttribute('subject');

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
		 */
		function checkKeyArguments($tags) {
			$id = $this->getArg(1);
			if ($id) {
				$obj = $this->xml->getElementById($id);
				if ($obj && in_array($obj->tagName, $tags)) {
					if ($obj->getAttribute('key') == $this->getArg(2))
						return $obj;
				}
			}
			notify('error', __('invalid-or-no-key'));
			return false;
		}

	}
?>

