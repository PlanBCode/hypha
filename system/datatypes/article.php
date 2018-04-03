<?php
/*
        Module: article



	Article features.
 */
require_once __DIR__ . '/../core/WymHTMLForm.php';
require_once __DIR__ . '/../core/DocPage.php';

use DOMWrap\NodeList;

$hyphaPageTypes[] = 'article';

/*
	Class: article
*/

/**
 * mail:
 *
 * to all, status change
 * to all, blocking review remark
 * to digest, non-blocking review remark
 * to all, resolved blocking review remark
 *
 */
// TODO [LRM]: discussion closed by at
// TODO [LRM]: version control on article
// TODO [LRM]: visitor comment, verify with email
class article extends Page {

	const FIELD_NAME_USER = 'user';
	const FIELD_NAME_CREATED_AT = 'created_at';
	const FIELD_NAME_UPDATED_AT = 'updated_at';

	const FIELD_NAME_DISCUSSION_CONTAINER = 'discussions';
	const FIELD_NAME_DISCUSSION = 'discussion';
	const FIELD_NAME_DISCUSSION_BLOCKING = 'blocking';
	const FIELD_NAME_DISCUSSION_BLOCK_RESOLVED = 'block_resolved';
	const FIELD_NAME_DISCUSSION_CLOSED = 'closed';
	const FIELD_NAME_DISCUSSION_COMMENT = 'discussion_comment';
	const FIELD_NAME_APPROVE_CONTAINER = 'approves';
	const FIELD_NAME_APPROVE = 'approve';
	const FIELD_NAME_ARTICLE = 'article';
	const FIELD_NAME_CONTENT = 'content';
	const FIELD_NAME_TEXT = 'text';
	const FIELD_NAME_SOURCES = 'sources';
	const FIELD_NAME_CONTEXT = 'context';
	const FIELD_NAME_TITLE = 'title';
	const FIELD_NAME_AUTHOR = 'author';
	const FIELD_NAME_STATUS = 'status';
	const FIELD_NAME_EXCERPT = 'excerpt';
	const FIELD_NAME_METHOD = 'method';

	const FIELD_NAME_PAGE_NAME = 'page_name';

	const STATUS_DRAFT = 'draft';
	const STATUS_REVIEW = 'review';
	const STATUS_APPROVED = 'approved';
	const STATUS_PUBLISHED = 'published';
	const STATUS_RETRACTED = 'retracted';

	const PATH_EDIT = 'edit';
	const PATH_DISCUSSION = 'discussion';
	const PATH_COMMENT = 'comment';
	const PATH_APPROVE = 'approve';
	const PATH_STATUS_CHANGE_FIRST_ARG = 'status_change';
	const PATH_STATUS_CHANGE = 'status_change/{new_status}/{current_status}';
	const PATH_DISCUSSIONS = 'discussions';
	const PATH_RESOLVED = 'resolved';
	const PATH_CLOSED = 'closed';
	const PATH_DISCUSSION_RESOLVED = 'discussions/{id}/resolved';
	const PATH_DISCUSSION_CLOSED = 'discussions/{id}/closed';
	const PATH_DISCUSSION_COMMENT = 'discussions/{id}/comment';

	const FORM_CMD_EDIT = 'edit';
	const FORM_CMD_DISCUSSION = 'discussion';
	const FORM_CMD_COMMENT = 'comment';
	const FORM_CMD_DISCUSSION_RESOLVED = 'discussion_resolved';
	const FORM_CMD_DISCUSSION_CLOSED = 'discussion_closed';

	const EVENT_STATUS_CHANGE = 'event_status_change';
	const EVENT_DISCUSSION_STARTED = 'event_discussion_started';
	const EVENT_DISCUSSION_CLOSED = 'event_discussion_closed';

	private static $dataStructure = [
		self::FIELD_NAME_ARTICLE => [
			self::FIELD_NAME_CONTENT => [
				self::FIELD_NAME_TEXT => [],
				self::FIELD_NAME_SOURCES => [],
			],
			self::FIELD_NAME_CONTEXT => [
				self::FIELD_NAME_TITLE => [],
				self::FIELD_NAME_EXCERPT => [],
				self::FIELD_NAME_METHOD => [],
			],
		],
		self::FIELD_NAME_DISCUSSION_CONTAINER => [],
		self::FIELD_NAME_APPROVE_CONTAINER => [],
	];

	/** @var Xml */
	private $xml;

	/** @var DOMElement */
	private $hyphaUser;

	/** @var DocPage */
	private $rootDocPage;

	/** @var array */
	private $docPagesMtx = [
		'id' => [],
		'name' => [],
	];

	private $statusMtx = [
		self::STATUS_DRAFT => [self::STATUS_REVIEW => 'review'],
		self::STATUS_REVIEW => [self::STATUS_APPROVED => 'approve'],
		self::STATUS_APPROVED => [self::STATUS_PUBLISHED => 'publish'],
		self::STATUS_PUBLISHED => [/*self::STATUS_RETRACTED => 'retract'*/], // retracted is not supported yet
		self::STATUS_RETRACTED => [/*self::STATUS_DRAFT => 'to_draft'*/], // "to draft" is not supported yet
	];

