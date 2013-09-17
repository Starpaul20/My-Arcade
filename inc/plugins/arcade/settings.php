<?php
/**
 * My Arcade
 * Copyright 2013 Starpaul20
 */

$arcade_settings[] = array(
	'name' => 'enablearcade',
	'title' => 'Enable Arcade Functionality',
	'description' => 'If you wish to disable the arcade on your board, set this option to no.',
	'optionscode' => 'yesno',
	'value' => 1,
	'disporder' => 1
);

$arcade_settings[] = array(
	'name' => 'arcade_stats',
	'title' => 'Show Statistic Box',
	'description' => 'Allows you to set whether or not the statistic box will be shown at the top of the arcade home page.',
	'optionscode' => 'onoff',
	'value' => 1,
	'disporder' => 2
);

$arcade_settings[] = array(
	'name' => 'arcade_stats_newgames',
	'title' => 'Newest Games/Most Played Games',
	'description' => 'The number of newest games and most played games to show on the statistics box.',
	'optionscode' => 'text',
	'value' => 15,
	'disporder' => 3
);

$arcade_settings[] = array(
	'name' => 'arcade_stats_newchamps',
	'title' => 'Newest Champions',
	'description' => 'The number of newest champions to show in the statistic box.',
	'optionscode' => 'text',
	'value' => 5,
	'disporder' => 4
);

$arcade_settings[] = array(
	'name' => 'arcade_stats_newscores',
	'title' => 'Latest Scores',
	'description' => 'Number of latest scores to show in the statistic box.',
	'optionscode' => 'text',
	'value' => 5,
	'disporder' => 5
);

$arcade_settings[] = array(
	'name' => 'arcade_stats_bestplayers',
	'title' => 'Show Best Players',
	'description' => 'Do you wish to show a box of the three best players in the statistic box?',
	'optionscode' => 'yesno',
	'value' => 1,
	'disporder' => 6
);

$arcade_settings[] = array(
	'name' => 'arcade_stats_avatar',
	'title' => 'Show Avatar',
	'description' => $db->escape_string('Do you wish to show the user\'s avatar on the Best Players section?'),
	'optionscode' => 'yesno',
	'value' => 1,
	'disporder' => 7
);

$arcade_settings[] = array(
	'name' => 'gamesperpage',
	'title' => 'Games Per Page',
	'description' => 'The number of games to show per page on the arcade page.',
	'optionscode' => 'text',
	'value' => 10,
	'disporder' => 8
);

$arcade_settings[] = array(
	'name' => 'gamessortby',
	'title' => 'Default Sort Games By',
	'description' => 'Select the field that you want games to be sorted by default.',
	'optionscode' => 'select
name=Name
date=Date Added
plays=Times Played
lastplayed=Date Last Played
rating=Rating',
	'value' => 'name',
	'disporder' => 9
);

$arcade_settings[] = array(
	'name' => 'gamesorder',
	'title' => 'Default Game Ordering',
	'description' => 'Select the order that you want games to be sorted by default.<br />Ascending: A-Z / beginning-end<br />Descending: Z-A / end-beginning',
	'optionscode' => 'select
asc=Ascending
desc=Descending',
	'value' => 'asc',
	'disporder' => 10
);

$arcade_settings[] = array(
	'name' => 'arcade_category_number',
	'title' => 'Number Of Categories Per Row',
	'description' => 'The number of categories to display on a single row of the category table. It is recommended that this value be no higher than 10.',
	'optionscode' => 'text',
	'value' => 5,
	'disporder' => 11
);

$arcade_settings[] = array(
	'name' => 'arcade_newgame',
	'title' => 'Days For New Game',
	'description' => 'The number of days a game will be marked as new after being added.',
	'optionscode' => 'text',
	'value' => 7,
	'disporder' => 12
);

$arcade_settings[] = array(
	'name' => 'arcade_ratings',
	'title' => 'Game Ratings',
	'description' => 'Do you wish to allow games to be rated? Group permission can be set on Group Management page.',
	'optionscode' => 'yesno',
	'value' => 1,
	'disporder' => 13
);

$arcade_settings[] = array(
	'name' => 'arcade_searching',
	'title' => 'Game Searching',
	'description' => 'Do you wish to allow users to search for games based on name, description and category? Group permission can be set on Group Management page.',
	'optionscode' => 'yesno',
	'value' => 1,
	'disporder' => 14
);

$arcade_settings[] = array(
	'name' => 'arcade_whosonline',
	'title' => $db->escape_string('Who\'s Online Display'),
	'description' => 'Do you wish to show a box of who is online in the arcade and who can view it?',
	'optionscode' => 'radio
0=Disabled
1=Arcade Moderators and Administrators only
2=Registered Members
3=Everyone',
	'value' => 3,
	'disporder' => 15
);

$arcade_settings[] = array(
	'name' => 'arcade_onlineimage',
	'title' => 'Online Image',
	'description' => 'Do you wish to show an image of where the user currently is in the arcade?',
	'optionscode' => 'yesno',
	'value' => 1,
	'disporder' => 16
);

