<?php

	namespace DragosRoua\PHPSteemTools;
	use WebSocket\Client;
	use DragosRoua\PHPSteemTools\SteemLayer;

class SteemApi
{
    private $SteemLayer;
    private $api_ids = array();

    public function __construct($config = null)
    {
        $this->SteemLayer = new SteemLayer($config);
    }

    public function getDiscussionsByAuthorBeforeDate($params, $transport='curl'){
	    $result = $this->SteemLayer->call('get_discussions_by_author_before_date', $params, $transport);
        return $result;
    }

    public function getRepliesByLastUpdate($params){
	    $result = $this->SteemLayer->call('get_replies_by_last_update', $params);
        return $result;
    }

    public function getCurrentMedianHistoryPrice($currency = 'STEEM')
    {
        // TEMP: until I figure this out...
        if ($currency == 'STEEM') {
            return 0.80;
        }
        if ($currency == 'SBD') {
            return 0.92;
        }
        $result = $this->SteemLayer->call('get_current_median_history_price');
        $price = 0;
        if ($currency == 'STEEM') {
            $price = $result['base'] * $result['quote'];
        }
        if ($currency == 'SBD') {
            $price = $result['base'] * $result['quote'];
        }
        return $price;
    }

    public function getContent($params)
    {
        $result = $this->SteemLayer->call('get_content', $params);
        return $result;
    }

    public function getContentReplies($params)
    {
        $result = $this->SteemLayer->call('get_content_replies', $params);
        return $result;
    }

    public function getDiscussionsByComments($params, $transport='curl')
    {
        $result = $this->SteemLayer->call('get_discussions_by_comments', $params, $transport);
        return $result;
    }

    public function getAccountVotes($params)
    {
        $result = $this->SteemLayer->call('get_account_votes', $params);
        return $result;
    }

    public function getAccountHistory($params)
    {
        $result = $this->SteemLayer->call('get_account_history', $params);
        return $result;
    }

    public function getFollowerCount($account)
    {
        $followers = $this->getFollowers($account);
        return count($followers);
    }
    public function getFollowers($account, $start = '')
    {
        $limit = 100;
        $followers = array();
        $params = array($this->getFollowAPIID(),'get_followers',array($account,$start,'blog',$limit));
        $followers = $this->SteemLayer->call('call', $params);
        if (count($followers) == $limit) {
            $last_account = $followers[$limit-1];
            $more_followers = $this->getFollowers($account, $last_account['follower']);
            array_pop($followers);
            $followers = array_merge($followers, $more_followers);
        }
        return $followers;
    }

    public function lookupAccounts($params)
    {
        $accounts = $this->SteemLayer->call('lookup_accounts', $params);
        return $accounts;
    }

    public function getAccounts($accounts)
    {
        $get_accounts_results = $this->SteemLayer->call('get_accounts', array($accounts));
        return $get_accounts_results;
    }
    public function getFollowAPIID()
    {
        return $this->getAPIID('follow_api');
    }
    public function getAPIID($api_name)
    {
        if (array_key_exists($api_name, $this->api_ids)) {
            return $this->api_ids[$api_name];
        }
        $response = $this->SteemLayer->call('call', array(1,'get_api_by_name',array($api_name)));
        $this->api_ids[$api_name] = $response;
        return $response;
    }
    public function cachedCall($call, $params, $serialize = false, $batch = false, $batch_size = 100)
    {
        $data = @file_get_contents($call . '_data.txt');
        if ($data) {
            if ($serialize) {
                $data = unserialize($data);
            }
            return $data;
        }
        $data = $this->{$call}($params);
        if ($serialize) {
            $data_for_file = serialize($data);
        } else {
            $data_for_file = $data;
        }
        file_put_contents($call . '_data.txt',$data_for_file);
        return $data;

    }