	private $eventList = [];

	/**
	 * @param DOMElement $pageListNode
	 * @param array $args
	 */
	public function __construct(DOMElement $pageListNode, $args) {
		global $hyphaLanguage, $hyphaUser;
		parent::__construct($pageListNode, $args);
		$this->xml = new Xml('article', Xml::multiLingualOff, Xml::versionsOff);
		$this->xml->loadFromFile('data/pages/' . $pageListNode->getAttribute('id'));
		$this->language = $hyphaLanguage;
		$this->hyphaUser = $hyphaUser;

		$this->registerEventListeners(self::EVENT_STATUS_CHANGE, [$this, 'onStatusChange']);
		$this->registerEventListeners(self::EVENT_STATUS_CHANGE, [$this, 'onStatusChangeToPublish']);
		$this->registerEventListeners(self::EVENT_DISCUSSION_STARTED, [$this, 'onDiscussionStarted']);
		$this->registerEventListeners(self::EVENT_DISCUSSION_CLOSED, [$this, 'onDiscussionClosed']);
	}

	protected function onStatusChange($param) {
		$sendMail = $param['new'] !== self::STATUS_DRAFT;

		if ($sendMail) {
			$title = $this->getTitle();
			$linkToPage = $this->constructFullPath($this->pagename);
			if (self::STATUS_REVIEW === $param['new']) {
				$subject = 'A new article had been submitted for review.';
				$message = '<p>'.$subject.'</p><a href="'.$linkToPage.'">'.$title.'</a>';
			} else {
				$subject = 'The status of an article has been updated.';
				$message = '<p>'.$subject.'</p><a href="'.$linkToPage.'">'.$title.'</a> now has \''.$param['new'].'\' as status';
			}
			$style = 'body {margin-top:10px; margin-left: 10px; font-size:10pt; font-family: Sans; color:black; background-color:white;}';
//			sendMail(getUserEmailList(), hypha_getTitle().': '.$subject, $message, hypha_getEmail(), 'Hypha', $style);
		}
	}

	protected function onStatusChangeToPublish($param) {
		if (self::STATUS_PUBLISHED !== $param['new']) {
			return;
		}

		// remove private flag
		if ($this->privateFlag) {
			global $hyphaXml;
			$hyphaXml->lockAndReload();
			$this->replacePageListNode(hypha_getPage($this->language, $this->pagename));
			hypha_setPage($this->pageListNode, $this->language, $this->pagename, 'off');
			$this->privateFlag = false;
			$hyphaXml->saveAndUnlock();
		}
	}

	protected function onDiscussionStarted($param) {
		$discussion = $this->getDocPageById($param['id']);
		if (!(bool)$discussion->getAttr(self::FIELD_NAME_DISCUSSION_BLOCKING)) {
			return;
		}

		$title = $this->getTitle();
		$linkToPage = $this->constructFullPath($this->pagename);
		$subject = 'A blocking comment had been submitted.';
		$message = '<p>'.$subject.'</p><a href="'.$linkToPage.'">'.$title.'</a>';
		$style = 'body {margin-top:10px; margin-left: 10px; font-size:10pt; font-family: Sans; color:black; background-color:white;}';
		sendMail(getUserEmailList(), hypha_getTitle().': '.$subject, $message, hypha_getEmail(), 'Hypha', $style);
	}

	protected function onDiscussionClosed($param) {
		$discussion = $this->getDocPageById($param['id']);
		if (!(bool)$discussion->getAttr(self::FIELD_NAME_DISCUSSION_BLOCKING) || !(bool)$discussion->getAttr(self::FIELD_NAME_DISCUSSION_BLOCK_RESOLVED)) {
			return;
		}

		$title = $this->getTitle();
		$linkToPage = $this->constructFullPath($this->pagename);
		$subject = 'A blocking comment had been resolved.';
		$message = '<p>'.$subject.'</p><a href="'.$linkToPage.'">'.$title.'</a>';
		$style = 'body {margin-top:10px; margin-left: 10px; font-size:10pt; font-family: Sans; color:black; background-color:white;}';
		sendMail(getUserEmailList(), hypha_getTitle().': '.$subject, $message, hypha_getEmail(), 'Hypha', $style);
	}

	private function getTitle() {
		$title = $this->getDocPageByName(self::FIELD_NAME_TITLE);
		if ($title instanceof DocPage) {
			$title = $title->getText();
		}
		if ($title === '') {
			$title = showPagename($this->pagename);
		}

		return $title;
	}

	/**
	 * @return string
	 */
	private function getStatus() {
		$status = $this->getAttr(self::FIELD_NAME_CONTEXT, self::FIELD_NAME_STATUS);
		if ('' == $status) {
			$status = self::STATUS_DRAFT;
		}
		return $status;
	}

	/**
	 * @return int
	 */
	private function getApproveCount() {
		return $this->getDocPageByName(self::FIELD_NAME_APPROVE_CONTAINER)->getDoc()->children()->count();
	}

