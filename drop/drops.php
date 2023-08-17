<?php

include_once $_SERVER["DOCUMENT_ROOT"] . "/data/utils.php";

$rewards = array_to_map(selectWhere(transfers, [type => DROP]), parameter);

$drops = selectWhere(drops, []);

foreach ($drops as &$drop) {
    $drop[rewarded] = $rewards[$drop[drop_id]] != null;
    $drop[link] = "http://localhost/stock/invite?i=$user_id&t=$drop[ticker]";
}

$response[drops] = $drops;

echo json_encode($response);