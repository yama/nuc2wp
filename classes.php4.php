<?

class Database
{
	
	var $host;
	var $user;
	var $pass;
	var $schema;
	var $prefix;
	var $conn;	

	function Database ($host, $user, $pass, $schema, $prefix='')
	{
		$this->host = $host;
		$this->user = $user;
		$this->pass = $pass;
		$this->schema = $schema;
		$this->prefix = $prefix;
		
		$this->conn = mysql_connect ($host, $user, $pass, true);
		mysql_select_db($schema, $this->conn);
	}
	
	function prefix ($tableName)
	{
		return ($this->prefix . $tableName);
	}
	
	function query ($query)
	{
		$result = mysql_query($query, $this->conn);
		if (!$result)
			die (__("<strong>ERROR</strong>: MySQL Error: " . mysql_error($this->conn) . "<br />SQL query: " . $query));
		
		return $result;
	}
	
	/* Returns a nested array of all the rows for a database query
   */
	function getResults ($query)
	{
		$result = $this->query($query);
		$output = array();
		
		while ($row = mysql_fetch_assoc($result))
		{
			array_push($output, $row);
		}
		
		return $output;
	}
	
	/* Returns a single value for a database query
   * If the query yields multiple rows, return the value from the first field of the first row
   */
	function getScalar ($query)
	{
		$result = $this->query($query);
		
		$output = mysql_fetch_row($result);
		return $output[0];
	}
	
	function tableExists ($table)
	{
		$query = sprintf("SHOW TABLES FROM `%s` LIKE '%s'", $this->schema, $this->prefix($table));
		$result = $this->query($query);
		
		if (mysql_num_rows($result) == 1)
			return true;
		else
			return false;
	}
	
} // Database

class User
{
	var $user_id;
	var $user_login;
	var $user_pass;
	var $user_nicename;
	var $user_email;
	var $user_url;
	var $user_registered;
	var $user_activation_key;
	var $user_status;
	var $display_name;
	var $meta;
		
	function User ($name, $realname, $password, $email, $url, $note, $role)
	{
		$this->user_login = $name;
		$this->user_pass = $password;
		$this->user_nicename = $name;
		$this->user_email = $email;
		$this->user_url = $url;
		$this->user_registered = date("Y-m-d H:i:s");
		$this->user_activation_key = '';
		$this->user_status = '0';
		$this->display_name = $name;
		
		$this->meta["nickname"] = $name;
		$realname = explode(' ', $realname, 2);
		if ($realname[0] != "")
			$this->meta["first_name"] = $realname[0];
		if ($realname[1] != "")
			$this->meta["last_name"] = $realname[1];
		if ($note != "")
			$this->meta["description"] = $note;
		
		$this->meta["capabilities"] = serialize(array($role => 1));
		switch ($role)
		{
			case "administrator":
				$this->meta["user_level"] = 10;
				break;
			case "editor":
				$this->meta["user_level"] = 7;
				break;
			case "author":
				$this->meta["user_level"] = 2;
				break;
			case "contributor":
				$this->meta["user_level"] = 1;
				break;
			case "subscriber":
				$this->meta["user_level"] = 0;
				break;
		}		
	}
	
	function writeToWp ($database)
	{
		// Insert the new user
		$query = sprintf("INSERT INTO `%s` (`user_login`, `user_pass`, `user_nicename`, `user_email`, `user_url`, `user_registered`, `user_activation_key`, `user_status`, `display_name`) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')", $database->prefix("users"), addslashes($this->user_login), addslashes($this->user_pass), addslashes($this->user_nicename), addslashes($this->user_email), addslashes($this->user_url), addslashes($this->user_registered), addslashes($this->user_activation_key), addslashes($this->user_status), addslashes($this->display_name));
		$database->query($query);
		
		// Get the user's ID
		$query = sprintf("SELECT `ID` FROM `%s` WHERE `user_login`='%s'", $database->prefix("users"), $this->user_login);
		$this->user_id = $database->getScalar($query);
		
		// Insert the user's meta information
		foreach ($this->meta as $key => $value)
		{
			switch ($key)
			{
				case "capabilities":
				case "user_level":
					$key = $database->prefix($key);
					break;
				default:
					break;
			}
			
			$query = sprintf("INSERT INTO `%s` (`user_id`, `meta_key`, `meta_value`) VALUES ('%s', '%s', '%s')", $database->prefix("usermeta"), $this->user_id, addslashes($key), addslashes($value));
			$database->query($query);
		}
		
		// Return the new user's ID
		return $this->user_id;		
	}
} // Author

class Category
{
	var $cat_ID;
	var $cat_name;
	var $category_nicename;
	var $category_description;
	var $category_parent;
	var $category_count;
	var $link_count;
	var $posts_private;
	var $links_private;
	
	var $posts;
	var $links;
	
