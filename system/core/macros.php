<?php

/**
 * Superclass for all macros.
 */
abstract class HyphaMacro {
	/**
	 * The RequestContext this macro is evaluated in.
	 * @var RequestContext
	 */
	protected $O_O;

	/**
	 * The <macro> tag this object represents and should replace.
	 *
	 * @var HyphaDomElement
	 */
	protected $macro_tag;

	/**
	 * The document that contains $macro_tag.
	 *
	 * @var HTMLDocument
	 */
	protected $doc;

	/**
	 * The context this macro is evaluated in.
	 *
	 * @var array
	 */
	protected $macro_context;

	public function __construct(RequestContext $O_O, HyphaDomElement $macro_tag, array $macro_context) {
		$this->O_O = $O_O;
		$this->macro_tag = $macro_tag;
		$this->doc = $macro_tag->document();
		$this->macro_context = $macro_context;
	}

	/**
	 * Invoke this macro.
	 *
	 * This method should either return a HyphaDomElement or a
	 * NodeList, which will then replace for the macro
	 * element.
	 *
	 * Alternatively, if more complex replacement is needed, this
	 * method can also return false, in which case the macro element
	 * is simply deleted (assuming this method has then already
	 * inserted new content elsewhere).
	 */
	abstract public function invoke();

	/**
	 * Helper that copies common attributes from the macro_tag to
	 * the given element.
	 *
	 * Currently copies `id` and `class`, more could be added in the
	 * future. The `class` attribute is merged, other attributes are
	 * replaced.
	 */
	protected function copyAttributesTo(HyphaDomElement $to) {
		$from = $this->macro_tag;
		if ($from->hasAttribute('id'))
			$to->setAttribute('id', $from->getAttribute('id'));
		if ($from->hasAttribute('class'))
			$to->addClass($from->getAttribute('class'));
	}
}

/**
 * Handles loading, registration and substitution of macros.
 *
 * Macros are identified by their name and stored in
 * system/macros/name.php. These PHP files should define a single
 * subclass of HyphaMacro and then return the classe (e.g. `return
 * MyMacro::class` at the end of the php file).
 */
class HyphaMacros {
	static private $macro_classes = [];

	/**
	 * Returns a macro object for the given name.
	 */
	static public function getMacro($name, RequestContext $O_O, HyphaDomElement $macro_tag, array $macro_context) {
		if (!preg_match("/^[a-zA-Z0-9_.-]+$/", $name))
			throw new UnexpectedValueException("Invalid macro name: $name");
		if (!array_key_exists($name, self::$macro_classes)) {
			$filename = 'system/macros/' . $name . '.php';
			if (!file_exists($filename))
				throw new UnexpectedValueException("Unknown macro: $name");
			$res = include($filename);
			if ($res === false)
				throw new UnexpectedValueException("Failed to include macro: $filename");
			if ($res === 1)
				throw new UnexpectedValueException("Macro $filename did not return anything");
			if (!is_string($res) || !class_exists($res))
				throw new UnexpectedValueException("Macro $filename did not return classname: " . var_export($res, true));
			if (!in_array(HyphaMacro::class, class_parents($res)))
				throw new UnexpectedValueException("Macro $filename returned class not subclass of HyphaMacro: $res");
			// All good, store the result
			self::$macro_classes[$name] = $res;

		}
		return new self::$macro_classes[$name]($O_O, $macro_tag, $macro_context);
	}

	static public function processMacro(HyphaDomElement $macro_tag, array $macro_context) {
		global $O_O;

		$name = $macro_tag->getAttribute('name');
		$macro = self::getMacro($name, $O_O, $macro_tag, $macro_context);
		$res = $macro->invoke();

		if ($res !== false && !($res instanceof HyphaDomElement) && !($res instanceof DOMWrap\NodeList))
			throw new UnexpectedValueException("Macro $name should return false, dom element or node list but returned: " . var_export($res, true));
		return $res;
	}

	static public function processAllMacros(HyphaDomElement $element, array $macro_context) {
		global $O_O;
		// XXX: Should this use hypha:macro? That means this
		// would be a hypha XML namespace, which requires
		// additional setup for DomDocument and Xpath to handle
		// this correctly.
		// https://stackoverflow.com/questions/4817112/xpath-query-for-xml-node-with-colon-in-node-name
		// https://stackoverflow.com/questions/4282147/use-xpath-to-parse-element-name-containing-a-colon?lq=1
		// Do not replace inside textarea or editor tags, since
		// there you typically want to edit the original tag.
		$xpath = ".//macro[not(ancestor::editor or ancestor::textarea)]";
		foreach ($element->findXPath($xpath) as $macro) {
			try {
				$replacement = self::processMacro($macro, $macro_context);
			} catch (Exception $e) {
				$xml = $macro->document()->saveXml($macro);
				$msg = $e->getMessage();
				trigger_error("Failed to invoke macro $xml: $msg", E_USER_WARNING);
				$replacement = $element->document()->create('<span class="macro-error"/>');
				if ($O_O->isUser())
					$replacement->text("Macro failed: $msg");
				else
					$replacement->text("Macro failed");
			}
			if ($replacement)
				$macro->replaceWith($replacement);
			else
				$macro->remove();
		}
	}

	/**
	 * Post-processing function called at the end of html rendering
	 * that processes all remaining macros with an empty context.
	 */
	static function postProcessMacros(HTMLDocument $html) {
		self::processAllMacros($html->documentElement, []);
	}
}

registerPostProcessingFunction('HyphaMacros::postProcessMacros');
