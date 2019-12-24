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
	/** @var Xml */
	private $xml;

	const FIELD_NAME_USER = 'user';
	const FIELD_NAME_CREATED_AT = 'created_at';
	const FIELD_NAME_UPDATED_AT = 'updated_at';
	const FIELD_NAME_PUBLISHED_AT = 'published_at';

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

	const STATUS_NEWLY_CREATED = 'newly created';
	const STATUS_DRAFT = 'draft';
	const STATUS_REVIEW = 'review';
	const STATUS_APPROVED = 'approved';
	const STATUS_PUBLISHED = 'published';
	const STATUS_RETRACTED = 'retracted';

	const PATH_EDIT = 'edit';
	const PATH_DISCUSSIONS = 'discussions';
	const PATH_DISCUSSIONS_TYPE = 'discussions/{type}';
	const PATH_DISCUSSIONS_CLOSED = 'discussions/{id}/closed';
	const PATH_DISCUSSIONS_COMMENT = 'discussions/{id}/comment';
	const PATH_COMMENT = 'comment';
	const PATH_DISCUSSIONS_COMMENT_CONFIRM = 'comment/{id}/confirm?code={code}';

	const CMD_SAVE = 'save';
	const CMD_DELETE = 'delete';
	const CMD_STATUS_CHANGE = 'status_change';
	const CMD_STATUS_CHANGE_REVIEW = self::CMD_STATUS_CHANGE . '_' . self::STATUS_REVIEW;
	const CMD_STATUS_CHANGE_APPROVED = self::CMD_STATUS_CHANGE . '_' . self::STATUS_APPROVED;
	const CMD_STATUS_CHANGE_PUBLISHED = self::CMD_STATUS_CHANGE . '_' . self::STATUS_PUBLISHED;
	const CMD_APPROVE = 'approve';
	const CMD_DISCUSSION_STARTED = 'discussion_started';
	const CMD_DISCUSSION_CLOSED = 'discussion_closed';
	const CMD_COMMENT = 'comment';

	private $statusMtx = [
		self::STATUS_NEWLY_CREATED => [],
		self::STATUS_DRAFT => [self::STATUS_REVIEW => ['label' => 'art-start-review', 'cmd' => self::CMD_STATUS_CHANGE_REVIEW]],
		self::STATUS_REVIEW => [self::STATUS_APPROVED => ['label' => 'art-approve', 'cmd' => self::CMD_STATUS_CHANGE_APPROVED]],
		self::STATUS_APPROVED => [self::STATUS_PUBLISHED => ['label' => 'art-publish', 'cmd' => self::CMD_STATUS_CHANGE_PUBLISHED]],
		self::STATUS_PUBLISHED => [/*self::STATUS_RETRACTED => 'retract'*/], // retracted is not supported yet
		self::STATUS_RETRACTED => [/*self::STATUS_DRAFT => 'to_draft'*/], // "to draft" is not supported yet
	];

	public static function getDatatypeName() {
		return __('datatype.name.peer_reviewed_article');
	}

	/**
	 * @param DOMElement $pageListNode
	 * @param RequestContext $O_O
	 */
	public function __construct(DomElement $pageListNode, RequestContext $O_O) {
		parent::__construct($pageListNode, $O_O);
		$this->xml = new Xml(get_called_class(), Xml::multiLingualOff, Xml::versionsOff);
		$this->xml->loadFromFile('data/pages/' . $pageListNode->getAttribute('id'));
	}

	/**
	 * @param HyphaRequest $request
	 * @return array|string|null
	 */
	public function process(HyphaRequest $request) {
		$this->html->writeToElement('pagename', $this->getTitle() . ' ' . asterisk($this->privateFlag));

		$this->ensureStructure();

		switch ([$request->getView(), $request->getCommand()]) {
			case [null,                   null]:                              return $this->defaultView($request);
			case [null,                   self::CMD_DELETE]:                  return $this->deleteAction($request);
			case [null,                   self::CMD_STATUS_CHANGE_REVIEW]:    return $this->statusChangeAction($request, self::STATUS_REVIEW);
			case [null,                   self::CMD_STATUS_CHANGE_APPROVED]:  return $this->statusChangeAction($request, self::STATUS_APPROVED);
			case [null,                   self::CMD_STATUS_CHANGE_PUBLISHED]: return $this->statusChangeAction($request, self::STATUS_PUBLISHED);
			case [null,                   self::CMD_APPROVE]:                 return $this->approveAction($request);
			case [self::PATH_EDIT,        null]:                              return $this->editView($request);
			case [self::PATH_EDIT,        self::CMD_SAVE]:                    return $this->editAction($request);
			case [self::PATH_DISCUSSIONS, self::CMD_DISCUSSION_STARTED]:      return $this->discussionAction($request);
			case [self::PATH_DISCUSSIONS, self::CMD_COMMENT]:                 return $this->discussionCommentAction($request);
			case [self::PATH_DISCUSSIONS, self::CMD_DISCUSSION_CLOSED]:       return $this->discussionClosedAction($request);
			case [self::PATH_COMMENT,     null]:                              return $this->commentConfirmAction($request);
		}

		return '404';
	}

	/**
	 * Checks if the status is new and if so builds the structure and sets the status to draft.
	 */
	private function ensureStructure() {
		$status = $this->getStatus();

		if (self::STATUS_NEWLY_CREATED !== $status) {
			return;
		}

		$dataStructure = [
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
		$this->xml->lockAndReload();
		$build = function (HyphaDomElement $doc, array $structure) use (&$build) {
			foreach ($structure as $name => $children) {
				$doc->append($build($doc->getOrCreate($name), $children));
			}
			return $doc;
		};
		$build($this->xml->documentElement, $dataStructure);

		// force private flag
		if (!$this->privateFlag) {
			global $hyphaXml;
			$hyphaXml->lockAndReload();
			$this->replacePageListNode(hypha_getPage($this->language, $this->pagename));
			hypha_setPage($this->pageListNode, $this->language, $this->pagename, 'on');
			$this->privateFlag = true;
			$hyphaXml->saveAndUnlock();
		}

		// set initial status, author, timestamps and title
		/** @var HyphaDomElement $article */
		$article = $this->xml->find(self::FIELD_NAME_ARTICLE);
		$article->setAttr(self::FIELD_NAME_STATUS, self::STATUS_DRAFT);
		$article->setAttr(self::FIELD_NAME_AUTHOR, $this->O_O->getUser()->getAttribute('fullname'));
		$article->setAttr(self::FIELD_NAME_CREATED_AT, 't' . time());
		$article->setAttr(self::FIELD_NAME_UPDATED_AT, 't' . time());
		$article->setAttr(self::FIELD_NAME_PUBLISHED_AT, '');
		/** @var HyphaDomElement $title */
		$title = $this->xml->find(self::FIELD_NAME_TITLE);
		$title->setText(showPagename($this->pagename));
		$this->xml->saveAndUnlock();
	}

	/**
	 * Displays the article.
	 *
	 * @param HyphaRequest $request
	 * @return null
	 */
	public function defaultView(HyphaRequest $request) {
		$status = $this->getStatus();

		// add buttons for registered users
		if (isUser()) {
			/** @var HyphaDomElement $commands */
			$commands = $this->html->find('#pageCommands');
			/** @var HyphaDomElement $commandsAtEnd */
			$commandsAtEnd = $this->html->find('#pageEndCommands');
			$commands->append($this->makeActionButton(__('edit'), self::PATH_EDIT));

			// the status change from review to approved is done automatically
			if (self::STATUS_REVIEW === $status) {
				$userId = $this->O_O->getUser()->getAttribute('id');
				if (!$this->hasUserApproved($userId)) {
					$commandsAtEnd->append($this->makeActionButton(__('art-approve'), null, self::CMD_APPROVE));
				}
			} else {
				foreach ($this->statusMtx[$status] as $newStatus => $option) {
					$commands->append($this->makeActionButton(__($option['label']), null, $option['cmd']));
				}
			}
			if (isAdmin()) {
				$path = $this->language . '/' . $this->pagename;
				$commands->append(makeButton(__('delete'), 'if(confirm(\'' . __('sure-to-delete') . '\'))' . makeAction($path, self::CMD_DELETE, '')));
			}
		}

		// display page name and text
		/** @var HyphaDomElement $article */
		$article = $this->xml->find(self::FIELD_NAME_ARTICLE);
		/** @var HyphaDomElement $content */
		$content = $this->xml->find(self::FIELD_NAME_CONTENT);

		$author = $article->getAttr(self::FIELD_NAME_AUTHOR);
		$publishedTimestamp = ltrim($article->getAttr(self::FIELD_NAME_PUBLISHED_AT), 't');

		/** @var HyphaDomElement $main */
		$main = $this->html->find('#main');

		/** @var HyphaDomElement $discussions */
		$discussions = $this->xml->find(self::FIELD_NAME_DISCUSSION_PUBLIC_CONTAINER);

		/** @var NodeList $commentCollection */
		$commentCollection = $discussions->findXPath('.//' . self::FIELD_NAME_DISCUSSION_COMMENT . '[not(@pending="1")]');

		$commentCount = $commentCollection->count();
		if ($commentCount > 0) {
			if ($commentCount == 1) {
				$commentCountText = __('art-public-comment-count-singular', ['count' => $commentCount]);
			} else {
				$commentCountText = __('art-public-comment-count-plural', ['count' => $commentCount]);
			}

			$this->html->find('#pagename')->before('<div class="number_of_comments">'.$commentCountText.'</div>');
		}

		// Sharing links
		$shareDiv = $this->html->create('<div class="share-links">')->appendTo($main);
		$linkToPage = $this->constructFullPath($this->pagename);
		$themeImgPath = 'data/themes/' . Hypha::$data->theme . '/images';

		// Email
		$subject = __('share-email-subject', array("domain" => hypha_getTitle(), "title" => $this->getTitle()));
		$body = __('share-email-body', array("domain" => hypha_getTitle(), "title" => $this->getTitle(), "url" => $linkToPage));
		$shareDiv->append('<a href="mailto:?subject='.rawurlencode($subject).'&body='.rawurlencode($body).'"><div class="email-link"></div></a>');
		// Twitter
		$text = __('share-twitter', array("domain" => hypha_getTitle(), "title" => $this->getTitle(), "url" => $linkToPage));
		$shareDiv->append('<a href="https://twitter.com/intent/tweet?text='.rawurlencode($text).'&url='.rawurlencode($linkToPage).'" target="_blank"><div class="twitter-link"></div></a>');
		// Facebook
		$shareDiv->append('<a href="https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($linkToPage).'" target="_blank"><div class="facebook-link"></div></a>');

		if ($author) {
			$main->append('<div class="author">' . __('art-by') . ' ' . htmlspecialchars($author) . '</div>');
		}
		if ($publishedTimestamp) {
			/** @var HyphaDomElement $publish */
			$publish = $this->html->createElement('div');
			$publish->setAttribute('class', 'published_at');
			/** @var HyphaDomElement $publishDate */
			$publishDate = $this->html->createElement('span');
			$publishDate->setAttribute('class', 'date');
			$publishDate->text(date(__('art-date-format-date'), $publishedTimestamp));
			$publish->append($publishDate);
			/** @var HyphaDomElement $publishTime */
			$publishTime = $this->html->createElement('span');
			$publishTime->setAttribute('class', 'time');
			$publishTime->text(date(__('art-date-format-time'), $publishedTimestamp));
			$publish->append($publishTime);
			$main->append($publish);
		}

		$text = $content->find(self::FIELD_NAME_TEXT)->children();
		/** @var HyphaDomElement $div */
		$div = $this->html->createElement('div');
		$div->setAttribute('class', 'article');
		$div->append($text);
		$main->append($div);

		/** @var NodeList $method */
		$method = $this->xml->find(self::FIELD_NAME_METHOD)->children();
		if ($method->count()) {
			/** @var HyphaDomElement $methodContainer */
			$methodContainer = $this->html->createElement('div');
			$methodContainer->attr('class', 'method');
			$methodContainer->append('<h2>' . __('art-method') . '</h2>');
			$methodContainer->append($method);
			$main->append($methodContainer);
		}

		/** @var NodeList $sources */
		$sources = $this->xml->find(self::FIELD_NAME_SOURCES)->children();
		if ($sources->count()) {
			/** @var HyphaDomElement $div */
			$div = $this->html->createElement('div');
			$div->setAttribute('class', 'sources');
			$div->append('<h2>' . __('art-sources') . '</h2>');
			$div->append($sources);
			$main->append($div);
		}

		if (isUser() && self::STATUS_DRAFT !== $status) {
			/** @var HyphaDomElement $discussionsDomElement */
			$discussionsDomElement = $this->html->createElement('div');
			$discussionsDomElement->attr('class', 'review-comments-wrapper');
			$discussionsDomElement->append('<h2>' . __('art-review-comments') . '</h2>');
			$this->fillDiscussionsContainer('review', $discussionsDomElement);
			$main->append($discussionsDomElement);
			$main->append($this->createApprovesDomElement());
		}

		if (self::STATUS_PUBLISHED === $status) {
			/** @var HyphaDomElement $discussionsContainer */
			$discussionsDomElement = $this->html->createElement('div');
			$discussionsDomElement->attr('class', 'public-comments-wrapper');
			$discussionsDomElement->append('<h2>' . __('art-comments') . '</h2>');
			$this->fillDiscussionsContainer('public', $discussionsDomElement);
			$main->append($discussionsDomElement);
		}

		return null;
	}

	/**
	 * Deletes the article.
	 *
	 * @param HyphaRequest $request
	 * @return array
	 */
	public function deleteAction(HyphaRequest $request) {
		if (!isAdmin()) {
			return ['errors' => ['art-insufficient-rights-to-perform-action']];
		}

		$this->deletePage();

		notify('success', ucfirst(__('page-successfully-deleted')));
		return ['redirect', $request->getRootUrl()];
	}

	/**
	 * Displays the form to edit the article.
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
		$formData = [
			self::FIELD_NAME_TITLE => html_entity_decode(strip_tags($this->xml->find(self::FIELD_NAME_TITLE)->text())),
			self::FIELD_NAME_AUTHOR => $this->xml->find(self::FIELD_NAME_ARTICLE)->getAttr(self::FIELD_NAME_AUTHOR),
			self::FIELD_NAME_TEXT => $this->xml->find(self::FIELD_NAME_TEXT)->children(),
			self::FIELD_NAME_EXCERPT => $this->xml->find(self::FIELD_NAME_EXCERPT)->children(),
			self::FIELD_NAME_METHOD => $this->xml->find(self::FIELD_NAME_METHOD)->children(),
			self::FIELD_NAME_SOURCES => $this->xml->find(self::FIELD_NAME_SOURCES)->children(),
		];

		$form = $this->createEditForm($formData);

		return $this->editViewRender($request, $form);
	}

	/**
	 * Appends edit form to #main and adds buttons
	 *
	 * @param HyphaRequest $request
	 * @param WymHTMLForm $form
	 * @return null
	 */
	private function editViewRender(HyphaRequest $request, WymHTMLForm $form) {
		// update the form dom so that error can be displayed, if there are any
		$form->updateDom();

		/** @var HyphaDomElement $main */
		$main = $this->html->find('#main');
		$main->append($form);

		/** @var HyphaDomElement $commands */
		$commands = $this->html->find('#pageCommands');
		$commands->append($this->makeActionButton(__('art-cancel')));
		$commands->append($this->makeActionButton(__('art-save'), self::PATH_EDIT, self::CMD_SAVE));
		/** @var HyphaDomElement $commandsAtEnd */
		$commandsAtEnd = $this->html->find('#pageEndCommands');
		$commandsAtEnd->append($this->makeActionButton(__('art-cancel')));
		$commandsAtEnd->append($this->makeActionButton(__('art-save'), self::PATH_EDIT, self::CMD_SAVE));

		return null;
	}

	/**
	 * Updates the article with the posted data.
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

		// process form if there are no errors
		if (!empty($form->errors)) {
			return $this->editViewRender($request, $form);
		}

		$this->xml->lockAndReload();

		$author = $form->dataFor(self::FIELD_NAME_AUTHOR);
		$this->xml->find(self::FIELD_NAME_ARTICLE)->setAttr(self::FIELD_NAME_AUTHOR, $author);
		$this->xml->find(self::FIELD_NAME_ARTICLE)->setAttr(self::FIELD_NAME_UPDATED_AT, 't' . time());
		$this->xml->find(self::FIELD_NAME_TITLE)->setText($form->dataFor(self::FIELD_NAME_TITLE));
		$this->xml->find(self::FIELD_NAME_TEXT)->setHtml($form->dataFor(self::FIELD_NAME_TEXT));
		$this->xml->find(self::FIELD_NAME_EXCERPT)->setHtml($form->dataFor(self::FIELD_NAME_EXCERPT));
		$this->xml->find(self::FIELD_NAME_METHOD)->setHtml($form->dataFor(self::FIELD_NAME_METHOD));
		$this->xml->find(self::FIELD_NAME_SOURCES)->setHtml($form->dataFor(self::FIELD_NAME_SOURCES));

		$this->xml->saveAndUnlock();

		notify('success', ucfirst(__('art-successfully-updated')));
		return ['redirect', $this->constructFullPath($this->pagename)];
	}

	/**
	 * Changes the status of the article
	 *
	 * @param HyphaRequest $request
	 * @param string $newStatus
	 * @return array
	 */
	public function statusChangeAction(HyphaRequest $request, $newStatus) {
		if (!isUser()) {
			notify('error', __('login-to-perform-action'));
			return ['redirect', $this->constructFullPath($this->pagename)];
		}

		$success = $this->updateToNewStatus($newStatus);
		if ($success) {
			notify('success', ucfirst(__('art-successfully-updated')));
		}

		return ['redirect', $this->constructFullPath($this->pagename)];
	}

	/**
	 * Add an user approve to the article.
	 *
	 * Three approves results in an approval status
	 *
	 * @param HyphaRequest $request
	 * @return array
	 */
	public function approveAction(HyphaRequest $request) {
		if (!isUser()) {
			notify('error', __('login-to-perform-action'));
			return ['redirect', $this->constructFullPath($this->pagename)];
		}

		$this->xml->lockAndReload();

		/** @var NodeList $approves */
		$approves = $this->xml->find(self::FIELD_NAME_APPROVE_CONTAINER);

		$userId = $this->O_O->getUser()->getAttribute('id');

		if (!$this->hasUserApproved($userId)) {
			/** @var HyphaDomElement $approve */
			$approve = $this->xml->createElement(self::FIELD_NAME_APPROVE);
			$approves->append($approve);
			$approve->setAttr(self::FIELD_NAME_USER, $userId);
			$approve->setAttr(self::FIELD_NAME_CREATED_AT, 't' . time());
			$this->xml->saveAndUnlock();
			if ($this->canBeSetAsApproved()) {
				$this->updateToNewStatus(self::STATUS_APPROVED);
			}
		} else {
			$this->xml->unlock();
		}

		notify('success', ucfirst(__('art-successfully-updated')));
		return ['redirect', $this->constructFullPath($this->pagename)];
	}

	/**
	 * Starts a review or public discussion.
	 *
	 * @param HyphaRequest $request
	 * @return array|null
	 */
	public function discussionAction(HyphaRequest $request) {
		$type = $request->getArg(1);
		if ($type === null) {
			notify('error', __('missing-arguments'));
			return ['redirect', $this->constructFullPath($this->pagename)];
		}
		$review = self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER === $type;
		$public = self::FIELD_NAME_DISCUSSION_PUBLIC_CONTAINER === $type;
		if (!$review && !$public) {
			notify('error', __('invalid-argument'));
			return ['redirect', $this->constructFullPath($this->pagename)];
		}

		$dataFieldSuffix = '/new_' . $type;

		// create form to start a discussion
		$form = $this->createCommentForm($type, $request->getPostData());
		if (!$review && !isUser()) {
			$form->validateRequiredField(self::FIELD_NAME_DISCUSSION_COMMENTER_NAME . $dataFieldSuffix);
			$form->validateRequiredField(self::FIELD_NAME_DISCUSSION_COMMENTER_EMAIL . $dataFieldSuffix);
			$form->validateEmailField(self::FIELD_NAME_DISCUSSION_COMMENTER_EMAIL . $dataFieldSuffix);
		}
		$form->validateRequiredField(self::FIELD_NAME_DISCUSSION_COMMENT . $dataFieldSuffix);

		// process form if it was posted
		if (!empty($form->errors)) {
			return $this->discussionRender($request, $form, $type);
		}

		$this->xml->lockAndReload();

		// store discussion and comment
		$discussions = $this->xml->find($type);
		$discussion = $this->createDiscussionDomElement($discussions, $form, $dataFieldSuffix);
		$comment = $this->createDiscussionCommentDomElement($discussion, $form, $dataFieldSuffix);
		$this->xml->saveAndUnlock();

		$blocking = (bool)$discussion->getAttr(self::FIELD_NAME_DISCUSSION_BLOCKING);
		$pending = (bool)$comment->getAttr(self::FIELD_NAME_DISCUSSION_COMMENT_PENDING);

		// send notification mails if needed
		if ($review && $blocking) {
			$this->sendBlockingDiscussionMail($discussion);
		}
		if (!$review && $pending) {
			$this->sendCommentConfirmMail($comment);
		}
		if (!$review && !$pending) {
			$this->sendNewCommentMail($comment);
		}

		if ($pending) {
			notify('success', ucfirst(__('art-comment-pending')));
		} else {
			notify('success', ucfirst(__('art-comment-posted')));
		}

		return ['redirect', $this->constructFullPath($this->pagename)];
	}

	/**
	 * @param HyphaRequest $request
	 * @param WymHTMLForm $form
	 * @param string $type
	 * @param null|HyphaDomElement $discussion
	 * @return null
	 */
	private function discussionRender(HyphaRequest $request, WymHTMLForm $form, $type, HyphaDomElement $discussion = null) {
		// update the form dom so that error can be displayed, if there are any
		$form->updateDom();

		/** @var HyphaDomElement $main */
		$main = $this->html->find('#main');
		$main->append($form);

		$actionButton = $this->makeDiscussionActionButton($type, $discussion);

		$main->append($actionButton);

		return null;
	}

	private function makeDiscussionActionButton($type, HyphaDomElement $discussion = null) {
		$new = $discussion === null;
		$review = self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER === $type;

		if ($new) {
			$path = str_replace('{type}', $type, self::PATH_DISCUSSIONS_TYPE);
			$command = self::CMD_DISCUSSION_STARTED;
		} else {
			$path = str_replace('{id}', $discussion->getId(), self::PATH_DISCUSSIONS_COMMENT);
			$command = self::CMD_COMMENT;
		}

		$label = $review ? __('art-add-review-comment') : __('art-add-comment');

		return $this->makeActionButton($label, $path, $command);
	}

	/**
	 * Adds a comment on a review or public discussion.
	 *
	 * @param HyphaRequest $request
	 * @return array|null
	 */
	public function discussionCommentAction(HyphaRequest $request) {
		$discussionId = $request->getArg(1);

		$this->xml->lockAndReload();
		/** @var HyphaDomElement $discussion */
		$discussion = $this->xml->document()->getElementById($discussionId);
		if (!$discussion instanceof HyphaDomElement) {
			notify('error', __('not-found')); // 404
			return null;
		}

		/** @var HyphaDomElement $discussions */
		$discussions = $discussion->parent();
		$type = $discussions->nodeName;
		$review = self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER === $type;

		$dataFieldSuffix = '/' . $discussionId;

		// create form to comment on a discussion
		$type = $discussion->parent()->nodeName;
		$form = $this->createCommentForm($type, $request->getPostData(), $discussion);
		if (!$review && !isUser()) {
			$form->validateRequiredField(self::FIELD_NAME_DISCUSSION_COMMENTER_NAME . $dataFieldSuffix);
			$form->validateRequiredField(self::FIELD_NAME_DISCUSSION_COMMENTER_EMAIL . $dataFieldSuffix);
			$form->validateEmailField(self::FIELD_NAME_DISCUSSION_COMMENTER_EMAIL . $dataFieldSuffix);
		}
		$form->validateRequiredField(self::FIELD_NAME_DISCUSSION_COMMENT . $dataFieldSuffix);

		// process form if it was posted
		if (!empty($form->errors)) {
			$this->xml->unlock();
			return $this->discussionRender($request, $form, $type, $discussion);
		}

		$comment = $this->createDiscussionCommentDomElement($discussion, $form, $dataFieldSuffix);
		$this->xml->saveAndUnlock();
		$pending = (bool)$comment->getAttr(self::FIELD_NAME_DISCUSSION_COMMENT_PENDING);
		if (!$review && $pending) {
			$this->sendCommentConfirmMail($comment);
		}
		if (!$review && !$pending) {
			$this->sendNewCommentMail($comment);
		}

		if ($pending) {
			notify('success', ucfirst(__('art-comment-pending')));
		} else {
			notify('success', ucfirst(__('art-comment-posted')));
		}

		return ['redirect', $this->constructFullPath($this->pagename)];
	}

	/**
	 * Closes a review or public discussion.
	 *
	 * @param HyphaRequest $request
	 * @return array|null
	 */
	public function discussionClosedAction(HyphaRequest $request) {
		$this->xml->lockAndReload();

		/** @var HyphaDomElement $discussion */
		$discussionId = $request->getArg(1);
		$discussion = $this->xml->document()->getElementById($discussionId);
		if (!$discussion instanceof HyphaDomElement) {
			$this->xml->unlock();
			notify('error', __('not-found')); // 404
			return null;
		}

		if ((bool)$discussion->attr(self::FIELD_NAME_DISCUSSION_CLOSED) === true) {
			$this->xml->unlock();
			return null;
		}

		$userId = $this->O_O->getUser()->getAttribute('id');

		$blocking = (bool)$discussion->attr(self::FIELD_NAME_DISCUSSION_BLOCKING);
		if ($blocking) {
			if ($discussion->getAttribute(self::FIELD_NAME_USER) !== $userId && !isAdmin()) {
				notify('error', __('art-insufficient-rights-to-set-as-closed'));
				$this->xml->unlock();
				return null;
			}
			$discussion->attr(self::FIELD_NAME_DISCUSSION_BLOCK_RESOLVED, true);
		}
		$discussion->attr(self::FIELD_NAME_DISCUSSION_CLOSED, true);
		$discussion->attr(self::FIELD_NAME_DISCUSSION_CLOSED_BY, $userId);
		$discussion->attr(self::FIELD_NAME_DISCUSSION_CLOSED_AT, 't' . time());

		$this->xml->saveAndUnlock();
		if ($blocking) {
			$this->sendResolvedBlockingDiscussionMail();
			if ($this->canBeSetAsApproved()) {
				$this->updateToNewStatus(self::STATUS_APPROVED);
			}
		}

		notify('success', ucfirst(__('art-successfully-updated')));
		return ['redirect', $this->constructFullPath($this->pagename)];
	}

	/**
	 * Confirms the comment that was submitted by a commenter.
	 *
	 * @param HyphaRequest $request
	 * @return array|null
	 */
	public function commentConfirmAction(HyphaRequest $request) {
		$code = isset($_GET['code']) ? $_GET['code'] : null;
		if (null == $code) {
			notify('error', __('missing-arguments'));
			return null;
		}

		$this->xml->lockAndReload();

		/** @var NodeList $commentCollection */
		$path = '//' . self::FIELD_NAME_DISCUSSION_COMMENT . '[@'.self::FIELD_NAME_DISCUSSION_COMMENT_CONFIRM_CODE.'=' . xpath_encode($code) . ']';
		$commentCollection = $this->xml->findXPath($path);
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

		$this->sendNewCommentMail($comment);

		notify('success', ucfirst(__('art-comment-posted')));
		return ['redirect', $this->constructFullPath($this->pagename)];
	}

	/**
	 * Updates the article to the status.
	 *
	 * @param string $newStatus
	 * @return bool
	 */
	private function updateToNewStatus($newStatus) {
		$currentStatus = $this->getStatus();
		if (!isset($this->statusMtx[$currentStatus]) || !isset($this->statusMtx[$currentStatus][$newStatus])) {
			notify('error', __('art-unsupported-status-change'));
			return false;
		}

		$this->xml->lockAndReload();
		/** @var HyphaDomElement $article */
		$article = $this->xml->find(self::FIELD_NAME_ARTICLE);
		$article->setAttr(self::FIELD_NAME_STATUS, $newStatus);
		if ($newStatus === self::STATUS_PUBLISHED) {
			$article->setAttr(self::FIELD_NAME_PUBLISHED_AT, 't' . time());
		}
		$this->xml->saveAndUnlock();

		// remove private flag
		if ($newStatus === self::STATUS_PUBLISHED && $this->privateFlag) {
			global $hyphaXml;
			$hyphaXml->lockAndReload();
			$this->replacePageListNode(hypha_getPage($this->language, $this->pagename));
			hypha_setPage($this->pageListNode, $this->language, $this->pagename, 'off');
			$this->privateFlag = false;
			$hyphaXml->saveAndUnlock();
		}

		$this->sendStatusChangeMail();

		return true;
	}

	/**
	 * Creates a discussion DOM element.
	 *
	 * @param NodeList $container
	 * @param WymHTMLForm $form
	 * @param string dataFieldSuffix
	 * @return HyphaDomElement
	 */
	private function createDiscussionDomElement(NodeList $container, WymHTMLForm $form, $dataFieldSuffix) {
		/** @var HyphaDomElement $discussion */
		$discussion = $this->xml->createElement(self::FIELD_NAME_DISCUSSION);
		$discussion->generateId();
		$container->append($discussion);

		$review = self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER === $container->nodeName;
		if ($review) {
			$discussion->setAttr(self::FIELD_NAME_USER, $this->O_O->getUser()->getAttribute('id'));
		}

		$blocking = $form->dataFor(self::FIELD_NAME_DISCUSSION_BLOCKING . $dataFieldSuffix) !== null;
		$discussion->setAttr(self::FIELD_NAME_DISCUSSION_BLOCKING, $blocking);
		if ($blocking) {
			$discussion->setAttr(self::FIELD_NAME_DISCUSSION_BLOCK_RESOLVED, false);
		}
		$discussion->setAttr(self::FIELD_NAME_DISCUSSION_CLOSED, false);

		return $discussion;
	}

	/**
	 * Creates a comment DOM element.
	 *
	 * @param HyphaDomElement $discussion
	 * @param WymHTMLForm $form
	 * @param string dataFieldSuffix
	 * @return HyphaDomElement
	 */
	private function createDiscussionCommentDomElement(HyphaDomElement $discussion, WymHTMLForm $form, $dataFieldSuffix) {
		$container = $discussion->parent();

		/** @var HyphaDomElement $comment */
		$comment = $this->xml->createElement(self::FIELD_NAME_DISCUSSION_COMMENT);
		$comment->generateId();
		$discussion->append($comment);

		$text = $form->dataFor(self::FIELD_NAME_DISCUSSION_COMMENT . $dataFieldSuffix);
		$comment->setText($text);
		if (isUser()) {
			$comment->setAttr(self::FIELD_NAME_USER, $this->O_O->getUser()->getAttribute('id'));
		}
		$comment->setAttr(self::FIELD_NAME_CREATED_AT, 't' . time());

		$review = self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER === $container->nodeName;
		if (!$review) {
			if (isUser()) {
				$userEmail = $this->O_O->getUser()->getAttribute('email');
				$userName = $this->O_O->getUser()->getAttribute('fullname');
			} else {
				$userEmail = $form->dataFor(self::FIELD_NAME_DISCUSSION_COMMENTER_EMAIL . $dataFieldSuffix);
				$userName = $form->dataFor(self::FIELD_NAME_DISCUSSION_COMMENTER_NAME . $dataFieldSuffix);
				$comment->setAttr(self::FIELD_NAME_DISCUSSION_COMMENT_PENDING, true);
				$comment->setAttr(self::FIELD_NAME_DISCUSSION_COMMENT_CONFIRM_CODE, $this->constructCode());
			}
			$comment->setAttr(self::FIELD_NAME_DISCUSSION_COMMENTER_EMAIL, $userEmail);
			$comment->setAttr(self::FIELD_NAME_DISCUSSION_COMMENTER_NAME, $userName);
		}

		return $comment;
	}

	/**
	 * Creates the approves DOM element.
	 *
	 * @return HyphaDomElement
	 */
	private function createApprovesDomElement() {
		/** @var HyphaDomElement $approvesDomElement */
		$approvesDomElement = $this->html->createElement('div');
		$approvesDomElement->attr('class', 'approves');
		$approves = $this->xml->find(self::FIELD_NAME_APPROVE_CONTAINER)->find(self::FIELD_NAME_APPROVE)->toArray();
		$approvesDomElement->append('<h2>' . __('art-approves') . '</h2>');

		/** @var HyphaDomElement $list */
		$list = $this->html->createElement('ul');
		foreach ($approves as $approve) {
			$createdAt = date('j-m-y, H:i', ltrim($approve->getAttr(self::FIELD_NAME_CREATED_AT), 't'));
			$approverId = $approve->getAttr(self::FIELD_NAME_USER);
			$approver = hypha_getUserById($approverId);
			$approverName = $approver instanceof HyphaDomElement ? $approver->getAttribute('fullname') : $approverId;
			$html = '<p>' . __('art-by') . ' <strong>' . $approverName . '</strong> ' . __('art-at') . ' ' . $createdAt . '</p>';
			$li = $this->html->createElement('li')->append($html);
			$list->append($li);
		}
		if (empty($approves)) {
			$list->append('<li>' . __('art-no-approves-yet') . '</li>');
		}
		$approvesDomElement->append($list);

		return $approvesDomElement;
	}

	/**
	 * Creates discussion DOM elements and appends them to the discussions container.
	 *
	 * @param string $type
	 * @param HyphaDomElement $discussionsContainer
	 * @return void
	 */
	private function fillDiscussionsContainer($type, HyphaDomElement $discussionsContainer) {
		$container = $type === 'review' ? self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER : self::FIELD_NAME_DISCUSSION_PUBLIC_CONTAINER;
		/** @var HyphaDomElement $container */
		$container = $this->xml->find($container);

		/** @var HyphaDomElement[] $discussions */
		$discussions = $container->find(self::FIELD_NAME_DISCUSSION)->toArray();

		// in case of no discussions
		if (empty($discussions)) {
			/** @var HyphaDomElement $noCommentContainer */
			$noCommentContainer = $this->html->createElement('ul')->appendTo($discussionsContainer);
			$noComment = $this->html->create('li')->appendTo($noCommentContainer);
			$noComment->text(__('art-no-comments-yet'));
		}

		// [open => [blocking, non-blocking], closed => [blocking, non-blocking]]
		$reviewCommentContainersSorted = [0 => [0 => [], 1 => [],], 1 => [0 => [], 1 => [],],];

		foreach ($discussions as $discussion) {
			$blocking = (bool)$discussion->getAttr(self::FIELD_NAME_DISCUSSION_BLOCKING);
			$closed = (bool)$discussion->getAttr(self::FIELD_NAME_DISCUSSION_CLOSED);
			$resolved = (bool)$discussion->getAttr(self::FIELD_NAME_DISCUSSION_BLOCK_RESOLVED);

			// create discussion container
			/** @var HyphaDomElement $list */
			$discussionContainer = $this->html->createElement('ul');

			foreach ($discussion->children() as $comments) {
				/** @var HyphaDomElement[] $comments */
				if (!is_array($comments)) {
					$comments = [$comments];
				}
				$firstComment = true;
				foreach ($comments as $comment) {
					if (self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER !== $type && (bool)$comment->getAttr(self::FIELD_NAME_DISCUSSION_COMMENT_PENDING)) {
						continue;
					}
					$createdAt = date('j-m-y, H:i', ltrim($comment->getAttr(self::FIELD_NAME_CREATED_AT), 't'));
					$committerName = $this->getCommentCommenter($comment);
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
					$discussionContainer->append('<li ' . ($firstComment ? 'class="first-comment"' : '') . '>' . $html . '</li>');
					$firstComment = false;
				}
			}

			if (!$discussionContainer->hasChildNodes()) {
				// if there are no comments to be displayed, all discussion parts do not need to be displayed.
				continue;
			}

			/** @var HyphaDomElement $reviewCommentContainer */
			$reviewCommentContainer = $this->html->createElement('div');
			$class = $type . '-comment-wrapper collapsed';
			foreach (['blocking' => $blocking, 'resolved' => $resolved, 'closed' => $closed] as $name => $isTrue) {
				if ($isTrue) {
					$class .= ' ' . $name;
				}
			}
			$reviewCommentContainer->attr('class', $class);
			$reviewCommentContainer->append($discussionContainer);

			if (self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER === $type) {
				$msgId = null;
				if ($blocking) {
					if (!$resolved && ($discussion->getAttr(self::FIELD_NAME_USER) === $this->O_O->getUser()->getAttribute('id') || isAdmin())) {
						$msgId = __('art-set-as-resolved');
					}
				} elseif (!$closed) {
					$msgId = __('art-set-as-closed');
				}
				if (null !== $msgId) {
					$path = str_replace('{id}', $discussion->getId(), self::PATH_DISCUSSIONS_CLOSED);
					$discussionContainer->append('<p>' . $this->makeActionButton(__($msgId), $path, self::CMD_DISCUSSION_CLOSED) . '</p>');
				}
			}

			// display comment form if the discussion is still open
			if (!$closed) {
				// create form to comment on a discussion
				$type = $discussion->parent()->nodeName;
				$replyForm = $this->createCommentForm($type, [], $discussion);
				$discussionContainer->append($replyForm);
				$discussionContainer->append($this->makeDiscussionActionButton($type, $discussion));
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

		// create form to start a discussion
		$commentForm = $this->createCommentForm($type);
		$discussionsContainer->append($commentForm);
		$discussionsContainer->append($this->makeDiscussionActionButton($type));
	}

	/**
	 * Creates a HTML form object for the article.
	 *
	 * @param array $values
	 * @return WymHTMLForm
	 */
	private function createEditForm(array $values = []) {
		$html = <<<EOF
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<div class="input-wrapper field_type_text field_name_[[field-name-title]]">
					<strong><label for="[[field-name-title]]">[[title]]</label></strong><br><input type="text" id="[[field-name-title]]" name="[[field-name-title]]" />
				</div>
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<div class="input-wrapper field_type_text field_name_[[field-name-author]]">
					<strong><label for="[[field-name-author]]">[[author]]</label></strong><br><input type="text" id="[[field-name-author]]" name="[[field-name-author]]" />
				</div>
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<div class="input-wrapper field_type_editor field_name_[[field-name-text]]">
					<strong><label for="[[field-name-text]]">[[text]]</label></strong>[[info-button-text]]<br><editor name="[[field-name-text]]"></editor>
				</div>
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<div class="input-wrapper field_type_editor field_name_[[field-name-excerpt]]">
					<strong><label for="[[field-name-excerpt]]">[[excerpt]]</label></strong>[[info-button-excerpt]]<br><editor name="[[field-name-excerpt]]"></editor>
				</div>
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<div class="input-wrapper field_type_editor field_name_[[field-name-method]]">
					<strong><label for="[[field-name-method]]">[[method]]</label></strong>[[info-button-method]]<br><editor name="[[field-name-method]]"></editor>
				</div>
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<div class="input-wrapper field_type_editor field_name_[[field-name-sources]]">
					<strong><label for="[[field-name-sources]]">[[sources]]</label></strong><br><editor name="[[field-name-sources]]"></editor>
				</div>
			</div>
EOF;

		$vars = [
			'title' => __('art-title'),
			'field-name-title' => self::FIELD_NAME_TITLE,
			'author' => __('art-author'),
			'field-name-author' => self::FIELD_NAME_AUTHOR,
			'text' => __('art-article'),
			'field-name-text' => self::FIELD_NAME_TEXT,
			'info-button-text' => makeInfoButton('help-art-text'),
			'excerpt' => __('art-excerpt'),
			'field-name-excerpt' => self::FIELD_NAME_EXCERPT,
			'info-button-excerpt' => makeInfoButton('help-art-excerpt'),
			'method' => __('art-method'),
			'field-name-method' => self::FIELD_NAME_METHOD,
			'info-button-method' => makeInfoButton('help-art-method'),
			'sources' => __('art-sources'),
			'field-name-sources' => self::FIELD_NAME_SOURCES,
		];

		$html = hypha_substitute($html, $vars);

		return new WymHTMLForm($html, $values);
	}

	/**
	 * Constructs the WymHTMLForm for the comment form.
	 *
	 * @param string $type
	 * @param array $data
	 * @param null|HyphaDomElement $discussion
	 * @return WymHTMLForm
	 */
	private function createCommentForm($type, array $data = [], HyphaDomElement $discussion = null) {
		$new = $discussion === null;

		$review = self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER === $type;

		$dataFieldSuffix = $new ? '/new_' . $type : '/' . $discussion->getId();

		$html = <<<EOF
			<div class="new-comment $type">
			<div>
				<strong><label for="[[field-name-comment]]">[[comment]]</label></strong><textarea name="[[field-name-comment]]" id="[[field-name-comment]]"></textarea>
			</div>
EOF;

		$vars = [];
		$vars['comment'] = $new ? __('art-comment-on-article') : __('art-comment-on-comment');
		if (!isUser()) {
			// only non-users need to enter their name
			$vars['comment'] .= ' ' . makeInfoButton('help-comment-rules');
		}
		$vars['field-name-comment'] = self::FIELD_NAME_DISCUSSION_COMMENT . $dataFieldSuffix;

		if ($new && $review && !in_array($this->getStatus(), [self::STATUS_APPROVED, self::STATUS_PUBLISHED])) {
			$html .= <<<EOF
			<div>
				<strong><label for="[[field-name-comment-blocking]]">[[blocking]]</label></strong><input type="checkbox" name="[[field-name-comment-blocking]]" id="[[field-name-comment-blocking]]" /> [[info-button-blocking]]
			</div>
EOF;
			$vars['blocking'] = __('art-blocking');
			$vars['info-button-blocking'] = makeInfoButton('help-blocking-comment');
			$vars['field-name-comment-blocking'] = self::FIELD_NAME_DISCUSSION_BLOCKING . $dataFieldSuffix;
		}

		if (!isUser()) {
			$html .= <<<EOF
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="[[field-name-name]]">[[name]]</label></strong><div class="label_suffix">[[un-anonymous]]</div> <input type="text" id="[[field-name-name]]" name="[[field-name-name]]" />
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="[[field-name-email]]">[[email]]</label></strong> <input type="text" id="[[field-name-email]]" name="[[field-name-email]]" />
			</div>
EOF;
			$vars['name'] = __('art-name');
			$vars['field-name-name'] = self::FIELD_NAME_DISCUSSION_COMMENTER_NAME . $dataFieldSuffix;
			$vars['un-anonymous'] = __('art-comment-unanonymous');
			$vars['email'] = __('art-email');
			$vars['field-name-email'] = self::FIELD_NAME_DISCUSSION_COMMENTER_EMAIL . $dataFieldSuffix;
		}
		$html .= '</div>';

		$html = hypha_substitute($html, $vars);

		return new WymHTMLForm($html, $data);
	}

	/**
	 * Constructs a random code with the given length.
	 *
	 * @param int $length
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
	 * Sends "status has changed" mail to all users.
	 */
	private function sendStatusChangeMail() {
		$newStatus = $this->getStatus();
		if (in_array($newStatus, [self::STATUS_NEWLY_CREATED, self::STATUS_DRAFT])) {
			return;
		}

		$title = $this->getTitle();
		$author = $this->O_O->getUser()->getAttribute('fullname');
		$linkToPage = $this->constructFullPath($this->pagename);
		if (self::STATUS_REVIEW === $newStatus) {
			$subject = __('art-review-request-subject', [
				"title" => $title,
				"author" => $author
			]);
			$message = __('art-review-request-body', [
				"link" => htmlspecialchars($linkToPage),
				"title" => htmlspecialchars($title),
				"author" => htmlspecialchars($author)
			]);
		} else {
			$subject = __('art-status-update-subject', [
				"status" => $newStatus,
				"title" => $title
			]);
			$message = __('art-status-update-body', [
				"link" => htmlspecialchars($linkToPage),
				"title" => htmlspecialchars($title),
				"author" => htmlspecialchars($author),
				"status" => htmlspecialchars($newStatus)
			]);
		}

		$this->sendMail(getUserEmailList(), $subject, $message);
	}

	/**
	 * Sends "email confirmation" mail to commenter.
	 *
	 * @param HyphaDomElement $comment
	 */
	private function sendCommentConfirmMail(HyphaDomElement $comment) {
		$code = $comment->getAttr(self::FIELD_NAME_DISCUSSION_COMMENT_CONFIRM_CODE);
		$commentBody = $comment->textContent;

		$title = $this->getTitle();
		$path = str_replace(['{id}', '{code}'], [$comment->getId(), $code], self::PATH_DISCUSSIONS_COMMENT_CONFIRM);
		$linkToConfirm = $this->constructFullPath($this->pagename . '/' . $path);

		$email = $comment->getAttr(self::FIELD_NAME_DISCUSSION_COMMENTER_EMAIL);
		$subject = __('art-confirm-comment-subject');
		$message = __('art-confirm-comment-body', array(
			'sitename' => htmlspecialchars(hypha_getTitle()),
			'title' => htmlspecialchars($title),
			'comment' => nl2br(htmlspecialchars($commentBody)),
			'link' => htmlspecialchars($linkToConfirm)
		));
		$this->sendMail($email, $subject, $message);
	}

	/**
	 * Sends "new comment" mail to all users.
	 *
	 * @param HyphaDomElement $comment
	 */
	private function sendNewCommentMail(HyphaDomElement $comment) {
		// send newly created comment to all users
		$title = $this->getTitle();
		$linkToPage = $this->constructFullPath($this->pagename);
		$name = $this->getCommentCommenter($comment);
		$commentBody = $comment->getText();

		$subject = __('art-comment-subject', array(
			'name' => $name,
			'title' => $title
		));
		$message = __('art-comment-body', array(
			'name' => htmlspecialchars($name),
			'link' => htmlspecialchars($linkToPage),
			'title' => htmlspecialchars($title),
			'comment' => nl2br(htmlspecialchars($commentBody))
		));
		$this->sendMail(getUserEmailList(), $subject, $message);
	}

	/**
	 * Sends "new blocking discussion" mail to all users.
	 *
	 * @param HyphaDomElement $discussion
	 */
	private function sendBlockingDiscussionMail(HyphaDomElement $discussion) {
		$title = $this->getTitle();
		$author = $this->O_O->getUser()->getAttribute('fullname');
		$linkToPage = $this->constructFullPath($this->pagename);

		$comment = $discussion->lastChild->textContent;
		$subject = __('art-block-submitted-subject', array(
			'title' => $title
		));
		$message = __('art-block-submitted-body', array(
			'link' => htmlspecialchars($linkToPage),
			'title' => htmlspecialchars($title),
			'author' => htmlspecialchars($author),
			'comment' => htmlspecialchars($comment)
		));

		$this->sendMail(getUserEmailList(), $subject, $message);
	}

	/**
	 * Sends "block has been resolved" mail to all users.
	 */
	private function sendResolvedBlockingDiscussionMail() {
		$title = $this->getTitle();
		$author = $this->O_O->getUser()->getAttribute('fullname');
		$linkToPage = $this->constructFullPath($this->pagename);
		$subject = __('art-block-resolved-subject', array(
			'title' => $title
		));
		$message = __('art-block-resolved-body', array(
			'link' => htmlspecialchars($linkToPage),
			'title' => htmlspecialchars($title),
			'author' => htmlspecialchars($author)
		));
		$this->sendMail(getUserEmailList(), $subject, $message);
	}

	/**
	 * Sends a mail.
	 *
	 * @param string $receivers A comma separated list of receivers.
	 * @param string $subject
	 * @param string $message
	 */
	private function sendMail($receivers, $subject, $message) {
		$style = 'body {margin-top:10px; margin-left: 10px; font-size:10pt; font-family: Sans; color:black; background-color:white;}';
		sendMail($receivers, hypha_getTitle() . ': '. $subject, $message, hypha_getEmail(), hypha_getTitle(), $style);
	}

	/**
	 * Gets the article title.
	 *
	 * @return string
	 */
	private function getTitle() {
		$title = $this->xml->find(self::FIELD_NAME_TITLE);
		if ($title instanceof NodeList) {
			$title = $title->getText();
		}
		if ('' === $title) {
			$title = showPagename($this->pagename);
		}

		return $title;
	}

	/**
	 * Gets the article status.
	 *
	 * @return string
	 */
	private function getStatus() {
		/** @var HyphaDomElement $article */
		$article = $this->xml->find(self::FIELD_NAME_ARTICLE);
		$status = $article->getAttr(self::FIELD_NAME_STATUS);
		if ('' == $status) {
			$status = self::STATUS_NEWLY_CREATED;
		}

		return $status;
	}

	/**
	 * Indication whether or not the article can be set as approved.
	 *
	 * @return bool
	 */
	private function canBeSetAsApproved() {
		$discussions = $this->xml->find(self::FIELD_NAME_DISCUSSION_CONTAINER);
		/** @var NodeList $blockingDiscussions */
		$blockingDiscussions = $discussions->findXPath('//' . self::FIELD_NAME_DISCUSSION . '[@' . self::FIELD_NAME_DISCUSSION_BLOCKING . '="1"][@' . self::FIELD_NAME_DISCUSSION_BLOCK_RESOLVED . '!="1"]');
		$blockingDiscussionsCount = $blockingDiscussions->count();
		if ($blockingDiscussionsCount > 0) {
			return false;
		}

		$approveCount = $this->xml->find(self::FIELD_NAME_APPROVE_CONTAINER)->children()->count();
		if ($approveCount < 3) {
			return false;
		}

		return $this->getStatus() === self::STATUS_REVIEW;
	}

	/**
	 * Indication whether or not the given user has approved the article.
	 *
	 * @param string $userId
	 * @return bool
	 */
	private function hasUserApproved($userId) {
		$approves = $this->xml->find(self::FIELD_NAME_APPROVE_CONTAINER);
		/** @var NodeList $approveCollection */
		$approveCollection = $approves->findXPath('//' . self::FIELD_NAME_APPROVE . '[@' . self::FIELD_NAME_USER . '="' . $userId . '"]');
		return $approveCollection->count() >= 1;
	}

	/**
	 * Gets the commenter name for the given comment.
	 *
	 * @param HyphaDomElement $comment
	 * @return string
	 */
	private function getCommentCommenter(HyphaDomElement $comment) {
		$userId = $comment->getAttr(self::FIELD_NAME_USER);
		if ($userId) {
			$user = hypha_getUserById($userId);
			return ($user instanceof HyphaDomElement) ? $user->getAttribute('fullname') : $userId;
		}
		return $comment->getAttr(self::FIELD_NAME_DISCUSSION_COMMENTER_NAME);
	}

	/**
	 * @todo [LRM]: move so it can be used throughout Hypha
	 * @param string $label
	 * @param null|string $path
	 * @param null|string $command
	 * @param null|string|array $argument
	 * @return string
	 */
	private function makeActionButton($label, $path = null, $command = null, $argument = null) {
		if (is_array($argument)) {
			$argument = json_encode($argument);
		}
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
	private function constructFullPath($path, $language = null) {
		global $hyphaUrl;
		$language = null == $language ? $this->language : $language;
		$path = '' == $path ? '' : '/' . $path;

		return $hyphaUrl . $language . $path;
	}
}
