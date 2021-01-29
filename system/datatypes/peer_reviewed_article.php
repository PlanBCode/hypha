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
class peer_reviewed_article extends HyphaDatatypePage {
	/** @var Xml */
	protected $xml;

	const FIELD_NAME_USER = 'user';
	const FIELD_NAME_CREATED_AT = 'created_at';
	const FIELD_NAME_CREATED_BY = 'created_by';
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
	const FIELD_NAME_DISCUSSION_COMMENTER_SUBSCRIBE = 'commenter_subscribe';
	const FIELD_NAME_DISCUSSION_SUBSCRIBER_CONTAINER = 'subscribers';
	const FIELD_NAME_DISCUSSION_SUBSCRIBER = 'subscriber';
	const FIELD_NAME_DISCUSSION_SUBSCRIBER_STATUS = 'subscriber_status';
	const FIELD_NAME_DISCUSSION_SUBSCRIBER_CONFIRM_CODE = 'subscriber_confirm_code';
	const FIELD_NAME_DISCUSSION_SUBSCRIBER_CODE = 'code';
	const FIELD_NAME_DISCUSSION_SUBSCRIBER_NAME = 'subscriber_name';
	const FIELD_NAME_DISCUSSION_SUBSCRIBER_EMAIL = 'subscriber_email';
	const FIELD_NAME_DISCUSSION_SUBSCRIBER_UNSUBSCRIBE_CODE = 'subscriber_unsubscribe_code';
	const FIELD_NAME_DISCUSSION_CLOSED_BY = 'closed_by';
	const FIELD_NAME_DISCUSSION_CLOSED_AT = 'closed_at';
	const FIELD_NAME_DISCUSSION_MODERATION_STATUS = 'moderation-status';
	const FIELD_NAME_DISCUSSION_MODERATION_REASON = 'moderation-reason';
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
	const FIELD_NAME_FEATURED_IMAGE = 'featured_image';
	const FIELD_NAME_METHOD = 'method';
	const FIELD_NAME_MODERATE_REASON = 'reason';

	const STATUS_NEWLY_CREATED = 'newly created';
	const STATUS_DRAFT = 'draft';
	const STATUS_REVIEW = 'review';
	const STATUS_APPROVED = 'approved';
	const STATUS_PUBLISHED = 'published';
	const STATUS_RETRACTED = 'retracted';

	const MODERATION_STATUS_HIDDEN = 'hidden';
	const MODERATION_STATUS_DELETED = 'deleted';

	const SUBSCRIBER_STATUS_PENDING = 'pending';
	const SUBSCRIBER_STATUS_CONFIRMED = 'confirmed';
	const SUBSCRIBER_STATUS_UNSUBSCRIBED = 'unsubscribed';

	const PATH_EDIT = 'edit';
	const PATH_DISCUSSIONS = 'discussions';
	const PATH_DISCUSSIONS_TYPE = 'discussions/{type}'; // TODO [LRM]: use hypha_substitute
	const PATH_DISCUSSIONS_CLOSED = 'discussions/{id}/closed'; // TODO [LRM]: use hypha_substitute
	const PATH_DISCUSSIONS_COMMENT = 'discussions/{id}/comment'; // TODO [LRM]: use hypha_substitute
	const PATH_COMMENTS = 'comments';
	const PATH_COMMENTS_MODERATE = 'comments/{id}/moderate'; // TODO [LRM]: use hypha_substitute
	const PATH_COMMENT = 'comment';
	const PATH_DISCUSSIONS_COMMENT_CONFIRM = 'comment/{id}/confirm?code={code}'; // TODO [LRM]: use hypha_substitute
	const PATH_UNSUBSCRIBE = 'unsubscribe';
	const PATH_UNSUBSCRIBE_CODE = 'unsubscribe?address=[[address]]&code=[[code]]';

	const CMD_SAVE = 'save';
	const CMD_DELETE = 'delete';
	const CMD_STATUS_CHANGE = 'status_change';
	const CMD_STATUS_CHANGE_REVIEW = self::CMD_STATUS_CHANGE . '_' . self::STATUS_REVIEW;
	const CMD_STATUS_CHANGE_PUBLISHED = self::CMD_STATUS_CHANGE . '_' . self::STATUS_PUBLISHED;
	const CMD_APPROVE = 'approve';
	const CMD_DISCUSSION_STARTED = 'discussion_started';
	const CMD_DISCUSSION_CLOSED = 'discussion_closed';
	const CMD_COMMENT = 'comment';
	const CMD_COMMENT_MODERATE_HIDE = 'hide';
	const CMD_COMMENT_MODERATE_DELETE = 'delete';
	const CMD_COMMENT_UNMODERATE = 'unmoderate';

	const MODERATE_REASONS = [
		'inappropriate',
		'violation',
		'offtopic',
		'duplicate',
		'spam',
		'other',
	];

	const APPROVES_NEEDED = 3;

	const CHECK_LEVEL_REQUIRED = 'required';
	const CHECK_LEVEL_RECOMMENDED = 'recommended';
	const CHECK_STATUS_PASSED = 'passed';
	const CHECK_STATUS_FAILED = 'failed';
	const CHECK_STATUS_RECOMMENDED = 'recommended';

	protected $statusMtx = [
		self::STATUS_NEWLY_CREATED => [],
		self::STATUS_DRAFT => [self::STATUS_REVIEW => ['label' => 'art-start-review', 'cmd' => self::CMD_STATUS_CHANGE_REVIEW]],
		self::STATUS_REVIEW => [self::STATUS_APPROVED => ['label' => 'art-approve']],
		self::STATUS_APPROVED => [self::STATUS_PUBLISHED => ['label' => 'art-publish', 'cmd' => self::CMD_STATUS_CHANGE_PUBLISHED]],
		self::STATUS_PUBLISHED => [/*self::STATUS_RETRACTED => 'retract'*/], // retracted is not supported yet
		self::STATUS_RETRACTED => [/*self::STATUS_DRAFT => 'to_draft'*/], // "to draft" is not supported yet
	];

	const URL_REGEX='/((https?:\/\/)|www.)[-\w\/]+\.[-\w\/?=%.&#_]*[-\w\/?=%&#_]/iu';
	// [protocol://|www.] [word] . [suffix /path ?query&parameter=value#bookmark] [last char must not be a dot]
	const MAX_COMMENT_LENGTH = 100 * 6; // Roughly hundred words
	const MIN_COMMENT_STUB_LENGTH = self::MAX_COMMENT_LENGTH * 0.7;

	public static function getDatatypeName() {
		return __('datatype.name.peer_reviewed_article');
	}

	public function __construct(HyphaDomElement $pageListNode, RequestContext $O_O) {
		parent::__construct($pageListNode, $O_O);
		$this->xml = new Xml('peer_reviewed_article', Xml::multiLingualOff, Xml::versionsOff);
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
			case [null,                   self::CMD_STATUS_CHANGE_PUBLISHED]: return $this->statusChangeAction($request, self::STATUS_PUBLISHED);
			case [null,                   self::CMD_APPROVE]:                 return $this->approveAction($request);
			case [self::PATH_EDIT,        null]:                              return $this->editView($request);
			case [self::PATH_EDIT,        self::CMD_SAVE]:                    return $this->editAction($request);
			case [self::PATH_DISCUSSIONS, self::CMD_DISCUSSION_STARTED]:      return $this->discussionAction($request);
			case [self::PATH_DISCUSSIONS, self::CMD_COMMENT]:                 return $this->discussionCommentAction($request);
			case [self::PATH_DISCUSSIONS, self::CMD_DISCUSSION_CLOSED]:       return $this->discussionClosedAction($request);
			case [self::PATH_COMMENTS,    null]:                              return $this->commentModerateView($request);
			case [self::PATH_COMMENTS,    self::CMD_COMMENT_MODERATE_HIDE]:   return $this->commentModerateAction($request);
			case [self::PATH_COMMENTS,    self::CMD_COMMENT_MODERATE_DELETE]: return $this->commentModerateAction($request);
			case [self::PATH_COMMENTS,    self::CMD_COMMENT_UNMODERATE]:      return $this->commentModerateAction($request);
			case [self::PATH_COMMENT,     null]:                              return $this->commentConfirmAction($request);
			case [self::PATH_UNSUBSCRIBE, null]:                              return $this->unsubscribeAction($request);
		}