	/**
	 * @return int
	 */
	private function getBlockingDiscussionsCount() {
		$discussions = $this->getDocPageByName(self::FIELD_NAME_DISCUSSION_CONTAINER);
		/** @var NodeList $blockingDiscussions */
		$blockingDiscussions = $discussions->getDoc()->findXPath('//discussion[@blocking="1"][@block_resolved!="1"]');
		return $blockingDiscussions->count();
	}

	/**
	 * @return bool
	 */
	private function canBeApproved() {
		return $this->getBlockingDiscussionsCount() === 0 && $this->getApproveCount() >= 3 && $this->getStatus() === self::STATUS_REVIEW;
	}

	/**
	 * @param string $userId
	 * @return bool
	 */
	private function hasUserApproved($userId) {
		$approves = $this->getDocPageByName(self::FIELD_NAME_APPROVE_CONTAINER);
		/** @var NodeList $approveCollection */
		$approveCollection = $approves->getDoc()->findXPath('//' . self::FIELD_NAME_APPROVE . '[@user="' . $userId . '"]');
		return $approveCollection->count() >= 1;
	}

	/**
	 * @return DocPage[]
	 */
	private function getDiscussions() {
		$discussions = $this->getDocPageByName(self::FIELD_NAME_DISCUSSION_CONTAINER)->getChildren();
		if (empty($discussions)) {
			return [];
		}
		$discussions = $discussions[self::FIELD_NAME_DISCUSSION];
		if ($discussions instanceof DocPage) {
			$discussions = [$discussions];
		}

		return $discussions;
	}

	/**
	 * @return DocPage[]
	 */
	private function getApproves() {
		$approves = $this->getDocPageByName(self::FIELD_NAME_APPROVE_CONTAINER)->getChildren();
		if (empty($approves)) {
			return [];
		}
		$approves = $approves[self::FIELD_NAME_APPROVE];
		if ($approves instanceof DocPage) {
			$approves = [$approves];
		}

		return $approves;
	}

	public function build() {
		$this->ensureStructure();
		$this->html->writeToElement('pagename', $this->getTitle() . ' ' . asterisk($this->privateFlag));
		$titleElement = $this->findBySelector('#pagename');

		$main = $this->findBySelector('#main');
		$main->attr('class', $this->getStatus());

		$status = $this->getStatus();
		if ($status === self::STATUS_REVIEW) {
			$status .= ', ' . $this->getApproveCount() . ' approves, ' . $this->getBlockingDiscussionsCount() . ' unresolved blocks';
		}
		if ($status !== self::STATUS_PUBLISHED) {
			$titleElement->after('<div class="review-info">'.$status.'</div>');
		}

		$firstArgument = $this->getArg(0);
		switch ($firstArgument) {
			default:
			case null:
				return $this->indexAction();
			case self::PATH_EDIT:
				return $this->editAction();
			case self::PATH_APPROVE:
				return $this->approveAction();
			case self::PATH_DISCUSSION:
				return $this->discussionAction();
			case self::PATH_STATUS_CHANGE_FIRST_ARG:
				return $this->statusChangeAction($this->getArg(1), $this->getArg(2));
			case self::PATH_DISCUSSIONS:
				switch ($this->getArg(2)) {
					case self::PATH_COMMENT:
						return $this->discussionCommentAction($this->getArg(1));
					case self::PATH_RESOLVED:
						return $this->discussionResolvedAction($this->getArg(1));
					case self::PATH_CLOSED:
						return $this->discussionClosedAction($this->getArg(1));
				}
		}
	}

	private function ensureStructure() {
		$this->xml->lockAndReload();

		$new = $this->xml->documentElement->children()->count() === 0;
		if (!$new) {
			// build structure from XML
			$this->rootDocPage = DocPage::build($this->xml);
			$this->xml->unlock();

			return;
		}

		$build = function (HyphaDomElement $doc, array $structure) use (&$build) {
			foreach ($structure as $name => $children) {
				$doc->appendChild($build($doc->getOrCreate($name), $children));
			}
			return $doc;
		};
		$build($this->xml->documentElement, self::$dataStructure);

		// build structure from XML
		$this->rootDocPage = DocPage::build($this->xml);

		// set initial status, create timestamp and title
		$context = $this->getDocPageByName(self::FIELD_NAME_CONTEXT);
		$context->setAttr(self::FIELD_NAME_STATUS, self::STATUS_DRAFT);
		$context->setAttr(self::FIELD_NAME_AUTHOR, $this->hyphaUser->getAttribute('fullname'));
		$context->setAttrWithTimestamp(self::FIELD_NAME_CREATED_AT);
		$title = $this->getDocPageByName(self::FIELD_NAME_TITLE);
		$title->setHtml(showPagename($this->pagename));
		$this->xml->saveAndUnlock();

		// force private flag
		if (!$this->privateFlag) {
			global $hyphaXml;
			$hyphaXml->lockAndReload();
			$this->replacePageListNode(hypha_getPage($this->language, $this->pagename));
			hypha_setPage($this->pageListNode, $this->language, $this->pagename, 'on');
			$this->privateFlag = true;
			$hyphaXml->saveAndUnlock();
		}
	}

