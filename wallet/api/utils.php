<?php

include_once $_SERVER["DOCUMENT_ROOT"] . "/data/api/utils.php";
include_once $_SERVER["DOCUMENT_ROOT"] . "/data/api/track.php";

$gas_domain = "usdt";

function dataWalletSettingsSave($user, $key, $value)
{
    $values = dataHistory([wallet, settings, $user, $key]) ?: [];
    if (array_search($value, $values) === false)
        dataSet([wallet, settings, $user, $key], $value);
    return dataGet([wallet, settings, $user, $key]) == $value;
}

function dataWalletSettingsRead($user, $key)
{
    return dataHistory([wallet, settings, $user, $key]) ?: [];
}

function dataWalletReg($address, $next_hash, $domain = null)
{
    if ($domain == null)
        $domain = getDomain();
    $path = $domain . "/wallet";
    if (dataExist([$path, $address])) error("$path/$address exist");
    dataSet([$path, $address, next_hash], $next_hash);
    dataWalletSettingsSave($address, domains, $domain);
    trackVolume($domain, wallets, 1);
    return dataExist([$path, $address, next_hash]);
}

function dataWalletRegScript($domain, $address, $script)
{
    if (!dataExist([$domain, wallet, $address])) {
        dataWalletReg($address, md5(pass), $domain);
        dataWalletDelegate($domain, $address, pass, $script);
    }
}

function dataWalletDelegate($domain, $address, $key, $script)
{
    if (!dataExist([$domain, wallet, $address])) error("$address not exist");
    if (dataGet([$domain, wallet, $address, next_hash]) != md5($key)) error("key is not right");
    dataSet([$domain, wallet, $address, script], $script);
    return dataExist([$domain, wallet, $address, script]);
}

function dataWalletBalance($domain, $address)
{
    return dataGet([$domain, wallet, $address, amount]) ?: 0.0;
}

function dataWalletSend(
    $domain,
    $from_address,
    $to_address,
    $amount,
    $key = null,
    $next_hash = null
)
{

    //die(json_encode(md5($key)));
    //die(json_encode(dataGet([$path, $from_address, next_hash])));
    if ($amount == 0)
        return true;
    if (dataWalletBalance($domain, $from_address) < $amount)
        error(strtoupper($domain) . " balance is not enough in $from_address wallet");
    if (!dataExist([$domain, wallet, $to_address])) error("$to_address receiver doesn't exist");
    if ($key == null || $next_hash == null) {
        if (dataGet([$domain, wallet, $from_address, script]) != scriptPath())
            error("script " . scriptPath() . " cannot use $from_address address. Only " . dataGet([$domain, wallet, $from_address, script]));
    } else {
        if (dataGet([$domain, wallet, $from_address, next_hash]) != md5($key))
            error("key is not right");
    }

    dataSet([$domain, wallet, $from_address, prev_key], $key);
    dataSet([$domain, wallet, $from_address, next_hash], $next_hash);

    dataDec([$domain, wallet, $from_address, amount], $amount);
    dataInc([$domain, wallet, $to_address, amount], $amount);

    $last_trans = dataGet([$domain, last_trans]);
    $next_trans = md5($last_trans . $from_address . $to_address . $amount);
    $GLOBALS[trans][$domain][] = $next_trans;
    dataSet([$domain, trans, $next_trans], [
        from => $from_address,
        to => $to_address,
        amount => $amount,
    ]);
    dataSet([$domain, last_trans], $next_trans);
    dataSet([$domain, wallet, $from_address, last_trans], $next_trans);
    dataSet([$domain, wallet, $to_address, last_trans], $next_trans);
    trackVolume($domain, transfer, $amount);
    return $next_trans;
}

function getTran($domain, $txid)
{
    $gas = 0;
    if ($domain != $GLOBALS[gas_domain]) {
        $gasTxid = dataGet([$domain, trans, $txid, gas]);
        $gas = dataGet([$GLOBALS[gas_domain], trans, $gasTxid, amount]);
    }
    return [
        domain => $domain,
        txid => $txid,
        from => dataGet([$domain, trans, $txid, from]),
        to => dataGet([$domain, trans, $txid, to]),
        amount => dataGet([$domain, trans, $txid, amount]),
        time => dataInfo([$domain, trans, $txid])[data_time],
        gas => $gas,
    ];
}

function commit($response, $gas_address = null)
{
    $gas_rows = 0;
    $gas_rows += count($GLOBALS[new_data]);
    $gas_rows += count($GLOBALS[new_history]);
    $gas_spent = 0.001 * $gas_rows;

    if ($gas_rows != 0) {
        if ($gas_address != null) {
            $dataTxid = dataWalletSend(
                $GLOBALS[gas_domain],
                $gas_address,
                admin,
                $gas_spent);
        } else {
            $dataTxid = dataWalletSend(
                $GLOBALS[gas_domain],
                get_required(gas_address),
                admin,
                $gas_spent,
                get_required(gas_key),
                get_required(gas_next_hash)
            );
        }
        dataSet([analytics, gas_spent, scriptPath()], $gas_spent, false);
        /*foreach ($GLOBALS[trans] as $domain => $txids) {
            foreach ($txids as $txid) {
                dataSet([$domain, trans, $txid, gas], $dataTxid);
            }
        }*/
        dataCommit();
        $response[gas_spend] = $gas_spent;
        $response[gas_txid] = $dataTxid;
    }
    echo json_encode($response);
}

