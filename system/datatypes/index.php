<?php
/*
	Class: indexpage
	Shows index page for various types of data.
*/
	class indexpage extends HyphaSystemPage {
		const PATH_IMAGES = 'images';
		const PATH_FILES = 'files';

		function __construct(RequestContext $O_O) {
			parent::__construct($O_O);
		}

		public function process(HyphaRequest $request) {
			$this->html->writeToElement('langList', hypha_indexLanguages('', $request->getArg(0)));
			switch ([$request->getView(), $request->getCommand()]) {
				case [self::PATH_IMAGES,        null]:   return $this->imageIndexView($request);
				case [self::PATH_FILES,         null]:   return $this->fileIndexView($request);
				default:                                 return $this->pageIndexView($request);
			};
		}
		public function imageIndexView(HyphaRequest $request) {
			$this->html->writeToElement('pagename', __('image-index'));
			$this->html->writeToElement('main', 'image index is not yet implemented');
		}

		public function fileIndexView(HyphaRequest $request) {
			$this->html->writeToElement('pagename', __('file-index'));
			$this->html->writeToElement('main', 'file index is not yet implemented');
		}

		public function pageIndexView(HyphaRequest $request) {
			$language = $request->getArg(0);
			if ($language === null)
				$language = $this->O_O->getContentLanguage();
			$isoLangList = Language::getIsoList();
			if (!array_key_exists($language, $isoLangList))
				return '404';
			$languageName = $isoLangList[$language];
			$languageName = substr($languageName, 0, strpos($languageName, ' ('));

			$this->html->linkScript('//cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js');
			$this->html->linkStyle('//cdn.datatables.net/1.10.22/css/jquery.dataTables.min.css');

			$html = <<<TABLE
	<table class="page_index">
		<thead>
			<tr>
				[[headerItems]]
			</tr>
		</thead>
		<tbody>
			[[tbody]]
		</tbody>
	</table>
TABLE;

			$tRowTpl = <<<ROW
			<tr>
				[[recordItems]]
			</tr>
ROW;
			$tRowItemTpl = <<<ROWITEM
				<td class="index-item [[name]] [[class]]" data-sort="[[sort]]">[[value]]</td>
ROWITEM;

			$dataTypes = hypha_getDataTypes();

			// get list of available pages and sort alphabetically
			$pageList = [];
			foreach(hypha_getPageList() as $index => $page) {
				$lang = hypha_pageGetLanguage($page, $language);
				$isPrivateOrUser = $this->O_O->isUser() || ($page->getAttribute('private') != 'on');
				$isInDataType = array_key_exists($page->getAttribute('type'), $dataTypes);
				if ($lang && $isPrivateOrUser && $isInDataType) {
					/** @var HyphaDatatypePage $hyphaPage */
					$hyphaPage = createPageInstance($this->O_O, $page);
					$titleSort = preg_replace("/[^A-Za-z0-9]/", '', $hyphaPage->getTitle());
					$titleSort .= '_'.$index;
					$pageList[$titleSort] = $hyphaPage;
				}
			}
			ksort($pageList);

			$columns = [];
			foreach ($dataTypes as $class => $name) {
				$cols = call_user_func($class . '::getIndexTableColumns');
				$columns += array_combine($cols, $cols);
			}

			$tbody = '';
			// iterate over page list
			foreach($pageList as $titleSort => $hyphaPage) {
				$recordItems = '';
				$items = $hyphaPage->getIndexData();
				foreach ($columns as $name) {
					if (array_key_exists($name, $items)) {
						$item = $items[$name];
					} else {
						$item = '';
					}
					if (!is_array($item)) {
						$item = ['value' => $item];
					}

					$vars = ['name' => $name];
					foreach (['value', 'sort', 'class'] as $key) {
						if (array_key_exists($key, $item)) {
							$vars[$key] = $item[$key];
						}
					}
					$recordItems .= hypha_substitute($tRowItemTpl, $vars);
				}

				$vars = [
				    'recordItems' => $recordItems,
                ];

				$tbody .= hypha_substitute($tRowTpl, $vars);
			}

			$headerItems = '';
			foreach ($columns as $column) {
				$headerItems .= '<th>'.$column.'</th>';
			}

			$vars = [
				'headerItems' => $headerItems,
				'tbody' => $tbody,
			];

			$html = hypha_substitute($html, $vars);

			// initialize datatable
			$js = <<<EOD
	$(document).ready(function () {
		$('.page_index').DataTable({
			order: [[ [[sortIndex]], "desc" ]],
			paging: false,
		});
	});
EOD;

			$dateIndex = array_search(__(HyphaDatatypePage::INDEX_TABLE_COLUMNS_DATE), array_values($columns));
			$vars = [
				'sortIndex' => $dateIndex,
			];
			$js = hypha_substitute($js, $vars);
			$this->html->writeScript($js);

			$this->html->writeToElement('pagename', __('page-index').': '.$languageName);
			$this->html->writeToElement('main', $html);
		}
	}
