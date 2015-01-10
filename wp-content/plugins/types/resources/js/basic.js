/**
 *
 * Use this file only for scripts needed in full version.
 * Before moving from embedded JS - make sure it's needed only here.
 *
 * $HeadURL: http://plugins.svn.wordpress.org/types/tags/1.6.4/resources/js/basic.js $
 * $LastChangedDate: 2014-11-18 06:47:25 +0000 (Tue, 18 Nov 2014) $
 * $LastChangedRevision: 1027712 $
 * $LastChangedBy: iworks $
 *
 */
jQuery(document).ready(function($){
    $('input[name=file]').on('change', function() {
        if($(this),$(this).val()) {
            $('input[name=import-file]').removeAttr('disabled');
        }
    });
    $('a.current').each( function() {
        if ($(this).attr('href').match(/page=wpcf\-edit(\-(type|usermeta))?/)) {
            $(this).attr('href', window.location.href);
        }
    });
});
