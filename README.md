This class provides the building blocks for someone wanting to use PHP to talk to Proxmox 2.0.
Relatively simple piece of code, just provides a get/put/post/delete abstraction layer as methods
on top of Proxmox's REST API, while also handling the Login Ticket headers required for authentication.

See http://pve.proxmox.com/wiki/Proxmox_VE_API for information about how this API works.
API spec available at http://pve.proxmox.com/pve2-api-doc/

## Requirements: ##

PHP 5 with cURL (including SSL) support.

## Caveats: ##

This is a work in progress, currently GET's and POST's are tested, just need to test the others...

## Usage: ##

Example - Return status array for each Proxmox Host in this cluster.

    require("./pve2-api-php-client/pve2_api.class.php");

    $pve2 = new PVE2_API("hostname", "username", "realm", "password");
    # realm above can be pve, pam or any other realm available.

    if ($pve2->constructor_success()) {
        /* Optional - enable debugging. It print()'s any results currently */
        // $pve2->set_debug(true);

        if ($pve2->login()) {
            foreach ($pve2->get_node_list() as $node_name) {
                print_r($pve2->get("/nodes/".$node_name."/status"));
            }
        } else {
            print("Login to Proxmox Host failed.\n");
            exit;
        }
    } else {
        print("Could not create PVE2_API object.\n");
        exit;
    }

Example - Create a new OpenVZ Container on the first host in the cluster.

    require("./pve2-api-php-client/pve2_api.class.php");

    $pve2 = new PVE2_API("hostname", "username", "realm", "password");
    # realm above can be pve, pam or any other realm available.

    if ($pve2->constructor_success()) {

        /* Optional - enable debugging. It print()'s any results currently */
        // $pve2->set_debug(true);

        if ($pve2->login()) {

            # Get first node name.
            $nodes = $pve2->get_node_list();
            $first_node = $nodes[0];
            unset($nodes);

            # Create a VZ container on the first node in the cluster.
            $new_container_settings = array();
            $new_container_settings['ostemplate'] = "local:vztmpl/debian-6.0-standard_6.0-4_amd64.tar.gz";
            $new_container_settings['vmid'] = "1234";
            $new_container_settings['cpus'] = "2";
            $new_container_settings['description'] = "Test VM using Proxmox 2.0 API";
            $new_container_settings['disk'] = "8";
            $new_container_settings['hostname'] = "testapi.domain.tld";
            $new_container_settings['memory'] = "1024";
            $new_container_settings['nameserver'] = "4.2.2.1";

            // print_r($new_container_settings);
            print("---------------------------\n");

            print_r($pve2->post("/nodes/".$first_node."/openvz", $new_container_settings));
            print("\n\n");
        } else {
            print("Login to Proxmox Host failed.\n");
            exit;
        }
    } else {
        print("Could not create PVE2_API object.\n");
        exit;
    }


Licensed under the MIT License.
See LICENSE file.
