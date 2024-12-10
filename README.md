toggl-worktime-script
======

Setup
-----
 * download repo to `~/Projects/toggle-worktime-script/`
 * go to toggl > username (bottom left) > Profile settings
    * Export account data (top right) > check Personal profile data > Compile file and send to email
    * go back to Profile settings, scroll down, copy API Token
    * check your emails and open link, open profile.json in .zip
 * in Terminal `cd ~/Projects/toggle-worktime-script/`
    * `cp settings.dist.php settings.php` 
    * copy id from profile.json and API Token to settings.php
    * adjust settings.php
 * create bash-alias to launch worktime-script in .bashrc `nano ~/.bashrc`
 * add following line at bottom: `alias wt="php ~/Projects/toggle-worktime-script/worktime.php"`
 * reload .bashrc: `source ~/.bashrc`
 * launch script: `wt`

Run
-----
 * launch script: `wt`
 * dump all vacation entries: `wt --dump`
 * debug every week this year `wt --debug`

