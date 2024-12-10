#!/usr/bin/env php
<?php

function getHolidays(int $year): array
{
    $easterDate = easter_date($year);
    $easterDay = date('j', $easterDate);
    $easterMonth = date('n', $easterDate);
    $easterYear = date('Y', $easterDate);

    // $bettag = strtotime('3 sunday', mktime(0, 0, 0, 9, 1, $year)); //betttag


    return [
        //  mktime(0, 0, 0, 12, 31, $year), // silvester
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
        mktime(0, 0, 0, $easterMonth, $easterDay + 50, $easterYear), //pfingst-montag
    ];
}

function getHalfHolidays(int $year): array
{
    $knabenschiessen = strtotime('2 sunday', mktime(0, 0, 0, 9, 1, $year));
    $knabenschiessen = strtotime('+1 day', $knabenschiessen);
    $heiligabend = mktime(0, 0, 0, 12, 24, $year);
    $silvester = mktime(0, 0, 0, 12, 31, $year);

    return [$silvester, $heiligabend, $knabenschiessen, getSechselauten($year)];
}

function getSechselauten($year)
{
    if (2030 === $year) {
        return mktime(0, 0, 0, 4, 29, $year);
    }

    //  see https://de.wikipedia.org/wiki/Sechsel%C3%A4uten#Datum
    $sechselauten = strtotime('3 monday', mktime(0, 0, 0, 4, 1, $year));
    $ostersonntag = easter_date($year);
    $secondsPerDay = 60 * 60 * 24;
    if ($ostersonntag + 1 * $secondsPerDay === $sechselauten) { // sechselauten am Ostermontag
        $sechselauten += 7 * $secondsPerDay;
    } elseif ($ostersonntag - 6 * $secondsPerDay === $sechselauten) { // sechselauten in Karwoche
        $sechselauten -= 7 * $secondsPerDay;
    }

    return $sechselauten;
}

function getUserInfos($user = null): array
{
    $user = $user ?? getenv('GOTOM_USER');
    if (!$user) {
        $user = 'settings';
    }
    $dataDir = getenv('GOTOM_DATA_DIR');
    if (!$dataDir) {
        $dataDir = __DIR__;
    }

    $config = require $dataDir.'/'.$user.'.php';
    $token = getenv('TOGGL_TOKEN');
    if ($token) {
        $config['TOGGL_API_TOKEN'] = $token;
    }

    if (!isset($config['TOGGL_API_TOKEN'])) {
        die('Create with a valid toggl token (api key) - https://toggl.com/app/profile');
    }
    if (!array_key_exists('TOGGL_USER_IDS', $config)) {
        die("set TOGGL_USER_IDS\n");
    }
    if (!array_key_exists('TOGGL_USER_AGENT', $config)) {
        $config['TOGGL_USER_AGENT'] = 'test_api';
    }


    if ($config['EMAIL'] ?? null) {
        $calamariRows = json_decode(file_get_contents($dataDir.'/calamari.json'), true, 512, JSON_THROW_ON_ERROR);
        $calamariRows = array_filter($calamariRows, fn($row) => $row['employeeEmail'] === $config['EMAIL'] && $row ['status']=== 'ACCEPTED'
);
        $calamariVacAmounts = [];

        foreach ($calamariRows as $row) {
            $year = substr($row['startTime'], 0, 4);

            if ($row['absenceTypeName'] === 'Vacation days') {
                if(!isset($calamariVacAmounts[$year])){
                    $calamariVacAmounts[$year] = 0;
                }
                $yearEnd = substr($row['endTime'], 0, 4);
                if($year === $yearEnd){
                    $calamariVacAmounts[$year] +=  (float) $row['entitlementAmount']  ;
                }else{
                    echo "\nWarning, there's a vacation amount of ({$row['entitlementAmount']}) for $year and $yearEnd, it will counted for $year\n\n";
                    $calamariVacAmounts[$year] +=  (float) $row['entitlementAmount']  ;
                }
            }

            $dateItem = [
                'FROM' => new DateTime(substr($row['startTime'], 0, 10)),
                'UNTIL' => new DateTime(substr($row['endTime'], 0, 10)),
            ];
            if (!$row['fullDayRequest']) {
                $dateItem['DAYS'] = $row['entitlementAmount'];
            }

            $config['VACATION'][$year][] = $dateItem;
        }

        foreach ($calamariVacAmounts as $year => $calamariVacAmount) {
            echo sprintf("Planned Vacation %d: %04.1f, left: %04.1f\n",$year,$calamariVacAmount, (($config['VACATION_DAYS_AMOUNT'] ?? 25) - $calamariVacAmount));
        }

    }

    foreach ($config['VACATION'] as $year => $yearConfig) {
        if (($yearConfig[0] ?? '') === 'sum') {
          continue;
        }
        usort($yearConfig, fn($rowA, $rowB) => $rowA['FROM'] <=> $rowB['FROM']);

        $lastEnd = null;
        foreach ($yearConfig as $row) {
            if ($lastEnd && $lastEnd > $row['FROM']) {
                trigger_error('FOUND OVERLAPPING DATE_RANGES, THIS IS THE FUCKED UP ROW: '.print_r($row, true), E_USER_WARNING);
            }
            $lastEnd = $row['UNTIL'];
        }

    }
    if(($_SERVER['argv'][1] ?? '') === '--dump'){
        foreach ($config['VACATION'] as $year => $yearConfig) {
            if ($yearConfig[0] === 'sum') {
                continue;
            }
            usort($yearConfig, fn($rowA, $rowB) => $rowA['FROM'] <=> $rowB['FROM']);
            echo "$year => [\n";
            foreach ($yearConfig as $row) {
                $from = $row['FROM']->format('d.m.Y');
                $until = $row['UNTIL']->format('d.m.Y');
                $days = isset($row['DAYS'])  ? "'DAYS' => {$row['DAYS']}" : '' ;
                echo "  ['FROM' => new \DateTime('$from'), 'UNTIL' => new \DateTime('$until)], $days \n";
            }
            echo "],\n";
        }
        die();
    }


    return $config;
}


