<?php

/*
 * Module: peer_reviewed_article
 *
 * Article features.
 */

use DOMWrap\NodeList;

/*
 * Class: peer_reviewed_article
 */

// TODO [LRM]: add version control on peer_reviewed_article
class peer_reviewed_article extends Page {
	use EventTrait;
	use DataTypeDocPageAwareTrait;

	/** @var Xml */
	private $xml;

	/** @var DOMElement */
	private $hyphaUser;

	const FIELD_NAME_USER = 'user';
	const FIELD_NAME_CREATED_AT = 'created_at';
	const FIELD_NAME_UPDATED_AT = 'updated_at';

	const FIELD_NAME_DISCUSSION_CONTAINER = 'discussions';
	const FIELD_NAME_DISCUSSION_REVIEW_CONTAINER = 'review';
	const FIELD_NAME_DISCUSSION_PUBLIC_CONTAINER = 'public';
	const FIELD_NAME_DISCUSSION = 'discussion';
	const FIELD_NAME_DISCUSSION_BLOCKING = 'blocking';
	const FIELD_NAME_DISCUSSION_BLOCK_RESOLVED = 'block_resolved';
	const FIELD_NAME_DISCUSSION_CLOSED = 'closed';
	const FIELD_NAME_DISCUSSION_COMMENT = 'discussion_comment';
	const FIELD_NAME_DISCUSSION_COMMENT_PENDING = 'pending';
	const FIELD_NAME_DISCUSSION_COMMENT_CONFIRM_CODE = 'confirm_code';
	const FIELD_NAME_DISCUSSION_COMMENTER_NAME = 'commenter_name';
	const FIELD_NAME_DISCUSSION_COMMENTER_EMAIL = 'commenter_email';
	const FIELD_NAME_DISCUSSION_CLOSED_BY = 'closed_by';
	const FIELD_NAME_DISCUSSION_CLOSED_AT = 'closed_at';
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
	const PATH_DELETE = 'delete';
	const PATH_DISCUSSION = 'discussion';
	const PATH_DISCUSSION_TYPE = 'discussion/{type}';
	const PATH_COMMENT = 'comment';
	const PATH_CONFIRM = 'confirm';
	const PATH_APPROVE = 'approve';
	const PATH_STATUS_CHANGE_FIRST_ARG = 'status_change';
	const PATH_STATUS_CHANGE = 'status_change/{new_status}/{current_status}';
	const PATH_DISCUSSIONS = 'discussions';
	const PATH_CLOSED = 'closed';
	const PATH_DISCUSSION_CLOSED = 'discussions/{id}/closed';
	const PATH_DISCUSSION_COMMENT = 'discussions/{id}/comment';
	const PATH_DISCUSSION_COMMENT_CONFIRM = 'comment/{id}/confirm?code={code}';

	const FORM_CMD_EDIT = 'edit';
	const FORM_CMD_DELETE = 'delete';
	const FORM_CMD_DISCUSSION = 'discussion';
	const FORM_CMD_COMMENT = 'comment';
	const FORM_CMD_DISCUSSION_RESOLVED = 'discussion_resolved';
	const FORM_CMD_DISCUSSION_CLOSED = 'discussion_closed';