	public function indexAction() {
		// add edit button for registered users
		$status = $this->getStatus();

		if (isUser()) {
			$commands = $this->findBySelector('#pageCommands');
			$commands->append($this->makeActionButton(__(self::PATH_EDIT), self::PATH_EDIT));

			// the status change from review to approved is done automatically
			if (self::STATUS_REVIEW === $status) {
				$userId = $this->hyphaUser->getAttribute('id');
				if (!$this->hasUserApproved($userId)) {
					$commands->append($this->makeActionButton(__(self::PATH_APPROVE), self::PATH_APPROVE));
				}
			} else {
				foreach ($this->statusMtx[$status] as $newStatus => $option) {
					$path = str_replace(['{new_status}', '{current_status}'], [$newStatus, $status], self::PATH_STATUS_CHANGE);
					$commands->append($this->makeActionButton(__($option), $path));
				}
			}
		}

		// display page name and text
		$context = $this->getDocPageByName(self::FIELD_NAME_CONTEXT)->getDoc();
		$content = $this->getDocPageByName(self::FIELD_NAME_CONTENT)->getDoc();

		$author = $context->attr(self::FIELD_NAME_AUTHOR);

		/** @var HyphaDomElement $main */
		$main = $this->findBySelector('#main');

		if ($author) {
			$main->append('<div class="author">' . __('by') . ' ' . $author . '</div>');
		}

		$article = $content->get(self::FIELD_NAME_TEXT)->html();
		$main->append('<div class="article">'.$article.'</div>');

		$method = $context->get(self::FIELD_NAME_METHOD)->html();
		if ($method) {
			/** @var HyphaDomElement $methodContainer */
			$methodContainer = $this->xml->createElement('div');
			$methodContainer->attr('class', 'method');
			$methodContainer->append('<h2>' . __('method') . '</h2>');
			$methodContainer->append($method);
			$main->append($methodContainer);
		}

		$sources = $this->getDocPageByName(self::FIELD_NAME_SOURCES)->getHtml();
		if (!empty($sources)) {
			$main->append('<div class="sources"><h2>' . __('sources') . '</h2>' . $sources . '</div>');
		}

		if (isUser()) {
			$discussions = $this->getDiscussions();
			/** @var HyphaDomElement $reviewCommentsContainer */
			$reviewCommentsContainer = $this->xml->createElement('div');
			$reviewCommentsContainer->attr('class', 'review-comments-wrapper');
			$reviewCommentsContainer->append('<h2>' . __('review comments') . '</h2>');
			/** @var HyphaDomElement $list */
			foreach ($discussions as $discussion) {
				$blocking = (bool)$discussion->getAttr(self::FIELD_NAME_DISCUSSION_BLOCKING);
				$closed = (bool)$discussion->getAttr(self::FIELD_NAME_DISCUSSION_CLOSED);
				$resolved = (bool)$discussion->getAttr(self::FIELD_NAME_DISCUSSION_BLOCK_RESOLVED);
				$list = $this->xml->createElement('ul');

				foreach ($discussion->getChildren() as $comments) {
					if (!is_array($comments)) {
						$comments = [$comments];
					}
					$firstComment = true;
					foreach ($comments as $comment) {
						$createdAt = date('j-m-y, H:i', ltrim($comment->getAttr(self::FIELD_NAME_CREATED_AT), 't'));
						$committerId = $comment->getAttr(self::FIELD_NAME_USER);
						$committer = hypha_getUserById($committerId);
						$committerName = $committer instanceof HyphaDomElement ? $committer->getAttribute('fullname') : $committerId;
						$html = $comment->getText();
						$html .= '<p>' . __('by') . ' <strong>' . $committerName . '</strong> ' . __('at') . ' ' . $createdAt;
						if ($firstComment) {
							if ($blocking) {
								$html .= ' | ' . ($resolved ? __('is resolved') : __('is blocking'));
							}
						}
						$html .= '</p>';
						$list->append('<li '.($firstComment ? 'class="first-comment"' : '').'>' . $html . '</li>');
						$firstComment = false;
					}
				}
				if ($blocking) {
					if (!$resolved && ($discussion->getAttr(self::FIELD_NAME_USER) === $this->hyphaUser->getAttribute('id') || isAdmin())) {
						$path = str_replace('{id}', $discussion->getId(), self::PATH_DISCUSSION_RESOLVED);
						$list->append('<p>' . $this->makeActionButton(__('set as resolved'), $path, self::FORM_CMD_DISCUSSION_RESOLVED) . '</p>');
					}
				} elseif (!$closed) {
					$path = str_replace('{id}', $discussion->getId(), self::PATH_DISCUSSION_CLOSED);
					$list->append('<p>' . $this->makeActionButton(__('set as closed'), $path, self::FORM_CMD_DISCUSSION_CLOSED) . '</p>');
				}

				if (!$closed) {
					$commentForm = $this->createDiscussionCommentForm($discussion);
					$list->append($commentForm->elem->children());
				}
				/** @var HyphaDomElement $reviewCommentContainer */
				$reviewCommentContainer = $this->xml->createElement('div');
				$class = 'review-comment-wrapper collapsed';
				if ($blocking) {
					$class .= ' blocking';
				}
				if ($resolved) {
					$class .= ' resolved';
				}
				if ($closed) {
					$class .= ' closed';
				}
				$reviewCommentContainer->attr('class', $class);
				$reviewCommentContainer->append($list);
				$reviewCommentsContainer->append($reviewCommentContainer);
			}
			if (empty($discussions)) {
				$list = $this->xml->createElement('ul');
				$list->append('<li>' . __('no comments yet') . '</li>');
				$reviewCommentsContainer->append($list);
			}
			$commentForm = $this->createDiscussionForm();
			$reviewCommentsContainer->append($commentForm->elem->children());
			$main->append($reviewCommentsContainer);

			if ($status !== self::STATUS_DRAFT) {
				/** @var HyphaDomElement $approvesContainer */
				$approvesContainer = $this->xml->createElement('div');
				$approvesContainer->attr('class', 'approves');
				$approves = $this->getApproves();
				$approvesContainer->append('<h2>' . __('approves') . '</h2>');
				/** @var HyphaDomElement $list */
				$list = $this->xml->createElement('ul');
				foreach ($approves as $approve) {
					$createdAt = date('j-m-y, H:i', ltrim($approve->getAttr(self::FIELD_NAME_CREATED_AT), 't'));
					$html = $approve->getHtml();
					$approverId = $approve->getAttr(self::FIELD_NAME_USER);
					$approver = hypha_getUserById($approverId);
					$approverName = $approver instanceof HyphaDomElement ? $approver->getAttribute('fullname') : $approverId;
					$html .= '<p>' . __('by') . ' <strong>' . $approverName . '</strong> ' . __('at') . ' ' . $createdAt . '</p>';
					$list->append('<li>' . $html . '</li>');
				}
				if (empty($approves)) {
					$list->append('<li>' . __('no approves yet') . '</li>');
				}
				$approvesContainer->append($list);
				$main->append($approvesContainer);
			}
		}

		return null;
	}