		return '404';
	}

	public function getSortDateTime() {
		$article = $this->xml->find(self::FIELD_NAME_ARTICLE);
		$value = $article->getAttribute(self::FIELD_NAME_PUBLISHED_AT);
		if (!$value)
			$value = $article->getAttribute(self::FIELD_NAME_UPDATED_AT);
		// Normally, everything should have an updated field,
		// except articles created before the field existed.
		if (!$value)
			$value = $article->getAttribute(self::FIELD_NAME_CREATED_AT);
		return timestampToDateTime($value);
	}

	public function renderExcerpt(HyphaDomElement $container) {
		$doc = $container->document();
		$h2 = $doc->createElement('h2')->setText($this->getTitle());
		$h2->addClass('title');
		$h2->appendTo($container);

		$article = $this->xml->find(self::FIELD_NAME_ARTICLE);
		$this->appendAuthorAndTimestamp($container, $article);
		$this->renderExcerptBody($container);
	}

	public function renderExcerptBody(HyphaDomElement $container) {
		$doc = $container->document();
		/** @var HyphaDomElement $excerpt */
		$excerpt = $doc->createElement('div')->appendTo($container);
		$excerpt->addClass('excerpt');

		$featuredImageSrc = $this->xml->documentElement->getOrCreate(self::FIELD_NAME_FEATURED_IMAGE)->text();
		if ($featuredImageSrc) {
			$featured_image = $doc->createElement('img')->appendTo($excerpt);
			$featured_image->addClass('featured_image');
			$featured_image->setAttribute('src', (new HyphaImage($featuredImageSrc))->getUrl());
		}

		$excerptBody = $doc->createElement('div')->appendTo($excerpt);
		$excerptBody->addClass('excerpt_body');
		$excerptBody->append($this->getExcerpt()->children());
	}

	/**
	 * Checks if the status is new and if so builds the structure and sets the status to draft.
	 */
	protected function ensureStructure() {
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
					self::FIELD_NAME_FEATURED_IMAGE => [],
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

		// force protected flag
		if (!$this->privateFlag) {
			global $hyphaXml;
			$hyphaXml->lockAndReload();
			$this->replacePageListNode(hypha_getPage($this->language, $this->pagename));
			hypha_setPage($this->pageListNode, $this->language, $this->pagename, 'on');
			$this->privateFlag = true;
			$hyphaXml->saveAndUnlock();
		}

		$user = $this->O_O->getUser();

		// set initial status, author, timestamps and title
		/** @var HyphaDomElement $article */
		$article = $this->xml->find(self::FIELD_NAME_ARTICLE);
		$article->setAttribute(self::FIELD_NAME_STATUS, self::STATUS_DRAFT);
		$article->setAttribute(self::FIELD_NAME_AUTHOR, $user->getAttribute('fullname'));
		$article->setAttribute(self::FIELD_NAME_CREATED_AT, 't' . time());
		$article->setAttribute(self::FIELD_NAME_CREATED_BY, $user->getAttribute('id'));
		$article->setAttribute(self::FIELD_NAME_UPDATED_AT, 't' . time());
		$article->setAttribute(self::FIELD_NAME_PUBLISHED_AT, '');
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
	protected function defaultView(HyphaRequest $request) {
		$status = $this->getStatus();

		// add buttons for registered users
		if (isUser()) {
			$userId = $this->O_O->getUser()->getAttribute('id');

			list($checkStatus, $checks) = $this->runChecks();

			/** @var HyphaDomElement $commands */
			$commands = $this->html->find('#pageCommands');
			/** @var HyphaDomElement $commandsAtEnd */
			$commandsAtEnd = $this->html->find('#pageEndCommands');
			$commands->append($this->makeActionButton(__('edit'), self::PATH_EDIT));

			// the status change from review to approved is done automatically
			if (self::STATUS_REVIEW === $status) {
				if (!$this->hasUserApproved($userId)) {
					$commandsAtEnd->append($this->makeActionButton(__('art-approve'), null, self::CMD_APPROVE));
				}
			} else if ($checkStatus != self::CHECK_STATUS_FAILED) {
				foreach ($this->statusMtx[$status] as $option) {
					if (self::STATUS_APPROVED === $status && $option['cmd'] === self::CMD_STATUS_CHANGE_PUBLISHED && !$this->hasUserCreated($userId) && !isAdmin()) {
						continue;
					}
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

		/** @var HyphaDomElement $main */
		$main = $this->html->find('#main')->first();

		/** @var HyphaDomElement $discussions */
		$discussions = $this->xml->find(self::FIELD_NAME_DISCUSSION_PUBLIC_CONTAINER);

		/** @var NodeList $commentCollection */
		$xpath = './/' . self::FIELD_NAME_DISCUSSION_COMMENT . '[@' . self::FIELD_NAME_DISCUSSION_COMMENT_PENDING . '!="1"]';
		$commentCollection = $discussions->findXPath($xpath);

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
		$themeImgPath = 'data/themes/' . $this->O_O->getThemeName() . '/images';

		// Email
		$subject = __('share-email-subject', ['domain' => hypha_getTitle(), 'title' => $this->getTitle()]);
		$body = __('share-email-body', ['domain' => hypha_getTitle(), 'title' => $this->getTitle(), 'url' => $linkToPage]);
		$shareDiv->append('<a href="mailto:?subject='.rawurlencode($subject).'&body='.rawurlencode($body).'"><div class="email-link"></div></a>');
		// Twitter
		$text = __('share-twitter', ['domain' => hypha_getTitle(), 'title' => $this->getTitle(), 'url' => $linkToPage]);
		$shareDiv->append('<a href="https://twitter.com/intent/tweet?text='.rawurlencode($text).'&url='.rawurlencode($linkToPage).'" target="_blank"><div class="twitter-link"></div></a>');
		// Facebook
		$shareDiv->append('<a href="https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($linkToPage).'" target="_blank"><div class="facebook-link"></div></a>');

		$this->appendAuthorAndTimestamp($main, $article);

		if (isUser()) {
			if ($checks) {
				$checklist = $this->renderChecklist($checkStatus, $checks);
				$checklist->appendTo($main);
			}

			$preview = $this->html->createElement('div')->addClass('excerpt-preview')->appendTo($main);
			$this->html->createElement('span')->addClass('prefix')->setText(__('art-excerpt-preview'))->appendTo($preview);
			$this->renderExcerptBody($preview);
		}

		$text = $content->find(self::FIELD_NAME_TEXT)->children();
		/** @var HyphaDomElement $div */
		$div = $this->html->createElement('div');
		$div->setAttribute('class', 'article');
		$div->append($text);
		$main->append($div);

		/** @var NodeList $method */
		$method = $this->xml->find(self::FIELD_NAME_METHOD)->children();
		if ($method->count() > 0) {
			/** @var HyphaDomElement $methodContainer */
			$methodContainer = $this->html->createElement('div');
			$methodContainer->attr('class', 'method');
			$methodContainer->append('<h2>' . __('art-method') . '</h2>');
			$methodContainer->append($method);
			$main->append($methodContainer);
		}

		/** @var NodeList $sources */
		$sources = $this->xml->find(self::FIELD_NAME_SOURCES)->children();
		if ($sources->count() > 0) {
			/** @var HyphaDomElement $div */
			$div = $this->html->createElement('div');
			$div->setAttribute('class', 'sources');
			$div->append('<h2>' . __('art-sources') . '</h2>');
			$div->append($sources);
			$main->append($div);
		}

		if (isUser() && self::STATUS_DRAFT !== $status) {
			$main->append($this->createDiscussionsDomElement(self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER));
			$main->append($this->createApprovesDomElement());
		}

		if (self::STATUS_PUBLISHED === $status) {
			$main->append($this->createDiscussionsDomElement(self::FIELD_NAME_DISCUSSION_PUBLIC_CONTAINER));
		}

		return null;
	}

	/**
	 * Returns an array describing all state transition checks.
	 *
	 * @return array<string, array{
	 *   statuses: list<self::STATUS_*>,
	 *   checker: callable():array{bool, array},
	 *   level: self::CHECK_LEVEL_*,
	 * }>
	 */
	protected function getChecks() {
		// This cannot be a static property, since anonymous
		// functions are not allowed in initializers for those.
		return [
			'has-article-body' => [
				'statuses' => [self::STATUS_DRAFT, self::STATUS_REVIEW, self::STATUS_APPROVED],
				'checker' => function() {
					return [$this->xml->find(self::FIELD_NAME_CONTENT)->hasContent(), []];
				},
				'level' => self::CHECK_LEVEL_REQUIRED,
			],
			'has-method' => [
				'statuses' => [self::STATUS_DRAFT, self::STATUS_REVIEW, self::STATUS_APPROVED],
				'checker' => function() {
					return [$this->xml->find(self::FIELD_NAME_METHOD)->hasTextContent(), []];
				},
				'level' => self::CHECK_LEVEL_REQUIRED,
			],
			'has-sources' => [
				'statuses' => [self::STATUS_DRAFT, self::STATUS_REVIEW, self::STATUS_APPROVED],
				'checker' => function() {
					return [$this->xml->find(self::FIELD_NAME_SOURCES)->hasTextContent(), []];
				},
				'level' => self::CHECK_LEVEL_RECOMMENDED,
			],
			'has-excerpt' => [
				'statuses' => [self::STATUS_DRAFT, self::STATUS_REVIEW, self::STATUS_APPROVED],
				'checker' => function() {
					return [$this->getExcerpt()->hasTextContent(), []];
				},
				'level' => self::CHECK_LEVEL_REQUIRED,
			],
			'has-featured-image' => [
				'statuses' => [self::STATUS_DRAFT, self::STATUS_REVIEW, self::STATUS_APPROVED],
				'checker' => function() {
					return [$this->xml->find(self::FIELD_NAME_FEATURED_IMAGE)->getText() !== "", []];
				},
				'level' => self::CHECK_LEVEL_RECOMMENDED,
			],
			'has-tags' => [
				'statuses' => [self::STATUS_DRAFT, self::STATUS_REVIEW, self::STATUS_APPROVED],
				'checker' => function() {
					return [count(HyphaTags::tagsForPageListNode($this->pageListNode)) > 0, []];
				},
				'level' => self::CHECK_LEVEL_REQUIRED,
			],
			'has-approvals' => [
				'statuses' => [self::STATUS_REVIEW, self::STATUS_APPROVED],
				'checker' => function() {
					$approveCount = $this->xml->find(self::FIELD_NAME_APPROVE_CONTAINER)->children()->count();
					return [$approveCount >= self::APPROVES_NEEDED, ['have' => $approveCount, 'need' => self::APPROVES_NEEDED]];
				},
				'level' => self::CHECK_LEVEL_REQUIRED,
			],
			'has-no-blocks' => [
				'statuses' => [self::STATUS_REVIEW, self::STATUS_APPROVED],
				'checker' => function() {
					$xpath = '//' . self::FIELD_NAME_DISCUSSION_CONTAINER . '//' . self::FIELD_NAME_DISCUSSION . '[@' . self::FIELD_NAME_DISCUSSION_BLOCKING . '="1" and @' . self::FIELD_NAME_DISCUSSION_BLOCK_RESOLVED . '!="1"]';
					$blockingCount = $this->xml->findXPath($xpath)->count();
					return [$blockingCount === 0, ['left' => $blockingCount]];
				},
				'level' => self::CHECK_LEVEL_REQUIRED,
			],
		];
	}

	/**
	 * Runs any checks, as appropriate for the current status, and
	 * returns both the overall result, and a list of each check for
	 * the current status with its result.
	 *
	 * @return array{
	 *   (self::CHECK_STATUS_*),
	 *   array<string, array{
	 *     passed: bool,
	 *     status: self::CHECK_STATUS_*,
	 *     label: string,
	 *   }>}
	 */
	protected function runChecks() {
		$result = [];
		$overallStatus = self::CHECK_STATUS_PASSED;

		$articleStatus = $this->getStatus();
		foreach ($this->getChecks() as $name => $check) {
			if (!in_array($articleStatus, $check['statuses']))
				continue;
			// Call the checker function
			list($passed, $labelargs) = $check['checker']();
			if ($passed) {
				$status = self::CHECK_STATUS_PASSED;
			} else if ($check['level'] == self::CHECK_LEVEL_REQUIRED) {
				$status = self::CHECK_STATUS_FAILED;
				$overallStatus = self::CHECK_STATUS_FAILED;
			} else {
				$status = self::CHECK_STATUS_RECOMMENDED;
				if ($overallStatus == self::CHECK_STATUS_PASSED)
					$overallStatus = self::CHECK_STATUS_RECOMMENDED;
			}
			$result[$name] = [
				'passed' => $passed,
				'status' => $status,
				'label' => __("art-check-$name-$status", $labelargs),
			];
		}
		$order = [self::CHECK_STATUS_PASSED, self::CHECK_STATUS_FAILED, self::CHECK_STATUS_RECOMMENDED];
		uasort($result, function($a, $b) use ($order) {return array_search($a['status'], $order) - array_search($b['status'], $order); });

		return [$overallStatus, $result];
	}

	/**
	 * Renders a checklist div containing the given check result (as
	 * returned by runChecks).
	 *
	 * @param bool $checkStatus
	 * @param array<string, array{
	 *     passed: bool,
	 *     status: self::CHECK_STATUS_*,
	 *     label: string,
	 *   }> $checks
	 * @return HyphaDomElement
	 */
	protected function renderChecklist($checkStatus, $checks) {
		$wrapper = $this->html->createElement('div');
		$wrapper->addClass('review-start-checklist');
		$wrapper->addClass($checkStatus);

		$header = $this->html->createElement('h2')->appendTo($wrapper);
		$header->setText(__('art-check-header'));

		$status = $this->getStatus();
		if ($status == self::STATUS_DRAFT) {
			if ($checkStatus == self::CHECK_STATUS_FAILED)
				$msg = __('art-check-cannot-start-review');
			else
				$msg = __('art-check-can-start-review');
		} else if ($status == self::STATUS_REVIEW || $status == self::STATUS_APPROVED) {
			if ($checkStatus == self::CHECK_STATUS_FAILED)
				$msg = __('art-check-cannot-publish');
			else
				$msg = __('art-check-can-publish');
		} else {
			$msg = '';
		}

		if ($msg) {
			$prefix = $this->html->createElement('span')->appendTo($wrapper);
			$prefix->addClass('prefix');
			$prefix->setText($msg);
		}

		$list = $this->html->createElement('ul')->appendTo($wrapper);
		foreach ($checks as $name => $check) {
			$li = $this->html->createElement('li')->appendTo($list);
			$li->addClass($name);
			$li->addClass($check['status']);
			$li->setText($check['label']);
		}
		return $wrapper;
	}

	protected function appendAuthorAndTimestamp($container, $article) {
		$doc = $container->document();

		$author = $article->getAttribute(self::FIELD_NAME_AUTHOR);
		$publishedTimestamp = ltrim($article->getAttribute(self::FIELD_NAME_PUBLISHED_AT), 't');

		if ($author) {
			$author_div = $doc->createElement('div');
			$author_div->addClass('author');
			$author_div->setText(__('art-by') . ' ' . $author);
			$container->append($author_div);
		}
		if ($publishedTimestamp) {
			/** @var HyphaDomElement $publish */
			$publish_div = $doc->createElement('div');
			$publish_div->setAttribute('class', 'published_at');
			/** @var HyphaDomElement $publishDate */
			$publishDate = $doc->createElement('span');
			$publishDate->setAttribute('class', 'date');
			$publishDate->text(strftime(__('art-date-format-date'), $publishedTimestamp));
			$publish_div->append($publishDate);
			/** @var HyphaDomElement $publishTime */
			$publishTime = $doc->createElement('span');
			$publishTime->setAttribute('class', 'time');
			$publishTime->text(strftime(__('art-date-format-time'), $publishedTimestamp));
			$publish_div->append($publishTime);
			$container->append($publish_div);
		}
	}



	/**
	 * Deletes the article.
	 *
	 * @param HyphaRequest $request
	 * @return array
	 */
	protected function deleteAction(HyphaRequest $request) {
		if (!isAdmin()) {
			notify('error', __('admin-rights-needed-to-perform-action'));
			return null;
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
	protected function editView(HyphaRequest $request) {
		if (!isUser()) {
			notify('error', __('login-to-perform-action'));
			return ['redirect', $this->constructFullPath($this->pagename)];
		}

		// create form
		$formData = [
			self::FIELD_NAME_TITLE => html_entity_decode(strip_tags($this->xml->find(self::FIELD_NAME_TITLE)->text())),
			self::FIELD_NAME_AUTHOR => $this->xml->find(self::FIELD_NAME_ARTICLE)->getAttribute(self::FIELD_NAME_AUTHOR),
			self::FIELD_NAME_TEXT => $this->xml->find(self::FIELD_NAME_TEXT)->children(),
			self::FIELD_NAME_EXCERPT => $this->xml->find(self::FIELD_NAME_EXCERPT)->children(),
			self::FIELD_NAME_FEATURED_IMAGE => $this->xml->documentElement->getOrCreate(self::FIELD_NAME_FEATURED_IMAGE)->text(),
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
	protected function editViewRender(HyphaRequest $request, WymHTMLForm $form) {
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
	protected function editAction(HyphaRequest $request) {
		if (!isUser()) {
			notify('error', __('login-to-perform-action'));
			return ['redirect', $this->constructFullPath($this->pagename)];
		}

		$formData = $request->getPostData();

		// create form
		$form = $this->createEditForm($formData);

		if (array_key_exists(self::FIELD_NAME_FEATURED_IMAGE, $_FILES) && $_FILES[self::FIELD_NAME_FEATURED_IMAGE]['size'] > 0) {
			$form->handleImageUpload(self::FIELD_NAME_FEATURED_IMAGE, $_FILES[self::FIELD_NAME_FEATURED_IMAGE]);
		}

		// process form if there are no errors
		if (!empty($form->errors)) {
			return $this->editViewRender($request, $form);
		}

		$this->xml->lockAndReload();

		$author = $form->dataFor(self::FIELD_NAME_AUTHOR);
		$this->xml->find(self::FIELD_NAME_ARTICLE)->setAttribute(self::FIELD_NAME_AUTHOR, $author);
		$this->xml->find(self::FIELD_NAME_ARTICLE)->setAttribute(self::FIELD_NAME_UPDATED_AT, 't' . time());
		$this->xml->find(self::FIELD_NAME_TITLE)->setText($form->dataFor(self::FIELD_NAME_TITLE));
		$this->xml->find(self::FIELD_NAME_TEXT)->setHtml($form->dataFor(self::FIELD_NAME_TEXT));
		$this->xml->find(self::FIELD_NAME_EXCERPT)->setHtml($form->dataFor(self::FIELD_NAME_EXCERPT));
		$this->xml->find(self::FIELD_NAME_METHOD)->setHtml($form->dataFor(self::FIELD_NAME_METHOD));
		$this->xml->find(self::FIELD_NAME_SOURCES)->setHtml($form->dataFor(self::FIELD_NAME_SOURCES));
		$this->xml->documentElement->getOrCreate(self::FIELD_NAME_FEATURED_IMAGE)->setText($form->dataFor(self::FIELD_NAME_FEATURED_IMAGE));

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
	protected function statusChangeAction(HyphaRequest $request, $newStatus) {
		if (!isUser()) {
			notify('error', __('login-to-perform-action'));
			return ['redirect', $this->constructFullPath($this->pagename)];
		}

		list($checkStatus, $_checks) = $this->runChecks();
		if ($checkStatus === self::CHECK_STATUS_FAILED) {
			$success = false;
		} else {
			$success = $this->updateToNewStatus($newStatus);
		}
		if ($success) {
			notify('success', ucfirst(__('art-successfully-updated')));
		} else {
			// Should not normally happen (e.g. the button
			// for this change should not exist).
			notify('error', __('art-unsupported-status-change'));
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
	protected function approveAction(HyphaRequest $request) {
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
			$approve->setAttribute(self::FIELD_NAME_USER, $userId);
			$approve->setAttribute(self::FIELD_NAME_CREATED_AT, 't' . time());
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
	protected function discussionAction(HyphaRequest $request) {
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

		$dataFieldSuffix = $this->constructDiscussionFieldSuffix($type);

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
		$discussionContainer = $this->xml->find($type)->first();
		$discussion = $this->addDiscussion($discussionContainer, $form, $dataFieldSuffix);
		$comment = $this->addDiscussionComment($discussion, $form, $dataFieldSuffix);
		$this->xml->saveAndUnlock();

		$blocking = (bool)$discussion->getAttribute(self::FIELD_NAME_DISCUSSION_BLOCKING);
		$pending = (bool)$comment->getAttribute(self::FIELD_NAME_DISCUSSION_COMMENT_PENDING);

		// send notification mails if needed
		if ($review && $blocking) $this->sendBlockingDiscussionMail($discussion);
		if ($public && $pending) $this->sendCommentConfirmMail($comment);
		if ($public && !$pending) $this->sendNewCommentMail($comment);

		$msg = $pending ? __('art-comment-pending') : __('art-comment-posted');
		notify('success', ucfirst($msg));

		return ['redirect', $this->constructFullPath($this->pagename)];
	}

	/**
	 * @param HyphaRequest $request
	 * @param WymHTMLForm $form
	 * @param string $type
	 * @param null|HyphaDomElement $discussion
	 * @return null
	 */
	protected function discussionRender(HyphaRequest $request, WymHTMLForm $form, $type, HyphaDomElement $discussion = null) {
		// update the form dom so that error can be displayed, if there are any
		$form->updateDom();

		/** @var HyphaDomElement $main */
		$main = $this->html->find('#main');
		$main->append($form);

		$actionButton = $this->makeDiscussionActionButton($type, $discussion);

		$main->append($actionButton);

		return null;
	}

	protected function makeDiscussionActionButton($type, HyphaDomElement $discussion = null) {
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
	protected function discussionCommentAction(HyphaRequest $request) {
		$discussionId = $request->getArg(1);

		$this->xml->lockAndReload();
		/** @var HyphaDomElement $discussion */
		$discussion = $this->xml->document()->getElementById($discussionId);
		if (!$discussion instanceof HyphaDomElement) {
			notify('error', __('not-found')); // 404
			return null;
		}

		$type = $discussion->parent()->nodeName;
		$review = self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER === $type;

		$dataFieldSuffix = $this->constructDiscussionFieldSuffix($type, $discussion);

		// create form to comment on a discussion
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

		$comment = $this->addDiscussionComment($discussion, $form, $dataFieldSuffix);
		$this->xml->saveAndUnlock();
		$pending = (bool)$comment->getAttribute(self::FIELD_NAME_DISCUSSION_COMMENT_PENDING);
		if (!$review) {
			$pending ? $this->sendCommentConfirmMail($comment) : $this->sendNewCommentMail($comment);
		}

		$msg = $pending ? __('art-comment-pending') : __('art-comment-posted');
		notify('success', ucfirst($msg));

		return ['redirect', $this->constructFullPath($this->pagename)];
	}

	/**
	 * Closes a review or public discussion.
	 *
	 * @param HyphaRequest $request
	 * @return array|null
	 */
	protected function discussionClosedAction(HyphaRequest $request) {
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
	 * Constructs the WymHTMLForm for the comment form.
	 *
	 * @param HyphaDomElement $comment
	 * @param array $values
	 * @return HTMLForm
	 */
	protected function createCommentModerateForm(HyphaDomElement $comment, array $values = []) {
		$html = <<<EOF
			<div>
				<strong><label for="[[field-name-reason]]">[[reason]]</label></strong>
				<br/>
				<input type="text" name="[[field-name-reason]]" id="[[field-name-reason]]"></input>
			</div>
EOF;

		$vars = [
			'reason' => __('art-moderate-reason'),
			'field-name-reason' => self::FIELD_NAME_MODERATE_REASON,
		];

		$html = hypha_substitute($html, $vars);
		return new HTMLForm($html, $values);
	}

	/**
	 * Allows editing the moderation status of a public comment.
	 *
	 * @param HyphaRequest $request
	 * @return array|null
	 */
	protected function commentModerateView(HyphaRequest $request) {
		if (!$this->O_O->isUser()) {
			notify('error', __('login-to-perform-action'));
			return ['redirect', $this->constructFullPath($this->pagename)];
		}

		/** @var HyphaDomElement|null $comment */
		$commentId = $request->getArg(1);
		$comment = $this->xml->document()->getElementById($commentId);
		if (!$comment instanceof HyphaDomElement || $comment->tagName !== self::FIELD_NAME_DISCUSSION_COMMENT) {
			$this->xml->unlock();
			notify('error', __('not-found')); // 404
		}

		$values = [
			self::FIELD_NAME_MODERATE_REASON => $comment->getAttribute(self::FIELD_NAME_DISCUSSION_MODERATION_REASON),
		];
		$form = $this->createCommentModerateForm($comment, $values);

		return $this->commentModerateViewRender($request, $form, $comment);
	}

	/**
	 * Appends moderate form to #main and adds buttons
	 *
	 * @return null
	 */
	protected function commentModerateViewRender(HyphaRequest $request, HTMLForm $form, HyphaDomElement $comment) {
		// update the form dom so that error can be displayed, if there are any
		$form->updateDom();
		$hidden = $comment->getAttribute(self::FIELD_NAME_DISCUSSION_MODERATION_STATUS) === self::MODERATION_STATUS_HIDDEN;
		$deleted = $comment->getAttribute(self::FIELD_NAME_DISCUSSION_MODERATION_STATUS) === self::MODERATION_STATUS_DELETED;

		$main = $this->html->find('#main')->first();
		$commentDiv = $this->html->createElement('div')->appendTo($main);
		$commentDiv->addClass('moderation-comment');

		$prefix = $this->html->createElement('span')->appendTo($commentDiv);
		$prefix->addClass('prefix');
		if ($hidden) {
			$prefix->setText(__('art-comment-is-hidden'));
		} else if ($deleted) {
			$prefix->setText(__('art-comment-is-deleted'));
		} else {
			$prefix->setText(__('art-comment-to-moderate'));
		}

		$this->createCommentDomElement($comment)->appendTo($commentDiv);

		$main->append($form);

		$commands = $this->html->find('#pageEndCommands');
		$commands->append($this->makeActionButton(__('cancel')));

		$path = str_replace('{id}', $comment->getId(), self::PATH_COMMENTS_MODERATE);
		// Show a hide button, but only if not already deleted,
		// since that is confusing (and you can still unmoderate
		// and then hide).
		if (!$deleted) {
			$hide_title = $hidden ? __('art-moderate-change-reason') : __('art-moderate-hide-comment');
			$commands->append($this->makeActionButton($hide_title, $path, self::CMD_COMMENT_MODERATE_HIDE ));
		}
		$delete_title = $deleted ? __('art-moderate-change-reason') : __('art-moderate-delete-comment');
		$commands->append($this->makeActionButton($delete_title, $path, self::CMD_COMMENT_MODERATE_DELETE ));

		if ($hidden || $deleted) {
			$commands->append($this->makeActionButton(__('art-unmoderate-comment'), $path, self::CMD_COMMENT_UNMODERATE ));
		}

		return null;
	}

	/**
	 * Sets the moderation status of a public comment.
	 *
	 * @param HyphaRequest $request
	 * @return array|null
	 */
	protected function commentModerateAction(HyphaRequest $request) {
		if (!$this->O_O->isUser()) {
			notify('error', __('login-to-perform-action'));
			return ['redirect', $this->constructFullPath($this->pagename)];
		}

		$this->xml->lockAndReload();

		/** @var HyphaDomElement|null $comment */
		$commentId = $request->getArg(1);
		$comment = $this->xml->document()->getElementById($commentId);
		if (!$comment instanceof HyphaDomElement || $comment->tagName != self::FIELD_NAME_DISCUSSION_COMMENT) {
			$this->xml->unlock();
			notify('error', __('not-found')); // 404
			return null;
		}

		$digestArgs = [
			'commenter' => $comment->getAttribute(self::FIELD_NAME_DISCUSSION_COMMENTER_NAME),
			'moderator' => $this->O_O->getUser()->getAttribute('fullname'),
			'title' => $this->getTitle(),
		];

		if ($request->getCommand() === self::CMD_COMMENT_UNMODERATE) {
			$comment->removeAttribute(self::FIELD_NAME_DISCUSSION_MODERATION_STATUS);
			writeToDigest(__('art-digest-comment-unmoderated', $digestArgs), 'comment moderation');
		} else {
			$form = $this->createCommentModerateForm($comment, $request->getPostData());

			if (!empty($form->errors)) {
				return $this->commentModerateViewRender($request, $form, $comment);
			}

			$reason = $form->dataFor(self::FIELD_NAME_MODERATE_REASON);
			$digestArgs['reason'] = $reason;

			if ($request->getCommand() === self::CMD_COMMENT_MODERATE_HIDE) {
				$status = self::MODERATION_STATUS_HIDDEN;
				writeToDigest(__('art-digest-comment-hidden', $digestArgs), 'comment moderation');
			} else {
				$status = self::MODERATION_STATUS_DELETED;
				writeToDigest(__('art-digest-comment-deleted', $digestArgs), 'comment moderation');
			}

			$comment->setAttribute(self::FIELD_NAME_DISCUSSION_MODERATION_REASON, $reason);
			$comment->setAttribute(self::FIELD_NAME_DISCUSSION_MODERATION_STATUS, $status);
		}

		$this->xml->saveAndUnlock();

		notify('success', ucfirst(__('art-successfully-updated')));
		return ['redirect', $this->constructFullPath($this->pagename)];
	}

	/**
	 * Confirms the comment that was submitted by a commenter.
	 *
	 * @param HyphaRequest $request
	 * @return array|null
	 */
	protected function commentConfirmAction(HyphaRequest $request) {
		$code = isset($_GET['code']) ? $_GET['code'] : null;
		if (null == $code) {
			notify('error', __('missing-arguments'));
			return null;
		}

		$this->xml->lockAndReload();

		/** @var NodeList $commentNodeList */
		$xpath = '//' . self::FIELD_NAME_DISCUSSION_COMMENT . '[@' . self::FIELD_NAME_DISCUSSION_COMMENT_CONFIRM_CODE . '=' . xpath_encode($code) . ']';
		$commentNodeList = $this->xml->findXPath($xpath);
		$comment = $commentNodeList->first();
		if (!$comment instanceof HyphaDomElement) {
			$this->xml->unlock();
			notify('error', __('art-invalid-code'));
			return null;
		}

		$confirmedComment = false;
		$confirmedSubscriber = false;

		if ((bool)$comment->getAttribute(self::FIELD_NAME_DISCUSSION_COMMENT_PENDING)) {
			$comment->setAttribute(self::FIELD_NAME_DISCUSSION_COMMENT_PENDING, false);
			$confirmedComment = true;
		}

		/** @var NodeList $confirmCodeNodeList */
		$xpath = '//' . self::FIELD_NAME_DISCUSSION_SUBSCRIBER_CONFIRM_CODE . '[@' . self::FIELD_NAME_DISCUSSION_SUBSCRIBER_CODE . '=' . xpath_encode($code) . ']';
		$confirmCodeNodeList = $this->xml->findXPath($xpath);
		if ($confirmCodeNodeList->count() >= 1) {
			/** @var HyphaDomElement $subscriber */
			$subscriber = $confirmCodeNodeList->first()->parent();
			$subscriber->setAttribute(self::FIELD_NAME_DISCUSSION_SUBSCRIBER_STATUS, self::SUBSCRIBER_STATUS_CONFIRMED);
			$subscriber->setAttribute(self::FIELD_NAME_DISCUSSION_SUBSCRIBER_UNSUBSCRIBE_CODE, $this->constructCode());

			// no need anymore for other confirmation codes
			/** @var NodeList $confirmations */
			$confirmations = $subscriber->find(self::FIELD_NAME_DISCUSSION_SUBSCRIBER_CONFIRM_CODE);
			$confirmations->remove();
			$confirmedSubscriber = true;
		}

		if ($confirmedComment || $confirmedSubscriber) {
			$this->xml->saveAndUnlock();
		} else {
			$this->xml->unlock();
		}

		if ($confirmedComment) {
			$this->sendNewCommentMail($comment);
		}

		notify('success', ucfirst(__('art-comment-posted')));
		return ['redirect', $this->constructFullPath($this->pagename)];
	}

	/**
	 * @param HyphaRequest $request
	 * @return array|null
	 */
	protected function unsubscribeAction(HyphaRequest $request) {
		$code = isset($_GET['code']) ? $_GET['code'] : null;
		if (null == $code) {
			notify('error', __('missing-arguments'));
			return null;
		}

		$this->xml->lockAndReload();

		/** @var NodeList $subscriberContainer */
		$xpath = '//' . self::FIELD_NAME_DISCUSSION_CONTAINER . '//' . self::FIELD_NAME_DISCUSSION_SUBSCRIBER . '[@' . self::FIELD_NAME_DISCUSSION_SUBSCRIBER_UNSUBSCRIBE_CODE . '=' . xpath_encode($code) . ']';
		/** @var HyphaDomElement $subscriber */
		$subscriber = $this->xml->findXPath($xpath)->first();
		if (!$subscriber instanceof HyphaDomElement) {
			$this->xml->unlock();
			notify('error', __('art-invalid-code'));
			return null;
		}

		if ($subscriber->getAttribute(self::FIELD_NAME_DISCUSSION_SUBSCRIBER_STATUS) === self::SUBSCRIBER_STATUS_PENDING) {
			// weird case, should never happen, let's handle it anyway
			$this->xml->unlock();
			notify('error', __('art-subscriber-has-invalid-status'));
		}

		if ($subscriber->getAttribute(self::FIELD_NAME_DISCUSSION_SUBSCRIBER_STATUS) != self::SUBSCRIBER_STATUS_UNSUBSCRIBED) {
			$subscriber->setAttribute(self::FIELD_NAME_DISCUSSION_SUBSCRIBER_STATUS, self::SUBSCRIBER_STATUS_UNSUBSCRIBED);
			$this->xml->saveAndUnlock();
		} else {
			$this->xml->unlock();
		}

		notify('success', ucfirst(__('art-subscriber-deleted')));
		return ['redirect', $this->constructFullPath($this->pagename)];
	}

	/**
	 * Updates the article to the status.
	 *
	 * @param string $newStatus
	 * @return bool
	 */
	protected function updateToNewStatus($newStatus) {
		$currentStatus = $this->getStatus();
		if (!isset($this->statusMtx[$currentStatus]) || !isset($this->statusMtx[$currentStatus][$newStatus])) {
			return false;
		}

		$this->xml->lockAndReload();
		/** @var HyphaDomElement $article */
		$article = $this->xml->find(self::FIELD_NAME_ARTICLE);
		$article->setAttribute(self::FIELD_NAME_STATUS, $newStatus);
		if ($newStatus === self::STATUS_PUBLISHED) {
			$article->setAttribute(self::FIELD_NAME_PUBLISHED_AT, 't' . time());
		}
		$this->xml->saveAndUnlock();

		// remove protected flag
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
	 * Adds a discussion DOM element to the database.
	 *
	 * @param NodeList|HyphaDomElement $discussionContainer
	 * @param WymHTMLForm $form
	 * @param string dataFieldSuffix
	 * @return HyphaDomElement
	 */
	protected function addDiscussion(HyphaDomElement $discussionContainer, WymHTMLForm $form, $dataFieldSuffix) {
		$this->xml->requireLock();

		/** @var HyphaDomElement $discussion */
		$discussion = $this->xml->createElement(self::FIELD_NAME_DISCUSSION);
		$discussion->generateId();
		$discussionContainer->append($discussion);

		$type = $discussionContainer->nodeName;
		$review = self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER === $type;
		if ($review) {
			$discussion->setAttribute(self::FIELD_NAME_USER, $this->O_O->getUser()->getAttribute('id'));
		}

		$blocking = $form->dataFor(self::FIELD_NAME_DISCUSSION_BLOCKING . $dataFieldSuffix) !== null;
		$discussion->setAttribute(self::FIELD_NAME_DISCUSSION_BLOCKING, $blocking);
		if ($blocking) {
			$discussion->setAttribute(self::FIELD_NAME_DISCUSSION_BLOCK_RESOLVED, false);
		}
		$discussion->setAttribute(self::FIELD_NAME_DISCUSSION_CLOSED, false);

		return $discussion;
	}

	/**
	 * Adds a comment DOM element to a discussion in the database.
	 *
	 * @param HyphaDomElement $discussion
	 * @param WymHTMLForm $form
	 * @param string dataFieldSuffix
	 * @return HyphaDomElement
	 */
	protected function addDiscussionComment(HyphaDomElement $discussion, WymHTMLForm $form, $dataFieldSuffix) {
		$this->xml->requireLock();

		/** @var HyphaDomElement $comment */
		$comment = $this->xml->createElement(self::FIELD_NAME_DISCUSSION_COMMENT);
		$comment->generateId();
		$discussion->append($comment);

		$text = $form->dataFor(self::FIELD_NAME_DISCUSSION_COMMENT . $dataFieldSuffix);
		$comment->setText($text);
		if (isUser()) {
			$comment->setAttribute(self::FIELD_NAME_USER, $this->O_O->getUser()->getAttribute('id'));
		}
		$comment->setAttribute(self::FIELD_NAME_CREATED_AT, 't' . time());

		$type = $discussion->parent()->nodeName;
		$review = self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER === $type;
		if (!$review) {
			if (isUser()) {
				$userEmail = $this->O_O->getUser()->getAttribute('email');
				$userName = $this->O_O->getUser()->getAttribute('fullname');
			} else {
				$code = $this->constructCode();
				$userEmail = $form->dataFor(self::FIELD_NAME_DISCUSSION_COMMENTER_EMAIL . $dataFieldSuffix);
				$userName = $form->dataFor(self::FIELD_NAME_DISCUSSION_COMMENTER_NAME . $dataFieldSuffix);
				$comment->setAttribute(self::FIELD_NAME_DISCUSSION_COMMENT_PENDING, true);
				$comment->setAttribute(self::FIELD_NAME_DISCUSSION_COMMENT_CONFIRM_CODE, $code);

				if ($form->dataFor(self::FIELD_NAME_DISCUSSION_COMMENTER_SUBSCRIBE . $dataFieldSuffix)) {
					/** @var HyphaDomElement $subscriberContainer */
					$subscriberContainer = $discussion->getOrCreate(self::FIELD_NAME_DISCUSSION_SUBSCRIBER_CONTAINER);

					$subscriberEmail = trim(strtolower($userEmail));

					// get or create subscriber element by email
					$xpath = './/' . self::FIELD_NAME_DISCUSSION_SUBSCRIBER .
						'[@' . self::FIELD_NAME_DISCUSSION_SUBSCRIBER_EMAIL . '=' . xpath_encode($subscriberEmail) .
						' and @' . self::FIELD_NAME_DISCUSSION_SUBSCRIBER_STATUS . '!=' . xpath_encode(self::SUBSCRIBER_STATUS_UNSUBSCRIBED) .
						']';
					/** @var NodeList $subscriberNodeList */
					$subscriber = $subscriberContainer->findXPath($xpath)->first();
					if (!$subscriber instanceof HyphaDomElement) {
						$subscriber = $this->xml->createElement(self::FIELD_NAME_DISCUSSION_SUBSCRIBER);
						$subscriber->setAttribute(self::FIELD_NAME_DISCUSSION_SUBSCRIBER_STATUS, self::SUBSCRIBER_STATUS_PENDING);
						$subscriber->setAttribute(self::FIELD_NAME_DISCUSSION_SUBSCRIBER_EMAIL, $subscriberEmail);
						$subscriber->setAttribute(self::FIELD_NAME_DISCUSSION_SUBSCRIBER_NAME, $userName);
						$subscriberContainer->append($subscriber);
					}

					// if still pending, add notification confirm code
					if ($subscriber->getAttribute(self::FIELD_NAME_DISCUSSION_SUBSCRIBER_STATUS) === self::SUBSCRIBER_STATUS_PENDING) {
						/** @var HyphaDomElement $subscribeConfirmCode */
						$subscribeConfirmCode = $this->xml->createElement(self::FIELD_NAME_DISCUSSION_SUBSCRIBER_CONFIRM_CODE);
						$subscribeConfirmCode->setAttribute(self::FIELD_NAME_DISCUSSION_SUBSCRIBER_CODE, $code);
						$subscriber->append($subscribeConfirmCode);
					}
				}
			}
			$comment->setAttribute(self::FIELD_NAME_DISCUSSION_COMMENTER_EMAIL, $userEmail);
			$comment->setAttribute(self::FIELD_NAME_DISCUSSION_COMMENTER_NAME, $userName);
		}

		return $comment;
	}

	/**
	 * Creates the approves HTML DOM element.
	 *
	 * @return HyphaDomElement
	 */
	protected function createApprovesDomElement() {
		/** @var HyphaDomElement $approvesDomElement */
		$approvesDomElement = $this->html->createElement('div');
		$approvesDomElement->attr('class', 'approves');
		$approves = $this->xml->find(self::FIELD_NAME_APPROVE_CONTAINER)->find(self::FIELD_NAME_APPROVE)->toArray();
		$approvesDomElement->append('<h2>' . __('art-approves') . '</h2>');

		/** @var HyphaDomElement $list */
		$list = $this->html->createElement('ul');
		foreach ($approves as $approve) {
			$createdAt = date('j-m-y, H:i', ltrim($approve->getAttribute(self::FIELD_NAME_CREATED_AT), 't'));
			$approverId = $approve->getAttribute(self::FIELD_NAME_USER);
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

	protected function processCommentText($commentText){
		$commentHtml = htmlspecialchars($commentText);
		$tryToParseLinks = preg_replace_callback(self::URL_REGEX, function($matches) { // replace urls with links
			$url = $text = $matches[0];
			if (count($matches) < 3 || !$matches[2])
				$url = 'https://' . $url;
			return '<a href="' . $url . '" target="_blank">' . $text . '</a>';
		}, $commentHtml);

		if(!is_null($tryToParseLinks)) $commentHtml = $tryToParseLinks; // In case regex fails

		$commentHtml = nl2br($commentHtml);
		return $commentHtml;
	}

	/**
	 * Render a single comment.
	 *
	 * @param HyphaDomElement $comment
	 * @param bool $review
	 * @param bool $firstAndBlocking
	 * @param bool $resolved
	 * @return DomNodeList
	 */
	protected function createCommentDomElement(HyphaDomElement $comment, $review = false, $firstAndBlocking = false, $resolved = false) {
		$createdAt = date('j-m-y, H:i', ltrim($comment->getAttribute(self::FIELD_NAME_CREATED_AT), 't'));
		$committerName = $this->getCommentCommenter($comment);
		$commentText = $comment->getText();

		if (strlen($commentText) > self::MAX_COMMENT_LENGTH) {
			$firstWordBreak = self::MIN_COMMENT_STUB_LENGTH + strcspn($commentText, " \n", self::MIN_COMMENT_STUB_LENGTH);
			$commentStub = substr($commentText,0, $firstWordBreak);
			$commentHtml = '<div class="comment-full" style="display:none;">'.$this->processCommentText($commentText).'</div>
			<span class="comment-stub">'.$this->processCommentText($commentStub).'...</span>
			<a class="comment-read-more" onclick="
				const commentToggle = event.target;
				const commentStub = commentToggle.previousElementSibling;
				const commentFull = commentStub.previousElementSibling;
				commentStub.style.display=\'none\';
				commentFull.style.display=\'block\';
				commentToggle.style.display=\'none\';
			">' . __('art-read-more') . '...</a>';
		} else {
			$commentHtml = $this->processCommentText($commentText);
		}

		$commentHtml .= ' <p>' . __('art-by') . ' <strong>' . htmlspecialchars($committerName) . '</strong> ' . __('art-at') . ' ' . htmlspecialchars($createdAt);
		if (!$review && isUser()) {
			$committerEmail = $comment->getAttribute(self::FIELD_NAME_DISCUSSION_COMMENTER_EMAIL);
			$commentHtml .= ' <span> | ' . __('art-email') . ': <a href="mailto:' . htmlspecialchars($committerEmail) . '">' . htmlspecialchars($committerEmail) . '</a></span>';
		}
		if ($firstAndBlocking) {
			$commentHtml .= ' | ' . ($resolved ? __('art-is-resolved') : __('art-is-blocking'));
		}
		$commentHtml .= '</p>';
		return $this->html->create($commentHtml);
	}

	/**
	 * Creates discussions HTML DOM element.
	 *
	 * @param string $type
	 * @return HyphaDomElement
	 */
	protected function createDiscussionsDomElement($type) {
		/** @var HyphaDomElement $discussionsDomElement */
		$discussionsDomElement = $this->html->createElement('div');
		$review = self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER === $type;

		/** @var HyphaDomElement $container */
		$container = $this->xml->find($review ? self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER : self::FIELD_NAME_DISCUSSION_PUBLIC_CONTAINER);

		/** @var HyphaDomElement[] $discussions */
		$discussions = $container->find(self::FIELD_NAME_DISCUSSION)->toArray();

		// in case of no discussions
		if (empty($discussions)) {
			/** @var HyphaDomElement $noCommentContainer */
			$noCommentContainer = $this->html->createElement('ul')->appendTo($discussionsDomElement);
			$noComment = $this->html->create('li')->appendTo($noCommentContainer);
			$noComment->text(__('art-no-comments-yet'));
		}

		// [open => [blocking, non-blocking], closed => [blocking, non-blocking]]
		$reviewCommentContainersSorted = [0 => [0 => [], 1 => [],], 1 => [0 => [], 1 => [],],];

		foreach ($discussions as $discussion) {
			$blocking = (bool)$discussion->getAttribute(self::FIELD_NAME_DISCUSSION_BLOCKING);
			$closed = (bool)$discussion->getAttribute(self::FIELD_NAME_DISCUSSION_CLOSED);
			$resolved = (bool)$discussion->getAttribute(self::FIELD_NAME_DISCUSSION_BLOCK_RESOLVED);

			// create comment list
			/** @var HyphaDomElement $commentList */
			$commentList = $this->html->createElement('ul');

			/** @var NodeList|HyphaDomElement[] $comments */
			$comments = $discussion->find(self::FIELD_NAME_DISCUSSION_COMMENT);
			$firstComment = true;
			foreach ($comments as $comment) {
				// skip pending non-review comments
				if (!$review && (bool)$comment->getAttribute(self::FIELD_NAME_DISCUSSION_COMMENT_PENDING)) {
					continue;
				}

				$li = $this->html->createElement('li')->appendTo($commentList);
				$commentHtml = $this->createCommentDomElement($comment, $review, $firstComment && $blocking, $resolved);
				$hidden = $comment->getAttribute(self::FIELD_NAME_DISCUSSION_MODERATION_STATUS) === self::MODERATION_STATUS_HIDDEN;
				$deleted = $comment->getAttribute(self::FIELD_NAME_DISCUSSION_MODERATION_STATUS) === self::MODERATION_STATUS_DELETED;
				if ($hidden || $deleted) {
					if ($deleted) {
						$li->addClass('deleted');
						$placeholder = $this->html->createElement('div')->appendTo($li);
					} else {
						$li->addClass('hidden');
						$details = $this->html->createElement('details')->appendTo($li);
						$placeholder = $this->html->createElement('summary')->appendTo($details);
						$commentHtml->appendTo($details);
					}
					$placeholder->addClass('moderated-placeholder');
					$statusSpan = $this->html->createElement('span')->appendTo($placeholder);
					if ($deleted) {
						$statusSpan->setText(__('art-comment-is-deleted'));
					} else {
						$statusSpan->setText(__('art-comment-is-hidden'));
					}
					$statusSpan->addClass('moderate-status');
					$moderateReason = $comment->getAttribute(self::FIELD_NAME_DISCUSSION_MODERATION_REASON);
					if ($moderateReason) {
						$reasonSpan = $this->html->createElement('span')->appendTo($placeholder);
						$reasonSpan->addClass('moderate-reason');
						$reasonSpan->setText(' (' . $moderateReason . ')');
					}
					if (!$deleted) {
						$showSpan = $this->html->createElement('span')->appendTo($placeholder);
						$showSpan->setText(__('art-moderate-show-comment'));
						$showSpan->addClass('show-moderated');
					}
				} else {
					$li->append($commentHtml);
				}

				if ($firstComment) {
					$li->addClass("first-comment");
				}

				if (!$review && $this->O_O->isUser()) {
					$moderate = $this->html->createElement('a');
					$moderate->addClass('moderate-link');
					$path = $this->pagename . '/' . str_replace('{id}', $comment->getId(), self::PATH_COMMENTS_MODERATE);
					$moderate->setAttribute('href', $this->constructFullPath($path));
					if ($hidden || $deleted) {
						$moderate->setText(__('art-moderate-modify'));
						$placeholder->append($moderate);
					} else {
						$moderate->setText(__('art-moderate-comment'));
						$commentHtml->append($moderate);
					}
				}
				$firstComment = false;
			}

			if (!$commentList->hasChildNodes()) {
				// if there are no comments to be displayed, all discussion parts do not need to be displayed.
				continue;
			}

			/** @var HyphaDomElement $discussionHtml */
			$discussionHtml = $this->html->createElement('div');
			$class = $type . '-comment-wrapper collapsed';
			foreach (['blocking' => $blocking, 'resolved' => $resolved, 'closed' => $closed] as $name => $isTrue) {
				if ($isTrue) {
					$class .= ' ' . $name;
				}
			}
			$discussionHtml->attr('class', $class);
			$discussionHtml->append($commentList);

			if ($review) {
				$msgId = null;
				if ($blocking) {
					if (!$resolved && ($discussion->getAttribute(self::FIELD_NAME_USER) === $this->O_O->getUser()->getAttribute('id') || isAdmin())) {
						$msgId = __('art-set-as-resolved');
					}
				} elseif (!$closed) {
					$msgId = __('art-set-as-closed');
				}
				if (null !== $msgId) {
					$path = str_replace('{id}', $discussion->getId(), self::PATH_DISCUSSIONS_CLOSED);
					$commentList->append('<p>' . $this->makeActionButton(__($msgId), $path, self::CMD_DISCUSSION_CLOSED) . '</p>');
				}
			}

			// display comment form if the discussion is still open
			if (!$closed) {
				// create form to comment on a discussion
				$dataFieldSuffix = $this->constructDiscussionFieldSuffix($type, $discussion);
				$formData = [
					self::FIELD_NAME_DISCUSSION_COMMENTER_SUBSCRIBE . $dataFieldSuffix => true,
				];
				$replyForm = $this->createCommentForm($type, $formData, $discussion);
				$replyForm->updateDom();
				$commentList->append($replyForm);
				$commentList->append($this->makeDiscussionActionButton($type, $discussion));
			}

			$reviewCommentContainersSorted[(int)$closed][(int)!$blocking][] = $discussionHtml;
		}

		foreach ($reviewCommentContainersSorted as $openClosedSorted) {
			foreach ($openClosedSorted as $blockingNonBLockingSorted) {
				foreach ($blockingNonBLockingSorted as $discussionHtml) {
					$discussionsDomElement->append($discussionHtml);
				}
			}
		}

		// create form to start a discussion
		$dataFieldSuffix = $this->constructDiscussionFieldSuffix($type);
		$formData = [
			self::FIELD_NAME_DISCUSSION_COMMENTER_SUBSCRIBE . $dataFieldSuffix => true,
		];
		$commentForm = $this->createCommentForm($type, $formData);
		$commentForm->updateDom();
		$discussionsDomElement->append($commentForm);
		$discussionsDomElement->append($this->makeDiscussionActionButton($type));

		$title = $review ? __('art-review-comments') : __('art-comments');
		$discussionsDomElement->append('<h2>' . $title . '</h2>');
		$discussionsDomElement->attr('class', $type . '-comments-wrapper');

		return $discussionsDomElement;
	}

	/**
	 * Creates a HTML form object for the article.
	 *
	 * @param array $values
	 * @return WymHTMLForm
	 */
	protected function createEditForm(array $values = []) {
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
				<div class="input-wrapper field_type_editor field_name_[[field-name-excerpt]]">
					<strong><label for="[[field-name-excerpt]]">[[excerpt]]</label></strong>[[info-button-excerpt]]<br><editor name="[[field-name-excerpt]]"></editor>
				</div>
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<div class="input-wrapper field_type_editor field_name_[[field-name-featured-image]]">
					<img data-preview-for="[[field-name-featured-image]]">
					<strong><label for="[[field-name-featured-image]]">[[featured-image]]</label></strong><br>
					<input type="file" id="[[field-name-featured-image]]" name="[[field-name-featured-image]]" accept="image/png,image/jpeg,image/jpg,image/gif,image/bmp" />
				</div>
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<div class="input-wrapper field_type_editor field_name_[[field-name-text]]">
					<strong><label for="[[field-name-text]]">[[text]]</label></strong>[[info-button-text]]<br><editor name="[[field-name-text]]"></editor>
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
			'excerpt' => __('art-excerpt'),
			'field-name-excerpt' => self::FIELD_NAME_EXCERPT,

			'featured-image' => __('art-featured-image'),
			'field-name-featured-image' => self::FIELD_NAME_FEATURED_IMAGE,

			'info-button-excerpt' => makeInfoButton('help-art-excerpt'),
			'text' => __('art-article'),
			'field-name-text' => self::FIELD_NAME_TEXT,
			'info-button-text' => makeInfoButton('help-art-text'),
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
	 * @param array $values
	 * @param null|HyphaDomElement $discussion
	 * @return WymHTMLForm
	 */
	protected function createCommentForm($type, array $values = [], HyphaDomElement $discussion = null) {
		$new = $discussion === null;

		$review = self::FIELD_NAME_DISCUSSION_REVIEW_CONTAINER === $type;

		$dataFieldSuffix = $this->constructDiscussionFieldSuffix($type, $discussion);

		$html = <<<EOF
			<div class="new-comment [[type]]">
			<div>
				<strong><label for="[[field-name-comment]]">[[comment]]</label></strong><textarea name="[[field-name-comment]]" id="[[field-name-comment]]"></textarea>
			</div>
EOF;

		$vars = [];
		$vars['type'] = $type;
		$vars['comment'] = $new ? __('art-comment-on-article') : __('art-comment-on-comment');
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
			// only non-users need to enter their name
			$html .= <<<EOF
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="[[field-name-name]]">[[name]]</label></strong><div class="label_suffix">[[un-anonymous]]</div> <input type="text" id="[[field-name-name]]" name="[[field-name-name]]" />
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="[[field-name-email]]">[[email]]</label></strong> <input type="text" id="[[field-name-email]]" name="[[field-name-email]]" />
			</div>
			<div class="section" style="padding:5px; margin-bottom:5px; position:relative;">
				<strong><label for="[[field-name-subscribe]]">[[subscribe]]</label></strong> <input type="checkbox" id="[[field-name-subscribe]]" name="[[field-name-subscribe]]" /> [[info-button-subscribe]]
			</div>
EOF;
			// make non-users aware why they need to enter their name
			$vars['comment'] .= ' ' . makeInfoButton('help-comment-rules');

			$vars['name'] = __('art-name');
			$vars['field-name-name'] = self::FIELD_NAME_DISCUSSION_COMMENTER_NAME . $dataFieldSuffix;
			$vars['un-anonymous'] = __('art-comment-unanonymous');
			$vars['email'] = __('art-email');
			$vars['field-name-email'] = self::FIELD_NAME_DISCUSSION_COMMENTER_EMAIL . $dataFieldSuffix;
			$vars['subscribe'] = __('art-subscribe');
			$vars['info-button-subscribe'] = makeInfoButton('help-art-subscribe');
			$vars['field-name-subscribe'] = self::FIELD_NAME_DISCUSSION_COMMENTER_SUBSCRIBE . $dataFieldSuffix;
		}
		$html .= '</div>';

		$html = hypha_substitute($html, $vars);

		return new WymHTMLForm($html, $values);
	}

	/**
	 * Constructs the field suffix for a discussion.
	 *
	 * @param string $type
	 * @param null|HyphaDomElement $discussion
	 * @return string
	 */
	protected function constructDiscussionFieldSuffix($type, HyphaDomElement $discussion = null) {
		return $discussion === null ? '/new_' . $type : '/' . $discussion->getId();
	}

	/**
	 * Constructs a random code with the given length.
	 *
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
		} catch (\Exception $e) {
		}
		return bin2hex(openssl_random_pseudo_bytes($length));
	}

	/**
	 * Sends "status has changed" mail to all users.
	 */
	protected function sendStatusChangeMail() {
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
	protected function sendCommentConfirmMail(HyphaDomElement $comment) {
		$code = $comment->getAttribute(self::FIELD_NAME_DISCUSSION_COMMENT_CONFIRM_CODE);
		$commentBody = $comment->textContent;

		$title = $this->getTitle();
		$path = str_replace(['{id}', '{code}'], [$comment->getId(), $code], self::PATH_DISCUSSIONS_COMMENT_CONFIRM);
		$linkToConfirm = $this->constructFullPath($this->pagename . '/' . $path);

		$email = $comment->getAttribute(self::FIELD_NAME_DISCUSSION_COMMENTER_EMAIL);
		$subject = __('art-confirm-comment-subject');
		$message = __('art-confirm-comment-body', [
			'sitename' => htmlspecialchars(hypha_getTitle()),
			'title' => htmlspecialchars($title),
			'comment' => nl2br(htmlspecialchars($commentBody)),
			'link' => htmlspecialchars($linkToConfirm),
		]);
		$this->sendMail($email, $subject, $message);
	}

	/**
	 * Sends "new comment" mail to all users.
	 *
	 * @param HyphaDomElement $comment
	 */
	protected function sendNewCommentMail(HyphaDomElement $comment) {
		// send newly created comment to all users
		$title = $this->getTitle();
		$linkToPage = $this->constructFullPath($this->pagename);
		$name = $this->getCommentCommenter($comment);
		$commentBody = $comment->getText();

		$subject = __('art-comment-subject', [
			'name' => $name,
			'title' => $title
		]);
		$messageArgs = [
			'name' => htmlspecialchars($name),
			'link' => htmlspecialchars($linkToPage),
			'title' => htmlspecialchars($title),
			'comment' => nl2br(htmlspecialchars($commentBody)),
		];
		$message = __('art-comment-body', $messageArgs);
		$this->sendMail(getUserEmailList(), $subject, $message);

		$subscriberSubject = __('art-comment-subscriber-subject', [
			'name' => $name,
			'title' => $title
		]);
		$subscriberMessage = __('art-comment-subscriber-body', $messageArgs);

		// also send newly create comment to all who subscribed to this discussion, with unsubscribe link
		foreach ($this->getSubscribers($comment) as $subscriber) {
			$email = $subscriber->getAttribute(self::FIELD_NAME_DISCUSSION_SUBSCRIBER_EMAIL);

			// Do not notify people of their own comments
			if ($email == $comment->getAttribute(self::FIELD_NAME_DISCUSSION_COMMENTER_EMAIL))
				continue;

			$code = $subscriber->getAttribute(self::FIELD_NAME_DISCUSSION_SUBSCRIBER_UNSUBSCRIBE_CODE);
			// include email for server record purposes
			$path = hypha_substitute(self::PATH_UNSUBSCRIBE_CODE, ['address' => $email, 'code' => $code]);
			$linkToUnsubscribe = $this->constructFullPath($this->pagename . '/' . $path);
			$message = $subscriberMessage;
			$message .= '<p><a href="' . htmlspecialchars($linkToUnsubscribe) . '">' . htmlspecialchars(__('art-unsubscribe')) . '</a></p>';
			$this->sendMail($email, $subscriberSubject, $message);
		}
	}

	/**
	 * @param HyphaDomElement $comment
	 * @return HyphaDomElement[]
	 */
	protected function getSubscribers(HyphaDomElement $comment) {
		/** @var HyphaDomElement $discussion */
		$discussion = $comment->parent();

		/** @var NodeList|HyphaDomElement[] $subscriberElements */
		$xpath = './/' . self::FIELD_NAME_DISCUSSION_SUBSCRIBER_CONTAINER . '/' . self::FIELD_NAME_DISCUSSION_SUBSCRIBER . '[@ ' . self::FIELD_NAME_DISCUSSION_SUBSCRIBER_STATUS . '=' . xpath_encode(self::SUBSCRIBER_STATUS_CONFIRMED) . ']';
		$subscriberElements = $discussion->findXPath($xpath);

		$subscribers = [];
		foreach ($subscriberElements as $subscriber) {
			$email = $subscriber->getAttribute(self::FIELD_NAME_DISCUSSION_SUBSCRIBER_EMAIL);
			$subscribers[$email] = $subscriber;
		}

		return array_values($subscribers);
	}

	/**
	 * Sends "new blocking discussion" mail to all users.
	 *
	 * @param HyphaDomElement $discussion
	 */
	protected function sendBlockingDiscussionMail(HyphaDomElement $discussion) {
		$title = $this->getTitle();
		$author = $this->O_O->getUser()->getAttribute('fullname');
		$linkToPage = $this->constructFullPath($this->pagename);

		$comment = $discussion->lastChild->textContent;
		$subject = __('art-block-submitted-subject', [
			'title' => $title
		]);
		$message = __('art-block-submitted-body', [
			'link' => htmlspecialchars($linkToPage),
			'title' => htmlspecialchars($title),
			'author' => htmlspecialchars($author),
			'comment' => htmlspecialchars($comment),
		]);

		$this->sendMail(getUserEmailList(), $subject, $message);
	}

	/**
	 * Sends "block has been resolved" mail to all users.
	 */
	protected function sendResolvedBlockingDiscussionMail() {
		$title = $this->getTitle();
		$author = $this->O_O->getUser()->getAttribute('fullname');
		$linkToPage = $this->constructFullPath($this->pagename);
		$subject = __('art-block-resolved-subject', [
			'title' => $title
		]);
		$message = __('art-block-resolved-body', [
			'link' => htmlspecialchars($linkToPage),
			'title' => htmlspecialchars($title),
			'author' => htmlspecialchars($author),
		]);
		$this->sendMail(getUserEmailList(), $subject, $message);
	}

	/**
	 * Sends a mail.
	 *
	 * @param string $receivers A comma separated list of receivers.
	 * @param string $subject
	 * @param string $message
	 */
	protected function sendMail($receivers, $subject, $message) {
		$style = 'body {margin-top:10px; margin-left: 10px; font-size:10pt; font-family: Sans; color:black; background-color:white;}';
		sendMail($receivers, hypha_getTitle() . ': '. $subject, $message, hypha_getEmail(), hypha_getTitle(), $style);
	}

	/**
	 * Gets the article title.
	 *
	 * @return string
	 */
	public function getTitle() {
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
	 * Gets the article excerpt.
	 *
	 * @return NodeList
	 */
	public function getExcerpt() {
		return $this->xml->find(self::FIELD_NAME_EXCERPT);
	}

	/**
	 * Gets the article status.
	 *
	 * @return string
	 */
	protected function getStatus() {
		/** @var HyphaDomElement $article */
		$article = $this->xml->find(self::FIELD_NAME_ARTICLE)->first();
		if ($article instanceof HyphaDomElement) {
			$status = $article->getAttribute(self::FIELD_NAME_STATUS);
			if ('' == $status) {
				$status = self::STATUS_NEWLY_CREATED;
			}
		} else {
			$status = self::STATUS_NEWLY_CREATED;
		}

		return $status;
	}

	/**
	 * Indication whether or not the article can be set as approved.
	 *
	 * @return bool
	 */
	protected function canBeSetAsApproved() {
		list($_checkStatus, $checks) = $this->runChecks();

		return $this->getStatus() === self::STATUS_REVIEW &&
		       $checks['has-approvals']['passed'] &&
		       $checks['has-no-blocks']['passed'];
	}

	/**
	 * Indication whether or not the given user has approved the article.
	 *
	 * @param string $userId
	 * @return bool
	 */
	protected function hasUserApproved($userId) {
		/** @var HyphaDomElement $approves */
		$approves = $this->xml->find(self::FIELD_NAME_APPROVE_CONTAINER);
		/** @var NodeList $approveCollection */
		$xpath = './/' . self::FIELD_NAME_APPROVE . '[@' . self::FIELD_NAME_USER . '="' . $userId . '"]';
		$approveCollection = $approves->findXPath($xpath);
		return $approveCollection->count() >= 1;
	}

	/**
	 * Indication whether or not the given user created the article.
	 *
	 * @param string $userId
	 * @return bool
	 */
	protected function hasUserCreated($userId) {
		$article = $this->xml->find(self::FIELD_NAME_ARTICLE);
		$createdBy = $article->getAttribute(self::FIELD_NAME_CREATED_BY);
		return $createdBy === $userId;
	}

	/**
	 * Gets the commenter name for the given comment.
	 *
	 * @param HyphaDomElement $comment
	 * @return string
	 */
	protected function getCommentCommenter(HyphaDomElement $comment) {
		$userId = $comment->getAttribute(self::FIELD_NAME_USER);
		if ($userId) {
			$user = hypha_getUserById($userId);
			return ($user instanceof HyphaDomElement) ? $user->getAttribute('fullname') : $userId;
		}
		return $comment->getAttribute(self::FIELD_NAME_DISCUSSION_COMMENTER_NAME);
	}

	/**
	 * @todo [LRM]: move so it can be used throughout Hypha
	 * @param string $label
	 * @param null|string $path
	 * @param null|string $command
	 * @param null|string|array $argument
	 * @return string
	 */
	protected function makeActionButton($label, $path = null, $command = null, $argument = null) {
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
	protected function constructFullPath($path, $language = null) {
		global $hyphaUrl;
		$language = null == $language ? $this->language : $language;
		$path = '' == $path ? '' : '/' . $path;

		return $hyphaUrl . $language . $path;
	}
}
