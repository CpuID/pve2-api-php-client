<?php

/*

Copyright (c) 2012 Nathan Sullivan

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

*/

class PVE2_API {
	private $constructor_success;

	protected $pve_hostname;
	protected $pve_username;
	protected $pve_realm;
	protected $pve_password;

	private $print_debug;

	protected $pve_login_ticket;
	protected $pve_login_ticket_timestamp;
	protected $pve_cluster_node_list;

	public function __construct ($pve_hostname, $pve_username, $pve_realm, $pve_password) {
		if (empty($pve_hostname) || empty($pve_username) || empty($pve_realm) || empty($pve_password)) {
			# TODO - better error handling?
			print("Error - Hostname/Username/Realm/Password required for PVE_API object constructor.\n");
			$this->constructor_success = false;
			return false;
		}
		# Check hostname resolves.
		if (gethostbyname($pve_hostname) == $pve_hostname && !filter_var($pve_hostname, FILTER_VALIDATE_IP)) {
			# TODO - better error handling?
			print("Cannot resolve ".$pve_hostname.", exiting.\n");
			$this->constructor_success = false;
			return false;
		}

		$this->pve_hostname = $pve_hostname;
		$this->pve_username = $pve_username;
		$this->pve_realm = $pve_realm;
		$this->pve_password = $pve_password;

		$this->print_debug = false;

		# Default this to null, so we can check later on if were logged in or not.
		$this->pve_login_ticket = null;
		$this->pve_login_ticket_timestamp = null;
		$this->pve_cluster_node_list = null;
		$this->constructor_success = true;
	}

	public function constructor_success () {
		return $this->constructor_success;
	}

	private function convert_postfields_array_to_string ($postfields_array) {
		$postfields_key_values = array();
		foreach ($postfields_array as $field_key => $field_value) {
			$postfields_key_values[] = urlencode($field_key)."=".urlencode($field_value);
		}
		$postfields_string = implode("&", $postfields_key_values);
		return $postfields_string;
	}

	/*
	 * bool set_debug (bool on_off)
	 * Sets if we should print() debug information throughout the process,
	 * to assist in troubleshooting...
	 */
	public function set_debug ($on_off) {
		if (is_bool($on_off)) {
			$this->print_debug = $on_off;
			return true;
		} else {
			return false;
		}
	}