	function Category ($name, $description, $parent=0)
	{
		$this->cat_name = $description;
		$this->category_nicename = strtolower($name);
		$this->category_description = '';
		$this->category_parent = $parent;
		$this->category_count = 0;
		$this->link_count = 0;
		$this->posts_private = 0;
		$this->links_private = 0;
		$this->posts = array();
		$this->links = array();
	}
	
	function writeToWp ($database)
	{
		// Insert the category
		$query = sprintf("INSERT INTO `%s` (`cat_name`, `category_nicename`, `category_description`, `category_parent`, `category_count`, `link_count`, `posts_private`, `links_private`) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')", $database->prefix("categories"), addslashes($this->cat_name), addslashes($this->category_nicename), addslashes($this->category_description), addslashes($this->category_parent), addslashes($this->category_count), addslashes($this->link_count), addslashes($this->posts_private), addslashes($this->links_private));
		$database->query($query);
		
		// Get the new category ID
		$query = sprintf("SELECT `cat_ID` FROM `%s` WHERE `category_nicename` = '%s'", $database->prefix("categories"), $this->category_nicename);
		$this->cat_ID = $database->getScalar($query);
		
		return $this->cat_ID;
	}
	
}

class Post
{
	var $ID;
	var $post_author;
	var $post_date;
	var $post_date_gmt;
	var $post_content;
	var $post_title;
	var $post_category;
	var $post_excerpt;
	var $post_status;
	var $comment_status;
	var $ping_status;
	var $post_password;
	var $post_name;
	var $to_ping;	
	var $pinged;
	var $post_modified;
	var $post_modified_gmt;
	var $post_content_filtered;
	var $post_parent;
	var $guid;
	var $menu_order;
	var $post_type;
	var $post_mime_type;
	var $comment_count;
	
	function Post ($title, $urltitle, $body, $more, $author, $time, $offset, $closed, $draft, $cat)
	{
		$this->post_author = $author;
		$this->post_date = $time;
		// Get the GMT time
		$timestamp = strtotime($time);
		$timestamp -= $offset * 60 * 60;
		$this->post_date_gmt = date("Y-m-d H:i:s", $timestamp);
		$this->post_content = $more == "" ? $body : $body . "\n<!--more-->\n" . $more;
		$this->post_title = $title;
		$this->post_category = $cat;
		$this->post_excerpt = '';
		$this->post_status = $draft == 0 ? "publish" : "draft";
		$this->comment_status = $closed == 0 ? "open" : "closed";
		$this->ping_status = "open";
		$this->post_password = '';
		$this->post_name = $urltitle == '' ? $this->sanitize_title_with_dashes($title) : $urltitle;
		$this->to_ping = '';
		$this->pinged = '';
		$this->post_modified = date("Y-m-d H:i:s");
		$this->post_modified_gmt = gmdate("Y-m-d H:i:s");
		$this->post_content_filtered = '';
		$this->post_parent = 0;
		$this->guid = '';
		$this->menu_order = 0;
		$this->post_type = "post";
		$this->post_mime_type = '';
		$this->comment_count = 0;
	}
	
	function writeToWp ($database)
	{
		// Insert post
		$query = sprintf("INSERT INTO `%s` (`post_author`, `post_date`, `post_date_gmt`, `post_content`, `post_title`, `post_category`, `post_excerpt`, `post_status`, `comment_status`, `ping_status`, `post_password`, `post_name`, `to_ping`, `pinged`, `post_modified`, `post_modified_gmt`, `post_content_filtered`, `post_parent`, `guid`, `menu_order`, `post_type`, `post_mime_type`, `comment_count`) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')", $database->prefix("posts"), addslashes($this->post_author), $this->post_date, $this->post_date_gmt, addslashes($this->post_content), 	addslashes($this->post_title), $this->post_category, addslashes($this->post_excerpt), $this->post_status, $this->comment_status, $this->ping_status, addslashes($this->post_password), addslashes($this->post_name), addslashes($this->to_ping), addslashes($this->pinged), $this->post_modified, $this->post_modified_gmt, $this->post_content_filtered, $this->post_parent, addslashes($this->guid), $this->menu_order, addslashes($this->post_type), $this->post_mime_type, $this->comment_count);
		$database->query($query);
		
		// Get the post's ID
		$query = sprintf("SELECT `ID` FROM `%s` WHERE `post_date_gmt`='%s' AND `post_author`=%s", $database->prefix("posts"), $this->post_date_gmt, $this->post_author);
		$this->ID = $database->getScalar($query);
		
		// Insert post2cat
		$query = sprintf("INSERT INTO `%s` (`post_id`, `category_id`) VALUES (%s, %s)", $database->prefix("post2cat"), $this->ID, $this->post_category);
		$database->query($query);
		
		return $this->ID;
	}
	