function installApp($domain, $app_domain, $filepath = null)
{
    if ($app_domain != null && $filepath == null) {
        $filepath = $_SERVER[DOCUMENT_ROOT] . "/wallet/apps/$app_domain.zip";
    }
    $archive_hash = hash_file(md5, $filepath);
    if (!$archive_hash) error("file hash is false in $filepath");
    //if (dataGet([$domain, packages, $app_domain, hash]) == $archive_hash) error("archive was uploaded before");

    $zip = new ZipArchive;
    if ($zip->open($filepath) !== true) error("zip->open is false");

    $zip->extractTo($_SERVER[DOCUMENT_ROOT] . DIRECTORY_SEPARATOR . $domain);

    $files = [];
    $hasRootIndex = false;
    $hasConsole = false;
    // calc $GLOBALS[gas_bytes] += 1; before unzip
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        if ($filename == "index.html") $hasRootIndex = true;
        if ($filename == "console/index.html") $hasConsole = true;
        $filepath = $domain . "/" . $filename;
        $filepath = implode("/", explode("\\", $filepath));
        $file_hash = hash_file(md5, $_SERVER[DOCUMENT_ROOT] . DIRECTORY_SEPARATOR . $filepath);
        $files[$file_hash] = $filepath;
        $GLOBALS[gas_bytes] += 1;
    }
    dataSet([$domain, packages, $app_domain, hash], $archive_hash);
    dataSet([wallet, info, $domain, ui], $hasRootIndex ? 1 : 0);
    dataSet([wallet, info, $domain, console], $hasConsole ? 1 : 0);
    dataSet([wallet, info, $domain, contracts], $files);
    $zip->close();

    return $archive_hash;
}

function uploadContent($domain, $filepath, $local_path)
{
    $zip = new ZipArchive;
    if ($zip->open($filepath) !== true) error("zip->open is false");

    for ($i = 0; $i < $zip->numFiles; $i++) {
        if (!in_array(pathinfo($zip->getNameIndex($i))[extension], [jpg, svg])) {
            error("file extension is not correct");
        }
    }
    $domain_folder = $_SERVER[DOCUMENT_ROOT] . DIRECTORY_SEPARATOR . $domain . DIRECTORY_SEPARATOR;
    $local_folder = $domain_folder . $local_path . DIRECTORY_SEPARATOR;
    $temp_folder = $local_folder . temp . DIRECTORY_SEPARATOR;
    $zip->extractTo($temp_folder);

    $files = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $extension = strtolower(pathinfo($zip->getNameIndex($i))[extension]);
        $hash = hash_file(md5, $temp_folder . $zip->getNameIndex($i));
        $target_filename = "$local_folder$hash.$extension";
        mkdir($local_folder, 0777, true);
        unlink($target_filename);
        rename($temp_folder . $zip->getNameIndex($i), $target_filename);
        $files[$hash] = $hash . "." . $extension;
    }

    $zip->close();
    return $files;
}

function dataIcoSell($key, $next_hash, $amount, $price) // shop
{
    $domain = getDomain();
    $gas_address = get_required(gas_address);
    $owner_address = dataGet([wallet, info, $domain, owner]);
    if ($gas_address == $owner_address) {
        if (!dataExist([$domain, wallet, ico])) {
            dataWalletRegScript($domain, $domain . _ico, "$domain/api/token/ico/buy.php");
            dataWalletRegScript(usdt, $domain . _ico, "$domain/api/token/ico/sell.php");
        }
        dataWalletSend($domain, $gas_address, ico, $amount, $key, $next_hash);
        dataSet([$domain, price], $price);
    } else {
        $token_price = dataGet([$domain, price]);
        $total_usdt = $amount * $token_price;
        dataWalletSend($domain, $gas_address, $domain . _ico, $amount, $key, $next_hash);
        dataWalletSend(usdt, $domain . _ico, $gas_address, $total_usdt);
    }
}

function dataIcoBuy($amount)
{
    $domain = getDomain();
    $gas_address = get_required(gas_address);
    $gas_key = get_required(gas_key);

    $temp_next_hash = md5($gas_key);

    $owner_address = dataGet([wallet, info, $domain, owner]);
    if ($gas_address == $owner_address) {
        dataWalletSend(usdt, $gas_address, $domain . _ico, $amount, $gas_key, $temp_next_hash);
    } else {
        $token_price = dataGet([$domain, price]);
        $total_usdt = $amount * $token_price;
        dataWalletSend(usdt, $gas_address, $domain . _ico, $total_usdt, $gas_key, $temp_next_hash);
        dataWalletSend($domain, ico, $gas_address, $amount);
    }
}

function dataWalletBonusCreate($domain,
                               $address,
                               $key,
                               $next_hash,
                               $amount,
                               $invite_next_hash)
{

}

function dataWalletBonusReceive($domain,
                                $invite_key,
                                $to_address)
{

}

function dataWalletKey($path, $address, $password, $prev_key = "")
{
    return md5($path . $address . $password . $prev_key);
}

function dataWalletHash($path, $address, $password, $prev_key = "")
{
    return md5(dataWalletKey($path, $address, $password, $prev_key));
}

function dataWalletProfile($domain, $address = null)
{
    return [
        domain => $domain,
        owner => dataGet([wallet, info, $domain, owner]),
        price => dataGet([$domain, price]) ?: 0,
        price24hChange => 0,
        balance => dataWalletBalance($domain, $address),
        mining => dataExist([$domain, mining]),
        created => dataInfo([$domain])[data_time],
    ];
}

if (get_string(gas_spent) != null) {
    $response[gas_spent] = dataGet([analytics, gas_spent, scriptPath()]) ?: 0;
    $response[gas_gap] = 0.01;
    $response[gas_recommended] = $response[gas_spent] + $response[gas_gap];
    die(json_encode($response));
}