	/*
	 * bool login ()
	 * Performs login to PVE Server using JSON API, and obtains Access Ticket.
	 */
	public function login () {
		if (!$this->constructor_success) {
			return false;
		}

		# Prepare login variables.
		$login_postfields = array();
		$login_postfields['username'] = $this->pve_username;
		$login_postfields['password'] = $this->pve_password;
		$login_postfields['realm'] = $this->pve_realm;

		$login_postfields_string = $this->convert_postfields_array_to_string($login_postfields);
		unset($login_postfields);

		# Perform login request.
		$prox_ch = curl_init();
		curl_setopt($prox_ch, CURLOPT_URL, "https://".$this->pve_hostname.":8006/api2/json/access/ticket");
		curl_setopt($prox_ch, CURLOPT_POST, true);
		curl_setopt($prox_ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($prox_ch, CURLOPT_POSTFIELDS, $login_postfields_string);
		curl_setopt($prox_ch, CURLOPT_SSL_VERIFYPEER, false);

		$login_ticket = curl_exec($prox_ch);

		curl_close($prox_ch);
		unset($prox_ch);
		unset($login_postfields_string);

		$login_ticket_data = json_decode($login_ticket, true);
		if ($login_ticket_data == null) {
			# Login failed.
			# Just to be safe, set this to null again.
			$this->pve_login_ticket_timestamp = null;
			return false;
		} else {
			# Login success.
			$this->pve_login_ticket = $login_ticket_data['data'];
			# We store a UNIX timestamp of when the ticket was generated here, so we can identify when we need
			# a new one expiration wise later on...
			$this->pve_login_ticket_timestamp = time();
			return true;
		}
	}

	/*
	 * bool pve_check_login_ticket ()
	 * Checks if the login ticket is valid still, returns false if not.
	 * Method of checking is purely by age of ticket right now...
	 */
	protected function pve_check_login_ticket () {
		if ($this->pve_login_ticket == null) {
			# Just to be safe, set this to null again.
			$this->pve_login_ticket_timestamp = null;
			return false;
		}
		if ($this->pve_login_ticket_timestamp >= (time() + 7200)) {
			# Reset login ticket object values.
			$this->pve_login_ticket = null;
			$this->pve_login_ticket_timestamp = null;
			return false;
		} else {
			return true;
		}
	}

	/*
	 * object pve_action (string action_path, string http_method[, array put_post_parameters])
	 * This method is responsible for the general cURL requests to the JSON API,
	 * and sits behind the abstraction layer methods get/put/post/delete etc.
	 */
	private function pve_action ($action_path, $http_method, $put_post_parameters = null) {
		if (!$this->constructor_success) {
			return false;
		}

		# Check if we have a prefixed / on the path, if not add one.
		if (substr($action_path, 0, 1) != "/") {
			$action_path = "/".$action_path;
		}

		if (!$this->pve_check_login_ticket()) {
			if ($this->print_debug === true) {
				print("Error - Not logged into Proxmox Host. No Login Access Ticket found or Ticket Expired.\n");
			}
			return false;
		}

		# Prepare cURL resource.
		$prox_ch = curl_init();
		if ($this->print_debug === true) {
			print("\nURL - https://".$this->pve_hostname.":8006/api2/json".$action_path."\n");
		}
		curl_setopt($prox_ch, CURLOPT_URL, "https://".$this->pve_hostname.":8006/api2/json".$action_path);

		$put_post_http_headers = array();
		$put_post_http_headers[] = "CSRFPreventionToken: ".$this->pve_login_ticket['CSRFPreventionToken'];
		# Lets decide what type of action we are taking...
		switch ($http_method) {
			case "GET":
				# Nothing extra to do.
				break;
			case "PUT":
				curl_setopt($prox_ch, CURLOPT_CUSTOMREQUEST, "PUT");

				# Set "POST" data.
				$action_postfields_string = $this->convert_postfields_array_to_string($put_post_parameters);
				curl_setopt($prox_ch, CURLOPT_POSTFIELDS, $action_postfields_string);
				unset($action_postfields_string);

				# Add required HTTP headers.
				curl_setopt($prox_ch, CURLOPT_HTTPHEADER, $put_post_http_headers);
				break;
			case "POST":
				curl_setopt($prox_ch, CURLOPT_POST, true);

				# Set POST data.
				$action_postfields_string = $this->convert_postfields_array_to_string($put_post_parameters);
				curl_setopt($prox_ch, CURLOPT_POSTFIELDS, $action_postfields_string);
				unset($action_postfields_string);

				# Add required HTTP headers.
				curl_setopt($prox_ch, CURLOPT_HTTPHEADER, $put_post_http_headers);
				break;
			case "DELETE":
				curl_setopt($prox_ch, CURLOPT_CUSTOMREQUEST, "DELETE");

				# No "POST" data required, the delete destination is specified in the URL.

				# Add required HTTP headers.
				curl_setopt($prox_ch, CURLOPT_HTTPHEADER, $put_post_http_headers);
				break;
			default:
				if ($this->print_debug === true) {
					print("Error - Invalid HTTP Method specified.\n");	
				}
				return false;
		}

		curl_setopt($prox_ch, CURLOPT_HEADER, true);
		curl_setopt($prox_ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($prox_ch, CURLOPT_COOKIE, "PVEAuthCookie=".$this->pve_login_ticket['ticket']);
		curl_setopt($prox_ch, CURLOPT_SSL_VERIFYPEER, false);

		$action_response = curl_exec($prox_ch);

		curl_close($prox_ch);
		unset($prox_ch);

		$split_action_response = explode("\r\n\r\n", $action_response, 2);
		$header_response = $split_action_response[0];
		$body_response = $split_action_response[1];

		if ($this->print_debug === true) {
			print("----------------------------------------------\n");

			print("\nFULL RESPONSE:\n\n");
			print($action_response);
			print("\n\nEND FULL RESPONSE.\n");

			print("\nHeaders:\n\n");
			print($header_response);
			print("\n\nEnd Headers.\n");

			print("\nData:\n\n");
			print($body_response);
			print("\n\nEnd Headers.\n");
		}

		$action_response_array = json_decode($body_response, true);
		if ($this->print_debug === true) {
			print("\nRESPONSE ARRAY:\n\n");
			print_r($action_response_array);
			print("\nEND RESPONSE ARRAY.\n");
			print("----------------------------------------------\n");
		}

		unset($action_response);

		# Parse response, confirm HTTP response code etc.
		$split_headers = explode("\r\n", $header_response);
		if (substr($split_headers[0], 0, 9) == "HTTP/1.1 ") {
			$split_http_response_line = explode(" ", $split_headers[0]);
			if ($split_http_response_line[1] == "200") {
				if ($http_method == "PUT") {
					return true;
				} else {
					return $action_response_array['data'];
				}
			} else {
				if ($this->print_debug === true) {
					print("This API Request Failed.\n");
					print("HTTP Response - ".$split_http_response_line[1]."\n");
					print("HTTP Error - ".$split_headers[0]."\n");
				}
				return false;
			}
		} else {
			if ($this->print_debug === true) {
				print("Error - Invalid HTTP Response.\n");
				print_r($split_headers);
				print("\n");
			}
			return false;
		}

		if (!empty($action_response_array['data'])) {
			return $action_response_array['data'];
		} else {
			if ($this->print_debug === true) {
				print("Error - \$action_response_array['data'] is empty. Returning false.\n");
				var_dump($action_response_array['data']);
				print("\n");
			}
			return false;
		}
	}

	/*
	 * array get_node_list ()
	 * Returns the list of node names as provided by /api2/json/nodes.
	 * We need this for future get/post/put/delete calls.
	 * ie. $this->get("nodes/XXX/status"); where XXX is one of the values from this return array.
	 */
	public function reload_node_list () {
		if (!$this->constructor_success) {
			return false;
		}

		$node_list = $this->pve_action("/nodes", "GET");
		if (count($node_list) > 0) {
			$nodes_array = array();
			foreach ($node_list as $node) {
				$nodes_array[] = $node['node'];
			}
			$this->pve_cluster_node_list = $nodes_array;
			return true;
		} else {
			if ($this->print_debug === true) {
				print("Error - Empty list of nodes returned in this cluster.\n");
			}
			return false;
		}
	}

	public function get_node_list () {
		# We run this if we haven't queried for cluster nodes as yet, and cache it in the object.
		if ($this->pve_cluster_node_list == null) {
			if ($this->reload_node_list() === false) {
				return false;
			}
		}

		return $this->pve_cluster_node_list;
	}

	/*
	 * object/array? get (string action_path)
	 */
	public function get ($action_path) {
		if (!$this->constructor_success) {
			return false;
		}

		# We run this if we haven't queried for cluster nodes as yet, and cache it in the object.
		if ($this->pve_cluster_node_list == null) {
			if ($this->reload_node_list() === false) {
				return false;
			}
		}

		return $this->pve_action($action_path, "GET");
	}

	/*
	 * bool put (string action_path, array parameters)
	 */
	public function put ($action_path, $parameters) {
		if (!$this->constructor_success) {
			return false;
		}

		# We run this if we haven't queried for cluster nodes as yet, and cache it in the object.
		if ($this->pve_cluster_node_list == null) {
			if ($this->reload_node_list() === false) {
				return false;
			}
		}

		return $this->pve_action($action_path, "PUT", $parameters);
	}

	/*
	 * bool post (string action_path, array parameters)
	 */
	public function post ($action_path, $parameters) {
		if (!$this->constructor_success) {
			return false;
		}

		# We run this if we haven't queried for cluster nodes as yet, and cache it in the object.
		if ($this->pve_cluster_node_list == null) {
			if ($this->reload_node_list() === false) {
				return false;
			}
		}

		return $this->pve_action($action_path, "POST", $parameters);
	}

	/*
	 * bool delete (string action_path)
	 */
	public function delete ($action_path) {
		if (!$this->constructor_success) {
			return false;
		}

		# We run this if we haven't queried for cluster nodes as yet, and cache it in the object.
		if ($this->pve_cluster_node_list == null) {
			if ($this->reload_node_list() === false) {
				return false;
			}
		}

		return $this->pve_action($action_path, "DELETE");
	}

	# Logout not required, PVEAuthCookie tokens have a 2 hour lifetime.
}

?>