    public function getResultInfo($blocks)
    {
        $max_timestamp = 0;
        $min_timestamp = 0;
        $max_id = 0;
        $min_id = 0;
        foreach($blocks as $block) {
            $timestamp = strtotime($block[1]['timestamp']);
            if ($timestamp >= $max_timestamp) {
                $max_timestamp = $timestamp;
                $max_id = $block[0];
            }
            if (!$min_timestamp) {
                $min_timestamp = $max_timestamp;
            }
            if ($timestamp <= $min_timestamp) {
                $min_timestamp = $timestamp;
                $min_id = $block[0];
            }
        }
        return array(
                'max_id' => $max_id,
                'max_timestamp' => $max_timestamp,
                'min_id' => $min_id,
                'min_timestamp' => $min_timestamp
            );
    }
    public function getAccountHistoryFiltered($account, $types, $start, $end)
    {
        $start_timestamp = strtotime($start);
        $end_timestamp = strtotime($end);
        $limit = 2000;
        $params = array($account, -1, $limit);
        $result = $this->getAccountHistory($params);
        $info = $this->getResultInfo($result);
        $filtered_results = $this->filterAccountHistory($result,$start_timestamp,$end_timestamp,$types);
        while ($start_timestamp < $info['min_timestamp']) {
            $from = $info['min_id'];
            if ($limit > $from) {
                $limit = $from;
            }
            $params = array($account, $info['min_id'], $limit);
            $result = $this->getAccountHistory($params);
            $filtered_results = array_merge(
                $filtered_results,
                $this->filterAccountHistory($result,$start_timestamp,$end_timestamp,$types)
                );
            $info = $this->getResultInfo($result);
        }
        return $filtered_results;
    }
    public function filterAccountHistory($result, $start_timestamp, $end_timestamp, $ops)
    {
        $filtered_results = array();
        if (count($result)) {
            foreach($result as $block) {
                $timestamp = strtotime($block[1]['timestamp']);
                if (in_array($block[1]['op'][0], $ops)
                    && $timestamp >= $start_timestamp
                    && $timestamp <= $end_timestamp
                    ) {
                    $filtered_results[] = $block;
                }
            }
        }
        return $filtered_results;
    }
    public function getOpData($result)
    {
        $data = array();
        foreach($result as $block) {
            $data[] = $block[1]['op'][1];
        }
        return $data;
    }
    private $dynamic_global_properties = array();

    public function getProps($refresh = false)
    {
        if ($refresh || count($this->dynamic_global_properties) == 0) {
            $this->dynamic_global_properties = $this->SteemLayer->call('get_dynamic_global_properties', array());
        }
        return $this->dynamic_global_properties;
    }

    public function getConversionRate() {
        $props = $this->getProps();
        $values = array(
            'total_vests' => (float) $props['total_vesting_shares'],
            'total_vest_steem' => (float) $props['total_vesting_fund_steem'],
        );
        return $values;
    }

    public function vest2sp($value)
    {
        $values = $this->getConversionRate();
        return round($values['total_vest_steem'] * ($value / $values['total_vests']), 3);
    }

    /* exchange related functions */

	public function getCurrentValue($coin){
		// calls CoinMarketCap Api and returns the current curse
		$url = "https://api.coinmarketcap.com/v1/ticker/".$coin."/";

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt($ch, CURLOPT_ENCODING, "gzip,deflate");
		$response = curl_exec($ch);
		curl_close($ch);
		return $response;
	}

	public function getUserJSONData($user){
		// calls CoinMarketCap Api and returns the current curse
		$url = "https://steemit.com/@".$user.".json";

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt($ch, CURLOPT_ENCODING, "gzip,deflate");
		$response = curl_exec($ch);
		curl_close($ch);
		return $response;
	}


	// Contributions by @profchydon
	// Getting this tool up to date with all available methods on api.steemjs documentation

	public function setSubscribeCallback ($params)
	{
			$result = $this->SteemLayer->call('set_subscribe_callback', $params);
			return $result;
	}

	public function setPendingTransactionCallback ($params)
	{
			$result = $this->SteemLayer->call('set_pending_transaction_callback', $params);
			return $result;
	}

	public function setBlockAppliedCallback ($params)
	{
			$result = $this->SteemLayer->call('set_block_applied_callback', $params);
			return $result;
	}

	public function cancelAllSubscriptions ($params)
	{
			$result = $this->SteemLayer->call('cancel_all_subscriptions', $params);
			return $result;
	}

	public function getTrendingTags ($params)
	{
			$result = $this->SteemLayer->call('get_trending_tags', $params);
			return $result;
	}

	public function getTagsUsedByAuthor ($params)
	{
			$result = $this->SteemLayer->call('get_tags_used_by_author', $params);
			return $result;
	}

	public function getPostDiscussionsByPayout ($params)
	{
			$result = $this->SteemLayer->call('get_post_discussions_by_payout', $params);
			return $result;
	}

	public function getCommentDiscussionsByPayout ($params)
	{
			$result = $this->SteemLayer->call('get_comment_discussions_by_payout', $params);
			return $result;
	}

