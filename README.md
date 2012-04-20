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

    # Example - Return status array for each Proxmox Host in this cluster.

    require("./pve2-api-php-client/pve2_api.class.php");

    $pve2 = new PVE2_API("hostname", "username", "realm", "password");
    # realm above can be pve, pam or any other realm available.

    if ($pve2->constructor_success()) {
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


Licensed under the MIT License.
See LICENSE file.
