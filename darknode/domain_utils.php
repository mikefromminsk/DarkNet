<?php

include_once $_SERVER["DOCUMENT_ROOT"] . "/db-utils/db.php";

function domain_hash($domain_name, $fromIndex = 0)
{
    $charsum = 0;
    for ($i = $fromIndex; $i < strlen($domain_name); $i++)
        $charsum += ord($domain_name[$i]);
    return $charsum;
}

function domains_set($domain_prefix, $domains)
{
    $success_domain_changed = [];
    $server_url = $GLOBALS["server_url"];
    if ($server_url == null)
        error("server_url is not defined. Please check properties.php file");

    $current_server_item = selectMap("select * from servers where server_domain_name = '" . uencode($domain_prefix) . "' "
        . " and server_url = '" . uencode($server_url) . "'");

    if ($current_server_item == null) {
        $current_server_item = array(
            "server_group_id" => random_id(),
            "server_domain_name" => $domain_prefix,
            "server_url" => $server_url,
        );
        insertList("servers", $current_server_item);
    }
    $users_cache = array();
    foreach ($domains as $domain) {

        $user_id = $domain["user_id"];
        $user_login = $domain["user_login"];
        $domain_name = $domain["domain_name"];

        if (strpos($domain_name, $domain_prefix) != 0)
            continue;

        if ($user_login != null) {
            if ($users_cache[$user_login] == null)
                $users_cache[$user_login] = scalar("select user_id from users where user_login = '" . uencode($user_login) . "'");
            $user_id = $users_cache[$user_login];
        }

        $current_domain = selectMap("select * from domains where domain_name = '" . uencode($domain_name) . "'");
        if ($current_domain != null) {
            if (hash("sha256", $domain["domain_next_key"]) == $current_domain["domain_next_key_hash"]) {
                $domain_next_key = random_id();
                $new_domain = array(
                    "domain_name" => $domain_name,
                    "domain_name_hash" => domain_hash($domain_name),
                    "domain_prev_key" => $domain["domain_prev_key"],
                    "domain_next_key_hash" => hash("sha256", $domain_next_key),
                    "domain_next_key" => $domain_next_key,
                    "server_group_id" => $current_server_item["server_group_id"],
                    "user_id" => $user_id,
                );
                if (updateList("domains", $new_domain, "domain_name", $domain_name))
                    $success_domain_changed[] = $new_domain["domain_name"];
            }
        } else {
            $new_domain = array(
                "domain_name" => $domain_name,
                "domain_name_hash" => domain_hash($domain_name),
                "domain_prev_key" => $domain["domain_prev_key"],
                "domain_next_key_hash" => $domain["domain_next_key_hash"],
                "domain_next_key" => $domain["domain_next_key"],
                "server_group_id" => $current_server_item["server_group_id"],
                "user_id" => $user_id,
            );
            if (insertList("domains", $new_domain))
                $success_domain_changed[] = $new_domain["domain_name"];
        }
    }


    return $success_domain_changed;
}

function domain_get($domain_name)
{
    return selectMap("select domain_name, domain_prev_key, domain_next_key_hash from domains where domain_name = '" . uencode($domain_name) . "'");
}

function domain_similar($domain_name)
{
    $domain_name_hash = domain_hash($domain_name);
    return select("select domain_name, domain_prev_key, domain_next_key_hash from domains "
        . " where domain_name_hash > " . ($domain_name_hash - 32768) . " and domain_name_hash < " . ($domain_name_hash + 32768)
        . " order by ABS(domain_name_hash - $domain_name_hash)  limit 5");
}

function getListFromStart($domain_prefix, $count, $user_id = null, $to_user_login = null)
{
    if ($user_id != null) {
        $where = "where user_id = $user_id and domain_name like '$domain_prefix%' limit $count";
        $domains = select("select domain_name, domain_next_key_hash, domain_next_key from domains $where");
        if ($user_id != null)
            update("update domains set user_id = null $where");
    } else {
        $domains = select("select domain_name, domain_next_key_hash from domains where domain_name like '$domain_prefix%' limit $count");
    }
    if ($to_user_login != null)
        foreach ($domains as $domain)
            $domain["user_login"] = $to_user_login;
    return $domains;
}


define("FILE_SIZE_HEX_LENGTH", 8);
define("HASH_ALGO", "sha256");
define("HASH_LENGTH", 51);
define("MAX_SMALL_DATA_LENGTH", HASH_LENGTH + FILE_SIZE_HEX_LENGTH);

function getFile($filename, $override_user_id = null)
{
    $path_items = explode("/", $filename);
    $domain_name = $path_items[0];
    $server_group_id = scalar("select server_group_id from domains where domain_name = '" . uencode($domain_name) . "' "
        . ($override_user_id == null ? "" : " and user_id = $override_user_id"));
    if ($server_group_id == null)
        return null;
    $file_id = $server_group_id;
    array_shift($path_items);
    foreach ($path_items as $file_name) {
        $next_file_id = scalar("select file_id from files where file_parent_id = $file_id and file_name = '" . uencode($file_name) . "'");
        if ($next_file_id == null) {
            if ($mkdirs) {
                $next_file_id = insertListAndGetId("files", array(
                    "file_parent_id" => $file_id,
                    "file_name" => $file_name, // save to file if > 59
                ));
            } else
                return null;
        }
        $file_id = $next_file_id;
    }
    return selectMap("select * from files where file_id = $file_id");
}

function getData($hash_or_data)
{
    if (strlen($hash_or_data) == MAX_SMALL_DATA_LENGTH) {
        $hash = substr($hash_or_data, FILE_SIZE_HEX_LENGTH);
        return file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/darknode/files/" . $hash);
    }
    return $hash_or_data;
}

function getSize($hash_or_data)
{
    if (strlen($hash_or_data) == MAX_SMALL_DATA_LENGTH)
        return hexdec(substr($hash_or_data, 0, FILE_SIZE_HEX_LENGTH));
    return strlen($hash_or_data);
}
