<?php
/**
 *
 * $HeadURL: http://plugins.svn.wordpress.org/types/tags/1.6.4/embedded/common/toolset-forms/classes/class.colorpicker.php $
 * $LastChangedDate: 2014-11-18 06:47:25 +0000 (Tue, 18 Nov 2014) $
 * $LastChangedRevision: 1027712 $
 * $LastChangedBy: iworks $
 *
 */
require_once 'class.field_factory.php';

/**
 * Description of class
 *
 * @author Srdjan
 */
class WPToolset_Field_Colorpicker extends FieldFactory
{
    public function init()
    {
		
		if ( !is_admin() ) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script(
                'iris',
                admin_url( 'js/iris.min.js' ),
                array( 'jquery-ui-draggable', 'jquery-ui-slider', 'jquery-touch-punch' ),
                false,
                1
            );
            wp_enqueue_script(
                'wp-color-picker',
                admin_url( 'js/color-picker.min.js' ),
                array( 'iris' ),
                false,
                1
            );
            $colorpicker_l10n = array(
                'clear' => __( 'Clear' ),
                'defaultString' => __( 'Default', 'wpv-views' ),
                'pick' => __( 'Select Color', 'wpv-views' )
            );
            wp_localize_script( 'wp-color-picker', 'wpColorPickerL10n', $colorpicker_l10n );
        }
		wp_register_script(
            'wptoolset-field-colorpicker',
            WPTOOLSET_FORMS_RELPATH . '/js/colorpicker.js',
            array('iris'),
            WPTOOLSET_FORMS_VERSION,
            true
        );
		wp_enqueue_script( 'wptoolset-field-colorpicker' );
        
	}

    static public function registerScripts()
    {
        
    }

    public function enqueueScripts()
    {
        
    }

    public function metaform()
    {
        $classes = array();
        $classes[] = 'js-wpt-colorpicker';
        $form = array();
        $form['name'] = array(
            '#type' => 'textfield',
            '#title' => $this->getTitle(),
			'#description' => $this->getDescription(),
            '#value' => $this->getValue(),
            '#name' => $this->getName(),
            '#attributes' => array('class' => implode(' ', $classes )),
            '#validate' => $this->getValidationData(),
            '#after' => '',
            '#repetitive' => $this->isRepetitive(),
        );
        return $form;
    }

    public static function filterValidationValue($value)
    {
        if ( isset( $value['datepicker'] ) ) {
            return $value['datepicker'];
        }
        return $value;
    }
}
