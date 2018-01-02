<?php

require __DIR__ . '/vendor/autoload.php';

$config['webservice_url'] = 'steemd.minnowsupportproject.org';
$api = new DragosRoua\PHPSteemTools\SteemApi($config);

$author = "utopian-io";

/**
	
	Get the number of folloers, since it's resource intensive, we're using a basic cache mechanism
	
	*/
$follower_count  = $api->getFollowerCount($account);
$returned_follower_count = $follower_count['followers'];

$cache_folder = "./";
$file_name = $cache_folder.$author.".txt";
$cache_interval = 86400; // seconds for the cache file to live
if(file_exists($file_name)){
	
	// if the file was modified less than $cache_interval, we do nothing
	
	$current_time = time();
	if($current_time - filemtime($file_name) > $cache_interval){
		$follower_count  = $api->getFollowerCount($author);
		// write to the file again
		$handle = fopen($file_name, 'w+') or die('Cannot open file:  '.$file_name);
		fwrite($handle, "followers|".$follower_count);
		fclose($handle);
		$returned_follower_count = $follower_count;
	}
	else {
		// get the data from the cache file
		$cache_contents = file($file_name);
		$first_line = $cache_contents[0];
		$content = explode("|", $first_line);
		$returned_follower_count = $content[1];
	}
	
}
else {
	$follower_count  = $api->getFollowerCount($author);
	// write the data to cache file
	$handle = fopen($file_name, 'w+') or die('Cannot open file:  '.$file_name);
	fwrite($handle, "followers|".$follower_count);
	fclose($handle);
	$returned_follower_count = $follower_count;
}

/**
	
	Get content for the last 7 days and calculate the number of votes
	
	*/
	
date_default_timezone_set('UTC');
$dateNow = (new \DateTime())->format('Y-m-d\TH:i:s'); 
$date_8days_ago = date('Y-m-d\TH:i:s', strtotime('-8 days', strtotime($dateNow)));
$params = [$author, '', $date_8days_ago, 100];

$remote_content = $api->getDiscussionsByAuthorBeforeDate($params, 'websocket');	
//print_r($remote_content);

if(isset($remote_content)){
	if (array_key_exists('error', $remote_content)){
		echo "Something is not ok. You should investigate more.\n";
	}
	else 
	{

		$votes_number = array();
		$total_posts = 0;
		
		foreach($remote_content as $rkey => $rvalue){
			
			if($rvalue['pending_payout_value'] !== '0.000 SBD' && $rvalue['max_accepted_payout'] != 0){
				
				foreach($rvalue['active_votes'] as $idx => $voter){
					$voter_nickname = $voter['voter'];
					
					if($voter_nickname != $author){
						
						if(array_key_exists($voter['voter'], $votes_number)){
							
							$votes_number[$voter['voter']] += 1;
						} 
						else {
							
							$votes_number[$voter['voter']] = 1;
						}
					}
				}
			}
			$total_posts++;
		}
		echo "\n**************\n";
		echo "Total number of posts during the last 7 days: ".$total_posts."\n";
		echo "Total number of voters: ".count($votes_number)." \n";
		echo "Total followers: ".$returned_follower_count."\n";
		
		echo "**************\n";
		echo "Conversion rate: ".number_format((count($votes_number) * 100)/$returned_follower_count, 2)."%\n**************\n";
	}
}


?>