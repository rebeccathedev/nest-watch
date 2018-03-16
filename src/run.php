<?php

include "vendor/autoload.php";

date_default_timezone_set("America/Chicago");

use cli\Arguments;
use InfluxDB\Point;
use InfluxDB\Database;

$cli = new Arguments();
$cli->addFlag(array('verbose', 'v'), 'Turn on verbose output');
$cli->addFlag(array('help', 'h'), 'Show this help screen');
$cli->addOption(array('config', 'c'), 'The config file.');
$cli->parse();

if ($cli['help']) {
    echo "nest-watch, a script that fetches data from Nest and put it into a.\n";
    echo "time-series database.\n";

    echo $cli->getHelpScreen();
    echo "\n\n";
    exit(0);
}

define('VERBOSE', !empty($cli["verbose"]));

$config_file = null;
if ($cli["config"]) {
    $config_file = $cli["config"];
} else {
    $search_paths = [
        "/usr/local/etc/nestwatch.conf",
        "/etc/nestwatch.conf",
        "./nestwatch.conf"
    ];

    foreach ($search_paths as $search_path) {
        if (file_exists($search_path)) {
            $config_file = $search_path;
            break;
        }
    }
}

if (empty($config_file)) {
    echo "ERROR: No config file found!\n";
    exit(1);
}

$config = parse_ini_file($config_file, true);

if (VERBOSE) echo "Attempting to connect to Nest.\n";
$nest = new Nest($config["nest"]["username"], $config["nest"]["password"]);

if (VERBOSE) echo "Attempting to connect to InfluxDB.\n";
$client = new InfluxDB\Client($config["influxdb"]["host"], $config["influxdb"]["port"]);
$database = $client->selectDB($config["influxdb"]["database"]);

if (VERBOSE) echo "Getting Weather information.\n";
$weather = $nest->getWeather($config["general"]["postal_code"]);

$points = [];

if (!empty($weather)) {
    foreach ($weather as $key => $value) {
        $points[] = new Point(
            "weather",
            (float)$value,
            ["key" => $key]
        );
    }
}

if (VERBOSE) echo "Getting devices.\n";
$devices = $nest->getDevices();

if (!empty($devices)) {
    foreach ($devices as $device_id) {
        if (VERBOSE) echo "Getting device info for $device_id.\n";
        $info = $nest->getDeviceInfo($device_id);

        if (!empty($info)) {
            $state = $info->current_state;
            $data = [
                "auto_cool" => (float)$info->auto_cool,
                "auto_heat" => (float)$info->auto_heat,
                "temperature" => (float)$state->temperature,
                "humidity" => (float)$state->humidity,
                "ac" => (float)$state->ac,
                "heat" => (float)$state->heat,
                "alt_heat" => (float)$state->alt_heat,
                "fan" => (float)$state->fan,
                "auto_away" => (float)$state->auto_away,
                "manual_away" => (float)$state->manual_away,
                "structure_away" => (float)$state->structure_away,
                "leaf" => (float)$state->leaf,
                "battery_level" => (float)$state->battery_level,
                "active_stages.heat.stage1" => (float)$state->active_stages->heat->stage1,
                "active_stages.heat.stage2" => (float)$state->active_stages->heat->stage2,
                "active_stages.heat.stage3" => (float)$state->active_stages->heat->stage3,
                "active_stages.heat.alt" => (float)$state->active_stages->heat->alt,
                "active_stages.heat.alt_stage2" => (float)$state->active_stages->heat->alt_stage2,
                "active_stages.heat.aux" => (float)$state->active_stages->heat->aux,
                "active_stages.heat.emergency" => (float)$state->active_stages->heat->emergency,
                "active_stages.cool.stage1" => (float)$state->active_stages->cool->stage1,
                "active_stages.cool.stage2" => (float)$state->active_stages->cool->stage2,
                "active_stages.cool.stage3" => (float)$state->active_stages->cool->stage3,
                "network.online" => (float)$info->network->online,
		        "target.timetotarget" => (float) $info->target->time_to_target
            ];

            switch($info->target->mode){
                case "off":
                    $data["target.mode"] = (float) 0;
                    break;
                case "range":
                    $data["target.mode"] = (float) 1;
                    $data["target.cool_temperature"] = (float) $info->target->temperature[1];
                    $data["target.heat_temperature"] = (float) $info->target->temperature[0];
                    break;
                case "cool":
                    $data["target.mode"] = (float) 2;
                    $data["target.cool_temperature"] = (float) $info->target->temperature;
                    break;
                case "heat":
                    $data["target.mode"] = (float) 3;
                    $data["target.heat_temperature"] = (float) $info->target->temperature;
                    break;
            }

            foreach ($data as $key => $value) {
                $points[] = new Point(
                    "nest",
                    $value,
                    ["key" => $key, "location" => $info->where, "name" => $info->name]
                );
            }
        }
    }
}

if (VERBOSE) echo "Writing to InfluxDB.\n";
$database->writePoints($points, Database::PRECISION_SECONDS);

if (VERBOSE) echo "Done.\n";