	private function editAction() {
		// throw error if edit is requested without client logged in
		if (!isUser()) {
			notify('error', __('login-to-edit'));

			return null;
		}

		// check if form is posted and get form data
		$formPosted = $this->isPosted(self::FORM_CMD_EDIT);
		if ($formPosted) {
			$formData = $_POST;
		} else {
			$formData = [
				self::FIELD_NAME_TITLE => html_entity_decode(strip_tags($this->getDocPageByName(self::FIELD_NAME_TITLE)->getText())),
				self::FIELD_NAME_AUTHOR => $this->getAttr(self::FIELD_NAME_CONTEXT, self::FIELD_NAME_AUTHOR),
				self::FIELD_NAME_EXCERPT => $this->getDocPageByName(self::FIELD_NAME_EXCERPT)->getHtml(),
				self::FIELD_NAME_TEXT => $this->getDocPageByName(self::FIELD_NAME_TEXT)->getHtml(),
				self::FIELD_NAME_METHOD => $this->getDocPageByName(self::FIELD_NAME_METHOD)->getHtml(),
				self::FIELD_NAME_SOURCES => $this->getDocPageByName(self::FIELD_NAME_SOURCES)->getHtml(),
			];
		}

		// create form
		$form = $this->createEditForm($formData);

		// process form if it was posted
		if ($formPosted) {
			if (empty($form->errors)) {
				$this->rootDocPage->lockAndReload();
				$author = $form->dataFor(self::FIELD_NAME_AUTHOR);
				$this->getDocPageByName(self::FIELD_NAME_CONTEXT)->setAttr(self::FIELD_NAME_AUTHOR, $author);
				$title = $this->getDocPageByName(self::FIELD_NAME_TITLE);
				$title->setText($form->dataFor(self::FIELD_NAME_TITLE));
				$excerpt = $this->getDocPageByName(self::FIELD_NAME_EXCERPT);
				$excerpt->setHtml($form->dataFor(self::FIELD_NAME_EXCERPT), true);
				$text = $this->getDocPageByName(self::FIELD_NAME_TEXT);
				$text->setHtml($form->dataFor(self::FIELD_NAME_TEXT), true);
				$method = $this->getDocPageByName(self::FIELD_NAME_METHOD);
				$method->setHtml($form->dataFor(self::FIELD_NAME_METHOD), true);
				$sources = $this->getDocPageByName(self::FIELD_NAME_SOURCES);
				$sources->setHtml($form->dataFor(self::FIELD_NAME_SOURCES), true);

				$this->rootDocPage->saveAndUnlock();

				notify('success', ucfirst(__('ml-successfully-updated')));
				return ['redirect', $this->constructFullPath($this->pagename)];
			}
		}

		// update the form dom so that error can be displayed, if there are any
		$form->updateDom();

		$this->findBySelector('#main')->append($form->elem->children());

		return null;
	}

