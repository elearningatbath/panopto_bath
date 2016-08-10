<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The auth soap client for panopto
 *
 * @package block_panopto
 * @copyright Panopto 2009 - 2016 with contributions from Spenser Jones (sjones@ambrose.edu),
 * Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This can't be defined moodle internal because it is called from panopto to authorize login.

/**
 * The auth soap client for panopto
 *
 * @copyright Panopto 2009 - 2016 with contributions from Spenser Jones (sjones@ambrose.edu),
 * Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class panopto_auth_soap_client extends SoapClient {

    /**
     * The Moodle http options for curl to use as proxy settings etc.
     */
    private static $curloptions;
    public $timeout = 60;
    private $getVersionAction = "http://tempuri.org/IAuth/GetServerVersion";
    
    public function __construct($servername)
    {
        global $CFG;
        // Instantiate SoapClient in WSDL mode.
        // Set call timeout to 5 minutes.
        parent::__construct
        (
            'https://'. $servername . '/Panopto/PublicAPI/4.0/Auth.svc?wsdl'
        );
        // Use Moodle http proxy settings.
        // todo does not consider proxybypass setting.
        if (empty(self::$curloptions)) {
            self::$curloptions = array(
                CURLOPT_PROXY => $CFG->proxyhost,
                CURLOPT_PROXYPORT => $CFG->proxyport,
                CURLOPT_PROXYTYPE => (($CFG->proxytype === 'HTTP') ? CURLPROXY_HTTP : CURLPROXY_SOCKS5),
                CURLOPT_PROXYUSERPWD => ((empty($CFG->proxypassword)) ? $CFG->proxyuser : "{$CFG->proxyuser}:{$CFG->proxypassword}"),
            );
        }
    }

    /**
    * Override SOAP action to work around bug in older PHP SOAP versions.
    */
//Overrides parent __doRequest function to make SOAP calls with custom timeout
    public function __doRequest($request, $location, $action, $version, $one_way = FALSE)
    {
        global $CFG;
        //Attempt to intitialize cURL session to make SOAP calls.
        $curl = curl_init($location);

        //Check cURL was initialized
        if ($curl !== false)
        {
            //Set standard cURL options
            $options = array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $request,
                CURLOPT_NOSIGNAL => true,
                CURLOPT_HTTPHEADER => array(sprintf('Content-Type: %s', $version == 2 ? 'application/soap+xml' : 'text/xml'), sprintf('SOAPAction: %s', $action)),
                CURLOPT_SSL_VERIFYPEER => true, //All of our SOAP calls must be made via ssl
                CURLOPT_TIMEOUT => $this->timeout //Set call timeout in seconds
            );
            //Add curl options
            $options[CURLOPT_PROXY] = $CFG->proxyhost;
            $options[CURLOPT_PROXYPORT] = $CFG->proxyport;
            $options[CURLOPT_PROXYTYPE] = (($CFG->proxytype === 'HTTP') ? CURLPROXY_HTTP : CURLPROXY_SOCKS5);
            $option[CURLOPT_PROXYUSERPWD] = ((empty($CFG->proxypassword)) ? $CFG->proxyuser : "{$CFG->proxyuser}:{$CFG->proxypassword}");
            //$options = array_merge(self::$curloptions, $options); // Add proxy options to curl request.
            //Attempt to set the options for the cURL call
            if (curl_setopt_array($curl, $options) !== false)
            {
                //Make call using cURL (including timeout settings)
                $response = curl_exec($curl);
                //If cURL throws an error, log it
                if (curl_errno($curl) !== 0)
                {
                    error_log(curl_error($curl));
                }
            }
            else
            {
                //A cURL option could not be set.
                throw new Exception('Failed setting cURL options.');
            }
        }
        else
        {
            //cURL was not initialized properly.
            throw new Exception("Couldn't initialize cURL to make SOAP calls");
        }

        //Close cURL session.
        curl_close($curl);

        //Return the SOAP response
        return $response;
    }

    private function get_server_version()
    {
        return parent::__soapCall("GetServerVersion", array());
    }

    /**
     * Returns the version number of the current Panopto server.
     */
    public function get_panopto_server_version() {
        $panoptoversion;

        $serverversionresult = $this->get_server_version();

        if (!empty($serverversionresult)) {
            if (!empty($serverversionresult->{'GetServerVersionResult'})) {
                $panoptoversion = $serverversionresult->{'GetServerVersionResult'};
            }
        }
        return $panoptoversion;
    }
}

/* End of file panopto_auth_soap_client.php */
