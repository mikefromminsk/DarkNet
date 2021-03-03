<?php

include_once $_SERVER["DOCUMENT_ROOT"] . "/dark_data/test.php";
include_once $_SERVER["DOCUMENT_ROOT"] . "/dark_domain/test.php";

$admin_token = requestNotEquals("localhost/dark_wallet/reg.php",
    array(
        "login" => "admin",
        "password" => "123",
    ),"token", null)["token"];

$keys = requestCountEquals("localhost/dark_wallet/coin_generate.php",
    array(
        "token" => $admin_token,
        "domain_name" => "POT",
        "domain_postfix_length" => "2",
    ),"keys", 100)["keys"];

function encode_decode(&$keys)
{
    foreach ($keys as $key => $value)
        $keys[$key] = strrev($value);
}

encode_decode($keys);

requestEquals("localhost/dark_wallet/save.php",
    array(
        "token" => $admin_token,
        "domain_name" => "POT",
        "keys" => $keys,
    ),"added", 99 /*todo need 100*/);


/*

include_once $_SERVER["DOCUMENT_ROOT"] . "/dark_wallet/properties.php";

foreach ($servers as $server) {
    http_post_json("$server/dark_wallet/hosting.php", array(
        "domain_name" => "POT",
        "domain_postfix_length" => "2",
    ));
}
*/


$user1_token = requestEquals("localhost/dark_wallet/reg.php",
    array(
        "login" => "user1",
        "password" => "123",
    ),"login", "user1")["token"];

// buy pots payment_start.php;
// save pots payment_finish.php;


encode_decode($keys);
$pot_send_keys = array_slice($keys, 0, 10);

requestEquals("localhost/dark_wallet/send.php",
    array(
        "token" => $admin_token,
        "receiver" => "user1",
        "domain_name" => "POT",
        "keys" => $pot_send_keys,
    ),"added", 10);


$pot_saved = requestNotEquals("localhost/dark_wallet/wallet.php",
    array(
        "token" => $user1_token,
    ),"income.admin.POT", null)["income.admin.POT"];

requestEquals("localhost/dark_wallet/save.php",
    array(
        "token" => $user1_token,
        "domain_name" => "POT",
        "keys" => $pot_saved,
    ),"added", 10);



/*

$keys =http_post_json("localhost/dark_wallet/coin_generate.php", array(
    "domain_name" => "TET",
    "domain_postfix_length" => "2",
    "keys" => array_slice($user1_pot_keys, 0, 2),
))["keys"];
http_post("localhost/dark_wallet/wallet/income.php", array(
    "token" => $user1_token,
    "keys" => $keys,
));


$payment_keys = [
    array_slice($user1_pot_keys, 2, 2),
    array_slice($user1_pot_keys, 4, 2),
    array_slice($user1_pot_keys, 6, 2),
];
for($i = 0; $i < 3; $i++) {
    $friend = $friends[$i];
    $payment = $payment_keys[$i];
    $response =http_post_json("$friend/dark_wallet/hosting.php", array(
        "domain_name" => "TET",
        "domain_postfix_length" => "2",
        "keys" => $payment,
    ));
}



$user2_token =http_post_json("localhost/dark_wallet/reg.php", array(
    "login" => "user2",
    "password" => "123",
))["token"];

$response =http_post_json("localhost/dark_wallet/send.php", array(
    "token" => $user1_token,
    "receiver" => "user2",
    "domain_name" => "TET",
    "count" => 10,
));
$coins =http_post_json("localhost/dark_wallet/coins.php", array(
    "token" => $user1_token,
));
$coins =http_post_json("localhost/dark_wallet/income.php", array(
    "token" => $user1_token,
    "keys" => $coins["income"]["user1"]["transaction123"]["keys"],
));
*/


