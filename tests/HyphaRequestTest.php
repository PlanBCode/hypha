<?php

include_once __DIR__ . '/../system/core/HyphaRequest.php';
include_once __DIR__ . '/../system/core/language.php';

class HyphaRequestTest {
	protected $counter = 0;
	protected $results = [];
	protected $testResultMaxLength = [
		'query' => 0,
		'test' => [],
		'testText' => [],
	];

	public function test(array $isoLangList) {
		$data = $this->getData();
		foreach ($data as $item) {
			$query = $item['query'];
			$request = new HyphaRequest($query, $isoLangList);
			$this->assertSame($request->getRequestQuery(false), $query, $query, 'query');
			$this->assertSame($request->isSystemPage(), $item['system'], $query, 'isSystem');
			$this->assertSame($request->getLanguage(), $item['lang'], $query, 'getLanguage');
			$this->assertSame($request->getPageName(), $item['name'], $query, 'getPageName');
			$this->assertSame($request->getArgs(), $item['args'], $query, 'getArgs');
			$this->assertSame($request->getRequestParts(), $item['parts'], $query, 'getRequestParts');
			$this->assertSame($request->getRequestParts(false), $item['partsInclude'], $query, 'getRequestParts(false)');
		}

		return $this;
	}

	private function getData() {
		return [
			[
				'query' => '',
				'system' => false,
				'lang' => null,
				'name' => null,
				'args' => [],
				'parts' => [],
				'partsInclude' => [],
			],
			[
				'query' => 'nl/festival',
				'system' => false,
				'lang' => 'nl',
				'name' => 'festival',
				'args' => [],
				'parts' => ['festival'],
				'partsInclude' => ['nl', 'festival'],
			],
			[
				'query' => 'en/festival',
				'system' => false,
				'lang' => 'en',
				'name' => 'festival',
				'args' => [],
				'parts' => ['festival'],
				'partsInclude' => ['en', 'festival'],
			],
			[
				'query' => 'en/festival/edit',
				'system' => false,
				'lang' => 'en',
				'name' => 'festival',
				'args' => ['edit'],
				'parts' => ['festival', 'edit'],
				'partsInclude' => ['en', 'festival', 'edit'],
			],
			[
				'query' => 'festival/festival',
				'system' => false,
				'lang' => null,
				'type' => 'festival',
				'name' => 'festival',
				'args' => [],
				'parts' => ['festival', 'festival'],
				'partsInclude' => ['festival', 'festival'],
			],
			[
				'query' => HyphaRequest::HYPHA_SYSTEM_PAGE_FILES,
				'system' => true,
				'lang' => null,
				'name' => null,
				'args' => [],
				'parts' => [HyphaRequest::HYPHA_SYSTEM_PAGE_FILES],
				'partsInclude' => [HyphaRequest::HYPHA_SYSTEM_PAGE_FILES],
			],
			[
				'query' => HyphaRequest::HYPHA_SYSTEM_PAGE_SETTINGS,
				'system' => true,
				'lang' => null,
				'name' => null,
				'args' => [],
				'parts' => [HyphaRequest::HYPHA_SYSTEM_PAGE_SETTINGS],
				'partsInclude' => [HyphaRequest::HYPHA_SYSTEM_PAGE_SETTINGS],
			],
			[
				'query' => 'mailing/' . HyphaRequest::HYPHA_SYSTEM_PAGE_SETTINGS,
				'system' => false,
				'lang' => null,
				'name' => 'settings',
				'args' => [],
				'parts' => ['mailing', HyphaRequest::HYPHA_SYSTEM_PAGE_SETTINGS],
				'partsInclude' => ['mailing', HyphaRequest::HYPHA_SYSTEM_PAGE_SETTINGS],
			],
			[
				'query' => HyphaRequest::HYPHA_SYSTEM_PAGE_FILES . '/' . HyphaRequest::HYPHA_SYSTEM_PAGE_FILES,
				'system' => true,
				'lang' => null,
				'name' => null,
				'args' => [HyphaRequest::HYPHA_SYSTEM_PAGE_FILES],
				'parts' => [HyphaRequest::HYPHA_SYSTEM_PAGE_FILES, HyphaRequest::HYPHA_SYSTEM_PAGE_FILES],
				'partsInclude' => [HyphaRequest::HYPHA_SYSTEM_PAGE_FILES, HyphaRequest::HYPHA_SYSTEM_PAGE_FILES],
			],
			[
				'query' => 'text/system',
				'system' => false,
				'lang' => null,
				'name' => 'system',
				'args' => [],
				'parts' => ['text', 'system'],
				'partsInclude' => ['text', 'system'],
			],
			[
				'query' => 'text/system/edit',
				'system' => false,
				'lang' => null,
				'name' => 'system',
				'args' => ['edit'],
				'parts' => ['text', 'system', 'edit'],
				'partsInclude' => ['text', 'system', 'edit'],
			],
			[
				'query' => 'en/index',
				'system' => false,
				'lang' => 'en',
				'name' => 'index',
				'args' => [],
				'parts' => ['index'],
				'partsInclude' => ['en', 'index'],
			],
		];
	}