	function sanitize_title_with_dashes($title)
	{
		$title = strip_tags($title);
		// Preserve escaped octets.
		$title = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $title);
		// Remove percent signs that are not part of an octet.
		$title = str_replace('%', '', $title);
		// Restore octets.
		$title = preg_replace('|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $title);
	
		$title = remove_accents($title);
		if (seems_utf8($title)) {
			if (function_exists('mb_strtolower')) {
				$title = mb_strtolower($title, 'UTF-8');
			}
			$title = utf8_uri_encode($title);
		}
	
		$title = strtolower($title);
		$title = preg_replace('/&.+?;/', '', $title); // kill entities
		$title = preg_replace('/[^%a-z0-9 _-]/', '', $title);
		$title = preg_replace('/\s+/', '-', $title);
		$title = preg_replace('|-+|', '-', $title);
		$title = trim($title, '-');
	
		return $title;
	}
	
}

class Comment
{
	var $comment_ID;
	var $comment_post_ID;
	var $comment_author;
	var $comment_author_email;
	var $comment_author_url;
	var $comment_author_IP;
	var $comment_date;
	var $comment_date_gmt;
	var $comment_content;
	var $comment_karma;
	var $comment_approved;
	var $comment_agent;
	var $comment_type;
	var $comment_parent;
	var $user_id;
	
	function Comment ($body, $user, $mail, $email, $member, $item, $time, $offset, $ip, $type='comment')
	{
		$this->comment_post_ID = $item;
		$this->comment_author = $user;
		if (preg_match("/.+@.+/", $mail))
			$this->comment_author_email = $mail;
		else
		{
			$this->comment_author_email = $email;
			$this->comment_author_url = $mail;
		}
		$this->comment_author_IP = $ip;
		$this->comment_date = $time;
		// Get the GMT time
		$timestamp = strtotime($time);
		$timestamp -= $offset * 60 * 60;
		$this->comment_date_gmt = date("Y-m-d H:i:s", $timestamp);
		$this->comment_content = $body;
		$this->comment_karma = 0;
		$this->comment_approved = 1;
		$this->comment_agent = '';
		$this->comment_type = $type;
		$this->comment_parent = 0;
		$this->user_id = $member;
	}
	
	function writeToWp ($database)
	{
		$query = sprintf("INSERT INTO `%s` (`comment_post_ID`, `comment_author`, `comment_author_email`, `comment_author_url`, `comment_author_IP`, `comment_date`, `comment_date_gmt`, `comment_content`, `comment_karma`, `comment_approved`, `comment_agent`, `comment_type`, `comment_parent`, `user_id`) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')", $database->prefix("comments"), addslashes($this->comment_post_ID), addslashes($this->comment_author), addslashes($this->comment_author_email), addslashes($this->comment_author_url), addslashes($this->comment_author_IP), addslashes($this->comment_date), addslashes($this->comment_date_gmt), addslashes($this->comment_content), addslashes($this->comment_karma), addslashes($this->comment_approved), addslashes($this->comment_agent), addslashes($this->comment_type), addslashes($this->comment_parent), addslashes($this->user_id));
		$database->query($query);
	}	
}

class Link
{
	var $link_id;
	var $link_url;
	var $link_name;
	var $link_image;
	var $link_target;
	var $link_category;
	var $link_description;
	var $link_visible;
	var $link_owner;
	var $link_rating;
	var $link_updated;
	var $link_rel;
	var $link_notes;
	var $link_rss;
	var $cat_ID;
	
	function Link ($owner, $group, $url, $text, $title)
	{
		$this->link_url = $url;
		$this->link_name = $text;
		$this->link_image = '';
		$this->link_target = '';
		$this->link_description = $title;
		$this->link_category = 0;
		$this->link_visible = 'Y';
		$this->link_owner = $owner;
		$this->link_rating = 0;
		$this->link_updated = "0000-00-00 00:00:00";
		$this->link_rel = '';
		$this->link_notes = '';
		$this->link_rss = '';
		$this->cat_ID = $group;
	}
	
	function writeToWp ($database)
	{
		// Insert link
		$query = sprintf("INSERT INTO `%s` (`link_url`, `link_name`, `link_image`, `link_target`, `link_category`, `link_description`, `link_visible`, `link_owner`, `link_rating`, `link_updated`, `link_rel`, `link_notes`, `link_rss`) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')", $database->prefix("links"), addslashes($this->link_url), addslashes($this->link_name), addslashes($this->link_image), addslashes($this->link_target), addslashes($this->link_category), addslashes($this->link_description), addslashes($this->link_visible), addslashes($this->link_owner), addslashes($this->link_rating), addslashes($this->link_updated), addslashes($this->link_rel), addslashes($this->link_notes), addslashes($this->link_rss));
		$database->query($query);
		
		// Get link ID
		$query = sprintf("SELECT `link_id` FROM `%s` WHERE `link_url`='%s' AND `link_name`='%s' AND `link_owner`=%d", $database->prefix("links"), addslashes($this->link_url), addslashes($this->link_name), $this->link_owner);
		$this->link_id = $database->getScalar($query);
		
		// Insert link2cat
		$query = sprintf("INSERT INTO `%s` (`link_id`, `category_id`) VALUES (%d, %d)", $database->prefix("link2cat"), $this->link_id, $this->cat_ID);
		$database->query($query);
		
		return $this->link_id;
	}
}

?>
