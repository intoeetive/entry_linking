<?php

/*
=====================================================
 Entry Linking - by Yuriy Salimovskiy
-----------------------------------------------------
 http://www.intoeetive.com/
-----------------------------------------------------
 Copyright (c) 2010-2014 Yuriy Salimovskiy
=====================================================
 This software is based upon and derived from
 ExpressionEngine software protected under
 copyright dated 2004 - 2010. Please see
 http://expressionengine.com/docs/license.html
=====================================================
 File: pi.entry_linking.php
-----------------------------------------------------
 Purpose: Links to previous/next entry using custom sorting order
=====================================================
*/

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
  'pi_name' => 'Entry linking',
  'pi_version' =>'2.4',
  'pi_author' =>'Yuri Salimovskiy',
  'pi_author_url' => 'http://www.intoeetive.com/',
  'pi_description' => 'Next/prev entry linking with custom ordering',
  'pi_usage' => Entry_linking::usage()
  );

class Entry_linking {

  function Entry_linking()
  {
    
	$this->EE =& get_instance(); 
    
    $TMPL = $this->EE->TMPL;
    $DB = $this->EE->db;
    $PREFS = $this->EE->config;
    $FNS = $this->EE->functions;
	
	$entry_id = is_numeric($TMPL->fetch_param('entry_id')) ? $TMPL->fetch_param('entry_id') : '0';
	if ($entry_id<=0) return;
    $link = ($TMPL->fetch_param('link')) ? $TMPL->fetch_param('link') : 'next';
    $channel_arr = ($TMPL->fetch_param('channel')) ? explode("|", $TMPL->fetch_param('channel')) : array();
    $category_arr = ($TMPL->fetch_param('category')) ? explode("|", $TMPL->fetch_param('category')) : array();
    $site_id = $PREFS->item('site_id');
    $orderby = ($TMPL->fetch_param('orderby')) ? $TMPL->fetch_param('orderby') : 'entry_date';
    $orderby = ($orderby=='date') ? 'entry_date' : $orderby;
    $sort = ($TMPL->fetch_param('sort')) ? $TMPL->fetch_param('sort') : 'ASC';
    $status_arr = ($TMPL->fetch_param('status')) ? explode("|", $TMPL->fetch_param('status')) : array('open');
    $mode = ($TMPL->fetch_param('mode')) ? $TMPL->fetch_param('mode') : 'short';
    $no_results = ($TMPL->fetch_param('no_results')) ? $TMPL->fetch_param('no_results') : 'cycle';
    /*if ($TMPL->fetch_param('no_results_link_next')!='')
    {
        if (strpos($TMPL->fetch_param('no_results_link_next'), 'http')===0)
        {
            $no_results_link_next = $TMPL->fetch_param('no_results_link_next');
        }
        else
        {
            $no_results_link_next = $FNS->create_url($TMPL->fetch_param('no_results_link_next'));
        }
    }
    else
    {
        $no_results_link_next = '';
    }
    if ($TMPL->fetch_param('no_results_link_prev')!='')
    {
        if (strpos($TMPL->fetch_param('no_results_link_prev'), 'http')===0)
        {
            $no_results_link_prev = $TMPL->fetch_param('no_results_link_prev');
        }
        else
        {
            $no_results_link_prev = $FNS->create_url($TMPL->fetch_param('no_results_link_prev'));
        }
    }
    else
    {
        $no_results_link_prev = '';
    }    */
    
    if ($orderby=='rating') {$orderby='rating_avg';}
    
    $channel_titles_fields = array('entry_id', 'site_id', 'channel_id', 'author_id', 'pentry_id', 'forum_topic_id', 'ip_address', 'title', 'url_title', 'status','versioning_enabled', 'view_count_one', 'view_count_two', 'view_count_three', 'view_count_four', 'allow_comments', 'allow_trackbacks', 'sticky', 'entry_date', 'year', 'month', 'day', 'expiration_date', 'comment_expiration_date', 'edit_date', 'recent_comment_date', 'comment_total', 'trackback_total', 'sent_trackbacks', 'recent_trackback_date', 'rating_avg');
    $join_needed = in_array($orderby, $channel_titles_fields) ? FALSE : TRUE;

    if ($join_needed===TRUE)
    {
      $get_field_id = $DB->query("SELECT field_id FROM exp_channel_fields WHERE field_name='$orderby' AND site_id=$site_id");
      if ($get_field_id->num_rows>0)
      {
        $orderby = 'field_id_'.$get_field_id->row('field_id');
      }
      else
      {
        $orderby = 'entry_date';
      }
    }
    
    $query = "SELECT DISTINCT t.entry_id FROM exp_channel_titles AS t";
    if ($join_needed===TRUE)
    {
      $query .= " LEFT JOIN exp_channel_data AS d ON d.entry_id=t.entry_id ";
    }
    if (!empty($channel_arr))
    {
      foreach ($channel_arr as $i=>$channel)
        {
          $query .= " LEFT JOIN exp_channels AS w$i ON w$i.channel_id=t.channel_id  ";
        }
    }
    if (!empty($category_arr))
    {
        foreach ($category_arr as $i=>$category)
        {
          $query .= " LEFT JOIN exp_category_posts AS cp$i ON cp$i.entry_id=t.entry_id  ";
          $query .= " LEFT JOIN exp_categories AS c$i ON c$i.cat_id=cp$i.cat_id ";
        }
    }

    $query .= " WHERE t.site_id='$site_id' ";
    if (!empty($channel_arr))
    {
      $query .= " AND (";
      foreach ($channel_arr as $i=>$channel)
        {
          $query .= " w$i.channel_name='$channel' OR ";
        }
      $query = trim($query, "OR "). " ) ";
    }
    if (!empty($category_arr))
    {
      $query .= " AND (";
      foreach ($category_arr as $i=>$category)
        {
          $query .= " c$i.cat_url_title='$category' OR ";
        }
      $query = trim($query, "OR "). " ) ";
    }
    $query .= " AND (";
    foreach ($status_arr as $status)
    {
      $query .= " t.status='$status' OR ";
    }
    $query = trim($query, "OR ");
    $query .= " ) ORDER BY $orderby $sort";
    if ($orderby != 'entry_date')
    {
        $query .= " , entry_date $sort";
    }
    //echo $query;
    $result = $DB->query($query);
    if ($result->num_rows()<=2) 
    {
        return $TMPL->no_results();    
    }
    
    $ids = array();
    foreach ($result->result_array() as $row)
    {
      array_push($ids, $row['entry_id']);
    }


    $pos = array_search($entry_id, $ids);

    $vars = array();
    $vars['no_next_link'] = FALSE;
    $vars['no_prev_link'] = FALSE;

    if ($link=='next')
    {
      $pos++;
      if ($pos==count($ids)) 
      {
        switch ($no_results)
        {
            case 'empty':
                return $TMPL->no_results;
                break;
            case 'cycle':
            default:
                $pos=0;
                break;
        }
        $vars['no_next_link'] = TRUE;
      }
    } else {
      $pos--;
      if ($pos==-1) 
      {
        switch ($no_results)
        {
            case 'empty':
                return $TMPL->no_results;
                break;
            case 'cycle':
            default:
                $pos=count($ids)-1;
                break;
        }
        $vars['no_prev_link'] = TRUE;
      }
    }
    
    
    $id = $ids[$pos];

    $tagdata = $TMPL->tagdata;
    $tagdata = $TMPL->swap_var_single('link_total', count($ids), $tagdata);
    $tagdata = $TMPL->swap_var_single('link_count', $pos+1, $tagdata);
    
    
    $query = $DB->query("SELECT * FROM exp_channel_titles WHERE entry_id=$id AND site_id=$site_id");
    foreach( $query->result_array() as $row )
    {
        foreach ( $row as $key => $val )
        {
            //$tagdata = $TMPL->swap_var_single('link_'.$key, $val, $tagdata);
            $vars['link_'.$key] = $val;
        }
    }
    
    if ($mode=='full')
    {
        $q = $DB->query("SELECT field_id, field_name FROM exp_channel_fields WHERE site_id=$site_id");
        $d = $DB->query("SELECT * FROM exp_channel_data WHERE entry_id=$id AND site_id=$site_id");
        foreach ($q->result_array() as $r)
        {
        	//$tagdata	= $TMPL->swap_var_single('link_'.$r['field_name'], $d->row('field_id_'.$r['field_id']), $tagdata);
            $vars['link_'.$r['field_name']] = $d->row('field_id_'.$r['field_id']);
        }
    }
    
    //$tagdata = $FNS->prep_conditionals($tagdata, $cond);
    $tagdata = $this->EE->TMPL->parse_variables_row($tagdata, $vars);
		
    $this->return_data = $tagdata;
    return $this->return_data;
  }
  

  
  // ----------------------------------------
  //  Plugin Usage
  // ----------------------------------------

