<?php

function getenvdefault($name, ?string $default = null): ?string {
    if (($result = getenv($name)) === false) {
        return $default;
    }
    return $result;
}

function getHolidays($year)
{
    $easterDate = easter_date($year);

    $easterDay = date('j', $easterDate);
    $easterMonth = date('n', $easterDate);
    $easterYear = date('Y', $easterDate);
    $bettag = strtotime("3 sunday", mktime(0, 0, 0, 9, 1, $year)); //betttag


    $holidays = [
        //   mktime(0, 0, 0, 12, 31, $year), // silvester
        mktime(0, 0, 0, 1, 1, $year), // neujahr
        mktime(0, 0, 0, 1, 2, $year), // berchtold
        mktime(0, 0, 0, 8, 1, $year), // 1. aug
        mktime(0, 0, 0, 5, 1, $year), // 1. mai
        mktime(0, 0, 0, 12, 25, $year), //weihnacht
        mktime(0, 0, 0, 12, 26, $year), //stephanstag
        // ostern
        mktime(0, 0, 0, $easterMonth, $easterDay - 2, $easterYear), //karfreitag
        mktime(0, 0, 0, $easterMonth, $easterDay + 1, $easterYear), //ostermontag
        mktime(0, 0, 0, $easterMonth, $easterDay + 39, $easterYear), //auffahrt
        mktime(0, 0, 0, $easterMonth, $easterDay + 51, $easterYear), //pfingst-montag
        //  strtotime('+1 day', $bettag), //bettmontag
    ];

    return $holidays;
}

function getHalfHolidays($year)
{
    $knabenschiessen = strtotime("2 sunday", mktime(0, 0, 0, 9, 1, $year));
    $knabenschiessen = strtotime('+1 day', $knabenschiessen);
    switch ($year) {
        case 2017:
            $sechselauten = mktime(0, 0, 0, 4, 24, $year);
            break;
        case 2018:
            $sechselauten = mktime(0, 0, 0, 4, 16, $year);
            break;
        case 2019:
            $sechselauten = mktime(0, 0, 0, 4, 8, $year);
            break;
        case 2020:
            $sechselauten = mktime(0, 0, 0, 4, 20, $year);
            break;
        case 2021:
            $sechselauten = mktime(0, 0, 0, 4, 19, $year);
            break;
        default:
            throw new \Exception('unhandled year');
    }

    return [$knabenschiessen, $sechselauten];
}

function totalHours($since, $until)
{
    $token = getenv('TOGGL_TOKEN');
    $userIds = getenv('TOGGL_USER_IDS');
    $userAgent = getenv('TOGGL_USER_AGENT');
    if($token === false){
        die("set TOGGL_TOKEN\n");
    }
    if($userIds === false){
        die("set TOGGL_USER_IDS\n");
    }
    if($userAgent === false){
        die("set TOGGL_USER_AGENT\n");
    }
    $since = new DateTime($since);
    $since = $since->format('Y-m-d');
    $until = new DateTime($until);
    $until = $until->format('Y-m-d');
    $command = ' curl -s  -u '.$token.':api_token GET "https://toggl.com/reports/api/v2/summary?type=me&workspace_id=1006502&since='.$since.'&until='.$until.'&user_ids='.$userIds.'&user_agent='.$userAgent.'" ';

    $json = exec($command);
    $arr = json_decode($json, true);
    $totalH = $arr['total_grand'] / 1000 / 60 / 60;

    return $totalH;
}

function hoursToWork($since, $until)
{
    $since = new DateTime($since);
    $until = new DateTime($until);
    $days = 0;
    while ($since <= $until) {
        $w = $since->format('w');
        if ($w != 0 && $w != 6) {
            $hdays = getHolidays($since->format('Y'));
            if (array_search($since->getTimestamp(), $hdays) === false) {
                $halfDays = getHalfHolidays($since->format('Y'));
                if (array_search($since->getTimestamp(), $halfDays) !== false) {
                    $days += 0.5;
                } else {
                    $days++;
                }
            }
        }
        $since->modify('+1 day');
    }

    return $days * (40 / 5);
}

function printHours($since, $until, $extraO = 0)
{
    echo "$since - $until:\n";
    $t = totalHours($since, $until);
    $w = hoursToWork($since, $until);
    $o = $t - $w + $extraO;
    printf("%01.2f - %01.2f = %01.2f \n\n", $t, $w, $o);

    return $o;
}


printHours('today', 'today');
printHours('yesterday', 'yesterday');
printHours('last Sunday', 'last Sunday +6 days');
printHours('last Sunday -1 week', 'last Sunday -1 week +6 days');

$total = 0;
$thisYear= date('Y');
$startYear = intval(getenvdefault('TOGGL_START_YEAR', '2017'));

for ($year=$startYear; $year <= $thisYear; $year++){
    $notHere = (int) getenv('TOGGL_AWAY_'.$year);
    $notHere *= 8;
    if($year == $thisYear){
        $total += printHours('01.01.'.$year, 'yesterday', $notHere);
    }else{
        $total += printHours('01.01.'.$year, '30.12.'.$year, $notHere);
    }
}


printf("%01.2f \n\n", $total);