	public function getDiscussionsByTrending ($params)
	{
			$result = $this->SteemLayer->call('get_discussions_by_trending', $params);
			return $result;
	}

	public function getDiscussionsByTrending30 ($params)
	{
			$result = $this->SteemLayer->call('get_discussions_by_trending30', $params);
			return $result;
	}

	public function getDiscussionsByCreated ($params)
	{
			$result = $this->SteemLayer->call('get_discussions_by_created', $params);
			return $result;
	}

	public function getDiscussionsByActive ($params)
	{
			$result = $this->SteemLayer->call('get_discussions_by_active', $params);
			return $result;
	}

	public function getDiscussionsByCashout ($params)
	{
			$result = $this->SteemLayer->call('get_discussions_by_cashout', $params);
			return $result;
	}

	public function getDiscussionsByPayout ($params)
	{
			$result = $this->SteemLayer->call('get_discussions_by_payout', $params);
			return $result;
	}

	public function getDiscussionsByVotes ($params)
	{
			$result = $this->SteemLayer->call('get_discussions_by_votes', $params);
			return $result;
	}

	public function getDiscussionsByChildren ($params)
	{
			$result = $this->SteemLayer->call('get_discussions_by_children', $params);
			return $result;
	}

	public function getDiscussionsByHot ($params)
	{
			$result = $this->SteemLayer->call('get_post_discussions_by_hot', $params);
			return $result;
	}

	public function getDiscussionsByFeed ($params)
	{
			$result = $this->SteemLayer->call('get_discussions_by_feed', $params);
			return $result;
	}

	public function getDiscussionsByBlog ($params)
	{
			$result = $this->SteemLayer->call('get_discussions_by_blog', $params);
			return $result;
	}

	public function getDiscussionsByPromoted ($params)
	{
			$result = $this->SteemLayer->call('get_discussions_by_promoted', $params);
			return $result;
	}

	public function getBlockHeader ($params)
	{
			$result = $this->SteemLayer->call('get_block_header', $params);
			return $result;
	}

	public function getBlock ($params)
	{
			$result = $this->SteemLayer->call('get_block', $params);
			return $result;
	}

	public function getOpsInBlock ($params)
	{
			$result = $this->SteemLayer->call('get_ops_in_block', $params);
			return $result;
	}

	public function getState ($params)
	{
			$result = $this->SteemLayer->call('get_state', $params);
			return $result;
	}

	public function getTrendingCategories ($params)
	{
			$result = $this->SteemLayer->call('get_trending_categories', $params);
			return $result;
	}

	public function getBestCategories ($params)
	{
			$result = $this->SteemLayer->call('get_best_categories', $params);
			return $result;
	}

	public function getActiveCategories ($params)
	{
			$result = $this->SteemLayer->call('get_active_categories', $params);
			return $result;
	}

	public function getRecentCategories ($params)
	{
			$result = $this->SteemLayer->call('get_recent_categories', $params);
			return $result;
	}

	public function getConfig ($params)
	{
			$result = $this->SteemLayer->call('get_config', $params);
			return $result;
	}

	public function getDynamicGlobalProperties ($params)
	{
			$result = $this->SteemLayer->call('get_dynamic_global_properties', $params);
			return $result;
	}

	public function getChainProperties ($params)
	{
			$result = $this->SteemLayer->call('get_chain_properties', $params);
			return $result;
	}

	public function getFeedHistory ($params)
	{
			$result = $this->SteemLayer->call('get_feed_history', $params);
			return $result;
	}

	public function getWitnessSchedule ($params)
	{
			$result = $this->SteemLayer->call('get_witness_schedule', $params);
			return $result;
	}

	public function getHardforkVersion ($params)
	{
			$result = $this->SteemLayer->call('get_hardfork_version', $params);
			return $result;
	}

	public function getNextScheduledHardfork ($params)
	{
			$result = $this->SteemLayer->call('get_next_scheduled_hardfork', $params);
			return $result;
	}

	public function getKeyReferences ($params)
	{
			$result = $this->SteemLayer->call('get_key_references', $params);
			return $result;
	}

	public function getAccountReferences ($params)
	{
			$result = $this->SteemLayer->call('get_account_references', $params);
			return $result;
	}

	public function lookupAccountNames ($params)
	{
			$result = $this->SteemLayer->call('lookup_account_names', $params);
			return $result;
	}

	public function lookupAccounts ($params)
	{
			$result = $this->SteemLayer->call('lookup_accounts', $params);
			return $result;
	}

	// start here

}
