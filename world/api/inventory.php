<?php
include_once $_SERVER["DOCUMENT_ROOT"] . "/world/api/utils.php";

$address = get_required(address);

$response[inventory] = dataObject([world, avatar, $address, inventory], 100);

commit($response);