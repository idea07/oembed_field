<?php

	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	/**
	 *
	 * Abstract class that represents a service that offers oEmbed API
	 * @author Nicolas
	 *
	 */
	abstract class ServiceDriver {

		protected $Name = null;

		protected $Domains = null;

		/**
		 *
		 * Basic constructor that takes the name of the service and its Url as parameters
		 * @param string $name
		 * @param string|array $domains
		 */
		protected function __construct($name, $domains) {
			$this->Name = $name;
			$this->Domains = $domains;
		}

		/**
		 *
		 * Accessor for the Name property
		 * @return string
		 */
		public final function getName() {
			return $this->Name;
		}

		/**
		 *
		 * Accessor for the Domain property
		 * @deprecated  @see <code>getDomains</code>
		 */
		public final function getDomain() {
			return $this->Domains;
		}

		/**
		 *
		 * Accessor for the unified Domains property
		 * This will alway return an array, even if the domain was set as a string
		 * Fix issue #19
		 * @return Array
		 */
		public final function getDomains() {
			if (!is_array($this->Domains)) {
				return array($this->Domains);
			}
			return $this->Domains;
		}

		/**
		 *
		 * Methods used to check if this drivers corresponds to the
		 * data passed in parameter. Overrides at will
		 * @param data $url
		 * @return boolean
		 */
		public function isMatch($url) {
			$doms = $this->getDomains();
			foreach ($doms as $d) {
				if (strpos($url, $d) > -1) {
					return true;
				}
			}
			return false;
		}

		/**
		 *
		 * Gets the oEmbed XML data from the Driver Source
		 *
		 * @param array $data
		 * @param bool $errorFlag - ref parameter to flag if the operation was successful (new in 1.3)
		 */
		public final function getXmlDataFromSource($data, &$errorFlag) {

			// assure we have no error
			$errorFlag = false;

			// get the complete url
			$url = $this->getOEmbedXmlApiUrl($data);


			$xml = array();


			// add url to array
			$xml['url'] = $url;

			// trying to load XML into DOM Document
			$doc = new DOMDocument();
			$doc->preserveWhiteSpace = false;
			$doc->formatOutput = false;

			// ignore errors, but save if it was successful
			$errorFlag = !(@$doc->load($url));

			if (!$errorFlag) {
				$xml['xml'] = $doc->saveXML();

				// add id to array
				$idTagName = $this->getIdTagName();
				if ($idTagName == null) {
					$xml['id'] = Lang::createHandle($url);
				} else {
					$xml['id'] = $doc->getElementsByTagName($idTagName)->item(0)->nodeValue;
				}

				$xml['title'] = $doc->getElementsByTagName($this->getTitleTagName())->item(0)->nodeValue;
				$xml['thumb'] = $doc->getElementsByTagName($this->getThumbnailTagName())->item(0)->nodeValue;

			}
			else {
				// return somthing since the column can't be null
				$xml['xml'] = '<error>' . __('Symphony could not load XML from oEmbed remote service') . '</error>';
			}

			return $xml;
		}

		/**
		 *
		 * Enter description here ...
		 * Issue #15
		 */
		public function formatDataFromSource() {

		}

		/**
		 *
		 * Overridable method that shall return the HTML code for embedding
		 * this resource into the backend
		 * @param array $data
		 * @param array $options
		 */
		public function getEmbedCode($data, $options) {
			// ref to the html string to output in the backend
			$player = null;
			// xml string from the DB
			$xml_data = $data['oembed_xml'];

			if(empty($xml_data)) return false;

			// create a new DOMDocument to manipulate the XML string
			$xml = new DOMDocument();

			// if we can load the string into the document
			if (@$xml->loadXML($xml_data)) {
				// get the value of the html node
				// NOTE: this could be the XML children if the html is not encoded
				$player = $xml->getElementsByTagName('html')->item(0)->nodeValue;

				// if the field is in the side bar
				if ($options['location'] == 'sidebar') {
					// replace height and width to make it fit in the backend
					$w = $this->getEmbedSize($options, 'width');
					$h = $this->getEmbedSize($options, 'height');

					// actual replacement
					$player = preg_replace(
						array('/width="([^"]*)"/', '/height="([^"]*)"/'),
						array("width=\"{$w}\"", "height=\"{$h}\""),
						$player
					);
				}

				return $player;
			}

			return false;
		}

		/**
		 *
		 * Abstract method that shall return the URL for the oEmbed XML API
		 * @param $params
		 */
		public abstract function getOEmbedApiUrl($params);

		/**
		 *
		 * Basic about method that returns an array for the credits of the driver
		 */
		public abstract function about();


		/**
		 *
		 * Method that returns the format used in oEmbed API responses
		 * @return string (xml|json)
		 */
		public function getRootTagName() {
			return 'xml'; // xml || json
		}

		/**
		 *
		 * Method that returns the name of the root tag.
		 * Overrides at will. Default returns 'oembed'
		 * @return string
		 */
		public function getRootTagName() {
			return 'oembed';
		}

		/**
		 *
		 * Method that returns the name of the Thumbnail_url tag.
		 * Overrides at will. Default returns 'title'
		 * @return string
		 */
		protected function getThumbnailTagName() {
			return 'thumbnail_url';
		}

		/**
		 *
		 * Method that returns the name of the Title tag.
		 * Overrides at will. Default returns 'title'
		 * @return string
		 */
		protected function getTitleTagName() {
			return 'title';
		}


		/**
		 *
		 * Overridable method that shall return the name of the tag
		 * that will be used as ID. Default returns null
		 */
		protected function getIdTagName() {
			return null; // will use url as id
		}

		/**
		 *
		 * This method will be called when adding sites
		 * to the authorized JIT image manipulations external urls.
		 *
		 * It should return url as value
		 * i.e. array('http://*.example.org/*', 'http://*.example.org/images/*')
		 *
		 * @return array|null
		 */
		public function getNeededUrlsToJITimages() {
			return null;
		}

		/**
		 *
		 * Utility method that returns the good size based on the location of the field
		 * @param array $options
		 * @param string $size (width and/or height)
		 * @return array
		 */
		protected function getEmbedSize($options, $size) {
			if (!isset($options['location']) || !isset($options[$size . '_side']) || $options['location'] == 'main' ) {
				return $options[$size];
			}
			return $options[$size. '_side'];
		}

	}