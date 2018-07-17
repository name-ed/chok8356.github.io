<?php

if( !class_exists( 'HKB_Dynamic_Stats' )) {
    class HKB_Dynamic_Stats {
    
        function __construct() {
            add_action( 'wp_ajax_hkba_dynamic_stats' , array( $this, 'hkba_dynamic_stats' ));
        }

        /**
        * AJAX dynamic stats handler
        */
        function hkba_dynamic_stats() {
            global $wpdb;
            $data = null;
            $action = (isset($_GET['aq']) && $_GET['aq']) ? $_GET['aq'] : '';
            //day offset used to set the correct end point
            $day_offset = (24 * 60 * 60);

            $wp_time_format = (get_option( 'date_format' )) ? get_option( 'date_format' ) : 'F j, Y';

            $begin = (isset($_GET['begin']) && $_GET['begin']) ? $_GET['begin'] : '';

            //santize the period begin timestamp
            if(empty($begin)){
                $begin_timestamp = time();
                $begin_sql = date('Y-m-d');
            } else {
                $begin_timestamp = (int) $begin;
                $begin_sql = date('Y-m-d', $begin_timestamp);
            }
            //begining user date format
            $begin_user_format = date_i18n( $wp_time_format, $begin_timestamp );

            //santize the end period timestamp
            $end = (isset($_GET['end']) && $_GET['end']) ? $_GET['end'] : '';
            if(empty($end)){
                $end_timestamp = time();
                $end_sql = date('Y-m-d', time() + $day_offset);
            } else {
                $end_timestamp = (int) $end;
                $end_sql = date('Y-m-d', $end_timestamp + $day_offset);
            }
            //end user date format
            $end_user_format = date_i18n( $wp_time_format, $end_timestamp );       

            //switch on action
            switch ($action) {
                case 'updateusermetadates':
                    check_ajax_referer('updateUserMetaDates','nonce');
                    $user_ID = get_current_user_id();
                    //update meta
                    update_user_meta( $user_ID, HT_KB_ANALYTICS_BEGIN_DATE_META_KEY, $begin_timestamp );
                    update_user_meta( $user_ID, HT_KB_ANALYTICS_END_DATE_META_KEY, $end_timestamp );
                    $data['response'] = true;
                    break;

                case 'kbviewsmonthly':
                    check_ajax_referer('monthlyViewsChart','nonce');

                    $monthly_kb_views_query = "SELECT count(*) as count, DATE_FORMAT(datetime,'%M') as month, YEAR(datetime) as year 
                                                FROM {$wpdb->prefix}hkb_visits
                                                WHERE datetime > '{$begin_sql}' AND datetime < '{$end_sql}'
                                                GROUP BY month, year 
                                                ORDER BY datetime
                                                ";
                    //monthly searches
                    $stats = $wpdb->get_results($monthly_kb_views_query);
                    $labels = array();
                    $values = array();
                    foreach($stats as $stat) {
                      array_push($labels, $stat->month);
                      array_push($values, $stat->count);
                    }

                    $data = array('labels'=>$labels, 'values'=>$values);
                    break;


                case 'newarticlescount':
                    check_ajax_referer('newArticleStats','nonce');
                    
                    $begin_query = "SELECT COUNT(*) as beginTotal FROM {$wpdb->prefix}posts
                                          WHERE post_date < '{$begin_sql}' AND post_type = 'ht_kb' 
                                          ORDER BY post_date";
                    $begin_stats = $wpdb->get_results($begin_query);
                    $begin_count = $begin_stats[0]->beginTotal;
                    $data['begin_count'] = $begin_count;

                    $end_query = "SELECT COUNT(*) as endTotal FROM {$wpdb->prefix}posts
                                          WHERE post_date < '{$end_sql}'  AND post_type = 'ht_kb' 
                                          ORDER BY post_date";
                    $end_stats = $wpdb->get_results($end_query);
                    $end_count = $end_stats[0]->endTotal;
                    $data['end_count'] = $end_count;

                    $delta = $end_count-$begin_count;
                    $data['count'] = $delta;
                    $data['label'] = sprintf(__('Articles published between %s and %s', 'ht-knowledge-base'), $begin_user_format, $end_user_format);
                    break;


                case 'totalarticles':
                    check_ajax_referer('totalArticlesStats','nonce');
                    $total_articles_query = "SELECT COUNT(*) as articleTotal FROM {$wpdb->prefix}posts
                                          WHERE post_date < '{$end_sql}' AND post_type = 'ht_kb' 
                                          ORDER BY post_date";
                    $article_stats = $wpdb->get_results($total_articles_query);
                    $article_total = $article_stats[0]->articleTotal;
                    $data['count'] = $article_total;
                    $data['label'] = sprintf(__('Articles published before %s', 'ht-knowledge-base'), $end_user_format);
                    break;

                case 'articlesperiod':
                    check_ajax_referer('articlesPeriodStats','nonce');
                    $articles_in_period = "SELECT COUNT(*) as articleTotal FROM {$wpdb->prefix}posts
                                          WHERE post_date > '{$begin_sql}'  AND post_date < '{$end_sql}'  AND post_type = 'ht_kb' 
                                          ORDER BY post_date";
                    $article_stats = $wpdb->get_results($articles_in_period);
                    $article_period = $article_stats[0]->articleTotal;
                    $data['count'] = $article_period;
                    $data['label'] = sprintf(__('Articles published in this period between %s and %s', 'ht-knowledge-base'), $begin_user_format, $end_user_format);
                    break;

                case 'articlevisits':
                    check_ajax_referer('articleViewsStats','nonce');
                    
                    $total_visits_query = "SELECT
                                              COUNT(*) as totalVisits
                                             FROM {$wpdb->prefix}hkb_visits
                                             WHERE datetime > '{$begin_sql}' AND datetime < '{$end_sql}'
                                             AND object_type = 'ht_kb_article'
                                            ";
                    
                    //Article views
                    $stats = $wpdb->get_results($total_visits_query);
                    $data['count'] = $stats[0]->totalVisits;
                    $data['label'] = sprintf(__('Article views between %s and %s', 'ht-knowledge-base'), $begin_user_format, $end_user_format);
                    break;

                case 'feedbackoverview':
                    check_ajax_referer('feedbackOverview','nonce');
                    $feedback_responses_query = "SELECT COUNT(*) AS totalResponses,
                                                SUM(CASE WHEN {$wpdb->prefix}" . HT_VOTING_TABLE  .".magnitude > 0 THEN 1 ELSE 0 END) AS totalUp,
                                                SUM(CASE WHEN {$wpdb->prefix}" . HT_VOTING_TABLE  .".magnitude = 0 THEN 1 ELSE 0 END) AS totalDown
                                                FROM {$wpdb->prefix}" . HT_VOTING_TABLE  .
                                                " WHERE datetime > '{$begin_sql}' AND datetime < '{$end_sql}'
                                                ";
                    //feedback overview
                    $stats = $wpdb->get_results($feedback_responses_query);
                    $data = $stats[0];

                    //hard set the variables
                    $data->totalUp = (isset($data->totalUp)) ? $data->totalUp : '-';
                    $data->totalDown = (isset($data->totalDown)) ? $data->totalDown : '-';

                    $data->feedbackArticleSuccess = ((int)$data->totalResponses > 0) ? round( ( (int)$data->totalUp / (int)$data->totalResponses )*100 ) : 100;
                    break;

                case 'searchesoverview':
                    check_ajax_referer('searchesOverview','nonce');

                    $total_searches_query = "SELECT
                                             COUNT(*) as totalSearches,
                                             SUM(CASE WHEN {$wpdb->prefix}hkb_analytics_search_atomic.hits > 0 THEN 1 ELSE 0 END) AS totalSuccess,
                                             SUM(CASE WHEN {$wpdb->prefix}hkb_analytics_search_atomic.hits = 0 THEN 1 ELSE 0 END) AS totalNull
                                             FROM {$wpdb->prefix}hkb_analytics_search_atomic
                                             WHERE datetime > '{$begin_sql}' AND datetime < '{$end_sql}'
                                            ";
                    //total number of searches
                    $stats = $wpdb->get_results($total_searches_query);

                    $data = $stats[0];

                    //hard set the variables
                    $data->totalSuccess = (isset($data->totalSuccess)) ? $data->totalSuccess : '-';
                    $data->totalNull = (isset($data->totalNull)) ? $data->totalNull : '-';

                    $data->feedbackSuccess = ((int)$data->totalSearches > 0) ? round( ( (int)$data->totalSuccess / (int)$data->totalSearches )*100 ) : 100;
                    
                    break;

                case 'newarticles':
                    check_ajax_referer('articleCount','nonce');
                    
                    $begin_query = "SELECT COUNT(*) as beginTotal FROM {$wpdb->prefix}posts
                                          WHERE post_date < '{$begin_sql}' AND post_type = 'ht_kb' 
                                          ORDER BY post_date";
                    //begining total
                    $begin_stats = $wpdb->get_results($begin_query);
                    $begin_count = $begin_stats[0]->beginTotal;
                    $data['begin_count'] = $begin_count;

                    $end_query = "SELECT COUNT(*) as endTotal FROM {$wpdb->prefix}posts
                                          WHERE post_date < '{$end_sql}'  AND post_type = 'ht_kb' 
                                          ORDER BY post_date";
                    //end total
                    $end_stats = $wpdb->get_results($end_query);
                    $end_count = $end_stats[0]->endTotal;
                    $data['end_count'] = $end_count;

                    $delta = $end_count-$begin_count;
                    $data['delta'] = $delta;
                    $delta_abs = abs($delta);
                    $data['delta_abs'] = $delta_abs;
                    $delta_direction = ($delta < 0) ? __('down', 'ht-knowledge-base', 'ht-knowledge-base') : __('up', 'ht-knowledge-base', 'ht-knowledge-base');
                    $data['delta_direction'] = $delta_direction;
                    $percentage_diff = ($begin_count>0) ? $delta_abs / $begin_count: 0;
                    $data['percentage_diff'] = number_format($percentage_diff*100, 1);                
                    break;

                case 'totalsearches':
                    check_ajax_referer('searchDonut','nonce');

                    $total_searches_query = "SELECT datetime,
                                              SUM(CASE WHEN {$wpdb->prefix}hkb_analytics_search_atomic.hits > 0 THEN 1 ELSE 0 END) AS totalPopulated,
                                              SUM(CASE WHEN {$wpdb->prefix}hkb_analytics_search_atomic.hits = 0 THEN 1 ELSE 0 END) AS totalNULL,
                                              COUNT(*) as totalSearches
                                             FROM {$wpdb->prefix}hkb_analytics_search_atomic
                                             WHERE datetime > '{$begin_sql}' AND datetime < '{$end_sql}'
                                            ";
                    //total number of searches
                    $stats = $wpdb->get_results($total_searches_query);

                    $data = $stats[0];
                    break;

                case 'articleviewsdetail':
                    check_ajax_referer('articleViewsDetail','nonce');
                   
                    $article_views_query = "SELECT *
                                                FROM {$wpdb->prefix}hkb_visits
                                                WHERE datetime > '{$begin_sql}' AND datetime < '{$end_sql}'
                                                AND object_type = 'ht_kb_article'
                                                ORDER BY datetime";
                    $stats = $wpdb->get_results($article_views_query);
                    $rows = array();
                    foreach($stats as $stat) {
                        $row = array();
                        //article
                        $id = $stat->object_id;
                        $article_column = '' . get_the_title($id) . ' ' . sprintf('(<a href="%s">%s</a>)', get_permalink($id), __('View', 'ht-knowledge-base', 'ht-knowledge-base')) . ' ' . sprintf('(<a href="%s">%s</a>)', get_edit_post_link($id), __('Edit', 'ht-knowledge-base', 'ht-knowledge-base'));
                        array_push($row, $article_column);
                        //user
                        $user_id = $stat->user_id;
                        if($user_id>0){
                            //link to user
                            $user_info = get_userdata( $user_id );
                            $user_ip_column =  sprintf( '<a href="%s">%s</a>', get_edit_user_link($user_id), $user_info->user_nicename );
                        } else {
                            //ip
                            $user_ip_column = $stat->user_ip;
                        
                        }
                        array_push($row, $user_ip_column);
                        //duration
                        array_push($row, $stat->duration);
                        array_push($rows, $row);
                    }

                    $data['data'] = $rows;
                    break;

                case 'searchmonthly':
                    check_ajax_referer('monthlySearchesChart','nonce');

                    $monthly_searches_query = "SELECT count(terms) as count, terms, DATE_FORMAT(datetime,'%M') as month, YEAR(datetime) as year 
                                                FROM {$wpdb->prefix}hkb_analytics_search_atomic
                                                WHERE datetime > '{$begin_sql}' AND datetime < '{$end_sql}'
                                                GROUP BY month, year 
                                                ORDER BY datetime
                                                ";
                    //monthly searches
                    $stats = $wpdb->get_results($monthly_searches_query);
                    $labels = array();
                    $values = array();
                    foreach($stats as $stat) {
                      array_push($labels, $stat->month);
                      array_push($values, $stat->count);
                    }

                    $data = array('labels'=>$labels, 'values'=>$values);
                    break;

                case 'nullsearches':
                    check_ajax_referer('nullSearches','nonce');

                    $null_searches_query = "SELECT *, COUNT(*) as count 
                                            FROM {$wpdb->prefix}hkb_analytics_search_atomic 
                                            WHERE terms != '' AND hits=0 AND datetime > '{$begin_sql}' AND datetime < '{$end_sql}'
                                            GROUP BY terms 
                                            ORDER BY count 
                                            DESC LIMIT 100
                                            ";
                    //null searches
                    $top_null_results = $wpdb->get_results($null_searches_query);
                    $rows = array();
                    foreach($top_null_results as $stat) {
                        $row = array();
                        array_push($row, $stat->terms);
                        array_push($row, $stat->count);
                        array_push($rows, $row);
                    }

                    $data['data'] = $rows;
                    break;

                case 'topsearches':
                    check_ajax_referer('topSearches','nonce');

                    $top_searches_query = "SELECT *, COUNT(*) as count 
                                            FROM {$wpdb->prefix}hkb_analytics_search_atomic  
                                            WHERE datetime > '{$begin_sql}' AND datetime < '{$end_sql}'
                                            GROUP BY terms 
                                            ORDER BY count 
                                            DESC LIMIT 100
                                            ";
                    //top searches
                    $top_searches_results = $wpdb->get_results($top_searches_query);
                    $rows = array();
                    foreach($top_searches_results as $stat) {
                        $row = array();
                        array_push($row, $stat->terms);
                        array_push($row, $stat->count);
                        array_push($rows, $row);
                    }
                    $data['data'] = $rows;
                    break;


                case 'feedbackresponses':
                    check_ajax_referer('feedbackResponses','nonce');
                    $feedback_responses_query = "SELECT COUNT(*) AS totalResponses,
                                                SUM(CASE WHEN {$wpdb->prefix}" . HT_VOTING_TABLE  .".magnitude > 0 THEN 1 ELSE 0 END) AS totalUp,
                                                SUM(CASE WHEN {$wpdb->prefix}" . HT_VOTING_TABLE  .".magnitude = 0 THEN 1 ELSE 0 END) AS totalDown
                                                FROM {$wpdb->prefix}" . HT_VOTING_TABLE  .
                                                " WHERE datetime > '{$begin_sql}' AND datetime < '{$end_sql}'
                                                ";
                    //feedback responses
                    $stats = $wpdb->get_results($feedback_responses_query);
                    $data = $stats[0];

                    $data->feedbackGoodWidth = ((int)$data->totalResponses > 0)  ? round( ( (int)$data->totalUp / (int)$data->totalResponses )*100 ) : 50;
                    $data->feedbackBadWidth = ((int)$data->totalResponses > 0) ? round( ( (int)$data->totalDown / (int)$data->totalResponses )*100 ) : 50;
                    break;

                case 'feedbackcards':
                    check_ajax_referer('feedbackCards','nonce');
                    $feedback_cards_query =    "SELECT *
                                                FROM {$wpdb->prefix}" . HT_VOTING_TABLE  .
                                                " WHERE 1 AND feedback <>  '' 
                                                AND datetime > '{$begin_sql}' AND datetime < '{$end_sql}'
                                                ORDER BY datetime  DESC 
                                                LIMIT 30
                                                ";
                    //recent feedback
                    $feedback_cards_data = $wpdb->get_results($feedback_cards_query);
                    $cards = array();
                    foreach($feedback_cards_data as $vote_row) {
                        $card =  new stdClass();
                        $card->rating = ($vote_row->magnitude > 0 ) ?  'helpful' : 'unhelpful';
                        $article_id = $vote_row->post_id;
                        $card->articleID = $article_id;
                        $article_title = get_the_title($article_id);
                        $article_title = (empty($article_title)) ? __('No Title or Deleted Article', 'ht-knowledge-base', 'ht-knowledge-base') : get_the_title($article_id);
                        $card->articleTitle = $article_title;
                        $card->articleEditUrl = get_edit_post_link($article_id);
                        $feedback_body = $vote_row->feedback;
                        $truncation_limit = 80;
                        $feedback_snippet = substr($feedback_body, 0, $truncation_limit);
                        $card->snippet = $feedback_snippet;
                        $card->fullFeedback = $feedback_body;
                        $card->isTruncated = ($feedback_body!=$feedback_snippet) ? true : false;
                        $card->feedbackID = $vote_row->vote_id;
                        $feedback_author_id = $vote_row->user_id;
                        $card->authorID = $feedback_author_id;
                        $feedback_author = get_userdata( $feedback_author_id);
                        $card->authorImg = get_avatar( $feedback_author_id, 30 );
                        $card->authorName = ($feedback_author) ? $feedback_author->display_name : __('Anonymous', 'ht-knowledge-base', 'ht-knowledge-base');
                        $sql_datetime = $vote_row->datetime;
                        $datetime_object = new DateTime($sql_datetime);
                        $card->datetime = $datetime_object->format( 'M d Y' )  . ' &middot; ' . $datetime_object->format('G:i') ;
                        
                        array_push($cards, $card);
                    }
                    $data['cards'] = $cards;
                    break;

                case 'authorstats':
                    check_ajax_referer('authorStats','nonce');
                    $author_stats_query =    "SELECT posts.post_author, 
                                                COUNT(DISTINCT posts.ID) as articles_published, 
                                                COUNT(*) as articles_with_votes, 
                                                SUM(voting.magnitude) as author_score 
                                                FROM {$wpdb->prefix}posts as posts 
                                                INNER JOIN  {$wpdb->prefix}" . HT_VOTING_TABLE  .
                                                " as voting
                                                ON posts.ID=voting.post_id
                                                WHERE datetime > '{$begin_sql}' AND datetime < '{$end_sql}'
                                                GROUP BY posts.post_author
                                                ";
                    //author stats
                    $author_stats_results = $wpdb->get_results($author_stats_query);
                    $rows = array();
                    foreach($author_stats_results as $author_stats) {
                        $row = array();
                        //link to user
                        $author_id = $author_stats->post_author;
                        $author_info = get_userdata( $author_id );
                        $author_column =  sprintf( '<a href="%s">%s</a>', get_edit_user_link($author_id), $author_info->user_nicename );
                        array_push($row, $author_column);
                        array_push($row, $author_stats->articles_published);
                        array_push($row, $author_stats->author_score);
                        array_push($rows, $row);
                    }
                    $data['data'] = $rows;
                    break;
                //exits tab
                case 'totalviewstats':
                    check_ajax_referer('totalViewsStats','nonce');
                    
                    $total_visits_query = "SELECT
                                              COUNT(*) as totalVisits
                                             FROM {$wpdb->prefix}hkb_visits
                                             WHERE datetime > '{$begin_sql}' AND datetime < '{$end_sql}'
                                            ";
                
                    //total views
                    $stats = $wpdb->get_results($total_visits_query);
                    $data['count'] = $stats[0]->totalVisits;
                    $data['label'] = sprintf(__('Total KB views between %s and %s', 'ht-knowledge-base'), $begin_user_format, $end_user_format);
                    break;
                case 'totalexitsstats':
                    check_ajax_referer('totalExitsStats','nonce');
                    
                    $total_exits_query = "SELECT
                                              COUNT(*) as totalExits
                                             FROM {$wpdb->prefix}hkb_exits
                                             WHERE datetime > '{$begin_sql}' AND datetime < '{$end_sql}'
                                            ";
                    
                    //Exits
                    $stats = $wpdb->get_results($total_exits_query);
                    $data['count'] = $stats[0]->totalExits;
                    $data['label'] = sprintf(__('Total KB exits views between %s and %s', 'ht-knowledge-base'), $begin_user_format, $end_user_format);
                    break;
                case 'exitpercentagesstats':
                    check_ajax_referer('exitPercentageStats','nonce');
                    
                    $total_visits_query = "SELECT
                                              COUNT(*) as totalVisits
                                             FROM {$wpdb->prefix}hkb_visits
                                             WHERE datetime > '{$begin_sql}' AND datetime < '{$end_sql}'
                                            ";
                    $total_exits_query = "SELECT
                                              COUNT(*) as totalExits
                                             FROM {$wpdb->prefix}hkb_exits
                                             WHERE datetime > '{$begin_sql}' AND datetime < '{$end_sql}'
                                            ";
                    
                    //Views
                    $stats1 = $wpdb->get_results($total_visits_query);
                    //Exits
                    $stats2 = $wpdb->get_results($total_exits_query);
                    $total_visits = $stats1[0]->totalVisits;
                    $total_exits = $stats2[0]->totalExits;
                    $exit_percentage = 0;
                    //avoid division by 0
                    if(is_numeric($total_visits)&&$total_visits>0){
                        $exit_percentage = ($total_exits/$total_visits)*100;
                        $exit_percentage = round($exit_percentage, 2);
                    }
                    $data['visits'] = $total_visits;
                    $data['exits'] = $total_exits;
                    $data['count'] = $exit_percentage;
                    $data['label'] = sprintf(__('Total KB exits percentage between %s and %s', 'ht-knowledge-base'), $begin_user_format, $end_user_format);
                    break;
                case 'exitssplit':
                    check_ajax_referer('exitsDonut','nonce');

                    $group_exits_query = " SELECT {$wpdb->prefix}hkb_visits.object_type as objectType, count({$wpdb->prefix}hkb_visits.object_type) AS visits, temp.exits AS exits
                                                    FROM {$wpdb->prefix}hkb_visits
                                                    LEFT JOIN (SELECT object_type, count(*) AS exits FROM {$wpdb->prefix}hkb_exits
                                                     WHERE datetime > '{$begin_sql}' AND datetime < '{$end_sql}'
                                                     GROUP BY object_type ) AS temp
                                                    ON temp.object_type = {$wpdb->prefix}hkb_visits.object_type
                                                    WHERE datetime > '{$begin_sql}' AND datetime < '{$end_sql}'
                                                    AND {$wpdb->prefix}hkb_visits.object_type != 'undefined' 
                                                    GROUP BY {$wpdb->prefix}hkb_visits.object_type
                                                    ORDER BY visits DESC";
                    //exit split
                    $stats = $wpdb->get_results($group_exits_query);
                    $data = $stats;
                    $exit_percentage_total = 0;
                    //var_dump($group_exits_query);
                    foreach ($data as $key => $data_item) {
                        $visits = isset($data_item->visits) ? $data_item->visits : 0;
                        $data_item->visits = $visits;
                        $exits = isset($data_item->exits) ? $data_item->exits : 0;
                        $data_item->exits = $exits;

                        $exit_percentage = 0;
                        //avoid division by 0
                        if(is_numeric($visits)&&$visits>0){
                            $exit_percentage = ($exits/$visits)*100;
                            $exit_percentage = round($exit_percentage, 2);
                            $exit_percentage = sprintf('%0.2f', $exit_percentage);
                        }
                        $data_item->exitPercentage = $exit_percentage; 
                        switch ($data_item->objectType) {
                            case 'ht_kb_archive':
                                $data_item->label = __('Archive Exits', 'ht-knowledge-base');
                                $data_item->color = '#555300';
                                break;
                            case 'ht_kb_article':
                                $data_item->label = __('Article Exits', 'ht-knowledge-base');
                                $data_item->color = '#AA3939';
                                break;
                            case 'ht_kb_category':
                                $data_item->label = __('Category Exits', 'ht-knowledge-base');
                                $data_item->color = '#29526D';
                                break;
                            
                            default:
                                $data_item->label = __('Unclassified Exits', 'ht-knowledge-base');
                                $data_item->color = '#AA8D39';
                                break;
                        }                        
                    }
                    break;

                case 'exitsfromcats':
                    check_ajax_referer('categoryExits','nonce');

                    $transfers_from_cats_query = "SELECT {$wpdb->prefix}hkb_visits.object_type, {$wpdb->prefix}hkb_visits.object_id , count({$wpdb->prefix}hkb_visits.object_id) AS visits, temp.exits AS exits
                                                    FROM {$wpdb->prefix}hkb_visits
                                                    LEFT JOIN (SELECT object_type, object_id, count(*) AS exits FROM {$wpdb->prefix}hkb_exits
                                                     WHERE datetime > '{$begin_sql}' AND datetime < '{$end_sql}'
                                                     GROUP BY object_type, object_id) AS temp
                                                    ON temp.object_type = {$wpdb->prefix}hkb_visits.object_type
                                                    AND temp.object_id = {$wpdb->prefix}hkb_visits.object_id
                                                    WHERE {$wpdb->prefix}hkb_visits.object_type = 'ht_kb_category'
                                                    ANd datetime > '{$begin_sql}' AND datetime < '{$end_sql}'
                                                    GROUP BY {$wpdb->prefix}hkb_visits.object_type, {$wpdb->prefix}hkb_visits.object_id
                                                    ORDER BY visits DESC
                                                    LIMIT 100";
                    //exits from categories
                    $transfers_from_cats = $wpdb->get_results($transfers_from_cats_query);
                    $rows = array();
                    foreach($transfers_from_cats as $stat) {
                        $row = array();
                        $object_id = $stat->object_id;
                        $term_obj = get_term($object_id, 'ht_kb_category');
                        $name = __('Unknown term', 'ht-knowledge-base');
                        if(!is_wp_error($term_obj) && isset($term_obj)){
                            $name = $term_obj->name;
                        }
                        array_push($row, $name);
                        $visits = isset($stat->visits) ? $stat->visits : 0;
                        array_push($row, $visits);
                        $exits = isset($stat->exits) ? $stat->exits : 0;
                        array_push($row, $exits);
                        $exit_percentage = 0;
                        //avoid division by 0
                        if(is_numeric($visits)&&$visits>0){
                            $exit_percentage = ($exits/$visits)*100;
                            $exit_percentage = round($exit_percentage, 2);
                            $exit_percentage = sprintf('%0.2f', $exit_percentage);
                        }
                        array_push($row, $exit_percentage);
                        array_push($rows, $row);
                    }

                    $data['data'] = $rows;
                    break;

                 case 'exitsfromarticles':
                    check_ajax_referer('articleExits','nonce');

                    $transfers_from_articles_query = "SELECT {$wpdb->prefix}hkb_visits.object_type, {$wpdb->prefix}hkb_visits.object_id , count({$wpdb->prefix}hkb_visits.object_id) AS visits, temp.exits AS exits
                                                    FROM {$wpdb->prefix}hkb_visits
                                                    LEFT JOIN (SELECT object_type, object_id, count(*) AS exits FROM {$wpdb->prefix}hkb_exits
                                                     WHERE datetime > '{$begin_sql}' AND datetime < '{$end_sql}'
                                                     GROUP BY object_type, object_id) AS temp
                                                    ON temp.object_type = {$wpdb->prefix}hkb_visits.object_type
                                                    AND temp.object_id = {$wpdb->prefix}hkb_visits.object_id
                                                    WHERE {$wpdb->prefix}hkb_visits.object_type = 'ht_kb_article'
                                                    ANd datetime > '{$begin_sql}' AND datetime < '{$end_sql}'
                                                    GROUP BY {$wpdb->prefix}hkb_visits.object_type, {$wpdb->prefix}hkb_visits.object_id
                                                    ORDER BY visits DESC
                                                    LIMIT 100";
                    //exits from articles
                    $transfers_from_articles = $wpdb->get_results($transfers_from_articles_query);
                    $rows = array();
                    foreach($transfers_from_articles as $stat) {
                        $row = array();
                        $object_id = $stat->object_id;
                        $post_obj = get_post($object_id);
                        //$post_title = __('Deleted article', 'ht-knowledge-base');
                        $post_title = sprintf(__('Deleted article %s', 'ht-knowledge-base'), $object_id);
                        if(!is_wp_error($post_obj) && isset($post_obj)){
                            $post_title = $post_obj->post_title;
                        }
                        array_push($row, $post_title);
                        $visits = isset($stat->visits) ? $stat->visits : 0;
                        array_push($row, $visits);
                        $exits = isset($stat->exits) ? $stat->exits : 0;
                        array_push($row, $exits);
                        $exit_percentage = 0;
                        //avoid division by 0
                        if(is_numeric($visits)&&$visits>0){
                            $exit_percentage = ($exits/$visits)*100;
                            $exit_percentage = round($exit_percentage, 2);
                            $exit_percentage = sprintf('%0.2f', $exit_percentage);
                        }
                        array_push($row, $exit_percentage);
                        array_push($rows, $row);
                    }

                    $data['data'] = $rows;
                    break;

                default:
                    //nothing here
                    break;
            }

            echo json_encode($data);
            die;
            
        } //end get dynamic stats
    }

}

if( class_exists( 'HKB_Dynamic_Stats' )) {
    $ht_hkb_dyn_stats_init = new HKB_Dynamic_Stats();
}