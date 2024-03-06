<?php

return [
    'TOGGL_API_TOKEN'       => '',      // your toggl API Token ( OR AS ENVIROMENT VARIABLE: TOGGL_TOKEN)
    'EMAIL'                 => '',     // your email-address, used for calamari data
    'TOGGL_USER_AGENT'      => '',      // your toggl username (GoTom-email)
    'TOGGL_USER_IDS'        => '',      // your toggl id from profile.json
    'DISPLAY_DATE_FORMAT'   => 'd.m.Y',      // optional, a format to display the dates on readout e.g. 'Y-m-d' or 'd.m.Y', etc.
    'START_DATE'            => '',    // optional, 2017 by default, the year you started
    'DAYS_OFF'              => [],       // to configure part time work, weekly  days off, enter the day number for each day as array entry (1 = monday, 2 = tuesday, ...) and the date range config, i.e. [5 => ['FROM' => new \DateTimeImmutable('01.01.2022'), 'UNTIL' => new \DateTimeImmutable()]]
    'HALF_DAYS_OFF'         => [],       // to configure part time work, weekly half days off, enter the day number for each day as array entry (1 = monday, 2 = tuesday, ...) and the date range config, i.e. [5 => ['FROM' => new \DateTimeImmutable('01.01.2022'), 'UNTIL' => new \DateTimeImmutable()]]
    'VACATION_DAYS_AMOUNT'  => 5*5,     // 5 weeks à 5 days
    'VACATION'              => [    // one entry for each year
                                    2020 => [
                                        [   // one entry for each vacation in year
                                            'FROM' => null,  //Datetime, e.g.new DateTime('1-1-2020');
                                            'UNTIL' => null, //timestamp, e.g. new DateTime('2-1-2020');
                                            // days => 1.4 additional float, override the days-calculator, for partial/special days vacation
                                        ],
                                    ],
                                ],
    'MODIFICATIONS'         =>  [
                                     2020 => 0.0 // special changes etc.
                                 ],
];


