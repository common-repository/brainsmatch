<?php
/*
Plugin Name: Brainsmatch
Plugin URI: http://wp.brainsmatch.com
Description: Adds fascinating people match functionality
Author: Constantine Poltyrev
Version: 1.0.2
Author URI: http://wp.brainsmatch.com
Generated At: http://wp.brainsmatch.com;
*/ 

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


require_once("bmcs_vote.php");
register_activation_hook( __FILE__, 'bmcs_install' );

if (!class_exists('WpBmcsVote')) {
	class WpBmcsVote {

		var $localizationName = 'bmcs';
		
		/**
		* PHP 4 Compatible Constructor
		*/
		function WpBmcsVote(){$this->__construct();}

		/**
		* PHP 5 Constructor
		*/		
		function __construct()
		{
			add_action( 'wp_head', array(&$this,'bmcs_js_header') );
			add_action('comment_post', array(&$this,'bmcs_comment'));
			add_action('publish_post', array(&$this,'bmcs_post'));
			add_action('widgets_init', array(&$this, 'initWidget'));
			add_action('init', array(&$this, 'init'));
		}

		function init() 
		{
			//* Begin Localization Code */
			$bmcs_locale = get_locale();
			$bmcs_mofile = WP_CONTENT_DIR . "/plugins/brainsmatch/languages/" . $this->localizationName . "-". $bmcs_locale.".mo";
			load_textdomain($this->localizationName, $bmcs_mofile);
			//* End Localization Code */
		}
		
		function initWidget()
		{
			if (!function_exists('register_sidebar_widget'))
					return;
			register_sidebar_widget('BrainsMatch Soulmates', array('WpBmcsVote', 'showWidgetMates'));
			register_sidebar_widget('BrainsMatch Opponents', array('WpBmcsVote', 'showWidgetOpponents'));
		}

		function showWidgetMates($args)
		{
			extract($args);
			global $wpdb;
			$current_user = wp_get_current_user();
			$siteUrl = get_option('siteurl');
			$isLoggedIn = true;
			if(!$current_user || !$current_user->ID)
			{
				$uid = $wpdb->get_var( $wpdb->prepare("SELECT MIN(ID) FROM $wpdb->users" ));
				$current_user = get_userdata($uid);
				$isLoggedIn = false;
			}
			$user = $current_user->display_name;
			
			$res = WpBmcsVote::getProximityData($current_user->user_email, $siteUrl, 10);
			echo $before_widget;
			echo $before_title . ($isLoggedIn? __('My soulmates') : sprintf(__("soulmates of %s"), $user)).$after_title;
			if(is_array($res))
			{
				echo "<ul>";
				foreach($res as $match)
				{
				   echo "<li>".WpBmcsVote::getUserName($match['email']).' ('.$match['distance'].')</li>';
				}
				echo "</ul>";
			}
			echo $after_widget;
		}

		function showWidgetOpponents($args)
		{
			extract($args);
			global $wpdb;
			$current_user = wp_get_current_user();
			$siteUrl = get_option('siteurl');
			$isLoggedIn = true;
			if(!$current_user || !$current_user->ID)
			{
				$uid = $wpdb->get_var( $wpdb->prepare("SELECT MIN(ID) FROM $wpdb->users" ));
				$current_user = get_userdata($uid);
				$isLoggedIn = false;
			}
			$user = $current_user->display_name;

			$res = WpBmcsVote::getProximityData($current_user->user_email, $siteUrl, 10, true);
			echo $before_widget;
			echo $before_title . ($isLoggedIn? __('My opponents') : sprintf(__("opponents of %s"), $user)).$after_title; 
			if(is_array($res))
			{
				if(is_array($res))
				{
					echo "<ul>";
					foreach($res as $match)
					{
					   echo "<li>".WpBmcsVote::getUserName($match['email']).' ('.$match['distance'].')</li>';
					}
					echo "</ul>";
				}
			}
			echo $after_widget;
		}

		function getUserName($email)
		{
			global $wpdb;
			list($dispEmail, $rest) = split('@', $email);
			$uname = $wpdb->get_var( $wpdb->prepare("SELECT display_name FROM $wpdb->users WHERE user_email = %s", mysql_escape_string($email)) );
			return $uname?$uname:$dispEmail;
		}
		
		function getProximityData($email, $siteUrl, $num, $diff=false)
		{
			$c = new xmlrpc_client('http://www.brainsmatch.com/wp/engine.php');
			
			$c->return_type = 'phpvals'; // let client give us back php values instead of xmlrpcvals
		
			$function = wrap_xmlrpc_method($c, 'bmcs.getCoin');
			if (!$function)return false;
			$ret = $function($email, $siteUrl, $num, $diff);
			if (is_a($res, 'xmlrpcresp')) // call failed
			{
			  return false;
			}
			return $ret;
		}		
		
		function process_item($content='')
		{
			global $post, $more, $link, $wpdb, $urls;
			$current_user = wp_get_current_user();
			$author = $current_user->ID;
			$postID = $post->ID;
			$email = $current_user->user_email;

			$this->vote_results = bmcs_getVotes($post->ID);
			if($more)
			{
				$pro = $con = '&nbsp;';
				if($this->vote_results[0])
				{
						$pro .= '('.$this->vote_results[0]->pro.')';
						$con .= '('.$this->vote_results[0]->con.')';
				}
				//$content .= '<div class="bmcs_vote_result" id="bm_vote0">'.$prc.'</div>';
				if($current_user->ID)
				{
					$content .= '<div class="bm_options">'
					 . '<a href="javascript:void(0);" onclick="bmcs_vote( ' . $postID . ', 0, 1, '.$author.', \''.$email.'\');" title="'.__('Agree').'">'.__('Agree').'<span class="bm_vote_pro" id="bm_vote_pro0">'.$pro.'</span></a>'
					 . '&nbsp;<a href="javascript:void(0);" onclick="bmcs_vote( ' . $postID . ', 0, 0, '.$author.', \''.$email.'\');" title="'.__('Disagree').'">'.__('Disagree').'<span class="bm_vote_con" id="bm_vote_con0">'.$con.'</span></a></div>';
				}
			}
			return $content;
		}
		
		function bmcs_add_vote($content= '') {
			global $comment;
			$current_user = wp_get_current_user();
			$author = $current_user->ID;
			$postID = $comment->comment_post_ID;
			$email = $current_user->user_email;

			if (empty($comment)) { return $content; }
			$pro = $con = '&nbsp;';
			if($this->vote_results[$comment->comment_ID])
			{
				$pro .= '('.$this->vote_results[$comment->comment_ID]->pro.')';
				$con .= '('.$this->vote_results[$comment->comment_ID]->con.')';
			}
			//$content .= '<div class="bmcs_vote_result" id="bm_vote'.$comment->comment_ID.'">'.$prc.'</div>';
			if($current_user->ID)
			{
					$content .= '<div class="bm_options">'
                        .'<a href="javascript:void(0);" onclick="bmcs_vote( ' . $postID . ', ' . $comment->comment_ID . ', 1, '.$author.', \''.$email.'\');" title="'.__('Agree').'">'.__('Agree').'<span class="bm_vote_pro" id="bm_vote_pro'.$comment->comment_ID.'">'.$pro.'</span></a>'
						.'&nbsp;<a href="javascript:void(0);" onclick="bmcs_vote( ' . $postID . ', ' . $comment->comment_ID . ', 0, '.$author.', \''.$email.'\');" title="'.__('Disagree').'">'.__('Disagree').'<span class="bm_vote_con" id="bm_vote_con'.$comment->comment_ID.'">'.$con.'</span></a></div>';
			}
			return $content;
		}

		function bmcs_comment($cid)
		{
			global $post;
			$current_user = wp_get_current_user();
			if($current_user->ID)
			{
				$comment = get_comment($cid);
				$err = bmcs_vote($comment->comment_post_ID, $cid, 1, $current_user->user_email);
			}
		}

		function bmcs_post($pid)
		{
			$current_user = wp_get_current_user();
			if($current_user->ID)
			{
				$err = bmcs_vote($pid, 0, 1, $current_user->user_email);
			}
		}
		
		function bmcs_js_header()
		{
			global $bmcsPath;
			$current_user = wp_get_current_user();
		
			add_filter('comment_text', array(&$this, 'bmcs_add_vote'));
			add_filter('the_content', array(&$this, 'process_item'));
			
			$bmcsPath = '/wp-content/plugins/brainsmatch';
			wp_print_scripts( array( 'sack' ) );
		
			?>
			
			<link href="<?php bloginfo('wpurl'); echo $bmcsPath; ?>/style.css" rel="stylesheet" type="text/css" />
			
			<script type="text/javascript">
			
			function bmcs_vote( postID, commentID, vote, author, email )
			{
				var mysack = new sack( "<?php bloginfo('wpurl'); echo $bmcsPath; ?>/bmcs_ajax.php" );
		
				mysack.method = 'POST';
				
				mysack.setVar( 'bmcs_post', postID );
				mysack.setVar( 'bmcs_comment', commentID );
				mysack.setVar( 'bmcs_vote', vote );
				mysack.setVar( 'bmcs_author', author);
				mysack.setVar( 'bmcs_email', email);
				
				mysack.onError	= function() { alert( 'Voting error.' ) };
				mysack.onCompletion = function() { bmcs_finishVote( commentID, eval( '(' + this.response + ')' )); }
				
				mysack.runAJAX();
			}
			
			function bmcs_finishVote( commentID, response )
			{
			  //console.dir(response);
			  if(response.success)
			  {
				var els = getElementsByClassName("bmcs_vote_result");
				for(i=0, n=els.length; i < n; i++)
				{
				  els[i].innerHTML='';
				}

				for(i=0, n=response.votes.length; i < n; i++)
				{
				  var elId = "bm_vote_pro"+response.votes[i].idx;
				  var el = document.getElementById(elId);
				  if(el)el.innerHTML = '('+response.votes[i].pro+')';
				  
				  elId = "bm_vote_con"+response.votes[i].idx;
				  el = document.getElementById(elId);
				  if(el)el.innerHTML = '('+response.votes[i].con+')';
				}
			  }
			  else alert(response.error);
			}

			function getElementsByClassName(classname, node) {
			  if(!node) node = document.getElementsByTagName("body")[0];
			  var a = [];
			  var re = new RegExp('\\b' + classname + '\\b');
			  var els = node.getElementsByTagName("*");
			  for(var i=0,j=els.length; i<j; i++)
				if(re.test(els[i].className))a.push(els[i]);
			  return a;
			}			
		</script>
	
	<?php
		}
	}
}

function bmcs_install()
{
	global $wpdb;

	$dbVersion = get_option('bmcs_db_version');

	$myDbVersion = 3;
	
	if( $dbVersion < $myDbVersion )
	{
		$bmcsTable = $wpdb->prefix . 'bmcs_votes';
		$wpdb->query( "DROP TABLE IF EXISTS `$bmcsTable`");
		$wpdb->query( "CREATE TABLE  `$bmcsTable` (
					  `uid` varchar(255) NOT NULL,
					  `post_id` int(11) NOT NULL,
					  `comment_id` int(11) NOT NULL,
					  `vote` tinyint(1) NOT NULL,
					  PRIMARY KEY (`uid`,`post_id`)
					) DEFAULT CHARSET=utf8"
		);

		add_option( 'bmcs_db_version', $myDbVersion );
	}
}

//instantiate the class
if (class_exists('WpBmcsVote')) {
	if (get_bloginfo('version') >= "2.5" && !$WpBmcsVote) {
		$WpBmcsVote = new WpBmcsVote();
	}
}

