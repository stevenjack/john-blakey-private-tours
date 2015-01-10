jQuery( document ).ready(function($) {
    $('#types_marketing_message_survey_2014_09 .survey').bind('click', function() {
        $.post(ajaxurl,{ action: 'types_marketing_message_survey_2014_09_action', do: 'go' }, function() {
            wpcf_survey_2014_09.done = true;
        });
        $(this).attr('target','_new');
        return true;
    });
    $('#types_marketing_message_survey_2014_09 .later').bind('click', function() {
        $.post(ajaxurl,{ action: 'types_marketing_message_survey_2014_09_action', do: 'later' }, function() {
            $('#types_marketing_message_survey_2014_09').hide();
        });
        return false;
    });
    $('#types_marketing_message_survey_2014_09 .dismiss').bind('click', function() {
        if ( wpcf_survey_2014_09.done || confirm( wpcf_survey_2014_09.dismiss ) ) {
            $.post(ajaxurl,{ action: 'types_marketing_message_survey_2014_09_action', do: 'dismiss' }, function() {
                $('#types_marketing_message_survey_2014_09').hide();
            });
        }
        return false;
    });
});

