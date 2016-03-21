<?php

if (version_compare("5", PHP_VERSION, ">"))
	require_once("./classes.php4.php");
else
	require_once("./classes.php5.php");
require_once('../../../wp-config.php');
require_once('../../upgrade-functions.php');

if (isset($_GET['step']))
	$step = $_GET['step'];
else
	$step = 0;
	
header( 'Content-Type: text/html; charset=utf-8' );
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title><?php _e('Nucleus to WordPress Conversion'); ?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<style media="screen" type="text/css">
	<!--
	html {
		background: #eee;
	}
	body {
		background: #fff;
		color: #000;
		font-family: Georgia, "Times New Roman", Times, serif;
		margin-left: 20%;
		margin-right: 20%;
		padding: .2em 2em;
	}

	h1 {
		color: #006;
		font-size: 18px;
		font-weight: lighter;
	}

	h2 {
		font-size: 16px;
	}

	p, li, dt {
		line-height: 140%;
		padding-bottom: 2px;
	}

	ul, ol {
		padding: 5px 5px 5px 20px;
	}
	#logo {
		margin-bottom: 2em;
	}
	.step a, .step input {
		font-size: 2em;
	}
	td input {
		font-size: 1.5em;
	}
	.step, th {
		text-align: right;
	}
	#footer {
		text-align: center; 
		border-top: 1px solid #ccc; 
		padding-top: 1em; 
		font-style: italic;
	}
	-->
	</style>
</head>
<body>
<h1 id="logo"><img alt="WordPress" src="../../images/wordpress-logo.png" /></h1>
<?php
// Let's check to make sure WP is installed.
if ( !is_blog_installed() ) die('<h1>'.__('Not yet installed').'</h1><p>'.__('You appear to have not yet installed WordPress. Please <a href="../install.php">install Wordpress</a> first.').'</p></body></html>');