	private function discussionAction() {
		// throw error if edit is requested without client logged in
		if (!isUser()) {
			notify('error', __('login-to-comment'));

			return null;
		}

		// check if form is posted and get form data
		$formPosted = $this->isPosted(self::FORM_CMD_DISCUSSION);
		$formData = $formPosted ? $_POST : [];

		// create form
		$form = $this->createDiscussionForm($formData);

		// process form if it was posted
		if ($formPosted && empty($form->errors)) {
			$comment = $form->dataFor(self::FIELD_NAME_DISCUSSION_COMMENT)['new'];
			if ($comment != '') {
				$discussions = $this->getDocPageByName(self::FIELD_NAME_DISCUSSION_CONTAINER);
				$discussions->lockAndReload();
				$discussion = $discussions->createChild(self::FIELD_NAME_DISCUSSION);
				$blocking = $form->dataFor(self::FIELD_NAME_DISCUSSION_BLOCKING) !== null;
				$discussion->setAttr(self::FIELD_NAME_DISCUSSION_BLOCKING, $blocking);
				$discussion->setAttr(self::FIELD_NAME_USER, $this->hyphaUser->getAttribute('id'));
				if ($blocking) {
					$discussion->setAttr(self::FIELD_NAME_DISCUSSION_BLOCK_RESOLVED, false);
				}
				$discussion->setAttr(self::FIELD_NAME_DISCUSSION_CLOSED, false);
				$this->createDiscussionComment($discussion, $comment);
				$discussions->saveAndUnlock();
				$this->resetDocPagesMtx();
				$this->event(self::EVENT_DISCUSSION_STARTED, ['id' => $discussion->getId()]);
				notify('success', ucfirst(__('ml-successfully-updated')));
			} else {
				notify('success', ucfirst(__('please-add-a-comment')));
			}
			return ['redirect', $this->constructFullPath($this->pagename)];
		}

		// update the form dom so that error can be displayed, if there are any
		$form->updateDom();

		$this->findBySelector('#main')->append($form->elem->children());

		return null;
	}

	private function discussionCommentAction($discussionId) {
		// throw error if edit is requested without client logged in
		if (!isUser()) {
			notify('error', __('login-to-set-as-processed'));

			return null;
		}

		$discussion = $this->getDocPageById($discussionId);
		$discussion->lockAndReload();

		// check if form is posted and get form data
		$formPosted = $this->isPosted(self::FORM_CMD_COMMENT);
		$formData = $formPosted ? $_POST : [];

		// create form
		$form = $this->createDiscussionCommentForm($discussion, $formData);

		// process form if it was posted
		if ($formPosted && empty($form->errors)) {
			$comment = $form->dataFor(self::FIELD_NAME_DISCUSSION_COMMENT)[$discussionId];
			if ($comment != '') {
				$this->createDiscussionComment($discussion, $comment);
				$discussion->saveAndUnlock();
				$this->resetDocPagesMtx();
				notify('success', ucfirst(__('ml-successfully-updated')));
			} else {
				notify('success', ucfirst(__('please-add-a-comment')));
			}
			return ['redirect', $this->constructFullPath($this->pagename)];
		}

		// update the form dom so that error can be displayed, if there are any
		$form->updateDom();

		$this->findBySelector('#main')->append($form->elem->children());

		return null;
	}

	private function discussionResolvedAction($discussionId) {
		// throw error if edit is requested without client logged in
		if (!isUser()) {
			notify('error', __('login-to-set-as-resolved'));

			return null;
		}

		$this->rootDocPage->lockAndReload();
		/** @var HyphaDomElement $discussion */
		$discussion = $this->xml->document()->getElementById($discussionId);

		$userId = $this->hyphaUser->getAttribute('id');
		if ($discussion->getAttribute(self::FIELD_NAME_USER) !== $userId && !isAdmin()) {
			notify('error', __('insufficient-rights-to-set-as-processed'));
			$this->rootDocPage->unlock();

			return null;
		}

		$discussion->attr(self::FIELD_NAME_DISCUSSION_BLOCK_RESOLVED, true);
		$discussion->attr(self::FIELD_NAME_DISCUSSION_CLOSED, true);

		if ($this->canBeApproved()) {
			$context = $this->getDocPageByName(self::FIELD_NAME_CONTEXT);
			$context->setAttr(self::FIELD_NAME_STATUS, self::STATUS_APPROVED);
		}
		$this->rootDocPage->saveAndUnlock();
		$this->event(self::EVENT_DISCUSSION_CLOSED, ['id' => $discussion->getId()]);

		notify('success', ucfirst(__('ml-successfully-updated')));
		return ['redirect', $this->constructFullPath($this->pagename)];
	}

