<?php
header('Content-Type: text/html; charset=UTF-8');
define('DOING_AJAX', true);
$root	= dirname( __FILE__ ) . '/../../..';

if( file_exists( $root . '/wp-load.php' ) )
{
		// WP 2.6
		require_once( $root . '/wp-load.php' );
} else {
		// Pre 2.6
		require_once( $root . '/wp-config.php' );
}
require_once("bmcs_vote.php");
if( !function_exists('json_encode') )
{
   //--seems we are in PHP < 5.2... or json_encode() is disabled
   if(!class_exists('Services_JSON'))
     require_once( 'compat/json.php' );
   function json_encode($obj)
   {
	   $json = new Services_JSON();
	   return $json->encode( $obj );
   }
}
$userID = $_POST['bmcs_author'];
$postID = $_POST['bmcs_post'];
$vote = $_POST['bmcs_vote'];
$commentID = $_POST['bmcs_comment'];
$email = $_POST['bmcs_email'];
if($commentID == 0)
{
	$post = get_post($postID);
	$postAuthor = get_userdata($post->post_author);
}
else
{
	$comment = get_comment($commentID);
	$author = $comment->comment_author_email;
	$postAuthor = get_user_by_email($author);
}
	
if($postAuthor && $postAuthor->user_email && $email != $postAuthor->user_email && !bmcs_checkVoted($postID, $commentID, $postAuthor->user_email))
{
	$err = bmcs_vote($postID, $commentID, 1, $postAuthor->user_email);
	if($err)
	{
		$res->success = false;
		$res->error = $err;
		die(json_encode($res));
	}
}

$err = bmcs_vote($postID, $commentID, $vote, $email);
if($err)
{
	$res->success = false;
	$res->error = $err;
	die(json_encode($res));
}

$vote_results = array_merge(bmcs_getVotes($postID));


$res->success = true;
$res->votes = $vote_results;
echo json_encode($res);
