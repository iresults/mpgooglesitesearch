<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Mathias Petermann <mathias.petermann@gmail.com>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 *
 *
 * @package mpgooglesitesearch
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 *
 */
class Tx_Mpgooglesitesearch_Utility_ResultParser
{
    /**
     * The search result XML that gets returned by google
     *
     * @var DOMDocument of search results
     */
    protected $xml;

    /**
     * Fetches the xml from Google via file_get_contents or curl
     * and loads it into $xml
     *
     * @param string $query          the query to search for
     * @param int    $start          the result number to search from
     * @param int    $resultsPerPage the number of results we want from Google
     * @param string $cseNumber      the Google Site Search ID
     * @param string $language       the language the search in
     * @param string $countryCode    the country for which we want Google to prioritize the results
     *
     * @throws Exception
     *
     * @return void
     */
    public function fetchXml($query, $start, $resultsPerPage, $cseNumber, $language, $countryCode)
    {
        $url = 'http://www.google.com/search?client=google-csbe&output=xml_no_dtd'.
            '&cr=country'.$countryCode.
            '&lr=lang_'.$language.
            '&cx='.$cseNumber.
            '&start='.$start.
            '&num='.$resultsPerPage.
            '&q='.urlencode($query);

        /** @var \TYPO3\CMS\Core\Http\HttpRequest $request */
        $request = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            'TYPO3\\CMS\\Core\\Http\\HttpRequest',
            $url
        );
        try {
            $result = $request->send();
        } catch (Exception $exception) {
            throw new Exception('Error while fetching the XML, please check your configuration', 1452854243, $exception);
        }

        $searchResultString = $result->getBody();
        if ($result->getStatus() && !empty($searchResultString)) {
            $this->xml = new DOMDocument();
            $this->xml->loadXML($searchResultString);
        } else {
            throw new Exception('Error while fetching the XML, please check your configuration');
        }
    }

    /**
     * Parse the xml for search results
     *
     * @throws Exception
     *
     * @return array
     */
    public function getSearchResultArray()
    {

        if (empty($this->xml) || !($this->xml instanceof DomDocument)) {
            throw new Exception('No XML Loaded');
        }

        $results = $this->xml->getElementsByTagName('R');

        $resultArray = Array();
        foreach ($results as $result) {
            /** @var $resultObject Tx_Mpgooglesitesearch_Domain_Model_Result */
            $resultObject = t3lib_div::makeInstance('Tx_Mpgooglesitesearch_Domain_Model_Result');

            // Get basic properties
            $resultObject->setTitle($result->getElementsByTagName('T')->item(0)->nodeValue);
            $resultObject->setUrl($result->getElementsByTagName('U')->item(0)->nodeValue);
            $resultObject->setContent($result->getElementsByTagName('S')->item(0)->nodeValue);

            $pageMap = $result->getElementsByTagName('PageMap')->item(0);

            // ToDo: Can't this be writter nicer?
            if (is_object($pageMap)) {
                foreach ($pageMap->getElementsByTagName('DataObject') as $dataObj) {
                    if ($dataObj->getAttribute('type') == 'metatags') {
                        // Get LastModified (every result got this)
                        foreach ($dataObj->getElementsByTagName('Attribute') as $attr) {
                            if ($attr->getAttribute('name') == 'lastmodified') {
                                $resultObject->setLastModified($attr->getAttribute('value'));
                            }
                        }
                    } elseif ($dataObj->getAttribute('type') == 'cse_image') {
                        // If there is an image, get the url
                        foreach ($dataObj->getElementsByTagName('Attribute') as $attr) {
                            if ($attr->getAttribute('name') == 'src') {
                                $resultObject->setImage($attr->getAttribute('value'));
                            }
                        }
                    } elseif ($dataObj->getAttribute('type') == 'cse_thumbnail') {
                        // If there is a thumbnail, get the url and the dimensions
                        $thumbnailArray = Array();
                        foreach ($dataObj->getElementsByTagName('Attribute') as $attr) {
                            $thumbnailArray[$attr->getAttribute('name')] = $attr->getAttribute('value');
                        }
                        $resultObject->setThumbnail($thumbnailArray);
                    }

                }
            }

            if ($result->hasAttribute('MIME')) {
                $resultObject->setMime($result->getAttribute('MIME'));
            }
            $cNode = $result->getElementsByTagName('HAS')->item(0)->getElementsByTagName('C')->item(0);
            if (is_object($cNode)) {
                $pageSize = $cNode->getAttribute('SZ');
                $resultObject->setPageSize($pageSize);
            }


            $resultArray[] = $resultObject;
        }

        return $resultArray;
    }

    /**
     * Read the general information from the xml
     *
     * @throws Exception
     *
     * @return array
     */
    public function getGeneralInformation()
    {

        if (empty($this->xml) || !($this->xml instanceof DomDocument)) {
            throw new Exception('No XML Loaded');
        }
        $general = Array();

        $general['numberOfResults'] = $this->xml->getElementsByTagName('M')->item(0)->nodeValue;

        if (is_object($this->xml->getElementsByTagName('RES')->item(0))) {
            if ($this->xml->getElementsByTagName('RES')->item(0)->hasAttribute('SN')) {
                $general['start'] = $this->xml->getElementsByTagName('RES')->item(0)->getAttribute('SN');
            }

            if ($this->xml->getElementsByTagName('RES')->item(0)->hasAttribute('EN')) {
                $general['end'] = $this->xml->getElementsByTagName('RES')->item(0)->getAttribute('EN');
            }
        }

        return $general;
    }
}