function totalHours(DateTime $sinceDate, DateTime $untilDate, $config): float
{
    $since = $sinceDate->format('Y-m-d');
    $until = $untilDate->format('Y-m-d');
    $url = 'https://api.track.toggl.com/reports/api/v2/summary?type=me&workspace_id=1006502&since='.$since.'&until='.$until.'&user_ids='.$config['TOGGL_USER_IDS'].'&user_agent='.$config['TOGGL_USER_AGENT'];
    $command = ' curl -s  -u '.$config['TOGGL_API_TOKEN'].':api_token GET "'.$url.'" ';

    $json = exec($command);
    $arr = json_decode($json, true, 512);

    return $arr['total_grand'] / 1000 / 60 / 60;
}

function hoursToWork(DateTime $since, DateTime $until, array $config): float
{
    $sinceStart = new DateTime($config['START_DATE']);

    if ($since < $sinceStart) {
        $since = $sinceStart;
    }

    $hours = 0;
    while ($since <= $until) {
        $hoursPerDay = 8;
        if(isset($config['WORKING_HOURS'])){
            foreach ($config['WORKING_HOURS']as $workingDayConfig) {
                if($since >= $workingDayConfig['FROM'] && $since <= $workingDayConfig['UNTIL']){
                    $hoursPerDay = $workingDayConfig['VALUE'];
                }
          }
        }
        $daysOff = getDaysOff($since, $config['DAYS_OFF'] ?? []);
        $halfDaysOff = getDaysOff($since, $config['HALF_DAYS_OFF'] ?? []);
        $w = (int) $since->format('w');
        if ($w !== 0 && $w !== 6 && !in_array($w, $daysOff, true)) {
            $hdays = getHolidays((int) $since->format('Y'));
            if (!in_array($since->getTimestamp(), $hdays, true)) {
                $halfDays = getHalfHolidays((int) $since->format('Y'));
                $hours += $hoursPerDay;
                if (in_array($w, $halfDaysOff, true)) {
                    // current day is a weekly half day off
                    $hours -= (0.5*$hoursPerDay);
                }
                if (in_array($since->getTimestamp(), $halfDays, true)) {
                    // current day is not  a public holiday
                    $hours -= (0.5*$hoursPerDay);
                }
            }
        }
        $since->modify('+1 day');
    }

    return $hours;
}

function printHours(string $since, string $until, array $config, float $extraO = 0): float
{
    $sinceDatetime = new DateTime((new DateTime($since))->format('d.m.Y')) ;
    $untilDatetime =new DateTime((new DateTime($until))->format('d.m.Y')) ;

    if (array_key_exists('DISPLAY_DATE_FORMAT', $config) && $config['DISPLAY_DATE_FORMAT'] !== '') {
        $since = $sinceDatetime->format($config['DISPLAY_DATE_FORMAT']);
        $until = $untilDatetime->format($config['DISPLAY_DATE_FORMAT']);
    }

    echo "\n";
    if ($since === $until) {
        echo $since.":\n";
    } else {
        echo $since.' - '.$until.":\n";
    }

    $t = totalHours(clone $sinceDatetime, clone $untilDatetime, $config);
    $w = hoursToWork(clone $sinceDatetime, clone $untilDatetime, $config);
    $v = countVacationDays(clone $sinceDatetime, clone $untilDatetime, $config);
    $o = $t - $w + $v + $extraO;
    printf("  %01.2f - %01.2f + %01.2f = %01.2f \n", $t, $w, $v, $o);

    return $o;
}

