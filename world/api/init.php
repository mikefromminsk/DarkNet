<?php

include_once $_SERVER["DOCUMENT_ROOT"] . "/token/utils.php";
include_once $_SERVER["DOCUMENT_ROOT"] . "/world/api/utils.php";

$address = get_required(address);
$password = get_required(password);

if (!DEBUG) error("cannot use not in debug session");

function postWithGas($url, $params)
{
    requestEquals($url, array_merge($params, [
        gas_address => $GLOBALS[address],
        gas_pass => tokenPass($GLOBALS[gas_domain], $GLOBALS[address], $GLOBALS[password]),
    ]));
}

requestEquals("/token/init.php", [
    address => $address,
    password => $password
]);

function installApp($domain, $app_domain)
{
    postWithGas("/wallet/store/api/archive.php", [domain => $app_domain]);
    postWithGas("/wallet/store/api/install.php", [
        domain => $domain,
        app_domain => $app_domain,
    ]);
}

function launchList($tokens, $address, $password)
{
    foreach ($tokens as $token) {
        $domain = $token[domain];
        $amount = $token[amount] ?: 1000000;
        tokenAccountReg($domain, $address, $password, $amount);
        if (tokenAddressBalance($domain, $GLOBALS[address]) > 0)
            postWithGas("/world/api/token_deposit.php", [
                domain => $domain,
                amount => tokenAddressBalance($domain, $GLOBALS[address]),
                pass => tokenPass($domain, $GLOBALS[address], $GLOBALS[password]),
            ]);
        unset($token[domain]);
        if (sizeof(array_keys($token)) > 0) {
            postWithGas("/world/api/info_set.php", [
                domain => $domain,
                info => json_encode($token),
            ]);
        }
        if (worldBalance($domain, [avatar, $GLOBALS[address]]) == 1000000)
            postWithGas("/world/api/send.php", [
                from_path => implode("/", [avatar, $GLOBALS[address]]),
                to_path => world,
                domain => $domain,
                amount => $amount / 2,
            ]);
    }
}


$tokens = [
    [domain => "rock"],
    [domain => "oak_tree_generator"],
    [domain => "oak_tree", loot => [
        "oak_log" => 1,
    ]],
    [domain => "oak_log"],
    [domain => "stone"],
    [domain => "house1", recipe => [
        "oak_log" => 8
    ]],
    [domain => "house2", recipe => [
        "oak_log" => 20
    ]],
    [domain => "house3", recipe => [
        "stone" => 30
    ]],
    [domain => "zombie", loot => [
        "stone" => 1,
    ]],
    [domain => "zombie_spawner"],
    [domain => "zombie_spawner_generator"],
    [domain => "chest", recipe => [
        "oak_log" => 8
    ]],
];

launchList($tokens, $address, $password);


$response[success] = true;

echo json_encode($response);