switch($step) {

	// Opening words
	case 0:
?>
<h1><?php _e('A Word of Caution'); ?></h1>
<p><?php _e("Please note that you should be running this on a freshly set-up WordPress blog with no items or comments. You can, however, already have users set up in WordPress, or allow this script to import your Nucleus members into WordPress."); ?></p>
<p><?php _e("This script will <em>not</em> attempt to import your Nucleus blog settings. Only members, categories, items and comments will be imported. If you have NP_TrackBack and/or NP_Blogroll installed in Nucleus, this script will attempt to import from those plugins as well."); ?></p>
<form id="setup" method="post" action="?step=1">
	<h2 class="step">
		<input type="submit" name="Submit" value="<?php _e('Locate your Nucleus config file &raquo;'); ?>" />
	</h2>
</form>

<?php
		break;
		
	// Locate config.php
	case 1:
		$path = realpath('../../../../') . '/';
?>
<h1><?php _e('First Step'); ?></h1>
<p><?php _e('First, let\'s collect some information about your Nucleus setup.'); ?></p>
<form id="setup" method="post" action="?step=2">
<table width="100%">
<tr>

<th width="33%"><?php _e('Path to Nucleus config.php:'); ?></th>
<td><input name="config_path" type="text" id="config_path" value="<?php _e($path); ?>" size="25" /></td>
</tr>
<tr>
<th><?php __('Hint:'); ?></th>
<td><p><?php _e('You can find this information in your Nucleus admin panel, under the Global Settings section in the field labeled "Nucleus Directories".'); ?></p></td>
</tr>
</table>
<h2 class="step">
<input type="submit" name="Submit" value="<?php _e('Choose a blog to import &raquo;'); ?>" />
</h2>
</form>

<?php
		break;
		
	// Select Nucleus blog
	case 2:		
		$settings["config_path"] = realpath(stripslashes($_POST['config_path'])) . '/config.php';
		if (realpath($settings["config_path"]))
		{
			$config = file($settings["config_path"]);
			foreach ($config as $line)
			{
				$line = trim($line);
				$tokens = explode('=', $line);
				$tokens[0] = trim($tokens[0], " $");
				$tokens[1] = trim($tokens[1]," ;'\"");
				switch ($tokens[0])
				{
					case "MYSQL_HOST":
					case "MYSQL_USER":
					case "MYSQL_PASSWORD":
					case "MYSQL_DATABASE":
					case "MYSQL_PREFIX":
						$nucleus_config[$tokens[0]] = $tokens[1];
						break;
					default:
				}
			}
		}
		else
			die (__("<strong>ERROR</strong>: config.php not found at " . dirname($settings["config_path"])));

		$nucleus_db = new Database($nucleus_config["MYSQL_HOST"], $nucleus_config["MYSQL_USER"], $nucleus_config["MYSQL_PASSWORD"], $nucleus_config["MYSQL_DATABASE"], $nucleus_config["MYSQL_PREFIX"]);
		$wordpress_db = new Database(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, $table_prefix);
		$settings["nucleus_config"] = $nucleus_config;
		
		// Check Nucleus and Wordpress database versions
		$query = sprintf("SELECT `value` FROM `%s` WHERE `name`='DatabaseVersion'", $nucleus_db->prefix("nucleus_config"));
		$settings["nucleus_version"] = $nucleus_db->getScalar($query);
		
		$query = sprintf("SELECT `option_value` FROM `%s` WHERE `option_name`='db_version'", $wordpress_db->prefix("options"));
		$settings["wordpress_db_version"] = $wordpress_db->getScalar($query);
		
		if ($settings["nucleus_version"] < 322 || $settings["wordpress_db_version"] < 3582)
			_e("<p>This import script has only been tested with Nucleus 3.22/3.23/3.3CVS and WordPress 2.1.x (nightly builds). Attempting to import from any other Nucleus version or into any other WordPress version may result in unexpected errors. Do so at your own risk. Regardless of your software versions, please back up your database before doing this import! <strong>I take no responsibility for any data loss incurred by the use of this script</strong>.</p>");

		// Get list of available Nucleus blogs
		$query = "SELECT * FROM `" . $nucleus_db->prefix("nucleus_blog") . "`";
		$blogs = $nucleus_db->getResults($query);
?>
<h1><?php _e('Second Step'); ?></h1>
<p><?php _e('Please select the Nucleus blog you want to import entries and comments from.'); ?></p>
<form id="setup" method="post" action="?step=3">
<table width="100%">
<?php
		$i = 1;
		foreach ($blogs as $blog)
		{
?>
<tr>
<th><input type="radio" name="nucleus_blog" id="nucleus_blog_<?php _e($blog["bnumber"]); ?>" value="<?php _e($blog["bnumber"]); ?>" <?php if ($i++ == 1) _e('checked="checked"'); ?>/></th>
<td><label for="nucleus_blog_<?php _e($blog["bnumber"]); ?>"><?php _e($blog["bname"] . ' (' . $blog["bshortname"] . ')'); ?></label></td>
</tr>
<?php
		}
?>
</table>
<h2 class="step">
<input type="hidden" name="settings" value="<?php _e(htmlentities(serialize($settings))); ?>" />
<input type="submit" name="Submit" value="<?php _e('Import authors &raquo;'); ?>" />
</h2>
</form>
<?php
		break;
	
	// Map authors
	case 3:
		// Restore settings and database connections
		$settings = unserialize(html_entity_decode(stripslashes($_POST["settings"])));
		$nucleus_config = $settings["nucleus_config"];
		$nucleus_db = new Database($nucleus_config["MYSQL_HOST"], $nucleus_config["MYSQL_USER"], $nucleus_config["MYSQL_PASSWORD"], $nucleus_config["MYSQL_DATABASE"], $nucleus_config["MYSQL_PREFIX"]);
		$wordpress_db = new Database(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, $table_prefix);
		
		// Validate and add new settings
		$settings["nucleus_blog"] = stripslashes($_POST['nucleus_blog']);
		if ($settings["nucleus_blog"] <= 0)
			die (__("<strong>ERROR</strong>: Please select a blog to import."));
		
		// Get Nucleus authors
		$query = "SELECT * FROM `" . $nucleus_db->prefix("nucleus_member") . "`";
		$nucleus_users = $nucleus_db->getResults($query);
		// Get Wordpress authors
		$query = "SELECT * FROM `" . $wordpress_db->prefix("users") . "`";
		$wordpress_users = $wordpress_db->getResults($query);
?>
<h1><?php _e('Third Step'); ?></h1>
<p><?php _e('Please choose the WordPress user each Nucleus user should be mapped onto. You can also choose to import the Nucleus user into a new WordPress user (if you do this, the new WordPress user will be given the same login and password as the imported Nucleus user.'); ?></p>
<p><?php _e('<em>If you create a new WordPress user from a Nucleus user with the same login name as an existing WordPress user, this will overwrite the existing user. This will potentially screw things up and you are strongly discouraged from doing so. Again, do so at your own risk.</em>'); ?></p>
<form id="setup" method="post" action="?step=4">
<table width="100%">
<?php
		foreach ($nucleus_users as $nuser)
		{
?>
<tr>
<th><?php _e($nuser["mname"] . ' (' . $nuser["mrealname"] . ')'); ?></th>
<td>
<select name="nucleus_user_<?php _e($nuser["mnumber"]); ?>" id="nucleus_user_<?php _e($nuser["mnumber"]); ?>">
<?php
			foreach ($wordpress_users as $wpuser)
			{
				$usermeta = $wordpress_db->getResults("SELECT * FROM `" . $wordpress_db->prefix("usermeta") . "` WHERE `user_id`=" . $wpuser["ID"]);
				$realname = '';
				foreach ($usermeta as $meta)
				{
					if ($meta["meta_key"] == "first_name")
						$realname = $meta["meta_value"];
				}
				foreach ($usermeta as $meta)
				{
					if ($meta["meta_key"] == "last_name")
						$realname .= ' ' . $meta["meta_value"];
				}
?>
<option value="<?php _e($wpuser["ID"]); ?>"><?php _e($wpuser["display_name"]) ?> (<?php _e($realname); ?>)</option>
<?php
			}
?>
<option value="inherit">New User (inherit role from Nucleus)</option>
<option value="administrator">New Administrator</option>
<option value="editor">New Editor</option>
<option value="author">New Author</option>
<option value="contributor">New Contributor</option>
<option value="subscriber">New Subscriber</option>
</select>
</tr>
<?
		}
?>
</table>
<h2 class="step">
<input type="hidden" name="settings" value="<?php _e(htmlentities(serialize($settings))); ?>" />
<input type="submit" name="Submit" value="<?php _e('Import from Nucleus &raquo;'); ?>" />
</h2>
</form>

<?php
		break;
		
	// Import categories
	case 4:
		// Restore settings and database connections
		$settings = unserialize(html_entity_decode(stripslashes($_POST["settings"])));
		$nucleus_config = $settings["nucleus_config"];
		$nucleus_db = new Database($nucleus_config["MYSQL_HOST"], $nucleus_config["MYSQL_USER"], $nucleus_config["MYSQL_PASSWORD"], $nucleus_config["MYSQL_DATABASE"], $nucleus_config["MYSQL_PREFIX"]);
		$wordpress_db = new Database(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, $table_prefix);
?>
<h1><?php _e('Fourth Step'); ?></h1>
<p><?php _e('Okay, here comes the heavy work. Don\'t touch your browser window while this goes on.'); ?></php>
<blockquote><pre>
<?php
		flush();
		// First let's import the Nucleus authors
		// Get Nucleus authors
		_e("<p>Adding users to the database...<br />");
		flush();
				
		$query = sprintf("SELECT * FROM `%s`", $nucleus_db->prefix("nucleus_member"));
		$nucleus_users = $nucleus_db->getResults($query);
		$new_users = 0;
		foreach ($nucleus_users as $nuser)
		{
			$nid = $nuser["mnumber"];
			$user_map[$nid] = stripslashes($_POST['nucleus_user_' . $nid]);

			if (!is_numeric($user_map[$nid]))
			{
				$new_users++;
				if ($user_map[$nid] == "inherit")
				{
					if ($nuser["madmin"] == 1)
						$role = "administrator";
					else
					{
						$query = sprintf("SELECT * FROM `%s` WHERE `tmember`=%s AND `tblog`=%s", $nucleus_db->prefix("nucleus_team"), $nid, $settings["nucleus_blog"]);
						$nucleus_team = $nucleus_db->getResults($query);
						if (count($nucleus_team) > 0)
						{
							if ($nucleus_team[0]["tadmin"] == 1)
								$role = "editor";
							else
								$role = "author";
						}
						else
							$role = "subscriber";
					}
				}
				else
				{
					$role = $user_map[$nid];
				}
				
				$user = new User ($nuser["mname"], $nuser["mrealname"], $nuser["mpassword"], $nuser["memail"], $nuser["murl"], $nuser["mnotes"], $role);
				$user_map[$nid] = $user->writeToWp($wordpress_db);
			}
			
		}
		$settings["user_map"] = $user_map;
		_e(sprintf("&nbsp;&nbsp;&nbsp;&nbsp;%d new users added.</p>", $new_users));
		flush();
			
		// Okay, now that's done, time for the heavy work.
		// Import the categories
		_e("<p>Importing categories...<br />");
		flush();
		// Get Nucleus categories
		$query = sprintf("SELECT * FROM `%s`", $nucleus_db->prefix("nucleus_category"));
		$nucleus_categories = $nucleus_db->getResults($query);
		
		foreach ($nucleus_categories as $ncat)
		{
			$wpcat = new Category ($ncat["cname"], $ncat["cdesc"]);
			$categories[$ncat["catid"]] = $wpcat->writeToWp($wordpress_db);
		}
		
		_e(sprintf("&nbsp;&nbsp;&nbsp;&nbsp;%d categories found.</p>", count($categories)));
		flush();
		
		// Get Nucleus blog offset
		_e("<p>Getting your Nucleus blog's time offset...<br />");
		$query = sprintf("SELECT `btimeoffset` FROM `%s` WHERE `bnumber`=%s", $nucleus_db->prefix("nucleus_blog"), $settings["nucleus_blog"]);
		$blog_offset = $nucleus_db->getScalar($query);
		_e(sprintf("&nbsp;&nbsp;&nbsp;&nbsp;Time offset of %s hours found.</p>", $blog_offset));
		flush();
		
		// Get Nucleus items
		_e("<p>Importing Nucleus items...<br />");
		flush();
		$query = sprintf("SELECT * FROM `%s` WHERE `iblog`=%s ORDER BY `idraft`, `itime`", $nucleus_db->prefix("nucleus_item"), $settings["nucleus_blog"]);
		$nucleus_items = $nucleus_db->query($query);
				
		while ($nitem = mysql_fetch_assoc($nucleus_items))
		{
			$wppost = new Post($nitem["ititle"], $nitem["iurltitle"], $nitem["ibody"], $nitem["imore"], $user_map[$nitem["iauthor"]], $nitem["itime"], $blog_offset, $nitem["iclosed"], $nitem["idraft"], $categories[$nitem["icat"]]);			
			$posts[$nitem["inumber"]] = $wppost->writeToWp($wordpress_db);
		}
		
		// Update post counts in categories
		foreach ($categories as $ncat_id => $wpcat_id)
		{
			$query = sprintf("SELECT COUNT(*) FROM `%s` WHERE `category_id`=%d", $wordpress_db->prefix("post2cat"), $wpcat_id);
			$post_count = $wordpress_db->getScalar($query);
			$query = sprintf("UPDATE `%s` SET `category_count`=%d WHERE `cat_ID`=%d", $wordpress_db->prefix("categories"), $post_count, $wpcat_id);
			$wordpress_db->query($query);
		}
		
		_e(sprintf("&nbsp;&nbsp;&nbsp;&nbsp;%d items found.</p>", count($posts)));
		flush();
		
		// Get Nucleus comments
		_e("<p>Importing Nucleus comments...<br />");
		flush();
		
		$query = sprintf("SELECT * FROM `%s` WHERE `cblog`=%s ORDER BY `ctime`", $nucleus_db->prefix("nucleus_comment"), $settings["nucleus_blog"]);
		$nucleus_comments = $nucleus_db->query($query);
		
		while ($ncomment = mysql_fetch_assoc($nucleus_comments))
		{
			// Check to see if we need to get author information
			if ($ncomment["cmember"] != 0)
			{
				$query = sprintf("SELECT * FROM `%s` WHERE `mnumber`=%d", $nucleus_db->prefix("nucleus_member"), $ncomment["cmember"]);
				$member = $nucleus_db->getResults($query);
				$ncomment["cuser"] = $member[0]["mname"];
				$ncomment["cmail"] = $member[0]["murl"];
				$ncomment["cemail"] = $member[0]["memail"];
			}
			$wpcomment = new Comment($ncomment["cbody"], $ncomment["cuser"], $ncomment["cmail"], $ncomment["cemail"], $user_map[$ncomment["cmember"]], $posts[$ncomment["citem"]], $ncomment["ctime"], $blog_offset, $ncomment["cip"]);
			$comments[$ncomment["cnumber"]] = $wpcomment->writeToWp($wordpress_db);
		}
		
		// Update comment count in posts
		foreach ($posts as $npost_id => $wppost_id)
		{
			$query = sprintf("SELECT COUNT(*) FROM `%s` WHERE `comment_post_ID`=%d", $wordpress_db->prefix("comments"), $wppost_id);
			$comment_count = $wordpress_db->getScalar($query);
			$query = sprintf("UPDATE `%s` SET `comment_count`=%d WHERE `ID`=%d", $wordpress_db->prefix("posts"), $comment_count, $wppost_id);
			$wordpress_db->query($query);
		}
		
		_e(sprintf("&nbsp;&nbsp;&nbsp;&nbsp;%d comments found.</p>", count($comments)));
		flush();
		
		// -- This section depends on the presence of specific Nucleus plugins --//
		$query = sprintf("SELECT `pfile` FROM `%s`", $nucleus_db->prefix("nucleus_plugin"));		
		$nucleus_plugins = array();
		$result = $nucleus_db->query($query);
		while ($plugin = mysql_fetch_assoc($result))
		{
			array_push($nucleus_plugins, $plugin["pfile"]);
		}
	
		// Get Nucleus trackbacks
		// First check for NP_TrackBack
		if (in_array("NP_TrackBack", $nucleus_plugins))
		{
			_e("<p>NP_TrackBack detected. Getting your Nucleus trackbacks...<br />");
			flush();
			$query = sprintf("SELECT `inumber` FROM `%s` WHERE `iblog`=%d", $nucleus_db->prefix("nucleus_item"), $settings["nucleus_blog"]);
			$item_numbers = $nucleus_db->query($query);
			
			$inumbers = array();
			while ($inum = mysql_fetch_assoc($item_numbers))
			{
				array_push($inumbers, $inum["inumber"]);
			}
			
			$query = sprintf("SELECT * FROM `%s` WHERE `tb_id` IN (%s)", $nucleus_db->prefix("nucleus_plugin_tb"), implode(",", $inumbers));
			$nucleus_trackbacks = $nucleus_db->query($query);
			
			while ($ntb = mysql_fetch_assoc($nucleus_trackbacks))
			{
				$wptb = new Comment($ntb["excerpt"], $ntb["blog_name"], $ntb["url"], '', 0, $posts[$ntb["tb_id"]], $ntb["timestamp"], $blog_offset, '', "trackback");
				$trackbacks[$ntb["id"]] = $wptb->writeToWp($wordpress_db);
			}
			
			// Update comment count in posts
			foreach ($posts as $npost_id => $wppost_id)
			{
				$query = sprintf("SELECT COUNT(*) FROM `%s` WHERE `comment_post_ID`=%d", $wordpress_db->prefix("comments"), $wppost_id);
				$comment_count = $wordpress_db->getScalar($query);
				$query = sprintf("UPDATE `%s` SET `comment_count`=%d WHERE `ID`=%d", $wordpress_db->prefix("posts"), $comment_count, $wppost_id);
				$wordpress_db->query($query);
			}
			
			_e(sprintf("&nbsp;&nbsp;&nbsp;&nbsp;%d trackbacks found.</p>", count($trackbacks)));
			flush();
		}
		
		// Get Nucleus blogroll links
		// First check for NP_BlogRoll
		if (in_array("NP_Blogroll", $nucleus_plugins))
		{
			_e("<p>NP_Blogroll detected. Getting your Nucleus blogroll links...<br />");	
			flush();
			
			// Import Blogroll groups into Wordpress categories				
			$query = sprintf("SELECT * FROM `%s`", $nucleus_db->prefix("nucleus_plug_blogroll_groups"));
			$nucleus_blogroll_groups = $nucleus_db->query($query);
			$current_user = 0;
			$current_user_category = 0;
			$query = sprintf("SELECT `cat_ID` FROM `%s` WHERE `cat_name`='Blogroll'", $wordpress_db->prefix("categories"));
			$parent = $wordpress_db->getScalar($query);
			
			while ($nbgroup = mysql_fetch_assoc($nucleus_blogroll_groups))
			{
				// Create a new category for each Nucleus member who has blogroll links
				if ($current_user != $user_map[$nbgroup["owner"]])
				{
					$current_user = $user_map[$nbgroup["owner"]];
					$query = sprintf("SELECT `user_nicename` FROM `%s` WHERE `ID`=%d", $wordpress_db->prefix("users"), $current_user);
					$current_user_name = $wordpress_db->getScalar($query);
					$group = new Category ($current_user_name, $current_user_name . "'s bookmarks", $parent);
					$current_user_category = $group->writeToWp($wordpress_db);
				}
					
				$group = new Category($nbgroup["name"], $nbgroup["desc"], $current_user_category);
				$blogroll_groups[$nbgroup["id"]] = $group->writeToWp($wordpress_db);				
			}				
			
			// Now import the links
			$query = sprintf("SELECT * FROM `%s`", $nucleus_db->prefix("nucleus_plug_blogroll_links"));
			$nucleus_blogroll_links = $nucleus_db->query($query);
			
			while ($nblink = mysql_fetch_assoc($nucleus_blogroll_links))
			{
				$link = new Link ($nblink["owner"], $blogroll_groups[$nblink["group"]], $nblink["url"], $nblink["text"], $nblink["title"]);
				$blogroll_links[$nblink["id"]] = $link->writeToWp($wordpress_db);
			}
			
			// Update link count in categories
			$query = sprintf("SELECT * FROM `%s`", $wordpress_db->prefix("categories"));
			$wpcategories = $wordpress_db->query($query);
			
			while ($wpcat = mysql_fetch_assoc($wpcategories))
			{
				$query = sprintf("SELECT COUNT(*) FROM `%s` WHERE `cat_ID`=%d", $wordpress_db->prefix("categories"), $wpcat["cat_ID"]);
				$link_count = $wordpress_db->getScalar($query);
				$query = sprintf("UPDATE `%s` SET `link_count`=%d WHERE `cat_ID`=%d", $wordpress_db->prefix("categories"), $link_count, $wpcat["cat_ID"]);	
				$wordpress_db->query($query);
			}
			
			_e(sprintf("&nbsp;&nbsp;&nbsp;&nbsp;%d links in %d groups found.</p>", count($blogroll_links), count($blogroll_groups)));
			flush();
		}
		
		
		// Get Nucleus item tags
		// First check for NP_Tags
		if (in_array("NP_Tags", $nucleus_plugins))
		{
			// Next check for Ultimate Tag Warrior
			if ($wordpress_db->tableExists("tags"))
			{
				_e("<p>NP_Tags detected. Getting your Nucleus tags...<br />");	
				flush();
				
				$query = sprintf("SELECT * FROM `%s`", $nucleus_db->prefix("nucleus_plug_tags"));
				$result = $nucleus_db->query($query);
				$tag_IDs = array();
				
				while ($item = mysql_fetch_assoc($result))
				{
					// Get tags for current item
					$tok = strtok($item["tags"], "/,");
					$itemtags = array();
					while ($tok !== false)
					{
						array_push($itemtags, $tok);
						$tok = strtok("/,");
					}
					
					// Add post 2 tags info
					foreach ($itemtags as $tag)
					{
						// Check if tags are already defined, if not, add them to the tags db
						if (!array_key_exists($tag, $tag_IDs))
						{
							//Insert tag
							$query = sprintf("INSERT INTO `%s` (`tag`) VALUES ('%s')", $wordpress_db->prefix("tags"), addslashes($tag));
							$wordpress_db->query($query);
							//Get ID
							$query = sprintf("SELECT `tag_ID` FROM `%s` WHERE `tag`='%s'", $wordpress_db->prefix("tags"), addslashes($tag));
							$tag_IDs[$tag] = $wordpress_db->getScalar($query);
						}
						
						$query = sprintf("INSERT INTO `%s` (`tag_id`, `post_id`) VALUES ('%d', '%d')", $wordpress_db->prefix("post2tag"), $tag_IDs[$tag], $posts[$item["item_id"]]);
						$wordpress_db->query($query);
					}
					
				}
				
				_e(sprintf("&nbsp;&nbsp;&nbsp;&nbsp;%d unique tags added.</p>", count($tag_IDs)));
			flush();
			}
		}
		
		
?>
</pre></blockquote>
<p><?php _e("There, all done! Have fun with WordPress!"); ?></p>
<?php
		break;
}
?>
<p id="footer"><?php _e('<a href="http://wordpress.org/">WordPress</a>, personal publishing platform.'); ?></p>
</body>
</html>

<?php

// Miscellaneous functions

function countLinks ($database, $cat_ID)
{
	// Get children of current category
	$query = sprintf("SELECT * FROM `%s` WHERE `category_parent`=%d", $database->prefix("categories"), $cat_ID);
	$children = $database->getResults($query);
	if (count($children) == 0) // base case
	{
		$query = sprintf("SELECT COUNT(*) FROM `%s` WHERE `category_id`=%d", $database->prefix("link2cat"), $cat_ID);
		return $database->getScalar($query);
	}
	else
	{
		$count = 0;
		foreach ($children as $child)
		{
			$count += countLinks($database, $child["cat_ID"]);
		}
		return $count;
	}	
}
?>