	protected function assertSame($actual, $expected, $query, $test) {
		$result = $this->assert($actual === $expected, $query);
		$actualText = $actual;
		$expectedText = $expected;
		if (is_array($actualText)) {
			$actualText = json_encode($actualText);
		}
		if (is_array($expectedText)) {
			$expectedText = json_encode($expectedText);
		}
		$resultText = ($result ? '' : '! ' . $expectedText . '(' . gettype($expected) . ') -> ') . $actualText . ($result ? '' : '(' . gettype($actual) . ')');
		$this->results[$query][$test] = [$resultText, $actual, $expected, $result];

		if ($this->testResultMaxLength['test'][$test] < strlen($test)) {
			$this->testResultMaxLength['testText'][$test]  = $test;
			$this->testResultMaxLength['test'][$test] = strlen($test);
		}
		if ($this->testResultMaxLength['query'] < strlen($query)) {
			$this->testResultMaxLength['query'] = strlen($query);
		}
		if ($this->testResultMaxLength['test'][$test] < strlen($resultText)) {
			$this->testResultMaxLength['test'][$test] = strlen($resultText);
		}

		return $result;
	}

	protected function assert($assertion, $description) {
		$this->counter++;

		return assert($assertion, $description);
	}

	public function summary() {
		$delimiter = ' | ';
		$summary = str_pad('', $this->testResultMaxLength['query']);
		$headSeparation = str_pad('', $this->testResultMaxLength['query']+1, '-');
		foreach ($this->testResultMaxLength['testText'] as $test) {
			$summary .= $delimiter . str_pad($test, $this->testResultMaxLength['test'][$test]);
			$headSeparation .= '+' . str_pad('', $this->testResultMaxLength['test'][$test]+2, '-');
		}
		$summary .= $delimiter . 'success';
		$headSeparation .= '+' . str_pad('', strlen('success')+1, '-');

		$width = strlen($summary);
		$summary = str_pad('', $width, '=') . PHP_EOL . $summary;
		$summary .= PHP_EOL;
		$summary .= $headSeparation . PHP_EOL;

		foreach ($this->results as $query => $resultsPerQuery) {
			$summary .= str_pad($query, $this->testResultMaxLength['query']);
			$success = true;
			foreach ($resultsPerQuery as $test => $resultSet) {
				$summary .= $delimiter . str_pad($resultSet[0], $this->testResultMaxLength['test'][$test]);
				if ($resultSet[3] === false) {
					$success = false;
				}
			}
			$summary .= $delimiter . str_pad(($success ? 'true' : 'false'), strlen('success'));
			$summary .= PHP_EOL;
		}
		$summary .= str_pad('', $width, '-') . PHP_EOL;

		$summary .= PHP_EOL . 'asserted ' . $this->counter . ' tests' . PHP_EOL;

		return $summary;
	}
}

ob_start();
$test = (new HyphaRequestTest())->test($isoLangList);
ob_end_clean();
echo $test->summary();
