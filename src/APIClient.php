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
use DBConnection;
use CommonITILObject;

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
        /* if (isset($_SESSION['grafana']['session_token'])) {
            return true;
        }*/

        // send connect with http query
        $data = $this->httpQuery('search', [], 'GET');

        /*if (is_array($data)) {
        if (isset($data['id'])) {
           $_SESSION['grafana']['session_token'] = $data['id'];
        }
     }*/

        return ($data !== false && count($data) > 0);
    }

    public function checkSession()
    {
        // do a simple query
        $this->getCurrentUser(true);

        // check session token, if set, we still have a valid token
        if (isset($_SESSION['grafana']['session_token'])) {
            return true;
        }

        // so reconnect
        $this->connect();

        // check again session token, if set, we now have a valid token
        if (isset($_SESSION['grafana']['session_token'])) {
            return true;
        }

        return false;
    }

    public function getCurrentUser($skip_session_check = false)
    {
        if (
            !$skip_session_check
            && !$this->checkSession()
        ) {
            return false;
        }

        $data = $this->httpQuery('user/current');

        return $data;
    }

    public function getUsers()
    {
        if (!$this->checkSession()) {
            return false;
        }

        $data = $this->httpQuery('user');

        return $data;
    }

    public function getDatabases()
    {
        if (!$this->checkSession()) {
            return false;
        }

        $data = $this->httpQuery('database');

        return $data;
    }

    public function getDatabase($db_id = 0)
    {
        if (!$this->checkSession()) {
            return false;
        }

        $data = $this->httpQuery("database/$db_id");

        return $data;
    }

    public function getGlpiDatabase()
    {
        // we already have stored the id of glpi database
        if (($db_id = $this->api_config['glpi_db_id']) != 0) {
            return $this->getDatabase($db_id);
        }

        if (($databases = $this->getDatabases()) === false) {
            return false;
        }

        foreach ($databases['data'] as $database) {
            if ($database['name'] == 'GLPI (plugin auto-generated)') {
                return $database;
            }
        }

        $this->last_error[] = __('No auto-generated GLPI database found', 'grafana');

        return false;
    }

    public function createGlpiDatabase()
    {
        /** @var \DBmysql $DB */
        global $DB;

        if (($data = $this->getGlpiDatabase()) === false) {
            // try to switch to slave db
            DBConnection::switchToSlave();

            // post conf for the glpi database
            $data = $this->httpQuery('database', [
                'timeout' => $this->api_config['timeout'],
                'json'    => [
                    'name'         => 'GLPI (plugin auto-generated)',
                    'engine'       => 'mysql',
                    'is_full_sync' => true,
                    'details'      => [
                        'host'        => $DB->dbhost,
                        'port'        => 3306,
                        'dbname'      => $DB->dbdefault,
                        'user'        => $DB->dbuser,
                        'password'    => $DB->dbpassword,
                        'tunnel-port' => 22,
                    ],
                ],
            ], 'POST');

            // switch back to master
            DBConnection::switchToMaster();
        }

        return $data;
    }

    public function getDatabaseMetadata($db_id = 0)
    {
        if (!$this->checkSession()) {
            return false;
        }

        $data = $this->httpQuery("database/$db_id/metadata", [
            'timeout' => $this->api_config['timeout'],
        ]);

        return $data;
    }

    public function createForeignKey($f_id_src = 0, $f_id_trgt = 0)
    {
        if (!$this->checkSession()) {
            return false;
        }

        $data = $this->httpQuery("/api/field/$f_id_src", [
            'json' => [
                'special_type'       => 'type/FK',
                'fk_target_field_id' => $f_id_trgt,
            ],
        ], 'PUT');

        return $data;
    }

    public function setItiObjectHardcodedMapping()
    {
        if (!isset($_SESSION['grafana']['fields'])) {
            return false;
        }

        $ticket  = new \Ticket();
        $problem = new \Problem();
        $change  = new \Change();

        return $this->setTicketTypeMapping()
            && $this->setITILStatusMapping($ticket)
            && $this->setITILMatrixMapping($ticket)
            && $this->setITILStatusMapping($problem)
            && $this->setITILMatrixMapping($problem)
            && $this->setITILStatusMapping($change)
            && $this->setITILMatrixMapping($change);
    }

    public function setTicketTypeMapping()
    {
        $field_id = $_SESSION['grafana']['fields']['glpi_tickets.type'];
        $this->setFieldCustomMapping($field_id, __('Type'));
        $data = $this->httpQuery("/api/field/$field_id/values", [
            'json' => [
                'values' => [
                    [\Ticket::INCIDENT_TYPE, __('Incident')],
                    [\Ticket::DEMAND_TYPE, __('Request')],
                ],
            ],
        ], 'POST');

        return isset($data['status']) && $data['status'] === 'success';
    }

    public function setITILStatusMapping(CommonITILObject $item)
    {
        $statuses        = $item::getAllStatusArray();
        $statuses_topush = [];
        foreach ($statuses as $key => $label) {
            $statuses_topush[] = [$key, $label];
        }
        $table    = $item::getTable();
        $field_id = $_SESSION['grafana']['fields']["$table.status"];
        $this->setFieldCustomMapping($field_id, __('Status'));
        $data = $this->httpQuery("/api/field/$field_id/values", [
            'json' => [
                'values' => $statuses_topush,
            ],
        ], 'POST');

        return isset($data['status']) && $data['status'] === 'success';
    }

    public function setITILMatrixMapping(CommonITILObject $item)
    {
        $table = $item::getTable();
        foreach (['urgency', 'impact', 'priority'] as $matrix_field) {
            $field_id = $_SESSION['grafana']['fields']["$table.$matrix_field"];
            $this->setFieldCustomMapping($field_id, __(mb_convert_case($matrix_field, MB_CASE_TITLE)));
            $data_topush = [
                [5, _x($matrix_field, 'Very high')],
                [4, _x($matrix_field, 'High')],
                [3, _x($matrix_field, 'Medium')],
                [2, _x($matrix_field, 'Low')],
                [1, _x($matrix_field, 'Very low')],
            ];
            if ($matrix_field === 'priority') {
                array_unshift($data_topush, [6, _x($matrix_field, 'Major')]);
            }
            $data = $this->httpQuery("/api/field/$field_id/values", [
                'json' => [
                    'values' => $data_topush,
                ],
            ], 'POST');

            if (
                !isset($data['status'])
                || $data['status'] !== 'success'
            ) {
                return false;
            }
        }

        return true;
    }

    public function setFieldCustomMapping($field_id, $label = '')
    {
        $data = $this->httpQuery("/api/field/$field_id", [
            'json' => [
                'special_type'     => 'type/Category',
                'has_field_values' => 'list',
            ],
        ], 'PUT');

        $data = $this->httpQuery("/api/field/$field_id/dimension", [
            'json' => [
                'human_readable_field_id' => null,
                'type'                    => 'internal',
                'name'                    => $label,
            ],
        ], 'POST');
    }

    public function getFolders()
    {
        /*         if (!$this->checkSession()) {
            return false;
        }
 */
        $data = $this->httpQuery('search?type=dash-folder');

        return $data;
    }

    public function getDashboard($dashboard_uid)
    {
        /*if (!$this->checkSession()) {
            return false;
        }
        */
        return $this->httpQuery("search?type=dash-db&dashboardUIDs=" . $dashboard_uid);
    }

    public function getDashboards($folder_uid = '')
    {
        if ($folder_uid !== '') {
            $data = $this->httpQuery('search?type=dash-db&folderUIDs=' . $folder_uid);
        } else {
            $data = $this->httpQuery('search?type=dash-db');
        }
        return $data;
    }

    /**
     * Destroy session on grafana api (auth endpoint)
     *
     * @return bool
     */
    public function disconnect()
    {
        if (!isset($_SESSION['grafana']['session_token'])) {
            return true;
        }

        // send disconnect with http query
        $data = $this->httpQuery('session', [
            'json' => [
                'session_id' => $_SESSION['grafana']['session_token'],
            ],
        ], 'DELETE');

        unset($_SESSION['grafana']['session_token']);

        return $data !== false;
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
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        // declare default params
        $default_params = [
            '_with_metadata'  => false,
            'allow_redirects' => false,
            'timeout'         => 5,
            'connect_timeout' => 2,
            'debug'           => false,
            'verify'          => false,
            'query'           => [], // url parameter
            'body'            => '', // raw data to send in body
            'json'            => [], // json data to send
            'headers'         => [
                'content-type' => 'application/json',
                'Accept'                         => 'application/json',
            ],
        ];
        // if connected, append auth token
        //      if (isset($_SESSION['grafana']['session_token'])) {

        $user_pass_string = $this->api_config['username'] . ':' . (new GLPIKey())->decrypt($this->api_config['password']);
        $base64_token = base64_encode($user_pass_string);

        $default_params['headers']['Authorization'] =  "Basic " . $base64_token;
        //       }
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
            $this->last_error = [
                'title'     => 'Grafana API error',
                'exception' => $e->getMessage(),
                'params'    => $params,
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

            if ($_SESSION['glpi_use_mode'] == Session::DEBUG_MODE) {
                Toolbox::backtrace();
                Toolbox::logDebug($this->last_error);
            }

            return false;
        }

        // parse http response
        $http_code = $response->getStatusCode();
        $headers   = $response->getHeaders();

        // check http errors
        if (intval($http_code) > 400) {
            // we have an error if http code is greater than 400
            return false;
        }

        // cast body as string, guzzle return strems
        $json = (string) $response->getBody();
        $data = json_decode($json, true);

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
