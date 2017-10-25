<?php
	
require __DIR__ . '/vendor/autoload.php';

	$config['webservice_url'] = 'gtg.steem.house:8090';
	$api = new DragosRoua\PHPSteemTools\SteemApi($config);
	
	$author = "stellabelle";
	
	
	date_default_timezone_set('UTC');
	$dateNow = (new \DateTime())->format('Y-m-d\TH:i:s'); 
	$date_8days_ago = date('Y-m-d\TH:i:s', strtotime('-8 days', strtotime($dateNow)));
	$params = [$author, '', $date_8days_ago, 100];
	
	$remote_content = $api->getDiscussionsByAuthorBeforeDate($params, 'websocket');	
	print_r($remote_content);
	
	if(isset($remote_content)){
		if (array_key_exists('error', $remote_content)){
			$return_value = "<br/>Something is not ok.</br>";
		}
		else 
		{
			$total_value = 0;
			$total_votes = 0;
			
			foreach($remote_content as $rkey => $rvalue){
				if($rvalue['pending_payout_value'] !== '0.000 SBD' && $rvalue['max_accepted_payout'] != 0){
					
					$tmp = explode(" ", $rvalue['pending_payout_value']);
					$payout = $tmp[0];
					
					// check to see if we have something in the beneficaries array
					if(count($rvalue['beneficiaries'] != 0)){
						foreach($rvalue['beneficiaries'] as $kk => $vv){
							if($vv['account'] == $author){
								$payout = $payout*($vv['weight']/10000);
							}
						}
					}
					$total_value =+ $payout * 0.75;
				}
			}
			echo "Total payout for $author: ".$total_value."\n";
		}
	}

	





?>