	private function discussionClosedAction($discussionId) {
		// throw error if edit is requested without client logged in
		if (!isUser()) {
			notify('error', __('login-to-set-as-closed'));

			return null;
		}

		$this->rootDocPage->lockAndReload();
		/** @var HyphaDomElement $discussion */
		$discussion = $this->xml->document()->getElementById($discussionId);
		if ((bool)$discussion->attr(self::FIELD_NAME_DISCUSSION_CLOSED) === true) {
			$this->rootDocPage->unlock();

			return null;
		}

		$discussion->attr(self::FIELD_NAME_DISCUSSION_CLOSED, true);
		$this->rootDocPage->saveAndUnlock();
		$this->event(self::EVENT_DISCUSSION_CLOSED, ['id' => $discussion->getId()]);

		notify('success', ucfirst(__('ml-successfully-updated')));
		return ['redirect', $this->constructFullPath($this->pagename)];
	}

	private function statusChangeAction($status, $currentStatus, $notifyOnSuccess = true) {
		// throw error if edit is requested without client logged in
		if (!isUser()) {
			notify('error', __('login-to-change-status'));

			return null;
		}

		if (!isset($this->statusMtx[$currentStatus]) || !isset($this->statusMtx[$currentStatus][$status])) {
			notify('error', __('unsupported-status-change'));

			return null;
		}

		if ($this->getStatus() !== $currentStatus) {
			if ($this->getStatus() === $status) {
				notify('success', ucfirst(__('ml-successfully-updated')));

				return ['redirect', $this->constructFullPath($this->pagename)];
			}
			notify('error', __('status-changed-in-between'));

			return null;
		}

		$context = $this->getDocPageByName(self::FIELD_NAME_CONTEXT);
		$context->lockAndReload();

		$context->setAttr(self::FIELD_NAME_STATUS, $status);
		$context->saveAndUnlock();

		$this->event(self::EVENT_STATUS_CHANGE, ['new' => $status, 'old' => $currentStatus]);

		if ($notifyOnSuccess) {
			notify('success', ucfirst(__('ml-successfully-updated')));
		}

		return ['redirect', $this->constructFullPath($this->pagename)];
	}

	private function approveAction() {
		// throw error if edit is requested without client logged in
		if (!isUser()) {
			notify('error', __('login-to-comment'));

			return null;
		}

		$approves = $this->getDocPageByName(self::FIELD_NAME_APPROVE_CONTAINER);
		$approves->lockAndReload();

		$userId = $this->hyphaUser->getAttribute('id');

		if (!$this->hasUserApproved($userId)) {
			$approve = $approves->createChild(self::FIELD_NAME_APPROVE);
			$approve->setAttr(self::FIELD_NAME_USER, $userId);
			$approve->setAttr(self::FIELD_NAME_CREATED_AT, 't' . time());
			$approves->saveAndUnlock();
			$this->resetDocPagesMtx();
			if ($this->canBeApproved()) {
				$this->statusChangeAction(self::STATUS_APPROVED, self::STATUS_REVIEW, false);
			}
		} else {
			$approves->unlock();
		}

		notify('success', ucfirst(__('ml-successfully-updated')));
		return ['redirect', $this->constructFullPath($this->pagename)];
	}

	private function createDiscussionComment(DocPage $discussion, $text) {
		$comment = $discussion->createChild(self::FIELD_NAME_DISCUSSION_COMMENT, false);
		$comment->setText($text);
		$comment->setAttr(self::FIELD_NAME_USER, $this->hyphaUser->getAttribute('id'));
		$comment->setAttrWithTimestamp(self::FIELD_NAME_CREATED_AT);
	}

	/**
	 * @param array $data
	 *
	 * @return WymHTMLForm
	 */
	private function createEditForm(array $data = []) {
		$title = __('title');
		$titleFieldName = self::FIELD_NAME_TITLE;
		$author = __('author');
		$authorFieldName = self::FIELD_NAME_AUTHOR;
		$excerpt = __('excerpt');
		$excerptFieldName = self::FIELD_NAME_EXCERPT;
		$text = __('article');
		$textFieldName = self::FIELD_NAME_TEXT;
		$method = __('method');
		$methodFieldName = self::FIELD_NAME_METHOD;
		$sources = __('sources');
		$sourcesFieldName = self::FIELD_NAME_SOURCES;
		$html = <<<EOF
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="$titleFieldName">$title</label></strong> <input type="text" id="$titleFieldName" name="$titleFieldName" />
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="$authorFieldName"> $author </label></strong> <input type="text" id="$authorFieldName" name="$authorFieldName" />
			</div>
			<!--div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="$excerptFieldName"> $excerpt </label></strong><editor name="$excerptFieldName"></editor>
			</div-->
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="$textFieldName"> $text </label></strong><editor name="$textFieldName"></editor>
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="$methodFieldName"> $method </label></strong><editor name="$methodFieldName"></editor>
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="$sourcesFieldName"> $sources </label></strong><editor name="$sourcesFieldName"></editor>
			</div>
EOF;
		/** @var HyphaDomElement $form */
		$form = $this->html->createElement('form');
		/** @var \DOMWrap\Element $elem */
		$elem = $form->html($html);

		// buttons
		$commands = $this->findBySelector('#pageCommands');
		$commands->append($this->makeActionButton(__('cancel')));
		$commands->append($this->makeActionButton(__('save'), self::PATH_EDIT, self::FORM_CMD_EDIT));

		return $this->createForm($elem, $data);
	}