	const EVENT_STATUS_CHANGE = 'event_status_change';
	const EVENT_DISCUSSION_STARTED = 'event_discussion_started';
	const EVENT_DISCUSSION_CLOSED = 'event_discussion_closed';
	const EVENT_PUBLIC_COMMENT = 'event_public_comment';

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
		self::FIELD_NAME_DISCUSSION_CONTAINER => [
			self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER => [],
			self::FIELD_NAME_DISCUSSION_PUBLIC_CONTAINER => [],
		],
		self::FIELD_NAME_APPROVE_CONTAINER => [],
	];

	private $statusMtx = [
		self::STATUS_DRAFT => [self::STATUS_REVIEW => 'art-start-review'],
		self::STATUS_REVIEW => [self::STATUS_APPROVED => 'art-approve'],
		self::STATUS_APPROVED => [self::STATUS_PUBLISHED => 'art-publish'],
		self::STATUS_PUBLISHED => [/*self::STATUS_RETRACTED => 'retract'*/], // retracted is not supported yet
		self::STATUS_RETRACTED => [/*self::STATUS_DRAFT => 'to_draft'*/], // "to draft" is not supported yet
	];

	/**
	 * @param DOMElement $pageListNode
	 * @param array $args
	 */
	public function __construct(DOMElement $pageListNode, $args) {
		global $hyphaUser, $hyphaContentLanguage;
		parent::__construct($pageListNode, $args);

		$this->xml = new Xml(get_called_class(), Xml::multiLingualOff, Xml::versionsOff);
		$this->xml->loadFromFile('data/pages/' . $pageListNode->getAttribute('id'));
		$this->language = $hyphaContentLanguage;
		$this->hyphaUser = $hyphaUser;

		$this->registerEventListener(self::EVENT_STATUS_CHANGE, [$this, 'onStatusChange']);
		$this->registerEventListener(self::EVENT_STATUS_CHANGE, [$this, 'onStatusChangeToPublish']);
		$this->registerEventListener(self::EVENT_DISCUSSION_STARTED, [$this, 'onDiscussionStarted']);
		$this->registerEventListener(self::EVENT_DISCUSSION_CLOSED, [$this, 'onDiscussionClosed']);
		$this->registerEventListener(self::EVENT_PUBLIC_COMMENT, [$this, 'onPublicComment']);
	}

	public static function getDatatypeName() {
		return __('datatype.name.peer_reviewed_article');
	}

	protected function getXml() {
		return $this->xml;
	}

	/**
	 * @return array
	 */
	protected function getDataStructure() {
		return self::$dataStructure;
	}

	protected function onStatusChange(array $statusArray) {
		$sendMail = self::STATUS_DRAFT !== $statusArray['new'];

		if (!$sendMail) {
			return;
		}

		$title = $this->getTitle();
		$author = $this->hyphaUser->getAttribute('fullname');
		$linkToPage = $this->constructFullPath($this->pagename);
		if (self::STATUS_REVIEW === $statusArray['new']) {
			$subject = __('art-review-request-subject', array(
				"title" => $title,
				"author" => $author
			));
			$message = __('art-review-request-body', array(
				"link" => htmlspecialchars($linkToPage),
				"title" => htmlspecialchars($title),
				"author" => htmlspecialchars($author)
			));
		} else {
			$subject = __('art-status-update-subject', array(
				"status" => $statusArray['new'],
				"title" => $title
			));
			$message = __('art-status-update-body', array(
				"link" => htmlspecialchars($linkToPage),
				"title" => htmlspecialchars($title),
				"author" => htmlspecialchars($author),
				"status" => htmlspecialchars($statusArray['new'])
			));
		}
		$this->sendMail(getUserEmailList(), $subject, $message);
	}

	protected function onStatusChangeToPublish(array $statusArray) {
		if (self::STATUS_PUBLISHED !== $statusArray['new']) {
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

	protected function onDiscussionStarted(array $param) {
		$discussion = $this->getDocPageById($param['id']);
		if (!(bool)$discussion->getAttr(self::FIELD_NAME_DISCUSSION_BLOCKING)) {
			return;
		}

		$title = $this->getTitle();
		$author = $this->hyphaUser->getAttribute('fullname');
		$linkToPage = $this->constructFullPath($this->pagename);
		
		$comment = $discussion->getDoc()->lastChild->textContent;
		$subject = __('art-block-submitted-subject', array(
			"title" => $title
		));
		$message = __('art-block-submitted-body', array(
			"link" => htmlspecialchars($linkToPage),
			"title" => htmlspecialchars($title),
			"author" => htmlspecialchars($author),
			"comment" => htmlspecialchars($comment)
		));
		
		$this->sendMail(getUserEmailList(), $subject, $message);
	}

	protected function onDiscussionClosed(array $param) {
		$discussion = $this->getDocPageById($param['id']);
		if (!(bool)$discussion->getAttr(self::FIELD_NAME_DISCUSSION_BLOCKING) || !(bool)$discussion->getAttr(self::FIELD_NAME_DISCUSSION_BLOCK_RESOLVED)) {
			return;
		}

		$title = $this->getTitle();
		$author = $this->hyphaUser->getAttribute('fullname');
		$linkToPage = $this->constructFullPath($this->pagename);
		$subject = __('art-block-resolved-subject', array(
			"title" => $title
		));
		$message = __('art-block-resolved-body', array(
			"link" => htmlspecialchars($linkToPage),
			"title" => htmlspecialchars($title),
			"author" => htmlspecialchars($author)
		));
		$this->sendMail(getUserEmailList(), $subject, $message);
	}

	protected function onPublicComment(array $param) {
		$comment = $this->getDocPageById($param['id']);
		$pending = (bool)$comment->getAttr(self::FIELD_NAME_DISCUSSION_COMMENT_PENDING);
		if (!$pending) {
			// send message when comment was made by a user
			$this->sendNewCommentMessage($comment->getDoc());
			return;
		}

		$code = $comment->getAttr(self::FIELD_NAME_DISCUSSION_COMMENT_CONFIRM_CODE);
		$commentBody = $comment->getDoc()->textContent;

		$title = $this->getTitle();
		$path = str_replace(['{id}', '{code}'], [$param['id'], $code], self::PATH_DISCUSSION_COMMENT_CONFIRM);
		$linkToConfirm = $this->constructFullPath($this->pagename . '/' . $path);

		$email = $comment->getAttr(self::FIELD_NAME_DISCUSSION_COMMENTER_EMAIL);
		$subject = __('art-confirm-comment-subject');
		$message = __('art-confirm-comment-body', array(
			"sitename" => htmlspecialchars(hypha_getTitle()),
			"title" => htmlspecialchars($title),
			"comment" => nl2br(htmlspecialchars($commentBody)),
			"link" => htmlspecialchars($linkToConfirm)
		));
		$this->sendMail($email, $subject, $message);
	}

	protected function getTitle() {
		$title = $this->getDocPageByName(self::FIELD_NAME_TITLE);
		if ($title instanceof DocPage) {
			$title = $title->getText();
		}
		if ('' === $title) {
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
		$path = '//' . self::FIELD_NAME_DISCUSSION . '[@' . self::FIELD_NAME_DISCUSSION_BLOCKING . '="1"][@' . self::FIELD_NAME_DISCUSSION_BLOCK_RESOLVED . '!="1"]';
		/** @var NodeList $blockingDiscussions */
		$blockingDiscussions = $discussions->getDoc()->findXPath($path);
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
		$approveCollection = $approves->getDoc()->findXPath('//' . self::FIELD_NAME_APPROVE . '[@' . self::FIELD_NAME_USER . '="' . $userId . '"]');
		return $approveCollection->count() >= 1;
	}

	/**
	 * @param string $type
	 * @return DocPage[]
	 */
	private function getDiscussions($type) {
		$container = $type === 'review' ? self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER : self::FIELD_NAME_DISCUSSION_PUBLIC_CONTAINER;
		return $this->getContainerItems($container, self::FIELD_NAME_DISCUSSION);
	}

	/**
	 * @return DocPage[]
	 */
	private function getApproves() {
		return $this->getContainerItems(self::FIELD_NAME_APPROVE_CONTAINER, self::FIELD_NAME_APPROVE);
	}

	private static function getCommentPoster($comment) {
		$userid = $comment->getAttr(self::FIELD_NAME_USER);
		if ($userid) {
			$user = hypha_getUserById($userid);
			return ($user instanceof HyphaDomElement) ? $user->getAttribute('fullname') : $userid;
		}
		return $comment->getAttr(self::FIELD_NAME_DISCUSSION_COMMENTER_NAME);
	}

	/**
	 * @return array|null
	 */
	public function build() {
		$this->initialSetup();
		$this->html->writeToElement('pagename', $this->getTitle() . ' ' . asterisk($this->privateFlag));

		$this->beforeProcessRequest();
		return $this->processRequest();
	}

	protected function initialSetup() {
		$new = $this->ensureStructure();
		if (!$new) {
			return;
		}

		// force private flag
		if (!$this->privateFlag) {
			global $hyphaXml;
			$hyphaXml->lockAndReload();
			$this->replacePageListNode(hypha_getPage($this->language, $this->pagename));
			hypha_setPage($this->pageListNode, $this->language, $this->pagename, 'on');
			$this->privateFlag = true;
			$hyphaXml->saveAndUnlock();
		}
		$this->lockAndReload();

		// set initial status, create timestamp and title
		$context = $this->getDocPageByName(self::FIELD_NAME_CONTEXT);
		$context->setAttr(self::FIELD_NAME_STATUS, self::STATUS_DRAFT);
		$context->setAttr(self::FIELD_NAME_AUTHOR, $this->hyphaUser->getAttribute('fullname'));
		$context->setAttrToNow(self::FIELD_NAME_CREATED_AT);
		$title = $this->getDocPageByName(self::FIELD_NAME_TITLE);
		$title->setHtml(showPagename($this->pagename));
		$this->saveAndUnlock();
	}

	public function beforeProcessRequest() {
		$status = $this->getStatus();
		$main = $this->findBySelector('#main');
		$main->attr('class', $status);

		$statusHtml = '<span class="'.$status.'">' .  __('art-status-' . $status) . '</span>';
		if (self::STATUS_PUBLISHED !== $status) {
			if (self::STATUS_REVIEW === $status) {
				$statusHtml .= ', <span class="approves">' . $this->getApproveCount() . ' '.__('art-approve(s)') . '</span>, <span class="blocks">' . $this->getBlockingDiscussionsCount() . ' ' . __('art-unresolved-block(s)') . '</span>';
			}
			$titleElement = $this->findBySelector('#pagename');
			$titleElement->after('<div class="review-info">' . $statusHtml . '</div>');
		}
	}

	protected function processRequest() {
		// By default a user can preform any action
		$valid = isUser();
		if (!isUser()) {
			// Non users are limited to preform certain actions
			$valid = null === $this->getArg(0);
			if (self::PATH_DISCUSSION === $this->getArg(0) && self::FIELD_NAME_DISCUSSION_PUBLIC_CONTAINER === $this->getArg(1)) {
				$valid = true;
			}
			if (self::PATH_DISCUSSIONS === $this->getArg(0) && self::PATH_COMMENT === $this->getArg(2)) {
				$valid = true;
			}
			if (self::PATH_COMMENT === $this->getArg(0) && self::PATH_CONFIRM === $this->getArg(2)) {
				$valid = true;
			}
		}
		// Only admins can delete a page
		if (self::PATH_DELETE === $this->getArg(0) && !isAdmin()) {
			$valid = false;
		}
		if (!$valid) {
			$msg = isUser() ? 'art-insufficient-rights-to-preform-action' : 'art-login-preform-action';
			notify('error', __($msg));

			return ['redirect', $this->constructFullPath($this->pagename)];
		}

		switch ($this->getArg(0)) {
			default:
			case null:
				return $this->indexAction();
			case self::PATH_EDIT:
				return $this->editAction();
			case self::PATH_DELETE:
				return $this->deleteAction();
			case self::PATH_APPROVE:
				return $this->approveAction();
			case self::PATH_DISCUSSION:
				return $this->discussionAction($this->getArg(1));
			case self::PATH_STATUS_CHANGE_FIRST_ARG:
				return $this->statusChangeAction($this->getArg(1), $this->getArg(2));
			case self::PATH_DISCUSSIONS:
				switch ($this->getArg(2)) {
					case self::PATH_COMMENT:
						return $this->discussionCommentAction($this->getArg(1));
					case self::PATH_CLOSED:
						return $this->discussionClosedAction($this->getArg(1));
				}
				break;
			case self::PATH_COMMENT:
				switch ($this->getArg(2)) {
					case self::PATH_CONFIRM:
						return $this->commentConfirmAction($this->getArg(1));
				}
				break;
		}

		return null;
	}

	protected function commentConfirmAction($commentId) {
		$code = isset($_GET['code']) ? $_GET['code'] : null;
		if (null == $code) {
			notify('error', __('art-missing-arguments'));

			return null;
		}

		$this->lockAndReload();

		/** @var NodeList $commentCollection */
		$commentCollection = $this->xml->findXPath('//' . self::FIELD_NAME_DISCUSSION_COMMENT . '[@'.self::FIELD_NAME_DISCUSSION_COMMENT_CONFIRM_CODE.'="' . $code . '"]');
		$comment = $commentCollection->first();
		if (!$comment instanceof HyphaDomElement) {
			$this->xml->unlock();
			notify('error', __('art-invalid-code'));

			return null;
		}

		if (!(bool)$comment->getAttribute(self::FIELD_NAME_DISCUSSION_COMMENT_PENDING)) {
			$this->xml->unlock();
			notify('success', ucfirst(__('art-comment-posted')));
			return ['redirect', $this->constructFullPath($this->pagename)];
		}

		$comment->setAttribute(self::FIELD_NAME_DISCUSSION_COMMENT_PENDING, false);
		$this->xml->saveAndUnlock();

		notify('success', ucfirst(__('art-comment-posted')));

		// send message when comment was confirmed by a visitor
		$this->sendNewCommentMessage($comment);

		return ['redirect', $this->constructFullPath($this->pagename)];
	}

	protected function sendNewCommentMessage($comment) {
		// send fresh readers comment to all users
		$title = $this->getTitle();
		$linkToPage = $this->constructFullPath($this->pagename);
		$name = self::getCommentPoster($comment);
		$commentBody = $comment->getText();
		
		$subject = __('art-comment-subject', array(
			"name" => $name,
			"title" => $title
		));
		$message = __('art-comment-body', array(
			"name" => htmlspecialchars($name),
			"link" => htmlspecialchars($linkToPage),
			"title" => htmlspecialchars($title),
			"comment" => nl2br(htmlspecialchars($commentBody))
		));
		$this->sendMail(getUserEmailList(), $subject, $message);
	}

	public function indexAction() {
		// add edit button for registered users
		$status = $this->getStatus();

		if (isUser()) {
			$commands = $this->findBySelector('#pageCommands');
			$commandsAtEnd = $this->findBySelector('#pageEndCommands');
			$commands->append($this->makeActionButton(__('edit'), self::PATH_EDIT));

			// the status change from review to approved is done automatically
			if (self::STATUS_REVIEW === $status) {
				$userId = $this->hyphaUser->getAttribute('id');
				if (!$this->hasUserApproved($userId)) {
					$commandsAtEnd->append($this->makeActionButton(__('art-approve'), self::PATH_APPROVE));
				}
			} else {
				foreach ($this->statusMtx[$status] as $newStatus => $option) {
					$path = str_replace(['{new_status}', '{current_status}'], [$newStatus, $status], self::PATH_STATUS_CHANGE);
					$commands->append($this->makeActionButton(__($option), $path));
				}
			}
			if (isAdmin()) {
				$path = $this->language . '/' . $this->pagename . '/' . self::PATH_DELETE;
				$commands->append(makeButton(__('delete'), 'if(confirm(\'' . __('sure-to-delete') . '\'))' . makeAction($path, self::FORM_CMD_DELETE, '')));
			}
		}

		// display page name and text
		$context = $this->getDocPageByName(self::FIELD_NAME_CONTEXT);
		$content = $this->getDocPageByName(self::FIELD_NAME_CONTENT);

		$author = $context->getAttr(self::FIELD_NAME_AUTHOR);

		/** @var HyphaDomElement $main */
		$main = $this->findBySelector('#main');

		$discussions = $this->getDocPageByName(self::FIELD_NAME_DISCUSSION_PUBLIC_CONTAINER);

		/** @var NodeList $approveCollection */
		$commentCollection = $discussions->getDoc()->findXPath('.//' . self::FIELD_NAME_DISCUSSION_COMMENT . '[not(@pending="1")]');

		$commentCount = $commentCollection->count();
		if ($commentCount > 0) {
			if ($commentCount == 1) {
				$commentCountText = __('art-public-comment-count-singular', ['count' => $commentCount]);
			} else {
				$commentCountText = __('art-public-comment-count-plural', ['count' => $commentCount]);
			}

			$this->findBySelector('#pagename')->before('<div class="number_of_comments">'.$commentCountText.'</div>');
		}

		if ($author) {
			$main->append('<div class="author">' . __('art-by') . ' ' . htmlspecialchars($author) . '</div>');
		}

		$article = $content->getChild(self::FIELD_NAME_TEXT)->getHtml();
		$main->append('<div class="article">'.$article.'</div>');

		$method = $context->getChild(self::FIELD_NAME_METHOD)->getHtml();
		if ($method) {
			/** @var HyphaDomElement $methodContainer */
			$methodContainer = $this->xml->createElement('div');
			$methodContainer->attr('class', 'method');
			$methodContainer->append('<h2>' . __('art-method') . '</h2>');
			$methodContainer->append($method);
			$main->append($methodContainer);
		}

		$sources = $this->getDocPageByName(self::FIELD_NAME_SOURCES)->getHtml();
		if (!empty($sources)) {
			$main->append('<div class="sources"><h2>' . __('art-sources') . '</h2>' . $sources . '</div>');
		}

		if (isUser() && self::STATUS_DRAFT !== $status) {
			/** @var HyphaDomElement $discussionsContainer */
			$discussionsContainer = $this->xml->createElement('div');
			$discussionsContainer->attr('class', 'review-comments-wrapper');
			$discussionsContainer->append('<h2>' . __('art-review-comments') . '</h2>');
			$this->updateDiscussionsContainer('review', $discussionsContainer);
			$main->append($discussionsContainer);
			$main->append($this->getApprovesContainer());
		}

		if (self::STATUS_PUBLISHED === $status) {
			/** @var HyphaDomElement $discussionsContainer */
			$discussionsContainer = $this->xml->createElement('div');
			$discussionsContainer->attr('class', 'public-comments-wrapper');
			$discussionsContainer->append('<h2>' . __('art-comments') . '</h2>');
			$this->updateDiscussionsContainer('public', $discussionsContainer);
			$main->append($discussionsContainer);
		}

		return null;
	}

	/**
	 * @param HyphaDomElement $discussionsContainer
	 * @param string $type
	 */
	private function updateDiscussionsContainer($type, $discussionsContainer) {
		$discussions = $this->getDiscussions($type);

		// in case of no discussions
		if (empty($discussions)) {
			/** @var HyphaDomElement $list */
			$list = $this->xml->createElement('ul');
			$list->append('<li>' . __('art-no-comments-yet') . '</li>');
			$discussionsContainer->append($list);
		}

		$status = $this->getStatus();

		// [open => [blocking, non-blocking], closed => [blocking, non-blocking]]
		$reviewCommentContainersSorted = [0 => [0 => [], 1 => [],], 1 => [0 => [], 1 => [],],];

		foreach ($discussions as $discussion) {
			$blocking = (bool)$discussion->getAttr(self::FIELD_NAME_DISCUSSION_BLOCKING);
			$closed = (bool)$discussion->getAttr(self::FIELD_NAME_DISCUSSION_CLOSED);
			$resolved = (bool)$discussion->getAttr(self::FIELD_NAME_DISCUSSION_BLOCK_RESOLVED);

			// create discussion container
			/** @var HyphaDomElement $list */
			$list = $this->xml->createElement('ul');

			foreach ($discussion->getChildren() as $comments) {
				/** @var DocPage[] $comments */
				if (!is_array($comments)) {
					$comments = [$comments];
				}
				$firstComment = true;
				foreach ($comments as $comment) {
					if (self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER !== $type && (bool)$comment->getAttr(self::FIELD_NAME_DISCUSSION_COMMENT_PENDING)) {
						continue;
					}
					$createdAt = date('j-m-y, H:i', ltrim($comment->getAttr(self::FIELD_NAME_CREATED_AT), 't'));
					$committerName = self::getCommentPoster($comment);
					$html = nl2br(htmlspecialchars($comment->getText()));
					$html .= '<p>' . __('art-by') . ' <strong>' . htmlspecialchars($committerName) . '</strong> ' . __('art-at') . ' ' . htmlspecialchars($createdAt);
					if (self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER !== $type && isUser()) {
						$committerEmail = $comment->getAttr(self::FIELD_NAME_DISCUSSION_COMMENTER_EMAIL);
						$html .= ' <span> | ' . __('art-email') . ': <a href="mailto:' . htmlspecialchars($committerEmail) . '">' . htmlspecialchars($committerEmail) . '</a></span>';
					}
					if ($firstComment) {
						if ($blocking) {
							$html .= ' | ' . ($resolved ? __('art-is-resolved') : __('art-is-blocking'));
						}
					}
					$html .= '</p>';
					$list->append('<li ' . ($firstComment ? 'class="first-comment"' : '') . '>' . $html . '</li>');
					$firstComment = false;
				}
			}

			if (!$list->hasChildNodes()) {
				// if there are no comments to be displayed, all discussion parts do not need to be displayed.
				continue;
			}

			/** @var HyphaDomElement $reviewCommentContainer */
			$reviewCommentContainer = $this->xml->createElement('div');
			$class = 'review-comment-wrapper collapsed';
			foreach (['blocking' => $blocking, 'resolved' => $resolved, 'closed' => $closed] as $name => $isTrue) {
				if ($isTrue) {
					$class .= ' ' . $name;
				}
			}
			$reviewCommentContainer->attr('class', $class);
			$reviewCommentContainer->append($list);

			if (self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER === $type) {
				$msgId = null;
				if ($blocking) {
					if (!$resolved && ($discussion->getAttr(self::FIELD_NAME_USER) === $this->hyphaUser->getAttribute('id') || isAdmin())) {
						$msgId = __('art-set-as-resolved');
					}
				} elseif (!$closed) {
					$msgId = __('art-set-as-closed');
				}
				if (null !== $msgId) {
					$path = str_replace('{id}', $discussion->getId(), self::PATH_DISCUSSION_CLOSED);
					$list->append('<p>' . $this->makeActionButton(__($msgId), $path, self::FORM_CMD_DISCUSSION_CLOSED) . '</p>');
				}
			}

			// display comment form if the discussion is still open
			if (!$closed) {
				$replyForm = $this->createDiscussionCommentForm($discussion);
				$list->append($replyForm->elem->children());
			}

			$reviewCommentContainersSorted[(int)$closed][(int)!$blocking][] = $reviewCommentContainer;
		}

		foreach ($reviewCommentContainersSorted as $openClosedSorted) {
			foreach ($openClosedSorted as $blockingNonBLockingSorted) {
				foreach ($blockingNonBLockingSorted as $reviewCommentContainer) {
					$discussionsContainer->append($reviewCommentContainer);
				}
			}
		}

		$commentForm = $this->createDiscussionForm($type);
		$discussionsContainer->append($commentForm->elem->children());
	}

	private function getApprovesContainer() {
		/** @var HyphaDomElement $approvesContainer */
		$approvesContainer = $this->xml->createElement('div');
		$approvesContainer->attr('class', 'approves');
		$approves = $this->getApproves();
		$approvesContainer->append('<h2>' . __('art-approves') . '</h2>');
		/** @var HyphaDomElement $list */
		$list = $this->xml->createElement('ul');
		foreach ($approves as $approve) {
			$createdAt = date('j-m-y, H:i', ltrim($approve->getAttr(self::FIELD_NAME_CREATED_AT), 't'));
			$html = $approve->getHtml();
			$approverId = $approve->getAttr(self::FIELD_NAME_USER);
			$approver = hypha_getUserById($approverId);
			$approverName = $approver instanceof HyphaDomElement ? $approver->getAttribute('fullname') : $approverId;
			$html .= '<p>' . __('art-by') . ' <strong>' . $approverName . '</strong> ' . __('art-at') . ' ' . $createdAt . '</p>';
			$list->append('<li>' . $html . '</li>');
		}
		if (empty($approves)) {
			$list->append('<li>' . __('art-no-approves-yet') . '</li>');
		}
		$approvesContainer->append($list);
		return $approvesContainer;
	}

	public function editAction() {
		// check if form is posted and get form data
		$formPosted = $this->isPosted(self::FORM_CMD_EDIT);
		if ($formPosted) {
			$formData = $_POST;
		} else {
			$formData = [
				self::FIELD_NAME_TITLE => html_entity_decode(strip_tags($this->getDocPageByName(self::FIELD_NAME_TITLE)->getText())),
				self::FIELD_NAME_AUTHOR => $this->getAttr(self::FIELD_NAME_CONTEXT, self::FIELD_NAME_AUTHOR),
				self::FIELD_NAME_TEXT => $this->getDocPageByName(self::FIELD_NAME_TEXT)->getHtml(),
				self::FIELD_NAME_EXCERPT => $this->getDocPageByName(self::FIELD_NAME_EXCERPT)->getHtml(),
				self::FIELD_NAME_METHOD => $this->getDocPageByName(self::FIELD_NAME_METHOD)->getHtml(),
				self::FIELD_NAME_SOURCES => $this->getDocPageByName(self::FIELD_NAME_SOURCES)->getHtml(),
			];
		}

		// create form
		$form = $this->createEditForm($formData);

		// process form if it was posted
		if ($formPosted && empty($form->errors)) {
			$this->lockAndReload();
			$author = $form->dataFor(self::FIELD_NAME_AUTHOR);
			$this->getDocPageByName(self::FIELD_NAME_CONTEXT)->setAttr(self::FIELD_NAME_AUTHOR, $author);
			$title = $this->getDocPageByName(self::FIELD_NAME_TITLE);
			$title->setText($form->dataFor(self::FIELD_NAME_TITLE));
			$text = $this->getDocPageByName(self::FIELD_NAME_TEXT);
			$text->setHtml($form->dataFor(self::FIELD_NAME_TEXT), true);
			$excerpt = $this->getDocPageByName(self::FIELD_NAME_EXCERPT);
			$excerpt->setHtml($form->dataFor(self::FIELD_NAME_EXCERPT), true);
			$method = $this->getDocPageByName(self::FIELD_NAME_METHOD);
			$method->setHtml($form->dataFor(self::FIELD_NAME_METHOD), true);
			$sources = $this->getDocPageByName(self::FIELD_NAME_SOURCES);
			$sources->setHtml($form->dataFor(self::FIELD_NAME_SOURCES), true);

			$this->saveAndUnlock();

			notify('success', ucfirst(__('art-successfully-updated')));
			return ['redirect', $this->constructFullPath($this->pagename)];
		}

		// update the form dom so that error can be displayed, if there are any
		$form->updateDom();

		$this->findBySelector('#main')->append($form->elem->children());
		return null;
	}

	public function deleteAction() {
		// check if form is posted and get form data
		$formPosted = $this->isPosted(self::FORM_CMD_DELETE);
		if (!$formPosted) {
			return null;
		}

		global $hyphaUrl;

		$this->deletePage();

		notify('success', ucfirst(__('page-successfully-deleted')));
		return ['redirect', $hyphaUrl];
	}

	public function discussionAction($type) {
		// check if form is posted and get form data
		$formPosted = $this->isPosted(self::FORM_CMD_DISCUSSION);
		$formData = $formPosted ? $_POST : [];

		$this->lockAndReload();
		$review = self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER === $type;

		// create form
		$form = $this->createDiscussionForm($type, $formData);
		if (!$review && !isUser()) {
			$form->validateRequiredField(self::FIELD_NAME_DISCUSSION_COMMENTER_NAME);
			$form->validateRequiredField(self::FIELD_NAME_DISCUSSION_COMMENTER_EMAIL);
			$form->validateEmailField(self::FIELD_NAME_DISCUSSION_COMMENTER_EMAIL);
		}
		$form->validateRequiredField(self::FIELD_NAME_DISCUSSION_COMMENT . '/new_' . $type);

		// process form if it was posted
		if ($formPosted && empty($form->errors)) {
			$container = $review ? self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER : self::FIELD_NAME_DISCUSSION_PUBLIC_CONTAINER;
			$discussions = $this->getDocPageByName($container);
			$discussion = $discussions->createChild(self::FIELD_NAME_DISCUSSION);
			$blocking = $form->dataFor(self::FIELD_NAME_DISCUSSION_BLOCKING) !== null;
			$discussion->setAttr(self::FIELD_NAME_DISCUSSION_BLOCKING, $blocking);
			if ($review) {
				$discussion->setAttr(self::FIELD_NAME_USER, $this->hyphaUser->getAttribute('id'));
			}
			if ($blocking) {
				$discussion->setAttr(self::FIELD_NAME_DISCUSSION_BLOCK_RESOLVED, false);
			}
			$discussion->setAttr(self::FIELD_NAME_DISCUSSION_CLOSED, false);
			$commentText = $form->dataFor(self::FIELD_NAME_DISCUSSION_COMMENT . '/new_' . $type);
			$comment = $this->createDiscussionComment($discussion, $commentText, $form);
			$this->saveAndUnlock();
			$this->resetDocPagesMtx();
			$pending = (bool)$comment->getAttr(self::FIELD_NAME_DISCUSSION_COMMENT_PENDING);
			if (!$review)
				$this->fireEvent(self::EVENT_PUBLIC_COMMENT, ['id' => $comment->getId()]);
			$this->fireEvent(self::EVENT_DISCUSSION_STARTED, ['id' => $discussion->getId()]);
			// TODO: the event handler sends the
			// confirmation mail, but we notify the user.
			// Would be better to have more coupling between
			// those.
			if ($pending)
				notify('success', ucfirst(__('art-comment-pending')));
			else
				notify('success', ucfirst(__('art-comment-posted')));
			return ['redirect', $this->constructFullPath($this->pagename)];
		}
		$this->unlock();

		// update the form dom so that error can be displayed, if there are any
		$form->updateDom();

		$this->findBySelector('#main')->append($form->elem->children());
		return null;
	}

	/**
	 * @param string $discussionId
	 * @return array|null
	 */
	public function discussionCommentAction($discussionId) {
		// check if form is posted and get form data
		$formPosted = $this->isPosted(self::FORM_CMD_COMMENT);
		$formData = $formPosted ? $_POST : [];

		$this->lockAndReload();
		$discussion = $this->getDocPageById($discussionId);
		$review = $discussion->getParent()->getName() === self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER;

		// create form
		$form = $this->createDiscussionCommentForm($discussion, $formData);
		if (!$review && !isUser()) {
			$form->validateRequiredField(self::FIELD_NAME_DISCUSSION_COMMENTER_NAME);
			$form->validateRequiredField(self::FIELD_NAME_DISCUSSION_COMMENTER_EMAIL);
			$form->validateEmailField(self::FIELD_NAME_DISCUSSION_COMMENTER_EMAIL);
		}
		$form->validateRequiredField(self::FIELD_NAME_DISCUSSION_COMMENT . '/' . $discussionId);

		// process form if it was posted
		if ($formPosted && empty($form->errors)) {
			$commentText = $form->dataFor(self::FIELD_NAME_DISCUSSION_COMMENT . '/' . $discussionId);
			$comment = $this->createDiscussionComment($discussion, $commentText, $form);
			$this->saveAndUnlock();
			$this->resetDocPagesMtx();
			$pending = (bool)$comment->getAttr(self::FIELD_NAME_DISCUSSION_COMMENT_PENDING);
			if (!$review)
				$this->fireEvent(self::EVENT_PUBLIC_COMMENT, ['id' => $comment->getId()]);
			// TODO: the event handler sends the
			// confirmation mail, but we notify the user.
			// Would be better to have more coupling between
			// those.
			if ($pending)
				notify('success', ucfirst(__('art-comment-pending')));
			else
				notify('success', ucfirst(__('art-comment-posted')));
			return ['redirect', $this->constructFullPath($this->pagename)];
		}
		$this->unlock();

		// update the form dom so that error can be displayed, if there are any
		$form->updateDom();

		$this->findBySelector('#main')->append($form->elem->children());
		return null;
	}

	public function discussionClosedAction($discussionId) {
		$this->lockAndReload();
		/** @var HyphaDomElement $discussion */
		$discussion = $this->xml->document()->getElementById($discussionId);
		if ((bool)$discussion->attr(self::FIELD_NAME_DISCUSSION_CLOSED) === true) {
			$this->unlock();
			return null;
		}

		$userId = $this->hyphaUser->getAttribute('id');

		$blocking = (bool)$discussion->attr(self::FIELD_NAME_DISCUSSION_BLOCKING);
		if ($blocking) {
			if ($discussion->getAttribute(self::FIELD_NAME_USER) !== $userId && !isAdmin()) {
				notify('error', __('art-insufficient-rights-to-set-as-closed'));
				$this->unlock();
				return null;
			}
			$discussion->attr(self::FIELD_NAME_DISCUSSION_BLOCK_RESOLVED, true);
		}
		$discussion->attr(self::FIELD_NAME_DISCUSSION_CLOSED, true);
		$discussion->attr(self::FIELD_NAME_DISCUSSION_CLOSED_BY, $userId);
		$discussion->attr(self::FIELD_NAME_DISCUSSION_CLOSED_AT, 't' . time());

		$this->saveAndUnlock();
		$this->fireEvent(self::EVENT_DISCUSSION_CLOSED, ['id' => $discussion->getId()]);

		if ($blocking && $this->canBeApproved()) {
			$this->statusChangeAction(self::STATUS_APPROVED, self::STATUS_REVIEW, false);
		}

		notify('success', ucfirst(__('art-successfully-updated')));
		return ['redirect', $this->constructFullPath($this->pagename)];
	}

	public function statusChangeAction($status, $currentStatus, $notifyOnSuccess = true) {
		if (!isset($this->statusMtx[$currentStatus]) || !isset($this->statusMtx[$currentStatus][$status])) {
			notify('error', __('art-unsupported-status-change'));
			return null;
		}

		if ($this->getStatus() !== $currentStatus) {
			if ($this->getStatus() === $status) {
				notify('success', ucfirst(__('art-successfully-updated')));

				return ['redirect', $this->constructFullPath($this->pagename)];
			}
			notify('error', __('art-status-changed-in-between'));
			return null;
		}

		$this->lockAndReload();
		$context = $this->getDocPageByName(self::FIELD_NAME_CONTEXT);

		$context->setAttr(self::FIELD_NAME_STATUS, $status);
		$this->saveAndUnlock();

		$this->fireEvent(self::EVENT_STATUS_CHANGE, ['new' => $status, 'old' => $currentStatus]);

		if ($notifyOnSuccess) {
			notify('success', ucfirst(__('art-successfully-updated')));
		}

		return ['redirect', $this->constructFullPath($this->pagename)];
	}

	public function approveAction() {
		$this->lockAndReload();
		$approves = $this->getDocPageByName(self::FIELD_NAME_APPROVE_CONTAINER);

		$userId = $this->hyphaUser->getAttribute('id');

		if (!$this->hasUserApproved($userId)) {
			$approve = $approves->createChild(self::FIELD_NAME_APPROVE);
			$approve->setAttr(self::FIELD_NAME_USER, $userId);
			$approve->setAttr(self::FIELD_NAME_CREATED_AT, 't' . time());
			$this->saveAndUnlock();
			$this->resetDocPagesMtx();
			if ($this->canBeApproved()) {
				$this->statusChangeAction(self::STATUS_APPROVED, self::STATUS_REVIEW, false);
			}
		} else {
			$this->unlock();
		}

		notify('success', ucfirst(__('art-successfully-updated')));
		return ['redirect', $this->constructFullPath($this->pagename)];
	}

	/**
	 * @param DocPage $discussion
	 * @param string $text
	 * @param WymHTMLForm $form
	 * @return DocPage
	 */
	private function createDiscussionComment(DocPage $discussion, $text, WymHTMLForm $form) {
		$comment = $discussion->createChild(self::FIELD_NAME_DISCUSSION_COMMENT);
		$comment->setText($text);
		if (isUser()) {
			$comment->setAttr(self::FIELD_NAME_USER, $this->hyphaUser->getAttribute('id'));
		}
		$comment->setAttrToNow(self::FIELD_NAME_CREATED_AT);

		if (self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER !== $discussion->getParent()->getName()) {
			if (isUser()) {
				$userEmail = $this->hyphaUser->getAttribute('email');
				$userName = $this->hyphaUser->getAttribute('fullname');
			} else {
				$userEmail = $form->dataFor(self::FIELD_NAME_DISCUSSION_COMMENTER_EMAIL);
				$userName = $form->dataFor(self::FIELD_NAME_DISCUSSION_COMMENTER_NAME);
				$comment->setAttr(self::FIELD_NAME_DISCUSSION_COMMENT_PENDING, true);
				$comment->setAttr(self::FIELD_NAME_DISCUSSION_COMMENT_CONFIRM_CODE, $this->constructCode());
			}
			$comment->setAttr(self::FIELD_NAME_DISCUSSION_COMMENTER_EMAIL, $userEmail);
			$comment->setAttr(self::FIELD_NAME_DISCUSSION_COMMENTER_NAME, $userName);
		}

		return $comment;
	}

	/**
	 * @param array $data
	 *
	 * @return WymHTMLForm
	 */
	private function createEditForm(array $data = []) {
		$title = __('art-title');
		$titleFieldName = self::FIELD_NAME_TITLE;
		$author = __('art-author');
		$authorFieldName = self::FIELD_NAME_AUTHOR;
		$text = __('art-article');
		$textFieldName = self::FIELD_NAME_TEXT;
		$infoArtText = makeInfoButton('help-art-text');
		$excerpt = __('art-excerpt');
		$excerptFieldName = self::FIELD_NAME_EXCERPT;
		$infoArtExcerpt = makeInfoButton('help-art-excerpt');
		$method = __('art-method');
		$methodFieldName = self::FIELD_NAME_METHOD;
		$infoArtMethod = makeInfoButton('help-art-method');
		$sources = __('art-sources');
		$sourcesFieldName = self::FIELD_NAME_SOURCES;
		$html = <<<EOF
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<div class="input-wrapper field_type_text field_name_$titleFieldName">
					<strong><label for="$titleFieldName">$title</label></strong><br><input type="text" id="$titleFieldName" name="$titleFieldName" />
				</div>
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<div class="input-wrapper field_type_text field_name_$authorFieldName">
					<strong><label for="$authorFieldName"> $author </label></strong><br><input type="text" id="$authorFieldName" name="$authorFieldName" />
				</div>
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<div class="input-wrapper field_type_editor field_name_$textFieldName">
					<strong><label for="$textFieldName"> $text </label></strong>$infoArtText<br><editor name="$textFieldName"></editor>
				</div>
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<div class="input-wrapper field_type_editor field_name_$excerptFieldName">
					<strong><label for="$excerptFieldName"> $excerpt </label></strong> $infoArtExcerpt <br><editor name="$excerptFieldName"></editor>
				</div>
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<div class="input-wrapper field_type_editor field_name_$methodFieldName">
					<strong><label for="$methodFieldName"> $method </label></strong>$infoArtMethod <br><editor name="$methodFieldName"></editor>
				</div>
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<div class="input-wrapper field_type_editor field_name_$sourcesFieldName">
					<strong><label for="$sourcesFieldName"> $sources </label></strong><br><editor name="$sourcesFieldName"></editor>
				</div>
			</div>
EOF;
		/** @var HyphaDomElement $form */
		$form = $this->html->createElement('form');
		/** @var \DOMWrap\Element $elem */
		$elem = $form->html($html);

		// buttons
		$commands = $this->findBySelector('#pageCommands');
		$commands->append($this->makeActionButton(__('art-cancel')));
		$commands->append($this->makeActionButton(__('art-save'), self::PATH_EDIT, self::FORM_CMD_EDIT));
		$commandsAtEnd = $this->findBySelector('#pageEndCommands');
		$commandsAtEnd->append($this->makeActionButton(__('art-cancel')));
		$commandsAtEnd->append($this->makeActionButton(__('art-save'), self::PATH_EDIT, self::FORM_CMD_EDIT));

		return $this->createForm($elem, $data);
	}

	/**
	 * @param string $type
	 * @param array $data
	 *
	 * @return WymHTMLForm
	 */
	private function createDiscussionForm($type, array $data = []) {
		$html = $this->getCommentFormHtml($type);
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
		$type = $discussion->getParent()->getName();
		$html = $this->getCommentFormHtml($type, $discussion);
		/** @var HyphaDomElement $form */
		$form = $this->html->createElement('form');
		/** @var \DOMWrap\Element $elem */
		$elem = $form->html($html);

		return $this->createForm($elem, $data);
	}

	/**
	 * @param string $type
	 * @param null|DocPage $discussion
	 *
	 * @return string
	 */
	private function getCommentFormHtml($type, DocPage $discussion = null) {
		$comment = __('art-comment');
		$new = $discussion === null;
		$commentFieldName = self::FIELD_NAME_DISCUSSION_COMMENT . '/' . ($new ? 'new_' . $type : $discussion->getId());
		$html = <<<EOF
			<div class="new-comment $type">
			<div>
				<strong><label for="$commentFieldName"> $comment </label></strong><textarea name="$commentFieldName" id="$commentFieldName" cols="36" rows="4"></textarea>
			</div>
EOF;
		if ($new && self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER === $type && !in_array($this->getStatus(), [self::STATUS_APPROVED, self::STATUS_PUBLISHED])) {
			$blocking = __('art-blocking');
			$commentBlockingFieldName = self::FIELD_NAME_DISCUSSION_BLOCKING;
			$infoBlocking = makeInfoButton('help-blocking-comment');
			$html .= <<<EOF
			<div>
				<strong><label for="$commentBlockingFieldName"> $blocking </label></strong><input type="checkbox" name="$commentBlockingFieldName" id="$commentBlockingFieldName" /> $infoBlocking
			</div>
EOF;
		}

		if (!isUser()) {
			$name = __('art-name');
			$nameFieldName = self::FIELD_NAME_DISCUSSION_COMMENTER_NAME;
			$email = __('art-email');
			$emailFieldName = self::FIELD_NAME_DISCUSSION_COMMENTER_EMAIL;
			$unAnonymous =  __('art-comment-unanonymous');
			$html .= <<<EOF
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="$nameFieldName"> $name </label></strong><div class="label_suffix">$unAnonymous</div> <input type="text" id="$nameFieldName" name="$nameFieldName" />
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="$emailFieldName"> $email </label></strong> <input type="text" id="$emailFieldName" name="$emailFieldName" />
			</div>
EOF;
		}

		if ($new) {
			$path = str_replace('{type}', $type, self::PATH_DISCUSSION_TYPE);
		} else {
			$path = str_replace('{id}', $discussion->getId(), self::PATH_DISCUSSION_COMMENT);
		}
		$label = 'review' === $type ? __('art-add-review-comment') : __('art-add-comment');
		$command = $new ? self::FORM_CMD_DISCUSSION : self::FORM_CMD_COMMENT;
		$html .= $this->makeActionButton($label, $path, $command);
		$html .= '</div>';

		return $html;
	}

	private function sendMail($receivers, $subject, $message) {
		$style = 'body {margin-top:10px; margin-left: 10px; font-size:10pt; font-family: Sans; color:black; background-color:white;}';
		sendMail($receivers, hypha_getTitle() . ': '.$subject, $message, hypha_getEmail(), hypha_getTitle(), $style);
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
	protected function createForm(DOMElement $elem, array $data = []) {
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
	protected function makeActionButton($label, $path = null, $command = null, $argument = null) {
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
	protected function findBySelector($selector) {
		return $this->html->find($selector);
	}

	/**
	 * @todo [LRM]: move so it can be used throughout Hypha
	 * @param string $path
	 * @param null|string $language
	 *
	 * @return string
	 */
	protected function constructFullPath($path, $language = null) {
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
	protected function isPosted($command = null) {
		return 'POST' == $_SERVER['REQUEST_METHOD'] && (null == $command || $command == $_POST['command']);
	}
}