  // This function describes how the plugin is used.
  //  Make sure and use output buffering

  function usage()
  {
	  ob_start(); 
	  ?>
	With this Plugin you can create next/previous entry links based on your own custom entries ordering:
	
{exp:entry_linking entry_id="1" link="next" channel="my_channel" orderby="my_custom_field" sort="ASC" status="open" mode="short" no_results="cycle"}
<a href="{path=site/{link_url_title}}">{link_title}</a>
{/exp:entry_linking} 

Parameters:
entry_id - the 'start' entry id to get next&prev links. REQUIRED.
link - type of link. Possible values are 'next' and 'prev'. Defaults to 'next'. 
channel - channel name to look up entries. Separate multiple channels with pipe |. If not specified, will look in all channels
category - category url title to look up entries. Separate multiple categories with pipe |. If not specified, will look in all categories
orderby - standard or custom field to biuld sorting. If not specified, defaults to entry_date.
sort - sorting order. ASC (default) or DESC.
status - status of entries to look up. Defaults to 'open'. Separate multiple values with pipe |.
no_results - behaviour in case there is no next or previous link. Possible values are 
        cycle - snow first entry for 'next' link, last entry for 'prev' link. This is default behaviour.
        empty - do not show the link if the are no results
mode - the returned data mode. Can be 'short' (default) - only standard entry fields returned (link_title, link_entry_id etc) or 'full' - all custom fields available as well. 

Variables:
All returned variables (field names) will be prefixed with 'link_', e.g. {link_entry_id}, {link_my_custom_field} etc.
All field names available within exp_channel_channels are available here (plus all custom fields if called in 'full' mode).
Additionally you have:
{link_total} - total number of entries in linking loop
{link_count} - the # of current entry in the linking loop

Conditionals:
{if no_next_link}...custom code or link ...{if:else}...regular code...{/if}
{if no_prev_link}...custom code or link ...{if:else}...regular code...{/if}


Note that plugin will not return any resulst if you have less that 3 entries.
	
	  <?php
	  $buffer = ob_get_contents();
		
	  ob_end_clean(); 
	
	  return $buffer;
  }
  // END

}
?>