$arcade_settings[] = array(
	'name' => 'scoresperpage',
	'title' => 'Scores Per Page',
	'description' => 'The number of scores to show per page on the score page.',
	'optionscode' => 'text',
	'value' => 10,
	'disporder' => 17
);

$arcade_settings[] = array(
	'name' => 'arcade_editcomment',
	'title' => 'Comment Edit Time Limit',
	'description' => 'The number of minutes until regular users cannot edit their own score comments. Enter 0 (zero) for no limit.',
	'optionscode' => 'text',
	'value' => 60,
	'disporder' => 18
);

$arcade_settings[] = array(
	'name' => 'arcade_maxcommentlength',
	'title' => 'Maximum Score Comment Length',
	'description' => 'The maximum number of characters a score comment can be.',
	'optionscode' => 'text',
	'value' => 120,
	'disporder' => 19
);

$arcade_settings[] = array(
	'name' => 'statsperpage',
	'title' => 'Stats Per Page',
	'description' => 'The number of games to show per page on the stats page. It is recommended this be no higher than 15.',
	'optionscode' => 'text',
	'value' => 10,
	'disporder' => 20
);

$arcade_settings[] = array(
	'name' => 'gamesperpageoptions',
	'title' => 'User Selectable Games Per Page',
	'description' => 'If you would like to allow users to select how many games per page are shown in the arcade, enter the options they should be able to select separated with commas. If this is left blank they will not be able to choose how many games are shown per page.',
	'optionscode' => 'text',
	'value' => '5,10,15,20,25,30,40',
	'disporder' => 21
);

$arcade_settings[] = array(
	'name' => 'scoresperpageoptions',
	'title' => 'User Selectable Scores Per Page',
	'description' => 'If you would like to allow users to select how many scores per page are shown on score pages, enter the options they should be able to select separated with commas. If this is left blank they will not be able to choose how many scores are shown per page.',
	'optionscode' => 'text',
	'value' => '5,10,15,20,25,30,40',
	'disporder' => 22
);

$arcade_settings[] = array(
	'name' => 'arcade_postbit',
	'title' => 'Display Championships On Postbit',
	'description' => $db->escape_string('Do you wish to display a user\'s championships on the postbit?'),
	'optionscode' => 'yesno',
	'value' => 1,
	'disporder' => 23
);

$arcade_settings[] = array(
	'name' => 'arcade_postbitlimit',
	'title' => 'Maximum Championships On Postbit',
	'description' => 'Enter the maximum number of championships that should be shown on the postbit. Enter 0 (zero) for no limit.',
	'optionscode' => 'text',
	'value' => 10,
	'disporder' => 24
);

$arcade_settings[] = array(
	'name' => 'myalerts_alert_arcade_champship',
	'title' => 'Alert on Championship Broken?',
	'description' => 'Do you wish for users to receive an alert when their championship is broken? This setting only works if you have the <a href="http://mods.mybb.com/view/myalerts">MyAlerts</a> plugin installed.',
	'optionscode' => 'yesno',
	'value' => 1,
	'disporder' => 25
);

$tournament_settings[] = array(
	'name' => 'enabletournaments',
	'title' => 'Enable Tournament Functionality',
	'description' => 'If you wish to disable the tournament feature on your board, set this option to no.',
	'optionscode' => 'yesno',
	'value' => 1,
	'disporder' => 1
);

$tournament_settings[] = array(
	'name' => 'tournaments_numrounds',
	'title' => 'Number Of Rounds',
	'description' => 'The user selectable number of rounds for a tournament. The higher the number, the more players can participate (2 rounds = 4 players, 4 rounds = 16 players).',
	'optionscode' => 'text',
	'value' => '1,2,3,4,5',
	'disporder' => 2
);

$tournament_settings[] = array(
	'name' => 'tournaments_numtries',
	'title' => 'Number Of Tries Per Round',
	'description' => 'The user selectable number of tries a player has to get the best score possible in a round.',
	'optionscode' => 'text',
	'value' => '1,2,3,4,5',
	'disporder' => 3
);

$tournament_settings[] = array(
	'name' => 'tournaments_numdays',
	'title' => 'Number Of Days Per Round',
	'description' => 'The user selectable number of days a single round will last.',
	'optionscode' => 'text',
	'value' => '1,2,3,4,5,6,7',
	'disporder' => 4
);

$tournament_settings[] = array(
	'name' => 'tournaments_canceltime',
	'title' => 'Tournament Cancel Time',
	'description' => 'The amount of time a tournament has (in days) to get players before it is cancelled.',
	'optionscode' => 'text',
	'value' => 90,
	'disporder' => 5
);

$tournament_settings[] = array(
	'name' => 'myalerts_alert_arcade_newround',
	'title' => 'Alert on New Tournament round?',
	'description' => 'Do you wish for users to receive an alert when a new tournament round starts? This setting only works if you have the <a href="http://mods.mybb.com/view/myalerts">MyAlerts</a> plugin installed.',
	'optionscode' => 'yesno',
	'value' => 1,
	'disporder' => 6
);

?>