function countVacationDays(DateTime $since, DateTime $until, array $config): float
{
    $year = (int) $since->format('Y');
    if (!array_key_exists('VACATION', $config) || !array_key_exists($year, $config['VACATION'])) {
        return 0;
    }
    $vacationDays = 0;
    $vacations = $config['VACATION'][$year];
    //fallback
    if ($vacations[0] === 'sum') {
        array_shift($vacations);

        return array_sum($vacations) * 8;
    }

    $hdays = getHolidays($year);
    $halfDays = getHalfHolidays($year);
    foreach ($config['VACATION'][$year] as $vacation) {
        if (!array_key_exists('FROM', $vacation) || !array_key_exists('UNTIL', $vacation)) {
            die('current vacation not configured correctly with \'FROM\' and \'UNTIL\'');
        }
        if ($vacation['FROM'] === null || $vacation['UNTIL'] === null) {
            break;
        }
        $from = clone $vacation['FROM'];


        if (isset($vacation['DAYS'])) {
            if ($from >= $since && $from <= $until) {
                $vacationDays += $vacation['DAYS'] * 8;
            }
            continue;
        }
        while ($from <= $vacation['UNTIL']) {
            if ($from >= $since && $from <= $until) {
                $daysOff = getDaysOff($from, $config['DAYS_OFF'] ?? []);
                $halfDaysOff = getDaysOff($from, $config['HALF_DAYS_OFF'] ?? []);
                $vacationDays += hoursToWorkAtDay($hdays, $halfDays, $daysOff, $halfDaysOff, $from);
            }
            $from->modify('+1 day');
        }
    }

    return $vacationDays;
}

function hoursToWorkAtDay(array $holidays, array $halfHolidays, array $daysOff, array $halfDaysOff, DateTime $day)
{
    $daysOff[] = 0;
    $daysOff[] = 6;
    $w = (int) $day->format('w');
    if (in_array($w, $daysOff, false)) {
        return 0;
    }

    if (in_array($day->getTimestamp(), $holidays, true)) {
        // no hours on public holidays
        return 0;
    }

    // 8 hours to work on a "normal" day
    $hours = 8;
    if (in_array($day->getTimestamp(), $halfHolidays, true)) {
        // 4 hours to work on a half a public holiday
        $hours -= 4;
    }
    if (in_array($day->getTimestamp(), $halfDaysOff, true)) {
        // no hours to work on a half day off on a half public holiday
        $hours -= 4;
    }

    return $hours;

}

date_default_timezone_set('Europe/Zurich');
//for ($year = 2020; $year <= 2022; $year++) {
//    $a = getHolidays($year);
// //   date_default_timezone_set('UTC');
//    $n = array_shift($a);
//    $date = new DateTime("@$n");
//    $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
//    echo $date->format('Y-m-d H:i:s') . "\n";
//  $l = array_pop($a);
//    $date = new DateTime("@$l");
//    $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
//    echo $date->format('Y-m-d H:i:s') . "\n";
////    echo $date->getTimestamp() . "\n";
////    var_dump($date);
////    $date = new DateTime("25-05-$year");
////    echo $date->format('Y-m-d H:i:s') . "\n";
////    echo $date->getTimestamp() . "\n";
////    var_dump($date);
//}

function getDaysOff(\DateTimeInterface $since, array $config): array
{
    if (!$config) {
        return [];
    }

    if (!is_array(current($config))) {
        throw new \RuntimeException('Please adjust your DAYS_OFF or HALF_DAYS_OFF config in your settings to the new format.');
    }

    return array_keys(
        array_filter(
            $config,
            static fn(array $activeDateRange): bool => $activeDateRange['FROM'] instanceof \DateTimeInterface
                && $activeDateRange['UNTIL'] instanceof \DateTimeInterface
                && $since >= $activeDateRange['FROM']
                && $since <= $activeDateRange['UNTIL']
        )
    );
}

echo "\n";

if (getenv('GOTOM_USER') === 'ALL') {
    foreach (glob(__DIR__.'/../data/*.php') as $file) {
        $matches = [];
        preg_match_all('|'.__DIR__.'/../data/(.*).php|', $file, $matches);
        $name = $matches[1][0];

        $config = getUserInfos($name);
        echo $name;
        printHours('last Sunday -1 week', 'last Sunday -1 week +6 days', $config);
        $thisYear = (int) date('Y');
        echo $name;
        printHours('01.01.'.$thisYear, 'yesterday', $config, $config['MODIFICATIONS'][$thisYear] ?? 0.0);
    }
    die;
}

$config = getUserInfos();

printHours('today', 'today', $config);
printHours('yesterday', 'yesterday', $config);
printHours('last Sunday -1 week', 'last Sunday -1 week +6 days', $config);
printHours('last Sunday -2 week', 'last Sunday -2 week +6 days', $config);

$total = 0;
$thisYear = (int) date('Y');
$startYear = (int) (new DateTimeImmutable($config['START_DATE']))->format('Y');
for ($year = $startYear; $year <= $thisYear; $year++) {
    $mod = $config['MODIFICATIONS'][$year] ?? 0.0;
    if ($year === $thisYear) {
        $start = '01.01.'.$year;
        if (new DateTimeImmutable($config['START_DATE']) > new DateTimeImmutable($start)) {
            $start = $config['START_DATE'];
        }
        $total += printHours($start, 'yesterday', $config, $mod);
    } else {
        $total += printHours('01.01.'.$year, '31.12.'.$year, $config, $mod);
    }
}

echo "\nTotal hours: ";
printf("%01.2f \n\n", $total);

printf("Overall: %01.2fh / %01.2fd \n\n", $total, $total / 8);

