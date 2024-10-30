<?php
/*  Copyright 2009  BrainsMatch  (email : info@brainsmatch.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

include("xmlrpc.inc");
include("xmlrpc_wrappers.inc");

function bmcs_vote($postID, $commentID, $vote, $email)
{
	global $wpdb;
	error_log("bmcs_vote: $postID, $commentID, $vote, $email");
	$siteUrl = get_option('siteurl');
	
	if(!bmcs_sendProximityData($email, $siteUrl, $postID, $commentID, $vote))
	{
		return __('Failed to update proximity data');
	}
	
	$bmcsTable = $wpdb->prefix . 'bmcs_votes';
	
	$query = $wpdb->prepare("INSERT INTO $bmcsTable  VALUES ('%s', %d, %d, %d) ON DUPLICATE KEY UPDATE comment_id=%d, vote=%d",
	 $email, $postID, $commentID, $vote, $commentID, $vote);
	
	if(false === $wpdb->query( $query ))
		return __('Failed to insert vote into database: '.$query);

	return false;
}

function bmcs_checkVoted($postID, $commentID, $email)
{
	global $wpdb;
	$bmcsTable = $wpdb->prefix . 'bmcs_votes';
	
	$query = $wpdb->prepare("SELECT vote FROM $bmcsTable WHERE uid='%s' AND post_id=%d AND comment_id=%d", $email, $postID, $commentID);
	$vote = $wpdb->get_var($query);
	if(null === $vote)return false;
	return true;
}

function bmcs_getVotes($postID)
{
	global $wpdb;

	$bmcsTable = $wpdb->prefix . 'bmcs_votes';
	$bmcsTableComments = $wpdb->prefix . 'comments';
	
	
	$q = $wpdb->prepare("SELECT v.comment_id, count(v.uid) as cnt FROM $bmcsTable v WHERE v.post_id=%d AND vote=1 GROUP BY v.comment_id", $postID);
	$votes_pro = $wpdb->get_results($q);
	$q = $wpdb->prepare("SELECT v.comment_id, count(v.uid) as cnt FROM $bmcsTable v WHERE v.post_id=%d AND vote=0 GROUP BY v.comment_id", $postID);
	$votes_con = $wpdb->get_results($q);
	
	$vote_results = array();
	foreach($votes_pro as $vote)
	{
		$v = new StdClass;
		$v->idx = $vote->comment_id;
		$v->pro = $vote->cnt;
		$v->con = 0;
		$vote_results[$vote->comment_id] = $v;
	}
	foreach($votes_con as $vote)
	{
		$v = $vote_results[$vote->comment_id];
		if(!$v)
		{
			$v = new StdClass;
			$v->idx = $vote->comment_id;
			$v->pro = 0;
		}
		$v->con = $vote->cnt;
		$vote_results[$vote->comment_id] = $v;
	}
	return $vote_results;
}

function bmcs_sendProximityData($email, $siteUrl, $postID, $commentID, $vote)
{
	$c = new xmlrpc_client('http://www.brainsmatch.com/wp/engine.php');
	
	$c->return_type = 'phpvals'; // let client give us back php values instead of xmlrpcvals

	$function = wrap_xmlrpc_method($c, 'bmcs.sendData');
	if (!$function)return false;
	$ret = $function($email, $siteUrl, $postID, $commentID, $vote);
	if (is_a($res, 'xmlrpcresp')) // call failed
	{
	  return false;
	}
	return $ret;
}
