<?php

/**
 * -------------------------------------------------------------------------
 * Derived from Metabase plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is based on Metabase.
 *
 * Metabase is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Metabase is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Metabase. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2018-2023 by Metabase plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/metabase
 * -------------------------------------------------------------------------
 * Modified by Grafana plugin team
 * @copyright Copyright (C) 2025 by Grafana plugin team.
 * @link      https://github.com/Open-Sa/grafana
 * -------------------------------------------------------------------------
 * Changes:
 * - Adaptations to grafana API
 */

namespace GlpiPlugin\Grafana;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Client as Guzzle_Client;
use CommonGLPI;
use GLPIKey;
use GlpiPlugin\Grafana\Config;
use Session;
use Toolbox;

class APIClient extends CommonGLPI
{
    private $api_config = [];
    private $last_error = [];

    public function __construct()
    {
        // retrieve plugin config
        $this->api_config = Config::getConfig();
    }

    /**
     * Check with grafana API the mandatory actions
     *
     * @return array of [label -> boolean]
     */
    public function status()
    {
        return [
            __('API: login', 'grafana')
            => $this->connect(),
        ];
    }

    /**
     * Attempt an http connection on grafana api
     * if suceed, set auth_token private properties
     *
     * @return bool
     */
    public function connect()
    {
        // send connect with http query
        $data = $this->httpQuery('search', [], 'GET');

        return (is_array($data) && count($data) > 0);
    }

    public function getFolders()
    {
        $data = $this->httpQuery('search?type=dash-folder');

        return $data;
    }

    public function getDashboard($dashboard_uid)
    {
        return $this->httpQuery("search?type=dash-db&dashboardUIDs=" . urlencode($dashboard_uid));
    }

    public function getDashboards($folder_uid = '')
    {
        if ($folder_uid !== '') {
            $data = $this->httpQuery('search?type=dash-db&folderUIDs=' . urlencode($folder_uid));
        } else {
            $data = $this->httpQuery('search?type=dash-db');
        }
        return $data;
    }


    /**
     * Return the grafana API base uri constructed from config
     *
     * @return string the uri
     */
    public function getAPIBaseUri()
    {
        $url = trim($this->api_config['url'], '/');
        $url .= '/api/';

        return $url;
    }

    /**
     * Send an http query to the grafana api
     *
     * @param  string $resource the endpoint to use
     * @param  array  $params   an array containg these possible options:
     *                             - _with_metadata (bool, default false)
     *                             - allow_redirects (bool, default false)
     *                             - timeout (int, default 5)
     *                             - connect_timeout (int, default 2)
     *                             - debug (bool, default false)
     *                             - verify (bool, default based on plugin config), check ssl certificate
     *                             - query (array) url parameters
     *                             - body (string) raw data to send in body
     *                             - json (array) array to pass into the body chich will be json_encoded
     *                             - json (headers) http headers
     * @param  string $method   Http verb (ex: GET, POST, etc)
     * @return array|false  data returned by the api
     */
    public function httpQuery($resource = '', $params = [], $method = 'GET')
    {
        // declare default params
        $default_params = [
            '_with_metadata'  => false,
            'allow_redirects' => false,
            'timeout'         => 5,
            'connect_timeout' => 2,
            'debug'           => false,
            'verify'          => true,
            'query'           => [], // url parameter
            'body'            => '', // raw data to send in body
            'json'            => [], // json data to send
            'headers'         => [
                'content-type' => 'application/json',
                'Accept'                         => 'application/json',
            ],
        ];

        $user_pass_string = $this->api_config['username'] . ':' . (new GLPIKey())->decrypt($this->api_config['password']);
        $base64_token = base64_encode($user_pass_string);

        $default_params['headers']['Authorization'] =  "Basic " . $base64_token;
        // merge default params
        $params = array_replace_recursive($default_params, $params);
        //remove empty values
        $params = plugin_grafana_recursive_remove_empty($params);

        // init guzzle
        $http_client = new Guzzle_Client(['base_uri' => $this->getAPIBaseUri()]);

        // send http request
        try {
            $response = $http_client->request(
                $method,
                $resource,
                $params,
            );
        } catch (GuzzleException $e) {
            $safe_params = $params;
            unset($safe_params['headers']['Authorization']);
            $this->last_error = [
                'title'     => 'Grafana API error',
                'exception' => $e->getMessage(),
                'params'    => $safe_params,
            ];

            if ($e instanceof RequestException) {
                $this->last_error['request'] = Message::toString($e->getRequest());

                if ($e->hasResponse()) {
                    $response                     = $e->getResponse();
                    $this->last_error['response'] = Message::toString($response);

                    // session with grafana ko, unset our token
                    if ($response->getStatusCode() == 401) {
                        unset($_SESSION['grafana']['session_token']);
                    }
                }
            }

            if ($e instanceof ConnectException) {
                Session::addMessageAfterRedirect(
                    __('Query to grafana failed because operation timed out. Maybe you should increase the timeout value in plugin configuration', 'grafana'),
                    true,
                    ERROR,
                );
            }

            if (($_SESSION['glpi_use_mode'] ?? null) == Session::DEBUG_MODE) {
                Toolbox::backtrace();
                Toolbox::logDebug($this->last_error);
            }

            return false;
        }

        // parse http response
        $http_code = $response->getStatusCode();
        $headers   = $response->getHeaders();

        // check http errors
        if (intval($http_code) >= 400) {
            $this->last_error = [
                'title'     => 'Grafana API error',
                'exception' => 'HTTP ' . $http_code . ' — ' . $response->getReasonPhrase(),
                'response'  => substr((string) $response->getBody(), 0, 500),
            ];
            return false;
        }

        // cast body as string, guzzle return streams
        $json = (string) $response->getBody();
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->last_error = [
                'title'     => 'Grafana API error',
                'exception' => 'Response is not valid JSON — possible proxy or authentication page intercepting the request',
                'response'  => substr($json, 0, 500),
            ];
            return false;
        }

        //append metadata
        if ($params['_with_metadata']) {
            $data['_headers']   = $headers;
            $data['_http_code'] = $http_code;
        }

        return $data;
    }

    /**
     * Return the error encountered with an http query
     *
     * @return array the error
     */
    public function getLastError()
    {
        return $this->last_error;
    }
}
