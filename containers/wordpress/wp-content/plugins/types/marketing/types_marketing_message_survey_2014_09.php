<?php
/**
 *
 * Ask Types users for feedback on their work
 * https://icanlocalize.basecamphq.com/projects/7393061-toolset/todo_items/188885189/comments
 *
 * $HeadURL: http://plugins.svn.wordpress.org/types/tags/1.6.4/marketing/types_marketing_message_survey_2014_09.php $
 * $LastChangedDate: 2014-11-18 06:47:25 +0000 (Tue, 18 Nov 2014) $
 * $LastChangedRevision: 1027712 $
 * $LastChangedBy: iworks $
 *
 */

class types_marketing_message_survey_2014_09
{
    private $option_name = 'wpcf-survey-2014-09';

    public function __construct()
    {
        /**
         * get survey status
         */
        $suvery = get_option($this->option_name);
        /**
         * if empty just create a event day
         */
        if ( empty( $suvery ) ) {
            add_option($this->option_name,time()+14*DAY_IN_SECONDS);
            return;
        }
        /**
         * do not show if is dismissed
         */
        if ( 'dismiss' == $suvery ) {
            return;
        }
        /**
         * do propper action
         */
        switch( $suvery ) {
            /**
             * do not show
             */
        case 'dismiss':
            break;
            /**
             * show
             */
        case 'show':
            break;
        default:
            if ( time() - $suvery > 0 ) {
                add_action( 'admin_notices', array($this, 'admin_notice' ) );
                add_action( 'wp_ajax_types_marketing_message_survey_2014_09_action', array($this,'ajax_action') );
            }
        }
    }

    public function admin_notice()
    {
        wp_enqueue_script(
            __FUNCTION__,
            plugin_dir_url(__FILE__).'types_marketing_message_survey_2014_09_admin_notice.js',
            array('jquery')
        );
        wp_localize_script(
            __FUNCTION__,
            'wpcf_survey_2014_09',
            array(
                'dismiss' => __( 'Are you sure you want to skip Types survey?', 'wpcf' ),
                'done' => 'done' == get_option($this->option_name)
            )
        );
        echo '<div class="updated" id="types_marketing_message_survey_2014_09">';
        echo '<p>';
        _e('Types development team is working on major new features. We need your feedback, so that Types can do what you need.', 'wpcf' );
        echo '</p>';
        echo '<p>';
        printf(
            '<a href="https://www.surveymonkey.com/s/i-do-with-types" class="survey button button-primary">%s</a>',
            __('5 Minute Survey', 'wpcf')
        );
        printf( ' &nbsp; <a href="#" class="later">%s</a>', __('Remind me later', 'wpcf'));
        printf( ' | <a href="#" class="dismiss">%s</a>', __('Dismiss', 'wpcf'));
        echo '</p>';
        echo '</div>';
    }

    public function ajax_action()
    {
        switch( $_POST['do'] ) {
        case 'dismiss':
            update_option($this->option_name,'dismiss');
            break;
        case 'later':
            update_option($this->option_name,time()+2*DAY_IN_SECONDS);
            break;
        case 'go':
            update_option($this->option_name,'done');
            break;
        }
        die();
    }
}