	/**
	 * @param array $data
	 *
	 * @return WymHTMLForm
	 */
	private function createDiscussionForm(array $data = []) {
		$comment = __('comment');
		$commentFieldName = self::FIELD_NAME_DISCUSSION_COMMENT . '[new]';
		$blocking = __('blocking');
		$commentBlockingFieldName = self::FIELD_NAME_DISCUSSION_BLOCKING;
		$html = <<<EOF
			<div class="new-comment">
			<div>
				<strong><label for="$commentFieldName"> $comment </label></strong><textarea name="$commentFieldName" id="$commentFieldName" cols="36" rows="4"></textarea>
			</div>
EOF;
		if (!in_array($this->getStatus(), [self::STATUS_APPROVED, self::STATUS_PUBLISHED])) {
			$html .= <<<EOF
			<div>
				<strong><label for="$commentBlockingFieldName"> $blocking </label></strong><input type="checkbox" name="$commentBlockingFieldName" id="$commentBlockingFieldName" />
			</div>
EOF;
		}

		$html .= $this->makeActionButton(__('add review comment'), self::PATH_DISCUSSION, self::FORM_CMD_DISCUSSION);
		$html .= '</div>';
		/** @var HyphaDomElement $form */
		$form = $this->html->createElement('form');
		/** @var \DOMWrap\Element $elem */
		$elem = $form->html($html);

		return $this->createForm($elem, $data);
	}

	/**
	 * @param DocPage $discussion
	 * @param array $data
	 *
	 * @return WymHTMLForm
	 */
	private function createDiscussionCommentForm(DocPage $discussion, array $data = []) {
		$comment = __('comment');
		$commentFieldName = self::FIELD_NAME_DISCUSSION_COMMENT . '[' . $discussion->getId() . ']';
		$html = <<<EOF
			<div class="new-comment">
			<div>
				<strong><label for="$commentFieldName"> $comment </label></strong><textarea name="$commentFieldName" id="$commentFieldName" cols="36" rows="4"></textarea>
			</div>
EOF;
		$path = str_replace('{id}', $discussion->getId(), self::PATH_DISCUSSION_COMMENT);
		$html .= $this->makeActionButton(__('add review comment'), $path, self::FORM_CMD_COMMENT);
		$html .= '</div>';
		/** @var HyphaDomElement $form */
		$form = $this->html->createElement('form');
		/** @var \DOMWrap\Element $elem */
		$elem = $form->html($html);

		return $this->createForm($elem, $data);
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
	 * @param string $name
	 * @return null|DocPage
	 */
	private function getDocPageByName($name) {
		$docPagesMtx = $this->getDocPagesMtx('name');
		if (array_key_exists($name, $docPagesMtx)) {
			return $docPagesMtx[$name];
		}

		return null;
	}

	/**
	 * @param string $id
	 * @return null|DocPage
	 */
	private function getDocPageById($id) {
		$docPagesMtx = $this->getDocPagesMtx('id');
		if (array_key_exists($id, $docPagesMtx)) {
			return $docPagesMtx[$id];
		}

		return null;
	}

	private function resetDocPagesMtx() {
		$this->docPagesMtx = ['id' => [], 'name' => []];
	}

	/**
	 * @param string $type
	 * @return DocPage[]
	 */
	private function getDocPagesMtx($type) {
		if (empty($this->docPagesMtx['name'])) {
			$registerDocPages = function (DocPage $docPage) use (&$registerDocPages) {
				if ($docPage->getId()) {
					$this->docPagesMtx['id'][$docPage->getId()] = $docPage;
				}
				$this->docPagesMtx['name'][$docPage->getName()] = $docPage;
				foreach ($docPage->getChildren() as $children) {
					if ($children instanceof DocPage) {
						$children = [$children];
					}
					foreach ($children as $child) {
						if ($child instanceof DocPage) {
							$registerDocPages($child);
						}
					}
				}
			};
			$registerDocPages($this->rootDocPage);
		}

		return $this->docPagesMtx[$type];
	}

	/**
	 * @param string $docPageName
	 * @param string $attr
	 * @return null|string
	 */
	private function getAttr($docPageName, $attr) {
		$docPage = $this->getDocPageByName($docPageName);
		if ($docPage instanceof DocPage) {
			return $docPage->getAttr($attr);
		}

		return null;
	}

	/**
	 * @param string $event
	 * @param callable $callback
	 */
	private function registerEventListeners($event, callable $callback) {
		$this->eventList[$event][] = $callback;
	}

	/**
	 * @param string $event
	 * @param array $arg
	 * @return bool
	 */
	private function event($event, array $arg = []) {
		if (isset($this->eventList[$event])) {
			foreach ($this->eventList[$event] as $callable) {
				$result = call_user_func($callable, $arg);
				if ($result === false) {
					return false;
				}
			}
		}

		return true;
	}
}
